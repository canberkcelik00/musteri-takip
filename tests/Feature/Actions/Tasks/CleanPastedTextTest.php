<?php

use App\Actions\Tasks\CleanPastedText;

function cleanPastedText(string $text): string
{
    return app(CleanPastedText::class)->handle($text);
}

it('drops both WhatsApp prefix formats', function (string $line) {
    expect(cleanPastedText($line))->toBe('normalde biraz daha acele edecektim ama');
})->with([
    'time first' => '[19:49, 26.10.2026] W Müşteri: normalde biraz daha acele edecektim ama',
    'date first' => '[26.10.2026 19:49] W Müşteri: normalde biraz daha acele edecektim ama',
]);

it('leaves the name out when one person speaks', function () {
    $text = "[19:49, 26.10.2026] W Müşteri: normalde biraz daha acele edecektim ama\n[19:50, 26.10.2026] W Müşteri: dengesini biraz";

    expect(cleanPastedText($text))->toBe("normalde biraz daha acele edecektim ama\ndengesini biraz");
});

it('writes the name where the speaker changes when more than one person speaks', function () {
    $text = "[19:49, 26.10.2026] Ahmet: banner değişsin\n[19:50, 26.10.2026] Ahmet: rengi mavi\n[19:51, 26.10.2026] Ben: tamam\n[19:52, 26.10.2026] Ahmet: teşekkürler";

    expect(cleanPastedText($text))->toBe("Ahmet: banner değişsin\nrengi mavi\nBen: tamam\nAhmet: teşekkürler");
});

it('keeps the lines of a multi-line message under its prefix', function () {
    expect(cleanPastedText("[19:49, 26.10.2026] Ahmet: ilk satır\nikinci satır"))->toBe("ilk satır\nikinci satır");
});

it('trims the lines and squeezes blank lines into one', function () {
    expect(cleanPastedText("\r\n  birinci   \r\n\r\n\r\n\r\nikinci  \n\n"))->toBe("birinci\n\nikinci");
});

it('keeps a text without prefixes as it is', function () {
    expect(cleanPastedText("Ana sayfa: banner\n[not] köşeli parantez"))->toBe("Ana sayfa: banner\n[not] köşeli parantez");
});

it('cuts the text at 5000 characters', function () {
    expect(mb_strlen(cleanPastedText(str_repeat('a', 6000))))->toBe(5000);
});
