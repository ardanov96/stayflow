# DOMAIN_MODEL

Status: **proposed**. Task P0-03 validates this model with a pilot hotel before schema work begins.
Field lists show the important columns only; every tenant table also has `id`, `organization_id`, timestamps (UTC), and soft-delete where noted.

## 1. Glossary

| Term | Meaning |
|---|---|
| Organization | The paying customer account (a hotel group or an independent owner) |
| Property | One hotel/villa/resort with its own inventory, timezone, currency |
| Room type | A sellable category (e.g. "Deluxe King"); inventory is counted per room type |
| Room unit | A physical room (e.g. "201"); used for assignment and housekeeping |
| Rate plan | A pricing/policy product (e.g. flexible, non-refundable, breakfast-included) |
| ARI | Availability, Rates, Inventory — the data pushed to channels |
| BAR | Best available rate |
| CTA / CTD | Closed to arrival / closed to departure |
| Stop-sell | No sales for that room/rate/date |
| OTA | Online travel agency (Booking.com, Expedia, Agoda, Airbnb…) |
| Business date | The hotel's operating day, closed by night audit |
| ADR | Average daily rate = room revenue / room-nights sold |
| RevPAR | Revenue per available room = room revenue / room-nights available |
| Folio | The guest's bill for a stay (ledger of entries) |

## 2. Entities by module

### Platform
| Entity | Key fields |
|---|---|
| Organization | name, plan_id, status, default_locale |
| User | name, email (unique), password_hash, two_factor_secret (encrypted), locale |
| Membership | user_id, organization_id, property_id (nullable = all), role |
| Plan | code, modules[] (pms, booking_engine, channel_manager, crm), price, billing_period |
| Subscription | organization_id, plan_id, status, trial_ends_at, current_period_end, gateway_ref |
| AuditEntry | actor_id, subject_type, subject_id, event, changes JSON, ip_hash, at |

### Catalog
| Entity | Key fields |
|---|---|
| Property | name, slug, timezone, currency, check_in_time, check_out_time, address, tax_profile_id |
| RoomType | property_id, code, name, max_adults, max_children, base_occupancy, total_units, description (i18n), media |
| RoomUnit | room_type_id, number, floor, bed_config (`king` \| `twin` \| `king_twin` \| `double` \| `single`), status (see Housekeeping), active |
| TaxRule | property_id, name, kind (tax/service_charge), rate_bps, inclusive, applies_to, order |
| PropertyPolicy | property_id, type (cancellation, payment, house rules), content (i18n), structured_terms JSON |

### Inventory
| Entity | Key fields |
|---|---|
| RatePlan | property_id, code, name, parent_id, derivation (e.g. -10%), meal_plan, cancellation_policy_id, active |
| RatePrice | rate_plan_id, room_type_id, date, amount_minor, currency; extra-person/child adjustments |
| RestrictionDay | rate_plan_id, room_type_id, date, min_stay, max_stay, cta, ctd, stop_sell |
| InventoryDay | room_type_id, date, total_units, held_units, sold_units, blocked_units |
| InventoryHold / HoldLine | idempotency_key, status, expires_at; room_type_id, date, units |

### Guests (CRM)
| Entity | Key fields |
|---|---|
| Guest | organization_id, first_name, last_name, email_normalized, phone_e164, country, language, merged_into_id, notes (encrypted if sensitive) |
| GuestIdentityDoc | guest_id, type, number (encrypted), country, expiry |
| Consent | guest_id, purpose, status, policy_version, source, recorded_at |
| Segment | name, rule JSON, owner |
| Automation / Campaign | trigger, conditions JSON, delay, template_id, status |
| CommunicationLog | guest_id, channel, direction, subject, status, external_id, at |

### Reservations
| Entity | Key fields |
|---|---|
| Reservation | property_id, code (human-readable, unique per property), status, source (direct, pms, ota:<channel>), channel_ref, guest_id (primary), arrival, departure, adults, children, total_minor, currency, version |
| ReservationRoom | reservation_id, room_type_id, rate_plan_id, room_unit_id (nullable until assigned), nights JSON/lines with nightly price |
| ReservationEvent | reservation_id, type (created, modified, status_changed, assigned…), payload, actor, at |
| ReservationGuest | reservation_id, guest_id, role (primary, companion) |

### Folio
| Entity | Key fields |
|---|---|
| Folio | reservation_id, status (open/closed), currency |
| FolioEntry | folio_id, type, amount_minor (signed), currency, business_date, posted_at, reversal_of_id, source, tax_snapshot JSON, created_by |
| Invoice | folio_id, number (gapless per property), issued_at, locale, totals snapshot, pdf_path |
| Payment | folio_id, method, amount_minor, status, gateway_ref, idempotency_key |

