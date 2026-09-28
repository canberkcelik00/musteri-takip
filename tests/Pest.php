<?php

use App\Livewire\TopbarActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
