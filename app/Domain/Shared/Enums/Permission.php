<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Typed permission constants for the Smart Factory RBAC system.
 *
 * Convention: {action}.{resource}
 * Actions:  view-any | view | create | update | delete | approve |
 *           close | classify | sync-permissions | assign-role | export
 *
 * These values are seeded into the `permissions` table via RbacSeeder.
 * Always reference this enum — never hardcode permission strings.
 */
enum Permission: string
{
    // ── Factory Management ────────────────────────────────────
    case VIEW_ANY_FACTORY           = 'view-any.factory';
    case VIEW_FACTORY               = 'view.factory';
    case CREATE_FACTORY             = 'create.factory';
    case UPDATE_FACTORY             = 'update.factory';
    case DELETE_FACTORY             = 'delete.factory';
    case VIEW_FACTORY_SETTINGS      = 'view.factory-settings';
    case UPDATE_FACTORY_SETTINGS    = 'update.factory-settings';

    // ── User Management ───────────────────────────────────────
    case VIEW_ANY_USER              = 'view-any.user';
    case VIEW_USER                  = 'view.user';
    case CREATE_USER                = 'create.user';
    case UPDATE_USER                = 'update.user';
    case DELETE_USER                = 'delete.user';
    case ASSIGN_ROLE_USER           = 'assign-role.user';
    case REVOKE_ROLE_USER           = 'revoke-role.user';

    // ── Role Management ───────────────────────────────────────
    case VIEW_ANY_ROLE              = 'view-any.role';
    case VIEW_ROLE                  = 'view.role';
    case CREATE_ROLE                = 'create.role';
    case UPDATE_ROLE                = 'update.role';
    case DELETE_ROLE                = 'delete.role';
    case SYNC_PERMISSIONS_ROLE      = 'sync-permissions.role';

    // ── Machine Management ────────────────────────────────────
    case VIEW_ANY_MACHINE           = 'view-any.machine';
    case VIEW_MACHINE               = 'view.machine';
    case CREATE_MACHINE             = 'create.machine';
    case UPDATE_MACHINE             = 'update.machine';
    case DELETE_MACHINE             = 'delete.machine';
    case VIEW_MACHINE_LOGS          = 'view.machine-logs';

    // ── Downtime Management ───────────────────────────────────
    case VIEW_ANY_DOWNTIME          = 'view-any.downtime';
    case VIEW_DOWNTIME              = 'view.downtime';
    case CREATE_DOWNTIME            = 'create.downtime';
    case UPDATE_DOWNTIME            = 'update.downtime';
    case DELETE_DOWNTIME            = 'delete.downtime';
    case CLOSE_DOWNTIME             = 'close.downtime';
    case CLASSIFY_DOWNTIME          = 'classify.downtime';

    // ── Production Planning ───────────────────────────────────
    case VIEW_ANY_PRODUCTION_PLAN   = 'view-any.production-plan';
    case VIEW_PRODUCTION_PLAN       = 'view.production-plan';
    case CREATE_PRODUCTION_PLAN     = 'create.production-plan';
    case UPDATE_PRODUCTION_PLAN     = 'update.production-plan';
    case DELETE_PRODUCTION_PLAN     = 'delete.production-plan';
    case APPROVE_PRODUCTION_PLAN    = 'approve.production-plan';

    // ── Production Actuals ────────────────────────────────────
    case VIEW_ANY_PRODUCTION_ACTUAL = 'view-any.production-actual';
    case VIEW_PRODUCTION_ACTUAL     = 'view.production-actual';
    case CREATE_PRODUCTION_ACTUAL   = 'create.production-actual';
    case UPDATE_PRODUCTION_ACTUAL   = 'update.production-actual';

    // ── Parts & Process Masters ───────────────────────────────
    case VIEW_ANY_PART              = 'view-any.part';
    case VIEW_PART                  = 'view.part';
    case CREATE_PART                = 'create.part';
    case UPDATE_PART                = 'update.part';
    case DELETE_PART                = 'delete.part';
    case VIEW_ANY_PROCESS_MASTER    = 'view-any.process-master';
    case CREATE_PROCESS_MASTER      = 'create.process-master';
    case UPDATE_PROCESS_MASTER      = 'update.process-master';
    case DELETE_PROCESS_MASTER      = 'delete.process-master';

