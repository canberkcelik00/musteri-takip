<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Actions\Customers\DeleteCustomer;
use App\Enums\CustomerServiceStatus;
use App\Filament\Actions\ArchiveCustomerAction;
use App\Filament\Actions\AssignCustomerServiceAction;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Actions\DeleteRecordAction;
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
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Livewire\Attributes\On;

/**
 * The customer card (T-55, restructured in T-66 for G-4.16): a full-width strip (open debt,
 * credit balance), then two columns — the work (services, open charges, recent payments) on the
 * left, information (contacts, billing, notes) on the right — and the destructive actions
 * (archive, delete) at the bottom, right-aligned and separate from both columns, not boxed in
 * their own card (T-67; a bordered Section around 0–1 button looked broken, docs/UI.md §5), but
 * with the same top margin a section would have (T-70/T-71; sitting flush under the grid looked
 * attached to the shorter, right column). Contacts is a bare Livewire component, not wrapped in
 * a Section (T-71 reverts a T-70 attempt that produced a visibly nested double card); its
 * padding/spacing parity with "Fatura bilgileri"/"Notlar" instead comes from those two Sections
 * each carrying their own `mt-6` (T-71 — the right column's own schema does not reliably apply a
 * gap between adjacent plain Sections the way it does around a Livewire component).
 * "Ödeme al" ve "Hizmet ekle" sayfa başlık eylemleri, şeritle aynı hizada.
 *
 * Bilinen, kök nedeni bulunamayan tarayıcı-ayrıştırma sorunu (T-71): açık borcu 3+ satır olan
 * bazı müşterilerde (ör. Deniz Lojistik, id 4) sağ sütunun `Grid::make(1)` sarmalayıcısı 1350
 * px'te "Fatura bilgileri"/"Notlar"ı dış `Grid::make(3)`'ün doğrudan çocuğu gibi render ediyor —
 * yalnız TARAYICIDA (ham sunucu HTML'i ve `$schema->toHtml()` ikisi de doğru iç içe geçmeyi
 * gösteriyor); kapsamlı Playwright/bisection denemeleriyle kök neden bulunamadı (docs/DECISIONS.md
 * T-71). **Savunmacı CSS düzeltmesi (T-72):** `resources/css/filament/panel/theme.css`'teki
 * `:has()` kuralı, bu "düzleşmiş" şekli (dış grid'in DOĞRUDAN çocuğu olan, düzleşmiş bir
 * `.fi-grid-col`) tespit edip yalnızca o durumda `grid-column: 3` zorluyor — doğru iç içe geçmiş
 * durumda (`:has()` eşleşmediğinden) hiçbir etkisi yok, diğer 18 örnek müşteride regresyon yok
 * (Playwright ile doğrulandı). Sütun artık doğru ama satır YANLIŞ: düzleşmiş öğe hâlâ dış
 * grid'in "2. satırı"nda kalıyor, bu satırın yüksekliği sol sütunun tam yüksekliğine eşit
 * olduğundan İlgili kişiler ile Fatura bilgileri arasında büyük bir boşluk kalıyor (DOM'da gerçek
 * iç içe geçme olmadan CSS'le düzeltilemeyen bir sınır — bkz. docs/DECISIONS.md T-72).
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
     * Recording or deleting a payment, adding a manual charge, or assigning a service changes
     * the open debt / credit balance shown in the strip and the cancelled-services count; they
     * are computed from the record, so a fresh record is enough.
     */
    #[On(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)]
    #[On(CreateManualChargeAction::CHARGES_CHANGED_EVENT)]
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
            Section::make()
                ->schema([
                    Grid::make(2)->schema(CustomerInfolist::stripSchema()),
                ]),
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
                ->alignment(Alignment::End)
                ->extraAttributes(['class' => 'mt-6']),
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
                ->afterHeader([EditAction::make()->color('gray')])
                ->schema(CustomerInfolist::billingSchema())
                ->extraAttributes(['class' => 'mt-6']),
            Section::make(__('customers.notes_section'))
                ->schema(CustomerInfolist::notesSchema())
                ->extraAttributes(['class' => 'mt-6']),
        ];
    }
}
