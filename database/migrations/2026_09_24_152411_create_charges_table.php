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
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('customer_service_id')->nullable()->constrained();
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->date('due_date')->index();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['customer_service_id', 'period_start']);
        });

        DB::statement('ALTER TABLE charges ADD CONSTRAINT charges_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE charges ADD CONSTRAINT charges_period_complete CHECK ((period_start IS NULL) = (period_end IS NULL))');
        DB::statement('ALTER TABLE charges ADD CONSTRAINT charges_period_end_after_period_start CHECK (period_end > period_start)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
