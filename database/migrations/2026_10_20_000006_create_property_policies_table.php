<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();
            $table->string('type', 32);
            $table->string('title');
            $table->json('description')->nullable();
            $table->json('structured_terms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['property_id', 'type']);
            $table->index(['organization_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_policies');
    }
};
