<?php

namespace App\Livewire\Pages\Customers;

use App\Actions\Calendar\ListCustomerEvents;
use App\Actions\Reminders\BuildWhatsAppReminder;
use App\Actions\Servers\BuildServiceCardSummaries;
use App\Actions\Tasks\CompleteTask;
use App\Enums\ChargeStatus;
use App\Enums\CustomerServiceStatus;
use App\Enums\Permission;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Settings;
use App\Support\Ui\RecentCustomers;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

        RecentCustomers::remember($customer);
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
    #[On('tasks-changed')]
    #[On('calendar-changed')]
    public function refresh(): void
    {
        $this->customer->refresh();
    }

    /**
     * "Bitti" on one of the card's tasks; only a task of this customer can be finished from here.
     */
    public function completeTask(int $id, CompleteTask $completeTask): void
    {
        $user = Auth::user();

        abort_unless($user?->can(Permission::TasksManage->value) === true, 403);

        $completeTask->handle($user, $this->customer->tasks()->findOrFail($id));

        $this->dispatch('tasks-changed');
        $this->dispatch('notify', message: __('tasks.completed'));
    }

    public function render(): View
    {
        $customer = $this->customer;

        $canSeeMoney = Auth::user()?->can(Permission::MoneyView->value) === true;
        $canSeeTasks = Auth::user()?->can(Permission::TasksManage->value) === true;
        $canSeeEvents = Auth::user()?->can(Permission::CalendarManage->value) === true;

        $customer->load([
            'contacts' => fn ($contacts) => $contacts->orderByDesc('is_primary')->orderBy('name'),
            'customerServices' => fn ($services) => $services->with('items.server')->orderBy('end_date')->orderBy('id'),
        ]);

        if ($canSeeMoney) {
            $customer->load([
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
        } else {
            foreach (['charges', 'payments', 'quotes'] as $relation) {
                $customer->setRelation($relation, new Collection);
            }
        }

        $primary = $customer->contacts->firstWhere('is_primary', true);
        $whatsApp = app(BuildWhatsAppReminder::class);

        foreach ($customer->charges as $charge) {
            $charge->setRelation('customer', $customer);
        }

        $customer->setRelation('primaryContact', $primary);

        /** @var Collection<int, CustomerService> $services */
        $services = $customer->customerServices;
        $activeServices = $services->where('status', CustomerServiceStatus::Active)->values();

        return view('livewire.pages.customers.show', [
            'activeServices' => $activeServices,
            'domains' => $activeServices
                ->flatMap(fn (CustomerService $service): array => [...$service->domains(), ...$service->otherDomains()])
                ->unique(fn (string $domain): string => mb_strtolower($domain))
                ->values()
                ->all(),
            'cloudflareDomains' => $activeServices
                ->flatMap(fn (CustomerService $service): array => $service->cloudflareDomains())
                ->unique()
                ->values()
                ->all(),
            'cancelledServices' => $services->where('status', CustomerServiceStatus::Cancelled)->values(),
            'whatsAppLinks' => $customer->charges->mapWithKeys(fn ($charge): array => [$charge->id => $whatsApp->handle($charge)]),
            'summaries' => app(BuildServiceCardSummaries::class)->handle($services),
            'leadDays' => app(Settings::class)->renewalLeadDays(),
            'openDebt' => $canSeeMoney ? $customer->openChargeTotals() : [],
            'creditBalance' => $canSeeMoney ? $customer->creditBalances() : [],
            'inUse' => $customer->isInUse(),
            'canSeeMoney' => $canSeeMoney,
            'openTasks' => $canSeeTasks ? $customer->tasks()->open()->orderForList()->with('assignee')->limit(5)->get() : new Collection,
            'upcomingEvents' => $canSeeEvents ? app(ListCustomerEvents::class)->handle($customer) : [],
        ])->title($customer->short_name);
    }
}
