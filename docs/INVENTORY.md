# INVENTORY — Inventory Days Engine, Availability, Restrictions, Blocks & Holds

Documentation for task `P1-08` (Inventory days engine) and `P1-09`
(Holds: hold, commit, release, expire).

## 1. Overview & Source of Truth

The Inventory engine is the core transactional foundation of Stayflow. It enforces the system's #1 hard rule:
> **Never oversell inventory.** All availability changes go through the Inventory module's public service (`InventoryService`). No raw SQL updates to `inventory_days` outside it.

Availability is **dynamic and derived**:
$$\text{available} = \text{total\_units} - \text{held\_units} - \text{sold\_units} - \text{blocked\_units}$$

Available units are **never stored** in the database to avoid race conditions and synchronization drifts.

## 2. Database Schema & Invariants

### 2.1 `inventory_days`
Pre-materialized daily unit counts per room type across the rolling booking window.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT PK | Auto-increment primary key |
| `organization_id` | BIGINT FK | References `organizations.id` (tenant root) |
| `property_id` | BIGINT FK | References `properties.id` |
| `room_type_id` | BIGINT FK | References `room_types.id` |
| `date` | DATE | Operating calendar date |
| `total_units` | INT | Physical capacity of the room type |
| `held_units` | INT | Temporarily reserved units (in checkout / booking holds) |
| `sold_units` | INT | Confirmed reservation units |
| `blocked_units` | INT | Units taken out of service (maintenance / manual stops) |

- **Constraints**:
  - `UNIQUE (room_type_id, date)`
  - `CHECK (total_units >= held_units + sold_units + blocked_units)` (enforced via database CHECK constraint in MySQL and trigger in SQLite).

### 2.2 `restrictions_days`
Per-date booking restrictions applied to specific rate plans and room types.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT PK | Primary key |
| `organization_id` | BIGINT FK | Tenant root |
| `property_id` | BIGINT FK | Property reference |
| `rate_plan_id` | BIGINT FK | Rate plan reference |
| `room_type_id` | BIGINT FK | Room type reference |
| `date` | DATE | Calendar date |
| `min_stay` | INT NULL | Minimum stay in nights required |
| `max_stay` | INT NULL | Maximum stay in nights allowed |
| `cta` | BOOL | Closed to Arrival (cannot check in on this date) |
| `ctd` | BOOL | Closed to Departure (cannot check out on this date) |
| `stop_sell` | BOOL | Stop sell (no bookings allowed traversing this date) |

- **Constraint**: `UNIQUE (rate_plan_id, room_type_id, date)`

### 2.3 `inventory_blocks`
Tracks room unit blocks for maintenance, renovations, or operational stops.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT PK | Primary key |
| `organization_id` | BIGINT FK | Tenant root |
| `property_id` | BIGINT FK | Property reference |
| `room_type_id` | BIGINT FK | Room type reference |
| `start_date` | DATE | First night blocked |
| `end_date` | DATE | Last night blocked |
| `units` | INT | Number of units blocked |
| `reason` | VARCHAR(255) | Maintenance or operational reason |
| `created_by` | BIGINT FK NULL | Staff user who created the block |

### 2.4 `inventory_holds`
The header of a temporary hold. The units it holds are recorded in
`inventory_hold_lines`.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT PK | Primary key |
| `organization_id` | BIGINT FK | Tenant root |
| `property_id` | BIGINT FK | Property reference |
| `room_type_id` | BIGINT FK | Room type reference |
| `check_in` | DATE | First night of the stay |
| `check_out` | DATE | Departure date (exclusive) |
| `units` | INT | Number of units held per night |
| `status` | VARCHAR(16) | `active`, `committed`, `released` or `expired` |
| `idempotency_key` | VARCHAR(64) | Caller supplied key that makes `hold()` idempotent |
| `expires_at` | TIMESTAMP | Moment the hold dies if it is not committed |
| `reservation_id` | BIGINT NULL | Reservation the hold was committed for (no FK yet, see below) |
| `committed_at` / `released_at` / `expired_at` | TIMESTAMP NULL | When the hold left the `active` state |
| `release_reason` | VARCHAR(64) NULL | Why the hold was released |

- **Constraints**:
  - `UNIQUE (organization_id, idempotency_key)` (`inventory_holds_org_idempotency_unique`) — one hold per organization and key.
  - `INDEX (status, expires_at)` — the expiry job scan.
