<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Shared\Enums\Permission as PermissionEnum;
use App\Domain\Shared\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RbacSeeder
 *
 * Seeds ALL roles and permissions for the Smart Factory RBAC system.
 * Run order: this seeder must run AFTER factories table is populated.
 *
 * IDEMPOTENT: safe to re-run; uses firstOrCreate throughout.
 *
 * TEAMS FEATURE:
 *   Spatie teams is enabled (config/permission.php: 'teams' => true).
 *   - Permissions and Roles are GLOBAL (no team_id).
 *   - Role assignments to users carry a team_id = factory_id.
 *   - Super Admin is assigned without team constraint.
 *
 * RUN:
 *   php artisan db:seed --class=RbacSeeder
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions (critical when re-seeding)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Seeding permissions...');
        $this->seedPermissions();

        $this->command->info('Seeding roles and assigning permissions...');
        $this->seedRoles();

        $this->command->info('Creating default Super Admin user...');
        $this->createSuperAdmin();

        // Re-cache after seeding
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('✓ RBAC seeding complete.');
    }

    // ─────────────────────────────────────────────────────────
    // Step 1: Permissions
    // ─────────────────────────────────────────────────────────

    private function seedPermissions(): void
    {
        // Disable team constraint — permissions are global
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $created = 0;

        foreach (PermissionEnum::cases() as $permissionEnum) {
            Permission::firstOrCreate(
                ['name' => $permissionEnum->value, 'guard_name' => 'web'],
            );
            $created++;
        }

        $this->command->line("  → {$created} permissions created/verified.");
    }

    // ─────────────────────────────────────────────────────────
    // Step 2: Roles + Permission Assignment
    // ─────────────────────────────────────────────────────────

    private function seedRoles(): void
    {
        // Disable team constraint — roles are global templates
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::firstOrCreate(
                ['name' => $roleEnum->value, 'guard_name' => 'web'],
            );

            $permissions = $roleEnum->defaultPermissions();

            if (empty($permissions)) {
                // Super Admin: no listed permissions; access via Gate::before bypass.
                $this->command->line(
                    "  → [{$roleEnum->label()}] No permissions listed (Gate bypass active)."
                );
                continue;
            }

            $permissionNames = array_map(
                fn(PermissionEnum $p) => $p->value,
                $permissions
            );

            // syncPermissions is safe: removes old, adds new
            $role->syncPermissions($permissionNames);

            $this->command->line(
                "  → [{$roleEnum->label()}] synced " . count($permissionNames) . ' permissions.'
            );
        }
    }

    // ─────────────────────────────────────────────────────────
    // Step 3: Default Super Admin User
    // ─────────────────────────────────────────────────────────

    private function createSuperAdmin(): void
    {
        // Assign super-admin WITHOUT factory scope (team_id = null)
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $user = \App\Models\User::firstOrCreate(
            ['email' => 'superadmin@smartfactory.local'],
            [
                'name'       => 'Super Administrator',
                'factory_id' => null,               // Super Admin has no factory affiliation
                'password'   => Hash::make('changeme_on_first_login!'),
                'role'       => RoleEnum::SUPER_ADMIN->value, // denormalized for quick checks
                'status'     => 'active',
            ]
        );

        // Assign role without team constraint
        if (!$user->hasRole(RoleEnum::SUPER_ADMIN->value)) {
            $user->assignRole(RoleEnum::SUPER_ADMIN->value);
        }

        $this->command->line(
            "  → Super Admin: {$user->email} (password: changeme_on_first_login!)"
        );
        $this->command->warn('  ⚠  Change the Super Admin password immediately after first login.');
    }
}
