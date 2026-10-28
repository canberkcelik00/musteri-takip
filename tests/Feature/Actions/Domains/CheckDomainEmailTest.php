<?php

use App\Actions\Domains\CheckDomainEmail;
use App\Enums\DnsRecordType;
use App\Enums\EmailCheckStatus;
use App\Livewire\Windows\CheckDomain;
use App\Models\User;
use App\Support\Dns\DnsLookup;
use Livewire\Livewire;

/**
 * Binds a DNS lookup that answers TXT questions per host name (missing = no record, null = PHP's `false`, a name
 * that does not exist or a failed lookup). The name servers answer unless `$dnsWorks` is false.
 *
 * @param  array<string, list<string>|null>  $txt
 */
function fakeMailTxt(array $txt, bool $dnsWorks = true): DnsLookup
{
    $fake = new class($txt, $dnsWorks) extends DnsLookup
    {
        /** @var list<string> */
        public array $asked = [];

        /** @param array<string, list<string>|null> $txt */
        public function __construct(private readonly array $txt, private readonly bool $dnsWorks) {}

        public function records(string $host, DnsRecordType $type): ?array
        {
            $this->asked[] = "{$host} {$type->value}";

            if ($type === DnsRecordType::Ns) {
                return $this->dnsWorks ? ['ns1.example.net'] : null;
            }

            return array_key_exists($host, $this->txt) ? $this->txt[$host] : [];
        }
    };

    app()->instance(DnsLookup::class, $fake);

    return $fake;
}

/**
 * @return array{status: EmailCheckStatus, record: string|null, reason: string|null}
 */
function mailCheck(string $record, array $txt, bool $dnsWorks = true): array
{
    fakeMailTxt($txt, $dnsWorks);

    return app(CheckDomainEmail::class)->handle('logi.com.tr')[$record];
}

it('asks the three names and nothing else', function () {
    $fake = fakeMailTxt([]);

    app(CheckDomainEmail::class)->handle(' Logi.com.TR. ');

    expect($fake->asked)->toBe([
        'logi.com.tr txt',
        'default._domainkey.logi.com.tr txt',
        '_dmarc.logi.com.tr txt',
    ]);
});

it('refuses an invalid host', function () {
    fakeMailTxt([]);

    expect(app(CheckDomainEmail::class)->handle('not a domain'))->toBeNull();
});

it('reports every state of the SPF record', function (?array $txt, EmailCheckStatus $status, ?string $reason) {
    $row = mailCheck('spf', ['logi.com.tr' => $txt]);

    expect($row['status'])->toBe($status)->and($row['reason'])->toBe($reason);
})->with([
    'fine' => [['google-site-verification=x', 'v=spf1 +a +mx ~all'], EmailCheckStatus::Ok, null],
    'redirect' => [['v=spf1 redirect=_spf.example.com'], EmailCheckStatus::Ok, null],
    'missing' => [['google-site-verification=x'], EmailCheckStatus::Missing, 'missing'],
    'two records' => [['v=spf1 a ~all', 'v=spf1 mx -all'], EmailCheckStatus::Invalid, 'multiple'],
    'open to everyone' => [['v=spf1 +all'], EmailCheckStatus::Invalid, 'open'],
    'bare all' => [['v=spf1 a all'], EmailCheckStatus::Invalid, 'open'],
    'no all' => [['v=spf1 a mx'], EmailCheckStatus::Invalid, 'no_all'],
    'unreadable' => [null, EmailCheckStatus::Unreadable, null],
]);

it('reports every state of the DKIM record', function (?array $txt, EmailCheckStatus $status, ?string $reason) {
    $row = mailCheck('dkim', ['default._domainkey.logi.com.tr' => $txt]);

    expect($row['status'])->toBe($status)->and($row['reason'])->toBe($reason);
})->with([
    'fine' => [['v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3'], EmailCheckStatus::Ok, null],
    'no version tag' => [['k=rsa; p=MIGfMA0GCSqGSIb3'], EmailCheckStatus::Ok, null],
    'missing' => [[], EmailCheckStatus::Missing, 'missing'],
    'unrelated text' => [['hello'], EmailCheckStatus::Missing, 'missing'],
    'revoked key' => [['v=DKIM1; k=rsa; p='], EmailCheckStatus::Invalid, 'empty_key'],
    'name does not exist' => [null, EmailCheckStatus::Missing, 'missing'],
]);

