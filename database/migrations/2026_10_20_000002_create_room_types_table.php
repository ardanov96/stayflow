<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->json('description')->nullable();
            $table->unsignedSmallInteger('base_occupancy')->default(2);
            $table->unsignedSmallInteger('max_adults')->default(2);
            $table->unsignedSmallInteger('max_children')->default(0);
            $table->unsignedInteger('total_units')->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['organization_id', 'property_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
