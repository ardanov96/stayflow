<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\Support\ConcurrencyConnection;
use Tests\Concurrency\Support\ConcurrencySchema;
use Tests\TestCase;

/**
 * Base class of the concurrency suite (task P1-10).
 *
 * The suite runs against a real MySQL 8 server, on a database of its own.
 * It deliberately does NOT use `RefreshDatabase`: the scenarios need the
 * fixtures to be committed and visible to the worker processes, so the
 * rows are cleaned up explicitly after every test instead.

 * See docs/CONCURRENCY.md for the proofs these tests rely on.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    protected Organization $organization;

    protected Property $property;

    protected OrganizationContext $context;

    protected TenantContext $tenant;

    protected InventoryService $inventoryService;

    protected string $checkIn;

    protected string $checkOut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardConcurrencySuite();

        config(['database.default' => ConcurrencyConnection::NAME]);
        DB::purge(ConcurrencyConnection::NAME);

        ConcurrencySchema::prepare();

        $this->context = app(OrganizationContext::class);
        $this->inventoryService = app(InventoryService::class);

        $this->organization = Organization::factory()->create();
        $this->property = Property::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->tenant = TenantContext::forOrganizationAndProperty(
            $this->organization->id,
            $this->property->id,
        );

        $this->checkIn = now()->addDay()->format('Y-m-d');
        $this->checkOut = now()->addDays(3)->format('Y-m-d');
    }

    protected function tearDown(): void
    {
        $this->truncateAll();

        parent::tearDown();
    }

    /**
     * Create a room type of the test property and materialize its days.
     */
    protected function createRoomType(int $totalUnits, ?string $startDate = null, ?string $endDate = null): RoomType
    {
        $roomType = RoomType::factory()->create([
            'organization_id' => $this->organization->id,
            'property_id' => $this->property->id,
            'total_units' => $totalUnits,
        ]);

        $this->materialize($roomType, $startDate, $endDate);

        return $roomType;
    }

    /**
     * Materialize the inventory days of a room type.
     */
    protected function materialize(RoomType $roomType, ?string $startDate = null, ?string $endDate = null): void
    {
        $this->context->swap($this->tenant, function () use ($roomType, $startDate, $endDate): void {
            $this->inventoryService->materializeDays(
                propertyId: $this->property->id,
                startDate: $startDate ?? $this->checkIn,
                endDate: $endDate ?? now()->addDays(30)->format('Y-m-d'),
                roomTypeIds: [$roomType->id],
            );
        });
    }

    /**
     * Build a worker job for the given operation.
     *
     * @param  array<string, mixed>  $operation
     * @param  list<array<string, mixed>>  $operations
     * @return array<string, mixed>
     */
    protected function job(
        string $worker,
        array $operation = [],
        array $operations = [],
    ): array {
        return [
            'worker' => $worker,
            'connection' => ConcurrencyConnection::config(),
            'organization_id' => $this->organization->id,
            'property_id' => $this->property->id,
            'operations' => $operations !== [] ? $operations : [$operation],
        ];
    }

    /**
     * A barrier descriptor for an operation.
     *
     * @return array{name: string, parties: int, timeout: int}
     */
    protected function barrier(string $name, int $parties): array
    {
        return [
            'name' => $name,
            'parties' => $parties,
            'timeout' => 180,
        ];
    }

    /**
     * Distribute the attempts over the workers in waves.
     *
     * Every wave gets its own barrier, so all the workers of a wave hit the
     * database at the same instant while the number of open MySQL sessions
     * stays bounded by the number of workers.
     *
     * @param  callable(int): array<string, mixed>  $operation  Builds the operation of an attempt.
     * @return list<array<string, mixed>>
     */
    protected function waveJobs(int $attempts, int $workers, callable $operation, string $prefix): array
    {
        $jobs = [];

        for ($worker = 0; $worker < $workers; $worker++) {
            $operations = [];
            $wave = 0;

            for ($index = $worker; $index < $attempts; $index += $workers) {
                $operations[] = $operation($index) + [
                    'barrier' => $this->barrier(
                        $prefix.'-wave-'.$wave,
                        min($workers, $attempts - ($wave * $workers)),
                    ),
                ];

                $wave++;
            }

            if ($operations !== []) {
                $jobs[] = $this->job($prefix.'-'.$worker, operations: $operations);
            }
        }

        return $jobs;
    }

    /**
     * The counters of one inventory day.
     *
     * @return array{total: int, held: int, sold: int, blocked: int}
     */
    protected function counters(RoomType $roomType, string $date): array
    {
        $row = $this->context->swap($this->tenant, fn () => DB::table('inventory_days')
            ->where('room_type_id', $roomType->id)
            ->where('date', $date)
            ->first());

        return [
            'total' => (int) ($row->total_units ?? 0),
            'held' => (int) ($row->held_units ?? 0),
            'sold' => (int) ($row->sold_units ?? 0),
            'blocked' => (int) ($row->blocked_units ?? 0),
        ];
    }

    /**
     * The number of holds of the test organization, per status.
     *
     * @return array<string, int>
     */
    protected function holdCounts(): array
    {
        /** @var list<object{status: string, total: int}> $rows */
        $rows = $this->context->swap($this->tenant, fn () => DB::table('inventory_holds')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->all());

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * The counters every inventory day must show, derived from the holds
     * themselves.
     *
     * This is the invariant the concurrency suite proves: the counters are
     * never anything but the sum of the holds that cover the day.
     *
     * @return array<int, array<string, array{held: int, sold: int}>>
     */
    protected function expectedCounters(): array
    {
        /** @var list<object{room_type_id: int, date: string, status: string, units: int}> $rows */
        $rows = DB::table('inventory_hold_lines')
            ->join('inventory_holds', 'inventory_holds.id', '=', 'inventory_hold_lines.hold_id')
            ->where('inventory_holds.organization_id', $this->organization->id)
            ->whereIn('inventory_holds.status', ['active', 'committed'])
            ->select([
                'inventory_holds.room_type_id',
                'inventory_hold_lines.date',
                'inventory_holds.status',
                'inventory_hold_lines.units',
            ])
            ->get()
            ->all();

        $expected = [];

        foreach ($rows as $row) {
            $roomTypeId = (int) $row->room_type_id;
            $date = (string) $row->date;

            $expected[$roomTypeId][$date] ??= ['held' => 0, 'sold' => 0];

            if ($row->status === 'active') {
                $expected[$roomTypeId][$date]['held'] += (int) $row->units;

                continue;
            }

            $expected[$roomTypeId][$date]['sold'] += (int) $row->units;
        }

        return $expected;
    }

    /**
     * Every inventory day of the test organization with its counters.
     *
     * @return array<int, array<string, array{total: int, held: int, sold: int, blocked: int}>>
     */
    protected function allCounters(): array
    {
        /** @var list<object{room_type_id: int, date: string, total_units: int, held_units: int, sold_units: int, blocked_units: int}> $rows */
        $rows = DB::table('inventory_days')
            ->where('organization_id', $this->organization->id)
            ->orderBy('room_type_id')
            ->orderBy('date')
            ->get()
            ->all();

        $counters = [];

        foreach ($rows as $row) {
            $counters[(int) $row->room_type_id][(string) $row->date] = [
                'total' => (int) $row->total_units,
                'held' => (int) $row->held_units,
                'sold' => (int) $row->sold_units,
                'blocked' => (int) $row->blocked_units,
            ];
        }

        return $counters;
    }

    /**
     * Empty the concurrency database.
     *
     * The database belongs to the suite, so every test starts from an
     * empty schema. Truncating (instead of deleting the tenant rows)
     * keeps the cleanup independent from the foreign keys between the
     * platform and the inventory tables.
     */
    private function truncateAll(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (DB::select('SHOW TABLES') as $row) {
                /** @var array<string, mixed> $values */
                $values = (array) $row;
                $table = (string) array_values($values)[0];

                DB::statement('TRUNCATE TABLE `'.$table.'`');
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function guardConcurrencySuite(): void
    {
        if (ConcurrencyConnection::isDisabled()) {
            $this->markTestSkipped('The concurrency suite is disabled (CONCURRENCY_TESTS=0).');
        }

        if (! ConcurrencyConnection::isReachable()) {
            if (ConcurrencyConnection::isRequired()) {
                $this->fail(
                    'The concurrency suite is required (CONCURRENCY_TESTS=1) '
                    .'but the MySQL 8 server is not reachable.'
                );
            }

            $this->markTestSkipped(
                'No MySQL 8 server is reachable for the concurrency suite. '
                .'Set CONCURRENCY_DB_* to point at one.'
            );
        }
    }
}
