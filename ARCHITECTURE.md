# Smart Factory — Laravel 11 Production Architecture

> Domain-Driven, Microservice-Ready, Enterprise IoT System

---

## Table of Contents
1. [Architectural Principles](#1-architectural-principles)
2. [Full Folder Tree](#2-full-folder-tree)
3. [Domain Layer — In Depth](#3-domain-layer--in-depth)
4. [HTTP Layer — In Depth](#4-http-layer--in-depth)
5. [Jobs & Queue Architecture](#5-jobs--queue-architecture)
6. [Broadcasting & Real-Time](#6-broadcasting--real-time)
7. [Providers & Binding](#7-providers--binding)
8. [Routes Architecture](#8-routes-architecture)
9. [Config Files](#9-config-files)
10. [Microservice Split Guide](#10-microservice-split-guide)
11. [Data Flow — IoT Ingestion to Dashboard](#11-data-flow--iot-ingestion-to-dashboard)
12. [Queue Channel Design](#12-queue-channel-design)
13. [Testing Strategy](#13-testing-strategy)
14. [Key Design Decisions](#14-key-design-decisions)

---

## 1. Architectural Principles

```
┌─────────────────────────────────────────────────────────────────────┐
│                    LAYERED ARCHITECTURE                             │
│                                                                     │
│  HTTP Layer      →  Domain Layer      →  Infrastructure Layer       │
│  (Controllers,      (Services,           (Eloquent, Redis,          │
│   Requests,          Repositories,        Queue, Broadcast)         │
│   Resources)         Actions, DTOs)                                 │
│                                                                     │
│  DEPENDENCY RULE: outer layers depend on inner; never reverse.      │
└─────────────────────────────────────────────────────────────────────┘
```

**Five non-negotiable rules applied here:**
1. **Domain isolation** — each domain owns its Models, Repos, Services, DTOs, Events
2. **Interface-first repositories** — concrete Eloquent implementations bound via service provider
3. **DTOs cross every boundary** — raw arrays never leave a controller or a repository
4. **Actions = single public method** — no action class does more than one thing
5. **Dashboard never touches `machine_logs`** — only aggregation tables

---

## 2. Full Folder Tree

```
smartfactory/
│
├── app/
│   │
│   ├── Domain/                                  ← ALL business logic lives here
│   │   │
│   │   ├── Shared/                              ← Shared kernel (no business logic)
│   │   │   ├── Contracts/
│   │   │   │   ├── Repositories/
│   │   │   │   │   └── BaseRepositoryInterface.php
│   │   │   │   └── Services/
│   │   │   │       └── BaseServiceInterface.php
│   │   │   ├── DataTransferObjects/
│   │   │   │   ├── PaginationData.php
│   │   │   │   └── DateRangeData.php
│   │   │   ├── Enums/
│   │   │   │   ├── MachineStatus.php            ← running|idle|fault|changeover
│   │   │   │   ├── DowntimeCategory.php         ← planned|unplanned|breakdown|changeover
│   │   │   │   ├── DowntimeSource.php           ← auto|manual
│   │   │   │   ├── PlanStatus.php               ← scheduled|in_progress|completed|cancelled
│   │   │   │   └── UserRole.php                 ← admin|engineer|operator|viewer
│   │   │   ├── Exceptions/
│   │   │   │   ├── DomainException.php
│   │   │   │   └── UnauthorizedFactoryAccessException.php
│   │   │   ├── Models/
│   │   │   │   └── BaseModel.php                ← Shared Eloquent base (timestamps, casts)
│   │   │   ├── Traits/
│   │   │   │   ├── BelongsToFactory.php         ← Scope + relationship used by 8 models
│   │   │   │   └── HasFactoryScope.php          ← Global scope: WHERE factory_id = ?
│   │   │   └── ValueObjects/
│   │   │       ├── DateTimeRange.php            ← Immutable start/end pair with validation
│   │   │       └── OeePct.php                  ← Typed 0–100 decimal; prevents raw float bugs
│   │   │
│   │   ├── Factory/                             ── ── ── FUTURE: Tenant Service
│   │   │   ├── Actions/
│   │   │   │   ├── CreateFactoryAction.php
│   │   │   │   └── UpdateFactorySettingsAction.php
│   │   │   ├── DataTransferObjects/
│   │   │   │   ├── FactoryData.php
│   │   │   │   └── FactorySettingsData.php
│   │   │   ├── Events/
│   │   │   │   └── FactoryCreatedEvent.php
│   │   │   ├── Exceptions/
│   │   │   │   └── FactoryNotFoundException.php
│   │   │   ├── Models/
│   │   │   │   ├── Factory.php
│   │   │   │   └── FactorySettings.php
│   │   │   ├── QueryBuilders/
│   │   │   │   └── FactoryQueryBuilder.php
│   │   │   ├── Repositories/
│   │   │   │   ├── Contracts/
│   │   │   │   │   └── FactoryRepositoryInterface.php
│   │   │   │   └── EloquentFactoryRepository.php
│   │   │   └── Services/
│   │   │       └── FactoryService.php
│   │   │
│   │   ├── Machine/                             ── ── ── FUTURE: Machine Data Service
│   │   │   ├── Actions/
│   │   │   │   ├── AuthenticateDeviceAction.php     ← validates device_token from machines table
│   │   │   │   ├── CreateMachineAction.php
│   │   │   │   ├── IngestMachineLogAction.php       ← writes to machine_logs; fires event
│   │   │   │   └── UpdateMachineStatusAction.php
│   │   │   ├── DataTransferObjects/
│   │   │   │   ├── MachineData.php
│   │   │   │   ├── MachineLogData.php               ← maps raw IoT payload to typed DTO
│   │   │   │   └── MachineStatusData.php
│   │   │   ├── Events/
│   │   │   │   ├── MachineFaultDetectedEvent.php    ← triggers downtime detection job
│   │   │   │   ├── MachineLogReceivedEvent.php      ← triggers broadcast listener
│   │   │   │   └── MachineStatusChangedEvent.php
│   │   │   ├── Exceptions/
│   │   │   │   ├── InvalidDeviceTokenException.php
│   │   │   │   └── MachineNotFoundException.php
│   │   │   ├── Jobs/
│   │   │   │   ├── ProcessMachineLogJob.php         ← queue: machine-logs (high throughput)
│   │   │   │   └── DetectDowntimePatternsJob.php    ← queue: downtime-detection
│   │   │   ├── Listeners/
│   │   │   │   └── DispatchMachineLogJobListener.php
│   │   │   ├── Models/
│   │   │   │   ├── Machine.php
│   │   │   │   └── MachineLog.php                  ← no FK constraints (partitioned table)
│   │   │   ├── QueryBuilders/
│   │   │   │   ├── MachineQueryBuilder.php
│   │   │   │   └── MachineLogQueryBuilder.php      ← always scoped to partition by date
│   │   │   ├── Repositories/
│   │   │   │   ├── Contracts/
│   │   │   │   │   ├── MachineRepositoryInterface.php
│   │   │   │   │   └── MachineLogRepositoryInterface.php
│   │   │   │   ├── EloquentMachineRepository.php
│   │   │   │   └── EloquentMachineLogRepository.php
│   │   │   └── Services/
│   │   │       ├── MachineAuthService.php           ← token lookup + cache
│   │   │       └── MachineLogIngestionService.php   ← validates DTO, persists, dispatches
│   │   │
│   │   ├── Downtime/                            ── ── ── FUTURE: Part of Machine Data Service
│   │   │   ├── Actions/
│   │   │   │   ├── AutoDetectDowntimeAction.php     ← reads recent machine_logs fault pattern
│   │   │   │   ├── CloseDowntimeAction.php          ← sets ended_at + computes duration
│   │   │   │   └── CreateManualDowntimeAction.php
│   │   │   ├── DataTransferObjects/
│   │   │   │   └── DowntimeData.php
│   │   │   ├── Events/
│   │   │   │   ├── DowntimeOpenedEvent.php
│   │   │   │   └── DowntimeClosedEvent.php
│   │   │   ├── Exceptions/
│   │   │   │   └── DowntimeNotFoundException.php
│   │   │   ├── Jobs/
│   │   │   │   └── AutoCloseStaleDowntimeJob.php    ← queue: downtime-detection
│   │   │   ├── Listeners/
│   │   │   │   └── BroadcastDowntimeAlertListener.php
│   │   │   ├── Models/
│   │   │   │   ├── Downtime.php
│   │   │   │   └── DowntimeReason.php
│   │   │   ├── QueryBuilders/
│   │   │   │   └── DowntimeQueryBuilder.php
│   │   │   ├── Repositories/
│   │   │   │   ├── Contracts/
│   │   │   │   │   └── DowntimeRepositoryInterface.php
│   │   │   │   └── EloquentDowntimeRepository.php
│   │   │   └── Services/
│   │   │       ├── DowntimeDetectionService.php
│   │   │       └── DowntimeReportService.php
│   │   │
│   │   ├── Production/                          ── ── ── FUTURE: Production Service
│   │   │   ├── Actions/
│   │   │   │   ├── CreateProductionPlanAction.php
│   │   │   │   ├── RecordProductionActualAction.php
│   │   │   │   └── UpdatePlanStatusAction.php
│   │   │   ├── DataTransferObjects/
│   │   │   │   ├── CustomerData.php
│   │   │   │   ├── PartData.php
│   │   │   │   ├── PartProcessData.php
│   │   │   │   ├── ProductionActualData.php
│   │   │   │   └── ProductionPlanData.php
│   │   │   ├── Events/
│   │   │   │   ├── ProductionPlanCreatedEvent.php
│   │   │   │   ├── ProductionActualRecordedEvent.php
│   │   │   │   └── ProductionTargetReachedEvent.php
│   │   │   ├── Exceptions/
│   │   │   │   ├── PlanConflictException.php        ← machine double-booked in same shift
│   │   │   │   └── PlanAlreadyCompletedException.php
│   │   │   ├── Listeners/
│   │   │   │   └── BroadcastProductionUpdateListener.php
│   │   │   ├── Models/
│   │   │   │   ├── Customer.php
│   │   │   │   ├── Part.php
│   │   │   │   ├── PartProcess.php
│   │   │   │   ├── ProcessMaster.php
│   │   │   │   ├── ProductionActual.php
│   │   │   │   ├── ProductionPlan.php
│   │   │   │   └── Shift.php
│   │   │   ├── QueryBuilders/
│   │   │   │   ├── ProductionPlanQueryBuilder.php
│   │   │   │   └── ProductionActualQueryBuilder.php
│   │   │   ├── Repositories/
│   │   │   │   ├── Contracts/
│   │   │   │   │   ├── PartRepositoryInterface.php
│   │   │   │   │   └── ProductionPlanRepositoryInterface.php
│   │   │   │   ├── EloquentPartRepository.php
│   │   │   │   └── EloquentProductionPlanRepository.php
│   │   │   └── Services/
│   │   │       ├── ProductionPlanService.php
│   │   │       └── ProductionAttainmentService.php
│   │   │
│   │   └── Analytics/                           ── ── ── FUTURE: Analytics Service
│   │       ├── Actions/
│   │       │   ├── AggregateHourlyLogsAction.php    ← reads machine_logs; writes _hourly
│   │       │   ├── AggregateDailyLogsAction.php     ← reads _hourly; writes _daily
│   │       │   └── CalculateOeeAction.php           ← reads _daily + plans + downtime; writes oee_daily
│   │       ├── DataTransferObjects/
│   │       │   ├── HourlyAggregateData.php
│   │       │   ├── DailyAggregateData.php
│   │       │   └── OeeData.php
│   │       ├── Events/
│   │       │   ├── OeeCalculatedEvent.php
│   │       │   └── LowOeeDetectedEvent.php
│   │       ├── Exceptions/
│   │       │   └── InsufficientDataException.php
│   │       ├── Jobs/
│   │       │   ├── AggregateHourlyLogsJob.php       ← queue: aggregation; runs :10 past hour
│   │       │   ├── AggregateDailyLogsJob.php        ← queue: aggregation; runs at 00:30
│   │       │   └── CalculateOeeDailyJob.php         ← queue: oee; runs at shift end
│   │       ├── Listeners/
│   │       │   └── TriggerOeeAlertListener.php
│   │       ├── Models/
│   │       │   ├── MachineLogHourly.php
│   │       │   ├── MachineLogDaily.php
│   │       │   └── MachineOeeDaily.php
│   │       ├── QueryBuilders/
│   │       │   ├── MachineLogHourlyQueryBuilder.php
│   │       │   └── OeeDailyQueryBuilder.php
│   │       ├── Repositories/
│   │       │   ├── Contracts/
│   │       │   │   ├── AnalyticsRepositoryInterface.php
│   │       │   │   └── OeeRepositoryInterface.php
│   │       │   ├── EloquentAnalyticsRepository.php
│   │       │   └── EloquentOeeRepository.php
│   │       └── Services/
│   │           ├── AggregationService.php
│   │           └── OeeCalculationService.php
│   │
│   ├── Http/
│   │   │
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── V1/                          ← REST API for frontend / mobile
│   │   │   │   │   ├── Analytics/
│   │   │   │   │   │   ├── OeeController.php
│   │   │   │   │   │   └── ReportController.php
│   │   │   │   │   ├── Auth/
│   │   │   │   │   │   └── AuthController.php
│   │   │   │   │   ├── Downtime/
│   │   │   │   │   │   └── DowntimeController.php
│   │   │   │   │   ├── Factory/
│   │   │   │   │   │   └── FactoryController.php
│   │   │   │   │   ├── Machine/
│   │   │   │   │   │   └── MachineController.php
│   │   │   │   │   └── Production/
│   │   │   │   │       ├── ProductionActualController.php
│   │   │   │   │       └── ProductionPlanController.php
│   │   │   │   └── IoT/                         ← Device push endpoint — separate auth
│   │   │   │       └── MachineDataController.php    ← ONLY endpoint IoT devices call
│   │   │   │
│   │   │   └── Admin/                           ← Blade-based admin panel
│   │   │       ├── Auth/
│   │   │       │   └── AdminAuthController.php
│   │   │       ├── Dashboard/
│   │   │       │   └── DashboardController.php
│   │   │       ├── Factory/
│   │   │       │   └── FactoryController.php
│   │   │       ├── Machine/
│   │   │       │   └── MachineController.php
│   │   │       ├── OEE/
│   │   │       │   └── OeeController.php
│   │   │       ├── Production/
│   │   │       │   └── ProductionController.php
│   │   │       └── User/
│   │   │           └── UserController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── Api/
│   │   │   │   ├── AuthenticateMachineDevice.php    ← reads device_token; sets $request->machine
│   │   │   │   ├── EnsureFactoryScope.php           ← injects factory_id from JWT into request
│   │   │   │   └── ValidateApiVersion.php
│   │   │   └── Admin/
│   │   │       ├── EnsureAdminRole.php
│   │   │       └── RedirectIfNotAuthenticated.php
│   │   │
│   │   ├── Requests/
│   │   │   ├── Api/
│   │   │   │   ├── Analytics/
│   │   │   │   │   └── OeeReportRequest.php
│   │   │   │   ├── Downtime/
│   │   │   │   │   ├── CloseDowntimeRequest.php
│   │   │   │   │   └── CreateDowntimeRequest.php
│   │   │   │   ├── Machine/
│   │   │   │   │   └── CreateMachineRequest.php
│   │   │   │   └── Production/
│   │   │   │       ├── CreateProductionPlanRequest.php
│   │   │   │       └── RecordActualRequest.php
│   │   │   └── IoT/
│   │   │       └── MachineLogRequest.php            ← validates raw sensor payload structure
│   │   │
│   │   └── Resources/                           ← JSON API output shape
│   │       ├── Analytics/
│   │       │   ├── OeeDailyResource.php
│   │       │   └── OeeSummaryResource.php
│   │       ├── Downtime/
│   │       │   └── DowntimeResource.php
│   │       ├── Factory/
│   │       │   └── FactoryResource.php
│   │       ├── Machine/
│   │       │   ├── MachineResource.php
│   │       │   └── MachineStatusResource.php
│   │       └── Production/
│   │           ├── ProductionActualResource.php
│   │           └── ProductionPlanResource.php
│   │
│   ├── Broadcasting/                            ← Real-time push (Reverb / Pusher)
│   │   ├── Channels/
│   │   │   ├── FactoryChannel.php               ← presence channel: factory.{id}
│   │   │   └── MachineChannel.php               ← private channel: machine.{id}
│   │   └── Events/
│   │       ├── Dashboard/
│   │       │   └── DashboardMetricsUpdated.php  ← broadcasts every 60s; hourly aggregate
│   │       ├── Downtime/
│   │       │   └── DowntimeAlertBroadcast.php   ← fires when downtime opened/closed
│   │       ├── Machine/
│   │       │   └── MachineStatusBroadcast.php   ← fires when status changes
│   │       └── Production/
│   │           └── ProductionCountUpdated.php   ← fires when actual_qty updated
│   │
│   ├── Notifications/
│   │   ├── Downtime/
│   │   │   └── DowntimeAlertNotification.php    ← channels: mail + slack + database
│   │   ├── OEE/
│   │   │   └── LowOeeAlertNotification.php      ← fires when OEE < factory target
│   │   └── Production/
│   │       └── ProductionMilestoneNotification.php
│   │
│   ├── Support/                                 ← Cross-cutting non-domain utilities
│   │   ├── Macros/
│   │   │   └── QueryBuilderMacros.php           ← ::whereDateRange(), ::forFactory()
│   │   ├── Pipelines/
│   │   │   └── MachineLogPipeline.php           ← Laravel Pipeline: validate→normalize→persist
│   │   └── Cache/
│   │       ├── OeeCacheService.php              ← Redis wrapper; TTL per factory settings
│   │       └── MachineStatusCacheService.php    ← caches last-known status per machine
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── BroadcastServiceProvider.php
│       ├── DomainServiceProvider.php            ← registers all domain services + actions
│       ├── EventServiceProvider.php             ← maps every Event → Listener[]
│       └── RepositoryServiceProvider.php        ← binds every Interface → EloquentImpl
│
├── bootstrap/
│   ├── app.php                                  ← Laravel 11: middleware, routes, exceptions
│   └── providers.php                            ← lists all registered service providers
│
├── config/
│   ├── aggregation.php                          ← lag_minutes, batch_size, chunk_size
│   ├── factory.php                              ← defaults pushed to factory_settings seed
│   ├── iot.php                                  ← token_cache_ttl, rate_limit, queue_channel
│   └── oee.php                                  ← formula constants, alert thresholds
│
├── database/
│   ├── factories/
│   │   ├── FactoryFactory.php
│   │   ├── MachineFactory.php
│   │   ├── MachineLogFactory.php                ← generates realistic sensor data
│   │   ├── ProductionPlanFactory.php
│   │   └── UserFactory.php
│   ├── migrations/                              ← matches DDL order from smartfactory_ddl.sql
│   │   ├── 2026_01_01_000001_create_process_masters_table.php
│   │   ├── 2026_01_01_000002_create_downtime_reasons_table.php
│   │   ├── 2026_01_01_000003_create_factories_table.php
│   │   ├── 2026_01_01_000004_create_factory_settings_table.php
│   │   ├── 2026_01_01_000005_create_users_table.php
│   │   ├── 2026_01_01_000006_create_machines_table.php
│   │   ├── 2026_01_01_000007_create_customers_table.php
│   │   ├── 2026_01_01_000008_create_shifts_table.php
│   │   ├── 2026_01_01_000009_create_parts_table.php
│   │   ├── 2026_01_01_000010_create_part_processes_table.php
│   │   ├── 2026_01_01_000011_create_machine_logs_table.php      ← raw DDL; migrations cannot partition
│   │   ├── 2026_01_01_000012_create_downtimes_table.php
│   │   ├── 2026_01_01_000013_create_production_plans_table.php
│   │   ├── 2026_01_01_000014_create_production_actuals_table.php
│   │   ├── 2026_01_01_000015_create_machine_logs_hourly_table.php
│   │   ├── 2026_01_01_000016_create_machine_logs_daily_table.php
│   │   └── 2026_01_01_000017_create_machine_oee_daily_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── DevelopmentSeeder.php                ← realistic factory + 100 machines + 30-day logs
│       └── Reference/
│           ├── DowntimeReasonSeeder.php
│           └── ProcessMasterSeeder.php
│
├── routes/
│   ├── api.php                                  ← entry: loads V1 + IoT route files
│   ├── api_v1.php                               ← /api/v1/* — Sanctum auth
│   ├── api_iot.php                              ← /api/iot/* — device_token auth
│   ├── admin.php                                ← /admin/* — session auth + EnsureAdminRole
│   ├── channels.php                             ← Echo/Reverb channel authorization
│   └── web.php                                  ← SPA entry point only
│
├── storage/
│   └── logs/
│       ├── laravel.log
│       └── iot/
│           └── ingestion.log                    ← dedicated IoT channel (config/logging.php)
│
└── tests/
    ├── Feature/
    │   ├── Api/
    │   │   ├── Analytics/
    │   │   │   └── OeeReportTest.php
    │   │   ├── Machine/
    │   │   │   └── MachineControllerTest.php
    │   │   └── Production/
    │   │       └── ProductionPlanControllerTest.php
    │   ├── Admin/
    │   │   └── DashboardTest.php
    │   └── IoT/
    │       └── MachineDataIngestionTest.php     ← high-volume insert + queue dispatch test
    └── Unit/
        ├── Domain/
        │   ├── Analytics/
        │   │   ├── AggregationServiceTest.php
        │   │   └── OeeCalculationTest.php       ← pure math; no DB; fast
        │   ├── Downtime/
        │   │   └── DowntimeDetectionServiceTest.php
        │   ├── Machine/
        │   │   └── MachineLogIngestionTest.php
        │   └── Production/
        │       └── ProductionAttainmentTest.php
        └── ValueObjects/
            └── OeePctTest.php
```

---

## 3. Domain Layer — In Depth

### 3.1 Shared Kernel (`Domain/Shared/`)

The shared kernel contains **only** primitives used by more than one domain.
Cross-domain calls must go through **Events**, not direct service injection.

```
Domain/Shared/
├── Enums/                  ← PHP 8.1 backed enums; never raw strings in code
│   ├── MachineStatus.php       cases: Running, Idle, Fault, Changeover
│   ├── DowntimeCategory.php    cases: Planned, Unplanned, Breakdown, Changeover
│   ├── PlanStatus.php          cases: Scheduled, InProgress, Completed, Cancelled
│   └── UserRole.php            cases: Admin, Engineer, Operator, Viewer
│
├── ValueObjects/           ← Immutable, self-validating primitives
│   ├── DateTimeRange.php       from/to with validation (to > from)
│   └── OeePct.php              typed 0.00–100.00; prevents raw float logic bugs
│
└── Traits/
    ├── BelongsToFactory.php    ← relationship + scope used by 8+ models
    └── HasFactoryScope.php     ← global scope auto-applied; filters by factory_id
```

### 3.2 Actions vs Services

```
Actions                         Services
───────────────────────────     ───────────────────────────────────────
One public method: __invoke()   Multiple public methods (orchestration)
One responsibility              Coordinates multiple actions/repos
Dependency-injected via DI      Dependency-injected; bound as singleton
Independently testable          Integration-tested
Used by: Controllers, Jobs      Used by: Controllers (complex flows)

EXAMPLE:
IngestMachineLogAction          MachineLogIngestionService
  → validates DTO                 → calls AuthenticateDeviceAction
  → persists to machine_logs      → calls IngestMachineLogAction
  → dispatches event              → dispatches ProcessMachineLogJob
                                  → returns result DTO
```

### 3.3 Repository Pattern

```
Interface (Domain/Machine/Repositories/Contracts/)
  MachineLogRepositoryInterface
    + findByMachineAndDateRange(int $machineId, DateTimeRange $range): Collection
    + countByStatusInWindow(int $machineId, MachineStatus $status, DateTime $from): int
    + insertBatch(array $logs): void                  ← bulk insert for IoT throughput

Implementation (Domain/Machine/Repositories/)
  EloquentMachineLogRepository implements MachineLogRepositoryInterface
    → all Eloquent calls are here; never in services or controllers
    → always adds logged_at to WHERE clause (triggers partition pruning)
    → uses DB::table() for bulk inserts (bypasses Eloquent overhead)

Binding (Providers/RepositoryServiceProvider.php)
  $this->app->bind(
      MachineLogRepositoryInterface::class,
      EloquentMachineLogRepository::class
  );
```

### 3.4 Data Transfer Objects (DTOs)

All DTOs are **PHP 8.2 readonly classes**. They:
- Cross every layer boundary (IoT → Controller → Service → Repository)
- Never contain Eloquent models
- Fail fast in constructor if data is invalid

```
MachineLogData (readonly class)
  + machineId: int
  + loggedAt: CarbonImmutable          ← never DateTime; always immutable
  + status: MachineStatus              ← Enum; never raw string
  + productionCount: int
  + cycleTime: ?float
  + temperature: ?float
  + vibration: ?float
  + powerKw: ?float
  + rawPayload: array                  ← preserved for audit

  public static function fromRequest(MachineLogRequest $request): self
  public static function fromArray(array $data): self
```

### 3.5 QueryBuilders

Each domain's Eloquent models return a custom query builder.
This keeps complex query logic out of repositories.

```
MachineLogQueryBuilder extends Builder
  + forMachine(int $id): self
  + inDateRange(DateTimeRange $range): self    ← always include; forces partition pruning
  + withStatus(MachineStatus $status): self
  + running(): self                            ← shorthand: ->withStatus(Running)
  + faulted(): self
  + groupByHour(): self

USAGE:
MachineLog::query()
    ->forMachine($machineId)
    ->inDateRange($range)          ← REQUIRED: without this, full table scan
    ->faulted()
    ->get();
```

---

## 4. HTTP Layer — In Depth

### 4.1 API vs IoT vs Admin Separation

```
Route Prefix   Auth Mechanism          Controller Namespace       Purpose
─────────────  ──────────────────────  ─────────────────────────  ─────────────────────────
/api/v1/*      Laravel Sanctum (JWT)   Http\Controllers\Api\V1    SPA / mobile consumers
/api/iot/*     device_token header     Http\Controllers\Api\IoT   IoT firmware push only
/admin/*       Laravel session         Http\Controllers\Admin     Blade admin panel
```

**IoT endpoint is intentionally minimal:**
```
POST /api/iot/machine-data
  Header: X-Device-Token: {device_token}
  Body:   raw sensor payload (JSON)

MachineDataController:
  1. AuthenticateMachineDevice middleware resolves machine from token (Redis cached)
  2. MachineLogRequest validates payload shape
  3. MachineLogIngestionService::ingest(MachineLogData::fromRequest($request))
  4. Returns 202 Accepted immediately
  5. All real work is async in ProcessMachineLogJob
```

### 4.2 Controller Responsibility

Controllers are **thin**. They only:
1. Resolve the authenticated user/factory
2. Build a DTO from the request
3. Call one service or action
4. Return a Resource or 2xx response

```
NEVER in a controller:
  × DB queries
  × Business logic
  × Direct model access
  × Raw array returns (always use API Resources)
```

### 4.3 API Resources

Resources control the JSON shape independently of the model structure.
This is the only place column names are translated to API field names.

```
OeeDailyResource
  + machine_id
  + machine_name              ← from relationship; model column is `name`
  + date
  + oee_pct                  ← model column: oee_pct; renamed for clarity
  + availability_pct
  + performance_pct
  + quality_pct
  + source_values: [         ← nested; hides implementation details
      planned_time_min,
      downtime_min,
      actual_qty, ...
    ]
  + links: { machine, factory }
```

---

## 5. Jobs & Queue Architecture

### 5.1 Job Placement

Jobs inside a domain folder (`Domain/Machine/Jobs/`) belong to that domain's
processing pipeline. When split into a microservice, they move with the domain.

```
DOMAIN JOBS (co-located):
  Domain/Machine/Jobs/ProcessMachineLogJob.php       ← high frequency; domain-owned
  Domain/Machine/Jobs/DetectDowntimePatternsJob.php
  Domain/Downtime/Jobs/AutoCloseStaleDowntimeJob.php
  Domain/Analytics/Jobs/AggregateHourlyLogsJob.php
  Domain/Analytics/Jobs/AggregateDailyLogsJob.php
  Domain/Analytics/Jobs/CalculateOeeDailyJob.php
```

### 5.2 Queue Channels & Priority

```
Queue Name          Priority    Processed By           Jobs
──────────────────  ────────    ───────────────────    ──────────────────────────────────
machine-logs        high        dedicated worker       ProcessMachineLogJob
                                (4 workers)            → 50 logs/min × 100 machines = 5,000/min
downtime-detection  medium      shared worker (2)      DetectDowntimePatternsJob
                                                       AutoCloseStaleDowntimeJob
aggregation         low         single worker          AggregateHourlyLogsJob
                                                       AggregateDailyLogsJob
oee                 low         single worker          CalculateOeeDailyJob
notifications       medium      shared worker (1)      All notification jobs
broadcast           high        dedicated worker       All broadcast events
default             medium      shared worker (2)      Everything else

WORKER CONFIGURATION (Supervisor):
  [program:smartfactory-machine-logs]
  command=php artisan queue:work redis --queue=machine-logs --sleep=1 --tries=3
  numprocs=4                               ← 4 workers for high-throughput IoT queue

  [program:smartfactory-aggregation]
  command=php artisan queue:work redis --queue=aggregation,oee --sleep=3 --tries=2
  numprocs=1
```

### 5.3 Job Design for IoT Throughput

```
ProcessMachineLogJob
  implements ShouldQueue, ShouldBeUnique (per machine per 5s window)
  uses WithoutOverlapping                 ← prevents duplicate processing
  public $timeout = 30
  public $tries   = 3
  public $backoff = [2, 5, 10]           ← exponential: 2s, 5s, 10s

  handle():
    1. Validate DTO (already done in controller; re-validate for safety)
    2. MachineLogRepository::insertBatch()     ← DB::table() bulk insert, not Eloquent
    3. Detect status change vs previous log
    4. If status changed: dispatch MachineStatusChangedEvent
    5. If status = fault AND last_fault > threshold: dispatch MachineFaultDetectedEvent
```

---

## 6. Broadcasting & Real-Time

### 6.1 Channel Architecture

```
Laravel Reverb (self-hosted WebSocket) or Pusher

CHANNELS:
  factory.{factoryId}        → presence channel; all dashboard users in factory
  machine.{machineId}        → private channel; machine-specific subscribers
  downtime.{factoryId}       → private channel; downtime alert subscribers

AUTHORIZATION (routes/channels.php):
  Broadcast::channel('factory.{factoryId}', function (User $user, int $factoryId) {
      return $user->factory_id === $factoryId;
  });

  Broadcast::channel('machine.{machineId}', function (User $user, int $machineId) {
      $machine = Machine::find($machineId);
      return $machine && $user->factory_id === $machine->factory_id;
  });
```

### 6.2 Broadcast Event Flow

```
IoT Push
  │
  ▼
ProcessMachineLogJob
  │  fires domain event
  ▼
MachineStatusChangedEvent          ← domain event (not broadcastable)
  │
  ▼ (EventServiceProvider listener)
BroadcastMachineStatusListener
  │  creates broadcast event
  ▼
MachineStatusBroadcast             ← implements ShouldBroadcast
  │  on channel: machine.{id}
  ▼
Frontend Echo listener updates floor-map widget

SEPARATION REASON:
  Domain events are synchronous, PHP-only.
  Broadcast events are async (queued), WebSocket-aware.
  Keeping them separate means the domain has no broadcasting dependency.
  When splitting to microservices, domain stays clean.
```

### 6.3 Broadcast Events

```
Broadcasting/Events/Machine/MachineStatusBroadcast.php
  channel: machine.{machineId}
  payload: { machine_id, status, logged_at, production_count, power_kw }
  fired when: status changes (not every log — avoids 5,000 broadcasts/min)

Broadcasting/Events/Downtime/DowntimeAlertBroadcast.php
  channel: factory.{factoryId}
  payload: { downtime_id, machine_id, machine_name, category, started_at, elapsed_min }
  fired when: downtime opened OR closed

Broadcasting/Events/Production/ProductionCountUpdated.php
  channel: factory.{factoryId}
  payload: { plan_id, machine_id, actual_qty, planned_qty, attainment_pct }
  fired when: production_actuals updated

Broadcasting/Events/Dashboard/DashboardMetricsUpdated.php
  channel: factory.{factoryId}
  payload: { hourly aggregates snapshot for all machines }
  fired by: scheduled job every 60 seconds (not per-log)
```

---

## 7. Providers & Binding

### 7.1 RepositoryServiceProvider

```php
// Providers/RepositoryServiceProvider.php
// Registers ALL interface → implementation bindings.
// When splitting to microservices: copy only the relevant section.

Bindings registered:
  MachineRepositoryInterface        → EloquentMachineRepository
  MachineLogRepositoryInterface     → EloquentMachineLogRepository
  DowntimeRepositoryInterface       → EloquentDowntimeRepository
  ProductionPlanRepositoryInterface → EloquentProductionPlanRepository
  PartRepositoryInterface           → EloquentPartRepository
  AnalyticsRepositoryInterface      → EloquentAnalyticsRepository
  OeeRepositoryInterface            → EloquentOeeRepository
  FactoryRepositoryInterface        → EloquentFactoryRepository
```

### 7.2 EventServiceProvider

```
Event                              Listeners
─────────────────────────────────  ──────────────────────────────────────────
MachineLogReceivedEvent            DispatchMachineLogJobListener
MachineStatusChangedEvent          BroadcastMachineStatusListener (async)
MachineFaultDetectedEvent          DetectDowntimePatternsJob (dispatch)
DowntimeOpenedEvent                BroadcastDowntimeAlertListener (async)
                                   NotifyMaintenanceTeamListener (async)
DowntimeClosedEvent                BroadcastDowntimeAlertListener (async)
ProductionActualRecordedEvent      BroadcastProductionUpdateListener (async)
ProductionTargetReachedEvent       ProductionMilestoneNotification (async)
OeeCalculatedEvent                 TriggerOeeAlertListener (if below target)
LowOeeDetectedEvent                LowOeeAlertNotification (async)
```

### 7.3 DomainServiceProvider

```
Registers as SINGLETONS (state shared within request):
  MachineAuthService        ← caches token→machine_id for request lifetime
  OeeCacheService           ← wraps Redis; single instance per request

Registers as TRANSIENT (new instance each time):
  All Action classes        ← stateless; new instance is fine
  All Repository classes    ← handled by RepositoryServiceProvider
```

---

## 8. Routes Architecture

```
bootstrap/app.php
  →withRouting(
      web: 'routes/web.php',
      api: 'routes/api.php',          ← loads V1 + IoT
      commands: 'routes/console.php',
      channels: 'routes/channels.php',
      then: function() {
          Route::middleware(['web', 'auth', EnsureAdminRole::class])
               ->prefix('admin')
               ->group(base_path('routes/admin.php'));
      }
  )
```

### 8.1 routes/api.php

```
Loads two separate route groups:

Group 1: /api/v1 — User-facing REST API
  Middleware: [api, auth:sanctum, EnsureFactoryScope::class]
  Source: routes/api_v1.php

Group 2: /api/iot — IoT device push only
  Middleware: [api, throttle:iot, AuthenticateMachineDevice::class]
  Source: routes/api_iot.php
  Rate limit: 100 req/min per device (config/iot.php)

WHY SEPARATE FILES:
  When splitting Machine Data Service into its own microservice,
  routes/api_iot.php moves with it completely — zero refactoring.
```

### 8.2 routes/api_v1.php structure

```
/api/v1/
  auth/           login, logout, refresh, me
  factories/      CRUD (admin only)
  machines/       CRUD + status summary
  downtimes/      list, create, close, classify
  production/
    plans/        CRUD + status update
    actuals/      create, update
  analytics/
    oee/          daily, trend, factory-summary
    reports/      downtime, production, hourly
```

---

## 9. Config Files

### 9.1 config/iot.php
```
token_cache_ttl    => 300          seconds; device token cached in Redis
rate_limit         => 100          requests/minute per device
queue_channel      => machine-logs dedicated high-priority queue
log_channel        => iot          writes to storage/logs/iot/ingestion.log
payload_max_size   => 4096         bytes; reject oversized payloads early
```

### 9.2 config/oee.php
```
formula:
  availability => planned_time - unplanned_downtime / planned_time
  performance  => (ideal_cycle_time × actual_qty) / (operating_time × 60)
  quality      => good_qty / actual_qty
  oee          => availability × performance × quality

alert_threshold    => 70.00        trigger LowOeeDetectedEvent if oee_pct below
stale_threshold    => 2            hours; flag if OEE not recalculated
```

### 9.3 config/aggregation.php
```
hourly:
  lag_minutes   => 10              process logs older than 10 min (late arrival buffer)
  batch_size    => 1000            machines processed per job chunk
  chunk_size    => 10000           machine_logs rows per DB chunk

daily:
  run_at        => 00:30           UTC
  lag_hours     => 1               process yesterday + 1 hour overlap

oee:
  run_after_shift_end => true      triggers at each factory's shift end time
  fallback_run_at     => 01:00     UTC fallback if event-based trigger fails
```

---

## 10. Microservice Split Guide

### 10.1 Domain → Service Mapping

```
┌────────────────────────────────────────────────────────────────────┐
│ MONOLITH FOLDER                    FUTURE MICROSERVICE             │
├────────────────────────────────────┬───────────────────────────────┤
│ Domain/Machine/                    │ Machine Data Service           │
│ Domain/Downtime/                   │ (same service)                 │
│ Http/Controllers/Api/IoT/          │                                │
│ routes/api_iot.php                 │                                │
│ config/iot.php                     │                                │
│ Queue: machine-logs, downtime      │                                │
├────────────────────────────────────┼───────────────────────────────┤
│ Domain/Analytics/                  │ Analytics Service              │
│ Http/Controllers/Api/V1/Analytics/ │                                │
│ config/aggregation.php             │                                │
│ config/oee.php                     │                                │
│ Queue: aggregation, oee            │                                │
├────────────────────────────────────┼───────────────────────────────┤
│ Domain/Production/                 │ Production Service             │
│ Http/Controllers/Api/V1/Production │                                │
│ Http/Controllers/Api/V1/Downtime/  │                                │
├────────────────────────────────────┼───────────────────────────────┤
│ Http/Controllers/Admin/            │ Dashboard Service              │
│ Broadcasting/                      │ (reads from other services     │
│ Domain/Factory/                    │  via API or shared DB)         │
│ routes/admin.php                   │                                │
└────────────────────────────────────┴───────────────────────────────┘
```

### 10.2 What Changes When Splitting

```
STEP 1 — BEFORE split (monolith, shared DB):
  All domains call their own repositories directly.
  Cross-domain: MachineStatusChangedEvent → Analytics job reads machine_logs.
  No HTTP between domains.

STEP 2 — EXTRACTION POINT:
  Replace direct repository calls with HTTP client calls to the new service.
  Add DTOs that serialize to/from JSON (already done — DTOs have no Eloquent).
  Domain events become HTTP webhooks or message queue messages (Kafka/SQS).

STEP 3 — AFTER split:
  Analytics Service calls Machine Data Service's API to get machine_logs.
  OR: Analytics reads from replica DB of Machine Data Service.
  OR: Machine Data Service publishes to a shared event bus (Redis Streams).

KEY ENABLER:
  Because controllers depend on interfaces (not implementations),
  you swap EloquentMachineLogRepository for HttpMachineLogRepository
  and the service layer is untouched.
```

### 10.3 Interface Boundary Example

```
MachineLogRepositoryInterface (contract stays in shared package)
  ├── EloquentMachineLogRepository   ← used in monolith + Machine Data Service
  └── HttpMachineLogRepository       ← used by Analytics Service after split
        → calls Machine Data Service's /internal/machine-logs endpoint
        → maps HTTP response to same Collection<MachineLogData>
        → service layer code is IDENTICAL
```

---

## 11. Data Flow — IoT Ingestion to Dashboard

```
[Machine PLC]
     │ POST /api/iot/machine-data
     │ Header: X-Device-Token: abc123
     ▼
[AuthenticateMachineDevice Middleware]
     │ Redis::get("device_token:abc123") → machine_id=7
     │ If miss: DB lookup → Redis::set(TTL=300s)
     ▼
[MachineLogRequest]           validates payload structure
     ▼
[MachineDataController]       builds MachineLogData DTO
     ▼
[MachineLogIngestionService]  validates business rules
     ▼
[ProcessMachineLogJob]        dispatched to queue: machine-logs
     │ Returns 202 Accepted ← response sent HERE (async from this point)
     ▼
[Job Worker Process]
     ├── MachineLogRepository::insertBatch()     → machine_logs (partitioned table)
     ├── Compares status with previous log
     └── If status changed:
             fire MachineStatusChangedEvent
                  → BroadcastMachineStatusListener
                       → MachineStatusBroadcast (WebSocket)
                            → Frontend floor map updates
         If fault pattern:
             fire MachineFaultDetectedEvent
                  → DetectDowntimePatternsJob
                       → AutoDetectDowntimeAction
                            → creates downtime record
                                 → fire DowntimeOpenedEvent
                                      → DowntimeAlertBroadcast
                                      → DowntimeAlertNotification (mail/slack)

─── HOURLY (runs at :10 past each hour) ─────────────────────────────
[AggregateHourlyLogsJob]
     │ reads machine_logs WHERE logged_at BETWEEN last_hour
     │ (partition pruned — only 1 partition scanned)
     ▼
     writes machine_logs_hourly (UPSERT)

─── DAILY (runs at 00:30) ────────────────────────────────────────────
[AggregateDailyLogsJob]
     │ reads machine_logs_hourly WHERE hour_start = yesterday
     ▼
     writes machine_logs_daily (UPSERT)

─── OEE (runs at shift end) ──────────────────────────────────────────
[CalculateOeeDailyJob]
     │ reads machine_logs_daily (runtime/idle/fault minutes)
     │ reads downtimes (unplanned duration sum)
     │ reads production_plans (planned_qty)
     │ reads production_actuals (actual_qty, defect_qty)
     ▼
     OeeCalculationService::calculate(OeeData)
     ▼
     writes machine_oee_daily (UPSERT)
     ▼
     fire OeeCalculatedEvent
          → if oee_pct < factory target:
               fire LowOeeDetectedEvent
                    → LowOeeAlertNotification

─── DASHBOARD READS ──────────────────────────────────────────────────
  Real-time panel    → machine_logs_hourly (current hour)
  OEE gauges         → machine_oee_daily (Redis cached, TTL 5min)
  Production table   → production_plans JOIN production_actuals
  Downtime panel     → v_open_downtimes view
  Trend charts       → machine_logs_daily (7/30/90 day window)
```

---

## 12. Queue Channel Design

```
┌─────────────────────────────────────────────────────────────────────┐
│                    QUEUE TOPOLOGY                                   │
├──────────────────┬───────────┬────────────────────────────────────  │
│ Queue            │ Workers   │ Reason                              │
├──────────────────┼───────────┼─────────────────────────────────────┤
│ machine-logs     │ 4         │ 5,000 logs/min; must not back up    │
│ broadcast        │ 2         │ WebSocket delivery is time-critical │
│ downtime         │ 2         │ Downtime detection is near-realtime │
│ aggregation      │ 1         │ Batch; runs on schedule             │
│ oee              │ 1         │ Low frequency; once per shift       │
│ notifications    │ 1         │ Mail/Slack; latency-tolerant        │
│ default          │ 2         │ Catch-all for misc jobs             │
└──────────────────┴───────────┴─────────────────────────────────────┘

MONITORING:
  Laravel Horizon (if using Redis) provides:
    - Queue depth per channel
    - Job throughput / failure rate
    - Worker utilization
  Alert threshold: machine-logs queue depth > 5,000 → scale worker
```

---

## 13. Testing Strategy

```
tests/
├── Feature/                ← Tests full HTTP stack (controller→service→DB)
│   ├── IoT/
│   │   └── MachineDataIngestionTest.php
│   │       TESTS:
│   │         ✓ Valid token → 202 Accepted + job dispatched
│   │         ✓ Invalid token → 401 Unauthorized
│   │         ✓ Malformed payload → 422 Unprocessable
│   │         ✓ Rate limit exceeded → 429 Too Many Requests
│   │         ✓ 1,000 concurrent inserts maintain data integrity
│   │
│   ├── Api/Analytics/
│   │   └── OeeReportTest.php
│   │       TESTS:
│   │         ✓ Returns oee_daily scoped to user's factory
│   │         ✓ Cannot access other factory's OEE
│   │         ✓ Date range filters correctly
│   │         ✓ Empty date range returns empty collection, not error
│   │
│   └── Api/Production/
│       └── ProductionPlanControllerTest.php
│           TESTS:
│             ✓ Cannot double-book machine in same shift
│             ✓ Status transitions follow allowed flow
│             ✓ Recording actual > planned_qty accepted (no constraint)
│             ✓ defect_qty > actual_qty rejected (constraint)
│
└── Unit/                   ← Tests pure logic; no DB; no HTTP; very fast
    ├── Domain/Analytics/
    │   └── OeeCalculationTest.php
    │       TESTS:
    │         ✓ Correct formula: A × P × Q
    │         ✓ Zero planned_qty → quality = 0 (not division by zero)
    │         ✓ Performance > 100% when machine runs faster than ideal
    │         ✓ OEE capped at 100.00 in OeePct ValueObject
    │
    └── ValueObjects/
        └── OeePctTest.php
            TESTS:
              ✓ Rejects value > 100
              ✓ Rejects negative value
              ✓ Accepts 0.00 and 100.00 boundary values
```

---

## 14. Key Design Decisions

### Decision 1: Domain Jobs vs App/Jobs/

Jobs are co-located inside their domain (`Domain/Machine/Jobs/`)
rather than the Laravel-default `app/Jobs/`.

**Why:** When Machine Data Service is extracted, the entire
`Domain/Machine/` folder moves with zero refactoring. No hunting
through `app/Jobs/` to find which jobs belong to which service.

---

### Decision 2: No Eloquent in machine_logs bulk write

`EloquentMachineLogRepository::insertBatch()` uses `DB::table()->insert()`
not `MachineLog::create()`. Eloquent overhead (events, observers, casting)
adds ~30% latency per insert. At 5,000 inserts/min this matters.
Eloquent is used for reads only on this table.

---

### Decision 3: Device token in Redis, not session

IoT devices are not users. Sanctum sessions are wasteful for firmware.
`device_token` is stored in `machines.device_token` (SHA-256 hash).
The `AuthenticateMachineDevice` middleware:
1. Reads `X-Device-Token` header
2. Checks `Redis::get("device:{token}")` for `machine_id`
3. Cache miss: DB lookup + `Redis::set(TTL=300s)`
4. Cache hit: resolves in ~1ms, no DB touch

---

### Decision 4: DTOs are readonly classes

PHP 8.2 `readonly class` enforces immutability at language level.
Once a `MachineLogData` is constructed, its fields cannot change.
This prevents subtle bugs where a service modifies a DTO that's
also used by another listener in the same request.

---

### Decision 5: machine_logs partitioning not in Laravel migration

Laravel migrations cannot express `PARTITION BY RANGE`. The migration
file for `machine_logs` runs the raw DDL from `smartfactory_ddl.sql`
via `DB::unprepared()`. This keeps the partition logic in the SQL file
(DBA-owned) and the migration just executes it.

---

### Decision 6: QueryBuilders always require a date range

Every `MachineLogQueryBuilder` method that reads data
**requires** a `DateTimeRange` parameter. Without it,
MySQL cannot prune to a single partition and would scan all
216M rows/month. This constraint is enforced by the interface.

---

### Decision 7: Broadcast events are separate classes from domain events

`MachineFaultDetectedEvent` (domain) has no broadcasting dependency.
`DowntimeAlertBroadcast` (broadcast) has no domain logic.
A listener bridges the two. This means:
- Domain can be tested without Laravel broadcasting setup
- Broadcasting implementation can be swapped (Reverb → Pusher) with no domain changes
- When splitting services, domain moves without the broadcast layer

---

*Document version 1.0 | Matches smartfactory_ddl.sql v1.0*
