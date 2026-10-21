<?php

namespace App\Actions\Servers;

use App\Enums\UsageLevel;
use App\Models\ServerUsage;
use App\Support\ByteSize;
use App\Support\Settings;

/**
 * The one-line summary of a reseller's totals for the "Sunucular" screen (docs/UI.md "Kullanım
 * penceresi"), built from stored data only: "Bayi: Disk 38 GB / 50 GB · Trafik 120 GB / sınırsız ·
 * 24 / 30 hesap". A limit that could not be read is left out (the total stands alone). `level` is
 * the worst level among the resources, by the same threshold and rules as the warnings.
 */
class BuildResellerSummary
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{text: string, level: UsageLevel|null}|null null when nothing was read yet
     */
    public function handle(ServerUsage $serverUsage): ?array
    {
        $resources = $serverUsage->resources;

        if ($resources === null || $resources === []) {
            return null;
        }

        $threshold = $this->settings->usageWarningPercent();
        $parts = [];
        $worst = null;

        foreach ($resources as $resource) {
            $level = $resource['limit_read'] ? UsageLevel::of($resource['used'], $resource['limit'], $threshold) : null;

            if ($level !== null && ($worst === null || $level->severity() > $worst->severity())) {
                $worst = $level;
            }

            $parts[] = __('usage.'.$resource['id'], ['value' => $this->value($resource)]);
        }

        return ['text' => __('usage.reseller_summary', ['parts' => implode(' · ', $parts)]), 'level' => $worst];
    }

    /**
     * "38 GB / 50 GB" or "120 GB / sınırsız" when the limit is known, the plain total when not.
     * An unlimited account count is not worth saying.
     *
     * @param  array{id: string, label: string, used: int, limit: int|null, is_bytes: bool, limit_read: bool}  $resource
     */
    private function value(array $resource): string
    {
        $used = $resource['is_bytes'] ? ByteSize::format($resource['used']) : (string) $resource['used'];

        if (! $resource['limit_read']) {
            return $used;
        }

        if ($resource['limit'] === null) {
            return $resource['is_bytes'] ? $used.' / '.__('usage.unlimited') : $used;
        }

        return $used.' / '.($resource['is_bytes'] ? ByteSize::format($resource['limit']) : $resource['limit']);
    }
}
