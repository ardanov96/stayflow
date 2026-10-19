<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Identity;

use InvalidArgumentException;

/**
 * The closed set of fine-grained permissions the role system recognizes.
 *
 * Naming convention: `{module}.{action}`. Examples:
 *  - `users.invite`         — create a new user in the org
 *  - `roles.assign`         — change a membership's role
 *  - `reservations.create`  — create a reservation
 *  - `audit.read`           — view the audit log
 *
 * Two structural choices to keep in mind when adding permissions:
 *  1. Every permission is either **org-scoped** (acts on the whole
 *     organization) or **property-scoped** (acts inside a single
 *     property). The split is a code-and-doc convention, not a type
 *     tag: a permission string has no "scope" field. A property-scoped
 *     permission is meaningful on a Membership that has property_id set;
 *     on a NULL property_id (org-wide) Membership the permission is
 *     implicitly granted for every property in the org. The check is
 *     done by {@see RolePermissionMatrix::allows()}.
 *  2. Permissions are intentionally **fine-grained** so that "delete
 *     a reservation" and "void a charge" can be granted independently.
 *     The UI groups them by module; the wire format and the role
 *     matrix both use the dot string verbatim.
 */
enum Permission: string
{
    // Users and roles
    case UsersInvite = 'users.invite';
    case UsersRemove = 'users.remove';
    case RolesAssign = 'roles.assign';

    // Catalog (properties, room types, policies)
    case PropertiesRead = 'properties.read';
    case PropertiesUpdate = 'properties.update';
    case RoomTypesManage = 'room_types.manage';
    case PoliciesManage = 'policies.manage';

    // Inventory (rates, restrictions, availability)
    case RatePlansRead = 'rate_plans.read';
    case RatePlansManage = 'rate_plans.manage';
    case InventoryManage = 'inventory.manage';
    case RestrictionsManage = 'restrictions.manage';

    // Reservations
    case ReservationsRead = 'reservations.read';
    case ReservationsCreate = 'reservations.create';
    case ReservationsUpdate = 'reservations.update';
    case ReservationsCancel = 'reservations.cancel';
    case ReservationsCheckIn = 'reservations.check_in';
    case ReservationsCheckOut = 'reservations.check_out';

    // Folio and payments
    case FolioRead = 'folio.read';
    case FolioCharge = 'folio.charge';
    case FolioRefund = 'folio.refund';
    case PaymentsRefund = 'payments.refund';

    // Housekeeping
    case HousekeepingRead = 'housekeeping.read';
    case HousekeepingManage = 'housekeeping.manage';

    // Guests (CRM)
    case GuestsRead = 'guests.read';
    case GuestsWrite = 'guests.write';

    // BookingEngine and promos
    case PromosManage = 'promos.manage';
    case BookingEngineRead = 'booking_engine.read';
    case BookingEngineManage = 'booking_engine.manage';

    // Reporting
    case ReportsRead = 'reports.read';

    // Platform (admin)
    case AuditRead = 'audit.read';
    case SettingsManage = 'settings.manage';
    case BillingManage = 'billing.manage';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::UsersInvite,
            self::UsersRemove,
            self::RolesAssign,
            self::PropertiesRead,
            self::PropertiesUpdate,
            self::RoomTypesManage,
            self::PoliciesManage,
            self::RatePlansRead,
            self::RatePlansManage,
            self::InventoryManage,
            self::RestrictionsManage,
            self::ReservationsRead,
            self::ReservationsCreate,
            self::ReservationsUpdate,
            self::ReservationsCancel,
            self::ReservationsCheckIn,
            self::ReservationsCheckOut,
            self::FolioRead,
            self::FolioCharge,
            self::FolioRefund,
            self::PaymentsRefund,
            self::HousekeepingRead,
            self::HousekeepingManage,
            self::GuestsRead,
            self::GuestsWrite,
            self::PromosManage,
            self::BookingEngineRead,
            self::BookingEngineManage,
            self::ReportsRead,
            self::AuditRead,
            self::SettingsManage,
            self::BillingManage,
        ];
    }

    public static function fromString(string $value): self
    {
        $permission = self::tryFrom($value);
        if ($permission === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown permission "%s". Known permissions: %s.',
                $value,
                implode(', ', array_map(fn (self $p): string => $p->value, self::all())),
            ));
        }

        return $permission;
    }

    /**
     * Returns the module prefix (everything before the first dot). Used
     * to group permissions in the role-management UI and in test
     * output.
     */
    public function module(): string
    {
        $value = $this->value;
        $dot = strpos($value, '.');
        if ($dot === false) {
            return $value;
        }

        return substr($value, 0, $dot);
    }
}
