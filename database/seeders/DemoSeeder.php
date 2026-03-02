<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Factory\Models\Factory;
use App\Domain\Factory\Models\FactorySettings;
use App\Domain\Machine\Models\Machine;
use App\Domain\Production\Models\Customer;
use App\Domain\Production\Models\Part;
use App\Domain\Production\Models\PartProcess;
use App\Domain\Production\Models\ProcessMaster;
use App\Domain\Production\Models\ProductionPlan;
use App\Domain\Production\Models\Shift;
use App\Domain\Shared\Enums\Permission;
use App\Domain\Shared\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles & permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── 1. Seed all permissions ────────────────────────────
        $this->command->info('Seeding permissions...');
        foreach (Permission::cases() as $perm) {
            SpatiePermission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'sanctum']);
        }

        // ── 2. Seed roles + assign default permissions ─────────
        $this->command->info('Seeding roles...');
        foreach (Role::cases() as $roleEnum) {
            $role = SpatieRole::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'sanctum']);

            $perms = array_map(fn(Permission $p) => $p->value, $roleEnum->defaultPermissions());
            $role->syncPermissions($perms);
        }

        // ── 3. Create Factory ──────────────────────────────────
        $this->command->info('Creating factory...');
        $factory = Factory::firstOrCreate(
            ['code' => 'FAC-DEMO'],
            [
                'name'     => 'SmartFactory Demo Plant',
                'location' => '123 Industrial Ave, Demo City',
                'timezone' => 'Asia/Kuala_Lumpur',
                'status'   => 'active',
            ]
        );

        FactorySettings::firstOrCreate(
            ['factory_id' => $factory->id],
            [
                'oee_target_pct'           => 85.00,
                'availability_target_pct'  => 90.00,
                'performance_target_pct'   => 95.00,
                'quality_target_pct'       => 99.00,
                'working_hours_per_day'    => 8.00,
                'log_interval_seconds'     => 5,
                'downtime_threshold_min'   => 5,
                'aggregation_lag_min'      => 10,
                'raw_log_retention_days'   => 90,
            ]
        );

        // ── 4. Create Users ────────────────────────────────────
        $this->command->info('Creating users...');

        // Super Admin (no factory)
        $superAdmin = User::firstOrCreate(
            ['email' => 'super@demo.local'],
            [
                'name'       => 'Super Admin',
                'password'   => Hash::make('password'),
                'factory_id' => null,
                'is_active'  => true,
            ]
        );
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(0);
        $superAdmin->syncRoles([Role::SUPER_ADMIN->value]);

        // Factory Admin
        $factoryAdmin = User::firstOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'name'       => 'Factory Admin',
                'password'   => Hash::make('password'),
                'factory_id' => $factory->id,
                'is_active'  => true,
            ]
        );
        // Assign role with team scope
        app(PermissionRegistrar::class)->setPermissionsTeamId($factory->id);
        $factoryAdmin->syncRoles([Role::FACTORY_ADMIN->value]);

        // Operator
        $operator = User::firstOrCreate(
            ['email' => 'operator@demo.local'],
            [
                'name'       => 'Line Operator',
                'password'   => Hash::make('password'),
                'factory_id' => $factory->id,
                'is_active'  => true,
            ]
        );
        $operator->syncRoles([Role::OPERATOR->value]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        // ── 5. Create Shifts ───────────────────────────────────
        $this->command->info('Creating shifts...');
        $morning = Shift::firstOrCreate(
            ['factory_id' => $factory->id, 'name' => 'Morning Shift'],
            ['start_time' => '06:00:00', 'end_time' => '14:00:00', 'duration_min' => 480, 'is_active' => true]
        );
        $afternoon = Shift::firstOrCreate(
            ['factory_id' => $factory->id, 'name' => 'Afternoon Shift'],
            ['start_time' => '14:00:00', 'end_time' => '22:00:00', 'duration_min' => 480, 'is_active' => true]
        );

        // ── 6. Create Machines ─────────────────────────────────
        $this->command->info('Creating machines...');
        $machineA = Machine::firstOrCreate(
            ['code' => 'MCH-001'],
            [
                'factory_id'   => $factory->id,
                'name'         => 'CNC Lathe A',
                'type'         => 'CNC Lathe',
                'model'        => 'HAAS-ST-10',
                'status'       => 'active',
                
            ]
        );
        $machineB = Machine::firstOrCreate(
            ['code' => 'MCH-002'],
            [
                'factory_id'   => $factory->id,
                'name'         => 'Milling Center B',
                'type'         => 'Milling',
                'model'        => 'HAAS-VF-2',
                'status'       => 'active',
                
            ]
        );
        $machineC = Machine::firstOrCreate(
            ['code' => 'MCH-003'],
            [
                'factory_id'   => $factory->id,
                'name'         => 'Quality Inspection',
                'type'         => 'Inspection',
                'model'        => 'CMM-ZEISS-01',
                'status'       => 'active',
                
            ]
        );

        // ── 7. Create Process Masters ──────────────────────────
        $this->command->info('Creating process masters...');
        $turning = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-TURN'],
            [
                'name'                         => 'CNC Turning',
                'machine_type_default' => 'CNC Lathe',
                'standard_time'        => 12.50,
                'description'                  => 'Rough and finish turning on CNC lathe',
                'is_active'                    => true,
            ]
        );
        $milling = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-MILL'],
            [
                'name'                         => 'CNC Milling',
                'machine_type_default' => 'Milling',
                'standard_time'        => 18.00,
                'description'                  => 'Multi-axis milling operation',
                'is_active'                    => true,
            ]
        );
        $inspection = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-INSP'],
            [
                'name'                         => 'CMM Inspection',
                'machine_type_default' => 'Inspection',
                'standard_time'        => 5.00,
                'description'                  => 'Dimensional inspection on CMM',
                'is_active'                    => true,
            ]
        );

        // ── 8. Create Customer ─────────────────────────────────
        $this->command->info('Creating customer...');
        $customer = Customer::firstOrCreate(
            ['code' => 'CUST-ACME'],
            [
                'factory_id'    => $factory->id,
                'name'          => 'Acme Manufacturing Ltd',
                'contact_person' => 'John Smith',
                'email'          => 'john@acme.demo',
                'phone'          => '+1-555-0200',
                'status'         => 'active',
            ]
        );

        // ── 9. Create Part with Routing ────────────────────────
        $this->command->info('Creating parts with routing...');
        $part = Part::firstOrCreate(
            ['part_number' => 'ACM-SHAFT-001'],
            [
                'factory_id'      => $factory->id,
                'customer_id'     => $customer->id,
                'name'            => 'Drive Shaft Assembly',
                'revision'        => 'A',
                'cycle_time_std'  => 2100, // 35 min in seconds
                'total_cycle_time'=> 35.50,
                'status'          => 'active',
            ]
        );

        // Routing: Turn → Mill → Inspect
        if (PartProcess::where('part_id', $part->id)->doesntExist()) {
            PartProcess::insert([
                ['part_id' => $part->id, 'process_master_id' => $turning->id,    'sequence_order' => 1, 'standard_cycle_time' => null, 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => $part->id, 'process_master_id' => $milling->id,    'sequence_order' => 2, 'standard_cycle_time' => null, 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => $part->id, 'process_master_id' => $inspection->id, 'sequence_order' => 3, 'standard_cycle_time' => 3.00, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // ── 10. Create Production Plan ─────────────────────────
        $this->command->info('Creating production plan...');
        ProductionPlan::firstOrCreate(
            [
                'factory_id' => $factory->id,
                'machine_id' => $machineA->id,
                'part_id'    => $part->id,
                'shift_id'   => $morning->id,
                'planned_date' => today()->toDateString(),
            ],
            [
                'planned_qty' => 24,
                'status'      => 'scheduled',
                'notes'       => 'Demo seeder plan',
            ]
        );

        // ── 11. Downtime reasons ───────────────────────────────
        $this->command->info('Creating downtime reasons...');
        $reasons = [
            ['name' => 'Machine Breakdown',    'code' => 'DT-BRDN', 'category' => 'unplanned'],
            ['name' => 'Scheduled Maintenance','code' => 'DT-MAINT','category' => 'planned'],
            ['name' => 'Material Shortage',    'code' => 'DT-MATL', 'category' => 'unplanned'],
            ['name' => 'Tooling Change',       'code' => 'DT-TOOL', 'category' => 'planned'],
            ['name' => 'Quality Hold',         'code' => 'DT-QUAL', 'category' => 'unplanned'],
        ];

        foreach ($reasons as $reason) {
            \Illuminate\Support\Facades\DB::table('downtime_reasons')->insertOrIgnore(array_merge($reason, [
                'factory_id' => $factory->id,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('');
        $this->command->info('✓ Demo seed complete!');
        $this->command->info('');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['super-admin',   'super@demo.local',    'password'],
                ['factory-admin', 'admin@demo.local',    'password'],
                ['operator',      'operator@demo.local', 'password'],
            ]
        );
        $this->command->info('');
        $this->command->info("Factory: {$factory->name} (ID: {$factory->id})");
    }
}
