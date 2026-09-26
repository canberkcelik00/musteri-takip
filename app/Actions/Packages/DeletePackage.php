<?php

namespace App\Actions\Packages;

use App\Exceptions\RecordInUse;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

class DeletePackage
{
    /**
     * Delete a package that was sold to no customer (its content rows go with it); one in use is
     * archived instead. The row is locked so a concurrent assignment cannot slip in between the
     * check and the delete.
     *
     * @throws RecordInUse
     */
    public function handle(Package $package): void
    {
        DB::transaction(function () use ($package): void {
            $locked = Package::query()->lockForUpdate()->find($package->getKey());

            if ($locked === null) {
                return;
            }

            if ($locked->isInUse()) {
                throw new RecordInUse('package');
            }

            $locked->delete();
        });
    }
}