    // ── Customer Management ───────────────────────────────────
    case VIEW_ANY_CUSTOMER          = 'view-any.customer';
    case VIEW_CUSTOMER              = 'view.customer';
    case CREATE_CUSTOMER            = 'create.customer';
    case UPDATE_CUSTOMER            = 'update.customer';
    case DELETE_CUSTOMER            = 'delete.customer';

    // ── Shift Management ──────────────────────────────────────
    case VIEW_ANY_SHIFT             = 'view-any.shift';
    case CREATE_SHIFT               = 'create.shift';
    case UPDATE_SHIFT               = 'update.shift';
    case DELETE_SHIFT               = 'delete.shift';

    // ── Analytics & Reports ───────────────────────────────────
    case VIEW_OEE_REPORT            = 'view.oee-report';
    case VIEW_PRODUCTION_REPORT     = 'view.production-report';
    case VIEW_DOWNTIME_REPORT       = 'view.downtime-report';
    case VIEW_MACHINE_REPORT        = 'view.machine-report';
    case EXPORT_OEE_REPORT          = 'export.oee-report';
    case EXPORT_PRODUCTION_REPORT   = 'export.production-report';
    case EXPORT_DOWNTIME_REPORT     = 'export.downtime-report';
    case EXPORT_MACHINE_REPORT      = 'export.machine-report';

    // ─────────────────────────────────────────────────────────
    // Metadata helpers
    // ─────────────────────────────────────────────────────────

    /** Human-readable label for the admin UI */
    public function label(): string
    {
        return match($this) {
            self::VIEW_ANY_FACTORY           => 'List Factories',
            self::VIEW_FACTORY               => 'View Factory',
            self::CREATE_FACTORY             => 'Create Factory',
            self::UPDATE_FACTORY             => 'Update Factory',
            self::DELETE_FACTORY             => 'Delete Factory',
            self::VIEW_FACTORY_SETTINGS      => 'View Factory Settings',
            self::UPDATE_FACTORY_SETTINGS    => 'Update Factory Settings',

            self::VIEW_ANY_USER              => 'List Users',
            self::VIEW_USER                  => 'View User',
            self::CREATE_USER                => 'Create User',
            self::UPDATE_USER                => 'Update User',
            self::DELETE_USER                => 'Delete User',
            self::ASSIGN_ROLE_USER           => 'Assign Role to User',
            self::REVOKE_ROLE_USER           => 'Revoke Role from User',

            self::VIEW_ANY_ROLE              => 'List Roles',
            self::VIEW_ROLE                  => 'View Role',
            self::CREATE_ROLE                => 'Create Role',
            self::UPDATE_ROLE                => 'Update Role',
            self::DELETE_ROLE                => 'Delete Role',
            self::SYNC_PERMISSIONS_ROLE      => 'Sync Role Permissions',

            self::VIEW_ANY_MACHINE           => 'List Machines',
            self::VIEW_MACHINE               => 'View Machine',
            self::CREATE_MACHINE             => 'Create Machine',
            self::UPDATE_MACHINE             => 'Update Machine',
            self::DELETE_MACHINE             => 'Delete Machine',
            self::VIEW_MACHINE_LOGS          => 'View Machine Logs',

            self::VIEW_ANY_DOWNTIME          => 'List Downtimes',
            self::VIEW_DOWNTIME              => 'View Downtime',
            self::CREATE_DOWNTIME            => 'Create Downtime',
            self::UPDATE_DOWNTIME            => 'Update Downtime',
            self::DELETE_DOWNTIME            => 'Delete Downtime',
            self::CLOSE_DOWNTIME             => 'Close Downtime',
            self::CLASSIFY_DOWNTIME          => 'Classify Downtime',

            self::VIEW_ANY_PRODUCTION_PLAN   => 'List Production Plans',
            self::VIEW_PRODUCTION_PLAN       => 'View Production Plan',
            self::CREATE_PRODUCTION_PLAN     => 'Create Production Plan',
            self::UPDATE_PRODUCTION_PLAN     => 'Update Production Plan',
            self::DELETE_PRODUCTION_PLAN     => 'Delete Production Plan',
            self::APPROVE_PRODUCTION_PLAN    => 'Approve Production Plan',

            self::VIEW_ANY_PRODUCTION_ACTUAL => 'List Production Actuals',
            self::VIEW_PRODUCTION_ACTUAL     => 'View Production Actual',
            self::CREATE_PRODUCTION_ACTUAL   => 'Record Production Actual',
            self::UPDATE_PRODUCTION_ACTUAL   => 'Update Production Actual',

            self::VIEW_ANY_PART              => 'List Parts',
            self::VIEW_PART                  => 'View Part',
            self::CREATE_PART                => 'Create Part',
            self::UPDATE_PART                => 'Update Part',
            self::DELETE_PART                => 'Delete Part',
            self::VIEW_ANY_PROCESS_MASTER    => 'List Process Masters',
            self::CREATE_PROCESS_MASTER      => 'Create Process Master',
            self::UPDATE_PROCESS_MASTER      => 'Update Process Master',
            self::DELETE_PROCESS_MASTER      => 'Delete Process Master',

            self::VIEW_ANY_CUSTOMER          => 'List Customers',
            self::VIEW_CUSTOMER              => 'View Customer',
            self::CREATE_CUSTOMER            => 'Create Customer',
            self::UPDATE_CUSTOMER            => 'Update Customer',
            self::DELETE_CUSTOMER            => 'Delete Customer',

            self::VIEW_ANY_SHIFT             => 'List Shifts',
            self::CREATE_SHIFT               => 'Create Shift',
            self::UPDATE_SHIFT               => 'Update Shift',
            self::DELETE_SHIFT               => 'Delete Shift',

            self::VIEW_OEE_REPORT            => 'View OEE Report',
            self::VIEW_PRODUCTION_REPORT     => 'View Production Report',
            self::VIEW_DOWNTIME_REPORT       => 'View Downtime Report',
            self::VIEW_MACHINE_REPORT        => 'View Machine Report',
            self::EXPORT_OEE_REPORT          => 'Export OEE Report',
            self::EXPORT_PRODUCTION_REPORT   => 'Export Production Report',
            self::EXPORT_DOWNTIME_REPORT     => 'Export Downtime Report',
            self::EXPORT_MACHINE_REPORT      => 'Export Machine Report',
        };
    }

