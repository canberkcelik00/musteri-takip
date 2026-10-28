<?php

use App\Enums\DnsRecordType;
use App\Support\Dns\DnsLookup;
use App\Support\Dns\HostName;
use App\Support\Dns\RegistrationLookup;
use App\Support\Dns\TlsCertificateLookup;
use App\Support\Web\HttpProbe;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const RDAP_BOOTSTRAP = ['services' => [
    [['com', 'net'], ['http://rdap.example.test/', 'https://rdap.example.test/']],
    [['xyz'], []],
]];

/**
 * Replaces the WHOIS socket with a fixed answer (null = the server could not be reached).
 */
function fakeWhoisAnswer(?string $answer): RegistrationLookup
{
    return new class($answer) extends RegistrationLookup
    {
        public function __construct(private readonly ?string $answer) {}

        protected function queryWhois(string $server, string $query): ?string
        {
            return $this->answer;
        }
    };
}

it('cleans up a host name and rejects what is not one', function (string $input, ?string $expected) {
    expect(HostName::normalize($input))->toBe($expected);
})->with([
    'plain' => ['logi.com.tr', 'logi.com.tr'],
    'case, spaces and trailing dot' => ['  WWW.Logi.COM.tr. ', 'www.logi.com.tr'],
    'unicode' => ['bücher.example.com', 'xn--bcher-kva.example.com'],
    'empty' => ['', null],
    'single label' => ['localhost', null],
    'scheme' => ['https://logi.com.tr', null],
    'path' => ['logi.com.tr/admin', null],
    'port' => ['logi.com.tr:8080', null],
    'address' => ['192.168.1.1', null],
    'space inside' => ['logi .com', null],
    'leading hyphen' => ['-logi.com', null],
    'label too long' => [str_repeat('a', 64).'.com', null],
]);

it('formats DNS rows per record type', function () {
    $lookup = new DnsLookup;

    expect($lookup->format(DnsRecordType::A, [['ip' => '1.2.3.4'], ['ip' => '5.6.7.8']]))->toBe(['1.2.3.4', '5.6.7.8'])
        ->and($lookup->format(DnsRecordType::Aaaa, [['ipv6' => '2001:DB8::1']]))->toBe(['2001:db8::1'])
        ->and($lookup->format(DnsRecordType::Ns, [['target' => 'NS1.Logi.com.']]))->toBe(['ns1.logi.com'])
        ->and($lookup->format(DnsRecordType::Cname, [['target' => 'Target.logi.com']]))->toBe(['target.logi.com'])
        ->and($lookup->format(DnsRecordType::Mx, [['pri' => 20, 'target' => 'b.logi.com'], ['pri' => 10, 'target' => 'A.logi.com.']]))
        ->toBe(['10 a.logi.com', '20 b.logi.com'])
        ->and($lookup->format(DnsRecordType::Txt, [['txt' => 'v=spf1 -all'], ['entries' => ['a', 'b']], ['txt' => '']]))
        ->toBe(['v=spf1 -all', 'ab'])
        ->and($lookup->format(DnsRecordType::A, []))->toBe([]);
});

it('does not ask DNS about an invalid host', function () {
    expect((new DnsLookup)->records('not a host', DnsRecordType::A))->toBeNull();
});

it('reads the expiry and issuer of a parsed certificate', function () {
    $lookup = new TlsCertificateLookup;

    $certificate = $lookup->fromParsed([
        'validTo_time_t' => strtotime('2026-12-31 12:00:00 UTC'),
        'issuer' => ['O' => "Let's Encrypt", 'CN' => 'R11'],
        'subject' => ['CN' => 'logi.com.tr'],
    ]);

    expect($certificate->expiresAt->utc()->toDateTimeString())->toBe('2026-12-31 12:00:00')
        ->and($certificate->issuer)->toBe("Let's Encrypt")
        ->and($certificate->subject)->toBe('logi.com.tr')
        ->and($lookup->fromParsed(['validTo_time_t' => strtotime('2027-01-01 UTC'), 'issuer' => ['CN' => 'Home CA']])->issuer)->toBe('Home CA')
        ->and($lookup->fromParsed(['subject' => ['CN' => 'logi.com.tr']]))->toBeNull();
});

it('does not read a certificate of an invalid host', function () {
    expect((new TlsCertificateLookup)->certificateOf('localhost'))->toBeNull();
});

it('reports a site that answers as up, whatever the page', function (int $status) {
    Http::fake(['https://logi.com.tr/' => Http::response('x', $status)]);

    $result = (new HttpProbe)->probe('Logi.com.tr');

    expect($result->up)->toBeTrue()->and($result->status)->toBe($status)->and($result->reason)->toBeNull();
})->with([200, 301, 403, 404]);

it('reports a server error as down', function () {
    Http::fake(['https://logi.com.tr/' => Http::response('x', 503)]);

    $result = (new HttpProbe)->probe('logi.com.tr');

    expect($result->up)->toBeFalse()->and($result->status)->toBe(503)->and($result->reason)->toBe('HTTP 503');
});

it('falls back to plain HTTP when HTTPS cannot connect', function () {
    Http::fake([
        'https://logi.com.tr/' => fn () => throw new ConnectionException('cURL error 35: SSL connect error'),
        'http://logi.com.tr/' => Http::response('x', 200),
    ]);

    $result = (new HttpProbe)->probe('logi.com.tr');

    expect($result->up)->toBeTrue()->and($result->status)->toBe(200);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://logi.com.tr/');
});

