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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_company')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('status')->default('draft');
            $table->date('valid_until');
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_customer_or_recipient CHECK (customer_id IS NOT NULL OR (recipient_name IS NOT NULL AND recipient_email IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
