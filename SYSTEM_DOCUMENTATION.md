# SmartFactory — System Documentation

> Version: 2.1 | Platform: Laravel 11 + MariaDB | Architecture: DDD + Multi-Tenant
> Last Updated: 2026-03-12

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Technology Stack](#2-technology-stack)
3. [Setup & Installation](#3-setup--installation)
4. [Database Structure](#4-database-structure)
5. [Authentication & Access Control](#5-authentication--access-control)
6. [Admin Panel Modules](#6-admin-panel-modules)
7. [Employee Portal](#7-employee-portal)
8. [REST API Reference](#8-rest-api-reference)
9. [IoT Integration](#9-iot-integration)
10. [IoT Dashboard — KPI Reference](#10-iot-dashboard--kpi-reference)
11. [OEE Calculation Engine](#11-oee-calculation-engine)
12. [Production Planning — Complete Process & Output](#12-production-planning--complete-process--output)
13. [Work Orders](#13-work-orders)
14. [Background Jobs & Scheduler](#14-background-jobs--scheduler)
15. [Domain Architecture](#15-domain-architecture)
16. [Server Commands Reference](#16-server-commands-reference)
17. [Production Server Operations — OEE Scheduler & MySQL Data Management](#17-production-server-operations--oee-scheduler--mysql-data-management)

---

## 1. System Overview

SmartFactory is a **multi-tenant Industry 4.0 manufacturing platform** that connects IoT machines to a production management system. Each company is a **Factory** (tenant). Users, machines, production plans, and data are isolated per factory.

### What the System Does

| Area | Capability |
|------|-----------|
| IoT | Receives pulse data from CNC machines / PLCs via REST API (single or batch) |
| Machine Monitoring | Real-time status: RUNNING / IDLE / ALARM / OFFLINE per machine |
| OEE | Calculates Overall Equipment Effectiveness (Availability × Performance × Quality) per shift |
| Machine Timeline | Gantt-style state timeline (Running / Idle / Alarm / Offline segments per 5-min bucket) |
| Reliability | MTBF (Mean Time Between Failures) and MTTR (Mean Time To Repair) from IoT data |
| Production Planning | Calendar-based weekly plan with shift slots per machine |
| Work Orders | Group production plans into a work order for customer delivery tracking |
| Downtime | Records, classifies, and reports machine stoppages |
| Employees | Operator portal showing assigned machine jobs |
| Administration | Full CRUD for users, roles, permissions, machines, parts, customers |
| Analytics | OEE trend charts, fleet summary, production vs. target comparisons |

### Architecture at a Glance

```
┌─────────────────────────────────────────────────────┐
│  Browser (Tailwind CSS + Alpine.js)                  │
│  ┌──────────────────┐  ┌──────────────────────────┐ │
│  │  Admin Panel      │  │  Employee Portal          │ │
│  │  /admin/*         │  │  /employee/*              │ │
│  └──────────────────┘  └──────────────────────────┘ │
└──────────────────────────┬──────────────────────────┘
                           │ HTTP (Session / Sanctum token)
┌──────────────────────────▼──────────────────────────┐
│  Laravel 11 Application                              │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────┐ │
│  │ Web Routes  │  │  API Routes  │  │ Scheduler  │ │
│  │ (session)   │  │  (sanctum)   │  │ (5-min OEE)│ │
│  └─────────────┘  └──────────────┘  └────────────┘ │
│  Domain Layer: Factory / Machine / Production /      │
│                Analytics / Auth / Shared             │
└──────────────────────────┬──────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────┐
│  MariaDB (smartfactory database)                     │
│  23 tables including iot_logs, machine_oee_shifts    │
└─────────────────────────────────────────────────────┘
                           ▲
                           │ POST /api/iot/ingest (public)
┌──────────────────────────┴──────────────────────────┐
│  IoT Devices (CNC, PLCs, sensors)                   │
│  Pulse every N seconds per machine                  │
└─────────────────────────────────────────────────────┘
```

---

## 2. Technology Stack

| Component | Technology |
|-----------|-----------|
| Framework | Laravel 11 (PHP 8.2+) |
| Database | MariaDB / MySQL |
| Auth | Laravel Sanctum (API tokens) + session (web) |
| RBAC | Spatie Laravel Permission (with Teams) |
| Frontend | Tailwind CSS (CDN) + Alpine.js 3.x (CDN) |
| Charts | Chart.js 4.4 (IoT dashboard) |
| Web Server | Apache via XAMPP (development) |
| Cache | File cache (Redis-ready for production) |

---

## 3. Setup & Installation

### Prerequisites

- PHP 8.2+
- XAMPP (Apache + MariaDB)
- Composer
- Node.js (only needed to compile Tailwind CSS if not using CDN)

### Steps

```bash
# 1. Clone / place project in htdocs
cd /d/xampp/htdocs/smartfactory

# 2. Install PHP dependencies
composer install

# 3. Copy environment file
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Create database
# Open phpMyAdmin at http://localhost/phpmyadmin
# Create database named: smartfactory

# 6. Run all migrations
php artisan migrate

# 7. Seed demo data (factory, users, machines, parts, plans, IoT logs)
php artisan db:seed --class=DemoSeeder

# 8. Seed realistic IoT demo telemetry (machine 1 — two shifts)
php artisan db:seed --class=IotDemoDataSeeder

# 9. Start development server
php artisan serve --port=8000
```

> **NOTE:** If using XAMPP Apache (port 80), skip step 9. Access at http://localhost/smartfactory/public.
> The development server at http://127.0.0.1:8000 is recommended for development.

### Fresh Reset (wipe everything + re-seed)

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
php artisan db:seed --class=IotDemoDataSeeder
```

> **NOTE:** This destroys ALL data. Use only in development.

### Demo Login Credentials

| Portal | URL | Email | Password | Role |
|--------|-----|-------|----------|------|
| Admin | /login | super@demo.local | password | Super Admin |
| Admin | /login | admin@demo.local | password | Factory Admin |
| Employee | /employee/login | operator@demo.local | password | Operator |

### IoT Demo Data (IotDemoDataSeeder)

Seeds realistic telemetry for **Machine 1 (CNC Lathe A)** across two shifts:

| Shift | Window | Records | Profile |
|-------|--------|---------|---------|
| Night | Mar 11 22:00 → Mar 12 06:00 | 480 rows | 68% run / 22% idle / 10% alarm |
| Morning | Mar 12 06:00 → Mar 12 14:00 | 480 rows | 73% run / 14% idle / 13% alarm |

One record per minute. `part_count` pulses every 3 running minutes. `alarm_code` cycles through codes 5, 6, 7 during alarm segments.

---

## 4. Database Structure

### Core Tables

```
factories                   — Tenant master (one row = one company)
  └── factory_settings      — OEE targets, log intervals, retention settings

users                       — All users (admin + employee, all portals)
  ├── factory_id            — Which factory this user belongs to (null = super-admin)
  └── machine_id            — Assigned machine for operators (nullable)

machines                    — IoT-connected machines
  └── device_token          — Secret token for IoT data ingestion (hidden in API)

shifts                      — Shift definitions (Morning / Afternoon / Night)
  Fields: name, start_time (HH:MM:SS), end_time (HH:MM:SS),
          duration_min, is_active, factory_id
```

### Production Tables

```
customers                   — Customer companies that order parts
parts                       — Product master (part_number, cycle_time_std)
  └── part_processes        — Routing: which processes a part goes through
process_masters             — Global process library (reference data)
production_plans            — Scheduled manufacturing jobs
  └── Status flow: draft → scheduled → in_progress → completed / cancelled
production_actuals          — Recorded quantities (good_qty auto-calculated)
work_orders                 — Group of production plans for a customer delivery
```

### IoT & Analytics Tables

```
iot_logs                    — Raw pulse data (one row per tick per machine)
  Fields: machine_id, factory_id, alarm_code, auto_mode, cycle_state,
          part_count (pulse 0/1), part_reject (pulse 0/1),
          slave_id, slave_name, logged_at, created_at
  NOTE: No updated_at column (append-only telemetry table)

machine_oee_shifts          — Aggregated OEE per machine per shift per day
  UNIQUE: machine_id + shift_id + oee_date
  Replaces scanning millions of iot_logs rows for dashboard queries
```

### RBAC Tables (Spatie)

```
roles                       — Role definitions (guard_name = 'sanctum')
permissions                 — Permission definitions (78 total)
model_has_roles             — User ↔ Role (team_id = factory_id; super-admin team_id = 0)
model_has_permissions       — User ↔ Direct permission grants
role_has_permissions        — Role ↔ Permission grants
```

### Downtime Tables

```
downtime_reasons            — Reason code library (machine breakdown, changeover, etc.)
downtimes                   — Recorded downtime events (start/end times, reason, classification)
```

---

## 5. Authentication & Access Control

### Two Portals, One User Table

SmartFactory uses a **single `users` table** for both portals. The portal a user can access is determined by their **role**.

| Portal | URL Prefix | Guard | Middleware |
|--------|-----------|-------|-----------|
| Admin Panel | /admin | auth:web (session) | admin.role — blocks operator/viewer |
| Employee Portal | /employee | auth:web (session) | employee.role — blocks admin/manager roles |

### Login Flow

1. User submits credentials at `/login` or `/employee/login`
2. Session is created (`auth:web`)
3. A Sanctum API token is also created and stored in the session (`session('api_token')`)
4. Admin pages use this token for Alpine.js AJAX calls to the API

### Role Hierarchy

```
Super Admin (level 100)  — No factory; sees ALL factories
    │
Factory Admin (level 80) — Full control within one factory
    │
Production Manager (level 60) — Plans, analytics, machines
    │
Supervisor (level 40)    — Shifts, downtime, actuals
    │
Operator (level 20)      — Record production; report downtime
    │
Viewer (level 10)        — Read-only dashboards
```

**Rules:**
- A user can only assign roles **below** their own level
- Factory Admin cannot promote someone to Factory Admin or higher
- Super Admin cannot be assigned via the UI (seeder/console only)

### Permission System

78 permissions grouped into 11 categories:

| Group | Example Permissions |
|-------|-------------------|
| Factory Management | view-any.factory, create.factory, update.factory-settings |
| User Management | view-any.user, create.user, assign-role.user |
| Role Management | view-any.role, create.role, sync-permissions.role |
| Machine Management | view-any.machine, create.machine, view.machine-logs |
| Downtime Management | create.downtime, close.downtime, classify.downtime |
| Production Planning | create.production-plan, approve.production-plan |
| Production Actuals | create.production-actual, update.production-actual |
| Parts & Processes | create.part, update.part, create.process-master |
| Customer Management | create.customer, update.customer |
| Shift Management | create.shift, update.shift |
| Analytics & Reports | view.oee-report, export.oee-report, view.production-report |

### Multi-Tenant (Factory) Isolation

Every model except `Factory` and `ProcessMaster` has a **global scope** that automatically filters by `factory_id`:

```php
// This query automatically adds WHERE factory_id = {current factory}
Machine::where('status', 'active')->get();

// Bypass if needed (e.g., Super Admin)
Machine::withoutFactoryScope()->get();
```

---

## 6. Admin Panel Modules

Access all modules at: `http://127.0.0.1:8000/admin`

### 6.1 Dashboard (`/admin/dashboard`)

Real-time overview page that polls every 15 seconds.

**Shows:** Machine count (active/total), production plans today (pending/in progress/completed), open downtimes, active users.

**How it works:** Alpine.js `setInterval` calls the API every 15 seconds and updates counts without page reload.

---

### 6.2 IoT Dashboard (`/admin/iot`)

Industry 4.0 real-time machine monitoring panel. Built with Alpine.js + Chart.js. Auto-refreshes every 30 seconds.

#### Fleet KPI Summary (4 cards at top)

| Card | Value | Source |
|------|-------|--------|
| Running Now | Count of machines with `iot_status = running` | Live from `/api/v1/iot/status` |
| Active Alarms | Count of machines with `iot_status = alarm` | Live; icon blinks red when > 0 |
| Parts Today | Sum of all `total_parts` across all machines/shifts today | From OEE report |
| Fleet OEE | Average OEE% across all machines/shifts today | From OEE report; green ≥ 85%, amber ≥ 60%, red < 60% |

#### Machine Grid

Cards for every active machine, color-coded by status:

| Border Color | Status | Condition |
|-------------|--------|-----------|
| Green | RUNNING | `cycle_state = 1` (most recent log, fresh) |
| Yellow | IDLE | `cycle_state = 0` AND `alarm_code = 0` (fresh) |
| Red | ALARM | `alarm_code > 0` AND `cycle_state = 0` (fresh) |
| Gray | OFFLINE | No data in last 10 minutes |

> **Priority rule:** `cycle_state = 1` (RUNNING) takes priority over `alarm_code > 0`. A machine that is mid-cycle but also reporting a minor alarm code is shown as RUNNING.

Each card shows: status dot (with ping animation when running), part count, reject count, last seen timestamp, today's OEE% badge (green/amber/red).

**Click a machine card** → opens the full-screen Machine Detail Overlay.

#### Machine Detail Overlay (Full Screen)

Opens over the dashboard when a machine card is clicked. Auto-selects the shift matching the current local time on open.

**Header Bar:**
- Back button, machine name + code + type, live status badge
- Alarm blink indicator when `alarm_code > 0`
- Date picker (synced with OEE date)
- Shift selector (All Day / Morning / Afternoon / Night)
- CSV Export button

**KPI Strip (6 metrics):**

| Metric | Formula | Threshold Colors |
|--------|---------|-----------------|
| OEE | A × P × Q / 10000 | Green ≥ 85, Amber ≥ 60, Red < 60 |
| Availability | (Planned − Alarm) ÷ Planned × 100 | Same thresholds |
| Performance | (Parts × Cycle Time) ÷ Available Sec × 100 | Same; `—` if no plan |
| Quality | Good Parts ÷ Total Parts × 100 | Same |
| Parts | Total pulse-count for shift/period | White |
| Rejects | Total reject pulses | Red if > 0 |

**Time Analysis Strip (6 metrics):**

| Metric | Formula | Display |
|--------|---------|---------|
| Run Time | SUM(cycle_state=1 records) × interval_sec ÷ 60 | HH:MM |
| Idle Time | SUM(cycle_state=0 AND alarm_code=0) × interval_sec ÷ 60 | HH:MM |
| Alarm Time | SUM(alarm_code>0 AND cycle_state=0) × interval_sec ÷ 60 | HH:MM |
| Availability % | (Run + Idle) ÷ (Run + Idle + Alarm) × 100 | Green ≥ 85, Amber ≥ 60, Red < 60 |
| Spindle Util % | Run ÷ (Run + Idle + Alarm) × 100 | Violet ≥ 70, Amber ≥ 45, Red < 45 |
| Parts / Run Hr | Total Parts ÷ (Run Minutes ÷ 60) | Blue |

**Machine State Timeline (Gantt Bar):**
- Horizontal bar spanning the full time window (shift or All Day)
- Each 5-minute bucket is classified and colored
- Bucket classification priority: `cycle_state=1` → RUNNING, else if `alarm_code>0` → ALARM, else → IDLE, no data → OFFLINE
- Time axis tick marks below bar (15-min steps ≤ 90 min, 30-min ≤ 240 min, 60-min otherwise)
- Summary pills: Running (green), Idle (yellow), Alarm (red), Offline (gray, hidden if 0)
- Hover tooltip shows: state, time range, duration in minutes
- Window label: `HH:MM – HH:MM`; All Day shows `00:00 – 24:00`

**Reliability Metrics (ISO 22400) — derived from timeline segments:**

| Metric | Formula | Color |
|--------|---------|-------|
| Fault Events | COUNT(alarm segments) | Green = 0, Amber ≤ 2, Red > 2 |
| MTBF | Total Run Minutes ÷ Fault Event Count | — if no faults |
| MTTR | Total Alarm Minutes ÷ Fault Event Count | Green = 0, Amber ≤ 10m, Red > 10m |

**Production Progress (actual vs. planned):**
- Shows attainment % for the selected shift from the OEE/plan data
- Progress bar: Green ≥ 100%, Indigo ≥ 75%, Amber ≥ 50%, Red < 50%
- Planned / Produced / Gap (±) columns
- Empty state if no production plan assigned for the shift

**Shift OEE Breakdown Table (ISO 22400):**
Per-shift row with: Shift name, Parts, Rejects, Avail%, Perf%, Qual%, OEE%.
Color-coded badges per column. "No plan" shown for Performance/OEE if no cycle time available.

**Live Telemetry Panel:**
- Status indicator with ping animation
- Cycle State (Running / Stopped)
- Auto Mode (On / Off)
- Alarm Code (blinking red if > 0)
- Last Data (time ago)
- Part Count (today's total pulse count)
- Rejects (today's total)
- Slave / PLC name
- Machine Code
- Auto-refresh every 5s indicator

**Chart Panels (scrollable):**

| Chart | Type | X-axis | Y-axis |
|-------|------|--------|--------|
| Parts / Hour | Bar chart | Hour | Parts produced that hour |
| Spindle Utilization / Hour | Stacked bar | Hour | % of hour: running (green), idle (yellow), alarm (red) |
| Rejects / Hour | Bar chart | Hour | Reject count that hour |
| Alarm Events / Hour | Bar chart | Hour | Alarm event count that hour |

#### OEE Shift Production Report (bottom of main page)

Factory-wide table. Shows all machines × all shifts for the selected date.

**Columns:** Machine, Shift, Planned Qty, Actual Parts, Good Parts, Rejects, Attainment%, Avail%, Perf%, Qual%, OEE%, Alarm Minutes, Log Count.

Clicking a row opens that machine's detail overlay.

#### OEE Trend Chart (bottom of main page)

Historical line chart for the factory. Four lines: OEE%, Availability%, Performance%, Quality%. Selectable range: 7 / 14 / 30 / 90 days.

**Data source:** `machine_oee_shifts` table (pre-aggregated by scheduler).

**How the dashboard works:**
- Machine grid: `GET /api/v1/iot/status` (30s polling)
- Shift list: `GET /api/v1/shifts?factory_id=N`
- Chart + Time Analysis: `GET /api/v1/iot/machines/{id}/chart?shift_id=X&date=YYYY-MM-DD`
- Timeline: `GET /api/v1/iot/machines/{id}/timeline?shift_id=X&date=YYYY-MM-DD`
- OEE table: `GET /api/v1/iot/oee?date=YYYY-MM-DD&factory_id=N`
- OEE trend: `GET /api/v1/iot/oee/trend?factory_id=N&days=30`
- CSV export: `GET /admin/iot/machines/{id}/export?shift_id=X&date=YYYY-MM-DD`

---

### 6.3 Production Planning (`/admin/production/plans`)

Google Calendar-style weekly planning grid.

```
┌──────────────┬──────────┬──────────┬──────────┬──────────┐
│   Machine    │  Mon     │  Tue     │  Wed     │  Thu     │
├──────────────┼──────────┼──────────┼──────────┼──────────┤
│ CNC Lathe A  │[●BKT-001]│[+ Morn.] │[+ Morn.] │[●BKT-001]│
│              │ Morning  │[+ Aftn.] │[+ Aftn.] │ Morning  │
├──────────────┼──────────┼──────────┼──────────┼──────────┤
│ Welder B     │[+ Morn.] │[●WLD-002]│[+ Morn.] │[+ Morn.] │
└──────────────┴──────────┴──────────┴──────────┴──────────┘
```

**Plan card colors by status:**

| Status | Card Style |
|--------|-----------|
| draft | Gray border + gray background |
| scheduled | Blue border + blue-50 background |
| in_progress | Amber border + amber-50 background |
| completed | Green border + green-50 background |
| cancelled | Red border + red-50 background |

**Creating a plan:** Click any `[+ Shift Name]` empty slot → modal opens pre-filled with machine, date, and shift → select part and quantity → Save.

**Editing a plan:** Click any colored plan card → edit modal → change status, quantity, notes → Save.

**Navigation:** Prev/Next week buttons + Today shortcut. Super Admin sees factory selector.

**How it works:**
- Grid data: `GET /api/v1/production-plans?from_date=X&to_date=Y&per_page=500`
- Create: `POST /api/v1/production-plans`
- Edit: `PUT /api/v1/production-plans/{id}`
- Delete: `DELETE /api/v1/production-plans/{id}`

> **Date handling note:** `planned_date` is cast as `string` (not `date`) in the model to avoid Carbon UTC conversion shifting the date in non-UTC timezones (e.g., India UTC+5:30 would show the date as one day behind).

---

### 6.4 Downtime Management (`/admin/downtimes`)

Lists all downtime events for the factory.

**Columns:** Machine, Start time, End time, Duration, Reason, Status (open/closed), Classification.

**Actions:** Filter by machine/date range, create new downtime record, close open downtimes, classify with reason codes.

---

### 6.5 User Management (`/admin/users`)

Manage all factory users.

**Actions per user:**
- **Edit** — Update name, email, password, active status
- **Assign Machine** (operator/viewer only) — Link operator to a machine for the employee portal
- **Permissions** — Opens the permission matrix modal
- **Revoke** — Removes all roles from the user

**Permission Matrix Modal:**
- 11 permission groups with checkboxes
- Gray (checked) = inherited from role (read-only)
- Violet = direct grants on top of role
- Save flow: Role change → Machine assignment → Direct permissions sync

---

### 6.6 Roles Management (`/admin/roles`)

**Edit Permissions** → Opens a **centered modal** (not a side drawer):
- Permission matrix with 11 groups and 78 checkboxes
- "Select All / Deselect All" per group and globally
- Live counter (e.g., "42 of 78 assigned")
- Save button (enabled only when changes are made)

**New Role** (Super Admin only) → Slug format: `lowercase-letters-digits-hyphens`.

**Delete** (Super Admin only, custom roles only) → Two-click confirm. Blocked if users are assigned to the role.

> System roles (super-admin, factory-admin, production-manager, supervisor, operator, viewer) **cannot be deleted**.

---

### 6.7 Machines (`/admin/machines`)

Machine directory. **Actions:** Create, edit, retire (guarded — cannot retire with open downtimes).

---

### 6.8 Customers (`/admin/customers`)

**Actions:** Create, edit, deactivate (guarded — cannot deactivate if active parts exist).

---

### 6.9 Parts (`/admin/parts`)

**Actions:** Create, edit, discontinue (guarded — cannot discontinue with active production plans), define routing.

**Routing Builder** (`/admin/parts/{part}/routing`) — Add process steps from the Process Master library, set override cycle time per step.

---

### 6.10 Process Masters (`/admin/process-masters`)

Global library of manufacturing processes (e.g., CNC Turning, Welding). Factory-independent reference records.

---

### 6.11 Shifts (`/admin/shifts`)

Define shift windows per factory. Each shift has: name, start_time, end_time, duration_min, is_active.

---

### 6.12 Factories (`/admin/factories`)

Super Admin only. Create and manage factory tenants.

---

### 6.13 Employees (`/admin/employees`)

Directory of operator and viewer role users. Shows machine assignment status.

---

## 7. Employee Portal

Access at: `http://127.0.0.1:8000/employee/login`

Designed for **shop-floor operators** using tablets or workstation PCs.

### 7.1 Login (`/employee/login`)

After login:
- Operator with a machine assigned → `/employee/dashboard`
- Operator with **no machine** → `/employee/no-machine` (contact admin message)
- Admin/manager roles → redirected to admin panel

### 7.2 Dashboard (`/employee/dashboard`)

Shows the operator's assigned machine and current/upcoming jobs.

**Displays:** Machine name and status, today's production plans, active shift.

### 7.3 Jobs (`/employee/jobs`)

All production plans for the operator's machine:
- Past 7 days — Completed and in-progress jobs
- Today — Current jobs highlighted
- Next 14 days — Upcoming scheduled jobs

**Columns:** Date, Shift, Part number + name, Planned quantity, Status badge.

> Employees cannot create or edit plans — view only.

---

## 8. REST API Reference

**Base URL:** `http://127.0.0.1:8000/api`
**Authentication:** Bearer token (`Authorization: Bearer {token}`)

```bash
POST /api/v1/auth/login
{ "email": "admin@demo.local", "password": "password" }
# Response: { "token": "...", "user": {...} }
```

### 8.1 Public Endpoints (No Auth)

| Method | Endpoint | Description |
|--------|---------|-------------|
| POST | /iot/ingest | Single IoT pulse |
| POST | /iot/ingest/batch | Batch IoT pulses (up to 500 records) |
| POST | /v1/auth/login | Get API token |
| POST | /v1/auth/logout | Revoke API token |
| GET | /v1/auth/me | Current user info |

### 8.2 Protected Endpoints

#### Factories
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/factories | List factories |
| POST | /v1/factories | Create factory |
| GET | /v1/factories/{id} | Get factory |
| PUT | /v1/factories/{id} | Update factory |
| DELETE | /v1/factories/{id} | Deactivate factory |
| GET | /v1/factories/{id}/settings | Factory OEE settings |
| PUT | /v1/factories/{id}/settings | Update settings |
| GET | /v1/factories/{id}/daily-targets | Daily target vs actual |

#### Machines
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/machines | List machines (`?status=active&factory_id=N`) |
| POST | /v1/machines | Create machine |
| GET | /v1/machines/{id} | Get machine |
| PUT | /v1/machines/{id} | Update machine |
| DELETE | /v1/machines/{id} | Retire machine |

#### Production Plans
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/production-plans | List plans (`?from_date=&to_date=&machine_id=&status=`) |
| POST | /v1/production-plans | Create plan |
| GET | /v1/production-plans/{id} | Get plan |
| PUT | /v1/production-plans/{id} | Update plan |
| DELETE | /v1/production-plans/{id} | Delete plan |
| GET | /v1/production-plans/{id}/analysis | Plan vs. actual analysis |

**Production Plan Status Flow:**
```
draft → scheduled → in_progress → completed
                              └──→ cancelled
```
Plans in `completed` or `cancelled` state are **immutable**.

#### Production Actuals
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/production-actuals | List actuals |
| POST | /v1/production-actuals | Record actual |
| PUT | /v1/production-actuals/{id} | Update actual |

> `good_qty` = `actual_qty - defect_qty` (MySQL GENERATED ALWAYS AS column — never set directly).

#### Work Orders
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/work-orders | List work orders |
| POST | /v1/work-orders | Create work order |
| GET | /v1/work-orders/{id} | Get work order |
| PUT | /v1/work-orders/{id} | Update work order |
| DELETE | /v1/work-orders/{id} | Delete work order |

#### Downtimes
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/downtimes | List downtimes |
| POST | /v1/downtimes | Create downtime event |
| PUT | /v1/downtimes/{id} | Update (close, classify) |
| GET | /v1/downtime-reasons | List reason codes |
| POST | /v1/downtime-reasons | Create reason code |

#### IoT & OEE
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/iot/status | Latest status snapshot per machine (cached 30s) |
| GET | /v1/iot/machines/{id}/chart | Hourly chart data + time analysis stats |
| GET | /v1/iot/machines/{id}/timeline | 5-min bucket state timeline |
| GET | /v1/iot/machines/{id}/export | CSV of raw logs |
| GET | /v1/iot/oee | Factory-wide OEE (`?date=YYYY-MM-DD&factory_id=N`) |
| GET | /v1/iot/machines/{id}/oee | Single machine OEE (`?date=&shift_id=`) |
| GET | /v1/iot/oee/trend | Historical OEE trend (`?factory_id=N&days=30`) |
| GET | /v1/shifts | List active shifts for factory |

---

## 9. IoT Integration

### How a Machine Sends Data

Each machine is configured with a **device token** (shown in the Machines admin page). The device sends a POST request periodically:

```bash
POST http://127.0.0.1:8000/api/iot/ingest
Content-Type: application/json

{
  "device_token": "abc123xyz",
  "alarm_code":    0,
  "auto_mode":     1,
  "cycle_state":   1,
  "part_count":    1,
  "part_reject":   0,
  "slave_id":      1,
  "slave_name":    "CNC_A",
  "logged_at":     "2026-03-12 08:00:05"
}
```

**Field reference:**

| Field | Type | Description |
|-------|------|-------------|
| device_token | string | Machine authentication secret |
| alarm_code | int | 0 = OK; any positive integer = fault active |
| auto_mode | int | 1 = automatic mode; 0 = manual or stopped |
| cycle_state | int | **1 = machine actively mid-cycle (RUNNING); 0 = cycle not active** |
| part_count | int (0/1) | Pulse — 1 when a part completes this tick; 0 otherwise |
| part_reject | int (0/1) | Pulse — 1 when a reject is detected this tick; 0 otherwise |
| slave_id | int | Sub-device / PLC node ID |
| slave_name | string | Sub-device label |
| logged_at | datetime | Timestamp from the device clock |

> **Pulse fields:** `part_count` and `part_reject` are binary (0 or 1). SUM over a window = total parts produced/rejected.

### Batch Ingest (Recommended for High-Frequency)

For 50+ machines at high frequency, use batch to minimise HTTP overhead:

```bash
POST http://127.0.0.1:8000/api/iot/ingest/batch
Content-Type: application/json

[
  { "device_token": "abc", "alarm_code": 0, "cycle_state": 1, "part_count": 1, ... },
  { "device_token": "abc", "alarm_code": 0, "cycle_state": 0, "part_count": 0, ... }
  // up to 500 records per request
]
```

One `INSERT` for all records — 500× faster than individual requests.

### Machine Status Derivation (3-State Model)

Status is derived from the **most recent log record** for each machine:

| Priority | Condition | Status |
|----------|-----------|--------|
| 1 | No data in last 10 minutes | **OFFLINE** |
| 2 | `cycle_state = 1` | **RUNNING** (green) |
| 3 | `alarm_code > 0` AND `cycle_state = 0` | **ALARM** (red) |
| 4 | All other cases | **IDLE** (yellow) |

> **Running beats alarm.** A machine that has `cycle_state = 1` and also `alarm_code > 0` is classified as RUNNING. Many PLCs report minor alarm codes during normal operation. The machine is producing parts so it is RUNNING.

### Machine Token Cache

Device tokens are cached in the application cache (TTL 300 seconds) to avoid a DB lookup on every IoT pulse. Cache is busted when a machine is retired or its token is rotated.

---

## 10. IoT Dashboard — KPI Reference

This section documents every KPI shown on the IoT dashboard, its formula, data source, and color thresholds.

### 10.1 Machine State Classification

Every log record is classified into one of four states:

| State | Condition | Color |
|-------|-----------|-------|
| **running** | `cycle_state = 1` | Green `#22c55e` |
| **alarm** | `alarm_code > 0` AND `cycle_state = 0` | Red `#ef4444` |
| **idle** | `alarm_code = 0` AND `cycle_state = 0` | Yellow `#eab308` |
| **offline** | No log data in bucket | Dark slate `#1e293b` |

Running always takes priority over alarm.

### 10.2 Timeline Bucketing

The timeline groups logs into **5-minute buckets** relative to the window start:

```sql
bucket_num = FLOOR(TIMESTAMPDIFF(SECOND, window_start, logged_at) / 300)

Per bucket:
  alarm_c = SUM(alarm_code > 0)
  run_c   = SUM(cycle_state = 1)
  idle_c  = SUM(alarm_code = 0 AND cycle_state = 0)

Bucket state:
  IF run_c   > 0  → 'running'
  IF alarm_c > 0  → 'alarm'
  ELSE            → 'idle'
  No rows at all  → 'offline'
```

Consecutive buckets with the same state are merged into segments. Each segment has `from_label`, `to_label`, `duration_min`.

### 10.3 Time Window Resolution

The API resolves the time window in priority order:

| Priority | Parameters | Window |
|----------|-----------|--------|
| 1 | `shift_id` + `date` | Exact shift start_time → end_time on that date (overnight shifts handled) |
| 2 | `date` only (All Day) | 00:00:00 → 24:00:00 on that date |
| 3 | `hours` (fallback) | Rolling N hours ending now |

### 10.4 Time Analysis Stats (from chart endpoint)

All time values are derived from pulse counts multiplied by the detected log interval:

```
log_interval_sec = span_seconds / (total_samples - 1)
                   (calculated from MIN/MAX logged_at and record count)

run_seconds   = SUM(cycle_state = 1 records) × log_interval_sec
idle_seconds  = SUM(cycle_state = 0 AND alarm_code = 0) × log_interval_sec
alarm_seconds = SUM(alarm_code > 0 AND cycle_state = 0) × log_interval_sec
```

### 10.5 Availability % (IoT-based)

This is the **machine-level** availability derived directly from IoT signals (distinct from OEE Availability which uses shift planned time):

```
IoT Availability % = (Run Seconds + Idle Seconds) / (Run + Idle + Alarm Seconds) × 100

Meaning: fraction of active time the machine was NOT in alarm state.
```

| Threshold | Color |
|-----------|-------|
| ≥ 85% | Green (on target) |
| ≥ 60% | Amber (below target) |
| < 60% | Red (critical) |

### 10.6 Spindle Utilization %

```
Spindle Util % = Run Seconds / (Run + Idle + Alarm Seconds) × 100

Meaning: fraction of active time the machine spindle was actually cutting.
```

| Threshold | Color |
|-----------|-------|
| ≥ 70% | Violet (excellent) |
| ≥ 45% | Amber (acceptable) |
| < 45% | Red (poor) |

### 10.7 Parts / Run Hour

```
Parts per Run Hour = Total Parts / (Run Seconds / 3600)

Meaning: production rate during spindle-on time only (excludes idle/alarm time).
```

### 10.8 OEE — Three Components (ISO 22400)

#### Availability (OEE Component)

```
Availability = (Planned Minutes − Alarm Minutes) / Planned Minutes × 100

Where:
  Planned Minutes = shift.duration_min
  Alarm Minutes   = COUNT(records where alarm_code > 0 AND cycle_state = 0)
                    × log_interval_sec / 60
```

#### Performance

```
Performance = (Total Parts × Cycle Time Standard in seconds) / Available Seconds × 100

Where:
  Total Parts          = SUM(part_count) from iot_logs in shift window
  Cycle Time Standard  = part.cycle_time_std (seconds) from active production plan
  Available Seconds    = (Planned Minutes − Alarm Minutes) × 60
```

> Performance is `NULL` (shown as "No plan") when no production plan with a part cycle time exists for this machine/shift/date.

#### Quality

```
Quality = Good Parts / Total Parts × 100

Where:
  Good Parts  = Total Parts − Rejected Parts
  Total Parts = SUM(part_count)
  Rejects     = SUM(part_reject)
```

#### OEE

```
OEE = Availability × Performance × Quality / 10000
```

### 10.9 Reliability Metrics (MTBF & MTTR)

Computed from the timeline segments returned by the timeline API endpoint.

```
Fault Events = COUNT of segments where state = 'alarm'
Total Run Min = SUM(duration_min for segments where state = 'running')
Total Alarm Min = SUM(duration_min for segments where state = 'alarm')

MTBF (Mean Time Between Failures) = Total Run Min / Fault Events
MTTR (Mean Time To Repair)         = Total Alarm Min / Fault Events
```

**MTBF** — higher is better. A high MTBF means the machine runs a long time between faults.
**MTTR** — lower is better. A low MTTR means faults are recovered quickly.

| MTTR | Color |
|------|-------|
| 0 min (no faults) | Emerald |
| ≤ 10 min | Amber |
| > 10 min | Red |

### 10.10 Production Progress (Attainment)

```
Attainment % = MIN(100, (Actual Parts / Planned Qty) × 100)

Gap = Planned Qty − Actual Parts  (negative = over-produced)
```

Progress bar color:

| Attainment | Color |
|------------|-------|
| ≥ 100% | Emerald (target met) |
| ≥ 75% | Indigo (on track) |
| ≥ 50% | Amber (behind) |
| < 50% | Red (critically behind) |

### 10.11 Fleet OEE

```
Fleet OEE = Average of all non-null OEE% values across all machines × all shifts for today
```

Computed from the OEE Shift Production Report data already loaded on the dashboard.

### 10.12 Defect Rate

```
Defect Rate % = Total Rejects / Total Parts × 100
```

Shown in the OEE Shift Production Report table and the CSV export.

### 10.13 KPI Summary Table

| KPI | Formula | Source | Unit |
|-----|---------|--------|------|
| Run Time | Σ(cycle_state=1) × interval | iot_logs | HH:MM |
| Idle Time | Σ(cycle_state=0, alarm=0) × interval | iot_logs | HH:MM |
| Alarm Time | Σ(alarm>0, cycle=0) × interval | iot_logs | HH:MM |
| IoT Availability % | (Run+Idle) / (Run+Idle+Alarm) | iot_logs | % |
| Spindle Util % | Run / (Run+Idle+Alarm) | iot_logs | % |
| Parts / Run Hr | Parts / (RunSec/3600) | iot_logs | pcs/hr |
| OEE Availability % | (Planned−Alarm) / Planned | iot_logs + shifts | % |
| OEE Performance % | (Parts×CycleTime) / AvailSec | iot_logs + plans | % |
| OEE Quality % | Good / Total | iot_logs | % |
| OEE % | A×P×Q/10000 | Derived | % |
| Fault Events | COUNT(alarm segments) | Timeline | count |
| MTBF | TotalRunMin / FaultEvents | Timeline | min |
| MTTR | TotalAlarmMin / FaultEvents | Timeline | min |
| Attainment % | Actual / Planned | OEE + plans | % |
| Defect Rate % | Rejects / Total | iot_logs | % |
| Fleet OEE | AVG(all machine OEE%) | machine_oee_shifts | % |
| Parts Today | SUM(all machine parts) | machine_oee_shifts | pcs |

---

## 11. OEE Calculation Engine

### Data Source Strategy

The OEE API uses a **two-tier lookup** to stay fast at scale:

1. **Fast path (cache)** — Query `machine_oee_shifts` summary table (pre-aggregated every 5 min)
2. **Live path** — If no summary row exists, calculate live from `iot_logs`
3. Add `?live=1` to force live recalculation

Response includes `"source": "cache"` or `"source": "live"`.

### machine_oee_shifts Table

```
UNIQUE: machine_id + shift_id + oee_date

Populated by: php artisan iot:aggregate-oee
Populated every: 5 minutes (via scheduler)

Stores per machine per shift per day:
  run_ticks, idle_ticks, alarm_ticks, total_ticks
  total_parts, reject_parts, good_parts
  alarm_minutes, availability_pct, performance_pct
  quality_pct, oee_pct
```

At 50 machines × 3 shifts = 150 rows/day vs millions of raw log rows.

---

## 12. Production Planning — Complete Process & Output

### 12.1 Prerequisites

```
Factory
  └── Shifts (Morning, Afternoon, Night — start_time, end_time, duration_min)
  └── Machines (status=active, device_token for IoT)
  └── Factory Settings (oee_target_pct, working_hours_per_day)

Customers → Parts (part_number, cycle_time_std in minutes)
  └── Part Processes (routing steps → Process Masters)
```

**Setup order:** Factory → Shifts → Machines → Process Masters → Customers → Parts → Routing

### 12.2 End-to-End Process Flow

```
STEP 1 — PLANNER CREATES PLAN
  Admin → /admin/production/plans → Click empty shift slot →
  Modal: Machine + Shift + Date + Part + Planned Qty → [Create Plan]
  API: POST /api/v1/production-plans
  DB: production_plans (status = draft)
         ↓
STEP 2 — PLAN APPROVAL
  Edit modal → Change status to "Scheduled"
  API: PUT /api/v1/production-plans/{id} { "status": "scheduled" }
         ↓
STEP 3 — OPERATOR SEES JOB
  operator@demo.local → /employee/jobs
  Sees scheduled plan for their assigned machine
         ↓
STEP 4 — IOT DATA FLOWS IN (automatic)
  Machine PLC → POST /api/iot/ingest { cycle_state: 1, part_count: 1, ... }
  DB: iot_logs (one row per pulse)
  Scheduler (every 5 min): aggregates → machine_oee_shifts
         ↓
STEP 5 — RECORD PRODUCTION ACTUALS (optional manual)
  POST /api/v1/production-actuals { plan_id, actual_qty, defect_qty }
  DB: production_actuals (good_qty auto-calculated)
         ↓
STEP 6 — ANALYSIS OUTPUT
  GET /api/v1/production-plans/{id}/analysis
  → Shift Target, Attainment %, OEE, Remaining Qty
```

### 12.3 Cycle Time & Shift Target Calculation

```
Part routing example: BKT-A001 (Bracket A)
  Step 1: CNC Turning   8.0 min  (process master)
  Step 2: Deburring     2.5 min  (part override: 2.5, master: 3.0)
  Step 3: Quality Check 2.0 min  (process master)
  ─────────────────────────────
  Total Cycle Time     12.5 min/unit

Factory OEE Target: 85%
Efficiency Factor: 0.85

Morning Shift (480 min):
  Effective Minutes   = 480 × 0.85 = 408 min
  Target Qty          = ⌊ 408 / 12.5 ⌋ = 32 units
  Theoretical Max Qty = ⌊ 480 / 12.5 ⌋ = 38 units
  Capacity Gap        = 38 − 32 = 6 units (lost to inefficiency)
```

**effectiveCycleTime per step** = `part_processes.standard_cycle_time` ?? `process_masters.standard_time` ?? 0

### 12.4 Production Actuals & Attainment

```
Batch recording:
POST /api/v1/production-actuals
{ "production_plan_id": 42, "actual_qty": 80, "defect_qty": 5 }

DB row:
  actual_qty = 80
  defect_qty = 5
  good_qty   = 75  ← MySQL GENERATED ALWAYS AS (actual_qty - defect_qty)
```

```
Attainment % = total_good_qty / planned_qty × 100
Defect Rate  = total_defect_qty / total_actual_qty × 100
Yield Rate   = total_good_qty / total_actual_qty × 100
```

**Efficiency Status:**

| Attainment % | Status |
|-------------|--------|
| 0% | not_started |
| < OEE Target (85%) | below_target |
| ≥ OEE Target (85%) | on_target |
| > 100% | exceeded |

### 12.5 Plan Status Machine

```
Create → [draft]
           ↓ Schedule
       [scheduled]
           ↓ Start
       [in_progress] → [cancelled]  (immutable)
           ↓ Complete
       [completed]                  (immutable)
```

Plans in `completed` or `cancelled` state reject any `PUT` request with `403 Forbidden`.

### 12.6 Production Calendar Grid Logic

```
Rows    = All active machines
Columns = 7 days (Mon–Sun, current week)
Cells   = Each shift × each day

For each machine × day × shift:
  lookup plansMap["machineId:date:shiftId"]
  → If plan exists: show colored plan card
  → If no plan: show dashed "+ Shift Name" button
```

---

## 13. Work Orders

Work orders group production plans together for customer delivery tracking.

### What a Work Order Contains

- Customer reference
- Due date
- One or more production plans (from the Planning calendar)
- Status: draft → in_progress → completed / cancelled

### Work Order Flow

```
1. Production plans exist (status: scheduled or in_progress)
2. Create work order → select customer + due date + plans
3. Plans are linked to the work order (many-to-many or FK)
4. Track work order completion as plans complete
```

### API

```
GET    /api/v1/work-orders           List work orders
POST   /api/v1/work-orders           Create work order
GET    /api/v1/work-orders/{id}      Get work order with plans
PUT    /api/v1/work-orders/{id}      Update work order
DELETE /api/v1/work-orders/{id}      Delete work order
```

### Admin UI (`/admin/work-orders`)

- Table of all work orders with customer, due date, plan count, status
- **Schedule Production modal** — attach existing production plans to a work order
- **Add/Edit Work Order modal** — centered, max-w-lg, same size as Schedule Production modal

---

## 14. Background Jobs & Scheduler

### OEE Aggregation

Every 5 minutes the scheduler runs `iot:aggregate-oee`, which reads `iot_logs` and writes/updates rows in `machine_oee_shifts`.

```
iot_logs (millions of rows)
    ↓ every 5 min
machine_oee_shifts (≈150 rows/day for 50 machines × 3 shifts)
    ↓ instant
Dashboard OEE queries
```

### Running the Scheduler

```bash
# Keep this terminal open while the app is running
php artisan schedule:work

# Production cron (once per minute):
# * * * * * cd /path/to/smartfactory && php artisan schedule:run >> /dev/null 2>&1
```

### Manual Aggregation

```bash
php artisan iot:aggregate-oee                          # All factories, today
php artisan iot:aggregate-oee --factory=1              # Factory 1, today
php artisan iot:aggregate-oee --date=2026-03-12        # All factories, specific date
php artisan iot:aggregate-oee --factory=1 --date=2026-03-12
```

Logs to: `storage/logs/oee-aggregation.log`

---

## 15. Domain Architecture

The application follows **Domain-Driven Design (DDD)**. Business logic lives in `app/Domain/`, not in controllers.

```
app/Domain/
├── Shared/
│   ├── Enums/Permission.php     — 78 permission constants, groupedMatrix(), label()
│   ├── Enums/Role.php           — 6 roles with level(), isFactoryScoped(), defaultPermissions()
│   ├── Models/BaseModel.php     — Base Eloquent model ($guarded=['id'], casts: datetime)
│   ├── Scopes/FactoryScope.php  — Global scope; macros: withoutFactoryScope, forFactory
│   └── Traits/
│       ├── BelongsToFactory.php — Adds factory() + auto-sets factory_id on create
│       └── HasFactoryScope.php  — Boots FactoryScope automatically
│
├── Factory/                     — Tenant management
├── Machine/                     — Machine model, IotLog model, Redis device token cache
├── Production/                  — Customer, Part, Plan, Actual, Shift, Process
├── Analytics/                   — OeeCalculationService, OeeAggregationService, OeeResult VO
└── Auth/                        — Permission service (RBAC utilities)
```

### Repository Pattern

| Interface | Implementation |
|-----------|---------------|
| FactoryRepositoryInterface | EloquentFactoryRepository |
| MachineRepositoryInterface | EloquentMachineRepository |
| PartRepositoryInterface | EloquentPartRepository |
| CustomerRepositoryInterface | EloquentCustomerRepository |
| ProcessMasterRepositoryInterface | EloquentProcessMasterRepository |

All bindings: `app/Providers/RepositoryServiceProvider.php`

### Service Layer Business Guards

Each service enforces business rules via `DomainException` → caught by controller → `409 Conflict`:

| Service | Guard |
|---------|-------|
| FactoryService | Cannot deactivate factory with active machines |
| MachineService | Cannot retire machine with open downtimes |
| CustomerService | Cannot deactivate customer with active parts |
| PartService | Cannot discontinue part with active production plans |
| PartService::syncRouting | Cannot change routing if any plan is in_progress |

### Spatie Permission Teams — Critical Notes

- Teams enabled, `team_foreign_key = team_id`
- Super-admin stored with `team_id = 0` (NOT null — MariaDB PRIMARY KEY rejects NULL)
- Factory roles stored with `team_id = factory_id`
- All roles/permissions seeded with `guard_name = 'sanctum'`
- After any `hasRole()` call with `team_id = 0`, must call `$user->unsetRelation('roles')->unsetRelation('permissions')` to clear stale cache before factory-scoped checks
- `User::$guard_name = 'sanctum'` — tells Spatie to use sanctum guard for permission checks even on web routes

---

## 16. Server Commands Reference

> All commands run from: `d:/xampp/htdocs/smartfactory`

### Application Setup

| Command | Purpose |
|---------|---------|
| `composer install` | Install PHP dependencies |
| `php artisan key:generate` | Generate application encryption key |
| `php artisan migrate` | Run all pending database migrations |
| `php artisan migrate:fresh` | Drop all tables and re-run all migrations |
| `php artisan db:seed --class=DemoSeeder` | Seed demo factory, users, machines, parts |
| `php artisan db:seed --class=IotDemoDataSeeder` | Seed realistic IoT telemetry (machine 1, 2 shifts) |
| `php artisan migrate:fresh --seed --seeder=DemoSeeder` | Full fresh database with demo data |

### Development Server

| Command | Purpose |
|---------|---------|
| `php artisan serve --port=8000` | Start development server |
| `php artisan serve --host=0.0.0.0 --port=8000` | Accessible on local network |

### Cache Management

| Command | Purpose |
|---------|---------|
| `php artisan route:clear` | Clear compiled route cache |
| `php artisan view:clear` | Clear compiled Blade view cache |
| `php artisan config:clear` | Clear configuration cache |
| `php artisan cache:clear` | Clear application cache |
| `php artisan optimize:clear` | Clear all caches at once (recommended after code changes) |

> Run `php artisan optimize:clear` after modifying routes, Blade views, or config.

### IoT & OEE

| Command | Purpose |
|---------|---------|
| `php artisan iot:aggregate-oee` | Aggregate OEE for all factories (today) |
| `php artisan iot:aggregate-oee --factory=1` | Aggregate for factory ID 1 |
| `php artisan iot:aggregate-oee --date=2026-03-12` | Aggregate for a specific date |
| `php artisan schedule:work` | Run scheduler loop (keep terminal open) |

### Debugging & Inspection

| Command | Purpose |
|---------|---------|
| `php artisan route:list` | List all registered routes |
| `php artisan route:list --path=admin` | List admin routes only |
| `php artisan route:list --path=api` | List API routes only |
| `php artisan tinker` | Open Laravel REPL |
| `php artisan about` | Display framework information |

---

## 17. Production Server Operations — OEE Scheduler & MySQL Data Management

This section covers everything you need to keep the server running reliably in production:
- OEE aggregation scheduler (cron setup)
- IoT log data freeze / archival (the `iot_logs` table grows at millions of rows/day)
- MySQL backup schedule
- Maintenance commands with exact timings

---

### 17.1 Why These Jobs Are Needed

| Problem | Solution |
|---------|---------|
| `iot_logs` grows at up to 4.3 million rows/day (50 machines × 1 rec/sec) | Archive rows older than N days after aggregating to `machine_oee_shifts` |
| OEE dashboard needs fast queries | Pre-aggregate to `machine_oee_shifts` every 5 minutes |
| Data loss risk | Daily MySQL dump backup |
| Disk space exhaustion | Weekly cleanup of old backups and archived logs |

---

### 17.2 OEE Aggregation — Production Cron Setup

#### What it does
Reads raw `iot_logs` rows for all machines/shifts for today, calculates OEE components, and writes/updates one summary row per machine per shift into `machine_oee_shifts`. This makes the dashboard fast regardless of how many raw log rows exist.

#### Development (XAMPP — keep terminal open)
```bash
# Run in a dedicated terminal — keep it open
cd d:/xampp/htdocs/smartfactory
php artisan schedule:work
```

#### Linux Production Server (crontab)

```bash
# Open crontab editor
crontab -e

# Add this ONE line — Laravel schedule:run fires every minute
# Laravel's own scheduler (routes/console.php) then decides what to run when
* * * * * cd /var/www/smartfactory && php artisan schedule:run >> /dev/null 2>&1
```

#### What the Laravel Scheduler Runs Automatically

Defined in `routes/console.php`:

| Command | When | Overlap Guard |
|---------|------|--------------|
| `iot:aggregate-oee` | Every 5 minutes | Yes — skips if previous run still active |

#### Verify the scheduler is firing

```bash
# Tail the OEE aggregation log in real time
tail -f /var/www/smartfactory/storage/logs/oee-aggregation.log

# Expected output every 5 minutes:
# [2026-03-12 08:05:00] OEE aggregation started
# [2026-03-12 08:05:01] Factory 1 — 6 shifts processed, 6 rows upserted
# [2026-03-12 08:05:01] OEE aggregation finished (1.2s)
```

#### Manual OEE Run (run anytime, safe to repeat)

```bash
# Aggregate today for all factories
php artisan iot:aggregate-oee

# Aggregate a specific past date (e.g., to backfill after downtime)
php artisan iot:aggregate-oee --date=2026-03-11

# Aggregate a specific factory only
php artisan iot:aggregate-oee --factory=1

# Aggregate specific factory + specific date
php artisan iot:aggregate-oee --factory=1 --date=2026-03-11
```

> **Safe to run multiple times.** Uses `UPSERT` (`updateOrCreate`) — re-running overwrites the existing row, never duplicates.

---

### 17.3 IoT Log Data Freeze / Archival

#### The Problem

`iot_logs` is an **append-only** telemetry table. At 50 machines × 1 record/second:

```
Per machine per day  =  86,400 rows
50 machines per day  =  4,320,000 rows
Per month (50 mach.) =  ~130,000,000 rows
```

Once data is aggregated into `machine_oee_shifts`, the raw rows in `iot_logs` are no longer needed for the dashboard. They are only needed for:
- CSV export of raw logs (detail view)
- Historical OEE live recalculation (`?live=1`)

#### Recommended Retention Policy

| Data Age | Action |
|----------|--------|
| 0 – 30 days | Keep raw `iot_logs` intact |
| 31 – 90 days | Keep if disk allows; otherwise archive to CSV/cold storage |
| > 90 days | Delete from `iot_logs` (OEE already in `machine_oee_shifts`) |

#### Step 1 — Ensure OEE is Aggregated Before Deleting

Always aggregate before deleting raw logs. This prevents data loss:

```bash
# Aggregate the date range you are about to delete
php artisan iot:aggregate-oee --date=2026-01-01
php artisan iot:aggregate-oee --date=2026-01-02
# ... or run for a range using a shell loop:

for DATE in $(seq 0 30 | xargs -I{} date -d "2026-01-01 + {} days" +%Y-%m-%d 2>/dev/null); do
    php artisan iot:aggregate-oee --date=$DATE
done
```

#### Step 2 — Archive Raw Logs to CSV (Optional, Before Delete)

Export raw logs per machine per date before deleting — useful for audit trails:

```bash
# Via the API (authenticated):
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://yourserver/api/v1/iot/machines/1/export?date=2026-01-01" \
  -o /backup/iot/machine1_2026-01-01.csv

# Or directly via MySQL:
mysql -u root -p smartfactory -e "
SELECT machine_id, alarm_code, auto_mode, cycle_state,
       part_count, part_reject, slave_id, slave_name, logged_at
FROM iot_logs
WHERE logged_at < '2026-02-01 00:00:00'
INTO OUTFILE '/tmp/iot_logs_archive_jan2026.csv'
FIELDS TERMINATED BY ','
ENCLOSED BY '\"'
LINES TERMINATED BY '\n';
"
```

#### Step 3 — Delete Old Raw Logs (Data Freeze / Prune)

```bash
# SAFE: delete iot_logs older than 30 days
# Run this monthly via cron — always AFTER OEE aggregation

mysql -u root -p smartfactory -e "
DELETE FROM iot_logs
WHERE logged_at < NOW() - INTERVAL 30 DAY
LIMIT 500000;
"
```

> **LIMIT 500000** prevents the DELETE from locking the table for too long. Run it in batches during off-peak hours if the table is very large.

#### Batch Delete Script (safe for large tables)

```bash
#!/bin/bash
# /var/www/smartfactory/scripts/prune_iot_logs.sh
# Deletes iot_logs older than 30 days in batches of 100,000 rows

CUTOFF=$(date -d "30 days ago" '+%Y-%m-%d %H:%M:%S')
BATCH=100000
DELETED=1

echo "$(date) - Starting iot_logs pruning (cutoff: $CUTOFF)"

while [ $DELETED -gt 0 ]; do
    DELETED=$(mysql -u root -pYOUR_PASSWORD smartfactory -sse "
        DELETE FROM iot_logs
        WHERE logged_at < '$CUTOFF'
        LIMIT $BATCH;
        SELECT ROW_COUNT();
    ")
    echo "$(date) - Deleted batch: $DELETED rows"
    sleep 2  # pause between batches to reduce lock pressure
done

echo "$(date) - Pruning complete"
```

Make executable and test:
```bash
chmod +x /var/www/smartfactory/scripts/prune_iot_logs.sh
bash /var/www/smartfactory/scripts/prune_iot_logs.sh
```

---

### 17.4 MySQL Backup Schedule

#### Full Database Backup (Daily)

```bash
# /var/www/smartfactory/scripts/backup_db.sh

#!/bin/bash
DATE=$(date +%Y-%m-%d)
BACKUP_DIR=/backup/smartfactory/mysql
mkdir -p $BACKUP_DIR

mysqldump -u root -pYOUR_PASSWORD \
  --single-transaction \
  --routines \
  --triggers \
  --databases smartfactory \
  | gzip > $BACKUP_DIR/smartfactory_$DATE.sql.gz

echo "$(date) - Backup saved: $BACKUP_DIR/smartfactory_$DATE.sql.gz"

# Delete backups older than 30 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete
echo "$(date) - Old backups cleaned"
```

> `--single-transaction` takes a consistent snapshot without locking tables — safe for live production.

#### machine_oee_shifts Only Backup (Lightweight)

If `iot_logs` is very large, back up only the important aggregated table separately:

```bash
mysqldump -u root -pYOUR_PASSWORD smartfactory machine_oee_shifts \
  | gzip > /backup/smartfactory/oee_shifts_$(date +%Y-%m-%d).sql.gz
```

---

### 17.5 Complete Crontab — All Jobs Together

```bash
crontab -e
```

Paste the following (adjust paths and passwords):

```cron
# ─────────────────────────────────────────────────────
# SmartFactory Production Crontab
# ─────────────────────────────────────────────────────

# 1. Laravel Scheduler — drives ALL scheduled commands (including OEE every 5 min)
#    MUST run every minute — Laravel decides internally what to execute
* * * * * cd /var/www/smartfactory && php artisan schedule:run >> /dev/null 2>&1

# 2. MySQL Full Backup — runs every day at 02:00 AM
0 2 * * * /var/www/smartfactory/scripts/backup_db.sh >> /var/log/smartfactory/backup.log 2>&1

# 3. IoT Log Prune — runs every Sunday at 03:00 AM (after backup)
#    Deletes iot_logs older than 30 days in safe batches
0 3 * * 0 /var/www/smartfactory/scripts/prune_iot_logs.sh >> /var/log/smartfactory/prune.log 2>&1

# 4. OEE Backfill for yesterday — runs every day at 01:00 AM
#    Catches any missed 5-min aggregations from the previous day
0 1 * * * cd /var/www/smartfactory && php artisan iot:aggregate-oee --date=$(date -d yesterday +%Y-%m-%d) >> /var/log/smartfactory/oee.log 2>&1

# 5. Laravel log cleanup — delete logs older than 7 days, runs every Monday at 04:00 AM
0 4 * * 1 find /var/www/smartfactory/storage/logs -name "*.log" -mtime +7 -delete
```

---

### 17.6 When to Run Each Command — Reference Table

| Command | When to Run | How Often | Notes |
|---------|------------|-----------|-------|
| `php artisan schedule:run` | Every minute (cron) | Always — never stop | Drives the OEE 5-min aggregation automatically |
| `php artisan iot:aggregate-oee` | Any time, manually | On demand | Safe to run multiple times — idempotent |
| `php artisan iot:aggregate-oee --date=X` | After server downtime | Once per missed date | Backfills missed aggregations |
| `backup_db.sh` | Daily at 02:00 AM | Daily | Must run before any pruning |
| `prune_iot_logs.sh` | Weekly at 03:00 AM | Weekly | Always run AFTER backup AND AFTER OEE aggregation |
| `php artisan cache:clear` | After code deploy | On deploy | Clears IoT status cache (30s TTL) and view cache |
| `php artisan optimize:clear` | After code deploy | On deploy | Clears routes, views, config, cache all at once |
| `php artisan view:clear` | After Blade changes | On deploy | Forces Blade recompile |
| `php artisan route:clear` | After route changes | On deploy | Forces route recompile |

---

### 17.7 Monitoring — Check System Health

#### Check OEE aggregation is running

```bash
# How many OEE rows were written today?
mysql -u root -pYOUR_PASSWORD smartfactory -e "
SELECT
    oee_date,
    COUNT(*) AS machines_aggregated,
    SUM(total_parts) AS total_parts,
    ROUND(AVG(oee_pct), 1) AS avg_oee_pct
FROM machine_oee_shifts
WHERE oee_date = CURDATE()
GROUP BY oee_date;
"
```

#### Check iot_logs table size

```bash
mysql -u root -pYOUR_PASSWORD smartfactory -e "
SELECT
    table_name,
    table_rows AS approx_rows,
    ROUND((data_length + index_length) / 1024 / 1024, 1) AS size_mb,
    ROUND(data_free / 1024 / 1024, 1) AS free_mb
FROM information_schema.tables
WHERE table_schema = 'smartfactory'
ORDER BY (data_length + index_length) DESC;
"
```

#### Check oldest iot_logs row (data age)

```bash
mysql -u root -pYOUR_PASSWORD smartfactory -e "
SELECT
    MIN(logged_at) AS oldest_log,
    MAX(logged_at) AS newest_log,
    COUNT(*) AS total_rows,
    DATEDIFF(MAX(logged_at), MIN(logged_at)) AS data_span_days
FROM iot_logs;
"
```

#### Check for machines that stopped sending data

```bash
mysql -u root -pYOUR_PASSWORD smartfactory -e "
SELECT
    m.name AS machine,
    m.code,
    MAX(l.logged_at) AS last_log,
    TIMESTAMPDIFF(MINUTE, MAX(l.logged_at), NOW()) AS minutes_since_last_log
FROM machines m
LEFT JOIN iot_logs l ON l.machine_id = m.id
WHERE m.status = 'active'
GROUP BY m.id, m.name, m.code
HAVING minutes_since_last_log > 15 OR last_log IS NULL
ORDER BY minutes_since_last_log DESC;
"
```

---

### 17.8 Production Checklist After Server Start / Restart

Run these in order after any server restart or code deployment:

```bash
# 1. Clear all caches
php artisan optimize:clear

# 2. Run any pending migrations (safe — skips already-run migrations)
php artisan migrate

# 3. Backfill OEE for today (in case the server was down)
php artisan iot:aggregate-oee --date=$(date +%Y-%m-%d)

# 4. Backfill OEE for yesterday (in case downtime spanned midnight)
php artisan iot:aggregate-oee --date=$(date -d yesterday +%Y-%m-%d)

# 5. Verify crontab is loaded
crontab -l

# 6. Start the scheduler (dev only — production uses cron)
# php artisan schedule:work
```

---

### 17.9 Disk Space Planning

Estimates based on 50 machines, 1 record/minute (60 bytes per row):

| Retention | Raw iot_logs Rows | Approx Size | machine_oee_shifts Rows |
|-----------|-----------------|-------------|------------------------|
| 7 days | ~21M rows | ~1.3 GB | ~1,050 rows |
| 30 days | ~90M rows | ~5.5 GB | ~4,500 rows |
| 90 days | ~270M rows | ~16 GB | ~13,500 rows |
| 1 year | ~1.08B rows | ~65 GB | ~54,750 rows |

> **`machine_oee_shifts` is tiny** regardless of how long you keep it — 54,750 rows for a full year of 50 machines. The OEE dashboard always queries this table, so deleting `iot_logs` does not affect OEE charts.

**Recommended minimum disk:** 20 GB for `iot_logs` + 5 GB for MySQL binlog + 2 GB for backups = **27 GB** total for a 30-day retention policy.

---

*End of SmartFactory System Documentation v2.1*
