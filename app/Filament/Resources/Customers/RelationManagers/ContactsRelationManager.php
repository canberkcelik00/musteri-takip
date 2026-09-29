<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\MakeContactPrimary;
use App\Exceptions\PrimaryContactCannotBeDeleted;
use App\Models\Contact;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer's contacts, one stacked column each (T-67: name + "· Birincil" when primary,
 * title, phone as tel:, email as mailto:; blank ones drop out) so the card's narrow right
 * column never scrolls horizontally. No row action button (T-68, G-4.17 keeps this card free
 * of "⋮" menus): clicking the name opens the edit slide-over, wired as a **column** action
 * ({@see TextColumn::action()}) rather than a table row action. A row action registered only
 * to be hidden() breaks Livewire's partial re-render once its form is filled (confirmed from
 * source and by reproducing it: `Filament\Support\Livewire\Partials\PartialsComponentHook`
 * renders the hidden trigger's own partial as an empty string, which then fails Livewire's
 * root-tag check) — a column action sidesteps that registry entirely and needs no hidden()
 * button at all. "Birincil yap" and "Sil" (both hidden for the primary contact, "Sil" red and
 * confirmed) live in that slide-over's footer via extraModalFooterActions() and cancel it
 * (cancelParentActions()) instead of saving the form. Every change goes through the Actions in
 * App\Actions\Contacts, which keep exactly one primary contact.
 */
class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contacts.plural');
    }

    /**
     * Filament makes relation managers read-only on view pages; the contacts are managed on the customer card.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getContactFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->orderByDesc('is_primary')->orderBy('name'))
            ->paginated(false)
            ->columns([
                Stack::make([
                    TextColumn::make('name')
                        ->label(__('contacts.name'))
                        ->weight(FontWeight::Bold)
                        ->formatStateUsing(fn (Contact $record): string => $record->is_primary
                            ? $record->name.' · '.__('contacts.is_primary')
                            : $record->name)
                        ->action($this->buildEditAction()),
                    TextColumn::make('title')
                        ->label(__('contacts.title'))
                        ->visible(fn (?Contact $record): bool => $record === null || filled($record->title)),
                    TextColumn::make('phone')
                        ->label(__('contacts.phone'))
                        ->url(fn (Contact $record): ?string => filled($record->phone) ? "tel:{$record->phone}" : null)
                        ->visible(fn (?Contact $record): bool => $record === null || filled($record->phone)),
                    TextColumn::make('email')
                        ->label(__('contacts.email'))
                        ->url(fn (Contact $record): ?string => filled($record->email) ? "mailto:{$record->email}" : null)
                        ->visible(fn (?Contact $record): bool => $record === null || filled($record->email)),
                ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('contacts.add'))
                    ->color('gray')
                    ->schema([
                        ...$this->getContactFields(),
                        Toggle::make('make_primary')
                            ->label(__('contacts.make_primary_on_create')),
                    ])
                    ->using(function (array $data, CreateContact $createContact): Contact {
                        $makePrimary = (bool) ($data['make_primary'] ?? false);

                        unset($data['make_primary']);

                        $customer = $this->getOwnerRecord();
                        assert($customer instanceof Customer);

                        return $createContact->handle($customer, $data, $makePrimary);
                    }),
            ]);
    }

    /**
     * Note: these builder methods are deliberately not named "{action}Action" (editAction,
     * makePrimaryAction, deleteAction) — Filament falls back to calling a same-named public
     * method on the Livewire component to resolve an action it can't find in its registered
     * pool (the convention `TodayTasks::recordPaymentAction()` etc. rely on), and a private
     * method of that name breaks with a confusing BadMethodCallException instead (verified
     * from source; the nested footer actions below are exactly such a case, since only "edit"
     * is a registered table action).
     */
    private function buildEditAction(): EditAction
    {
        return EditAction::make()
            ->slideOver()
            ->extraModalFooterActions([
                $this->buildMakePrimaryAction(),
                $this->buildDeleteAction(),
            ]);
    }

    private function buildMakePrimaryAction(): Action
    {
        return Action::make('makePrimary')
            ->label(__('contacts.make_primary'))
            ->icon(Heroicon::OutlinedStar)
            ->visible(fn (Contact $record): bool => ! $record->is_primary)
            ->action(function (Contact $record, MakeContactPrimary $makeContactPrimary, Action $action): void {
                $makeContactPrimary->handle($record);

                $action->successNotificationTitle(__('contacts.made_primary'))->success();
            })
            ->cancelParentActions();
    }

    private function buildDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (Contact $record): bool => ! $record->is_primary)
            ->using(function (Contact $record, DeleteContact $deleteContact, Action $action): bool {
                try {
                    $deleteContact->handle($record);
                } catch (PrimaryContactCannotBeDeleted $exception) {
                    $action->failureNotificationTitle($exception->getMessage());

                    return false;
                }

                return true;
            })
            ->successNotificationTitle(__('contacts.deleted'))
            ->cancelParentActions();
    }

    /**
     * @return list<TextInput>
     */
    private function getContactFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('contacts.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('title')
                ->label(__('contacts.title'))
                ->maxLength(255),
            TextInput::make('phone')
                ->label(__('contacts.phone'))
                ->tel()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('contacts.email'))
                ->email()
                ->maxLength(255),
        ];
    }
}
