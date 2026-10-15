<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->boolean('is_renewal')->default(false)->after('cancel_reason');
            $table->dateTime('reminder_sent_at')->nullable()->after('is_renewal');
        });

        // Existing period charges: a renewal when its period starts after the service's start
        // date, or when another charge of the same service ends exactly where this one starts.
        // The ids are read first: MySQL does not allow updating `charges` while a subquery reads it.
        $renewalIds = DB::table('charges')
            ->whereNotNull('customer_service_id')
            ->whereNotNull('period_start')
            ->where(function ($query): void {
                $query
                    ->whereExists(function ($subQuery): void {
                        $subQuery->selectRaw('1')
                            ->from('customer_services')
                            ->whereColumn('customer_services.id', 'charges.customer_service_id')
                            ->whereColumn('customer_services.start_date', '<', 'charges.period_start');
                    })
                    ->orWhereExists(function ($subQuery): void {
                        $subQuery->selectRaw('1')
                            ->from('charges as previous')
                            ->whereColumn('previous.customer_service_id', 'charges.customer_service_id')
                            ->whereColumn('previous.period_end', 'charges.period_start');
                    });
            })
            ->pluck('id');

        foreach ($renewalIds->chunk(500) as $ids) {
            DB::table('charges')->whereIn('id', $ids->all())->update(['is_renewal' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['is_renewal', 'reminder_sent_at']);
        });
    }
};
