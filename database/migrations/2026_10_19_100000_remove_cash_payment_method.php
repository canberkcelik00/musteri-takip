<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The business takes no cash (G-5B.3), so the default "Nakit" method is removed. A method no
 * payment uses is deleted; a used one is archived so the old payments keep their method. It is
 * left alone when it is the only active method (the payment form needs at least one). Raw
 * `DB::table()` calls, not the model, so later model changes cannot affect it (see T-38).
 * Running it again changes nothing. The deleted row is not restored on rollback.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $cash = DB::table('payment_methods')->where('name', 'Nakit')->first();

        if ($cash === null) {
            return;
        }

        $otherActiveMethods = DB::table('payment_methods')
            ->where('id', '!=', $cash->id)
            ->whereNull('archived_at')
            ->exists();

        if (! $otherActiveMethods) {
            return;
        }

        if (DB::table('payments')->where('payment_method_id', $cash->id)->exists()) {
            if ($cash->archived_at === null) {
                DB::table('payment_methods')->where('id', $cash->id)->update(['archived_at' => now(), 'updated_at' => now()]);
            }

            return;
        }

        DB::table('payment_methods')->where('id', $cash->id)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
