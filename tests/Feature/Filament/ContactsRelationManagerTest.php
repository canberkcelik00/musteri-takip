<?php

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ContactsRelationManager;
use App\Filament\Support\RecordActionsSection;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');

    $this->customer = Customer::factory()->create();
    $this->primary = Contact::factory()->for($this->customer)->primary()->create(['name' => 'Ali Birincil']);
    $this->other = Contact::factory()->for($this->customer)->create(['name' => 'Veli Diğer']);
});

function contactsManager(Customer $customer)
{
    return Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

it('is shown on the customer card; the customer has no separate edit page', function () {
    Livewire::test(ViewCustomer::class, ['record' => $this->customer->id])
        ->assertSeeLivewire(ContactsRelationManager::class);

    expect(CustomerResource::hasPage('edit'))->toBeFalse();
});

it('lists the contacts of the customer only, primary first', function () {
    $stranger = Contact::factory()->primary()->create();

    contactsManager($this->customer)
        ->assertCanSeeTableRecords([$this->primary, $this->other], inOrder: true)
        ->assertCanNotSeeTableRecords([$stranger]);
});

it('adds a contact that is not primary', function () {
    contactsManager($this->customer)
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'name' => 'Ayşe Yeni',
            'title' => 'Muhasebe',
            'phone' => '0216 111 22 33',
            'email' => 'ayse@example.com',
        ])
        ->assertHasNoFormErrors();

    $contact = Contact::query()->where('name', 'Ayşe Yeni')->sole();

    expect($contact->customer_id)->toBe($this->customer->id)
        ->and($contact->is_primary)->toBeFalse()
        ->and($this->primary->refresh()->is_primary)->toBeTrue();
});

it('makes a newly added contact primary when asked, replacing the previous one', function () {
    contactsManager($this->customer)
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'name' => 'Ayşe Yeni',
            'make_primary' => true,
        ]);

    $contact = Contact::query()->where('name', 'Ayşe Yeni')->sole();

    expect($contact->is_primary)->toBeTrue()
        ->and($this->primary->refresh()->is_primary)->toBeFalse()
        ->and($this->customer->contacts()->where('is_primary', true)->count())->toBe(1);
});

it('requires a name and a valid e-mail address', function () {
    contactsManager($this->customer)
        ->callAction(TestAction::make(CreateAction::class)->table(), ['name' => null, 'email' => 'geçersiz'])
        ->assertHasFormErrors(['name' => 'required', 'email' => 'email']);

    expect($this->customer->contacts()->count())->toBe(2);
});

it('edits a contact without changing whether it is primary', function () {
    contactsManager($this->customer)
        ->callAction(TestAction::make('edit')->table($this->primary), ['name' => 'Ali Yeni Ad', 'phone' => '0555 000 00 00'])
        ->assertHasNoFormErrors();

    $this->primary->refresh();

    expect($this->primary->name)->toBe('Ali Yeni Ad')
        ->and($this->primary->phone)->toBe('0555 000 00 00')
        ->and($this->primary->is_primary)->toBeTrue();
});

it('makes another contact primary and clears the previous primary contact, from the edit panel', function () {
    contactsManager($this->customer)
        ->callAction([TestAction::make('edit')->table($this->other), TestAction::make('makePrimary')->schemaComponent(RecordActionsSection::ACTIONS_KEY)])
        ->assertNotified();

    expect($this->other->refresh()->is_primary)->toBeTrue()
        ->and($this->primary->refresh()->is_primary)->toBeFalse();
});

it('offers neither making primary nor deleting for the primary contact: its "Kişi işlemleri" section is hidden', function () {
    contactsManager($this->customer)
        ->mountAction(TestAction::make('edit')->table($this->primary))
        ->assertSchemaComponentHidden(RecordActionsSection::ACTIONS_KEY, 'mountedActionSchema0')
        ->assertSchemaComponentHidden(RecordActionsSection::DANGER_ACTIONS_KEY, 'mountedActionSchema0');

    contactsManager($this->customer)
        ->assertActionVisible([TestAction::make('edit')->table($this->other), TestAction::make('makePrimary')->schemaComponent(RecordActionsSection::ACTIONS_KEY)])
        ->assertActionVisible([TestAction::make('edit')->table($this->other), TestAction::make('delete')->schemaComponent(RecordActionsSection::DANGER_ACTIONS_KEY)]);
});

it('keeps the edit panel footer to Vazgeç and Kaydet only', function () {
    $manager = contactsManager($this->customer)
        ->mountAction(TestAction::make('edit')->table($this->other));

    $action = $manager->instance()->getMountedAction();

    expect(array_keys($action->getModalFooterActions()))->toBe(['submit', 'cancel']);
});

it('deletes a contact that is not primary, from the edit panel', function () {
    contactsManager($this->customer)
        ->callAction([TestAction::make('edit')->table($this->other), TestAction::make('delete')->schemaComponent(RecordActionsSection::DANGER_ACTIONS_KEY)])
        ->assertNotified();

    expect(Contact::query()->whereKey($this->other->id)->exists())->toBeFalse()
        ->and($this->primary->refresh()->is_primary)->toBeTrue();
});

it('has no row action button; the name column itself opens the edit slide-over', function () {
    $manager = contactsManager($this->customer)
        ->assertActionExists(TestAction::make('edit')->table($this->primary));

    expect($manager->instance()->getTable()->getRecordActions())->toBe([]);
});

it('shows the primary contact as text next to the name, not a colored icon', function () {
    contactsManager($this->customer)
        ->assertTableColumnFormattedStateSet('name', $this->primary->name.' · Birincil', record: $this->primary)
        ->assertTableColumnFormattedStateSet('name', $this->other->name, record: $this->other);
});
