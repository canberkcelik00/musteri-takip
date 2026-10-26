<?php

use App\Actions\Payments\RecordPayment;
use App\Enums\Currency;
use App\Livewire\Pages\Charges\Index as ChargesIndex;
use App\Livewire\Pages\Payments\Index as PaymentsIndex;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * Pay part of a charge so that it has an allocation.
 */
function uiPayPartOf(Charge $charge, int $amount): void
{
    app(RecordPayment::class)->handle($charge->customer, [
        'amount' => $amount,
        'currency' => $charge->currency,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->firstOrFail()->id,
    ], [$charge->id => $amount]);
}

/**
 * The descriptions in the order they appear in the HTML.
 *
 * @param  list<string>  $values
 */
function uiAppearsInOrder(string $html, array $values): bool
{
    $positions = array_map(fn (string $value) => strpos($html, $value), $values);

    return ! in_array(false, $positions, true) && $positions === collect($positions)->sort()->values()->all();
}

// --- Açık borçlar ---------------------------------------------------------

describe('charge list', function () {
    it('is reachable from the sidebar and needs a signed-in user', function () {
        $this->get(route('app.charges.index'))->assertOk()->assertSee(__('navigation.open_charges'));

        auth()->logout();

        $this->get(route('app.charges.index'))->assertRedirect(route('app.login'));
    });

    it('lists the open charges of all customers, oldest due date first', function () {
        Charge::factory()->create(['description' => 'Sonraki borç', 'due_date' => '2026-07-01']);
        Charge::factory()->create(['description' => 'Önceki borç', 'due_date' => '2026-05-01']);
        Charge::factory()->paid()->create(['description' => 'Ödenmiş borç']);
        Charge::factory()->cancelled()->create(['description' => 'İptal borç']);

        $html = Livewire::test(ChargesIndex::class)->assertDontSee('Ödenmiş borç')->assertDontSee('İptal borç')->html();

        expect(uiAppearsInOrder($html, ['Önceki borç', 'Sonraki borç']))->toBeTrue();
    });

    it('shows the status with the other statuses on request', function () {
        Charge::factory()->paid()->create(['description' => 'Ödenmiş borç']);
        Charge::factory()->cancelled()->create(['description' => 'İptal borç']);
        Charge::factory()->create(['description' => 'Açık kalem']);

        Livewire::test(ChargesIndex::class)
            ->set('status', 'paid')->assertSee('Ödenmiş borç')->assertDontSee('Açık kalem')
            ->set('status', 'cancelled')->assertSee('İptal borç')->assertDontSee('Ödenmiş borç')
            ->set('status', 'all')->assertSee('Açık kalem')->assertSee('Ödenmiş borç')->assertSee('İptal borç');
    });

    it('marks an open charge due within 7 days as due soon in amber, an overdue one in red and a farther one plain', function () {
        Charge::factory()->create(['description' => 'Yakın', 'due_date' => '2026-06-22']);
        Charge::factory()->create(['description' => 'Geçmiş', 'due_date' => '2026-06-01']);
        Charge::factory()->create(['description' => 'Uzak', 'due_date' => '2026-07-10']);

        $html = Livewire::test(ChargesIndex::class)->html();

        expect($html)
            ->toContain(__('charges.due_soon'))
            ->toContain('ui-badge ui-badge-amber')
            ->toContain('ui-badge ui-badge-red')
            ->toContain(__('charges.overdue_days', ['count' => 14]))
            ->toMatch('/>\s*'.__('enums.charge_status.open').'\s*</');
    });

    it('writes the original amount under a partly paid charge with the right Turkish suffix, and only there', function () {
        $charge = Charge::factory()->create(['amount' => 400000, 'due_date' => '2026-07-10']);
        $eur = Charge::factory()->create(['amount' => 5000, 'currency' => Currency::Eur, 'due_date' => '2026-07-10']);

        expect(Livewire::test(ChargesIndex::class)->html())->not->toContain("'den")->not->toContain('&#039;den');

        uiPayPartOf($charge, 100000);
        uiPayPartOf($eur, 1000);

        Livewire::test(ChargesIndex::class)
            ->assertSee("4.000 TL'den")
            ->assertSee("50 EUR'dan");
    });

    it('filters to overdue charges only', function () {
        Charge::factory()->create(['description' => 'Gecikmiş borç', 'due_date' => '2026-06-01']);
        Charge::factory()->create(['description' => 'Vadesi uzak', 'due_date' => '2026-07-10']);

        Livewire::test(ChargesIndex::class)
            ->call('toggle', 'onlyOverdue')
            ->assertSet('onlyOverdue', true)
            ->assertSee('Gecikmiş borç')
            ->assertDontSee('Vadesi uzak');
    });

    it('does not count a fully allocated charge as overdue in the filter', function () {
        $charge = Charge::factory()->create(['description' => 'Kapanan', 'amount' => 100000, 'due_date' => '2026-06-01']);
        uiPayPartOf($charge, 100000);

        Livewire::test(ChargesIndex::class)->set('status', 'all')->call('toggle', 'onlyOverdue')->assertDontSee('Kapanan');
    });

    it('filters by due this month, due soon and archived customers', function () {
        Charge::factory()->create(['description' => 'Haziran sonu', 'due_date' => '2026-06-28']);
        Charge::factory()->create(['description' => 'Yedi gün içinde', 'due_date' => '2026-06-20']);
        Charge::factory()->create(['description' => 'Ağustos başı', 'due_date' => '2026-08-01']);
        Charge::factory()->for(Customer::factory()->archived())->create(['description' => 'Arşivli müşteri borcu', 'due_date' => '2026-06-20']);

        Livewire::test(ChargesIndex::class)
            ->call('toggle', 'dueThisMonth')->assertSee('Haziran sonu')->assertDontSee('Ağustos başı')
            ->call('toggle', 'dueThisMonth')
            ->call('toggle', 'dueSoon')->assertSee('Yedi gün içinde')->assertDontSee('Haziran sonu')->assertSee('Arşivli müşteri borcu')
            ->call('toggle', 'hideArchived')->assertDontSee('Arşivli müşteri borcu')->assertSee('Yedi gün içinde');
    });

    it('hides the charges of archived customers with the filter alone too', function () {
        Charge::factory()->for(Customer::factory()->archived())->create(['description' => 'Arşivli borcu']);
        Charge::factory()->create(['description' => 'Normal borç']);

        Livewire::test(ChargesIndex::class)->assertSee('Arşivli borcu')->call('toggle', 'hideArchived')->assertDontSee('Arşivli borcu')->assertSee('Normal borç');
    });

    it('filters by currency and by customer', function () {
        $logi = Customer::factory()->create(['short_name' => 'Logi']);
        Charge::factory()->for($logi)->create(['description' => 'Logi TL', 'currency' => Currency::Try]);
        Charge::factory()->for($logi)->create(['description' => 'Logi USD', 'currency' => Currency::Usd]);
        Charge::factory()->create(['description' => 'Başkası']);

        Livewire::test(ChargesIndex::class)
            ->set('currency', 'USD')->assertSee('Logi USD')->assertDontSee('Logi TL')
            ->set('currency', '')
            ->set('customer', (string) $logi->id)->assertSee('Logi TL')->assertDontSee('Başkası');
    });

    it('opens limited to one customer and every status from the card link', function () {
        $logi = Customer::factory()->create(['short_name' => 'Logi']);
        Charge::factory()->for($logi)->paid()->create(['description' => 'Logi ödenmiş']);
        Charge::factory()->create(['description' => 'Başkası']);

        $this->get(route('app.charges.index', ['customer' => $logi->id, 'status' => 'all']))
            ->assertOk()
            ->assertSee('Logi ödenmiş')
            ->assertDontSee('Başkası');
    });

    it('finds a charge by its description and by the customer', function () {
        Charge::factory()->for(Customer::factory()->create(['short_name' => 'Logi']))->create(['description' => 'Hosting yenileme']);
        Charge::factory()->for(Customer::factory()->create(['short_name' => 'Başka']))->create(['description' => 'Alan adı']);

        Livewire::test(ChargesIndex::class)
            ->set('search', 'hosting')->assertSee('Hosting yenileme')->assertDontSee('Alan adı')
            ->set('search', 'başka')->assertSee('Alan adı')->assertDontSee('Hosting yenileme');
    });

    it('totals the remaining open debt per currency, minus partial payments, without paid or cancelled charges', function () {
        $first = Charge::factory()->create(['amount' => 400000, 'due_date' => '2026-07-10']);
        Charge::factory()->create(['amount' => 150000, 'due_date' => '2026-07-11']);
        Charge::factory()->create(['amount' => 5000, 'currency' => Currency::Usd, 'due_date' => '2026-07-12']);
        Charge::factory()->paid()->create(['amount' => 999900]);
        Charge::factory()->cancelled()->create(['amount' => 888800]);
        uiPayPartOf($first, 100000);

        $html = Livewire::test(ChargesIndex::class)->html();

        expect($html)->toContain('<tfoot>')->toContain(__('charges.total'))->toContain('4.500 TL')->toContain('50 USD')->not->toContain('9.999')->not->toContain('8.888');
    });

    it('follows the filters in the total row and has none when no open charge is listed', function () {
        Charge::factory()->create(['amount' => 100000, 'currency' => Currency::Try, 'due_date' => '2026-07-10']);
        Charge::factory()->create(['amount' => 5000, 'currency' => Currency::Usd, 'due_date' => '2026-07-10']);
        Charge::factory()->paid()->create();

        $filtered = Livewire::test(ChargesIndex::class)->set('currency', 'USD')->html();
        expect($filtered)->toContain('50 USD')->not->toContain('1.000 TL');

        expect(Livewire::test(ChargesIndex::class)->set('status', 'paid')->html())->not->toContain('<tfoot>');
    });

    it('totals the whole filtered list, not only the page shown', function () {
        Charge::factory()->count(30)->sequence(fn ($sequence) => ['due_date' => '2026-07-10', 'amount' => 10000, 'description' => sprintf('Borç %02d', $sequence->index)])->create();

        Livewire::test(ChargesIndex::class)->assertSee('3.000 TL')->assertDontSee('Borç 29')->call('nextPage')->assertSee('Borç 29');
    });

    it('has "Ödeme al" on an open charge and "Düzenle" from md, nothing for the other statuses', function () {
        $open = Charge::factory()->create();
        Charge::factory()->paid()->create();

        $html = Livewire::test(ChargesIndex::class)->html();

        expect($html)
            ->toContain("name: 'record-payment', params: { customer: {$open->customer_id}, currency: 'TRY' }")
            ->toContain("name: 'edit-charge', params: { charge: {$open->id} }");

        $paid = Livewire::test(ChargesIndex::class)->set('status', 'paid')->html();
        expect($paid)->not->toContain("name: 'record-payment'")->not->toContain("name: 'edit-charge'");
    });

    it('shows the description and the due date under the customer for narrow screens, escaped', function () {
        Charge::factory()->create(['description' => '<b>Hosting</b>', 'due_date' => '2026-07-10']);

        $html = Livewire::test(ChargesIndex::class)->html();

        expect($html)->toContain('ui-below-md')->toContain('&lt;b&gt;Hosting&lt;/b&gt;')->not->toContain('<b>Hosting</b>')->toContain(__('charges.due_date').': 10.07.2026');
    });

    it('links the customer name to the customer card', function () {
        $charge = Charge::factory()->create();

        Livewire::test(ChargesIndex::class)->assertSee('href="'.route('app.customers.show', $charge->customer).'"', false);
    });

    it('shows the empty state, and a different one when a search finds nothing', function () {
        Livewire::test(ChargesIndex::class)->assertSee(__('charges.empty_heading'));

        Charge::factory()->create();

        Livewire::test(ChargesIndex::class)->set('search', 'zzz')->assertSee(__('ui.table.no_results'));
    });

    it('refreshes after a payment or a charge changed in a window', function () {
        $charge = Charge::factory()->create(['description' => 'Eski açıklama', 'due_date' => '2026-07-10']);

        $list = Livewire::test(ChargesIndex::class)->assertSee('Eski açıklama');

        $charge->update(['description' => 'Yeni açıklama']);

        $list->dispatch('charges-changed')->assertSee('Yeni açıklama');
        $list->dispatch('payments-changed')->assertHasNoErrors();
    });

    it('runs the same number of queries for a few charges as for many', function () {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ChargesIndex::class);

            return count(DB::getQueryLog());
        };

        Charge::factory()->count(3)->create(['due_date' => '2026-07-10']);
        $count(); // The first request also reads the permissions, which are cached afterwards.
        $few = $count();
        Charge::factory()->count(20)->create(['due_date' => '2026-06-01', 'currency' => Currency::Usd]);

        expect($count())->toBe($few);
    });
});

