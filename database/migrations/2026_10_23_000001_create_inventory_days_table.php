<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_units')->default(0);
            $table->unsignedInteger('held_units')->default(0);
            $table->unsignedInteger('sold_units')->default(0);
            $table->unsignedInteger('blocked_units')->default(0);
            $table->timestamps();

            $table->unique(['room_type_id', 'date'], 'inventory_days_room_type_date_unique');
            $table->index(['organization_id', 'property_id', 'date'], 'inventory_days_org_prop_date_idx');
            $table->index(['property_id', 'date'], 'inventory_days_prop_date_idx');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared('
                CREATE TRIGGER IF NOT EXISTS check_inventory_days_insert_units BEFORE INSERT ON inventory_days
                BEGIN
                    SELECT CASE
                        WHEN NEW.total_units < (NEW.held_units + NEW.sold_units + NEW.blocked_units)
                        THEN RAISE(ABORT, "CHECK constraint failed: total_units >= held_units + sold_units + blocked_units")
                    END;
                END;
            ');

            DB::unprepared('
                CREATE TRIGGER IF NOT EXISTS check_inventory_days_update_units BEFORE UPDATE ON inventory_days
                BEGIN
                    SELECT CASE
                        WHEN NEW.total_units < (NEW.held_units + NEW.sold_units + NEW.blocked_units)
                        THEN RAISE(ABORT, "CHECK constraint failed: total_units >= held_units + sold_units + blocked_units")
                    END;
                END;
            ');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE inventory_days ADD CONSTRAINT check_inventory_days_units CHECK (total_units >= held_units + sold_units + blocked_units)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS check_inventory_days_insert_units');
            DB::unprepared('DROP TRIGGER IF EXISTS check_inventory_days_update_units');
        }

        Schema::dropIfExists('inventory_days');
    }
};
