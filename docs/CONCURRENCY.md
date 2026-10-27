# CONCURRENCY — inventory contention, idempotency and the proof suite

Documentation for task `P1-10` (concurrency and invariant test suite).

## 1. Why this document exists

Overbooking is the costliest failure of the platform (`AGENTS.md` §3.1).
The inventory engine therefore never trusts a number it read earlier: every
availability change happens inside a transaction that locks the rows it
is about to change, in a fixed order, and every terminal transition of a
hold is guarded by the current status.

This document describes:

1. the concurrency algorithm the Inventory module implements;
2. the races the algorithm has to survive, and how each one is resolved;
3. the test suite that proves it, on a **real MySQL 8 server**.

The proofs live in `tests/Concurrency/`. They are not unit tests: they run
real transactions in real, independent processes, against a real InnoDB.
SQLite is not a substitute — it serializes the writes and would hide every
race the suite exists to catch.

## 2. The algorithm

### 2.1 `hold()`

```
BEGIN
  INSERT INTO inventory_holds (organization_id, idempotency_key, ...)   -- claim the key
  SELECT ... FROM inventory_days
    WHERE room_type_id IN (...) AND date BETWEEN ...
    ORDER BY room_type_id, date FOR UPDATE                              -- lock the days
  -- validate the availability against the locked rows
  UPDATE inventory_days SET held_units = held_units + :units ...
  INSERT INTO inventory_hold_lines ...
COMMIT
-- publish InventoryChanged (after commit)
```

Three properties make this safe:

1. **The idempotency key is claimed first, with an `INSERT`.** The unique
   index `inventory_holds_org_idempotency_unique` is the arbiter: exactly
   one transaction can own a key. The loser gets the unique-constraint
   violation *after* the winner committed, so it can read the winning hold
   and return it.

   The alternative — `SELECT ... FOR UPDATE` on the key — is **not** used on
   purpose. Under `REPEATABLE READ` (the MySQL default) a locking read on a
   row that does not exist takes a **gap lock** on the index gap. Two
   transactions that both looked for the same missing key would then hold
   incompatible gap locks and deadlock instead of one of them simply
   winning. The `INSERT` claims the key atomically and never opens a gap.

2. **The inventory rows are always locked in the same order**
   (`room_type_id`, then `date`). Two writers that touch the same days
   therefore queue behind each other instead of deadlocking.

3. **The counters are derived from the locked rows**, never from a value
   read before the lock was taken. Available units are
   `total_units - held_units - sold_units - blocked_units` and are never
   stored (`docs/INVENTORY.md` §1).

### 2.2 `commit()`, `release()` and `expire()`

All three leave the `active` status. The transition is a **guarded
update**:

```sql
UPDATE inventory_holds
   SET status = :terminal, ...
 WHERE organization_id = :organization
   AND id = :hold
   AND status = 'active'
```

The update is the gate for the counter mutation:

```
BEGIN
  SELECT ... FROM inventory_holds WHERE id = :hold FOR UPDATE
  UPDATE inventory_holds SET status = :terminal WHERE ... AND status = 'active'
  -- only the caller whose UPDATE changed a row mutates the counters
  UPDATE inventory_days SET held_units = ... (or held -> sold)
COMMIT
```

Exactly one concurrent caller sees `rowCount() > 0`; it is the only one
that gives the units back (or turns them into sold units). The others see
`rowCount() = 0` and leave the counters untouched. The order matters: the
counter update **after** the guarded transition is what makes "the units
come back exactly once" true, even when the expiry job runs twice at the
same time.

`HoldRepository::transitionFromActive()` has no "from" argument on
purpose: the only legal source of a terminal transition is `active`, so
the repository hard-codes it and a caller cannot guard on the wrong
status.

### 2.3 Retries

`RetriesOnContention` retries a transaction up to 3 times with a growing
backoff when MySQL reports a deadlock (1213) or a lock-wait timeout (1205).
After the last attempt the caller gets `InventoryContentionException`
("try again").

