<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catalog_services', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->string('currency', 3)->default('TRY');
            $table->json('details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_services');
    }
};