it('reports every state of the DMARC record', function (?array $txt, EmailCheckStatus $status, ?string $reason) {
    $row = mailCheck('dmarc', ['_dmarc.logi.com.tr' => $txt]);

    expect($row['status'])->toBe($status)->and($row['reason'])->toBe($reason);
})->with([
    'reject' => [['v=DMARC1; p=reject; rua=mailto:a@logi.com.tr'], EmailCheckStatus::Ok, null],
    'quarantine' => [['v=DMARC1; p=quarantine'], EmailCheckStatus::Ok, null],
    'monitoring only' => [['v=DMARC1; p=none'], EmailCheckStatus::Ok, 'monitoring'],
    'missing' => [[], EmailCheckStatus::Missing, 'missing'],
    'two records' => [['v=DMARC1; p=none', 'v=DMARC1; p=reject'], EmailCheckStatus::Invalid, 'multiple'],
    'no policy' => [['v=DMARC1; rua=mailto:a@logi.com.tr'], EmailCheckStatus::Invalid, 'no_policy'],
    'bad policy' => [['v=DMARC1; p=banana'], EmailCheckStatus::Invalid, 'bad_policy'],
    'name does not exist' => [null, EmailCheckStatus::Missing, 'missing'],
]);

it('calls a name unreadable only when the name servers do not answer either', function () {
    $txt = [
        'logi.com.tr' => ['v=spf1 a ~all'],
        'default._domainkey.logi.com.tr' => null,
        '_dmarc.logi.com.tr' => null,
    ];

    expect(mailCheck('dkim', $txt, dnsWorks: false)['status'])->toBe(EmailCheckStatus::Unreadable)
        ->and(mailCheck('dmarc', $txt, dnsWorks: false)['status'])->toBe(EmailCheckStatus::Unreadable)
        ->and(mailCheck('spf', $txt, dnsWorks: false)['status'])->toBe(EmailCheckStatus::Ok);
});

it('does not mistake another tag for the DMARC policy', function () {
    $row = mailCheck('dmarc', ['_dmarc.logi.com.tr' => ['v=DMARC1; sp=reject']]);

    expect($row['reason'])->toBe('no_policy');
});

it('reads the e-posta tab once it is opened and shows the fix sentences', function () {
    $this->actingAs(User::factory()->admin()->create());
    $fake = fakeMailTxt([
        'logi.com.tr' => ['v=spf1 a'],
        '_dmarc.logi.com.tr' => ['v=DMARC1; p=none'],
        'default._domainkey.logi.com.tr' => null,
    ]);

    $window = Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
        ->assertSet('mailLoaded', false)
        ->assertSee(__('domain_check.loading'));

    expect($fake->asked)->toBe([]);

    $window->call('loadMail')
        ->assertSet('mailLoaded', true)
        ->assertSee('v=spf1 a')
        ->assertSee(__('domain_check.mail.spf.no_all'))
        ->assertSee(__('domain_check.mail.dmarc.monitoring'))
        ->assertSee(__('domain_check.mail.dkim.missing'));

    $window->call('loadMail');

    expect($fake->asked)->toHaveCount(4);
});

it('asks the e-posta records again on refresh once they were read', function () {
    $this->actingAs(User::factory()->admin()->create());
    $fake = fakeMailTxt([]);

    Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
        ->call('loadMail')
        ->call('refresh');

    expect(collect($fake->asked)->filter(fn (string $asked): bool => str_contains($asked, '_dmarc')))->toHaveCount(2);
});

it('lets a developer open the e-posta tab', function () {
    $this->actingAs(User::factory()->developer()->create());
    fakeMailTxt([]);

    Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
        ->call('loadMail')
        ->assertSet('mailLoaded', true)
        ->assertSee(__('domain_check.mail.spf.missing'));
});
