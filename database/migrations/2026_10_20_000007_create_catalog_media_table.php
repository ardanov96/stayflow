<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();
            $table->string('mediable_type');
            $table->unsignedBigInteger('mediable_id');
            $table->string('disk', 32)->default('public');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 64)->default('image/webp');
            $table->unsignedInteger('file_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'property_id']);
            $table->index(['mediable_type', 'mediable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_media');
    }
};
