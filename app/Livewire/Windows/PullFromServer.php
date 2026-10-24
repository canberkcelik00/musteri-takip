<?php

namespace App\Livewire\Windows;

use App\Actions\Servers\PreviewServerPull;
use App\Actions\Servers\PullFromServer as PullFromServerAction;
use App\Actions\Servers\ServerPullPreview;
use App\Actions\Servers\ServerPullResult;
use App\Enums\Currency;
use App\Enums\ServerType;
use App\Models\Server;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Sunucudan çek": the window reads the server first and shows what a pull would do; WHM asks a price for every new
 * package, cPanel a start date, and each new account the name of the customer it becomes (a domain of the account or
 * one typed by hand; a row can be taken off the list). Confirming runs PullFromServer. What the server offered is
 * locked, so only the answers can come from the browser. The result stays in the window, because the accounts that
 * could not be imported are listed there. It only calls PreviewServerPull and PullFromServer.
 */
class PullFromServer extends Component
{
    /** The "Diğer…" option of the customer name list: the name is typed by hand. */
    public const string OTHER = '__other';

    #[Locked]
    public int $serverId = 0;

    #[Locked]
    public bool $isCpanel = false;

    #[Locked]
    public ?string $error = null;

    #[Locked]
    public ?string $summary = null;

    /** @var list<string> */
    #[Locked]
    public array $packageNames = [];

    /** @var list<array{key: string, domains: list<string>, account: string}> */
    #[Locked]
    public array $rows = [];

    /** @var list<string> */
    #[Locked]
    public array $resultParts = [];

    /** @var list<string> */
    #[Locked]
    public array $resultProblems = [];

    #[Locked]
    public bool $done = false;

    /** @var array<int, string> The yearly price typed for each new package, by position. */
    public array $prices = [];

    /** @var array<int, string> */
    public array $currencies = [];

    /** @var array<int, string> The customer name chosen for each account row, by position. */
    public array $choices = [];

    /** @var array<int, string> */
    public array $customs = [];

    /** @var list<int> Positions of the account rows the user took off the list. */
    public array $removed = [];

    public string $start_date = '';

    public function mount(int $server, PreviewServerPull $previewServerPull): void
    {
        $record = Server::query()->findOrFail($server);
        $preview = $previewServerPull->handle($record);

        $this->serverId = $record->id;
        $this->isCpanel = $record->type === ServerType::Cpanel;
        $this->error = $preview->error;
        $this->start_date = today()->toDateString();

        if ($preview->error !== null) {
            return;
        }

        $this->summary = $this->describe($preview);
        $this->packageNames = $preview->newPackages;
        $this->prices = array_fill(0, count($preview->newPackages), '');
        $this->currencies = array_fill(0, count($preview->newPackages), Currency::Try->value);

        foreach ($preview->newAccountRows as $position => $row) {
            $this->rows[] = ['key' => $row['key'], 'domains' => $row['domains'], 'account' => "{$row['username']} — {$row['domain']}"];
            $this->choices[$position] = $row['domain'];
            $this->customs[$position] = '';
        }
    }

    public function removeRow(int $position): void
    {
        if (isset($this->rows[$position]) && ! in_array($position, $this->removed, true)) {
            $this->removed[] = $position;
        }
    }

