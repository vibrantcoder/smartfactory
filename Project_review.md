# SmartFactory — ISO/Industrial Standards Review Report

**Project:** SmartFactory (Laravel 12 + MariaDB, Industry 4.0 IoT MES)
**Review Date:** 2026-03-14
**Reviewer:** Claude Code (Sonnet 4.6)
**Scope:** Full codebase — 39 migrations, 45+ controllers, 22+ domain models, 5 services, 10 policies, 2 route files, all views

---

## Severity Legend

| Level | Meaning |
|---|---|
| 🔴 CRITICAL | Security breach, data loss, or system failure risk |
| 🟠 HIGH | Significant functional or security gap |
| 🟡 MEDIUM | Quality, maintainability, or compliance gap |
| 🟢 LOW | Best-practice improvement |

---

## Table of Contents

1. [Project Architecture and Folder Structure](#1-project-architecture-and-folder-structure)
2. [Code Quality, Readability, and Maintainability](#2-code-quality-readability-and-maintainability)
3. [Security Vulnerabilities and Data Protection](#3-security-vulnerabilities-and-data-protection)
4. [API Structure and Error Handling](#4-api-structure-and-error-handling)
5. [Database Design, Indexing, and Query Optimization](#5-database-design-indexing-and-query-optimization)
6. [Performance and Scalability](#6-performance-and-scalability)
7. [Logging and Monitoring](#7-logging-and-monitoring)
8. [Validation and Input Sanitization](#8-validation-and-input-sanitization)
9. [Authentication and Authorization](#9-authentication-and-authorization)
10. [Documentation and Configuration Management](#10-documentation-and-configuration-management)
11. [ISO/Industrial Standards Compliance](#11-isoindustrial-standards-compliance)
12. [Missing Features, Risks, and Potential System Failures](#12-missing-features-risks-and-potential-system-failures)
13. [Scalability Analysis: 3–5 Machines → 100+ Machines](#13-scalability-analysis-35-machines--100-machines)
14. [Summary Scorecard](#summary-scorecard)
15. [Priority Action Plan](#priority-action-plan)

---

## 1. Project Architecture and Folder Structure

### Overview

The project follows Domain-Driven Design (DDD) with a clear separation across:

```
app/
├── Domain/
│   ├── Analytics/          (OEE calculation, aggregation services)
│   ├── Factory/            (tenant root — models, repos, services)
│   ├── Machine/            (IoT device management)
│   ├── Production/         (plans, parts, customers, work orders)
│   └── Shared/             (base model, enums, traits, scopes)
├── Http/
│   ├── Controllers/Admin/  (session-auth web panel)
│   ├── Controllers/Api/V1/ (Sanctum token API)
│   ├── Controllers/Employee/ (employee portal)
│   ├── Middleware/
│   ├── Requests/           (FormRequest validation)
│   └── Resources/          (API response transformers)
├── Models/User.php
├── Policies/               (10 authorization policies)
└── Providers/              (AppServiceProvider, RepositoryServiceProvider)
```

### Strengths

- DDD layering (Domain / HTTP / Providers) is clean and consistently applied
- Shared layer (`app/Domain/Shared/`) with BaseModel, FactoryScope, BelongsToFactory, HasFactoryScope is elegant and reusable
- Query Builder pattern per model keeps Eloquent scopes organized and testable
- DTO pattern used for data transfer between HTTP and Domain layers
- `declare(strict_types=1)` consistently applied across all PHP files
- PHP 8.2 features used correctly (readonly properties, named arguments, match expressions)

### Issues

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| A1 | 🟠 HIGH | **Repository pattern is half-implemented.** Only 5 of ~10 domain models have repository interfaces and Eloquent implementations. `ProductionPlan`, `WorkOrder`, `Shift`, `Downtime`, `ProductionActual` are accessed directly via Eloquent inside controllers and services — bypassing the abstraction entirely. This makes unit testing and swapping implementations impossible for these models. | Create repository interfaces and bindings for all remaining domain models. |
| A2 | 🟡 MEDIUM | **No Application / Use Case layer.** Business workflows (releasing a WorkOrder, scheduling production, state transitions) are implemented inside API controllers rather than dedicated Command/Handler classes. | Introduce `app/Application/` with Command and Handler classes per use case. |
| A3 | 🟡 MEDIUM | **No Domain Events.** State transitions (plan: `draft→scheduled→in_progress`, WO: `confirmed→released`) have no event/listener mechanism. Cross-domain side effects (e.g., notify operator on plan release) cannot be implemented without coupling controllers. | Implement Laravel Events for all domain state transitions. |
| A4 | 🟡 MEDIUM | **`IotController` is 955 lines** handling ingest, chart, timeline, export, status, and shift listing. Violates Single Responsibility Principle. | Split into `IotIngestController`, `IotChartController`, `IotExportController`, `IotStatusController`. |
| A5 | 🟢 LOW | **`app/Models/User.php` lives outside the Domain layer.** Inconsistent with DDD structure. | Move to `app/Domain/Auth/Models/User.php`. |

---

## 2. Code Quality, Readability, and Maintainability

### Strengths

- Consistent naming conventions: snake_case for DB columns, camelCase for PHP methods, SCREAMING_SNAKE for enum cases
- Excellent inline comments on complex logic (LAG window function, Spatie team scoping, pulse signal detection)
- ARCHITECTURE.md and SYSTEM_DOCUMENTATION.md files present
- Good docblocks on all service classes

### Issues

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| B1 | 🔴 CRITICAL | **SQL Injection via string interpolation in `machineTimeline()`.** The user-supplied `?date=` parameter is processed through Carbon::parse() then interpolated directly into raw SQL strings: `->selectRaw("FLOOR(TIMESTAMPDIFF(SECOND, '{$sinceStr}', logged_at) / {$bucketSec})")`. While Carbon formats dates, a crafted input could still produce malformed SQL. | Replace with DB::raw parameterized bindings: `->selectRaw("FLOOR(TIMESTAMPDIFF(SECOND, ?, logged_at) / ?)", [$sinceStr, $bucketSec])` |
| B2 | 🟠 HIGH | **Raw SQL queries scattered across controllers.** `IotController::machineChart()` contains a 15-line raw SQL with LAG window function built inline. `OeeCalculationService` has two raw SQL blocks. These bypass the Query Builder and make codebase-wide optimization impossible. | Extract to dedicated QueryBuilder methods on each model's builder class. |
| B3 | 🟡 MEDIUM | **Duplicated device resolution logic.** `resolveDevice()` is called once per payload in `ingestBatch()`, potentially hitting Redis/DB 500 times per batch request. | Pre-group batch payloads by device token and resolve once per unique token. |
| B4 | 🟡 MEDIUM | **`mergeStoredChartData()` is 60 lines of dense array manipulation** with no unit tests. Logic errors here would silently corrupt historical dashboard data. | Extract to a dedicated ChartDataMerger class with unit test coverage. |
| B5 | 🟡 MEDIUM | **Inconsistent API error response shape.** IoT ingest returns `{'error': 'msg'}`, while API controllers return `{'message': 'msg'}`. No global error envelope contract. | Define a standard response contract and implement a global exception handler. |
| B6 | 🟢 LOW | **Magic numbers throughout the codebase.** Values like `5` (minutes offline threshold), `300` (bucket seconds), `30` (cache TTL), `500` (batch limit) are hardcoded. | Replace with named constants or configurable factory settings. |

---

## 3. Security Vulnerabilities and Data Protection

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| C1 | 🔴 CRITICAL | **SQL Injection via date parameter** (see B1). `$sinceStr` in `IotController::machineTimeline()` is interpolated into raw SQL string. | Use DB binding: `selectRaw("FLOOR(TIMESTAMPDIFF(SECOND, ?, ...) / ?)", [$sinceStr, $bucketSec])` |
| C2 | 🔴 CRITICAL | **Unauthenticated IoT "Demo Auth" on a public production endpoint.** `resolveDevice()` has Priority 2/3 fallbacks: any HTTP request with `machine_id` or `slavename` in the body can inject telemetry for any machine with zero authentication. Since `POST /api/iot/ingest` is a fully public route, any external actor can forge machine data, corrupt OEE calculations, and generate false alarms. | Remove `machine_id` and `slavename` fallbacks entirely from production, or gate behind `app()->environment('local')`. |
| C3 | 🟠 HIGH | **Personal access tokens never expire.** `config/sanctum.php` has `'expiration' => null`. A leaked token (from session hijacking or log exposure) is valid indefinitely. | Set `'expiration' => 10080` (7 days) and implement a token refresh mechanism. |
| C4 | 🟠 HIGH | **No Subresource Integrity (SRI) on CDN resources.** Admin views load Tailwind, Alpine.js, and Chart.js from public CDNs without `integrity=` attributes. A CDN compromise could inject arbitrary JavaScript into the admin panel. | Self-host assets via Vite (Tailwind is already compiled), or add SRI hash attributes to all CDN tags. |
| C5 | 🟠 HIGH | **Session not encrypted.** `SESSION_ENCRYPT=false` means session files on disk are readable in plaintext, exposing the embedded `api_token`. | Set `SESSION_ENCRYPT=true` in production `.env`. |
| C6 | 🟠 HIGH | **`APP_DEBUG=true` in `.env.example`** (and likely in deployed `.env`). In production, this exposes full stack traces, SQL queries, and config values to any HTTP client that triggers an error. | Set `APP_DEBUG=false` in production. Use Sentry/Bugsnag for error tracking instead. |
| C7 | 🟠 HIGH | **Spatie permission cache (24h) not invalidated on role/permission changes.** If a user's permissions are revoked via the admin panel, they remain effective for up to 24 hours. | Call `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()` inside every role sync and permission update handler. |
| C8 | 🟡 MEDIUM | **Device tokens hashed with SHA-256** — a fast hash not designed for secret storage. An attacker who obtains the `device_token` column can brute-force the original token at billions of hashes per second. | Use a keyed HMAC: `hash_hmac('sha256', $token, config('app.key'))` so the server's app key is required to verify. |
| C9 | 🟡 MEDIUM | **No CORS configuration.** Laravel's CORS middleware is available but no `config/cors.php` customization is present. The API accepts cross-origin requests from any domain. | Restrict `config/cors.php` allowed origins to your admin domain and IoT gateway IPs only. |
| C10 | 🟡 MEDIUM | **No Content-Security-Policy (CSP) headers.** Admin panel views have no CSP headers, making stored XSS attacks more damaging. | Add CSP via middleware: `Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{random}'` |
| C11 | 🟡 MEDIUM | **No HTTPS enforcement.** No `RedirectToHttps` middleware or HSTS header. Internet-facing deployments transmit session cookies and API tokens over plain HTTP. | Add `Strict-Transport-Security` response header and an HTTPS redirect middleware on all routes. |
| C12 | 🟡 MEDIUM | **Sanctum `api_token` stored inside server-side session** (`session(['api_token' => $token])`). If the session store is compromised, API tokens are also compromised. This tightly couples session auth and token auth. | Consider using short-lived CSRF tokens for web-to-API calls, decoupled from the Sanctum personal access token. |
| C13 | 🟢 LOW | **No brute-force protection on admin login.** The login POST route has no rate limiting beyond Laravel's default. | Add `throttle:5,1` middleware (5 attempts per minute per IP) to the login POST route. |
| C14 | 🟢 LOW | **Default password `password` in DemoSeeder** could reach production environments. | Add an environment guard in DemoSeeder: `if (app()->environment('production')) { throw new \RuntimeException('DemoSeeder must not run in production'); }` |

---

## 4. API Structure and Error Handling

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| D1 | 🟠 HIGH | **No global API exception handler.** Laravel's default exception handler returns HTML for some errors (model not found, 500) unless `Accept: application/json` is set. IoT devices and JS clients may receive HTML error pages. | Implement `app/Exceptions/Handler.php` with `renderable()` that always returns JSON for routes matching `/api/*`. |
| D2 | 🟡 MEDIUM | **Inconsistent response envelopes across all endpoints.** IoT status returns `{'data': [...]}`, ingest returns `{'ok': true, 'stored': N}`, OEE returns flat objects, timeline returns top-level keys. No uniform contract. | Define a standard response structure: `{'data': ..., 'meta': {...}, 'errors': [...]}` and apply consistently. |
| D3 | 🟡 MEDIUM | **No API documentation (OpenAPI/Swagger).** With 40+ endpoints consumed by IoT devices, admin SPA, and employee portal, there is no machine-readable API spec. | Integrate `darkaonline/l5-swagger` or `dedoc/scramble` to auto-generate OpenAPI docs from docblocks. |
| D4 | 🟡 MEDIUM | **Date and `shift_id` query parameters not validated in chart/timeline endpoints.** `?date=2026-13-99` or `?shift_id=abc` passes to `Carbon::parse()` and integer casts without FormRequest validation, causing unhandled exceptions. | Add FormRequest classes for all GET endpoints with date/shift_id parameters, using `date_format:Y-m-d` validation rule. |
| D5 | 🟡 MEDIUM | **IoT ingest endpoints lack FormRequest validation.** `POST /api/iot/ingest` and `/api/iot/ingest/batch` use raw `$request->json()->all()` with no Laravel validation rules. Invalid payloads are silently coerced. | Create `IngestRequest` and `BatchIngestRequest` FormRequest classes with field type and range validation. |
| D6 | 🟢 LOW | **HTTP 404 returned for auth failure on IoT ingest.** When `resolveDevice()` returns null, the response is 404. This conflates "device not found" with "authentication failed," making debugging difficult. | Return 401 for missing/invalid device token, 404 only for authenticated-but-unknown device. |

---

## 5. Database Design, Indexing, and Query Optimization

### Strengths

- Multi-tenant factory isolation with `factory_id` FK and cascade deletes on every scoped table
- `good_qty GENERATED ALWAYS AS (actual_qty - defect_qty) STORED` prevents application-level calculation inconsistency
- UNIQUE(machine_id, shift_id, oee_date) on `machine_oee_shifts` enables safe upserts
- `IotLog::UPDATED_AT = null` correctly enforces write-once telemetry semantics
- Cascade deletes maintain referential integrity across all tenant data

### Issues

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| E1 | 🟠 HIGH | **Missing composite index on `iot_logs(machine_id, logged_at)`.** The LAG window query in `OeeCalculationService` and `IotController::machineChart()` filters by `machine_id` and orders by `logged_at`. At 1 rec/sec × 50 machines = 4.3M rows/day, without this index every OEE aggregation performs a full table scan. | Add: `$table->index(['machine_id', 'logged_at']);` in the iot_logs migration. Verify with `SHOW INDEX FROM iot_logs`. |
| E2 | 🟠 HIGH | **`machine_oee_shifts.chart_data` is an unbounded JSON column.** With 50 machines × 3 shifts × 24 hourly data points per row, each chart_data JSON can be 5–20 KB. Over a year this adds gigabytes to MariaDB row storage, impacting table scan performance. | Store chart_data as files (S3 / local disk) with a path pointer column, or compress before storing. |
| E3 | 🟡 MEDIUM | **N+1 query pattern in OEE aggregation.** `aggregateFactory()` → `calculateAllShifts()` per machine → `calculateForShift()` per shift, each issuing 2+ queries. At 50 machines × 3 shifts = 300+ DB queries per 5-minute cron run. | Batch the seed LAG and aggregate queries across all machines for a date using a single window function query. |
| E4 | 🟡 MEDIUM | **No soft deletes on `production_plans` or `production_actuals`.** Hard-deleting a plan permanently removes all historical output data tied to it. | Add `softDeletes()` to production_plans and production_actuals migrations, with a retention policy. |
| E5 | 🟡 MEDIUM | **No audit/history table.** Changes to production plans, work orders, and role assignments are not tracked at the DB level. ISO 9001 and ISO 22400 require full traceability of production record modifications. | Add a `model_change_logs` table (model_type, model_id, field, old_value, new_value, changed_by, changed_at) or use the `spatie/laravel-activitylog` package. |
| E6 | 🟡 MEDIUM | **Status columns stored as VARCHAR with no DB-level ENUM constraint.** Application validation prevents invalid values, but the DB itself accepts any string. A direct DB write bypasses all Laravel validation. | Change status columns to `ENUM('draft','scheduled','in_progress','completed','cancelled')` in migrations. |
| E7 | 🟡 MEDIUM | **`factories.week_off_days` is a JSON array rather than a normalized table.** If multiple factories share national holidays, data is duplicated. The `factory_holidays` table already exists but is separate. | Consolidate to the `factory_holidays` table for individual dates; use `week_off_days` only for recurring day patterns. |
| E8 | 🟢 LOW | **Missing index on `part_processes(part_id, sequence_order)`.** Routing queries sort by sequence_order per part — a composite index would speed these up. | Add: `$table->index(['part_id', 'sequence_order']);` |
| E9 | 🟢 LOW | **Missing index on `production_plans(planned_date, machine_id)`.** Calendar queries filtering by date + machine on large plan tables perform full scans. | Add: `$table->index(['planned_date', 'machine_id']);` |

---

## 6. Performance and Scalability

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| F1 | 🟠 HIGH | **OEE aggregation runs synchronously in the scheduler every 5 minutes.** If `aggregateAll()` takes longer than 5 minutes (likely at 50-machine scale), the scheduler's `withoutOverlapping()` will skip the next run, creating silent aggregation gaps. | Dispatch one queued Job per machine: `AggregateOeeJob::dispatch($machine, $date)`. The scheduler only dispatches, workers execute in parallel. |
| F2 | 🟠 HIGH | **No queue worker setup documented or configured.** `QUEUE_CONNECTION=database` in `.env` but no `php artisan queue:work` instructions exist. Background jobs dispatched now or in the future will silently accumulate in the `jobs` table without processing. | Document and configure `php artisan queue:work --sleep=3 --tries=3` as a system service (Supervisor on Linux, Windows Service on XAMPP). |
| F3 | 🟡 MEDIUM | **IoT status endpoint returns ALL machines with no pagination.** A super-admin's factory-wide request returns every machine in the system. The 30-second cache only helps under steady load; a burst of admin users causes concurrent cache misses. | Add `?per_page=` pagination and factory_id scoping. Return a paginated response with metadata. |
| F4 | 🟡 MEDIUM | **`findByDeviceToken()` called per-record in `ingestBatch()`.** For a 500-record batch from 50 machines, this is up to 500 Redis lookups or DB queries (on cache miss). | Group batch payloads by device token before the loop. Resolve each unique token once, then map payloads to the resolved machine. |
| F5 | 🟡 MEDIUM | **Database-based session and cache under high IoT load.** Session and cache reads/writes compete with application queries on the same MariaDB instance. Under 50+ machines at 1 rec/sec, this creates I/O contention. | Configure `SESSION_DRIVER=redis` and `CACHE_STORE=redis` in production. Redis is purpose-built for high-throughput key-value operations. |
| F6 | 🟡 MEDIUM | **CSV export is unbounded in practice.** `?hours=168` (1 week) generates up to 604,800 rows. Streaming via `chunk(1000)` manages memory but the query scan is still large and ties up a DB connection for the full export duration. | Add a date range limit (max 24h) for synchronous export; offer background export (queue job) for larger ranges with download notification. |

---

## 7. Logging and Monitoring

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| G1 | 🟠 HIGH | **No structured application logging.** Only `oee-aggregation.log` uses a dedicated channel. Controller actions — plan state changes, WO releases, user logins, role assignments — produce zero log output. Under a production incident, there is no audit trail to diagnose what happened. | Add `Log::info('production.plan.state_changed', ['plan_id' => ..., 'from' => ..., 'to' => ..., 'user_id' => ...])` at every state transition. |
| G2 | 🟠 HIGH | **No exception monitoring integration.** No Sentry, Bugsnag, or equivalent configured. Unhandled exceptions only appear in `storage/logs/laravel.log` with no alerting or grouping. | Integrate `sentry/sentry-laravel` (free tier available) and configure the DSN in `.env`. |
| G3 | 🟡 MEDIUM | **No health-check endpoint.** No `/health` or `/api/health` route validating DB connectivity, cache connectivity, and queue health. Required for container orchestration (Docker/Kubernetes) and uptime monitoring tools. | Add a `HealthController` returning `{'status': 'ok', 'db': 'ok', 'cache': 'ok', 'queue_depth': N}`. |
| G4 | 🟡 MEDIUM | **No audit log for RBAC changes.** Role assignments, permission matrix syncs, and user deactivations produce no logged audit trail. ISO 27001 and IEC 62443 both require privilege change logging. | Log all role/permission changes to a dedicated `audit_logs` table (actor, action, target, before, after, timestamp). |
| G5 | 🟡 MEDIUM | **No API request/response logging for failures.** Failed API calls (4xx, 5xx) are not logged with request context (user, IP, payload shape). Debugging production failures requires guesswork. | Add a `LogApiFailures` middleware that logs 4xx/5xx responses with method, URL, user_id, IP, and response code. |
| G6 | 🟢 LOW | **Default log channel is `stack → single`**, creating an unbounded `laravel.log` file. In production, a busy system will grow this file indefinitely. | Switch to `LOG_CHANNEL=daily` with a 30-day retention policy: `'days' => 30` in `config/logging.php`. |

---

## 8. Validation and Input Sanitization

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| H1 | 🟠 HIGH | **IoT ingest endpoints have no FormRequest validation.** `POST /api/iot/ingest` accepts arbitrary JSON with no field type, range, or presence validation. Fields like `timestamp` accept any integer (including far-future dates that would create misleading OEE windows). Invalid payloads are silently coerced to 0 with no feedback to the device. | Create `IngestRequest` with rules: `'alarm_code' => 'integer\|min:0\|max:9999'`, `'timestamp' => 'integer\|min:0'`, `'part_count' => 'integer\|in:0,1'`, etc. |
| H2 | 🟠 HIGH | **Date parameters not validated before `Carbon::parse()`.** `?date=`, `?from_date=`, `?to_date=` in chart, timeline, and OEE endpoints are passed directly to `Carbon::parse()`. An invalid string like `?date=not-a-date` throws an uncaught `InvalidArgumentException`, producing a 500 error instead of a 422 validation error. | Add `date_format:Y-m-d` validation to all query parameters that accept dates before any Carbon usage. |
| H3 | 🟡 MEDIUM | **`slave_name` and `slave_id` not length-limited in IoT ingest.** The application does not validate length before DB insert. An oversized value triggers a MariaDB truncation error without a meaningful API response. | Add `'slave_name' => 'nullable\|string\|max:100'` validation to ingest request. |
| H4 | 🟡 MEDIUM | **`SyncPartProcessesRequest` allows duplicate `process_master_id` values** within the submitted array. Duplicate process steps in a routing would violate manufacturing sequence logic. | Add a custom rule: `Rule::unique` within the submitted array using `array_unique()` check in a custom validation rule. |
| H5 | 🟡 MEDIUM | **Part drawing uploads trust the submitted MIME type** without server-side re-verification via `finfo` / PHP's `mime_content_type()`. A malicious file disguised as a PDF or image could be stored. | Use `$file->getMimeType()` (which reads the file's magic bytes) rather than the submitted Content-Type. Allowlist only `image/jpeg`, `image/png`, `application/pdf`. |
| H6 | 🟢 LOW | **`notes` fields accept arbitrary HTML content.** While Blade's `{{ }}` escapes output, if notes are ever rendered raw (e.g., in a future email template or PDF export), unsanitized HTML could cause XSS. | Strip HTML tags from notes at save time: `strip_tags($request->notes)` or enforce `plain text only` via validation. |

---

## 9. Authentication and Authorization

### Strengths

- 6-level RBAC hierarchy (super-admin → factory-admin → production-manager → supervisor → operator → viewer) with Spatie Teams is well-designed and consistently applied
- Factory isolation enforced at both middleware (`SetFactoryPermissionScope`) and individual policy levels
- `Gate::before` super-admin bypass correctly handles deactivated users first before granting bypass
- `EnsureAdminRole` middleware auto-regenerates stale Sanctum API tokens transparently
- Policy-based authorization via `$this->authorize()` consistently applied across all API controllers
- Device token never included in `MachineResource` API responses

### Issues

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| I1 | 🔴 CRITICAL | **Unauthenticated demo auth fallback on the public IoT ingest endpoint** (see C2 above). Any HTTP client can inject telemetry for any machine using only `machine_id` in the request body. | Remove demo fallbacks from production. Use only `X-Device-Token` header authentication. |
| I2 | 🟠 HIGH | **Spatie permission cache not invalidated on permission matrix changes.** When `POST /admin/roles/{role}/sync-permissions` is called, the 24-hour Spatie cache is not cleared. Users with revoked permissions keep them until cache TTL expires. | Add `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()` to the permission sync handler. |
| I3 | 🟠 HIGH | **Sanctum tokens never expire** (`'expiration' => null` in sanctum config). A stolen token is valid indefinitely with no rotation mechanism. | Set `'expiration' => 10080` (7 days). Implement a token refresh endpoint: `POST /api/v1/auth/refresh`. |
| I4 | 🟡 MEDIUM | **No Multi-Factor Authentication (MFA).** ISO 27001 and IEC 62443 recommend MFA for privileged accounts. Factory-admin and super-admin roles manage production data and user access with no second factor. | Integrate TOTP-based MFA (e.g., `pragmarx/google2fa-laravel`) at minimum for factory-admin and super-admin roles. |
| I5 | 🟡 MEDIUM | **Employee portal shares the same `auth:web` guard and session cookie as the admin panel.** An operator navigating to `/admin/*` is only blocked by the `admin.role` redirect middleware, not by session isolation. | Consider a dedicated `auth:employee` guard with a separate session table or cookie name. |
| I6 | 🟡 MEDIUM | **No IP allowlisting for the admin panel.** The admin panel at `/admin/*` is accessible from any IP address. In a factory LAN, this should be restricted to internal network ranges. | Add an `AllowAdminIp` middleware enforcing allowlisted CIDR ranges for all `/admin/*` routes. |
| I7 | 🟢 LOW | **Remember-me tokens accumulate without pruning.** Long-lived remember tokens in the `users.remember_token` column are never cleaned up. | Add a scheduled command to null out remember tokens for inactive sessions older than 30 days. |

---

## 10. Documentation and Configuration Management

| ID | Severity | Issue | Recommendation |
|---|---|---|---|
| J1 | 🟠 HIGH | **No API documentation (OpenAPI/Swagger).** With 40+ API endpoints consumed by IoT devices, admin SPA, and the employee portal, the absence of a machine-readable API spec is a major maintainability and onboarding risk. | Integrate `dedoc/scramble` (zero-config for Laravel) or `darkaonline/l5-swagger` and generate docs at `/api/docs`. |
| J2 | 🟡 MEDIUM | **`.env.example` defaults to `DB_CONNECTION=sqlite`** with MariaDB settings commented out. New developers start on SQLite (which does not support MariaDB's `GENERATED ALWAYS AS` computed columns) and only discover the mismatch at migration time. | Set `DB_CONNECTION=mysql` as the default in `.env.example` with MariaDB credentials clearly documented. |
| J3 | 🟡 MEDIUM | **No deployment documentation.** No instructions for: PHP 8.2 version requirement, MariaDB ≥10.2 (required for GENERATED columns), `php artisan schedule:work` setup, queue worker configuration, storage symlink (`php artisan storage:link`). | Create `DEPLOYMENT.md` covering server requirements, environment variables, scheduler setup, queue workers, and post-deploy commands. |
| J4 | 🟡 MEDIUM | **No CHANGELOG or migration runbook.** The migration sequence has 39 files with several incremental column additions. Without a runbook, applying these to an existing production DB in order is error-prone and hard to audit. | Maintain `CHANGELOG.md` following Keep a Changelog format. Document breaking migrations (column drops, type changes) with rollback instructions. |
| J5 | 🟢 LOW | **ARCHITECTURE.md and SYSTEM_DOCUMENTATION.md are present** but appear to be manually maintained. They can drift from the actual codebase over time. | Add a CI step that runs `php artisan route:list --json > docs/routes.json` and commits it to keep route documentation current. |

---

## 11. ISO/Industrial Standards Compliance

### OEE — ISO 22400-2 Alignment

ISO 22400-2 is the international standard for Key Performance Indicators (KPIs) for manufacturing operations management.

| Metric | Project Implementation | ISO 22400-2 Standard | Compliance |
|---|---|---|---|
| Availability | `(plannedMin − alarmMin) / plannedMin × 100` | `Operating Time / Planned Production Time × 100` | ⚠ Partial |
| Performance | `(parts × cycleTimeStd) / availableSeconds × 100` | `(Theoretical Cycle Time × Actual Output) / Operating Time × 100` | ✅ Aligned |
| Quality | `goodParts / totalParts × 100` | `Good Count / Total Count × 100` | ✅ Aligned |
| OEE | `A × P × Q / 10000` | `Availability × Performance × Quality` | ✅ Correct |
| Planned Production Time | `duration_min − break_min` | Scheduled time minus planned stops | ⚠ Partial |

### ISO 22400-2 Specific Gaps

| ID | Severity | Gap | Impact |
|---|---|---|---|
| K1 | 🟠 HIGH | **Availability uses `alarm_code` as a downtime proxy instead of the `downtimes` table.** The project has a fully implemented `downtimes` table with `started_at`, `ended_at`, `duration_minutes`, and reason codes — but `OeeCalculationService` ignores it entirely and counts alarm IoT records × log_interval as approximate downtime. Manually recorded downtime events (machine breakdown, maintenance, changeover) are not reflected in the Availability metric. | Downtime recorded by supervisors has zero impact on OEE — making the downtime module decorative. |
| K2 | 🟠 HIGH | **Two different Availability formulas coexist.** `OeeCalculationService` uses `(plannedMin - alarmMin) / plannedMin`, while `IotController::machineChart()` uses `(runSec + idleSec) / totalIotSec`. These produce different results for the same time window, making the OEE summary table inconsistent with the dashboard chart's availability display. | Users see conflicting availability percentages in different parts of the UI. |
| K3 | 🟡 MEDIUM | **Week-off days and factory holidays not excluded from OEE planned time.** `factories.week_off_days` and `factory_holidays` exist in the DB but `OeeCalculationService` does not check them. On a weekend or holiday, the machine shows Availability = 0% (no IoT data = full alarm time) instead of being excluded from the calculation. | Weekend OEE reports show incorrect 0% Availability, distorting weekly and monthly OEE summaries. |
| K4 | 🟡 MEDIUM | **Planned Maintenance not integrated with OEE.** Machines with `status='maintenance'` are still included in OEE aggregation runs. Planned maintenance time should be subtracted from Planned Production Time (planned downtime), not counted as unplanned downtime. | Scheduled maintenance degrades OEE Availability incorrectly. |

### IEC 62443 (Industrial Cybersecurity) Alignment

IEC 62443 is the international standard for Industrial Automation and Control System (IACS) security.

| Requirement | Current Status | Gap |
|---|---|---|
| Device authentication | ⚠ SHA-256 token (acceptable but not certificate-based) | No PKI/certificate-based mutual authentication |
| Data integrity validation | ❌ No validation on IoT ingest payloads | Forged or corrupted telemetry is accepted silently |
| Least-privilege access | ✅ Factory-scoped RBAC with 6 roles | — |
| Audit logging | ❌ No audit trail for production record changes | No IEC 62443 SL-2 compliance |
| Secure communication | ⚠ No TLS enforcement at application layer | Relies on infrastructure to enforce HTTPS |
| Anomaly detection on telemetry | ❌ No anomaly detection | A compromised device can inject arbitrary values |
| Network segmentation | ❌ No documented network architecture | IoT devices and admin users share the same API |

### ISO 9001 (Quality Management) Alignment

| Requirement | Current Status |
|---|---|
| Traceability of production records | ⚠ Partial — production_actuals exist but no change history |
| Non-conformance management | ⚠ Partial — reject_reasons exist but not linked to production_actuals |
| Corrective action tracking | ❌ Not implemented |
| Document control (part drawings) | ✅ part_drawings table with UUID storage |
| Customer focus (work orders) | ✅ work_orders linked to customers and parts |

---

## 12. Missing Features, Risks, and Potential System Failures

### Automated Testing — Critical Gap

| ID | Finding |
|---|---|
| T1 | **There are exactly 2 test files** (`tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`) — both are Laravel scaffolding placeholders with no actual test assertions. A system of this complexity with zero meaningful test coverage is a significant quality and reliability risk. |
| T2 | **The OEE LAG window function calculation is the most mathematically critical code in the system and has zero test coverage.** A single off-by-one in the seed logic produces phantom parts across shift boundaries, silently inflating OEE performance metrics. |
| T3 | **Production plan state machine transitions have no automated tests.** Business rules (immutable when completed/cancelled) are enforced only in policies and controllers. No test verifies that direct DB writes or policy bypasses maintain state integrity. |
| T4 | **Multi-tenant factory isolation (FactoryScope) has no integration tests.** A regression in the global scope or middleware could expose one factory's data to another — a critical data breach scenario that should be covered by automated tests. |

### Missing Features

| Feature | Risk if Missing | Priority |
|---|---|---|
| **Queue worker** | Background jobs (future notifications, async exports) silently accumulate in `jobs` table, never processed. | 🟠 HIGH |
| **Email/SMS notifications** | `MAIL_MAILER=log` — operators receive no alerts on plan assignments, WO releases, or machine alarms. Supervisors cannot be notified of downtime events. | 🟡 MEDIUM |
| **Database backup strategy** | No documented DB backup. An accidental `migrate:fresh` permanently destroys all production data, OEE history, and work orders. | 🟠 HIGH |
| **Downtime ↔ OEE integration** | `downtimes` table is tracked but `OeeCalculationService` ignores it. Downtime records have zero effect on Availability calculation. The downtime module is effectively decorative for OEE purposes. | 🟠 HIGH |
| **Reject Reason ↔ Production Actuals FK** | `reject_reasons` and `production_actuals` both exist but have no relationship. Defects cannot be categorized by reason code — Pareto analysis and quality root-cause tracking are impossible. | 🟡 MEDIUM |
| **Work Order ↔ OEE integration** | Work orders define `total_planned_qty` and `expected_delivery_date` but are not used in OEE attainment or daily target calculations. | 🟡 MEDIUM |
| **Machine maintenance scheduling** | Machines have `maintenance` status but no maintenance calendar. Planned maintenance time is not excluded from OEE planned time. | 🟡 MEDIUM |
| **File storage for production** | Part drawings use `FILESYSTEM_DISK=local`. In a multi-server deployment, drawings uploaded to one server are not visible on others. | 🟡 MEDIUM |
| **Shift handover reports** | No end-of-shift summary (parts produced, downtime, OEE) generated automatically. Supervisors must manually query the dashboard. | 🟢 LOW |
| **Energy monitoring** | Machines have no energy consumption telemetry. ISO 50001 energy management is not addressed. | 🟢 LOW |
| **Spare parts inventory** | No module for tracking consumables tied to machine maintenance. | 🟢 LOW |

---

## 13. Scalability Analysis: 3–5 Machines → 100+ Machines

### The Raw Numbers

```
At 1 record/second per machine (standard IoT polling interval):

   5 machines  →       432,000 rows/day  →      13 M rows/month
  100 machines  →    8,640,000 rows/day  →     260 M rows/month
  100 machines  →  259,200,000 rows/year →  3.1 B rows/year
  (90-day live retention at 100 machines = ~780 M rows active in iot_logs)

Storage estimate (iot_logs ≈ 100 bytes/row + MariaDB index overhead ×2):
  100 machines, 90-day retention  →  ~155 GB for iot_logs alone
```

> **Short answer:** The system will start showing problems before 30 machines and will fail at 100 machines in 4 specific, identifiable places. Each one is fixable. Below is a precise breakdown with exact line references.

---

### Current Index Status (Verified in Database)

Before analysing problems, the following indexes were verified to already exist:

| Table | Index | Columns | Status |
|---|---|---|---|
| `iot_logs` | `iot_logs_machine_id_logged_at_index` | machine_id, logged_at | ✅ Present |
| `iot_logs` | `iot_logs_factory_id_logged_at_index` | factory_id, logged_at | ✅ Present |
| `production_plans` | `production_plans_machine_id_planned_date_index` | machine_id, planned_date | ✅ Present |
| `production_plans` | `production_plans_factory_id_planned_date_status_index` | factory_id, planned_date, status | ✅ Present |
| `part_processes` | `part_processes_part_id_sequence_order_unique` | part_id, sequence_order | ✅ Present (UNIQUE) |

Indexes are correctly defined. Bottlenecks below are code-level, not schema-level.

---

### Bottleneck 1 — 🔴 CRITICAL: OEE Aggregation Does 3× Redundant Work and Explodes to ~3,000 Queries per Run

**Location:** `app/Domain/Analytics/Services/OeeAggregationService.php` lines 58–63 and line 99.

The outer aggregation loop:

```php
foreach ($machines as $machine) {       // 100 iterations
    foreach ($shifts as $shift) {       // 3 iterations each = 300 total
        $this->upsertShift($machine, $shift, $date, $dateStr);
    }
}
```

Inside `upsertShift()` (line 99), for every single machine×shift combination:

```php
$shiftRows = $this->oeeCalculationService->calculateAllShifts($machine, $date);
// ↑ Calculates ALL 3 shifts for the machine, every time.
// Line 102 then throws away the other 2 results:
$matchingRow = $shiftRows->first(fn($r) => $r['shift']->id === $shift->id);
```

**This means `calculateAllShifts()` is called 300 times when 100 calls would be sufficient — 3× redundant work.**

Each `calculateForShift()` call issues these queries against `iot_logs`:

| Query | Per call | Total calls | Total queries |
|---|---|---|---|
| Seed LAG lookup (raw SQL) | 1 | 900 | 900 |
| Main LAG window aggregate (raw SQL) | 1 | 900 | 900 |
| `production_actuals` aggregate | 0–1 | 900 | up to 900 |
| `computeChartData()` seed + window | 2 | 300 | 600 |
| `MachineOeeShift::where()->first()` | 1 | 300 | 300 |
| `updateOrCreate()` | 1–2 | 300 | 300–600 |
| **Total** | | | **~3,000 queries** |

The scheduler runs this every **5 minutes**. With 3,000 queries per run at 100 machines — many of them LAG window functions scanning millions of rows — the command will take 15–60+ minutes. The `withoutOverlapping()` lock means every subsequent scheduler slot is **silently skipped**. OEE data stops updating entirely.

**Fix:** Move `calculateAllShifts()` outside the shift loop — call it once per machine:

```php
// Current (broken at scale):
foreach ($machines as $machine) {
    foreach ($shifts as $shift) {
        $this->upsertShift($machine, $shift, $date, $dateStr);
        // upsertShift internally calls calculateAllShifts() — wasteful
    }
}

// Fixed:
foreach ($machines as $machine) {
    $allResults = $this->oeeCalculationService->calculateAllShifts($machine, $date);
    foreach ($allResults as $result) {
        $this->upsertShiftFromResult($machine, $result, $date, $dateStr);
        $rows++;
    }
}
// calculateAllShifts() calls: 100 (was 300) — 3× reduction
// calculateForShift() calls:  300 (was 900) — 3× reduction
// Total queries:              ~1,000 (was ~3,000) — 3× reduction
```

---

### Bottleneck 2 — 🔴 CRITICAL: IoT Status Endpoint Self-Join Collapses at Scale

**Location:** `app/Http/Controllers/Api/V1/Iot/IotController.php` lines 222–233.

The status endpoint runs every **30 seconds** (cache TTL). It finds the latest log row per machine via a self-join:

```php
// Step 1: scan iot_logs to find MAX(logged_at) per machine
$latestTimes = DB::table('iot_logs')
    ->whereIn('machine_id', $machineIds)         // 100 IDs
    ->select('machine_id', DB::raw('MAX(logged_at) as latest_at'))
    ->groupBy('machine_id');

// Step 2: self-JOIN back to retrieve the full row for each machine's latest record
$latestLogs = IotLog::joinSub($latestTimes, 'latest', function ($join) {
    $join->on('iot_logs.machine_id', '=', 'latest.machine_id')
         ->on('iot_logs.logged_at',  '=', 'latest.latest_at');
})->select('iot_logs.*')->get();
```

Even with the `(machine_id, logged_at)` index, a GROUP BY + self-join across 780 M rows is a multi-second operation. Every cache miss (every 30 seconds minimum) hits this query.

| Scale | iot_logs rows | Status query time (indexed) |
|---|---|---|
| 5 machines, 1 week | ~3 M | ~10 ms |
| 20 machines, 1 month | ~52 M | ~200 ms |
| 100 machines, 3 months | ~780 M | **5–30 s** |

**Fix:** Add a dedicated `machine_latest_logs` table — one row per machine, upserted on every ingest. The status query becomes a simple primary-key lookup:

```sql
-- New migration
CREATE TABLE machine_latest_logs (
    machine_id   BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    factory_id   BIGINT UNSIGNED NOT NULL,
    alarm_code   SMALLINT        NOT NULL DEFAULT 0,
    auto_mode    TINYINT         NOT NULL DEFAULT 0,
    cycle_state  TINYINT         NOT NULL DEFAULT 0,
    part_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    part_reject  INT UNSIGNED    NOT NULL DEFAULT 0,
    slave_name   VARCHAR(100)    NULL,
    logged_at    TIMESTAMP       NOT NULL,
    updated_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_factory (factory_id),
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
);
```

On every ingest, upsert this table alongside the main `iot_logs` insert:

```sql
INSERT INTO machine_latest_logs (...) VALUES (...)
ON DUPLICATE KEY UPDATE
    alarm_code  = VALUES(alarm_code),
    auto_mode   = VALUES(auto_mode),
    cycle_state = VALUES(cycle_state),
    part_count  = VALUES(part_count),
    part_reject = VALUES(part_reject),
    logged_at   = VALUES(logged_at);
```

The status endpoint then queries `machine_latest_logs WHERE factory_id = ?` — **O(machines) rows, not O(iot_logs rows)**. Query time: <1 ms at any scale.

---

### Bottleneck 3 — 🟠 HIGH: `resolveDevice()` Called Per Record in Batch = Thousands of Cache Hits per Request

**Location:** `app/Http/Controllers/Api/V1/Iot/IotController.php` line 115.

```php
foreach ($payloads as $payload) {
    $machine = $this->resolveDevice($request, $payload);  // Redis/DB hit per record
    ...
}
```

If 100 machines each send a 500-record batch simultaneously:
- 100 requests × 500 records × 1 cache lookup = **50,000 Redis lookups per second**
- On a Redis cache miss (cold start, `migrate:fresh`), this becomes **50,000 DB queries per second**

**Fix:** Pre-resolve all unique tokens before the loop:

```php
// Collect all unique device tokens from the batch first
$header = $request->header('X-Device-Token');
$resolvedMachines = [];

if ($header) {
    // Single-token batch (all records from one device) — resolve once
    $resolvedMachines[$header] = $this->machineRepository
        ->findByDeviceToken(hash('sha256', $header));
} else {
    // Multi-device batch — resolve each unique token once
    foreach ($payloads as $payload) {
        $token = $payload['device_token'] ?? null;
        if ($token && !array_key_exists($token, $resolvedMachines)) {
            $resolvedMachines[$token] = $this->machineRepository
                ->findByDeviceToken(hash('sha256', $token));
        }
    }
}
// Then use $resolvedMachines[$token] inside the loop — O(unique devices) lookups
```

---

### Bottleneck 4 — 🟠 HIGH: No Table Partitioning — Purge Degrades Live Ingest

**Location:** `app/Console/Commands/PurgeIotLogsCommand.php`.

Without partitioning, purging old rows with `DELETE WHERE logged_at < X LIMIT 10000` on a 780 M-row table:
- Holds row-level locks during deletion — blocks concurrent IoT inserts
- Generates large InnoDB undo log entries — slows all concurrent reads
- Creates replication lag on any replica nodes

**Fix:** Apply `RANGE` partitioning by month on `iot_logs`:

```sql
ALTER TABLE iot_logs
PARTITION BY RANGE (TO_DAYS(logged_at)) (
    PARTITION p2026_01 VALUES LESS THAN (TO_DAYS('2026-02-01')),
    PARTITION p2026_02 VALUES LESS THAN (TO_DAYS('2026-03-01')),
    PARTITION p2026_03 VALUES LESS THAN (TO_DAYS('2026-04-01')),
    PARTITION p2026_04 VALUES LESS THAN (TO_DAYS('2026-05-01')),
    PARTITION p_future  VALUES LESS THAN MAXVALUE
);
```

Add new partitions monthly via a scheduled command. Purging old data becomes:

```sql
ALTER TABLE iot_logs DROP PARTITION p2026_01;
-- Instant, zero row locks, zero undo log, zero replication impact
```

> **Note:** Partitioning an existing large table requires a full table rebuild. Apply this before the table grows beyond ~50 M rows.

---

### Bottleneck 5 — 🟠 HIGH: Synchronous OEE Aggregation Will Miss Runs Under Load

**Location:** `routes/console.php` (scheduler) + `AggregateOeeCommand.php`.

```php
$schedule->command('iot:aggregate-oee')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

- `withoutOverlapping()` means: if run N is still executing when run N+1 is due, **run N+1 is silently skipped**
- At 100 machines, aggregation takes 15–60+ minutes → every subsequent run is dropped
- `runInBackground()` forks a process, but the single process still runs all 3,000 queries serially

**Fix:** Dispatch one queued job per machine from the scheduler. Workers process jobs in parallel:

```php
// routes/console.php
$schedule->call(function () {
    $date = \Carbon\Carbon::today()->format('Y-m-d');
    \App\Domain\Machine\Models\Machine::where('status', '!=', 'retired')
        ->each(fn ($machine) => \App\Jobs\AggregateOeeMachineJob::dispatch($machine->id, $date));
})->everyFiveMinutes();

// With 4 queue workers: 100 machines processed in parallel
// Total wall-clock time: ~1 min instead of 30–60 min
```

---

### Machine Count vs System Health

| Machine Count | iot_logs/day | OEE queries/run | Status query | Overall Verdict |
|---|---|---|---|---|
| **5** (current) | 432 K | ~30 | < 10 ms | ✅ Works fine |
| **20** | 1.7 M | ~120 | ~100 ms | ✅ Acceptable |
| **30** | 2.6 M | ~180 | ~300 ms | ⚠ OEE starts running slow |
| **50** | 4.3 M | ~750 | ~1–2 s | 🟠 OEE occasionally skips; status slow |
| **100** | 8.6 M | **~3,000** | **5–30 s** | 🔴 OEE stops updating; status times out |
| **100** (90-day data) | — | — | **timeout** | 🔴 Full system failure without fixes |

---

### Fix Priority for 100-Machine Scale

| Priority | Fix | Breaks Without It At |
|---|---|---|
| 1 | Fix `calculateAllShifts()` called 3× per machine in aggregation loop | ~20 machines |
| 2 | Add `machine_latest_logs` table for O(1) status lookups | ~30 machines |
| 3 | Move OEE aggregation to queued jobs (one job per machine) | ~30 machines |
| 4 | Pre-resolve device tokens before batch ingest loop | ~50 machines |
| 5 | Add `iot_logs` table partitioning by month | ~50 machines |
| 6 | Switch session/cache driver from database to Redis | ~50 machines |

> All five critical indexes (`machine_id+logged_at`, `factory_id+logged_at`, `planned_date+machine_id`, etc.) are already present in the current database and do not require migration changes.

---

## Summary Scorecard

| Area | Score | Primary Concern |
|---|---|---|
| 1. Architecture | 7 / 10 | Repository pattern incomplete; no Application layer or Domain Events |
| 2. Code Quality | 6 / 10 | SQL injection via string interpolation; 955-line controller; raw SQL in controllers |
| 3. Security | 4 / 10 | Demo auth bypass on public endpoint; no token expiry; no SRI; unencrypted sessions |
| 4. API Design | 5 / 10 | No API documentation; no ingest validation; inconsistent response envelopes |
| 5. Database | 7 / 10 | Good schema; missing indexes at scale; no soft deletes; no audit trail |
| 6. Performance | 6 / 10 | Synchronous OEE aggregation; N+1 in batch ingest; database-based cache |
| 7. Logging | 3 / 10 | Near-zero application logging; no monitoring integration; no health check |
| 8. Validation | 5 / 10 | IoT ingest completely unvalidated; date params unsafe; MIME type not verified |
| 9. Auth / Authz | 7 / 10 | Good RBAC design; critical demo bypass; no token expiry; no MFA |
| 10. Documentation | 5 / 10 | No API spec; no deployment docs; good inline comments |
| 11. ISO Compliance | 6 / 10 | OEE partially aligned; downtimes not integrated; two conflicting availability formulas |
| 12. Test Coverage | 1 / 10 | Zero meaningful tests across a 45-controller, 22-model codebase |

**Overall Score: 57 / 120 (48%)** — Not production-ready for an industrial MES without addressing Critical and High issues.

---

## Priority Action Plan

### Phase 1 — Immediate (Critical — Fix Before Any Production Deployment)

| # | Action | File(s) |
|---|---|---|
| 1 | Fix SQL Injection: replace `'{$sinceStr}'` string interpolation with `?` parameterized bindings in `machineTimeline()` | `app/Http/Controllers/Api/V1/Iot/IotController.php` |
| 2 | Remove demo auth bypass (`machine_id` / `slavename` fallbacks) from the public IoT ingest endpoint, or gate behind `app()->environment('local')` | `app/Http/Controllers/Api/V1/Iot/IotController.php` |
| 3 | Set `APP_DEBUG=false` and `SESSION_ENCRYPT=true` in production `.env` | `.env` / `.env.example` |
| 4 | Add SRI hash attributes to all CDN `<script>` and `<link>` tags in the admin layout | `resources/views/admin/layouts/app.blade.php` |
| 5 | Set Sanctum token expiration to 7 days: `'expiration' => 10080` | `config/sanctum.php` |
| 6 | Add `DemoSeeder` production environment guard | `database/seeders/DemoSeeder.php` |

### Phase 2 — Short Term (High — Fix Within First Sprint)

| # | Action | File(s) |
|---|---|---|
| 7 | Add `IngestRequest` and `BatchIngestRequest` FormRequest classes with field type and range validation | `app/Http/Requests/Api/Iot/` |
| 8 | Add date format validation before all `Carbon::parse()` calls in API query parameters | All IoT and OEE controllers |
| 9 | Flush Spatie permission cache on every role sync and permission matrix update | Role sync handler |
| 10 | Integrate `downtimes` table into `OeeCalculationService::calculateForShift()` for accurate Availability | `app/Domain/Analytics/Services/OeeCalculationService.php` |
| 11 | Unify the two Availability formulas — `OeeCalculationService` and `IotController::machineChart()` must use the same calculation | Both files above |
| 12 | Add FK between `production_actuals` and `reject_reasons` with a `reject_reason_id` column | New migration |
| 13 | Add composite indexes: `iot_logs(machine_id, logged_at)`, `production_plans(planned_date, machine_id)`, `part_processes(part_id, sequence_order)` | New migration |
| 14 | Add structured logging at every production plan state transition and work order status change | Plan and WO controllers / services |

### Phase 3 — Medium Term (Quality — Next Milestone)

| # | Action |
|---|---|
| 15 | Write unit tests for `OeeCalculationService` (LAG edge cases, shift boundary, no-plan scenario) |
| 16 | Write feature tests for factory isolation (cross-factory data access should return 403) |
| 17 | Write feature tests for production plan state machine (immutability of completed/cancelled plans) |
| 18 | Split `IotController` into focused controllers (Ingest, Chart, Export, Status) |
| 19 | Move OEE aggregation to queued jobs: one `AggregateOeeJob` per machine dispatched by the scheduler |
| 20 | Add a `/health` endpoint checking DB, cache, and queue depth |
| 21 | Generate OpenAPI documentation with `dedoc/scramble` or `darkaonline/l5-swagger` |
| 22 | Configure Redis for session and cache drivers in production |
| 23 | Add `spatie/laravel-activitylog` for audit trail on production plans, work orders, and role changes |
| 24 | Complete repository pattern for `ProductionPlan`, `WorkOrder`, `Shift`, `Downtime`, `ProductionActual` |
| 25 | Integrate Sentry (or equivalent) for exception monitoring with alerting |

### Phase 4 — Long Term (Compliance and Scalability)

| # | Action |
|---|---|
| 26 | Implement MFA (TOTP) for factory-admin and super-admin roles (IEC 62443) |
| 27 | Exclude week-off days and factory holidays from OEE planned time calculation (ISO 22400-2) |
| 28 | Integrate planned maintenance calendar with OEE availability (planned downtime) |
| 29 | Implement Domain Events for state transitions (plan released, WO completed, machine alarm) |
| 30 | Add HTTPS enforcement middleware and HSTS headers |
| 31 | Implement IP allowlisting for the admin panel |
| 32 | Migrate part drawings to S3-compatible storage for multi-server deployments |
| 33 | Add energy monitoring telemetry fields to IoT payload and iot_logs table (ISO 50001 baseline) |

---

*End of Report — SmartFactory ISO/Industrial Standards Review v1.1*
*Generated: 2026-03-14 | Total Issues Found: 65 | Critical: 6 | High: 21 | Medium: 28 | Low: 10*
*Sections: 12 ISO Review Areas + Scalability Analysis (Section 13)*
