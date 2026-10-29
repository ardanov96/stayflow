# FOLIO — the ledger of what the guest owes

Documentation for task `P2-09` (folio ledger). It is a gate of **M2**.

## 1. The ledger is append-only

An entry of the ledger is never updated and never deleted. A correction is
another entry that **reverses** the old one, so the trail says exactly
what happened and when.

The database says so, not only the application: the migration installs a
trigger that refuses an `UPDATE` and a `DELETE` of an entry (`folio_entries`).

## 2. The three rules

They are the invariant 3 and the invariant 4 of `docs/DOMAIN_MODEL.md` §3:

1. **The balance is always the sum of the entries.** The folio carries the
   balance and the entries; the two never drift apart.
2. **A reversal never exceeds what is left to reverse** of the entry it
   reverses. The sum of the reversals of an entry can never be bigger than
   the entry itself.
3. **A closed business date accepts no new entry.** The night audit
   (`P2-13`) closes the business date of a property; the folio refuses
   everything that would be posted on it.

## 3. The entries

| Type | Effect on the balance |
|---|---|
| `charge` | raises it (the room, the extras) |
| `tax` | raises it |
| `payment` | lowers it |
| `refund` | lowers it |
| `adjustment` | either way (a correction) |
| `reversal` | lowers it, and points at the entry it reverses |

Every entry carries the **business date** it belongs to, the currency, the
source that posted it, and the snapshot of the taxes that applied at that
moment.

## 4. The money

The amounts are **minor units with an explicit currency**
(`App\Support\Money\Money`, task `P1-05`). An entry in another currency is
refused: the amounts of a folio are never summed across currencies.

## 5. The payments

A payment is recorded twice: as an entry of the ledger (it lowers the
balance) and as a row of `payments` (the method, the status, the gateway
reference). The **idempotency key** makes sure the same payment is never
taken twice.

## 6. What is not built yet

- **The invoices** (the number gapless per property, the PDF, the mail)
  land with `P2-10`.
- **The night audit** that closes the business dates lands with `P2-13`;
  until then the closure is driven by the service.
- **The check-in and check-out** that post the room charges land with
  `P2-08`.

## 7. Related documents

- `docs/DOMAIN_MODEL.md` §2 — the entities, §3 — the invariants.
- `docs/MONEY.md` — the money.
- `docs/RESERVATIONS.md` — the reservation the folio belongs to.