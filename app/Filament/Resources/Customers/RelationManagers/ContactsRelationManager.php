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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer's contacts. Every change goes through the Actions in App\Actions\Contacts,
 * which keep exactly one primary contact.
 */
class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contacts.plural');
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
                TextColumn::make('name')
                    ->label(__('contacts.name')),
                TextColumn::make('title')
                    ->label(__('contacts.title')),
                TextColumn::make('phone')
                    ->label(__('contacts.phone')),
                TextColumn::make('email')
                    ->label(__('contacts.email')),
                IconColumn::make('is_primary')
                    ->label(__('contacts.is_primary'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
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
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('makePrimary')
                    ->label(__('contacts.make_primary'))
                    ->icon(Heroicon::OutlinedStar)
                    ->color('gray')
                    ->visible(fn (Contact $record): bool => ! $record->is_primary)
                    ->action(function (Contact $record, MakeContactPrimary $makeContactPrimary, Action $action): void {
                        $makeContactPrimary->handle($record);

                        $action->successNotificationTitle(__('contacts.made_primary'))->success();
                    }),
                DeleteAction::make()
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
                    ->successNotificationTitle(__('contacts.deleted')),
            ]);
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
