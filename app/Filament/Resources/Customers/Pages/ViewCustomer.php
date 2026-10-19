<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Actions\Customers\DeleteCustomer;
use App\Enums\CustomerServiceStatus;
use App\Filament\Actions\ArchiveCustomerAction;
use App\Filament\Actions\AssignCustomerServiceAction;
use App\Filament\Actions\DeleteRecordAction;
use App\Filament\Actions\EditChargeAction;
use App\Filament\Actions\EditCustomerAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\RelationManagers\CancelledCustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Customers\Schemas\CustomerInfolist;
use App\Models\Customer;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/**
 * The customer card (T-55, restructured in T-66 for G-4.16): a full-width header strip (name,
 * open debt, credit balance, the two actions; G-4B.5, see {@see self::getHeader()}), then two columns — the work (services, open charges, recent payments) on the
 * left, information (contacts, billing, notes) on the right — and the destructive actions
 * (archive, delete) at the bottom, right-aligned and separate from both columns, not boxed in
 * their own card (T-67; a bordered Section around 0–1 button looked broken, docs/UI.md §5), with
 * a normal section gap above it. Contacts is a bare Livewire component, not wrapped in a Section
 * (a T-70 attempt at that produced a visibly nested double card). "Ödeme al" ve "Hizmet ekle"
 * sayfa başlık eylemleri, şeritle aynı hizada.
 *
 * T-71/T-72'nin sağ sütun hizalama hatası YANLIŞ teşhis edilmişti (açık borç satır sayısıyla
 * ilgisi yoktu) ve o iki karardaki `mt-6`/savunmacı CSS geçici çözümleri artık YOK: gerçek kök
 * neden `ContactsRelationManager`'ın satırları iç içe `<button>` üretiyor olmasıydı (tarayıcı
 * bunu HTML5 kuralına göre yeniden yapılandırıyordu); T-73'te `recordAction(null)` ile
 * düzeltildi. DOM artık her zaman doğru iç içe geçtiğinden şemanın kendi 24px boşluğu tüm
 * bölümler arasında (Kişiler → Fatura bilgileri → Notlar, ve Grid → Arşivle satırı) güvenilir
 * şekilde çalışıyor; bkz. docs/DECISIONS.md T-73.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * Just the short name ("Deniz Lojistik"), not Filament's default "... görüntüle" title;
     * the ticari ünvan is the subheading below it.
     */
    public function getTitle(): string
    {
        $customer = $this->getRecord();
        assert($customer instanceof Customer);

        return $customer->short_name;
    }

    public function getSubheading(): ?string
    {
        $customer = $this->getRecord();
        assert($customer instanceof Customer);

        return $customer->legal_title;
    }

    /**
     * The card has no form or infolist schema (T-55); its content() schema (cached as
     * "content") holds the "danger_actions" Actions component (T-66). Without this, Filament's
     * test helpers default to a "form" schema name that does not exist here.
     */
    public function getDefaultTestingSchemaName(): ?string
    {
        return 'content';
    }

    /**
     * The header strip of the reference screen (G-4B.5): breadcrumb, short name and legal title on
     * the left, the open debt and credit balance (per currency, never summed) in the middle, the
     * two secondary actions on the right, all in one bordered card. The amounts are the same
     * derived values the strip showed (Customer::openChargeTotals(), creditBalances()); the page
     * heading, subheading and header actions are unchanged.
     */
    public function getHeader(): ?View
    {
        $customer = $this->getRecord();
        assert($customer instanceof Customer);

        return view('filament.customers.header', [
            'breadcrumbs' => array_slice($this->getBreadcrumbs(), 0, -1, true),
            'title' => $this->getTitle(),
            'subheading' => $this->getSubheading(),
            'openDebt' => $customer->openChargeTotals(),
            'creditBalance' => $customer->creditBalances(),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $customer = $this->getRecord();
        assert($customer instanceof Customer);

        return [
            RecordPaymentAction::make()->customer($customer)->color('gray'),
            AssignCustomerServiceAction::make()->customer($customer)->color('gray'),
        ];
    }

    /**
     * Recording or deleting a payment, changing a charge, or assigning a service changes
     * the open debt / credit balance shown in the strip and the cancelled-services count; they
     * are computed from the record, so a fresh record is enough.
     */
    #[On(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)]
    #[On(EditChargeAction::CHARGES_CHANGED_EVENT)]
    #[On(AssignCustomerServiceAction::SERVICES_CHANGED_EVENT)]
    public function refreshAfterPaymentChange(): void
    {
        $this->record->refresh();
    }

    public function content(Schema $schema): Schema
    {
        $customer = $this->getRecord();
        assert($customer instanceof Customer);

        return $schema->record($customer)->components([
            Grid::make(3)
                ->schema([
                    Group::make($this->workColumn($customer))->columnSpan(2)->key('work_column'),
                    Grid::make(1)->schema($this->infoColumn($customer))->columnSpan(1)->key('info_column'),
                ]),
            Actions::make([
                ArchiveCustomerAction::make(),
                UnarchiveAction::make(),
                DeleteRecordAction::make()->deleteWith(DeleteCustomer::class),
            ])
                ->key('danger_actions')
                ->alignment(Alignment::End),
        ]);
    }

    /**
     * @return list<Component>
     */
    private function workColumn(Customer $customer): array
    {
        $cancelledCount = $customer->customerServices()->where('status', CustomerServiceStatus::Cancelled)->count();

        return [
            Livewire::make(CustomerServicesRelationManager::class, [
                'ownerRecord' => $customer,
                'pageClass' => static::class,
            ])->key('customerServices'),
            Section::make(__('customers.cancelled_services', ['count' => $cancelledCount]))
                ->collapsed()
                ->visible($cancelledCount > 0)
                ->schema([
                    Livewire::make(CancelledCustomerServicesRelationManager::class, [
                        'ownerRecord' => $customer,
                        'pageClass' => static::class,
                    ])->key('cancelledCustomerServices'),
                ]),
            Livewire::make(ChargesRelationManager::class, [
                'ownerRecord' => $customer,
                'pageClass' => static::class,
            ])->key('charges'),
            Livewire::make(PaymentsRelationManager::class, [
                'ownerRecord' => $customer,
                'pageClass' => static::class,
            ])->key('payments'),
        ];
    }

    /**
     * @return list<Component>
     */
    private function infoColumn(Customer $customer): array
    {
        return [
            Livewire::make(ContactsRelationManager::class, [
                'ownerRecord' => $customer,
                'pageClass' => static::class,
            ])->key('contacts'),
            Section::make(__('customers.billing_section'))
                ->key('billing_section')
                ->afterHeader([EditCustomerAction::make()->link()->icon(null)])
                ->schema(CustomerInfolist::billingSchema()),
            Section::make(__('customers.notes_section'))
                ->schema(CustomerInfolist::notesSchema()),
        ];
    }
}
