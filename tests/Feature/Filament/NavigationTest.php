<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->get('/panel'); // boots the panel so navigation groups and items are registered
});

it('orders the menu by frequency: Bugün, Müşteriler, then the grouped items (docs/UI.md §3)', function () {
    $groups = collect(Filament::getNavigation())
        ->map(fn ($group) => [
            'label' => $group->getLabel(),
            'items' => collect($group->getItems())->map(fn ($item) => $item->getLabel())->all(),
        ])
        ->values();

    expect($groups[0]['label'])->toBeNull()
        ->and($groups[0]['items'])->toBe(['Bugün', 'Müşteriler']);

    $collections = $groups->firstWhere('label', 'Tahsilat');
    expect($collections['items'])->toBe(['Açık borçlar', 'Ödemeler']);

    $catalog = $groups->firstWhere('label', 'Katalog');
    expect($catalog['items'])->toBe(['Hizmetler', 'Paketler']);

    $settings = $groups->firstWhere('label', 'Ayarlar');
    expect($settings['items'])->toBe(['Genel', 'Ödeme Yöntemleri']);
});

it('starts the Katalog group collapsed, unlike Tahsilat and Ayarlar', function () {
    $groups = collect(Filament::getNavigationGroups())
        ->keyBy(fn ($group) => $group->getLabel());

    expect($groups->get('Katalog')->isCollapsed())->toBeTrue()
        ->and($groups->get('Tahsilat')->isCollapsed())->toBeFalse()
        ->and($groups->get('Ayarlar')->isCollapsed())->toBeFalse();
});
