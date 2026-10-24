<?php

namespace App\Support\Ui;

use InvalidArgumentException;

/**
 * Builds the Alpine expression that opens a window (`$dispatch('open-window', { name: '…', params: { … } })`) from
 * values the page controls: numbers, booleans and plain identifiers. Anything else is refused, so the result is safe
 * to print inside an HTML attribute without escaping.
 */
final class Js
{
    /**
     * @param  array<string, int|bool|string>  $params
     */
    public static function openWindow(string $name, array $params = []): string
    {
        $parts = [];

        foreach ($params as $key => $value) {
            $parts[] = self::identifier($key).': '.match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_int($value) => (string) $value,
                default => "'".self::identifier($value)."'",
            };
        }

        $params = $parts === [] ? '' : ', params: { '.implode(', ', $parts).' }';

        return "\$dispatch('open-window', { name: '".self::identifier($name)."'{$params} })";
    }

    private static function identifier(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("Cannot put [{$value}] into a window expression.");
        }

        return $value;
    }
}
