<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calendar events (Faz 15). A repeating event is one row: the repeats inside a visible range are expanded when
     * read, never stored. Every user and customer reference restricts deletion so the history keeps its "who" and "for
     * whom". `calendar_event_reminders` holds one row per reminder already sent, so a second run sends nothing.
     */
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('starts_on');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('all_day')->default(false);
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('remind_minutes')->nullable();
            $table->string('repeat')->default('none');
            $table->date('repeat_until')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('starts_on');
            $table->index(['customer_id', 'starts_on']);
        });

        Schema::create('calendar_event_user', function (Blueprint $table) {
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->primary(['calendar_event_id', 'user_id']);
        });

        Schema::create('calendar_event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->date('occurs_on');
            $table->timestamp('sent_at');

            $table->unique(['calendar_event_id', 'occurs_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_event_reminders');
        Schema::dropIfExists('calendar_event_user');
        Schema::dropIfExists('calendar_events');
    }
};