- `reservation_id` has **no foreign key yet**: the `reservations` table lands in P2-01 and the constraint is added there.

### 2.5 `inventory_hold_lines`
One row per stay date of a hold, so releasing or expiring a hold knows
exactly which `inventory_days` rows must give their units back.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT PK | Primary key |
| `organization_id` | BIGINT FK | Tenant root |
| `hold_id` | BIGINT FK | References `inventory_holds.id` |
| `property_id` | BIGINT FK | Property reference |
| `date` | DATE | The stay date this line covers (`check_in` .. `check_out - 1 day`) |
| `units` | INT | Units held on that date |

- **Constraint**: `UNIQUE (hold_id, date)`.

## 3. Rolling Window Materialization

`inventory_days` rows are materialized up to **24 months (730 days)** into the future per `docs/ARCHITECTURE.md` §5.1.

- **Action**: `App\Modules\Inventory\Application\Actions\MaterializeInventoryDaysAction`
- **Job**: `App\Modules\Inventory\Infrastructure\Jobs\MaterializeInventoryDaysJob` (dispatched daily or on room unit creation)
- **Idempotency**: Existing held, sold, and blocked units are preserved on re-materialization.

## 4. Availability & Quote Algorithm (`quote()`)

Read-only calculation across room types for stay dates ($[\text{checkInDate}, \text{checkOutDate} - 1]$):
1. **Date Validation**: Ensures $\text{checkInDate} < \text{checkOutDate}$ and dates fall within allowable 24-month window.
2. **Materialization Check**: All stay nights must exist in `inventory_days`.
3. **Bottleneck Availability**: Computes $\min(\text{available\_units})$ across all stay nights.
4. **Restriction Verification**:
   - `CTA`: Checked against arrival date ($\text{checkInDate}$).
   - `CTD`: Checked against departure date ($\text{checkOutDate}$).
   - `Stop Sell`: Checked for every night of the stay.
   - `Min Stay / Max Stay`: Checked against total stay nights ($N$).
5. **Pricing Integration**: Links itemized pricing quote via `PricingService::quoteStay()` when `rate_plan_id` is supplied.

## 5. Maintenance & Manual Blocks (`block()` / `unblock()`)

- `block(propertyId, roomTypeId, startDate, endDate, units, reason)`:
  - Validates availability across all dates in range.
  - Atomically increments `blocked_units` inside a database transaction.
  - Inserts `inventory_blocks` record.
  - Emits `inventory.blocked` structured audit log.
- `unblock(blockId)`:
  - Atomically decrements `blocked_units` across the date range.
  - Deletes the block record.
  - Emits `inventory.unblocked` audit log.

## 6. Holds: Place, Commit, Release, Expire (P1-09)

A hold reserves units temporarily: `held_units` goes up, and the units
become `sold_units` when the booking is confirmed, or go back to
availability when the hold is released or expires.

### 6.1 Lifecycle

```
active ──commit──▶ committed    (held_units → sold_units)
   │
   ├──release──▶ released       (held_units back to availability)
   └──expire───▶ expired        (held_units back to availability)
```

The terminal states are final (`App\Modules\Inventory\Domain\Hold\HoldStatus`).

- `commit` and `release` are **idempotent**: running them on a hold that
  already reached the state returns the hold unchanged, the units are
  never moved twice.
- A committed hold cannot be released (`HoldStateException`).
- Every operation takes the organization from the caller (or from the
  tenant context for the public service), never from the row itself
  (AGENTS.md §3.4).

### 6.2 Concurrency algorithm (`docs/ARCHITECTURE.md` §5.3, ADR-0006)

1. `BEGIN`.
2. Lock the **idempotency row first** (`SELECT … FOR UPDATE` on
   `inventory_holds` by `(organization_id, idempotency_key)`), then the
   **inventory rows second** (`inventory_days` of the stay dates, ordered
   by `room_type_id, date`). The order never changes, so concurrent
   writers cannot deadlock against each other.
3. Validate the availability against the locked rows. Available units are
   derived, never stored: `total_units − held_units − sold_units − blocked_units`.
4. Update the counters and insert the hold lines.
5. `COMMIT`, then publish `InventoryChanged` (after commit).
6. On a deadlock or a lock-wait timeout, retry up to 3 times with a
   growing backoff (`RetriesOnContention`); after the last attempt the
   caller gets `InventoryContentionException` ("try again").

