<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_hold_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('hold_id')->constrained('inventory_holds')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            // The stay date this line covers (check-in .. check-out - 1 day).
            $table->date('date');
            $table->unsignedInteger('units');
            $table->timestamps();

            // One line per stay date: release/expiry know exactly which
            // inventory_days rows they must give back.
            $table->unique(['hold_id', 'date'], 'inventory_hold_lines_hold_date_unique');
            $table->index(['organization_id', 'property_id'], 'inventory_hold_lines_org_prop_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_hold_lines');
    }
};