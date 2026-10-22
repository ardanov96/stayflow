<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 100);
            $table->json('description')->nullable();
            $table->string('meal_plan', 32)->default('room_only');
            $table->foreignId('cancellation_policy_id')->nullable()->constrained('property_policies')->nullOnDelete();
            $table->foreignId('parent_rate_plan_id')->nullable()->constrained('rate_plans')->nullOnDelete();
            $table->string('derivation_type', 32)->nullable();
            $table->integer('derivation_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['organization_id', 'property_id']);
            $table->index('parent_rate_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
