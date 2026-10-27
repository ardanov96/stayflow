<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restrictions_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained('rate_plans')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('min_stay')->nullable();
            $table->unsignedSmallInteger('max_stay')->nullable();
            $table->boolean('cta')->default(false);
            $table->boolean('ctd')->default(false);
            $table->boolean('stop_sell')->default(false);
            $table->timestamps();

            $table->unique(['rate_plan_id', 'room_type_id', 'date'], 'restrictions_days_plan_room_date_unique');
            $table->index(['organization_id', 'property_id', 'date'], 'restrictions_days_org_prop_date_idx');
            $table->index(['property_id', 'date'], 'restrictions_days_prop_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restrictions_days');
    }
};