    public function importAccounts(PullFromServerAction $pullFromServer): void
    {
        if ($this->error !== null || $this->done) {
            return;
        }

        $this->validate($this->rules(), attributes: $this->validationAttributes());

        $server = Server::query()->findOrFail($this->serverId);

        $prices = [];

        foreach ($this->packageNames as $position => $name) {
            $prices[$name] = ['price' => (int) MoneyAmountRule::minorUnits($this->prices[$position]), 'currency' => Currency::from($this->currencies[$position])];
        }

        $names = [];
        $excluded = [];

        foreach ($this->rows as $position => $row) {
            if (in_array($position, $this->removed, true)) {
                $excluded[] = $row['key'];

                continue;
            }

            $names[$row['key']] = trim($this->choices[$position] === self::OTHER ? $this->customs[$position] : $this->choices[$position]);
        }

        $result = $pullFromServer->handle(
            $server,
            $prices,
            $this->isCpanel ? Carbon::parse($this->start_date)->toImmutable() : null,
            $names,
            $excluded,
        );

        $this->dispatch('servers-changed');

        if ($result->error !== null) {
            $this->addError('form', $result->error);

            return;
        }

        $this->report($result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'start_date' => [$this->isCpanel ? 'required' : 'nullable', 'date'],
        ];

        foreach (array_keys($this->packageNames) as $position) {
            $rules["prices.{$position}"] = ['required', new MoneyAmountRule];
            $rules["currencies.{$position}"] = ['required', Rule::enum(Currency::class)];
        }

        foreach (array_keys($this->rows) as $position) {
            if (in_array($position, $this->removed, true)) {
                continue;
            }

            $domains = $this->rows[$position]['domains'];
            $rules["choices.{$position}"] = ['required', Rule::in([...$domains, self::OTHER])];
            $rules["customs.{$position}"] = [($this->choices[$position] ?? null) === self::OTHER ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $attributes = ['start_date' => __('servers.pull_start_date')];

        foreach ($this->packageNames as $position => $name) {
            $attributes["prices.{$position}"] = __('servers.pull_package_price').' ('.$name.')';
            $attributes["currencies.{$position}"] = __('money.currency');
        }

        foreach ($this->rows as $position => $row) {
            $attributes["choices.{$position}"] = __('servers.pull_customer_name');
            $attributes["customs.{$position}"] = __('servers.pull_customer_name_custom');
        }

        return $attributes;
    }

    private function describe(ServerPullPreview $preview): string
    {
        return __('servers.pull_summary', [
            'new' => $preview->newAccounts,
            'link' => $preview->linkableAccounts,
            'linked' => $preview->alreadyLinkedAccounts,
            'suspended' => $preview->suspendedAccounts,
        ]);
    }

    /**
     * What the pull did, kept in the window with the accounts that could not be imported.
     */
    private function report(ServerPullResult $result): void
    {
        $accounts = $result->accounts;
        assert($accounts !== null);

        $parts = [];

        if ($result->packages !== null && $result->packages->created !== []) {
            $parts[] = __('servers.pull_done_packages', ['count' => count($result->packages->created)]);
        }

        $parts[] = __('servers.pull_done_customers', ['count' => $accounts->customersCreated]);
        $parts[] = __('servers.pull_done_linked', ['count' => count($accounts->linked)]);
        $parts[] = __('servers.pull_done_suspended', ['count' => count($accounts->suspendedSkipped)]);

        if ($result->excludedAccounts !== []) {
            $parts[] = __('servers.pull_done_excluded', ['count' => count($result->excludedAccounts)]);
        }

        $problems = array_map(
            fn (string $key, string $message): string => "{$key}: {$message}",
            array_keys($accounts->errors),
            $accounts->errors,
        );

        if ($accounts->missingOnServer !== []) {
            $problems[] = __('servers.pull_missing_on_server', ['keys' => implode(', ', $accounts->missingOnServer)]);
        }

        $this->resultParts = $parts;
        $this->resultProblems = $problems;
        $this->done = true;

        $this->dispatch('customers-changed');

        if ($problems === []) {
            $this->dispatch('close-window');
            $this->dispatch('notify', message: implode(', ', $parts));
        }
    }

    public function render(): View
    {
        return view('livewire.windows.pull-from-server', [
            'server' => Server::query()->findOrFail($this->serverId),
            'currencyOptions' => collect(Currency::cases())->mapWithKeys(fn (Currency $case): array => [$case->value => $case->getLabel()])->all(),
        ]);
    }
}
