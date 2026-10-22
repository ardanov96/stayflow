<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->nullOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('category', 32)->default('general');
            $table->string('icon', 64)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'property_id']);
            $table->index('category');
        });

        Schema::create('property_amenities', function (Blueprint $table): void {
            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();
            $table->foreignId('amenity_id')
                ->constrained('amenities')
                ->cascadeOnDelete();

            $table->primary(['property_id', 'amenity_id']);
        });

        Schema::create('room_type_amenities', function (Blueprint $table): void {
            $table->foreignId('room_type_id')
                ->constrained('room_types')
                ->cascadeOnDelete();
            $table->foreignId('amenity_id')
                ->constrained('amenities')
                ->cascadeOnDelete();

            $table->primary(['room_type_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_amenities');
        Schema::dropIfExists('property_amenities');
        Schema::dropIfExists('amenities');
    }
};
