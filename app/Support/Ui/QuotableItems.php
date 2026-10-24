<?php

namespace App\Support\Ui;

use App\Models\CatalogService;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a quote line can be: a catalog service or a package that is not archived. A select option key is
 * `catalog_service:ID` or `package:ID`.
 */
final class QuotableItems
{
    /**
     * Catalog services and packages in two groups, by name.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        return array_filter([
            __('quotes.group_services') => self::optionsFor(CatalogService::query()->notArchived(), 'catalog_service'),
            __('quotes.group_packages') => self::optionsFor(Package::query()->notArchived(), 'package'),
        ]);
    }

    public static function resolve(mixed $key): CatalogService|Package|null
    {
        [$kind, $id] = array_pad(explode(':', (string) $key, 2), 2, null);

        return match ($kind) {
            'catalog_service' => CatalogService::query()->find($id),
            'package' => Package::query()->find($id),
            default => null,
        };
    }

    public static function keyOf(CatalogService|Package $quoted): string
    {
        return ($quoted instanceof Package ? 'package:' : 'catalog_service:').$quoted->id;
    }

    /**
     * @param  Builder<CatalogService>|Builder<Package>  $query
     * @return array<string, string>
     */
    private static function optionsFor(Builder $query, string $kind): array
    {
        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CatalogService|Package $record): array => ["{$kind}:{$record->id}" => $record->name])
            ->all();
    }
}
