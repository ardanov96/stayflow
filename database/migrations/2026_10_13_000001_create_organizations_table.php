<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organizations are the top-level tenants (ADR-0003). One row is
     * one customer account: a hotel group or an independent owner.
     * The table does NOT have an organization_id column because it
     * IS the organization; the BelongsToTenant trait is not applied
     * to this model.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 16)->default('active');
            $table->string('default_locale', 8)->default('en');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
