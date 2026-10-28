# BILLING — the subscription, the plans and the gateways

Documentation for task `P1-11` (SaaS subscription and plan gating).

## 1. The local subscription is the truth

ADR-0013: the **local `subscriptions` table is the single source of
truth**. The gateway is told what to do; it is never asked what the state
is. Everything the application decides (which modules the organization
has, whether it has to pay) is decided from the local table.

## 2. The plans

A plan says which **modules** the organization has and which **features**
are switched on (`config/plans.php`):

| Plan | Modules | Features |
|---|---|---|
| `pms` | catalog, inventory, reservations, folio, housekeeping | reports, night audit, import |
| `booking_engine` | booking_engine | promo codes, add-ons, widget, analytics |
| `channel_manager` | distribution | ARI push, reconciliation, overbooking quarantine |
| `crm` | guests | segments, automations, campaigns |

The modules are the ones of the URL of the API (`api/<module>/...`), so
**one middleware covers the routes and the API**: a module that the
organization does not pay for answers 403.

An organization without a subscription is on the **trial of the default
plan**, counted from the day it was created. The trial ends even if
nobody ever opens the billing page.

## 3. The state machine

```
trialing ──▶ active ──▶ past_due ──▶ active
    │           │           │
    └───────────┴───────────┴──▶ canceled ──▶ expired
```

- A cycle that **failed** does not end the subscription: it makes it
  *past due*, and the organization keeps the modules until the grace
  period is over.
- `canceled` and `expired` do not grant access to anything.
- The state machine is the contract with the billing; a move that is not
  allowed is refused, not forced.

## 4. The gateways

The organization chooses its gateway:

- **Xendit** (primary): the payment methods of OQ-05 — the virtual
  accounts, QRIS, the e-wallets, the cards — with the
  Merchant-Initiated Transactions.
- **Midtrans** (secondary): the cards.

The application speaks to both the same way: the payload of the provider
becomes the event of Stayflow (`BillingEvent`), and everything else is
identical. The `XenditBillingGateway` and the `MidtransBillingGateway`
only translate.

## 5. The webhook, and the replay

`POST /webhooks/billing/{organizationReference}/{token}`

The webhook of a provider is delivered **at least once**: the same event
can arrive twice, and it must not charge twice.

1. The event is **recorded before it is applied** (`billing_events`, unique
   on the gateway and the reference).
2. The second delivery finds the event and stops: the outcome is
   `replayed`, and **no second invoice is issued** (the invoice is unique
   per period).

The endpoint is public, so it carries a **shared secret in the path**,
compared in constant time, and no middleware: the provider knows nothing
about a session.

> **To verify before the endpoint is opened to the internet:** the exact
> signature scheme of each provider (the header and how the signature is
> computed) has to be read from its documentation and checked on top of
> the shared secret. `docs/VENDORS.md` lists what was verified on
> 2026-10-27 and what was not — the shape of the callback of Xendit was
> read, the one of Midtrans was not.

## 6. What is not built yet

- The **invoices of the fees** are recorded (`subscription_invoices`),
  but there is no PDF and no mail: the invoice of the SaaS is not the
  invoice of the guest (`P2-10`).
- The **charging** itself is not implemented: the application records the
  events the provider sends. The charge is created in the billing page,
  which is not built yet.
- The **feature flags** of a plan are declared; the code that switches
  them off is per module, not a general flag service.

## 7. Related documents

- `docs/DECISIONS.md` ADR-0013 — the billing in house, the two gateways.
- `docs/VENDORS.md` — the contract of the providers, and what is to verify.
- `docs/PRODUCTION_READINESS.md` — the readiness items.