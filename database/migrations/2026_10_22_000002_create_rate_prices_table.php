<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained('rate_plans')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('base_price_minor');
            $table->char('currency', 3)->default('IDR');
            $table->unsignedBigInteger('extra_adult_price_minor')->default(0);
            $table->unsignedBigInteger('extra_child_price_minor')->default(0);
            $table->timestamps();

            $table->unique(['rate_plan_id', 'room_type_id', 'date'], 'rate_prices_plan_room_date_unique');
            $table->index(['organization_id', 'property_id']);
            $table->index(['room_type_id', 'date']);
            $table->index(['rate_plan_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_prices');
    }
};
