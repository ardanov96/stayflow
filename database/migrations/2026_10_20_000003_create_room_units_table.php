<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();
            $table->foreignId('room_type_id')
                ->constrained('room_types')
                ->restrictOnDelete();
            $table->string('number', 32);
            $table->string('floor', 16)->nullable();
            $table->string('bed_config', 32)->default('king');
            $table->string('status', 32)->default('clean');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'number']);
            $table->index(['organization_id', 'property_id']);
            $table->index('room_type_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_units');
    }
};