    /** Group key for checkbox matrix grouping in the admin UI */
    public function group(): string
    {
        return match(true) {
            str_ends_with($this->value, '.factory') ||
            str_ends_with($this->value, '.factory-settings') => 'factory_management',

            str_ends_with($this->value, '.user') => 'user_management',
            str_ends_with($this->value, '.role') => 'role_management',
            str_ends_with($this->value, '.machine') ||
            str_ends_with($this->value, '.machine-logs') => 'machine_management',
            str_ends_with($this->value, '.downtime') => 'downtime_management',
            str_ends_with($this->value, '.production-plan') => 'production_planning',
            str_ends_with($this->value, '.production-actual') => 'production_actuals',
            str_ends_with($this->value, '.part') ||
            str_ends_with($this->value, '.process-master') => 'parts_and_processes',
            str_ends_with($this->value, '.customer') => 'customer_management',
            str_ends_with($this->value, '.shift') => 'shift_management',
            default => 'analytics_reports',
        };
    }

    /**
     * Returns all permissions grouped for the checkbox UI matrix.
     *
     * Structure:
     *   [
     *     'machine_management' => [
     *       'label'       => 'Machine Management',
     *       'permissions' => [Permission::VIEW_ANY_MACHINE, ...]
     *     ], ...
     *   ]
     *
     * @return array<string, array{label: string, permissions: Permission[]}>
     */
    public static function groupedMatrix(): array
    {
        $groups = [
            'factory_management' => ['label' => 'Factory Management',    'permissions' => []],
            'user_management'    => ['label' => 'User Management',       'permissions' => []],
            'role_management'    => ['label' => 'Role Management',       'permissions' => []],
            'machine_management' => ['label' => 'Machine Management',    'permissions' => []],
            'downtime_management'=> ['label' => 'Downtime Management',   'permissions' => []],
            'production_planning'=> ['label' => 'Production Planning',   'permissions' => []],
            'production_actuals' => ['label' => 'Production Actuals',    'permissions' => []],
            'parts_and_processes'=> ['label' => 'Parts & Processes',     'permissions' => []],
            'customer_management'=> ['label' => 'Customer Management',   'permissions' => []],
            'shift_management'   => ['label' => 'Shift Management',      'permissions' => []],
            'analytics_reports'  => ['label' => 'Analytics & Reports',   'permissions' => []],
        ];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()]['permissions'][] = $permission;
        }

        return $groups;
    }
}
