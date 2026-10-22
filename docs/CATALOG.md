# Property Catalog

Status: **accepted** for P1-06. Design, entities, and invariants are implemented in
`app/Modules/Catalog/{Domain,Application,Infrastructure,Contracts,Http}/*`. Tested by
`tests/Unit/Catalog/*` and `tests/Feature/Catalog/*`.
This document is the human-readable policy enforced by the code.

## 1. Overview

The **Catalog** module is the single source of truth for hotel physical inventory and metadata:
- Properties (hotels, resorts, villa complexes)
- Room Types (categories of rooms with capacity and bed configurations)
- Room Units (individual physical rooms/villas for housekeeping and physical allocation)
- Amenities (shared catalog of facilities with category tagging)
- Tax and Service Charge Rules (property-specific tax rules executed by the Money module)
- Property Policies (check-in, cancellation, no-show, guarantee, and pet policies)
- Media (images and files with automated WebP conversion and responsive metadata)

All other modules (Inventory, Reservations, Housekeeping, Booking Engine, Distribution) interact
with the catalog exclusively via `App\Modules\Catalog\Contracts\CatalogService` and its DTOs.

## 2. Models & Schema

All catalog entities (except system-wide amenities) are strictly tenant-scoped via `BelongsToTenant`:

| Model | Table | Scoping | Purpose |
|---|---|---|---|
| `Property` | `properties` | `organization_id` | Physical property details, timezone, currency, operating hours. |
| `RoomType` | `room_types` | `organization_id`, `property_id` | Sellable category (e.g. Deluxe Villa, Superior Suite). |
| `RoomUnit` | `room_units` | `organization_id`, `property_id` | Physical room identifier (e.g. Room 101, Villa A). |
| `Amenity` | `amenities` | Global (or org-custom) | Master list of amenities (WiFi, Pool, AC) categorized by type. |
| `TaxRule` | `catalog_tax_rules` | `organization_id`, `property_id` | Rates in basis points, inclusive/exclusive flag, calculation order. |
| `PropertyPolicy` | `property_policies` | `organization_id`, `property_id` | Rules and terms per policy type (`check_in`, `cancellation`, etc.). |
| `CatalogMedia` | `catalog_media` | `organization_id` | Polymorphic media attachment for Property and RoomType. |

## 3. Domain Primitives & Enums

The domain layer encapsulates business rules using pure PHP primitives:

1. **`PropertyStatus`**: `active`, `inactive`, `onboarding`, `maintenance`.
2. **`PolicyType`**: `check_in`, `cancellation`, `no_show`, `guarantee`, `pet`, `general`.
3. **`Occupancy` (Value Object)**:
   - Base adults, max adults, max children, max total guests.
   - Invariants: `base_adults <= max_adults`, `max_adults <= max_total_guests`, `max_total_guests > 0`.
4. **`BedConfig` (Value Object)**:
   - Configured beds (`king`, `queen`, `single`, `twin`, `sofa_bed`) with quantity.

## 4. Media Storage & WebP Optimization

All image uploads are processed via `App\Modules\Catalog\Infrastructure\Media\WebpMediaOptimizer`:
- Target format: WebP for optimal performance and compression.
- Responsive dimensions: original width/height captured and recorded in JSON metadata.
- Validation: Strict mime-type checks (`image/jpeg`, `image/png`, `image/webp`), size limits (max 10MB), and path sanitization.
- Fail-safe fallback: If GD/WebP extensions are not installed in the runtime environment, the optimizer safely stores the verified original image while recording correct metadata.

## 5. Tenancy & Security Invariants

1. **Strict Multi-Tenancy**:
   - Every catalog query is automatically scoped to `organization_id` via `BelongsToTenantScope`.
   - Accessing a property or room type belonging to another organization yields 404 or empty results.
   - Tested exhaustively in `CatalogTenantIsolationTest`.
2. **Audit Trail**:
   - All mutations (`CreateProperty`, `UpdateProperty`, `CreateRoomType`, `CreateRoomUnit`, `UploadCatalogMedia`, `SetPropertyPolicy`) emit audit log entries via `App\Modules\Platform\Application\AuditLog\Ports\Auditor`.
3. **Soft Deletes**:
   - Properties, RoomTypes, and RoomUnits implement soft deletes to prevent breaking historical reservations and folio charges.

## 6. Public Contracts & Integration

Downstream modules (e.g. P1-07 Rate Plans, P1-08 Inventory Engine) interact with Catalog via:
- `CatalogService::findProperty(int $id): ?CatalogPropertyDTO`
- `CatalogService::getRoomTypesForProperty(int $propertyId): array`
- `CatalogService::getRoomUnitsForProperty(int $propertyId, ?int $roomTypeId = null): array`
- `CatalogService::getActiveTaxRules(int $propertyId): array`
- `CatalogService::getPolicy(int $propertyId, PolicyType $type): ?PropertyPolicyDTO`
