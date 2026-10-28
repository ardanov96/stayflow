<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The settings of the organizations and of the properties (task P1-13).
     *
     * A setting belongs to the organization or to a property of it. The
     * value is JSON, so a setting can hold a number, a flag or a short
     * text without a column per setting.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Null for a setting of the organization itself.
            $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
            $table->string('key', 64);
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One value per setting and per scope.
            $table->unique(
                ['organization_id', 'property_id', 'key'],
                'settings_org_property_key_unique'
            );
            $table->index(['organization_id', 'property_id'], 'settings_org_property_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};