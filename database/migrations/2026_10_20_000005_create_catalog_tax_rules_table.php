<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('kind', 32); // tax, service_charge (TaxKind)
            $table->unsignedInteger('rate_bps'); // basis points: 1000 = 10%
            $table->boolean('is_inclusive')->default(false);
            $table->string('applies_to', 32)->default('all'); // room, addon, all (TaxAppliesTo)
            $table->unsignedSmallInteger('order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'property_id']);
            $table->index(['property_id', 'is_active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
