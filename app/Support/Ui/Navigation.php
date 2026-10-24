<?php

namespace App\Support\Ui;

use Illuminate\Support\Facades\Route;

/**
 * The sidebar and bottom tab bar entries of the glass interface, resolved from `config('ui.navigation')`:
 * an item links to its `app.*` route once that exists and to the Filament panel route until then.
 */
final class Navigation
{
    /**
     * @return list<array{label: string, icon: string, color: string, url: string, active: bool, tab: ?string}|array{group: string, collapsed: bool, open: bool, items: list<array{label: string, icon: string, color: string, url: string, active: bool, tab: ?string}>}>
     */
    public static function entries(?string $currentUrl = null): array
    {
        $currentUrl ??= request()->url();
        $entries = [];

        /** @var list<array<string, mixed>> $configured */
        $configured = config('ui.navigation', []);

        foreach ($configured as $entry) {
            if (isset($entry['group'])) {
                /** @var list<array<string, mixed>> $children */
                $children = $entry['items'];
                $items = array_map(fn (array $item): array => self::item($item, $currentUrl), $children);
                $active = collect($items)->contains('active', true);

                $entries[] = [
                    'group' => (string) $entry['group'],
                    'collapsed' => (bool) ($entry['collapsed'] ?? false),
                    'open' => ! ($entry['collapsed'] ?? false) || $active,
                    'items' => $items,
                ];

                continue;
            }

            $entries[] = self::item($entry, $currentUrl);
        }

        return $entries;
    }

    /**
     * The items that also appear in the bottom tab bar.
     *
     * @return list<array{label: string, icon: string, color: string, url: string, active: bool, tab: ?string}>
     */
    public static function tabs(?string $currentUrl = null): array
    {
        $tabs = [];

        foreach (self::entries($currentUrl) as $entry) {
            foreach (isset($entry['items']) ? $entry['items'] : [$entry] as $item) {
                if (isset($item['tab'])) {
                    $tabs[] = $item;
                }
            }
        }

        return $tabs;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{label: string, icon: string, color: string, url: string, active: bool, tab: ?string}
     */
    private static function item(array $item, string $currentUrl): array
    {
        $url = route(Route::has((string) $item['route']) ? (string) $item['route'] : (string) $item['fallback']);

        return [
            'label' => (string) $item['label'],
            'icon' => (string) $item['icon'],
            'color' => (string) $item['color'],
            'url' => $url,
            'active' => $currentUrl === $url || str_starts_with($currentUrl, $url.'/'),
            'tab' => isset($item['tab']) ? (string) $item['tab'] : null,
        ];
    }
}
