<?php

namespace App\Support\Ui;

use Illuminate\Support\Facades\Auth;

/**
 * The sidebar and bottom tab bar entries of the glass interface, resolved from `config('ui.navigation')`:
 * an item links to its `app.*` route.
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
                $items = array_map(
                    fn (array $item): array => self::item($item, $currentUrl),
                    array_values(array_filter($children, self::allowed(...))),
                );

                if ($items === []) {
                    continue;
                }

                $active = collect($items)->contains('active', true);

                $entries[] = [
                    'group' => (string) $entry['group'],
                    'collapsed' => (bool) ($entry['collapsed'] ?? false),
                    'open' => ! ($entry['collapsed'] ?? false) || $active,
                    'items' => $items,
                ];

                continue;
            }

            if (self::allowed($entry)) {
                $entries[] = self::item($entry, $currentUrl);
            }
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
     * Whether the signed-in user may see the item: it names no permission, or the user holds it.
     *
     * @param  array<string, mixed>  $item
     */
    private static function allowed(array $item): bool
    {
        return ! isset($item['permission']) || (Auth::user()?->can((string) $item['permission']) ?? false);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{label: string, icon: string, color: string, url: string, active: bool, tab: ?string}
     */
    private static function item(array $item, string $currentUrl): array
    {
        $url = route((string) $item['route']);

        return [
            'label' => (string) $item['label'],
            'icon' => (string) $item['icon'],
            'color' => (string) $item['color'],
            'url' => $url,
            'active' => $currentUrl === $url || (! in_array(parse_url($url, PHP_URL_PATH), [null, '', '/'], true) && str_starts_with($currentUrl, $url.'/')),
            'tab' => isset($item['tab']) ? (string) $item['tab'] : null,
        ];
    }
}
