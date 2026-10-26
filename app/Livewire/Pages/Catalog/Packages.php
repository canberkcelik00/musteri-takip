<?php

namespace App\Livewire\Pages\Catalog;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\WithTable;
use App\Models\Package;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The package list (docs/UI.md §5 Katalog), by name. The name opens the edit window; the row's one button starts a
 * quote for the package. Archived packages are hidden unless asked for.
 */
#[Layout('layouts.app')]
class Packages extends Component
{
    use AuthorizesPermission;
    use WithTable;

    public const string ARCHIVE_ACTIVE = 'active';

    public const string ARCHIVE_ARCHIVED = 'archived';

    public const string ARCHIVE_ALL = 'all';

    #[Url(except: self::ARCHIVE_ACTIVE)]
    public string $archive = self::ARCHIVE_ACTIVE;

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'price'];
    }

    public function updatedArchive(): void
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
     * @return LengthAwarePaginator<int, Package>
     */
    private function packages(): LengthAwarePaginator
    {
        $query = Package::query()->with('catalogServices:id,name');

        $term = trim($this->search);

        if ($term !== '') {
            $query->where('name', 'like', '%'.addcslashes($term, '\\%_').'%');
        }

        match ($this->archive) {
            self::ARCHIVE_ARCHIVED => $query->onlyArchived(),
            self::ARCHIVE_ALL => null,
            default => $query->notArchived(),
        };

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
        return view('livewire.pages.catalog.packages', ['packages' => $this->packages()])->title(__('packages.plural'));
    }
}
