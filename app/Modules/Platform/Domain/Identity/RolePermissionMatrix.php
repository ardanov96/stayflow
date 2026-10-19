<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Identity;

use InvalidArgumentException;

/**
 * The canonical mapping of {@see Role} to {@see Permission} set.
 *
 * This is a sealed, immutable domain service. It defines the compile-time
 * authority matrix for the six pilot roles in Stayflow.
 */
final class RolePermissionMatrix
{
    private static ?self $instance = null;

    /**
     * @var array<string, list<Permission>>
     */
    private array $matrix;

    private function __construct()
    {
        $allPermissions = Permission::all();

        // Manager has everything EXCEPT AuditRead and BillingManage
        $managerPermissions = array_values(array_filter(
            $allPermissions,
            static fn (Permission $p): bool => ! in_array($p, [
                Permission::AuditRead,
                Permission::BillingManage,
            ], true),
        ));

        // FrontDesk: front-office operations, check-in/out, folio charge, guest lookup/edit, basic catalog/rates read
        $frontDeskPermissions = [
            Permission::PropertiesRead,
            Permission::RatePlansRead,
            Permission::ReservationsRead,
            Permission::ReservationsCreate,
            Permission::ReservationsUpdate,
            Permission::ReservationsCancel,
            Permission::ReservationsCheckIn,
            Permission::ReservationsCheckOut,
            Permission::FolioRead,
            Permission::FolioCharge,
            Permission::HousekeepingRead,
            Permission::GuestsRead,
            Permission::GuestsWrite,
            Permission::BookingEngineRead,
        ];

        // Housekeeping: room status and housekeeping tasks only
        $housekeepingPermissions = [
            Permission::PropertiesRead,
            Permission::HousekeepingRead,
            Permission::HousekeepingManage,
        ];

        // Revenue: catalog, rate plans, restrictions, inventory days, reports
        $revenuePermissions = [
            Permission::PropertiesRead,
            Permission::RoomTypesManage,
            Permission::PoliciesManage,
            Permission::RatePlansRead,
            Permission::RatePlansManage,
            Permission::InventoryManage,
            Permission::RestrictionsManage,
            Permission::ReservationsRead,
            Permission::ReportsRead,
            Permission::PromosManage,
        ];

        // Marketing: promos, booking engine, guests CRM, reports
        $marketingPermissions = [
            Permission::PropertiesRead,
            Permission::RatePlansRead,
            Permission::GuestsRead,
            Permission::GuestsWrite,
            Permission::PromosManage,
            Permission::BookingEngineRead,
            Permission::BookingEngineManage,
            Permission::ReportsRead,
        ];

        $this->matrix = [
            Role::Owner->value => $allPermissions,
            Role::Manager->value => $managerPermissions,
            Role::FrontDesk->value => $frontDeskPermissions,
            Role::Housekeeping->value => $housekeepingPermissions,
            Role::Revenue->value => $revenuePermissions,
            Role::Marketing->value => $marketingPermissions,
        ];
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * @return list<Permission>
     */
    public function permissionsFor(Role $role): array
    {
        if (! isset($this->matrix[$role->value])) {
            throw new InvalidArgumentException(sprintf('No permissions defined for role "%s".', $role->value));
        }

        return $this->matrix[$role->value];
    }

    public function allows(Role $role, Permission $permission): bool
    {
        return in_array($permission, $this->permissionsFor($role), true);
    }
}
