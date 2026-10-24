<?php

namespace App\Livewire\Pages\Customers;

use App\Actions\Reminders\BuildWhatsAppReminder;
use App\Actions\Servers\BuildUsageReport;
use App\Enums\ChargeStatus;
use App\Enums\CustomerServiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Settings;
use App\Support\Ui\StatusBadges;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The customer card (docs/UI.md §5 "Müşteri kartı"): a header strip with the open debt and credit balance, then two
 * columns — the work (services, open charges, recent payments, quotes) on the left, information (contacts, billing,
 * notes) on the right — and the archive and delete actions apart at the bottom. Everything is changed through
 * windows; this component only reads and draws.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * A window changed something the card shows; the card is read again.
     */
    #[On('payments-changed')]
    #[On('charges-changed')]
    #[On('customer-services-changed')]
    #[On('customers-changed')]
    #[On('contacts-changed')]
    #[On('quotes-changed')]
    public function refresh(): void
    {
        $this->customer->refresh();
    }

    public function render(): View
    {
        $customer = $this->customer;

        $customer->load([
            'contacts' => fn ($contacts) => $contacts->orderByDesc('is_primary')->orderBy('name'),
            'customerServices' => fn ($services) => $services->with('items.server')->orderBy('end_date')->orderBy('id'),
            'charges' => fn ($charges) => $charges
                ->where('status', ChargeStatus::Open)
                ->withSum('allocations', 'amount')
                ->with('customerService.items')
                ->orderBy('due_date')
                ->orderBy('id'),
            'payments' => fn ($payments) => $payments
                ->with(['paymentMethod', 'allocations.charge.customerService.items'])
                ->orderByDesc('paid_on')
                ->orderByDesc('id')
                ->limit(5),
            'quotes' => fn ($quotes) => $quotes->with('items')->orderByDesc('created_at')->orderByDesc('id'),
        ]);

        $primary = $customer->contacts->firstWhere('is_primary', true);
        $whatsApp = app(BuildWhatsAppReminder::class);

        foreach ($customer->charges as $charge) {
            $charge->setRelation('customer', $customer);
        }

        $customer->setRelation('primaryContact', $primary);

        /** @var Collection<int, CustomerService> $services */
        $services = $customer->customerServices;

        return view('livewire.pages.customers.show', [
            'activeServices' => $services->where('status', CustomerServiceStatus::Active)->values(),
            'cancelledServices' => $services->where('status', CustomerServiceStatus::Cancelled)->values(),
            'whatsAppLinks' => $customer->charges->mapWithKeys(fn ($charge): array => [$charge->id => $whatsApp->handle($charge)]),
            'usageBadges' => $this->usageBadges($services->where('status', CustomerServiceStatus::Active)),
            'leadDays' => app(Settings::class)->renewalLeadDays(),
            'openDebt' => $customer->openChargeTotals(),
            'creditBalance' => $customer->creditBalances(),
            'inUse' => $customer->isInUse(),
        ])->title($customer->short_name);
    }

    /**
     * The worst problem of each service's linked accounts as one badge ("Disk %94"), keyed by service id; a
     * service whose accounts are all below the threshold has none.
     *
     * @param  Collection<int, CustomerService>  $services
     * @return array<int, array{item: int, text: string, tone: string}>
     */
    private function usageBadges(Collection $services): array
    {
        $badges = [];
        $reports = app(BuildUsageReport::class)->handleMany($services->flatMap(fn (CustomerService $service): Collection => $service->items));

        foreach ($services as $service) {
            $worst = null;

            foreach ($service->items as $item) {
                $badge = ($reports[$item->id] ?? null)?->badge();

                if ($badge !== null && ($worst === null || $badge['level']->severity() > $worst['level']->severity())) {
                    $worst = ['item' => $item->id, 'text' => $badge['text'], 'level' => $badge['level']];
                }
            }

            if ($worst !== null) {
                $badges[$service->id] = [
                    'item' => $worst['item'],
                    'text' => $worst['text'],
                    'tone' => StatusBadges::toneOfColor($worst['level']->getColor()),
                ];
            }
        }

        return $badges;
    }
}
