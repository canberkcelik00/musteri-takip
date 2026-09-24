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
        Schema::create('customer_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('catalog_service_id')->nullable()->constrained();
            $table->foreignId('package_id')->nullable()->constrained();
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->string('currency', 3);
            $table->unsignedSmallInteger('period_months')->default(12);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_free')->default(false);
            $table->string('status')->default('active');
            $table->date('cancelled_on')->nullable();
            $table->text('cancel_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE customer_services ADD CONSTRAINT customer_services_catalog_service_xor_package CHECK ((catalog_service_id IS NULL) <> (package_id IS NULL))');
        DB::statement('ALTER TABLE customer_services ADD CONSTRAINT customer_services_end_date_after_start_date CHECK (end_date > start_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_services');
    }
};
