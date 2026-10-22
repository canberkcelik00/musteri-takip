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
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_service_id')->nullable()->constrained();
            $table->foreignId('package_id')->nullable()->constrained();
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->string('currency', 3);
            $table->boolean('is_free')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE quote_items ADD CONSTRAINT quote_items_catalog_service_xor_package CHECK ((catalog_service_id IS NULL) <> (package_id IS NULL))');

        Schema::table('customer_services', function (Blueprint $table) {
            $table->foreignId('quote_item_id')->nullable()->unique()->after('package_id')->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quote_item_id');
        });

        Schema::dropIfExists('quote_items');
    }
};
