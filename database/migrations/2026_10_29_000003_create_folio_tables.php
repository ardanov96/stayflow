<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The folio of a reservation (task P2-09).
     *
     * The ledger is **append-only**: an entry is never updated and never
     * deleted. A correction is a new entry that reverses the old one. The
     * database says so with a trigger, not only the application.
     */
    public function up(): void
    {
        Schema::create('folios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            // One folio per reservation.
            $table->foreignId('reservation_id')->unique()->constrained('reservations')->cascadeOnDelete();
            // open or closed.
            $table->string('status', 16)->default('open');
            $table->string('currency', 3);
            // The sum of the entries: it always equals the balance.
            $table->bigInteger('balance_minor')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'folios_org_status_index');
        });

        Schema::create('folio_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained('folios')->cascadeOnDelete();
            // charge, tax, payment, refund, adjustment, reversal.
            $table->string('type', 24);
            // Signed: a charge is positive, a payment is negative.
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            // The operating day the entry belongs to.
            $table->date('business_date');
            $table->timestamp('posted_at');
            // The entry this one reverses.
            $table->foreignId('reversal_of_id')->nullable()->constrained('folio_entries')->nullOnDelete();
            // What posted the entry (room, folio, night audit, ...).
            $table->string('source', 64)->nullable();
            // The taxes that applied when it was posted.
            $table->json('tax_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // No updated_at: the entry is never updated.
            $table->timestamp('created_at');

            $table->index(['folio_id', 'business_date'], 'folio_entries_folio_business_date_index');
            $table->index('reversal_of_id', 'folio_entries_reversal_of_index');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained('folios')->cascadeOnDelete();
            // cash, card, transfer, gateway, ...
            $table->string('method', 32);
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            // pending, paid, failed, refunded.
            $table->string('status', 16)->default('pending');
            $table->string('gateway_ref', 128)->nullable();
            // The key of the request: the same payment is never taken twice.
            $table->string('idempotency_key', 64);
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'payments_org_idempotency_unique');
        });

        // The business dates the night audit closed.
        Schema::create('business_date_closures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['property_id', 'date'], 'business_date_closures_property_date_unique');
        });

        $this->guardTheLedger();
    }

    /**
     * The ledger is append-only: the database refuses an update and a
     * delete of an entry.
     */
    private function guardTheLedger(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared('
                CREATE TRIGGER IF NOT EXISTS folio_entries_no_update BEFORE UPDATE ON folio_entries
                BEGIN
                    SELECT RAISE(ABORT, "A folio entry is never updated.");
                END;
            ');

            DB::unprepared('
                CREATE TRIGGER IF NOT EXISTS folio_entries_no_delete BEFORE DELETE ON folio_entries
                BEGIN
                    SELECT RAISE(ABORT, "A folio entry is never deleted.");
                END;
            ');

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared('
                CREATE TRIGGER folio_entries_no_update BEFORE UPDATE ON folio_entries
                FOR EACH ROW SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "A folio entry is never updated."
            ');

            DB::unprepared('
                CREATE TRIGGER folio_entries_no_delete BEFORE DELETE ON folio_entries
                FOR EACH ROW SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "A folio entry is never deleted."
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_date_closures');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('folio_entries');
        Schema::dropIfExists('folios');
    }
};