<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Identity;

use InvalidArgumentException;

/**
 * A role a user holds in an organization (and optionally a specific
 * property inside it). Roles are stored as strings on the `memberships.role`
 * VARCHAR(32) column, so the underlying value MUST stay ≤ 32 bytes; the
 * {@see fromString()} factory rejects anything else at the seam.
 *
 * The six roles named here cover the pilot's staff set (per BACKLOG P1-03
 * and `docs/DOMAIN_MODEL.md` §2 Platform). They are intentionally a closed
 * set: the role-permission matrix in {@see RolePermissionMatrix} is keyed
 * on the enum, and "we'll add a role later" is a code change, not a
 * runtime decision.
 *
 * The role maps a (user, organization, property?) tuple to a set of
 * permissions. The mapping is static and lives in
 * {@see RolePermissionMatrix}; this enum is the closed set of valid
 * keys, nothing more.
 */
enum Role: string
{
    /** Org-wide super-user. May assign roles, manage billing, read audit log. */
    case Owner = 'owner';

    /** Property-level super-user (minus billing/payments). */
    case Manager = 'manager';

    /** Reservations, check-in/out, guest profiles, basic inventory view. */
    case FrontDesk = 'front_desk';

    /** Room status updates, housekeeping task management. */
    case Housekeeping = 'housekeeping';

    /** Rate plans, prices, restrictions, availability, reports. */
    case Revenue = 'revenue';

    /** Promos, add-ons, guest CRM, marketing reports. */
    case Marketing = 'marketing';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Owner,
            self::Manager,
            self::FrontDesk,
            self::Housekeeping,
            self::Revenue,
            self::Marketing,
        ];
    }

    public static function fromString(string $value): self
    {
        $role = self::tryFrom($value);
        if ($role === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown role "%s". Known roles: %s.',
                $value,
                implode(', ', array_map(fn (self $r): string => $r->value, self::all())),
            ));
        }

        return $role;
    }

    /**
     * Roles that grant org-wide (cross-property) authority. The
     * `BelongsToTenant` global scope reads the active tenant context
     * but does not distinguish "all properties" from "this property";
     * that distinction is enforced by {@see RolePermissionMatrix} at
     * the permission level.
     */
    public function isOrgWide(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Human label, suitable for the Filament admin UI and the role
     * management page. Centralized here so the matrix and the UI can
     * never disagree.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::FrontDesk => 'Front-desk',
            self::Housekeeping => 'Housekeeping',
            self::Revenue => 'Revenue',
            self::Marketing => 'Marketing',
        };
    }

    /**
     * Privileged roles that mandate two-factor authentication (2FA).
     * Backlog P1-02 and P1-03 require 2FA for Owner and Manager roles.
     */
    public function requiresTwoFactor(): bool
    {
        return $this === self::Owner || $this === self::Manager;
    }
}