it('reports a site nobody answers for as down with the reason, without throwing', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $result = (new HttpProbe)->probe('logi.com.tr');

    expect($result->up)->toBeFalse()->and($result->status)->toBeNull()->and($result->reason)->toContain('timed out');
});

it('does not probe an invalid host', function () {
    Http::fake();

    expect((new HttpProbe)->probe('http://x'))->toBeNull();
    Http::assertNothingSent();
});

it('parses an RDAP answer', function () {
    $registration = (new RegistrationLookup)->parseRdap([
        'events' => [
            ['eventAction' => 'registration', 'eventDate' => '2015-03-01T10:00:00Z'],
            ['eventAction' => 'expiration', 'eventDate' => '2027-03-01T10:00:00Z'],
        ],
        'entities' => [
            ['roles' => ['registrant'], 'vcardArray' => ['vcard', [['fn', [], 'text', 'Someone']]]],
            ['roles' => ['registrar'], 'vcardArray' => ['vcard', [['version', [], 'text', '4.0'], ['fn', [], 'text', 'Example Registrar, Inc.']]]],
        ],
        'nameservers' => [['ldhName' => 'NS1.Example.NET'], ['ldhName' => 'ns2.example.net.'], ['ldhName' => 'ns1.example.net']],
    ]);

    expect($registration->expiresOn->toDateString())->toBe('2027-03-01')
        ->and($registration->nameservers)->toBe(['ns1.example.net', 'ns2.example.net'])
        ->and($registration->registrar)->toBe('Example Registrar, Inc.')
        ->and((new RegistrationLookup)->parseRdap(['events' => [['eventAction' => 'registration', 'eventDate' => '2015-03-01']]]))->toBeNull()
        ->and((new RegistrationLookup)->parseRdap([]))->toBeNull();
});

it('parses a TRABİS WHOIS answer', function () {
    $answer = <<<'TXT'
** Domain Name: logi.com.tr
Domain Status: Registered
Frozen Status: -

** Registrant:
   Logi Ltd.

** Registrar:
NIC Handle        : ex1-metu
Organization Name : Example Bilişim A.Ş.
Address           : Istanbul

** Domain Servers:
ns1.logi.com.tr

Additional Info:
Created on..............: 2015-Nov-05.
Expires on..............: 2026-Nov-04.
TXT;

    $registration = (new RegistrationLookup)->parseTrWhois($answer);

    expect($registration->expiresOn->toDateString())->toBe('2026-11-04')
        ->and($registration->registrar)->toBe('Example Bilişim A.Ş.')
        ->and($registration->nameservers)->toBe(['ns1.logi.com.tr'])
        ->and((new RegistrationLookup)->parseTrWhois('No match found for "nope.com.tr".'))->toBeNull();
});

it('asks RDAP for a .com domain and keeps the IANA list for a week', function () {
    Http::fake([
        'https://data.iana.org/rdap/dns.json' => Http::response(RDAP_BOOTSTRAP),
        'https://rdap.example.test/domain/*' => Http::response(['events' => [['eventAction' => 'expiration', 'eventDate' => '2027-03-01T00:00:00Z']]]),
    ]);

    $lookup = new RegistrationLookup;

    expect($lookup->lookup('logi.com')->expiresOn->toDateString())->toBe('2027-03-01')
        ->and($lookup->lookup('other.net')->expiresOn->toDateString())->toBe('2027-03-01');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://rdap.example.test/domain/logi.com');
});

it('asks the TRABİS server for a .tr domain and never RDAP', function () {
    Http::fake();

    $registration = fakeWhoisAnswer("** Domain Name: logi.com.tr\nExpires on..............: 2026-Nov-04.\n")->lookup('logi.com.tr');

    expect($registration->expiresOn->toDateString())->toBe('2026-11-04');
    Http::assertNothingSent();
});

it('returns null instead of throwing when a registration cannot be read', function (callable $arrange) {
    $arrange();

    expect((new RegistrationLookup)->lookup('logi.com'))->toBeNull();
})->with([
    'IANA list unreachable' => [fn () => Http::fake(fn () => throw new ConnectionException('down'))],
    'IANA list is an error page' => [fn () => Http::fake(['*' => Http::response('oops', 500)])],
    'RDAP says not found' => [fn () => Http::fake([
        'https://data.iana.org/rdap/dns.json' => Http::response(RDAP_BOOTSTRAP),
        'https://rdap.example.test/*' => Http::response(['errorCode' => 404], 404),
    ])],
    'RDAP times out' => [fn () => Http::fake([
        'https://data.iana.org/rdap/dns.json' => Http::response(RDAP_BOOTSTRAP),
        'https://rdap.example.test/*' => fn () => throw new ConnectionException('timed out'),
    ])],
]);

it('returns null for an extension without an RDAP service, an invalid domain, or a silent WHOIS server', function () {
    Http::fake(['https://data.iana.org/rdap/dns.json' => Http::response(RDAP_BOOTSTRAP)]);

    expect((new RegistrationLookup)->lookup('logi.xyz'))->toBeNull()
        ->and((new RegistrationLookup)->lookup('logi.unknown'))->toBeNull()
        ->and((new RegistrationLookup)->lookup('not a domain'))->toBeNull()
        ->and(fakeWhoisAnswer(null)->lookup('logi.com.tr'))->toBeNull();
});
