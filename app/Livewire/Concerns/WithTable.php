<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * State of a list page (docs/UI.md §5): search, sort and page live in the URL so a list can be bookmarked and
 * the back button works. A page that uses it names the columns it may be sorted by.
 */
trait WithTable
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    /**
     * The column keys the list may be sorted by.
     *
     * @return list<string>
     */
    abstract protected function sortableColumns(): array;

    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * The sort the URL asks for, or null (the page's default order) when it is not a sortable column.
     *
     * @return array{string, 'asc'|'desc'}|null
     */
    protected function requestedSort(): ?array
    {
        if (! in_array($this->sort, $this->sortableColumns(), true)) {
            return null;
        }

        return [$this->sort, $this->direction === 'desc' ? 'desc' : 'asc'];
    }

    protected function perPage(): int
    {
        return 25;
    }
}
