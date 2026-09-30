<?php

use App\Filament\Support\RecordActionsSection;
use App\Livewire\TopbarActions;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * The topbar's "Ödeme al" and "Yeni" (G-4.13), used by any test that used to reach "Ödeme al" or
 * "Elle borç" as a header action of a resource list.
 */
function topbarActions()
{
    return Livewire::test(TopbarActions::class);
}

/**
 * A rarer action inside the named section of a record's edit panel (G-4.17): the path of the
 * edit action followed by the nested one, in the normal or the danger row of the section.
 *
 * @return list<TestAction>
 */
function sectionAction(TestAction $panel, string $name, bool $danger = false): array
{
    return [
        $panel,
        TestAction::make($name)->schemaComponent($danger ? RecordActionsSection::DANGER_ACTIONS_KEY : RecordActionsSection::ACTIONS_KEY),
    ];
}

/**
 * Whether the Livewire test component offers the (nested) action: an action that is hidden, or
 * whose whole section is hidden, cannot be resolved at all.
 *
 * @param  list<TestAction>|TestAction  $path
 */
function actionOffered(mixed $component, array|TestAction $path): bool
{
    try {
        $component->assertActionVisible($path);
    } catch (ActionNotResolvableException|AssertionFailedError) {
        return false;
    }

    return true;
}
