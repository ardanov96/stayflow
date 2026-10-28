<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reservations of the PMS (task P2-02).
     *
     * The tables follow docs/DOMAIN_MODEL.md section 2: a reservation, its
     * rooms, its guests and the trail of its events.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

            // The human-readable code of the reservation, unique per
            // property: the staff reads it out loud on the phone.
            $table->string('code', 32);
            $table->string('status', 24)->default('pending_payment');
            // direct, pms, ota:<channel>
            $table->string('source', 32)->default('pms');
            $table->string('channel_ref', 128)->nullable();

            // The primary guest. The guests table lands with the Guests
            // module (P5-01), so the reference is soft for now.
            $table->unsignedBigInteger('guest_id');

            // The stay dates: the nights of the hotel, not instants.
            $table->date('arrival');
            $table->date('departure');
            $table->unsignedInteger('adults')->default(1);
            $table->unsignedInteger('children')->default(0);

            // The money: minor units, with the currency beside it.
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('IDR');

            // The number of the change: the optimistic lock of the row.
            $table->unsignedInteger('version')->default(1);

            // The inventory hold this reservation came from.
            $table->foreignId('hold_id')->nullable()->constrained('inventory_holds')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'code'], 'reservations_property_code_unique');
            $table->index(['organization_id', 'status'], 'reservations_org_status_index');
            $table->index(['organization_id', 'arrival'], 'reservations_org_arrival_index');
            $table->index('guest_id', 'reservations_guest_index');
        });

        Schema::create('reservation_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained('rate_plans')->nullOnDelete();
            // The physical room: assigned later, and never for a villa that
            // is sold as a whole.
            $table->foreignId('room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();

            // The nights it covers, with the price of each night.
            $table->json('nights');
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();

            $table->index(['reservation_id', 'room_type_id'], 'reservation_rooms_reservation_room_type_index');
        });

        Schema::create('reservation_guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            // Soft for now: the guests table lands with P5-01.
            $table->unsignedBigInteger('guest_id');
            // primary or companion.
            $table->string('role', 16)->default('companion');
            $table->timestamps();

            $table->unique(['reservation_id', 'guest_id'], 'reservation_guests_reservation_guest_unique');
        });

        Schema::create('reservation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            // created, modified, status_changed, assigned, ...
            $table->string('type', 32);
            $table->json('payload')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('at');
            $table->timestamps();

            $table->index(['reservation_id', 'id'], 'reservation_events_reservation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_events');
        Schema::dropIfExists('reservation_guests');
        Schema::dropIfExists('reservation_rooms');
        Schema::dropIfExists('reservations');
    }
};