A **duplicate idempotency key is deliberately not retried**: the same
`INSERT` would fail the same way. The violation is rethrown immediately
and the caller resolves the winner instead (see
`HoldInventoryAction::execute()`). `ContentionDetector` only treats the
`inventory_holds_org_idempotency_unique` index as a lost race; a duplicate
on any other index is a real conflict and is never swallowed.

## 3. The suite

### 3.1 Layout

| Path | Purpose |
|---|---|
| `tests/Concurrency/ConcurrencyTestCase.php` | Base class: MySQL connection, fixtures, invariant helpers |
| `tests/Concurrency/Support/WorkerPool.php` | Runs the jobs in independent PHP processes |
| `tests/Concurrency/Support/worker.php` | The worker process: boots the app, waits at the barrier, runs the operation |
| `tests/Concurrency/Support/Barrier.php` | The database barrier that releases all the workers of a wave at the same instant |
| `tests/Concurrency/Support/ConcurrencyConnection.php` | The opt-in MySQL 8 connection and the gating |
| `tests/Concurrency/Support/ConcurrencySchema.php` | Builds the schema from the real migrations |
| `tests/Concurrency/LastUnitContentionTest.php` | 500 concurrent holds for the last unit |
| `tests/Concurrency/IdempotencyRaceTest.php` | Concurrent replays of one idempotency key |
| `tests/Concurrency/OverlappingRangesTest.php` | Overlapping ranges across many room types |
| `tests/Concurrency/DoubleExpiryTest.php` | Expiry (and release) racing with itself |
| `tests/Concurrency/PropertySequenceTest.php` | Random sequences of hold/commit/release |

### 3.2 How the concurrency is produced

Every worker is a **separate PHP process** with **its own MySQL session**.
The workers do not merely run at the same time: each operation may declare
a barrier, and the barrier lives in the database. A worker inserts its own
arrival row and waits until every worker of the wave arrived. Only then
does the operation start, so the whole wave hits the same inventory row
while the first one still holds the lock.

The attempts are distributed over the workers in **waves**: wave *w*
contains the attempts `w * workers … w * workers + workers - 1`, and every
wave has its own barrier. The number of attempts is the stress factor
(`CONCURRENCY_ATTEMPTS`, 500 by default); the number of workers is the
degree of concurrency, bounded by the server's `max_connections` (151 by
default in MySQL) and by the memory of the machine.

### 3.3 What each scenario proves

| Scenario | Claim | Test |
|---|---|---|
| 500 concurrent holds for the last unit | Exactly **one** success; the counters hold one unit; 499 callers get `InsufficientInventoryException` | `LastUnitContentionTest` |
| A burst of holds for 3 units | Exactly 3 successes, never more; the availability never goes below zero | `LastUnitContentionTest` |
| Concurrent replays of one idempotency key | Every caller receives the **same** hold; the units are taken **once** | `IdempotencyRaceTest` |
| Concurrent replays with a **different** payload | The loser gets `IdempotencyConflictException`; the counters match the winning payload | `IdempotencyRaceTest` |
| Overlapping ranges across many room types | No deadlock reaches the caller; no negative availability; the counters are the sum of the holds | `OverlappingRangesTest` |
| The expiry job runs twice concurrently | The units come back **exactly once** | `DoubleExpiryTest` |
| Release and expiry at the same time | The units come back **exactly once**, whoever wins | `DoubleExpiryTest` |
| Random sequences of hold/commit/release | `held + sold + blocked <= total` and the counters are exactly the sum of the holds that cover the day | `PropertySequenceTest` |

The invariant of the property test is checked against the holds
themselves, not against a snapshot: for every room type and day, the
`held_units` and `sold_units` of the row must equal the sum of the units
of the active and committed holds whose lines cover that day.

