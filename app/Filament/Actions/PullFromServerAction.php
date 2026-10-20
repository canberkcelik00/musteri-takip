<?php

namespace App\Filament\Actions;

use App\Actions\Servers\PreviewServerPull;
use App\Actions\Servers\PullFromServer;
use App\Actions\Servers\ServerPullPreview;
use App\Actions\Servers\ServerPullResult;
use App\Enums\Currency;
use App\Enums\ServerType;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Server;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * "Sunucudan çek": the window reads the server first and shows what a pull would do; WHM asks a
 * price for every new package, cPanel a start date. Confirming runs PullFromServer. It only calls
 * PreviewServerPull and PullFromServer.
 */
class PullFromServerAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'pullFromServer';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('servers.pull'))
            ->color('gray')
            ->modalHeading(fn (Server $record): string => __('servers.pull_heading', ['name' => $record->name]))
            ->modalSubmitActionLabel(__('servers.pull_confirm'))
            ->visible(fn (Server $record): bool => ! $record->isArchived())
            ->mountUsing(function (Schema $schema, Server $record): void {
                $preview = app(PreviewServerPull::class)->handle($record);

                $schema->fill([
                    'error' => $preview->error,
                    'summary' => $preview->error === null ? $this->summary($preview) : null,
                    'packages' => array_map(
                        fn (string $name): array => ['name' => $name, 'price' => null, 'currency' => Currency::Try->value],
                        $preview->newPackages,
                    ),
                ]);
            })
            ->schema(fn (Server $record): array => [
                Text::make(fn (Get $get): string => (string) $get('error'))
                    ->color('danger')
                    ->visible(fn (Get $get): bool => filled($get('error'))),
                Text::make(fn (Get $get): string => (string) $get('summary'))
                    ->visible(fn (Get $get): bool => filled($get('summary'))),
                Repeater::make('packages')
                    ->label(__('servers.pull_new_packages'))
                    ->helperText(__('servers.pull_new_packages_helper'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('servers.pull_package'))
                            ->readOnly(),
                        MoneyInput::make('price')
                            ->label(__('servers.pull_package_price'))
                            ->required()
                            ->currencyField(),
                        CurrencySelect::make('currency'),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->visible(fn (Get $get): bool => filled($get('packages'))),
                DatePicker::make('start_date')
                    ->label(__('servers.pull_start_date'))
                    ->helperText(__('servers.pull_start_date_helper'))
                    ->required()
                    ->visible(fn (Get $get): bool => $record->type === ServerType::Cpanel && blank($get('error'))),
            ])
            ->action(function (array $data, Server $record): void {
                $result = app(PullFromServer::class)->handle(
                    $record,
                    $this->prices($data['packages'] ?? []),
                    filled($data['start_date'] ?? null) ? CarbonImmutable::parse($data['start_date']) : null,
                );

                $this->notify($result);
            });
    }

    private function summary(ServerPullPreview $preview): string
    {
        return __('servers.pull_summary', [
            'new' => $preview->newAccounts,
            'link' => $preview->linkableAccounts,
            'linked' => $preview->alreadyLinkedAccounts,
            'suspended' => $preview->suspendedAccounts,
        ]);
    }

    /**
     * @param  array<array-key, array{name: string, price: int, currency: Currency|string}>  $rows
     * @return array<string, array{price: int, currency: Currency}>
     */
    private function prices(array $rows): array
    {
        $prices = [];

        foreach ($rows as $row) {
            $prices[$row['name']] = ['price' => (int) $row['price'], 'currency' => $row['currency'] instanceof Currency ? $row['currency'] : Currency::from($row['currency'])];
        }

        return $prices;
    }

    private function notify(ServerPullResult $result): void
    {
        if ($result->error !== null) {
            Notification::make()->title($result->error)->danger()->persistent()->send();

            return;
        }

        $accounts = $result->accounts;
        assert($accounts !== null);

        $parts = [];

        if ($result->packages !== null && $result->packages->created !== []) {
            $parts[] = __('servers.pull_done_packages', ['count' => count($result->packages->created)]);
        }

        $parts[] = __('servers.pull_done_customers', ['count' => $accounts->customersCreated]);
        $parts[] = __('servers.pull_done_linked', ['count' => count($accounts->linked)]);
        $parts[] = __('servers.pull_done_suspended', ['count' => count($accounts->suspendedSkipped)]);

        $problems = array_map(
            fn (string $key, string $message): string => "{$key}: {$message}",
            array_keys($accounts->errors),
            $accounts->errors,
        );

        if ($accounts->missingOnServer !== []) {
            $problems[] = __('servers.pull_missing_on_server', ['keys' => implode(', ', $accounts->missingOnServer)]);
        }

        $notification = Notification::make()->title(implode(', ', $parts));

        if ($problems === []) {
            $notification->success()->send();

            return;
        }

        $notification
            ->warning()
            ->persistent()
            ->body(new HtmlString(implode('<br>', array_map('e', $problems))))
            ->send();
    }
}