### Housekeeping
| Entity | Key fields |
|---|---|
| RoomStatus (on RoomUnit) | clean_state (dirty, clean, inspected), occupancy_state (vacant, occupied), out_of_order {from,to,reason} |
| HousekeepingTask | room_unit_id, type, assigned_to, status, due_on, notes |

### BookingEngine
| Entity | Key fields |
|---|---|
| Promo | property_id, code, kind (percent/fixed), value, valid_from/to, stay_window, min_stay, usage_limit, stackable |
| AddOn | property_id, name, pricing_basis (per_stay, per_night, per_person, per_person_per_night), price_minor, tax_rule_id |
| BookingSession | public_token, quote snapshot, hold_id, expires_at, utm/source |
| WidgetConfig | property_id, theme, languages, enabled_plugins, allowed_origins |

### Distribution
| Entity | Key fields |
|---|---|
| ChannelConnection | property_id, provider, channel_code, credentials (encrypted), status, paused_at, last_success_at |
| RoomMapping / RateMapping | connection_id, internal_id, external_id, active |
| ChannelChangeLog | connection_id, entity (inventory/rate/restriction), room_type_id, date_from/to, sequence, status |
| InboundEvent | provider, external_event_id (unique), payload, status (received, processed, quarantined, failed), error |
| ReconciliationRun / Drift | connection_id, run_at, drifts JSON, healed |

### Plugins
| Entity | Key fields |
|---|---|
| LoyaltyAccount / LoyaltyEntry | guest_id, tier; append-only points entries with reason |
| PriceMatchRule / PriceMatchEvent | max_delta_bps, floor_minor, offset_bps; per-offer audit |
| ChatConfig | property_id, chatwoot_inbox_id, identity_secret |
| SocialFeedConfig | property_id, account_id, token (encrypted), refresh_at |

## 3. Invariants (must always hold; each needs a test)

1. `inventory_days`: `held + sold + blocked ≤ total` and all counters ≥ 0.
2. A reservation's nights, room type, and rate plan are all inside the inventory window; each night has inventory held or sold equal to the reservation's room count.
3. Sum of a folio's entries equals its balance; entries are never updated or deleted; every reversal references an existing entry and cannot exceed it.
4. A closed business date accepts no new entries.
5. Invoice numbers are unique and gapless per property.
6. A reservation status only changes along the state machine (`docs/ARCHITECTURE.md` §7).
7. A room unit cannot be assigned to two reservations on overlapping nights.
8. Every tenant row's `organization_id` equals its parent's `organization_id`.
9. Money is stored in minor units with an explicit currency; amounts in different currencies are never summed.
10. An inbound channel event is processed at most once per (provider, external_event_id, revision).
11. Consent withdrawal stops all marketing sends within the next scheduled run, and is auditable.
12. A promo's usage count never exceeds its limit under concurrency.

## 4. Report definitions (confirm with the pilot hotel)

- **Room revenue:** room charges net of tax and service charge (confirm), excluding add-ons unless configured.
- **Available room-nights:** units × nights in range, minus out-of-order nights.
- **Occupancy %** = sold room-nights / available room-nights.
- **ADR** = room revenue / sold room-nights. **RevPAR** = room revenue / available room-nights.
- Reports use the business date, not the wall-clock timestamp.
## 5. Modeling questions (P0-03)

The physical schema is in `docs/ERD.md`. It is generated from the
migrations, so it always describes the database that exists.

| # | Question | Status | Decided by |
|---|---|---|---|
| 1 | Do pilots sell by room type only, or do any need unit-level selling (villas with unique units)? | **Resolved** | OQ-01: per room type only. `RoomUnit` is kept for housekeeping and room assignment, but it is not a sellable unit; per-unit pricing is deferred from MVP. |
| 2 | Per-person pricing vs flat; child age bands; extra-bed fees. | **Open** | Needs the pilot: OQ-11. |
| 3 | Packages that bundle multiple services (spa, transfers). | **Resolved for the MVP** | A package is an add-on: a set of extras attached to a reservation and priced per stay. The Booking Engine exposes add-ons (§2), so no package entity is needed before the pilot asks for one. |
| 4 | Multi-room bookings and groups. | **Resolved for the MVP** | One reservation can hold several rooms; group blocks are deferred from MVP (`docs/ROADMAP.md` §7). |
| 5 | Deposit policies and refund rules per rate plan. | **Open** | Needs the pilot and the gateway rules: OQ-12. |

The two questions that are still open are logged in
`docs/DECISIONS.md` §3 (OQ-11, OQ-12) and are part of the pilot
interview of task `P0-08`.
