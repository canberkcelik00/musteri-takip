<?php

namespace App\Livewire\Pages\Catalog;

use App\Enums\Permission;
use App\Enums\ServiceType;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\WithTable;
use App\Models\CatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The catalog services list (docs/UI.md §5 Katalog), by name. The name opens the edit window; the row's one button
 * starts a quote for it. Archived services are hidden unless asked for.
 */
#[Layout('layouts.app')]
class Services extends Component
{
    use AuthorizesPermission;
    use WithTable;

    public const string ARCHIVE_ACTIVE = 'active';

    public const string ARCHIVE_ARCHIVED = 'archived';

    public const string ARCHIVE_ALL = 'all';

    public const string TYPE_ALL = 'all';

    #[Url(except: self::ARCHIVE_ACTIVE)]
    public string $archive = self::ARCHIVE_ACTIVE;

    #[Url(except: self::TYPE_ALL)]
    public string $type = self::TYPE_ALL;

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'type', 'price'];
    }

    public function updatedArchive(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    /**
     * A catalog item was saved in a window: the list is read again.
     */
    #[On('catalog-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    /**
     * @return LengthAwarePaginator<int, CatalogService>
     */
    private function services(): LengthAwarePaginator
    {
        $query = CatalogService::query();

        $term = trim($this->search);

        if ($term !== '') {
            $query->where('name', 'like', '%'.addcslashes($term, '\%_').'%');
        }

        match ($this->archive) {
            self::ARCHIVE_ARCHIVED => $query->onlyArchived(),
            self::ARCHIVE_ALL => null,
            default => $query->notArchived(),
        };

        $type = ServiceType::tryFrom($this->type);

        if ($type !== null) {
            $query->where('type', $type);
        }

        $sort = $this->requestedSort();

        if ($sort !== null) {
            $query->orderBy($sort[0], $sort[1]);
        }

        return $query->orderBy('name')->orderBy('id')->paginate($this->perPage());
    }

    protected function requiredPermission(): ?Permission
    {
        return Permission::CatalogManage;
    }

    public function render(): View
    {
        $services = $this->services();

        return view('livewire.pages.catalog.services', [
            'services' => $services,
            'types' => [self::TYPE_ALL => __('archive.filter_all')] + collect(ServiceType::cases())->mapWithKeys(fn (ServiceType $case): array => [$case->value => $case->getLabel()])->all(),
        ])->title(__('catalog_services.plural'));
    }
}
