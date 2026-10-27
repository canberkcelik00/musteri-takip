<?php

use App\Rules\PcloudUrl;

it('accepts https addresses on pcloud.com and pcloud.link', function (string $url) {
    expect(PcloudUrl::isValid($url))->toBeTrue();
})->with([
    'https://u.pcloud.link/publink/show?code=XZabc',
    'https://e1.pcloud.link/publink/show?code=XZabc',
    'https://my.pcloud.com/#page=filemanager&folder=1',
    'https://pcloud.com/',
    'HTTPS://U.PCLOUD.LINK/x',
]);

it('rejects other schemes, look-alike hosts and plain text', function (string $url) {
    expect(PcloudUrl::isValid($url))->toBeFalse();
})->with([
    'http://u.pcloud.link/x',
    'https://evilpcloud.com/x',
    'https://pcloud.com.evil.io/x',
    'https://drive.google.com/drive/folders/1',
    'https://user:pass@pcloud.com/x',
    'javascript:alert(1)',
    'pcloud.com',
    'klasör',
]);
