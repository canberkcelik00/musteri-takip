<?php

use App\Support\ByteSize;

it('formats byte counts in Turkish with at most one decimal', function (int $bytes, string $expected) {
    expect(ByteSize::format($bytes))->toBe($expected);
})->with([
    'zero' => [0, '0 B'],
    'bytes' => [512, '512 B'],
    'just under a kilobyte' => [1023, '1023 B'],
    'kilobyte' => [1024, '1 KB'],
    'decimal comma' => [1536, '1,5 KB'],
    'gigabytes with a decimal' => [1288490188, '1,2 GB'],
    'whole gigabytes' => [5368709120, '5 GB'],
    'rounds up into the next unit' => [1048575, '1 MB'],
    'terabyte' => [1099511627776, '1 TB'],
    'stays in terabytes' => [2048 * 1099511627776, '2048 TB'],
    'negative is zero' => [-5, '0 B'],
]);
