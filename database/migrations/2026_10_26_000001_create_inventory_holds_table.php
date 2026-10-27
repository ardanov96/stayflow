<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('units');
            // active -> committed | released | expired. Mirrors
            // App\Modules\Inventory\Domain\Hold\HoldStatus.
            $table->string('status', 16)->default('active');
            // Caller-supplied key that makes hold() idempotent. Scoped per
            // organization: the same key in two organizations is two holds.
            $table->string('idempotency_key', 64);
            $table->timestamp('expires_at');
            // The Reservations module lands in P2-01; the foreign key is
            // added there, once the `reservations` table exists.
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('release_reason', 64)->nullable();
            $table->timestamps();

            // Idempotency: one hold per (organization, key).
            $table->unique(['organization_id', 'idempotency_key'], 'inventory_holds_org_idempotency_unique');
            // Expiry job scan: active holds past their TTL.
            $table->index(['status', 'expires_at'], 'inventory_holds_status_expires_idx');
            $table->index(['organization_id', 'property_id', 'status'], 'inventory_holds_org_prop_status_idx');
            $table->index(['room_type_id', 'check_in'], 'inventory_holds_room_type_check_in_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_holds');
    }
};