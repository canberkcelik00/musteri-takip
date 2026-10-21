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
        Schema::create('account_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('remote_username');
            $table->json('resources')->nullable();
            $table->json('databases')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('warning_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'remote_username']);
        });

        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('remote_username');
            $table->string('address');
            $table->string('domain');
            $table->unsignedBigInteger('used_bytes');
            $table->unsignedBigInteger('quota_bytes')->nullable();
            $table->boolean('login_suspended')->default(false);
            $table->boolean('incoming_suspended')->default(false);
            $table->timestamp('warning_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'remote_username', 'address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailboxes');
        Schema::dropIfExists('account_usages');
    }
};
