<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('UTC')->after('status');
            $table->string('currency', 3)->default('IDR')->after('timezone');
            $table->string('check_in_time', 8)->default('14:00')->after('currency');
            $table->string('check_out_time', 8)->default('11:00')->after('check_in_time');
            $table->string('email')->nullable()->after('check_out_time');
            $table->string('phone', 32)->nullable()->after('email');
            $table->json('address')->nullable()->after('phone');
            $table->json('description')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn([
                'timezone',
                'currency',
                'check_in_time',
                'check_out_time',
                'email',
                'phone',
                'address',
                'description',
            ]);
        });
    }
};