Redis is never authoritative (ADR-0006): it may only reduce contention.

### 6.3 Idempotency

`hold()` takes a caller-supplied `idempotencyKey` of 1 to 64 characters,
scoped per organization.

| Replay | Result |
|---|---|
| Same key, same payload (property, room type, dates, units) | The **existing hold** is returned; the counters are not touched again. |
| Same key, different payload | `IdempotencyConflictException`. |
| Same key in another organization | A different hold: the key is scoped by `organization_id`. |
| Empty key, or a key longer than 64 characters | `InvalidArgumentException`. |

The unique index `inventory_holds_org_idempotency_unique` is the last line
of defence: when two requests race on the same key, the loser hits the
unique-constraint violation, re-reads the winning hold and returns it
instead of holding the units twice.

### 6.4 Stay dates

A stay covers the **half-open** range `[check_in, check_out)`: a stay from
the 10th to the 12th holds the nights of the 10th and the 11th.
`inventory_hold_lines` stores one row per stay date, so `release` and
`expire` know exactly which `inventory_days` rows to give back.

### 6.5 Expiry

- **Action**: `App\Modules\Inventory\Application\Actions\ExpireHoldsAction`
  — per organization, one transaction per hold, so a single failure never
  rolls back the other holds.
- **Job**: `App\Modules\Inventory\Infrastructure\Jobs\ExpireHoldsJob`
  (tries 3, backoff 10s, timeout 120s, tenant context propagated).
- **Command**: `php artisan inventory:expire-holds` fans the job out, one
  per organization, so every job keeps its tenant context.
- **Schedule**: every minute, `withoutOverlapping()` (`bootstrap/app.php`).
- A hold is expired when `expires_at <= now()`. It can never be expired
  twice: the transition is a conditional `UPDATE` (`WHERE status = active`),
  so the second run finds the hold in a terminal state and does nothing.

> `docs/ARCHITECTURE.md` §5.3 mentions a `critical` queue for the expiry
> job. The queue topology does not exist in the code base yet (no queue
> names are configured in `config/queue.php`), so the job currently runs on
> the default queue and moves to `critical` together with the worker
> configuration.

### 6.6 Events and audit trail

- `App\Modules\Inventory\Domain\Events\InventoryChanged` implements
  `ShouldDispatchAfterCommit`, so a listener never observes a change that
  was rolled back. It is published with the `event()` helper — the
  `Dispatchable` trait dispatches queued jobs, not events.
  Reasons: `hold.created`, `hold.committed`, `hold.released`, `hold.expired`.
- Structured audit logs through the `Auditor` port:
  `inventory.hold.created`, `inventory.hold.committed`,
  `inventory.hold.released`, `inventory.hold.expired`.

### 6.7 Public API

`InventoryService` (`App\Modules\Inventory\Contracts\InventoryService`):

| Operation | Effect |
|---|---|
| `hold(propertyId, roomTypeId, checkIn, checkOut, units, idempotencyKey, ttlSeconds, userId)` | Reserves the units temporarily |
| `commitHold(holdId, reservationId, userId)` | Turns the held units into sold units |
| `releaseHold(holdId, reason, userId)` | Gives the held units back |
| `getHold(holdId)` | Reads one hold of the current organization |

`commitHold`, `releaseHold` and `getHold` address a hold by id only, so
the organization comes from the tenant context and never from the row.

`cancel(reservation)` (giving back the **sold** units of a confirmed
reservation) belongs to the Reservations module (P2-01): the
`reservations` table does not exist yet.

## 7. Public Contracts & Endpoints

### 7.1 `InventoryService` Interface
Located in `App\Modules\Inventory\Contracts\InventoryService`. All sibling modules (BookingEngine, Reservations, Distribution) access inventory exclusively through this contract.

### 7.2 HTTP Routes
Under `/api/inventory/`:
- `POST /availability/quote` (gated by `rate_plans.read`)
- `GET /availability` (gated by `rate_plans.read`)
- `POST /materialize` (gated by `inventory.manage`)
- `POST /blocks` (gated by `inventory.manage`)
- `DELETE /blocks/{id}` (gated by `inventory.manage`)
- `POST /restrictions` (gated by `restrictions.manage`)
