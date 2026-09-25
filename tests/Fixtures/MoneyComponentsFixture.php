<?php

namespace Tests\Fixtures;

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Infolists\Components\MoneyEntry;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\CatalogService;
use App\Support\Money;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

/**
 * Test-only page that wires the shared money components to a catalog service.
 */
class MoneyComponentsFixture extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?CatalogService $record = null;

    public function mount(?CatalogService $record = null): void
    {
        $this->record = $record;

        $this->form->fill($record?->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                Select::make('type')->options(ServiceType::class)->required(),
                MoneyInput::make('price')->required()->currencyField(),
                CurrencySelect::make('currency'),
            ])
            ->statePath('data')
            ->model($this->record ?? CatalogService::class);
    }

    public function infolist(Schema $schema): Schema
    {
        $totals = Money::sumByCurrency([
            new Money(35000, Currency::Usd),
            new Money(1000000, Currency::Try),
            new Money(200000, Currency::Try),
        ]);

        return $schema
            ->record($this->record)
            ->components([
                MoneyEntry::make('price'),
                MoneyEntry::make('totals')->state($totals),
                MoneyEntry::make('single_line_totals')->state($totals)->singleLine(),
                MoneyEntry::make('no_totals')->state([]),
                MoneyEntry::make('money')->state(new Money(400050, Currency::Eur)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CatalogService::query())
            ->columns([
                TextColumn::make('name'),
                MoneyColumn::make('price'),
                MoneyColumn::make('totals')->state(fn (): array => [
                    new Money(1000000, Currency::Try),
                    new Money(35000, Currency::Usd),
                ]),
                MoneyColumn::make('single_line_totals')->singleLine()->state(fn (): array => Money::sumByCurrency([
                    new Money(1000000, Currency::Try),
                    new Money(200000, Currency::Try),
                    new Money(35000, Currency::Usd),
                ])),
                MoneyColumn::make('no_totals')->state(fn (): array => []),
            ]);
    }

    public function create(): void
    {
        CatalogService::create($this->form->getState());
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {{ $this->form }}
                @if ($record)
                    {{ $this->infolist }}
                @endif
                {{ $this->table }}
            </div>
            BLADE;
    }
}
