<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memberships join a user to an organization (and optionally to a
     * specific property inside that organization). per DOMAIN_MODEL §2
     * Platform, property_id is nullable and NULL means "all properties
     * in the organization" (e.g. a group owner).
     *
     * Uniqueness per (user, organization, property) is enforced by a
     * UNIQUE index on a VIRTUAL generated column `property_id_key` that
     * maps NULL to 0. This works around the SQL standard that treats
     * NULL as distinct in UNIQUE constraints (so two (user, org, NULL)
     * rows would otherwise be allowed). 0 is never a real property id
     * (auto-increment starts at 1), so the sentinel is safe.
     *
     * The column is VIRTUAL, not STORED: MySQL refuses a foreign key
     * whose ON DELETE referential action is CASCADE, SET NULL or SET
     * DEFAULT when the referencing column is the base column of a
     * STORED generated column ("Cannot add foreign key constraint",
     * error 1215). A VIRTUAL column keeps `ON DELETE SET NULL` legal
     * and still supports the unique index.
     *
     * ON DELETE RESTRICT on user and organization protects against
     * accidental hard deletes. ON DELETE SET NULL on property lets a
     * property be hard-deleted without orphaning the membership
     * (soft-delete on properties does not fire ON DELETE at all).
     */
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->nullable()
                ->constrained('properties')
                ->nullOnDelete();
            $table->string('role', 32);
            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('property_id_key')
                ->virtualAs('COALESCE(property_id, 0)');

            $table->unique(
                ['user_id', 'organization_id', 'property_id_key'],
                'memberships_user_org_property_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