// --- Ödemeler ---------------------------------------------------------------

describe('payment list', function () {
    it('is reachable from the sidebar and needs a signed-in user', function () {
        $this->get(route('app.payments.index'))->assertOk()->assertSee(__('payments.plural'));

        auth()->logout();

        $this->get(route('app.payments.index'))->assertRedirect(route('app.login'));
    });

    it('lists the payments of all customers, newest first', function () {
        Payment::factory()->create(['amount' => 123400, 'paid_on' => '2026-05-01']);
        Payment::factory()->create(['amount' => 567800, 'paid_on' => '2026-06-01']);

        $html = Livewire::test(PaymentsIndex::class)->html();

        expect(uiAppearsInOrder($html, ['5.678 TL', '1.234 TL']))->toBeTrue();
    });

    it('shows customer, date, amount, method and note', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi']);
        $method = PaymentMethod::query()->firstOrFail();
        Payment::factory()->for($customer)->create(['amount' => 150000, 'paid_on' => '2026-06-10', 'payment_method_id' => $method->id, 'note' => 'Havale geldi']);

        Livewire::test(PaymentsIndex::class)
            ->assertSee('Logi')
            ->assertSee('10.06.2026')
            ->assertSee('1.500 TL')
            ->assertSee($method->name)
            ->assertSee('Havale geldi');
    });

    it('opens the detail window from the amount, and offers a separate confirmed delete from md', function () {
        $payment = Payment::factory()->create();

        $html = Livewire::test(PaymentsIndex::class)->html();

        expect($html)
            ->toContain("name: 'view-payment', params: { payment: {$payment->id} }")
            ->toContain("name: 'confirm', params: { action: 'delete-payment', id: {$payment->id} }")
            ->toContain('ui-from-md');
    });

    it('filters by customer and searches by customer name', function () {
        $logi = Customer::factory()->create(['short_name' => 'Logi']);
        Payment::factory()->for($logi)->create(['amount' => 111100]);
        Payment::factory()->for(Customer::factory()->create(['short_name' => 'Başka']))->create(['amount' => 222200]);

        Livewire::test(PaymentsIndex::class)
            ->set('customer', (string) $logi->id)->assertSee('1.111 TL')->assertDontSee('2.222 TL')
            ->set('customer', '')
            ->set('search', 'başka')->assertSee('2.222 TL')->assertDontSee('1.111 TL');
    });

    it('opens limited to one customer from the card link', function () {
        $logi = Customer::factory()->create();
        Payment::factory()->for($logi)->create(['amount' => 111100]);
        Payment::factory()->create(['amount' => 222200]);

        $this->get(route('app.payments.index', ['customer' => $logi->id]))->assertOk()->assertSee('1.111 TL')->assertDontSee('2.222 TL');
    });

    it('shows the empty state with "Ödeme al"', function () {
        Livewire::test(PaymentsIndex::class)
            ->assertSee(__('payments.empty_heading'))
            ->assertSee("name: 'record-payment'", false);
    });

    it('refreshes after a payment was recorded or deleted', function () {
        $list = Livewire::test(PaymentsIndex::class)->assertSee(__('payments.empty_heading'));

        Payment::factory()->create(['amount' => 333300]);

        $list->dispatch('payments-changed')->assertSee('3.333 TL');
    });

    it('pages the list', function () {
        Payment::factory()->count(30)->sequence(fn ($sequence) => ['note' => sprintf('Not %02d', $sequence->index), 'paid_on' => '2026-06-01'])->create();

        Livewire::test(PaymentsIndex::class)->assertSee('Not 29')->assertDontSee('Not 00')->call('nextPage')->assertSee('Not 00')->assertDontSee('Not 29');
    });
});
