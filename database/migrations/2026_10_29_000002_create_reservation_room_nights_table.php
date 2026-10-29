<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The nights a physical room is booked for (task P2-05).
     *
     * One row per night. The unique index on (room unit, night) is what
     * enforces the invariant 7 of docs/DOMAIN_MODEL.md section 3: *a room
     * unit cannot be assigned to two reservations on overlapping nights*.
     *
     * The database is the arbiter, not the application: two concurrent
     * assignments of the same room on the same night cannot both succeed,
     * whichever one gets there first.
     */
    public function up(): void
    {
        Schema::create('reservation_room_nights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('reservation_room_id')->constrained('reservation_rooms')->cascadeOnDelete();
            $table->foreignId('room_unit_id')->constrained('room_units')->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            // A room unit is booked for at most one reservation per night.
            $table->unique(
                ['room_unit_id', 'date'],
                'reservation_room_nights_unit_date_unique'
            );
            $table->index(['reservation_id', 'date'], 'reservation_room_nights_reservation_index');
            $table->index(['organization_id', 'room_unit_id'], 'reservation_room_nights_org_unit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_room_nights');
    }
};