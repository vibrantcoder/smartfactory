# SmartFactory — System Documentation

> Version: 1.0 | Platform: Laravel 11 + MariaDB | Architecture: DDD + Multi-Tenant

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
10. [OEE Calculation Engine](#10-oee-calculation-engine)
11. [Production Planning — Complete Process & Output](#11-production-planning--complete-process--output)
12. [Background Jobs & Scheduler](#12-background-jobs--scheduler)
13. [Domain Architecture](#13-domain-architecture)
14. [Server Commands Reference](#14-server-commands-reference)

---

## 1. System Overview

SmartFactory is a **multi-tenant Industry 4.0 manufacturing platform** that connects IoT machines to a production management system. Each company is a **Factory** (tenant). Users, machines, production plans, and data are isolated per factory.

### What the System Does

| Area | Capability |
|------|-----------|
| IoT | Receives 5-second pulse data from CNC machines / PLCs via REST API |
| OEE | Calculates Overall Equipment Effectiveness (Availability × Performance × Quality) in real time |
| Production Planning | Calendar-based weekly plan with shift slots per machine |
| Downtime | Records, classifies, and reports machine stoppages |
| Employees | Operator portal showing assigned machine jobs |
| Administration | Full CRUD for users, roles, permissions, machines, parts, customers |
| Analytics | OEE trend charts, production vs. target comparisons |

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
│  Pulse every 5 seconds per machine                  │
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
| Charts | Chart.js (IoT dashboard) |
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

# 8. Start development server
php artisan serve --port=8000
```

> **NOTE:** If using XAMPP Apache (port 80), skip step 8. Access at http://localhost/smartfactory/public.
> The development server at http://127.0.0.1:8000 is recommended for development.

### Fresh Reset (wipe everything + re-seed)

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
```

> **NOTE:** This destroys ALL data. Use only in development.

### Demo Login Credentials

| Portal | URL | Email | Password | Role |
|--------|-----|-------|----------|------|
| Admin | /login | super@demo.local | password | Super Admin |
| Admin | /login | admin@demo.local | password | Factory Admin |
| Employee | /employee/login | operator@demo.local | password | Operator |

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
```

### IoT & Analytics Tables

```
iot_logs                    — Raw pulse data (one row per 5-second tick per machine)
  Fields: machine_id, alarm_code, auto_mode, cycle_state,
          part_count (pulse 0/1), part_reject (pulse 0/1),
          slave_id, slave_name, logged_at

machine_oee_shifts          — Aggregated OEE per machine per shift per day
  (UNIQUE: machine_id + shift_id + oee_date)
  Replaces scanning millions of iot_logs rows for dashboard queries
```

### RBAC Tables (Spatie)

```
roles                       — Role definitions
permissions                 — Permission definitions (78 total)
model_has_roles             — User ↔ Role assignments (with team_id = factory_id)
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

78 permissions are grouped into 11 categories:

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

Every model except `Factory` and `ProcessMaster` has a **global scope** that automatically filters by `factory_id`. This means:

```php
// This query automatically adds WHERE factory_id = {current factory}
Machine::where('status', 'active')->get();

// Bypass if needed (e.g., Super Admin)
Machine::withoutFactoryScope()->get();
```

The current factory scope is set by the `factory.scope` middleware on every request based on the authenticated user's `factory_id`.

---

## 6. Admin Panel Modules

Access all modules at: `http://127.0.0.1:8000/admin`

### 6.1 Dashboard (`/admin/dashboard`)

Real-time overview page that polls every 15 seconds.

**Shows:**
- Machine count (active/total)
- Production plans today (pending/in progress/completed)
- Open downtimes
- Active users

**How it works:** Alpine.js `setInterval` calls the API every 15 seconds and updates the counts without page reload.

---

### 6.2 IoT Dashboard (`/admin/iot`)

Industry 4.0 real-time machine monitoring panel.

**Machine Grid** — Cards for every active machine showing:
- Status badge: RUNNING / IDLE / ALARM / OFFLINE
- Current cycle state and auto mode
- Part count for the day
- Last data timestamp

**Click a machine card** to open the detail panel:
- **OEE table** — Availability, Performance, Quality percentages per shift
- **Timeline chart** — Hourly activity visualization (Chart.js)
- **Line chart** — Part count trend over 24 hours

**OEE Date Picker** — Select any date to view historical OEE.

**CSV Export** — Download raw IoT log data for a machine.

**How it works:**
- Machine grid: `GET /api/v1/iot/status` (called every 30 seconds)
- OEE table: `GET /api/v1/iot/oee?date=YYYY-MM-DD&factory_id=N`
- Charts: `GET /api/v1/iot/machines/{id}/chart?hours=24`
- Timeline: `GET /api/v1/iot/machines/{id}/timeline?date=YYYY-MM-DD`

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
- Gray = Draft
- Blue = Scheduled
- Amber = In Progress
- Green = Completed
- Red = Cancelled

**Creating a plan:** Click any `[+ Shift Name]` empty slot → modal opens pre-filled with machine, date, and shift → select part and quantity → Save.

**Editing a plan:** Click any colored plan card → edit modal → change status, quantity, notes → Save.

**Navigation:** Prev/Next week buttons + Today shortcut. Super Admin sees a factory selector dropdown.

**How it works:**
- Grid data: `GET /api/v1/production-plans?from_date=X&to_date=Y&per_page=500`
- Create: `POST /api/v1/production-plans`
- Edit: `PUT /api/v1/production-plans/{id}`
- Delete: `DELETE /api/v1/production-plans/{id}`

---

### 6.4 Downtime Management (`/admin/downtimes`)

Lists all downtime events for the factory.

**Columns:** Machine, Start time, End time, Duration, Reason, Status (open/closed), Classification.

**Actions:** Filter by machine/date range, create new downtime record, close open downtimes, classify with reason codes.

**How it works:** Alpine.js calls `GET /api/v1/downtimes` with filter parameters. Create/update via `POST /api/v1/downtimes` and `PUT /api/v1/downtimes/{id}`.

---

### 6.5 User Management (`/admin/users`)

Manage all factory users.

**Table columns:** Name, Email, Role badge, Status (Active/Inactive), Actions.

**Actions per user:**
- **Edit** (pencil icon) — Update name, email, password, active status
- **Assign Machine** (amber, operator/viewer only) — Link operator to a specific machine so they can use the employee portal
- **Permissions** (blue) — Opens the permission drawer
- **Revoke** (red) — Removes all roles from the user

**Permission Drawer** (right-side panel):

The drawer has three sections:

1. **Assign Role** — Radio buttons showing all roles the logged-in user can assign. Select a role and save to change the user's role.

2. **Machine Assignment** (only shown for Operator/Viewer roles) — Dropdown of all factory machines. Select to link the user to a machine.

3. **Permission Matrix** — 11 permission groups with checkboxes:
   - **Gray checkboxes** (checked) = permissions inherited from role (cannot uncheck here)
   - **Violet checkboxes** = direct permissions granted on top of the role
   - Check/uncheck violet boxes to add or remove direct permissions

**Save flow:** Role change → Machine assignment → Direct permissions sync (three sequential API calls).

**Factory Selector** (Super Admin only) — Dropdown to switch between factories and manage users across all tenants.

---

### 6.6 Roles Management (`/admin/roles`)

> Accessible only to **Factory Admin** and above. Create/Delete require **Super Admin**.

**Role table:** Shows all roles with label, description, hierarchy level, scope (Factory/Global), and permission count.

**Edit Permissions button** → Opens the permission drawer for any role:
- Permission matrix with 11 groups and 78 checkboxes
- "Select All / Deselect All" per group and globally
- Live counter (e.g., "42 of 78 assigned")
- Save button (only enabled when changes are made)

**New Role button** (Super Admin only) → Modal to create a custom role:
- Slug format: lowercase, letters, digits, hyphens (e.g., `quality-inspector`)
- Custom roles start with 0 permissions; add them via Edit Permissions

**Delete button** (Super Admin only, custom roles only) → Two-click confirm to prevent accidents. Blocked if users are currently assigned to the role.

> **NOTE:** System roles (super-admin, factory-admin, production-manager, supervisor, operator, viewer) **cannot be deleted**.

---

### 6.7 Machines (`/admin/machines`)

Machine directory for the factory.

**Columns:** Machine code, Name, Type, Status (active/retired), Device token status.

**Actions:** Create machine, edit details, retire machine (guarded — cannot retire with open downtimes).

**How it works:** Alpine.js CRUD against `GET|POST|PUT|DELETE /api/v1/machines`.

---

### 6.8 Customers (`/admin/customers`)

Customer company management.

**Actions:** Create, edit, deactivate (guarded — cannot deactivate if active parts exist).

**How it works:** CRUD against `/api/v1/customers`.

---

### 6.9 Parts (`/admin/parts`)

Product part master.

**Columns:** Part number, Name, Customer, Cycle time standard, Status.

**Actions:** Create, edit, discontinue (guarded — cannot discontinue with active production plans), define routing.

**Routing Builder** (`/admin/parts/{part}/routing`) — Drag-and-drop process sequence:
- Add steps from the Process Master library
- Set override cycle time per step (or inherit from process master)
- Reorder steps

**How it works:**
- Parts: CRUD against `/api/v1/parts`
- Routing: `PUT /api/v1/parts/{part}/processes`

---

### 6.10 Process Masters (`/admin/process-masters`)

Global library of manufacturing processes (e.g., CNC Turning, Welding, Painting).

These are **factory-independent** reference records used when building part routing.

**Actions:** Create, edit, deactivate.

---

### 6.11 Employees (`/admin/employees`)

Directory of all operator and viewer role users in the factory.

Shows machine assignment status. Used as a quick view — full permission editing is done from the Users page.

---

## 7. Employee Portal

Access at: `http://127.0.0.1:8000/employee/login`

Designed for **shop-floor operators** using tablets or workstation PCs.

### 7.1 Login (`/employee/login`)

Same credentials as admin portal. After login, the system redirects:
- Operator with a machine assigned → `/employee/dashboard`
- Operator with **no machine** assigned → `/employee/no-machine` (shows a message to contact admin)
- Admin/manager roles → redirected to admin panel

### 7.2 Dashboard (`/employee/dashboard`)

Shows the operator's assigned machine and current/upcoming jobs.

**Displays:**
- Machine name and status
- Today's production plans for the machine
- Active shift

### 7.3 Jobs (`/employee/jobs`)

List of all production plans for the operator's machine:
- **Past 7 days** — Completed and in-progress jobs
- **Today** — Current jobs highlighted
- **Next 14 days** — Upcoming scheduled jobs

**Columns:** Date, Shift, Part number + name, Planned quantity, Status badge.

> **NOTE:** Employees cannot create or edit plans. They can only view them.

---

## 8. REST API Reference

**Base URL:** `http://127.0.0.1:8000/api`
**Authentication:** Bearer token (`Authorization: Bearer {token}`)

To get a token:
```bash
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "admin@demo.local", "password": "password" }
```

Response: `{ "token": "...", "user": {...} }`

---

### 8.1 Public Endpoints (No Auth)

| Method | Endpoint | Description |
|--------|---------|-------------|
| POST | /iot/ingest | Single IoT pulse from a machine |
| POST | /iot/ingest/batch | Batch IoT pulses (up to 500 records) |
| POST | /v1/auth/login | Get API token |
| POST | /v1/auth/logout | Revoke API token |
| GET | /v1/auth/me | Current user info |

---

### 8.2 Protected API Endpoints

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

#### Machines
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/machines | List machines (`?status=active`, `?factory_id=N`) |
| POST | /v1/machines | Create machine |
| GET | /v1/machines/{id} | Get machine |
| PUT | /v1/machines/{id} | Update machine |
| DELETE | /v1/machines/{id} | Retire machine |

#### Production Plans
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/production-plans | List plans (`?from_date=`, `?to_date=`, `?machine_id=`, `?status=`) |
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
Plans in `completed` or `cancelled` state are **immutable** (cannot be edited).

#### Production Actuals
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/production-actuals | List actuals |
| POST | /v1/production-actuals | Record actual |
| PUT | /v1/production-actuals/{id} | Update actual |

> **NOTE:** The `good_qty` field is auto-calculated by MySQL as `total_qty - reject_qty`. Never set it directly.

#### Downtimes
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/downtimes | List downtimes |
| POST | /v1/downtimes | Create downtime event |
| PUT | /v1/downtimes/{id} | Update (close, classify) |
| GET | /v1/downtime-reasons | List reason codes |
| POST | /v1/downtime-reasons | Create reason code |

#### Customers, Parts, Process Masters
Standard CRUD following the same pattern:
- `GET /v1/{resource}` — list
- `POST /v1/{resource}` — create
- `GET /v1/{resource}/{id}` — read
- `PUT /v1/{resource}/{id}` — update
- `DELETE /v1/{resource}/{id}` — deactivate/discontinue

#### IoT & OEE
| Method | Endpoint | Description |
|--------|---------|-------------|
| GET | /v1/iot/status | Latest status snapshot per machine |
| GET | /v1/iot/machines/{id}/chart | Hourly chart data (`?hours=24`) |
| GET | /v1/iot/machines/{id}/timeline | Timeline for a date |
| GET | /v1/iot/machines/{id}/export | CSV download of raw logs |
| GET | /v1/iot/oee | Factory-wide OEE (`?date=YYYY-MM-DD&factory_id=N`) |
| GET | /v1/iot/machines/{id}/oee | Single machine OEE (`?date=&shift_id=`) |
| GET | /v1/iot/oee/trend | 30-day OEE trend |
| GET | /v1/shifts | List shifts for current factory |

---

## 9. IoT Integration

### How a Machine Sends Data

Each machine is configured with its **device token** (shown in the Machines admin page). The device sends a POST request every 5 seconds:

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
  "logged_at":     "2026-03-05 08:00:05"
}
```

**Field explanations:**

| Field | Type | Description |
|-------|------|-------------|
| device_token | string | Machine authentication secret |
| alarm_code | int | 0 = OK, >0 = alarm condition |
| auto_mode | int | 1 = running automatically, 0 = manual/stopped |
| cycle_state | int | 1 = mid-cycle, 0 = cycle complete |
| part_count | int (0/1) | Pulse — 1 when a part is completed this tick |
| part_reject | int (0/1) | Pulse — 1 when a reject is detected this tick |
| slave_id | int | Sub-device ID on the machine bus |
| slave_name | string | Sub-device label |
| logged_at | datetime | Timestamp from the device |

> **NOTE:** `part_count` and `part_reject` are **pulses (0 or 1)**, not running totals. SUM of pulses over a period = total parts produced/rejected.

### Batch Ingest (Recommended for High-Frequency)

For 50+ machines at 1 record/second, use batch:

```bash
POST http://127.0.0.1:8000/api/iot/ingest/batch
Content-Type: application/json

[
  { "device_token": "abc", "alarm_code": 0, "part_count": 1, ... },
  { "device_token": "abc", "alarm_code": 0, "part_count": 0, ... },
  ...up to 500 records...
]
```

This performs a single `INSERT` for all records — much faster than N individual requests.

### Machine Status Derivation

The API derives machine status from the most recent log:

| Condition | Status |
|-----------|--------|
| alarm_code > 0 | ALARM |
| auto_mode = 1 AND cycle_state = 1 | RUNNING |
| auto_mode = 1 AND cycle_state = 0 | IDLE |
| No data in last 10 minutes | OFFLINE |

---

## 10. OEE Calculation Engine

**OEE (Overall Equipment Effectiveness)** = Availability × Performance × Quality

All values are percentages (0–100). OEE result is 0–100.

### Availability

```
Availability = (Planned Minutes - Alarm Minutes) / Planned Minutes × 100

Where:
  Planned Minutes = shift.duration_min (from shift definition)
  Alarm Minutes   = COUNT(records where alarm_code > 0) × log_interval_sec / 60
```

### Performance

```
Performance = (Total Parts Produced × Cycle Time Standard in seconds)
              ÷ Available Seconds × 100

Where:
  Total Parts Produced = SUM(part_count) from iot_logs in shift window
  Cycle Time Standard  = part.cycle_time_std (from the active production plan's part)
  Available Seconds    = (Planned Minutes - Alarm Minutes) × 60
```

> **NOTE:** Performance is `NULL` if no production plan exists for the machine/shift/date. A plan with a part that has `cycle_time_std` set is required.

### Quality

```
Quality = Good Parts / Total Parts × 100

Where:
  Good Parts   = Total Parts - Rejected Parts
  Total Parts  = SUM(part_count) from iot_logs
  Rejected Parts = SUM(part_reject) from iot_logs
```

### Data Source Strategy

The OEE API endpoint uses a two-tier lookup:

1. **Fast path** — Query the `machine_oee_shifts` summary table (pre-aggregated, tiny result set)
2. **Live path** — If no summary row exists, calculate live from `iot_logs` (expensive but accurate)
3. Add `?live=1` to force live recalculation even if a summary exists

The response includes `"source": "cache"` or `"source": "live"` to indicate which path was used.

---

## 11. Production Planning — Complete Process & Output

This section explains every step of the production planning lifecycle — from prerequisites through plan creation, IoT data collection, actual recording, and final analysis output.

---

### 11.1 Prerequisites (Must Exist Before Planning)

Before any production plan can be created, the following master data must be set up:

```
Factory
  └── Shifts (Morning, Afternoon, Night — each with start_time, end_time, duration_min)
  └── Machines (CNC Lathe, Welder, etc. — must be status=active)
  └── Factory Settings (oee_target_pct, working_hours_per_day)

Customers → Parts (with part_number, cycle_time_std)
  └── Part Processes (routing steps linked to Process Masters)
```

**Setup order:**
1. Create Factory + Factory Settings
2. Create Shifts (define shift windows and durations)
3. Create Machines (get device token for IoT)
4. Create Process Masters (global process library — e.g., "CNC Turning", "Deburring")
5. Create Customers
6. Create Parts → Define routing (which processes, what cycle time per step)

---

### 11.2 End-to-End Process Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│  STEP 1 — PLANNER CREATES PLAN                                          │
│                                                                         │
│  Admin → /admin/production/plans → Click empty shift slot →             │
│  Modal: Machine + Shift + Date + Part + Planned Qty → [Create Plan]    │
│                                                                         │
│  API call: POST /api/v1/production-plans                                │
│  DB write: production_plans (status = draft)                            │
└─────────────────────────┬───────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  STEP 2 — PLAN APPROVAL / STATUS CHANGE                                 │
│                                                                         │
│  Planner clicks plan card → Edit modal → Change status to "Scheduled"   │
│                                                                         │
│  API call: PUT /api/v1/production-plans/{id}  { "status": "scheduled" } │
│  DB write: production_plans.status = scheduled                          │
└─────────────────────────┬───────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  STEP 3 — OPERATOR SEES JOB (Employee Portal)                           │
│                                                                         │
│  operator@demo.local logs into /employee/jobs                           │
│  Sees the scheduled plan for their assigned machine                     │
│  (Last 7 days + Today + Next 14 days)                                   │
│                                                                         │
│  Operator marks it "In Progress" → supervisor changes status            │
└─────────────────────────┬───────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  STEP 4 — IOT DATA FLOWS IN (Parallel, automatic)                       │
│                                                                         │
│  Machine PLC sends pulse every 5 seconds:                               │
│  POST /api/iot/ingest  { part_count: 1, alarm_code: 0, ... }           │
│                                                                         │
│  DB write: iot_logs (one row per pulse per machine)                     │
│  Scheduler (every 5 min): aggregates into machine_oee_shifts            │
└─────────────────────────┬───────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  STEP 5 — RECORD PRODUCTION ACTUALS                                     │
│                                                                         │
│  Supervisor/Operator records batch output:                              │
│  POST /api/v1/production-actuals                                        │
│  { plan_id: 42, actual_qty: 80, defect_qty: 5 }                        │
│                                                                         │
│  DB write: production_actuals                                           │
│  good_qty = actual_qty - defect_qty  (MySQL GENERATED ALWAYS AS column) │
└─────────────────────────┬───────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  STEP 6 — PLAN ANALYSIS (Output)                                        │
│                                                                         │
│  GET /api/v1/production-plans/{id}/analysis                             │
│  GET /api/v1/factories/{id}/daily-targets?date=2026-03-05               │
│  GET /api/v1/iot/oee?date=2026-03-05&factory_id=1                      │
│                                                                         │
│  System calculates: Shift Target, Attainment %, OEE, Remaining Qty     │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 11.3 Cycle Time Calculation (How Targets Are Computed)

Every production target is derived from the **Part's cycle time** and the **Shift's duration**.

#### Step 1 — Part Routing Cycle Time

Each part has a routing: an ordered list of manufacturing processes.

```
Part: BKT-A001 (Bracket A)
  Step 1: CNC Turning    → 8.0 min  (from process master, no override)
  Step 2: Deburring      → 2.5 min  (part-level override: 2.5, master was 3.0)
  Step 3: Quality Check  → 2.0 min  (from process master)
  ─────────────────────────────────
  Total Cycle Time       = 12.5 min/unit
```

**Rule:** Each step uses `part_processes.standard_cycle_time` (override) if set, otherwise falls back to `process_masters.standard_time`.

```
effectiveCycleTime = part_processes.standard_cycle_time
                  ?? process_masters.standard_time
                  ?? 0
```

#### Step 2 — Efficiency Factor

The factory's OEE target is used as a planning assumption:

```
Factory Settings: oee_target_pct = 85%
Efficiency Factor = 85 / 100 = 0.85
```

This means: "We plan expecting to achieve 85% efficiency. We don't plan for 100%."

#### Step 3 — Shift Target Quantity

```
Shift: Morning (duration = 480 min)

Effective Minutes = 480 × 0.85 = 408 min
Target Qty        = ⌊ 408 / 12.5 ⌋ = 32 units
Theoretical Max   = ⌊ 480 / 12.5 ⌋ = 38 units   (at 100% OEE)
Capacity Gap      = 38 - 32 = 6 units             (lost to inefficiency)
```

#### Step 4 — Daily Target

```
Shifts active: Morning (480) + Afternoon (480) = 960 min/day

Daily Effective Minutes = 960 × 0.85 = 816 min
Daily Target Qty        = ⌊ 816 / 12.5 ⌋ = 65 units/day
Theoretical Max         = ⌊ 960 / 12.5 ⌋ = 76 units/day
```

---

### 11.4 Production Actuals & Attainment

Once production runs, output is recorded in `production_actuals`. Multiple batches can be recorded per plan.

#### Recording a batch

```json
POST /api/v1/production-actuals
{
  "production_plan_id": 42,
  "actual_qty": 80,
  "defect_qty": 5
}
```

**DB result:**
```
production_actuals row:
  actual_qty = 80
  defect_qty = 5
  good_qty   = 75   ← MySQL GENERATED ALWAYS AS (actual_qty - defect_qty)
```

#### Attainment calculation (for a plan with planned_qty = 100)

```
After Batch 1: actual=80, defect=5  → good=75
After Batch 2: actual=30, defect=2  → good=28

Totals (2 batches):
  total_actual_qty = 110
  total_defect_qty = 7
  total_good_qty   = 103
  batch_count      = 2

Defect Rate  = 7 / 110 × 100 = 6.36%
Yield Rate   = 103 / 110 × 100 = 93.64%

Attainment % = 103 / 100 × 100 = 103%  → Status: "exceeded" ✓
```

#### Efficiency Status Classification

| Attainment % | Status | Meaning |
|-------------|--------|---------|
| 0% | `not_started` | No actuals recorded |
| < OEE Target (85%) | `below_target` | Running behind |
| ≥ OEE Target (85%) | `on_target` | Meeting goal |
| > 100% | `exceeded` | Over-produced |

---

### 11.5 API Output Examples

#### Plan Analysis Output
`GET /api/v1/production-plans/42/analysis`

```json
{
  "data": {
    "plan": {
      "id": 42,
      "status": "in_progress",
      "planned_qty": 100,
      "planned_date": "2026-03-05",
      "machine": { "name": "CNC Lathe A", "code": "CNC-001" },
      "part":    { "part_number": "BKT-A001", "name": "Bracket A" },
      "shift":   { "name": "Morning Shift" }
    },
    "cycle_time_minutes": 12.5,
    "shift_target": {
      "shift_id":               1,
      "shift_name":             "Morning Shift",
      "shift_duration_minutes": 480,
      "efficiency_pct":         85.0,
      "effective_minutes":      408,
      "target_qty":             32,
      "theoretical_max_qty":    38,
      "capacity_gap_qty":       6
    },
    "actual_production": {
      "total_actual_qty": 110,
      "total_defect_qty": 7,
      "total_good_qty":   103,
      "batch_count":      2,
      "defect_rate_pct":  6.36,
      "yield_rate_pct":   93.64
    },
    "efficiency": {
      "planned_qty":         100,
      "actual_good_qty":     103,
      "efficiency_pct":      103.0,
      "variance_qty":        3,
      "variance_pct":        3.0,
      "status":              "exceeded",
      "is_on_target":        true,
      "target_threshold_pct": 85.0
    },
    "remaining_qty":                0,
    "estimated_remaining_minutes":  0
  },
  "meta": {
    "oee_target_pct":        85.0,
    "working_hours_per_day": 8.0
  }
}
```

#### Factory Daily Targets Output
`GET /api/v1/factories/1/daily-targets?date=2026-03-05`

```json
{
  "data": {
    "date": "2026-03-05",
    "daily_capacity_minutes": 960,
    "daily_capacity_hours":   16.0,
    "efficiency_factor":      0.85,
    "plans": [
      {
        "plan_id":           42,
        "status":            "in_progress",
        "part_number":       "BKT-A001",
        "machine_name":      "CNC Lathe A",
        "shift_name":        "Morning Shift",
        "planned_qty":       100,
        "cycle_time_minutes": 12.5,
        "shift_target_qty":  32,
        "actual_good_qty":   103,
        "defect_qty":        7,
        "efficiency_pct":    103.0,
        "efficiency_status": "exceeded",
        "is_on_target":      true,
        "remaining_qty":     0
      },
      {
        "plan_id":           43,
        "status":            "scheduled",
        "part_number":       "WLD-B002",
        "machine_name":      "Welder B",
        "shift_name":        "Morning Shift",
        "planned_qty":       50,
        "cycle_time_minutes": 8.0,
        "shift_target_qty":  51,
        "actual_good_qty":   0,
        "defect_qty":        0,
        "efficiency_pct":    0.0,
        "efficiency_status": "not_started",
        "is_on_target":      false,
        "remaining_qty":     50
      }
    ],
    "summary": {
      "total_plans":            8,
      "total_planned_qty":      650,
      "total_good_qty":         472,
      "overall_efficiency_pct": 72.6,
      "on_target_count":        5,
      "below_target_count":     3
    }
  },
  "meta": {
    "oee_target_pct":         85.0,
    "working_hours_per_day":  8.0
  }
}
```

#### Part Target Output
`GET /api/v1/parts/7/targets`

```json
{
  "data": {
    "part": {
      "id":              7,
      "part_number":     "BKT-A001",
      "name":            "Bracket A",
      "unit":            "pcs",
      "cycle_time_std":  12.5,
      "total_cycle_time": 12.5,
      "routing_steps":   3
    },
    "daily_target": {
      "daily_capacity_minutes": 960,
      "daily_capacity_hours":   16.0,
      "efficiency_pct":         85.0,
      "effective_minutes":      816,
      "target_qty":             65,
      "theoretical_max_qty":    76,
      "capacity_gap_qty":       11
    },
    "shift_targets": [
      {
        "shift_id":               1,
        "shift_name":             "Morning Shift",
        "shift_duration_minutes": 480,
        "efficiency_pct":         85.0,
        "effective_minutes":      408,
        "target_qty":             32,
        "theoretical_max_qty":    38,
        "capacity_gap_qty":       6
      },
      {
        "shift_id":               2,
        "shift_name":             "Afternoon Shift",
        "shift_duration_minutes": 480,
        "efficiency_pct":         85.0,
        "effective_minutes":      408,
        "target_qty":             32,
        "theoretical_max_qty":    38,
        "capacity_gap_qty":       6
      }
    ]
  },
  "meta": {
    "oee_target_pct":          85.0,
    "working_hours_per_day":   8.0,
    "daily_capacity_minutes":  960,
    "efficiency_factor":       0.85
  }
}
```

---

### 11.6 Plan Status Machine

A production plan moves through statuses in strict order. Completed and cancelled plans **cannot be edited**.

```
                ┌──────────┐
     Create ──► │  draft   │
                └────┬─────┘
                     │ Approve / Schedule
                     ▼
                ┌──────────────┐
                │  scheduled   │
                └────┬─────────┘
                     │ Start production
                     ▼
                ┌──────────────┐     ┌───────────────┐
                │  in_progress │────►│   cancelled   │ (immutable)
                └────┬─────────┘     └───────────────┘
                     │ All qty done
                     ▼
                ┌──────────────┐
                │  completed   │ (immutable)
                └──────────────┘
```

**Immutability rule:** Once a plan reaches `completed` or `cancelled`, any `PUT` request is rejected by the policy with `403 Forbidden`.

---

### 11.7 Production Calendar Grid (UI Behaviour)

The weekly grid at `/admin/production/plans` renders as:

```
Rows    = All active machines in the factory
Columns = 7 days (Mon–Sun, starting from current week)
Cells   = All shifts for that machine × day combination
```

**Cell rendering logic:**

```
For each machine × day × shift:
  lookup plansMap["machineId:date:shiftId"]

  If plans exist:
    → Show colored plan card(s) per status
    → Show compact "+ Add part" button below cards

  If no plans:
    → Show dashed "+ Shift Name" button (click to create)
```

**Plan card color by status:**

| Status | Border Color | Background | Text |
|--------|-------------|------------|------|
| draft | gray | gray-50 | gray-700 |
| scheduled | blue-400 | blue-50 | blue-800 |
| in_progress | amber-400 | amber-50 | amber-800 |
| completed | green-500 | green-50 | green-800 |
| cancelled | red-400 | red-50 | red-700 |

**Navigation:**
- `[◄ Prev]` / `[Next ►]` shifts week by ±7 days and re-fetches plans
- `[Today]` returns to current week
- `[+ New Plan]` opens modal with no pre-fill (all fields manual)
- Factory selector (Super Admin only) switches tenant and reloads grid

---

### 11.8 Summary: Data Flow Through Production Planning

```
Master Data Setup
     │
     ├── Factory Settings (oee_target_pct = 85%)
     ├── Shifts (duration_min)
     ├── Machines (device_token)
     └── Parts → Routing (cycle_time per step)
                    │
                    ▼
         Plan Creation (Admin Panel)
         production_plans row: machine + part + shift + date + qty + status=draft
                    │
                    ▼
         Status: draft → scheduled → in_progress
                    │
          ┌─────────┴─────────┐
          │                   │
          ▼                   ▼
    IoT Pulses           Manual Actuals
    iot_logs             production_actuals
    (auto, 5-sec)        (supervisor records batch)
          │                   │
          ▼                   │
    OEE Aggregation           │
    machine_oee_shifts         │
    (every 5 min)              │
          │                   │
          └─────────┬─────────┘
                    ▼
         Analysis Output
         ┌──────────────────────────────┐
         │ Shift Target: 32 units       │
         │ Actual Good:  103 units      │
         │ Attainment:   103%           │
         │ Status:       exceeded       │
         │ OEE:          A×P×Q/10000   │
         │ Defect Rate:  6.36%          │
         │ Remaining:    0 units        │
         └──────────────────────────────┘
```

---

## 12. Background Jobs & Scheduler



### OEE Aggregation Job

Every 5 minutes, the scheduler runs the OEE aggregation command, which reads from `iot_logs` and writes summarized OEE rows to `machine_oee_shifts`.

This keeps the dashboard fast even with millions of IoT log rows.

### Running the Scheduler

```bash
php artisan schedule:work
```

> **NOTE:** Run this command in a **separate terminal window** and keep it open while the application is running. It drives the scheduler loop. In production, this would be a cron job:
> ```
> * * * * * cd /path/to/smartfactory && php artisan schedule:run >> /dev/null 2>&1
> ```

### Running Aggregation Manually

```bash
# Aggregate OEE for all factories today
php artisan iot:aggregate-oee

# Aggregate for a specific factory
php artisan iot:aggregate-oee --factory=1

# Aggregate for a specific date
php artisan iot:aggregate-oee --date=2026-03-05

# Aggregate for specific factory + date
php artisan iot:aggregate-oee --factory=1 --date=2026-03-05
```

Aggregation results are logged to: `storage/logs/oee-aggregation.log`

---

## 13. Domain Architecture

The application follows **Domain-Driven Design (DDD)**. Business logic lives in `app/Domain/`, not in controllers.

```
app/Domain/
├── Shared/
│   ├── Enums/Permission.php    — 78 permission constants
│   ├── Enums/Role.php          — 6 roles with hierarchy
│   ├── Models/BaseModel.php    — Base Eloquent model
│   ├── Scopes/FactoryScope.php — Auto-filter by factory_id
│   └── Traits/
│       ├── BelongsToFactory.php — Adds factory() relationship + auto-set factory_id
│       └── HasFactoryScope.php  — Boots FactoryScope automatically
│
├── Factory/                    — Tenant management
├── Machine/                    — Machine + IoT log models
├── Production/                 — Customer, Part, Plan, Actual, Shift, Process
├── Analytics/                  — OEE calculation and aggregation
└── Auth/                       — Permission service (RBAC utilities)
```

### Repository Pattern

Repositories abstract the database from business logic:

```
Interface           →   Eloquent Implementation
FactoryRepositoryInterface  →  EloquentFactoryRepository
MachineRepositoryInterface  →  EloquentMachineRepository
PartRepositoryInterface     →  EloquentPartRepository
CustomerRepositoryInterface →  EloquentCustomerRepository
ProcessMasterRepositoryInterface → EloquentProcessMasterRepository
```

All bindings are registered in `app/Providers/RepositoryServiceProvider.php`.

### Service Layer Business Rules

Each domain service enforces business rules with `DomainException`:

| Service | Guard Example |
|---------|--------------|
| FactoryService | Cannot deactivate factory with active machines |
| MachineService | Cannot retire machine with open downtimes |
| CustomerService | Cannot deactivate customer with active parts |
| PartService | Cannot discontinue part with active production plans |
| PartService::syncRouting | Cannot change routing if plan is in_progress |

Controllers catch `DomainException` and return `409 Conflict` responses.

---

## 14. Server Commands Reference

> All commands must be run from the project root: `d:/xampp/htdocs/smartfactory`

### Application Setup

| Command | Purpose |
|---------|---------|
| `composer install` | Install PHP dependencies |
| `php artisan key:generate` | Generate application encryption key |
| `php artisan migrate` | Run all pending database migrations |
| `php artisan migrate:fresh` | Drop all tables and re-run all migrations |
| `php artisan db:seed --class=DemoSeeder` | Seed demo factory, users, machines, parts |
| `php artisan migrate:fresh --seed --seeder=DemoSeeder` | Fresh database with demo data |

### Development Server

| Command | Purpose |
|---------|---------|
| `php artisan serve --port=8000` | Start Laravel development server on port 8000 |
| `php artisan serve --host=0.0.0.0 --port=8000` | Start server accessible on local network |

### Cache Management

| Command | Purpose |
|---------|---------|
| `php artisan route:clear` | Clear compiled route cache |
| `php artisan view:clear` | Clear compiled Blade view cache |
| `php artisan config:clear` | Clear configuration cache |
| `php artisan cache:clear` | Clear application cache |
| `php artisan optimize:clear` | Clear all caches at once (recommended after code changes) |

> **NOTE:** Run `php artisan route:clear && php artisan view:clear` after modifying routes or Blade views, especially if you see "Route not defined" errors.

### IoT & OEE

| Command | Purpose |
|---------|---------|
| `php artisan iot:aggregate-oee` | Aggregate OEE for all factories (today) |
| `php artisan iot:aggregate-oee --factory=1` | Aggregate for factory ID 1 |
| `php artisan iot:aggregate-oee --date=2026-03-05` | Aggregate for a specific date |
| `php artisan schedule:work` | Run scheduler loop (keep terminal open) |

### Debugging & Inspection

| Command | Purpose |
|---------|---------|
| `php artisan route:list` | List all registered routes |
| `php artisan route:list --path=admin` | List admin routes only |
| `php artisan route:list --path=api/v1` | List API v1 routes only |
| `php artisan tinker` | Interactive PHP REPL (test models/queries live) |
| `php artisan about` | Show Laravel version and environment info |

### Permission Seeding (if roles/permissions get corrupted)

```bash
# Re-seed only permissions and roles (does not touch user data)
php artisan db:seed --class=RbacSeeder
```

> **NOTE:** This is safe to run on an existing database — it uses `firstOrCreate` so it won't duplicate records.

---

### Quick Reference: Common Operations

**Start the full application for development:**
```bash
# Terminal 1 — Web server
php artisan serve --port=8000

# Terminal 2 — Scheduler (for OEE aggregation every 5 minutes)
php artisan schedule:work
```

**After pulling code changes:**
```bash
composer install
php artisan migrate
php artisan optimize:clear
```

**Completely reset and start fresh:**
```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
php artisan optimize:clear
```

---

*Document generated for SmartFactory v1.0 — March 2026*
