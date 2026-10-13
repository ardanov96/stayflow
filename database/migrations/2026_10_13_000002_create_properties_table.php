<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Properties are the second-level tenants: one row per hotel/villa
     * inside an organization. The Property model lives in the Catalog
     * module; the table lives here so the migration list is the single
     * source of truth for schema.
     *
     * `organization_id` is the tenant column. UNIQUE(organization_id, slug)
     * gives per-tenant URL slugs while allowing the same slug across
     * different organizations. ON DELETE RESTRICT protects against
     * accidental hard deletes of organizations (we soft-delete instead).
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
