# Rate Plans and Prices

Status: **accepted** for P1-07. Design, entities, and invariants are implemented in
`app/Modules/Inventory/{Domain,Application,Infrastructure,Contracts,Http}/*`. Tested by
`tests/Unit/Inventory/*` and `tests/Feature/Inventory/*`.
This document is the human-readable policy enforced by the code.

## 1. Overview

The **Rate Plans and Prices** component within the **Inventory** module governs nightly room pricing,
occupancy supplements, meal plan inclusions, and rate plan derivations:
- Independent rate plans (e.g. Best Available Rate / BAR, Rack Rate).
- Derived rate plans with percentage or fixed amount discounts/premiums (e.g. Non-Refundable -10%, Early Bird -15%).
- Per-date, per-room-type price matrix with integer minor units via `Money` VO.
- Occupancy adjustments for extra adults (exceeding room type base occupancy) and extra children.
- Integration with Catalog policies (cancellation policy link and structured terms).
- Itemized price calculation service exposed through `PricingService` contract.

## 2. Models & Schema

Rate plan entities are strictly tenant-scoped via `BelongsToTenant`:

| Model | Table | Scoping | Purpose |
|---|---|---|---|
| `RatePlan` | `rate_plans` | `organization_id`, `property_id` | Rate configuration, meal plan, parent derivation link, cancellation policy link. |
| `RatePrice` | `rate_prices` | `organization_id`, `property_id` | Nightly base price and extra person/child adjustments. Composite unique on `(rate_plan_id, room_type_id, date)`. |

## 3. Rate Derivation & Hierarchy

1. **Independent Rate Plans**:
   - Have `parent_rate_plan_id = null`.
   - Prices are populated directly in `rate_prices`.
2. **Derived Rate Plans**:
   - Point to a parent `RatePlan`.
   - Define a `derivation_type` (`percentage` or `fixed_amount`) and a `derivation_value`.
   - **Percentage**: Value in basis points (e.g. `-1000` = -10.00%, `+1500` = +15.00%). Calculated via `Money::multipliedBy()->dividedBy(10000)`.
   - **Fixed Amount**: Value in minor units (e.g. `-500000` minor units). Clamped at zero if discount exceeds base price.
   - **Cycle Prevention**: The domain enforces ancestor traversal to guarantee no circular dependencies (e.g. A → B → A is rejected with `CircularDerivationException`).
   - **Selective Override**: If a specific date is directly set on a derived rate plan, the direct price takes precedence over the formula.

## 4. Occupancy Adjustments

Room types define a `base_occupancy` (e.g. 2 adults) and maximum guest limits.
When quoting a stay with `$adults` and `$children`:
- Extra adults count = `max(0, adults - base_occupancy)`.
- Extra adult supplement = `extra_adult_price_minor * extra_adults`.
- Extra child supplement = `extra_child_price_minor * children`.
- Night total = `base_price + extra_adult_supplement + extra_child_supplement`.

## 5. Price Calculation Service (`PricingService`)

Downstream modules (Booking Engine, Reservations, Channel Manager) calculate itemized stay quotes via:
```php
$quote = $pricingService->quoteStay(
    propertyId: $propertyId,
    roomTypeId: $roomTypeId,
    ratePlanId: $ratePlanId,
    checkInDate: '2026-11-01',
    checkOutDate: '2026-11-04',
    adults: 3,
    children: 1,
);
```

The resulting `StayPriceQuote` contains:
- Total stay subtotal as `Money`.
- Itemized `NightlyPriceBreakdown` for each night with base price, extra person breakdowns, and nightly total.
- Linked cancellation policy details (title, description, structured terms).

## 6. Audit Trail & Tenant Isolation

- Mutations to rate plans (`CreateRatePlanAction`, `UpdateRatePlanAction`) and rate prices (`SetRatePricesAction`) emit structured audit entries (`rate.changed`) via the `Auditor` port.
- Every query enforces `BelongsToTenant` scope. Users cannot view, modify, or calculate quotes across tenant boundaries.
