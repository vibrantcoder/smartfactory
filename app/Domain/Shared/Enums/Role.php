<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Typed role constants for the Smart Factory RBAC system.
 *
 * Roles follow a strict hierarchy:
 *   super-admin > factory-admin > production-manager > supervisor > operator > viewer
 *
 * super-admin is NOT factory-scoped (team_id = null in model_has_roles).
 * All other roles ARE factory-scoped (team_id = factory_id).
 */
enum Role: string
{
    case SUPER_ADMIN        = 'super-admin';
    case FACTORY_ADMIN      = 'factory-admin';
    case PRODUCTION_MANAGER = 'production-manager';
    case SUPERVISOR         = 'supervisor';
    case OPERATOR           = 'operator';
    case VIEWER             = 'viewer';

    // ─────────────────────────────────────────────────────────
    // Metadata
    // ─────────────────────────────────────────────────────────

    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN        => 'Super Admin',
            self::FACTORY_ADMIN      => 'Factory Admin',
            self::PRODUCTION_MANAGER => 'Production Manager',
            self::SUPERVISOR         => 'Supervisor',
            self::OPERATOR           => 'Operator',
            self::VIEWER             => 'Viewer',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::SUPER_ADMIN        => 'Full system access across all factories. Not factory-scoped.',
            self::FACTORY_ADMIN      => 'Full access within assigned factory. Manages users and machines.',
            self::PRODUCTION_MANAGER => 'Manages production plans, parts, customers, and views analytics.',
            self::SUPERVISOR         => 'Oversees shift execution; can record actuals and classify downtime.',
            self::OPERATOR           => 'Records production actuals and reports downtime events.',
            self::VIEWER             => 'Read-only access to reports and dashboards.',
        };
    }

    /**
     * Super Admin operates globally — not scoped to a factory.
     * All other roles are scoped to a specific factory via Spatie teams.
     */
    public function isFactoryScoped(): bool
    {
        return $this !== self::SUPER_ADMIN;
    }

    /**
     * Hierarchy level — higher = more privileged.
     * Used to prevent privilege escalation (users cannot assign roles
     * with a higher level than their own).
     */
    public function level(): int
    {
        return match($this) {
            self::SUPER_ADMIN        => 100,
            self::FACTORY_ADMIN      => 80,
            self::PRODUCTION_MANAGER => 60,
            self::SUPERVISOR         => 40,
            self::OPERATOR           => 20,
            self::VIEWER             => 10,
        };
    }

    /**
     * Roles that the given role is allowed to assign to other users.
     * A factory-admin cannot assign super-admin, for example.
     *
     * @return Role[]
     */
    public function assignableRoles(): array
    {
        return array_filter(
            self::cases(),
            fn(Role $r) => $r->level() < $this->level()
        );
    }

    // ─────────────────────────────────────────────────────────
    // Default Permission Matrix
    //
    // Defines which permissions each role receives by default.
    // The RbacSeeder calls $role->defaultPermissions() to seed
    // the role_has_permissions pivot table.
    //
    // Super Admin: bypass via Gate::before — no permissions listed.
    // All others: explicit allow-list (deny by default).
    // ─────────────────────────────────────────────────────────

    /** @return Permission[] */
    public function defaultPermissions(): array
    {
        return match($this) {

            // Super Admin has no listed permissions.
            // Access is granted via Gate::before() bypass in AppServiceProvider.
            self::SUPER_ADMIN => [],

            // ── Factory Admin ─────────────────────────────────
            // Full factory control. Cannot manage other factories
            // or manipulate the role/permission system globally.
            self::FACTORY_ADMIN => [
                Permission::VIEW_ANY_FACTORY,
                Permission::VIEW_FACTORY,
                Permission::UPDATE_FACTORY,
                Permission::VIEW_FACTORY_SETTINGS,
                Permission::UPDATE_FACTORY_SETTINGS,

                Permission::VIEW_ANY_USER,
                Permission::VIEW_USER,
                Permission::CREATE_USER,
                Permission::UPDATE_USER,
                Permission::DELETE_USER,
                Permission::ASSIGN_ROLE_USER,
                Permission::REVOKE_ROLE_USER,

                Permission::VIEW_ANY_ROLE,
                Permission::VIEW_ROLE,
                Permission::CREATE_ROLE,
                Permission::UPDATE_ROLE,
                // Intentionally excluded: DELETE_ROLE, SYNC_PERMISSIONS_ROLE

                Permission::VIEW_ANY_MACHINE,
                Permission::VIEW_MACHINE,
                Permission::CREATE_MACHINE,
                Permission::UPDATE_MACHINE,
                Permission::DELETE_MACHINE,
                Permission::VIEW_MACHINE_LOGS,

                Permission::VIEW_ANY_DOWNTIME,
                Permission::VIEW_DOWNTIME,
                Permission::CREATE_DOWNTIME,
                Permission::UPDATE_DOWNTIME,
                Permission::DELETE_DOWNTIME,
                Permission::CLOSE_DOWNTIME,
                Permission::CLASSIFY_DOWNTIME,

                Permission::VIEW_ANY_PRODUCTION_PLAN,
                Permission::VIEW_PRODUCTION_PLAN,
                Permission::CREATE_PRODUCTION_PLAN,
                Permission::UPDATE_PRODUCTION_PLAN,
                Permission::DELETE_PRODUCTION_PLAN,
                Permission::APPROVE_PRODUCTION_PLAN,

                Permission::VIEW_ANY_PRODUCTION_ACTUAL,
                Permission::VIEW_PRODUCTION_ACTUAL,
                Permission::CREATE_PRODUCTION_ACTUAL,
                Permission::UPDATE_PRODUCTION_ACTUAL,

                Permission::VIEW_ANY_PART,
                Permission::VIEW_PART,
                Permission::CREATE_PART,
                Permission::UPDATE_PART,
                Permission::DELETE_PART,
                Permission::VIEW_ANY_PROCESS_MASTER,
                Permission::CREATE_PROCESS_MASTER,
                Permission::UPDATE_PROCESS_MASTER,
                Permission::DELETE_PROCESS_MASTER,

                Permission::VIEW_ANY_CUSTOMER,
                Permission::VIEW_CUSTOMER,
                Permission::CREATE_CUSTOMER,
                Permission::UPDATE_CUSTOMER,
                Permission::DELETE_CUSTOMER,

                Permission::VIEW_ANY_SHIFT,
                Permission::CREATE_SHIFT,
                Permission::UPDATE_SHIFT,
                Permission::DELETE_SHIFT,

                Permission::VIEW_OEE_REPORT,
                Permission::VIEW_PRODUCTION_REPORT,
                Permission::VIEW_DOWNTIME_REPORT,
                Permission::VIEW_MACHINE_REPORT,
                Permission::EXPORT_OEE_REPORT,
                Permission::EXPORT_PRODUCTION_REPORT,
                Permission::EXPORT_DOWNTIME_REPORT,
                Permission::EXPORT_MACHINE_REPORT,
            ],

            // ── Production Manager ────────────────────────────
            // Plans and monitors production. No user/role management.
            self::PRODUCTION_MANAGER => [
                Permission::VIEW_ANY_MACHINE,
                Permission::VIEW_MACHINE,
                Permission::VIEW_MACHINE_LOGS,

                Permission::VIEW_ANY_DOWNTIME,
                Permission::VIEW_DOWNTIME,
                Permission::CREATE_DOWNTIME,
                Permission::UPDATE_DOWNTIME,
                Permission::CLOSE_DOWNTIME,
                Permission::CLASSIFY_DOWNTIME,

                Permission::VIEW_ANY_PRODUCTION_PLAN,
                Permission::VIEW_PRODUCTION_PLAN,
                Permission::CREATE_PRODUCTION_PLAN,
                Permission::UPDATE_PRODUCTION_PLAN,
                Permission::DELETE_PRODUCTION_PLAN,
                Permission::APPROVE_PRODUCTION_PLAN,

                Permission::VIEW_ANY_PRODUCTION_ACTUAL,
                Permission::VIEW_PRODUCTION_ACTUAL,
                Permission::CREATE_PRODUCTION_ACTUAL,
                Permission::UPDATE_PRODUCTION_ACTUAL,

                Permission::VIEW_ANY_PART,
                Permission::VIEW_PART,
                Permission::CREATE_PART,
                Permission::UPDATE_PART,
                Permission::VIEW_ANY_PROCESS_MASTER,
                Permission::CREATE_PROCESS_MASTER,
                Permission::UPDATE_PROCESS_MASTER,

                Permission::VIEW_ANY_CUSTOMER,
                Permission::VIEW_CUSTOMER,
                Permission::CREATE_CUSTOMER,
                Permission::UPDATE_CUSTOMER,

                Permission::VIEW_ANY_SHIFT,
                Permission::CREATE_SHIFT,
                Permission::UPDATE_SHIFT,

                Permission::VIEW_OEE_REPORT,
                Permission::VIEW_PRODUCTION_REPORT,
                Permission::VIEW_DOWNTIME_REPORT,
                Permission::VIEW_MACHINE_REPORT,
                Permission::EXPORT_OEE_REPORT,
                Permission::EXPORT_PRODUCTION_REPORT,
                Permission::EXPORT_DOWNTIME_REPORT,
            ],

            // ── Supervisor ────────────────────────────────────
            // Oversees shift. Records actuals, classifies downtime.
            // Cannot approve plans or delete core data.
            self::SUPERVISOR => [
                Permission::VIEW_ANY_MACHINE,
                Permission::VIEW_MACHINE,

                Permission::VIEW_ANY_DOWNTIME,
                Permission::VIEW_DOWNTIME,
                Permission::CREATE_DOWNTIME,
                Permission::UPDATE_DOWNTIME,
                Permission::CLOSE_DOWNTIME,
                Permission::CLASSIFY_DOWNTIME,

                Permission::VIEW_ANY_PRODUCTION_PLAN,
                Permission::VIEW_PRODUCTION_PLAN,
                Permission::UPDATE_PRODUCTION_PLAN,

                Permission::VIEW_ANY_PRODUCTION_ACTUAL,
                Permission::VIEW_PRODUCTION_ACTUAL,
                Permission::CREATE_PRODUCTION_ACTUAL,
                Permission::UPDATE_PRODUCTION_ACTUAL,

                Permission::VIEW_ANY_PART,
                Permission::VIEW_PART,
                Permission::VIEW_ANY_PROCESS_MASTER,

                Permission::VIEW_ANY_CUSTOMER,
                Permission::VIEW_CUSTOMER,

                Permission::VIEW_ANY_SHIFT,

                Permission::VIEW_OEE_REPORT,
                Permission::VIEW_PRODUCTION_REPORT,
                Permission::VIEW_DOWNTIME_REPORT,
                Permission::VIEW_MACHINE_REPORT,
            ],

            // ── Operator ──────────────────────────────────────
            // Front-line worker. Records production; reports faults.
            // Read-only on plans; cannot view analytics reports.
            self::OPERATOR => [
                Permission::VIEW_ANY_MACHINE,
                Permission::VIEW_MACHINE,

                Permission::VIEW_ANY_DOWNTIME,
                Permission::VIEW_DOWNTIME,
                Permission::CREATE_DOWNTIME,

                Permission::VIEW_ANY_PRODUCTION_PLAN,
                Permission::VIEW_PRODUCTION_PLAN,

                Permission::VIEW_ANY_PRODUCTION_ACTUAL,
                Permission::VIEW_PRODUCTION_ACTUAL,
                Permission::CREATE_PRODUCTION_ACTUAL,

                Permission::VIEW_ANY_PART,
                Permission::VIEW_PART,

                Permission::VIEW_ANY_CUSTOMER,

                Permission::VIEW_ANY_SHIFT,
            ],

            // ── Viewer ────────────────────────────────────────
            // Read-only dashboard access. Cannot create or modify anything.
            self::VIEWER => [
                Permission::VIEW_ANY_MACHINE,
                Permission::VIEW_MACHINE,

                Permission::VIEW_ANY_DOWNTIME,
                Permission::VIEW_DOWNTIME,

                Permission::VIEW_ANY_PRODUCTION_PLAN,
                Permission::VIEW_PRODUCTION_PLAN,

                Permission::VIEW_ANY_PRODUCTION_ACTUAL,
                Permission::VIEW_PRODUCTION_ACTUAL,

                Permission::VIEW_ANY_PART,
                Permission::VIEW_PART,

                Permission::VIEW_ANY_CUSTOMER,
                Permission::VIEW_CUSTOMER,

                Permission::VIEW_ANY_SHIFT,

                Permission::VIEW_OEE_REPORT,
                Permission::VIEW_PRODUCTION_REPORT,
                Permission::VIEW_DOWNTIME_REPORT,
                Permission::VIEW_MACHINE_REPORT,
            ],
        };
    }
}
