<?php

namespace App\Actions\Servers;

use App\Exceptions\RecordInUse;
use App\Models\Server;
use Illuminate\Support\Facades\DB;

class DeleteServer
{
    /**
     * Delete a server nothing was pulled from; one with pulled packages or accounts is archived
     * instead. The row is locked so a concurrent pull cannot slip in between the check and the delete.
     *
     * @throws RecordInUse
     */
    public function handle(Server $server): void
    {
        DB::transaction(function () use ($server): void {
            $locked = Server::query()->lockForUpdate()->find($server->getKey());

            if ($locked === null) {
                return;
            }

            if ($locked->isInUse()) {
                throw new RecordInUse('server');
            }

            $locked->delete();
        });
    }
}
