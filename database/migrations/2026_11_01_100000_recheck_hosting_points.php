<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Points elsewhere" answers given from the host name alone were often wrong (the sites sit on
     * another address of the server); they are forgotten so the next check asks again (T-187).
     */
    public function up(): void
    {
        DB::table('customer_service_items')
            ->where('points_to_server', false)
            ->update(['points_to_server' => null, 'points_checked_at' => null]);
    }

    /**
     * The forgotten answers are a cache and come back with the next check.
     */
    public function down(): void {}
};
