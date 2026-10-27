# INVENTORY — Inventory Days Engine, Availability, Restrictions & Blocks

Documentation for task `P1-08` (Inventory days engine).

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

## 6. Public Contracts & Endpoints

### 6.1 `InventoryService` Interface
Located in `App\Modules\Inventory\Contracts\InventoryService`. All sibling modules (BookingEngine, Reservations, Distribution) access inventory exclusively through this contract.

### 6.2 HTTP Routes
Under `/api/inventory/`:
- `POST /availability/quote` (gated by `rate_plans.read`)
- `GET /availability` (gated by `rate_plans.read`)
- `POST /materialize` (gated by `inventory.manage`)
- `POST /blocks` (gated by `inventory.manage`)
- `DELETE /blocks/{id}` (gated by `inventory.manage`)
- `POST /restrictions` (gated by `restrictions.manage`)
