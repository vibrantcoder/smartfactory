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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
                'working_hours_per_day'    => 16.00,
                'log_interval_seconds'     => 30,
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
        app(PermissionRegistrar::class)->setPermissionsTeamId($factory->id);
        $factoryAdmin->syncRoles([Role::FACTORY_ADMIN->value]);

        // Operator — assigned to Machine A
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

        // ── 6. Create 10 Machines ──────────────────────────────
        $this->command->info('Creating 10 machines...');
        $machineDefs = [
            ['MCH-001', 'CNC Lathe A',         'CNC Lathe',      'HAAS-ST-10'],
            ['MCH-002', 'Milling Center B',    'Milling',         'HAAS-VF-2'],
            ['MCH-003', 'Quality Inspection',  'Inspection',      'CMM-ZEISS-01'],
            ['MCH-004', 'CNC Lathe B',         'CNC Lathe',      'HAAS-ST-20'],
            ['MCH-005', 'Grinding Machine A',  'Grinding',        'JUNG-G500'],
            ['MCH-006', 'Drilling Center A',   'Drilling',        'DMG-D500'],
            ['MCH-007', 'Milling Center C',    'Milling',         'HAAS-VF-3'],
            ['MCH-008', 'Welding Station A',   'Welding',         'KEMPPI-W250'],
            ['MCH-009', 'Turning Center A',    'CNC Lathe',      'MAZAK-QT15'],
            ['MCH-010', 'Assembly Station A',  'Assembly',        'MANUAL-01'],
        ];

        $machines = [];
        foreach ($machineDefs as [$code, $name, $type, $model]) {
            $machines[] = Machine::firstOrCreate(
                ['code' => $code],
                ['factory_id' => $factory->id, 'name' => $name, 'type' => $type, 'model' => $model, 'status' => 'active']
            );
        }

        // Assign operator to Machine A (MCH-001)
        $operator->update(['machine_id' => $machines[0]->id]);

        // ── 7. Create Process Masters ──────────────────────────
        $this->command->info('Creating process masters...');
        $turning = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-TURN'],
            [
                'name'                 => 'CNC Turning',
                'machine_type_default' => 'CNC Lathe',
                'description'          => 'Rough and finish turning on CNC lathe',
                'is_active'            => true,
            ]
        );
        $milling = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-MILL'],
            [
                'name'                 => 'CNC Milling',
                'machine_type_default' => 'Milling',
                'description'          => 'Multi-axis milling operation',
                'is_active'            => true,
            ]
        );
        $inspection = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-INSP'],
            [
                'name'                 => 'CMM Inspection',
                'machine_type_default' => 'Inspection',
                'description'          => 'Dimensional inspection on CMM',
                'is_active'            => true,
            ]
        );
        $grinding = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-GRND'],
            [
                'name'                 => 'Surface Grinding',
                'machine_type_default' => 'Grinding',
                'description'          => 'Surface grinding for finish',
                'is_active'            => true,
            ]
        );
        $drilling = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-DRIL'],
            [
                'name'                 => 'CNC Drilling',
                'machine_type_default' => 'Drilling',
                'description'          => 'Precision drilling and tapping',
                'is_active'            => true,
            ]
        );
        $welding = ProcessMaster::firstOrCreate(
            ['code' => 'PRC-WELD'],
            [
                'name'                 => 'MIG Welding',
                'machine_type_default' => 'Welding',
                'description'          => 'MIG welding and assembly',
                'is_active'            => true,
            ]
        );

        // ── 8. Create Customers ────────────────────────────────
        $this->command->info('Creating customers...');
        $customer = Customer::firstOrCreate(
            ['code' => 'CUST-ACME'],
            [
                'factory_id'     => $factory->id,
                'name'           => 'Acme Manufacturing Ltd',
                'contact_person' => 'John Smith',
                'email'          => 'john@acme.demo',
                'phone'          => '+1-555-0200',
                'status'         => 'active',
            ]
        );
        $customer2 = Customer::firstOrCreate(
            ['code' => 'CUST-BETA'],
            [
                'factory_id'     => $factory->id,
                'name'           => 'Beta Industries Sdn Bhd',
                'contact_person' => 'Raj Kumar',
                'email'          => 'raj@beta.demo',
                'phone'          => '+60-3-5550199',
                'status'         => 'active',
            ]
        );

        // ── 9. Create Parts with Routing ───────────────────────
        $this->command->info('Creating parts with routing...');

        // Part 1: Drive Shaft Assembly (5-step routing)
        $partShaft = Part::firstOrCreate(
            ['part_number' => 'ACM-SHAFT-001'],
            [
                'factory_id'       => $factory->id,
                'customer_id'      => $customer->id,
                'name'             => 'Drive Shaft Assembly',
                'revision'         => 'A',
                'cycle_time_std'   => 2100,  // 35 min in seconds
                'total_cycle_time' => 35.50,
                'unit'             => 'pcs',
                'status'           => 'active',
            ]
        );

        // Part 2: Bracket Assembly
        $partBracket = Part::firstOrCreate(
            ['part_number' => 'BETA-BRKT-002'],
            [
                'factory_id'       => $factory->id,
                'customer_id'      => $customer2->id,
                'name'             => 'Structural Bracket',
                'revision'         => 'B',
                'cycle_time_std'   => 1440,  // 24 min in seconds
                'total_cycle_time' => 24.00,
                'unit'             => 'pcs',
                'status'           => 'active',
            ]
        );

        // Routing for Shaft: Turn → Mill → Grind → Drill → Inspect
        if (PartProcess::where('part_id', $partShaft->id)->doesntExist()) {
            PartProcess::insert([
                ['part_id' => $partShaft->id, 'process_master_id' => $turning->id,    'sequence_order' => 1, 'standard_cycle_time' => null, 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => $partShaft->id, 'process_master_id' => $milling->id,    'sequence_order' => 2, 'standard_cycle_time' => null, 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => $partShaft->id, 'process_master_id' => $grinding->id,   'sequence_order' => 3, 'standard_cycle_time' => 7.50, 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => $partShaft->id, 'process_master_id' => $drilling->id,   'sequence_order' => 4, 'standard_cycle_time' => null, 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => $partShaft->id, 'process_master_id' => $inspection->id, 'sequence_order' => 5, 'standard_cycle_time' => 3.00, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Routing for Bracket: Mill → Weld → Inspect
        if (PartProcess::where('part_id', $partBracket->id)->doesntExist()) {
            PartProcess::insert([
                ['part_id' => $partBracket->id, 'process_master_id' => $milling->id,    'sequence_order' => 1, 'standard_cycle_time' => null, 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => $partBracket->id, 'process_master_id' => $welding->id,    'sequence_order' => 2, 'standard_cycle_time' => null, 'created_at' => now(), 'updated_at' => now()],
                ['part_id' => $partBracket->id, 'process_master_id' => $inspection->id, 'sequence_order' => 3, 'standard_cycle_time' => 2.50, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // ── 10. Create Production Plans for today ──────────────
        $this->command->info('Creating production plans...');
        $today = today()->toDateString();

        // Look up PartProcess IDs for plan assignment
        $ppTurning    = PartProcess::where('part_id', $partShaft->id)->where('process_master_id', $turning->id)->first();
        $ppMillingS   = PartProcess::where('part_id', $partShaft->id)->where('process_master_id', $milling->id)->first();
        $ppGrinding   = PartProcess::where('part_id', $partShaft->id)->where('process_master_id', $grinding->id)->first();
        $ppDrilling   = PartProcess::where('part_id', $partShaft->id)->where('process_master_id', $drilling->id)->first();
        $ppInspShaft  = PartProcess::where('part_id', $partShaft->id)->where('process_master_id', $inspection->id)->first();
        $ppMillingB   = PartProcess::where('part_id', $partBracket->id)->where('process_master_id', $milling->id)->first();
        $ppWelding    = PartProcess::where('part_id', $partBracket->id)->where('process_master_id', $welding->id)->first();
        $ppInspBrkt   = PartProcess::where('part_id', $partBracket->id)->where('process_master_id', $inspection->id)->first();

        // [ machine_idx, part, part_process, shift, planned_qty, status ]
        $planDefs = [
            // MCH-001: CNC Lathe A — Turning (both shifts)
            [0, $partShaft,   $ppTurning,  $morning,   48, 'in_progress'],
            [0, $partShaft,   $ppTurning,  $afternoon, 48, 'scheduled'],
            // MCH-002: Milling Center B — Milling Shaft (morning) + Bracket (afternoon)
            [1, $partShaft,   $ppMillingS, $morning,   36, 'in_progress'],
            [1, $partBracket, $ppMillingB, $afternoon, 30, 'scheduled'],
            // MCH-003: Quality Inspection — Shaft inspection (morning)
            [2, $partShaft,   $ppInspShaft,$morning,   80, 'in_progress'],
            [2, $partBracket, $ppInspBrkt, $afternoon, 60, 'scheduled'],
            // MCH-004: CNC Lathe B — Turning shaft (both shifts)
            [3, $partShaft,   $ppTurning,  $morning,   48, 'in_progress'],
            [3, $partShaft,   $ppTurning,  $afternoon, 48, 'scheduled'],
            // MCH-005: Grinding — Shaft grinding
            [4, $partShaft,   $ppGrinding, $morning,   60, 'in_progress'],
            [4, $partShaft,   $ppGrinding, $afternoon, 60, 'scheduled'],
            // MCH-006: Drilling Center — Shaft drilling
            [5, $partShaft,   $ppDrilling, $morning,   80, 'in_progress'],
            [5, $partShaft,   $ppDrilling, $afternoon, 80, 'scheduled'],
            // MCH-007: Milling Center C — Bracket milling (both shifts)
            [6, $partBracket, $ppMillingB, $morning,   40, 'in_progress'],
            [6, $partBracket, $ppMillingB, $afternoon, 40, 'scheduled'],
            // MCH-008: Welding Station — Bracket welding
            [7, $partBracket, $ppWelding,  $morning,   35, 'in_progress'],
            [7, $partBracket, $ppWelding,  $afternoon, 35, 'scheduled'],
            // MCH-009: Turning Center A — Shaft turning
            [8, $partShaft,   $ppTurning,  $morning,   44, 'in_progress'],
            [8, $partShaft,   $ppTurning,  $afternoon, 44, 'scheduled'],
            // MCH-010: Assembly Station — Bracket final (morning only)
            [9, $partBracket, $ppWelding,  $morning,   25, 'scheduled'],
        ];

        foreach ($planDefs as [$machIdx, $part, $partProcess, $shift, $qty, $status]) {
            ProductionPlan::firstOrCreate(
                [
                    'factory_id'   => $factory->id,
                    'machine_id'   => $machines[$machIdx]->id,
                    'part_id'      => $part->id,
                    'shift_id'     => $shift->id,
                    'planned_date' => $today,
                ],
                [
                    'part_process_id' => $partProcess?->id,
                    'planned_qty'     => $qty,
                    'status'          => $status,
                    'notes'           => 'Demo seeder plan',
                ]
            );
        }

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
            DB::table('downtime_reasons')->insertOrIgnore(array_merge($reason, [
                'factory_id' => $factory->id,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // ── 12. Seed IoT demo data for all 10 machines ─────────
        $this->command->info('Seeding IoT demo data (10 machines × 2 shifts)...');
        $this->seedIotData($machines, $morning, $afternoon, $factory->id);

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
        $this->command->info("Machines: 10 | Parts: 2 | Shifts: Morning (06–14) + Afternoon (14–22)");
    }

    /**
     * Seed realistic IoT telemetry for today's two shifts across all machines.
     *
     * Interval: 30 seconds → 960 records per machine per shift → ~19,200 total rows.
     *
     * Each machine gets a performance profile:
     *   [ cycle_rate, reject_rate, alarm_rate, auto_pct ]
     *
     * cycle_rate  = fraction of ticks where machine is cycling (producing)
     * reject_rate = fraction of part pulses that are rejects
     * alarm_rate  = fraction of ticks in alarm state
     * auto_pct    = fraction of ticks in auto mode (idle or running)
     */
    private function seedIotData(array $machines, Shift $morning, Shift $afternoon, int $factoryId): void
    {
        $today = today()->toDateString();

        // Skip if we already have today's data for machine 1
        if (DB::table('iot_logs')
            ->where('machine_id', $machines[0]->id)
            ->whereDate('logged_at', $today)
            ->exists()
        ) {
            $this->command->info('  IoT logs already exist for today — skipping.');
            return;
        }

        // Performance profiles per machine (indexed 0–9)
        // [ cycle_rate, reject_rate, alarm_rate, auto_pct ]
        $profiles = [
            [0.88, 0.015, 0.010, 0.95],  // MCH-001: CNC Lathe A     — high performer
            [0.83, 0.025, 0.015, 0.92],  // MCH-002: Milling Center B — solid
            [0.76, 0.008, 0.008, 0.98],  // MCH-003: Quality Insp.    — slow but precise
            [0.85, 0.020, 0.012, 0.93],  // MCH-004: CNC Lathe B      — good
            [0.71, 0.040, 0.030, 0.88],  // MCH-005: Grinding A       — moderate
            [0.79, 0.030, 0.020, 0.91],  // MCH-006: Drilling A       — average
            [0.91, 0.010, 0.008, 0.96],  // MCH-007: Milling Center C — best performer
            [0.64, 0.050, 0.040, 0.84],  // MCH-008: Welding A        — lower efficiency
            [0.84, 0.018, 0.015, 0.94],  // MCH-009: Turning Center A — good
            [0.69, 0.030, 0.020, 0.87],  // MCH-010: Assembly A       — manual-assisted
        ];

        $now    = now()->format('Y-m-d H:i:s');
        $buffer = [];

        foreach ($machines as $idx => $machine) {
            [$cycleRate, $rejectRate, $alarmRate, $autoPct] = $profiles[$idx] ?? $profiles[0];

            foreach ([$morning, $afternoon] as $shift) {
                $ts    = Carbon::parse($today . ' ' . $shift->start_time);
                $tsEnd = Carbon::parse($today . ' ' . $shift->end_time);

                // Simulate a "micro-stop" block mid-shift (random 15–30 min window)
                $stopStart = $ts->copy()->addMinutes(rand(60, 180));
                $stopEnd   = $stopStart->copy()->addMinutes(rand(15, 30));

                while ($ts < $tsEnd) {
                    $inStop = $ts->between($stopStart, $stopEnd);

                    if ($inStop) {
                        // Micro-stop: machine offline / alarm
                        $isAlarm    = (rand(1, 100) <= 40); // 40% chance alarm during stop
                        $isCycling  = false;
                        $isAuto     = !$isAlarm;
                        $partCount  = 0;
                        $partReject = 0;
                        $alarmCode  = $isAlarm ? rand(1, 5) : 0;
                    } else {
                        $isAlarm    = (rand(1, 10000) / 10000) < $alarmRate;
                        $isAuto     = (rand(1, 10000) / 10000) < $autoPct;
                        $isCycling  = !$isAlarm && (rand(1, 10000) / 10000) < $cycleRate;
                        // Part pulse: ~1 part per 2 min when cycling (30-sec ticks → 1-in-4 chance)
                        $partCount  = ($isCycling && rand(1, 4) === 1) ? 1 : 0;
                        $partReject = ($partCount && (rand(1, 10000) / 10000) < $rejectRate) ? 1 : 0;
                        $alarmCode  = $isAlarm ? rand(1, 5) : 0;
                    }

                    $buffer[] = [
                        'machine_id'  => $machine->id,
                        'factory_id'  => $factoryId,
                        'alarm_code'  => $alarmCode,
                        'auto_mode'   => $isAuto ? 1 : 0,
                        'cycle_state' => $isCycling ? 1 : 0,
                        'part_count'  => $partCount,
                        'part_reject' => $partReject,
                        'slave_id'    => (string) ($idx + 1),
                        'slave_name'  => $machine->code,
                        'logged_at'   => $ts->format('Y-m-d H:i:s'),
                        'created_at'  => $now,
                    ];

                    // Flush in batches of 500 to avoid memory spike
                    if (count($buffer) >= 500) {
                        DB::table('iot_logs')->insert($buffer);
                        $buffer = [];
                    }

                    $ts->addSeconds(30);
                }
            }

            $this->command->info("  {$machine->code}: logs written");
        }

        // Flush remainder
        foreach (array_chunk($buffer, 500) as $chunk) {
            DB::table('iot_logs')->insert($chunk);
        }
    }
}
