<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_room_id')->constrained('meeting_rooms')->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('start_at')->index();
            $table->dateTime('end_at')->index();
            $table->string('organizer_name')->nullable();
            $table->text('internal_attendees')->nullable();
            $table->text('external_attendees')->nullable();
            $table->string('meeting_link')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('snacks_requested')->default(false);
            $table->boolean('is_online')->default(false);
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['meeting_room_id', 'start_at', 'end_at'], 'meeting_bookings_room_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_bookings');
    }
};