`cancel` (giving back the **sold** units of a confirmed reservation)
belongs to the Reservations module (P2-01, `docs/INVENTORY.md` §6.7): the
`reservations` table does not exist yet. The property test is written so
that `cancel` joins the sequence as soon as the operation exists.

## 4. Running the suite

### 4.1 Locally

The suite needs a MySQL 8 server and a database of its own. With Docker:

```bash
docker run -d --name stayflow-concurrency \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=stayflow_concurrency \
  -p 3307:3306 mysql:8.0
```

Then point the connection at it (`.env`):

```dotenv
CONCURRENCY_TESTS=auto
CONCURRENCY_DB_HOST=127.0.0.1
CONCURRENCY_DB_PORT=3307
CONCURRENCY_DB_DATABASE=stayflow_concurrency
CONCURRENCY_DB_USERNAME=root
CONCURRENCY_DB_PASSWORD=root
CONCURRENCY_WORKERS=24
CONCURRENCY_ATTEMPTS=500
```

and run:

```bash
composer concurrency
```

### 4.2 In CI

The suite runs in its own job (`.github/workflows/ci.yml`, job
`concurrency`) with a MySQL 8 service. It is **required** there
(`CONCURRENCY_TESTS=1`), so a missing server fails the build instead of
silently skipping the proofs.

### 4.3 Gating

| `CONCURRENCY_TESTS` | Behaviour |
|---|---|
| `auto` (default) | Run the suite when the server answers, skip it otherwise |
| `1` | The suite is required: a missing server fails the build |
| `0` | The suite is disabled |

The suite owns its database: it rebuilds the schema from the real
migrations and truncates it between the scenarios, so it never touches the
development or test data.

### 4.4 Environment variables

| Variable | Default | Meaning |
|---|---|---|
| `CONCURRENCY_TESTS` | `auto` | `auto`, `1` (required) or `0` (disabled) |
| `CONCURRENCY_DB_HOST` | `127.0.0.1` | Host of the MySQL 8 server |
| `CONCURRENCY_DB_PORT` | `3307` | Port of the MySQL 8 server |
| `CONCURRENCY_DB_DATABASE` | `stayflow_concurrency` | Database of the suite |
| `CONCURRENCY_DB_USERNAME` | `root` | User of the suite |
| `CONCURRENCY_DB_PASSWORD` | *(empty)* | Password of the user |
| `CONCURRENCY_WORKERS` | `24` | Number of worker processes (the degree of concurrency) |
| `CONCURRENCY_ATTEMPTS` | `500` | Number of `hold()` calls of the last-unit scenario |

## 5. Limits and assumptions

- **MySQL 8 is required.** The suite relies on InnoDB row locks, on the
  unique index as the arbiter of the idempotency race, and on the
  `CHECK (total_units >= held_units + sold_units + blocked_units)` constraint
  of `inventory_days`. SQLite serializes the writes and would prove
  nothing.
- **The degree of concurrency is bounded by `max_connections`.** MySQL
  allows 151 connections by default; each worker holds one. The CI job
  raises the limit to 1000 so `CONCURRENCY_WORKERS` can be raised too.
- **The database must be a dedicated one.** The suite truncates every
  table it finds.
- **The suite is slow.** Every scenario spawns one PHP process per worker,
  and each process boots the framework. With 24 workers and 500 attempts
  the suite takes a few minutes — the reason it lives in its own CI job.
- **A flaky result is a bug report, not a retry.** The scenarios are
  deterministic in what they assert; a failure means the invariant was
  broken (`AGENTS.md` §7).

## 6. Related documents

- `docs/ARCHITECTURE.md` §5.3 — the concurrency algorithm, §5.5 — the
  acceptance tests.
- `docs/INVENTORY.md` §6 — the hold lifecycle, the locking strategy and
  the idempotency rules.
- `docs/DECISIONS.md` ADR-0006 — the locking strategy.
- `AGENTS.md` §3 — the hard rules (never oversell, integer money, tenant
  scope).