<?php

namespace App\Support\Web;

use App\Support\Dns\HostName;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Asks a site for its front page (Faz 16). HTTPS is tried first, plain HTTP when no connection
 * can be made; redirects are followed. Unlike the other lookups, a site that does not answer is an
 * answer: it comes back as "down" with the reason, and only a host name that cannot be asked
 * returns null. The certificate is not verified here (the SSL check owns that), so an expired one
 * does not make a site look down.
 */
class HttpProbe
{
    public function probe(string $host): ?ProbeResult
    {
        $host = HostName::normalize($host);

        if ($host === null) {
            return null;
        }

        $reason = null;

        foreach (['https', 'http'] as $scheme) {
            try {
                $response = Http::timeout(10)
                    ->connectTimeout(5)
                    ->withoutVerifying()
                    ->withUserAgent('MusteriTakip-SiteCheck/1.0')
                    ->withOptions(['allow_redirects' => ['max' => 5]])
                    ->get("{$scheme}://{$host}/");
            } catch (ConnectionException|TransferException $exception) {
                $reason = mb_substr($exception->getMessage(), 0, 255);

                continue;
            }

            $status = $response->status();

            return new ProbeResult($status < 500, $status, $status < 500 ? null : "HTTP {$status}");
        }

        return new ProbeResult(false, null, $reason);
    }
}
