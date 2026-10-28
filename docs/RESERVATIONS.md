# RESERVATIONS — the aggregate and the state machine

Documentation for task `P2-01` (reservation aggregate and state machine).

## 1. The aggregate

`App\Modules\Reservations\Domain\Reservation` is the reservation. It owns:

- the **state** of the reservation, and the only moves that are legal;
- the **rooms** of the reservation (a room type, a rate plan, the nights
  and the price of each night);
- the **version**, the number of the change: it is what the callers use
  to make sure they do not write over a change they did not read;
- the **events** the reservation recorded.

Pure domain: no persistence, no framework. The application layer (task
`P2-02`) writes it and publishes the events.

## 2. The state machine

`docs/ARCHITECTURE.md` §7, and invariant 6 of `docs/DOMAIN_MODEL.md` §3:
*a reservation status only changes along the state machine*.

```
pending_payment ──▶ confirmed ──▶ checked_in ──▶ checked_out
     │                 │
     │                 ├──▶ cancelled
     ├──▶ expired      └──▶ no_show
     └──▶ cancelled
```

- A reservation is **created** either `pending_payment` (the booking
  engine: the guest has not paid) or `confirmed` (the staff of the hotel
  or an OTA).
- A cycle that **failed** does not end the reservation: it makes it
  `past_due`... (that is the subscription; for a reservation the payment
  that never comes **expires** the reservation.)
- `checked_out`, `cancelled`, `no_show` and `expired` are **final**: the
  reservation never leaves them, and it cannot be modified any more.

A move that is not on the machine is **refused**
(`InvalidReservationTransition`), never forced. The unit test tries
**every pair of states** — 49 of them — and asserts that the ones on the
machine work and the others are refused.

## 3. The versioned modifications

A modification (the dates, the guests, the number of people) is **not a
state**: it is a versioned event (`docs/ARCHITECTURE.md` §7). The
version is bumped, the `ReservationModified` event is recorded, and the
status does not change.

The version is also the optimistic lock of the row: a caller that read
the reservation before a change does not write over it.

## 4. The events

| Event | When |
|---|---|
| `ReservationCreated` | The reservation was written (the identifier is known) |
| `ReservationStatusChanged` | The state changed |
| `ReservationModified` | The reservation was modified |
| `RoomAssigned` | A physical room was assigned to one of the rooms |

Every event implements `ShouldDispatchAfterCommit`: **a listener never
observes a change that was rolled back**. The feature test proves it: the
event of a transaction that is rolled back is never published, and the
event of a transaction that commits is published once.

The events are published with the `event()` helper, not with the
`Dispatchable` trait: in Laravel 11+ that trait dispatches queued jobs,
not events.

## 5. Creating a reservation (P2-02)

`CreateReservationAction` creates the reservation of the staff of the
hotel:

1. the **quote** of the inventory decides the availability of the nights
   and the price of the stay (the staff sees the same quote before
   pressing the button);
2. the inventory is **held**, the reservation is written, and the hold is
   **committed** — the units become sold. The three happen in **one
   transaction**: either the guest has a room and the inventory says so,
   or nothing happened at all.
3. the request is **idempotent**: the idempotency key gives the same
   hold, and the same hold gives the same reservation. A double submit
   creates **one** reservation and takes the room **once**.

The four tables are `reservations`, `reservation_rooms`,
`reservation_guests` and `reservation_events` (the trail). The code of a
reservation is unique per property and carries the day of the property.

## 6. What is not built yet

- **No screen.** The PMS reads the reservation through the contract of
  the module; the screen belongs to `P2-17`.
- **The modification** of a reservation lands with `P2-03` (the
  versioned modifications of the aggregate are ready for it).
- **The assignment of a physical room** lands with `P2-05`.
- **The guest** of a reservation is a soft reference: the `guests` table
  lands with the Guests module (`P5-01`), and the constraint is added
  there.

## 7. Related documents

- `docs/ARCHITECTURE.md` §7 — the state machine and the booking flow.
- `docs/DOMAIN_MODEL.md` §2 — the entities, §3 — the invariants.
- `docs/INVENTORY.md` — the holds the reservations come from.