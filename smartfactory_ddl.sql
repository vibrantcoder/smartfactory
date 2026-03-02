-- ============================================================
-- SMART FACTORY SYSTEM — PRODUCTION MySQL DDL
-- ============================================================
-- Engine      : MySQL 8.0+
-- Charset     : utf8mb4 / utf8mb4_unicode_ci
-- Volume      : 100 machines × 50 logs/min = 7.2M rows/day
-- Environment : 4 GB VPS (tuning section at bottom)
-- ============================================================
-- TABLE INDEX
--   §1  Reference / lookup tables (no FK dependencies)
--   §2  Core tenant: factories + factory_settings
--   §3  Users
--   §4  Operational entities: machines, customers, shifts
--   §5  Parts & process routing
--   §6  HIGH-VOLUME: machine_logs (monthly partitioned)
--   §7  Downtimes
--   §8  Production plans & actuals
--   §9  Aggregation layer: machine_logs_hourly, _daily
--   §10 OEE: machine_oee_daily
--   §11 Useful views
--   §12 Partition maintenance EVENT
--   §13 MySQL tuning recommendations (as comments)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ============================================================
-- DATABASE
-- ============================================================

CREATE DATABASE IF NOT EXISTS smartfactory
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE smartfactory;


-- ============================================================
-- §1  REFERENCE / LOOKUP TABLES
--     No foreign-key dependencies — safe to create first.
-- ============================================================

-- ─── process_masters ─────────────────────────────────────────
-- Global library of manufacturing process templates.
-- Not factory-scoped; shared across all tenants.
-- part_processes links these to specific parts + sequences.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE process_masters (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code            VARCHAR(50)      NOT NULL,
    name            VARCHAR(150)     NOT NULL,
    description     TEXT,
    category        VARCHAR(100)     NOT NULL   COMMENT 'e.g. Machining, Assembly, QC, Welding',
    standard_time   DECIMAL(8,2)     NOT NULL DEFAULT 0.00
                                     COMMENT 'Ideal seconds/cycle; overridden per part in part_processes',
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_pm_code           (code),
    KEY         idx_pm_category      (category),
    KEY         idx_pm_is_active     (is_active)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Global reference library of manufacturing process templates';


-- ─── downtime_reasons ────────────────────────────────────────
-- Lookup codes for classifying downtime events.
-- Separating from downtimes keeps the high-volume table lean.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE downtime_reasons (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code            VARCHAR(20)      NOT NULL,
    category        ENUM(
                        'mechanical',
                        'electrical',
                        'tooling',
                        'material',
                        'operator',
                        'planned',
                        'changeover'
                    ) NOT NULL,
    description     VARCHAR(255)     NOT NULL,
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_dr_code           (code),
    KEY         idx_dr_category      (category)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Reference codes for classifying downtime events';


-- ============================================================
-- §2  CORE TENANT: FACTORIES + SETTINGS
-- ============================================================

-- ─── factories ───────────────────────────────────────────────
-- Root tenant entity. Every row of operational data traces back
-- to a factory through its relationships.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE factories (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)     NOT NULL,
    code            VARCHAR(20)      NOT NULL   COMMENT 'Short unique identifier, e.g. FAC-BKK-01',
    location        VARCHAR(255),
    timezone        VARCHAR(50)      NOT NULL DEFAULT 'UTC'
                                     COMMENT 'PHP/IANA timezone name, e.g. Asia/Bangkok',
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_factories_code    (code),
    KEY         idx_factories_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Root tenant entity — all operational data traces to a factory';


-- ─── factory_settings ────────────────────────────────────────
-- One row per factory. Drives scheduler thresholds and alerts.
-- Stored here rather than in code so ops can change at runtime.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE factory_settings (
    id                          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    factory_id                  BIGINT UNSIGNED  NOT NULL,

    -- OEE targets (alert if daily OEE drops below these)
    oee_target_pct              DECIMAL(5,2)     NOT NULL DEFAULT 85.00,
    availability_target_pct     DECIMAL(5,2)     NOT NULL DEFAULT 90.00,
    performance_target_pct      DECIMAL(5,2)     NOT NULL DEFAULT 95.00,
    quality_target_pct          DECIMAL(5,2)     NOT NULL DEFAULT 99.00,

    -- IoT & aggregation tuning
    log_interval_seconds        TINYINT UNSIGNED NOT NULL DEFAULT 5
                                COMMENT 'Expected IoT push cadence; used to compute time-in-status',
    downtime_threshold_min      TINYINT UNSIGNED NOT NULL DEFAULT 5
                                COMMENT 'Min consecutive fault minutes before auto-creating downtime record',
    aggregation_lag_min         TINYINT UNSIGNED NOT NULL DEFAULT 10
                                COMMENT 'Extra delay before hourly job processes logs (absorb late arrivals)',

    -- Retention
    raw_log_retention_days      SMALLINT UNSIGNED NOT NULL DEFAULT 90
                                COMMENT 'Days to keep raw machine_logs before dropping partition',

    created_at                  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_fs_factory_id     (factory_id),
    CONSTRAINT fk_fs_factory
        FOREIGN KEY (factory_id) REFERENCES factories (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-factory configuration values consumed by scheduler jobs and alert logic';


-- ============================================================
-- §3  USERS
-- ============================================================

-- ─── users ───────────────────────────────────────────────────
-- Scoped to one factory. Role controls access within that scope.
-- Extend to factory_user pivot table if cross-factory users
-- are required in a future iteration.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE users (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    factory_id      BIGINT UNSIGNED  NOT NULL,
    name            VARCHAR(150)     NOT NULL,
    email           VARCHAR(255)     NOT NULL,
    password        VARCHAR(255)     NOT NULL   COMMENT 'bcrypt/argon2 hash',
    role            ENUM(
                        'admin',
                        'engineer',
                        'operator',
                        'viewer'
                    ) NOT NULL DEFAULT 'operator',
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at   TIMESTAMP        NULL,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_users_email             (email),
    KEY         idx_users_factory_id       (factory_id),
    KEY         idx_users_factory_role     (factory_id, role),
    KEY         idx_users_status           (status),
    CONSTRAINT fk_users_factory
        FOREIGN KEY (factory_id) REFERENCES factories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Factory staff accounts — one factory per user, role drives authorization';


-- ============================================================
-- §4  OPERATIONAL ENTITIES
-- ============================================================

-- ─── machines ────────────────────────────────────────────────
-- Physical machines in a factory.
-- device_token authenticates IoT push requests without sessions.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE machines (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    factory_id      BIGINT UNSIGNED  NOT NULL,
    name            VARCHAR(150)     NOT NULL,
    code            VARCHAR(50)      NOT NULL   COMMENT 'Unique within a factory, e.g. MCH-001',
    type            VARCHAR(100)     NOT NULL   COMMENT 'Machine category: CNC, Press, Lathe, Robot, etc.',
    model           VARCHAR(100),
    manufacturer    VARCHAR(150),
    device_token    VARCHAR(64)      NOT NULL   COMMENT 'SHA-256 hex token; IoT device sends in Authorization header',
    status          ENUM('active','maintenance','retired') NOT NULL DEFAULT 'active',
    installed_at    DATE,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_machines_factory_code   (factory_id, code),
    UNIQUE  KEY uq_machines_device_token   (device_token),
    KEY         idx_machines_factory_id    (factory_id),
    KEY         idx_machines_type          (type),
    KEY         idx_machines_status        (status),
    CONSTRAINT fk_machines_factory
        FOREIGN KEY (factory_id) REFERENCES factories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Physical machines; device_token used by IoT firmware for API authentication';


-- ─── customers ───────────────────────────────────────────────
CREATE TABLE customers (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    factory_id      BIGINT UNSIGNED  NOT NULL,
    name            VARCHAR(150)     NOT NULL,
    code            VARCHAR(30)      NOT NULL,
    contact_person  VARCHAR(150),
    email           VARCHAR(255),
    phone           VARCHAR(30),
    address         TEXT,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_customers_factory_code  (factory_id, code),
    KEY         idx_customers_factory_id   (factory_id),
    KEY         idx_customers_status       (status),
    CONSTRAINT fk_customers_factory
        FOREIGN KEY (factory_id) REFERENCES factories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Customers who order parts manufactured in this factory';


-- ─── shifts ──────────────────────────────────────────────────
-- Shift definitions per factory.
-- duration_minutes is pre-computed to handle overnight spans
-- (e.g. 22:00–06:00 = 480 min, not a negative subtraction).
-- ─────────────────────────────────────────────────────────────
CREATE TABLE shifts (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    factory_id       BIGINT UNSIGNED NOT NULL,
    name             VARCHAR(50)     NOT NULL   COMMENT 'e.g. Morning, Afternoon, Night',
    start_time       TIME            NOT NULL,
    end_time         TIME            NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL COMMENT 'Pre-computed; handles overnight shifts correctly',
    is_active        TINYINT(1)      NOT NULL DEFAULT 1,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_shifts_factory_name     (factory_id, name),
    KEY         idx_shifts_factory_active  (factory_id, is_active),
    CONSTRAINT fk_shifts_factory
        FOREIGN KEY (factory_id) REFERENCES factories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Shift time windows per factory; duration_minutes handles overnight spans';


-- ============================================================
-- §5  PARTS & PROCESS ROUTING
-- ============================================================

-- ─── parts ───────────────────────────────────────────────────
-- Parts manufactured for customers.
-- factory_id is denormalized here so dashboard queries can
-- filter parts by factory without joining through customers.
-- cycle_time_std feeds the OEE Performance formula.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE parts (
    id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    customer_id         BIGINT UNSIGNED  NOT NULL,
    factory_id          BIGINT UNSIGNED  NOT NULL   COMMENT 'Denormalized from customers for direct factory queries',
    part_number         VARCHAR(100)     NOT NULL,
    name                VARCHAR(200)     NOT NULL,
    description         TEXT,
    unit                VARCHAR(20)      NOT NULL DEFAULT 'pcs',
    cycle_time_std      DECIMAL(8,2)     NOT NULL DEFAULT 0.00
                        COMMENT 'Ideal cycle time in seconds — used as Performance numerator in OEE',
    status              ENUM('active','discontinued') NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_parts_factory_number    (factory_id, part_number),
    KEY         idx_parts_customer_id      (customer_id),
    KEY         idx_parts_factory_id       (factory_id),
    KEY         idx_parts_status           (status),
    CONSTRAINT fk_parts_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_parts_factory
        FOREIGN KEY (factory_id) REFERENCES factories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Parts manufactured for customers; cycle_time_std drives OEE Performance calculation';


-- ─── part_processes ──────────────────────────────────────────
-- Routing table: ordered process steps a part must pass through.
-- UNIQUE (part_id, sequence_order) enforces unambiguous routing.
-- Many-to-many bridge: Parts ↔ ProcessMasters + sequence + overrides.
--
-- Example routing for "Bracket-A001":
--   Seq 1 → PROC-001 Laser Cutting   (machine_type: Laser)
--   Seq 2 → PROC-002 Bending         (machine_type: Press)
--   Seq 3 → PROC-003 Welding         (machine_type: Welder)
--   Seq 4 → PROC-010 QC Inspection   (machine_type: QC Station)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE part_processes (
    id                      BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    part_id                 BIGINT UNSIGNED  NOT NULL,
    process_master_id       BIGINT UNSIGNED  NOT NULL,
    sequence_order          TINYINT UNSIGNED NOT NULL   COMMENT '1-based routing order; enforced unique per part',
    machine_type_required   VARCHAR(100)                COMMENT 'Restricts eligible machines for this step',
    standard_cycle_time     DECIMAL(8,2)                COMMENT 'Overrides process_master.standard_time for this part (seconds)',
    notes                   TEXT,
    created_at              TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_pp_part_sequence        (part_id, sequence_order),
    KEY         idx_pp_part_id             (part_id),
    KEY         idx_pp_process_master_id   (process_master_id),
    CONSTRAINT fk_pp_part
        FOREIGN KEY (part_id) REFERENCES parts (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pp_master
        FOREIGN KEY (process_master_id) REFERENCES process_masters (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Routing table: ordered manufacturing steps per part. Many-to-many: Parts × ProcessMasters + sequence';


-- ============================================================
-- §6  HIGH-VOLUME: machine_logs (MONTHLY PARTITIONED)
-- ============================================================
--
-- VOLUME CALCULATION:
--   100 machines × 50 logs/min × 1,440 min/day = 7,200,000 rows/day
--   × 30 days = 216,000,000 rows/month
--   Row size ≈ 350 bytes (with JSON compressed) → ~14 GB/month raw
--   After 90-day retention → ~42 GB in hot storage
--
-- PARTITIONING STRATEGY:
--   RANGE on TO_DAYS(logged_at) — one partition per calendar month.
--   MySQL partition pruning fires when WHERE includes logged_at.
--   Old partitions are DROPPED via scheduled EVENT (§12).
--   New partitions are added monthly by the same EVENT.
--
-- FK CONSTRAINT LIMITATION:
--   MySQL does NOT allow foreign keys on partitioned tables.
--   machine_id / part_id / process_id are indexed for join
--   performance but referential integrity is enforced at the
--   application (Laravel) layer.
--
-- PRIMARY KEY REQUIREMENT:
--   All partition columns MUST be part of the primary key.
--   logged_at is included in PK for this reason.
--
-- ROW_FORMAT=COMPRESSED:
--   Saves ~30–40% storage, especially on the JSON raw_payload.
--   Requires innodb_file_per_table=ON (default in MySQL 8).
-- ============================================================

CREATE TABLE machine_logs (
    id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    machine_id       BIGINT UNSIGNED  NOT NULL,
    part_id          BIGINT UNSIGNED              COMMENT 'NULL when machine is idle with no active job',
    process_id       BIGINT UNSIGNED              COMMENT 'part_processes.id — which routing step is running',
    logged_at        DATETIME(3)      NOT NULL     COMMENT 'Millisecond precision; sensor timestamp, NOT insert time',
    status           ENUM(
                         'running',
                         'idle',
                         'fault',
                         'changeover'
                     ) NOT NULL,
    production_count SMALLINT UNSIGNED NOT NULL DEFAULT 0
                     COMMENT 'Units produced during this log interval',
    cycle_time       DECIMAL(8,3)                 COMMENT 'Actual seconds/cycle observed this interval',
    temperature      DECIMAL(6,2)                 COMMENT 'Celsius',
    vibration        DECIMAL(6,3)                 COMMENT 'm/s²',
    power_kw         DECIMAL(8,3)                 COMMENT 'Kilowatts consumed',
    raw_payload      JSON                         COMMENT 'Full sensor payload — kept for audit and replay',
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- logged_at must be in PK because it is the partition key
    PRIMARY KEY      (id, logged_at),

    -- ── INDEX STRATEGY ──────────────────────────────────────
    -- Each index below is scoped to (machine_id, logged_at) first
    -- so MySQL can combine partition pruning with index lookup.

    -- Primary time-series lookup: "give me machine M's last N logs"
    KEY idx_ml_machine_time        (machine_id, logged_at),

    -- Downtime detection: "find fault intervals for machine M"
    KEY idx_ml_machine_status_time (machine_id, status, logged_at),

    -- Part production queries: "how many units of Part P today?"
    KEY idx_ml_part_time           (part_id, logged_at),

    -- Process step queries: "all logs for process step S this shift"
    KEY idx_ml_process_time        (process_id, logged_at),

    -- Cross-machine status reports: "which machines are faulting now?"
    KEY idx_ml_status_time         (status, logged_at)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  ROW_FORMAT=COMPRESSED
  COMMENT='Raw IoT telemetry. Partitioned monthly. FKs enforced at app layer (MySQL limitation).'

PARTITION BY RANGE (TO_DAYS(logged_at)) (
    PARTITION p_2025_10 VALUES LESS THAN (TO_DAYS('2025-11-01')),
    PARTITION p_2025_11 VALUES LESS THAN (TO_DAYS('2025-12-01')),
    PARTITION p_2025_12 VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION p_2026_01 VALUES LESS THAN (TO_DAYS('2026-02-01')),
    PARTITION p_2026_02 VALUES LESS THAN (TO_DAYS('2026-03-01')),
    PARTITION p_2026_03 VALUES LESS THAN (TO_DAYS('2026-04-01')),
    PARTITION p_2026_04 VALUES LESS THAN (TO_DAYS('2026-05-01')),
    PARTITION p_2026_05 VALUES LESS THAN (TO_DAYS('2026-06-01')),
    PARTITION p_2026_06 VALUES LESS THAN (TO_DAYS('2026-07-01')),
    PARTITION p_2026_07 VALUES LESS THAN (TO_DAYS('2026-08-01')),
    PARTITION p_2026_08 VALUES LESS THAN (TO_DAYS('2026-09-01')),
    PARTITION p_2026_09 VALUES LESS THAN (TO_DAYS('2026-10-01')),
    PARTITION p_2026_10 VALUES LESS THAN (TO_DAYS('2026-11-01')),
    PARTITION p_2026_11 VALUES LESS THAN (TO_DAYS('2026-12-01')),
    PARTITION p_2026_12 VALUES LESS THAN (TO_DAYS('2027-01-01')),
    PARTITION p_future   VALUES LESS THAN MAXVALUE
);


-- ============================================================
-- §7  DOWNTIMES
-- ============================================================

-- ─── downtimes ───────────────────────────────────────────────
-- Machine downtime events.
-- source='auto'   → detected from machine_logs fault pattern
--                   (job watches for consecutive fault rows > threshold)
-- source='manual' → operator-entered via dashboard
-- ended_at NULL   → currently open / ongoing
-- duration_minutes is SET by the application when ended_at is recorded.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE downtimes (
    id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    machine_id          BIGINT UNSIGNED  NOT NULL,
    reason_id           BIGINT UNSIGNED              COMMENT 'NULL = auto-detected, not yet classified',
    reported_by         BIGINT UNSIGNED              COMMENT 'users.id — NULL if system-auto-detected',
    resolved_by         BIGINT UNSIGNED              COMMENT 'users.id — NULL while unresolved',
    started_at          DATETIME         NOT NULL,
    ended_at            DATETIME                     COMMENT 'NULL = downtime is ongoing',
    duration_minutes    INT UNSIGNED                 COMMENT 'Set by app when ended_at is recorded; not a generated column (avoids NULL issues)',
    category            ENUM(
                            'planned',
                            'unplanned',
                            'breakdown',
                            'changeover'
                        ) NOT NULL DEFAULT 'unplanned',
    source              ENUM('auto','manual') NOT NULL DEFAULT 'auto',
    description         TEXT,
    created_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Fetch downtime events for a machine in a time window
    KEY idx_dt_machine_started          (machine_id, started_at),
    -- OEE: sum unplanned downtime for a machine on a date
    KEY idx_dt_machine_category_started (machine_id, category, started_at),
    -- Dashboard: open downtime list (ended_at IS NULL)
    KEY idx_dt_machine_ongoing          (machine_id, ended_at),
    -- Administrative
    KEY idx_dt_reason_id                (reason_id),
    KEY idx_dt_reported_by              (reported_by),
    KEY idx_dt_started_at               (started_at),

    CONSTRAINT fk_dt_machine
        FOREIGN KEY (machine_id)  REFERENCES machines (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dt_reason
        FOREIGN KEY (reason_id)   REFERENCES downtime_reasons (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dt_reporter
        FOREIGN KEY (reported_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_dt_resolver
        FOREIGN KEY (resolved_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT chk_dt_dates
        CHECK (ended_at IS NULL OR ended_at > started_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Machine downtime events — auto-detected from IoT faults or manually entered';


-- ============================================================
-- §8  PRODUCTION PLANS & ACTUALS
-- ============================================================

-- ─── production_plans ────────────────────────────────────────
-- "Machine M5 will produce 500 pcs of Part X in Morning Shift
--  on 2026-02-25."
-- UNIQUE (machine_id, shift_date, shift_id) = one plan per
-- machine per shift — prevents double-scheduling.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE production_plans (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    factory_id      BIGINT UNSIGNED  NOT NULL   COMMENT 'Denormalized for factory-scoped plan listing',
    machine_id      BIGINT UNSIGNED  NOT NULL,
    part_id         BIGINT UNSIGNED  NOT NULL,
    shift_id        BIGINT UNSIGNED  NOT NULL,
    shift_date      DATE             NOT NULL,
    planned_qty     INT UNSIGNED     NOT NULL,
    planned_start   DATETIME         NOT NULL,
    planned_end     DATETIME         NOT NULL,
    status          ENUM(
                        'scheduled',
                        'in_progress',
                        'completed',
                        'cancelled'
                    ) NOT NULL DEFAULT 'scheduled',
    created_by      BIGINT UNSIGNED  NOT NULL   COMMENT 'users.id',
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- One plan per machine per shift per day
    UNIQUE  KEY uq_pp_machine_shift_date   (machine_id, shift_id, shift_date),
    -- Factory-level Gantt / schedule view
    KEY         idx_pp_factory_date        (factory_id, shift_date),
    -- Machine schedule view
    KEY         idx_pp_machine_date        (machine_id, shift_date),
    -- Part demand view
    KEY         idx_pp_part_date           (part_id, shift_date),
    KEY         idx_pp_shift_id            (shift_id),
    KEY         idx_pp_status              (status),
    KEY         idx_pp_created_by          (created_by),

    CONSTRAINT fk_pp_factory
        FOREIGN KEY (factory_id)  REFERENCES factories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pp_machine
        FOREIGN KEY (machine_id)  REFERENCES machines (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pp_part
        FOREIGN KEY (part_id)     REFERENCES parts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pp_shift
        FOREIGN KEY (shift_id)    REFERENCES shifts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pp_created_by
        FOREIGN KEY (created_by)  REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT chk_pp_dates  CHECK (planned_end > planned_start),
    CONSTRAINT chk_pp_qty    CHECK (planned_qty > 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Scheduled production: machine × part × shift × date. One plan per machine per shift.';


-- ─── production_actuals ──────────────────────────────────────
-- Actual production results recorded at end of shift.
-- One actual per plan (UNIQUE on plan_id).
-- good_qty is a GENERATED column = actual_qty - defect_qty.
-- machine_id / part_id / shift_id are denormalized from the
-- plan to avoid a join in every OEE calculation query.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE production_actuals (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    plan_id         BIGINT UNSIGNED  NOT NULL,
    machine_id      BIGINT UNSIGNED  NOT NULL   COMMENT 'Denormalized from production_plans',
    part_id         BIGINT UNSIGNED  NOT NULL   COMMENT 'Denormalized from production_plans',
    shift_id        BIGINT UNSIGNED  NOT NULL   COMMENT 'Denormalized from production_plans',
    operator_id     BIGINT UNSIGNED              COMMENT 'users.id — NULL if system-recorded',
    shift_date      DATE             NOT NULL,
    actual_qty      INT UNSIGNED     NOT NULL DEFAULT 0,
    defect_qty      INT UNSIGNED     NOT NULL DEFAULT 0,
    good_qty        INT UNSIGNED
                    GENERATED ALWAYS AS (actual_qty - defect_qty) STORED
                    COMMENT 'Computed: actual - defect; stored for index/query performance',
    recorded_at     DATETIME         NOT NULL,
    notes           TEXT,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Enforce one final actual per plan
    UNIQUE  KEY uq_pa_plan_id              (plan_id),
    -- OEE job: fetch actuals for a machine on a date
    KEY         idx_pa_machine_date        (machine_id, shift_date),
    -- Part demand analysis
    KEY         idx_pa_part_date           (part_id, shift_date),
    KEY         idx_pa_shift_id            (shift_id),
    KEY         idx_pa_operator_id         (operator_id),

    CONSTRAINT fk_pa_plan
        FOREIGN KEY (plan_id)     REFERENCES production_plans (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pa_machine
        FOREIGN KEY (machine_id)  REFERENCES machines (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pa_part
        FOREIGN KEY (part_id)     REFERENCES parts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pa_shift
        FOREIGN KEY (shift_id)    REFERENCES shifts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pa_operator
        FOREIGN KEY (operator_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT chk_pa_defect CHECK (defect_qty <= actual_qty)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Actual production per plan; good_qty is a generated stored column; one row per plan';


-- ============================================================
-- §9  AGGREGATION LAYER
-- ============================================================
--
-- AGGREGATION PIPELINE:
--   machine_logs (raw, 7.2M/day)
--       │  Hourly job (runs at :10 past each hour)
--       ▼
--   machine_logs_hourly  (100 machines × 24 = 2,400 rows/day)
--       │  Daily rollup job (runs at 00:30)
--       ▼
--   machine_logs_daily   (100 machines × 1 = 100 rows/day)
--
-- ALL JOBS use INSERT ... ON DUPLICATE KEY UPDATE (idempotent).
-- Dashboard NEVER queries machine_logs directly.
-- ============================================================

-- ─── machine_logs_hourly ─────────────────────────────────────
CREATE TABLE machine_logs_hourly (
    id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    machine_id       BIGINT UNSIGNED  NOT NULL,
    part_id          BIGINT UNSIGNED              COMMENT 'NULL = machine ran without an active job this hour',
    hour_start       DATETIME         NOT NULL     COMMENT 'Truncated to hour: e.g. 2026-02-25 08:00:00',

    -- Counts
    total_count      INT UNSIGNED     NOT NULL DEFAULT 0,
    good_count       INT UNSIGNED     NOT NULL DEFAULT 0,
    defect_count     INT UNSIGNED     NOT NULL DEFAULT 0,

    -- Cycle time
    avg_cycle_time   DECIMAL(8,3)                 COMMENT 'Average actual seconds/cycle for the hour',

    -- Time-in-status (minutes); sum should equal ~60 min
    runtime_min      DECIMAL(6,2)     NOT NULL DEFAULT 0.00,
    idle_min         DECIMAL(6,2)     NOT NULL DEFAULT 0.00,
    fault_min        DECIMAL(6,2)     NOT NULL DEFAULT 0.00,
    changeover_min   DECIMAL(6,2)     NOT NULL DEFAULT 0.00,

    -- Sensor averages
    avg_temperature  DECIMAL(6,2),
    avg_vibration    DECIMAL(6,3),
    avg_power_kw     DECIMAL(8,3),

    -- Audit
    log_count        INT UNSIGNED     NOT NULL DEFAULT 0
                     COMMENT 'Count of raw machine_logs rows summarized; sanity check',
    computed_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_mlh_machine_part_hour   (machine_id, part_id, hour_start),
    KEY         idx_mlh_machine_hour       (machine_id, hour_start),
    KEY         idx_mlh_part_hour          (part_id, hour_start),
    KEY         idx_mlh_hour_start         (hour_start),

    CONSTRAINT fk_mlh_machine
        FOREIGN KEY (machine_id) REFERENCES machines (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mlh_part
        FOREIGN KEY (part_id)    REFERENCES parts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Hourly aggregation of machine_logs; source for live dashboard trending; rebuilt idempotently';


-- ─── machine_logs_daily ──────────────────────────────────────
CREATE TABLE machine_logs_daily (
    id                   BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    machine_id           BIGINT UNSIGNED  NOT NULL,
    part_id              BIGINT UNSIGNED              COMMENT 'NULL = totals across all parts for machine+day',
    log_date             DATE             NOT NULL,

    -- Counts
    total_count          INT UNSIGNED     NOT NULL DEFAULT 0,
    good_count           INT UNSIGNED     NOT NULL DEFAULT 0,
    defect_count         INT UNSIGNED     NOT NULL DEFAULT 0,

    -- Cycle time
    avg_cycle_time       DECIMAL(8,3)                 COMMENT 'Weighted average across all hours',

    -- Time-in-status (minutes); sum should ≈ 1440 min for all-day
    total_runtime_min    DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
    total_idle_min       DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
    total_fault_min      DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
    total_changeover_min DECIMAL(8,2)     NOT NULL DEFAULT 0.00,

    -- Sensor averages
    avg_temperature      DECIMAL(6,2),
    avg_vibration        DECIMAL(6,3),
    avg_power_kw         DECIMAL(8,3),

    -- Audit
    log_count            INT UNSIGNED     NOT NULL DEFAULT 0
                         COMMENT 'Count of hourly rows rolled up',
    computed_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at           TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_mld_machine_part_date   (machine_id, part_id, log_date),
    KEY         idx_mld_machine_date       (machine_id, log_date),
    KEY         idx_mld_part_date          (part_id, log_date),
    KEY         idx_mld_log_date           (log_date),

    CONSTRAINT fk_mld_machine
        FOREIGN KEY (machine_id) REFERENCES machines (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mld_part
        FOREIGN KEY (part_id)    REFERENCES parts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Daily aggregation rolled from machine_logs_hourly; permanent retention; historical reports source';


-- ============================================================
-- §10  OEE: machine_oee_daily
-- ============================================================
--
-- OEE FORMULA:
--   Availability % = Operating Time / Planned Time × 100
--                    (Operating Time = Planned Time − Unplanned Downtime)
--
--   Performance %  = (Ideal Cycle Time × Actual Qty)
--                    / (Operating Time × 60) × 100
--
--   Quality %      = Good Qty / Actual Qty × 100
--
--   OEE %          = (Availability% / 100)
--                    × (Performance% / 100)
--                    × (Quality% / 100) × 100
--
-- POPULATION:
--   Written by a scheduled Laravel job — NOT in real-time.
--   All source values are stored alongside computed ratios
--   for full transparency and audit trail.
-- ============================================================

CREATE TABLE machine_oee_daily (
    id                   BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    machine_id           BIGINT UNSIGNED  NOT NULL,
    factory_id           BIGINT UNSIGNED  NOT NULL   COMMENT 'Denormalized for factory-level OEE aggregation',
    shift_id             BIGINT UNSIGNED  NOT NULL,
    oee_date             DATE             NOT NULL,

    -- ── Computed OEE Components (0.00–100.00) ───────────────
    availability_pct     DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
    performance_pct      DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
    quality_pct          DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
    oee_pct              DECIMAL(5,2)     NOT NULL DEFAULT 0.00
                         COMMENT 'A% × P% × Q% / 10000',

    -- ── Availability Inputs ──────────────────────────────────
    planned_time_min     SMALLINT UNSIGNED NOT NULL
                         COMMENT 'Copied from shifts.duration_minutes at time of calculation',
    unplanned_down_min   INT UNSIGNED      NOT NULL DEFAULT 0
                         COMMENT 'SUM(downtimes.duration_minutes WHERE category = unplanned)',
    planned_down_min     INT UNSIGNED      NOT NULL DEFAULT 0
                         COMMENT 'SUM(downtimes.duration_minutes WHERE category = planned)',
    operating_time_min   SMALLINT UNSIGNED NOT NULL
                         COMMENT 'planned_time_min − unplanned_down_min',

    -- ── Performance Inputs ───────────────────────────────────
    ideal_cycle_time_sec DECIMAL(8,2)                COMMENT 'parts.cycle_time_std at calc time (seconds)',
    planned_qty          INT UNSIGNED      NOT NULL DEFAULT 0,
    actual_qty           INT UNSIGNED      NOT NULL DEFAULT 0,

    -- ── Quality Inputs ───────────────────────────────────────
    good_qty             INT UNSIGNED      NOT NULL DEFAULT 0,
    defect_qty           INT UNSIGNED      NOT NULL DEFAULT 0,

    -- ── Job Metadata ─────────────────────────────────────────
    calculated_at        DATETIME          NOT NULL
                         COMMENT 'Timestamp the OEE job ran; used to detect stale rows',
    created_at           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Enforce one OEE row per machine per shift per day
    UNIQUE  KEY uq_oee_machine_shift_date  (machine_id, shift_id, oee_date),
    -- Factory OEE dashboard: all machines for factory on a date
    KEY         idx_oee_factory_date       (factory_id, oee_date),
    -- Single-machine trend chart
    KEY         idx_oee_machine_date       (machine_id, oee_date),
    -- Alert query: "which machines have OEE below target today?"
    KEY         idx_oee_date_score         (oee_date, oee_pct),
    KEY         idx_oee_shift_id           (shift_id),

    CONSTRAINT fk_oee_machine
        FOREIGN KEY (machine_id) REFERENCES machines (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_oee_factory
        FOREIGN KEY (factory_id) REFERENCES factories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_oee_shift
        FOREIGN KEY (shift_id)   REFERENCES shifts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    CONSTRAINT chk_oee_availability CHECK (availability_pct BETWEEN 0.00 AND 100.00),
    CONSTRAINT chk_oee_performance  CHECK (performance_pct  BETWEEN 0.00 AND 100.00),
    CONSTRAINT chk_oee_quality      CHECK (quality_pct      BETWEEN 0.00 AND 100.00),
    CONSTRAINT chk_oee_score        CHECK (oee_pct          BETWEEN 0.00 AND 100.00)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Pre-computed OEE per machine per shift per day. Single source of truth for OEE dashboard.';


-- ============================================================
-- §11  VIEWS
-- ============================================================

-- ─── v_machine_status ────────────────────────────────────────
-- Latest status for every active machine.
-- Used by the real-time floor map dashboard widget.
-- Query hits machine_logs_hourly (current hour) — not raw logs.
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_machine_status AS
SELECT
    m.id              AS machine_id,
    m.factory_id,
    m.name            AS machine_name,
    m.code            AS machine_code,
    m.type            AS machine_type,
    m.status          AS machine_status,
    mlh.hour_start,
    mlh.total_count,
    mlh.runtime_min,
    mlh.fault_min,
    mlh.avg_power_kw
FROM machines m
LEFT JOIN machine_logs_hourly mlh
    ON  mlh.machine_id = m.id
    AND mlh.hour_start = (
        SELECT MAX(h2.hour_start)
        FROM machine_logs_hourly h2
        WHERE h2.machine_id = m.id
    )
WHERE m.status = 'active';


-- ─── v_oee_summary ───────────────────────────────────────────
-- Factory-level OEE for the last 30 days.
-- Weighted average across all machines and shifts.
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_oee_summary AS
SELECT
    f.id                     AS factory_id,
    f.name                   AS factory_name,
    o.oee_date,
    COUNT(o.id)              AS machine_shift_count,
    ROUND(AVG(o.oee_pct), 2)          AS avg_oee_pct,
    ROUND(AVG(o.availability_pct), 2) AS avg_availability_pct,
    ROUND(AVG(o.performance_pct), 2)  AS avg_performance_pct,
    ROUND(AVG(o.quality_pct), 2)      AS avg_quality_pct,
    SUM(o.planned_qty)                AS total_planned_qty,
    SUM(o.actual_qty)                 AS total_actual_qty,
    SUM(o.good_qty)                   AS total_good_qty,
    SUM(o.defect_qty)                 AS total_defect_qty,
    SUM(o.unplanned_down_min)         AS total_downtime_min
FROM machine_oee_daily o
JOIN factories f ON f.id = o.factory_id
WHERE o.oee_date >= CURDATE() - INTERVAL 30 DAY
GROUP BY f.id, f.name, o.oee_date
ORDER BY f.id, o.oee_date DESC;


-- ─── v_production_attainment ─────────────────────────────────
-- Plan vs Actual comparison per machine per shift.
-- Source for the production attainment report.
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_production_attainment AS
SELECT
    pp.factory_id,
    pp.machine_id,
    m.name              AS machine_name,
    pp.part_id,
    p.part_number,
    p.name              AS part_name,
    pp.shift_date,
    s.name              AS shift_name,
    pp.planned_qty,
    COALESCE(pa.actual_qty,  0)  AS actual_qty,
    COALESCE(pa.good_qty,    0)  AS good_qty,
    COALESCE(pa.defect_qty,  0)  AS defect_qty,
    ROUND(
        COALESCE(pa.actual_qty, 0) / NULLIF(pp.planned_qty, 0) * 100,
        2
    )                            AS attainment_pct,
    pp.status           AS plan_status
FROM production_plans pp
JOIN machines m  ON m.id  = pp.machine_id
JOIN parts    p  ON p.id  = pp.part_id
JOIN shifts   s  ON s.id  = pp.shift_id
LEFT JOIN production_actuals pa ON pa.plan_id = pp.id
WHERE pp.shift_date >= CURDATE() - INTERVAL 7 DAY;


-- ─── v_open_downtimes ────────────────────────────────────────
-- Currently active (unresolved) downtime events.
-- Dashboard alert panel source.
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_open_downtimes AS
SELECT
    d.id              AS downtime_id,
    m.factory_id,
    d.machine_id,
    m.name            AS machine_name,
    m.code            AS machine_code,
    dr.category       AS reason_category,
    dr.description    AS reason_description,
    d.category        AS downtime_category,
    d.started_at,
    TIMESTAMPDIFF(MINUTE, d.started_at, NOW()) AS elapsed_minutes,
    d.source
FROM downtimes d
JOIN machines m          ON m.id  = d.machine_id
LEFT JOIN downtime_reasons dr ON dr.id = d.reason_id
WHERE d.ended_at IS NULL
ORDER BY d.started_at ASC;


-- ============================================================
-- §12  PARTITION MAINTENANCE EVENT
-- ============================================================
--
-- This MySQL EVENT runs on the 25th of each month and:
--   1. Adds a partition for the month after next (2-month buffer)
--   2. Drops the partition older than the retention window
--      (factory_settings.raw_log_retention_days default = 90 days)
--
-- ENABLE the MySQL event scheduler in my.cnf:
--   event_scheduler = ON
--
-- ============================================================

DELIMITER $$

CREATE EVENT IF NOT EXISTS evt_maintain_machine_log_partitions
ON SCHEDULE EVERY 1 MONTH
STARTS (DATE(NOW()) + INTERVAL (25 - DAY(NOW())) DAY)   -- runs on 25th of each month
ON COMPLETION PRESERVE
ENABLE
COMMENT 'Add next month partition and drop partitions older than retention window'
DO
BEGIN
    DECLARE next_month_start DATE;
    DECLARE next_month_end   DATE;
    DECLARE drop_before_date DATE;
    DECLARE partition_name   VARCHAR(30);
    DECLARE drop_name        VARCHAR(30);
    DECLARE sql_stmt         TEXT;

    -- Target: add partition for the month-after-next (2-month buffer)
    SET next_month_start = DATE_FORMAT(NOW() + INTERVAL 2 MONTH, '%Y-%m-01');
    SET next_month_end   = next_month_start + INTERVAL 1 MONTH;
    SET partition_name   = CONCAT('p_', DATE_FORMAT(next_month_start, '%Y_%m'));

    SET sql_stmt = CONCAT(
        'ALTER TABLE machine_logs REORGANIZE PARTITION p_future INTO (',
        'PARTITION ', partition_name,
        ' VALUES LESS THAN (TO_DAYS(''', DATE_FORMAT(next_month_end, '%Y-%m-01'), ''')),',
        'PARTITION p_future VALUES LESS THAN MAXVALUE)'
    );

    SET @ddl = sql_stmt;
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    -- Drop partition older than 90 days (adjust month math as needed)
    SET drop_before_date = DATE_FORMAT(NOW() - INTERVAL 3 MONTH, '%Y-%m-01');
    SET drop_name        = CONCAT('p_', DATE_FORMAT(drop_before_date, '%Y_%m'));

    -- Only drop if it exists (guard against double-execution)
    SET @drop_ddl = CONCAT('ALTER TABLE machine_logs DROP PARTITION ', drop_name);
    PREPARE drop_stmt FROM @drop_ddl;
    EXECUTE drop_stmt;
    DEALLOCATE PREPARE drop_stmt;

END$$

DELIMITER ;


-- ============================================================
-- §13  RE-ENABLE CONSTRAINTS
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- §14  INDEX EXPLANATION REFERENCE
-- ============================================================
--
-- TABLE: machine_logs
-- ─────────────────────────────────────────────────────────────
-- idx_ml_machine_time (machine_id, logged_at)
--   The hottest index. Powers every time-series query:
--   "Show last 100 logs for Machine #7."
--   Partition pruning on logged_at + index seek on machine_id.
--
-- idx_ml_machine_status_time (machine_id, status, logged_at)
--   Downtime detection job: find contiguous 'fault' intervals.
--   "WHERE machine_id = ? AND status = 'fault' AND logged_at > ?"
--
-- idx_ml_part_time (part_id, logged_at)
--   Part production summary across machines:
--   "How many Part-X units were logged today?"
--
-- idx_ml_process_time (process_id, logged_at)
--   Process step monitoring: cycle time trend per routing step.
--
-- idx_ml_status_time (status, logged_at)
--   Factory-wide fault scan: "how many machines faulting right now?"
--   Runs against the current-hour partition only (partition pruning).
--
-- ─────────────────────────────────────────────────────────────
-- TABLE: machine_oee_daily
-- ─────────────────────────────────────────────────────────────
-- uq_oee_machine_shift_date (machine_id, shift_id, oee_date)
--   Enforces one OEE record per machine per shift per day.
--   Also serves as a lookup index for upsert operations.
--
-- idx_oee_factory_date (factory_id, oee_date)
--   Factory OEE dashboard: aggregate across all machines.
--   "AVG(oee_pct) WHERE factory_id = ? AND oee_date BETWEEN ?"
--
-- idx_oee_date_score (oee_date, oee_pct)
--   Alert query: "which machines scored below 70% OEE today?"
--   Scans only one day's rows and returns sorted scores.
--
-- ─────────────────────────────────────────────────────────────
-- TABLE: production_plans
-- ─────────────────────────────────────────────────────────────
-- uq_pp_machine_shift_date (machine_id, shift_id, shift_date)
--   Prevents double-scheduling a machine in the same shift.
--   Also the primary lookup for OEE job inserts.
--
-- idx_pp_factory_date (factory_id, shift_date)
--   Factory schedule view / Gantt chart query.
--
-- ─────────────────────────────────────────────────────────────
-- TABLE: machine_logs_hourly / machine_logs_daily
-- ─────────────────────────────────────────────────────────────
-- UNIQUE (machine_id, part_id, hour_start / log_date)
--   Allows safe INSERT ... ON DUPLICATE KEY UPDATE (idempotent
--   re-runs if aggregation job crashes mid-way).
--
-- idx_mlh_machine_hour / idx_mld_machine_date
--   Dashboard chart query: "give me 24-hour trend for Machine #7."
--


-- ============================================================
-- §15  MySQL CONFIGURATION RECOMMENDATIONS (my.cnf)
-- ============================================================
--
-- TARGET: 4 GB VPS, MySQL 8.0, InnoDB only
-- Apply to /etc/mysql/mysql.conf.d/mysqld.cnf (Ubuntu/Debian)
--        or /etc/my.cnf (CentOS/RHEL)
--
-- ─────────────────────────────────────────────────────────────
-- [mysqld]
--
-- # ── InnoDB Buffer Pool ─────────────────────────────────────
-- # Rule: 50–70% of total RAM for a dedicated DB server.
-- # 2 GB leaves 2 GB for OS, connections, and sort buffers.
-- innodb_buffer_pool_size         = 2G
-- innodb_buffer_pool_instances    = 2      # 1 per GB; reduces contention
--
-- # ── InnoDB Redo Log ────────────────────────────────────────
-- # Larger = fewer checkpoints = better write throughput.
-- # Must be consistent pair; MySQL 8 auto-configures if not set.
-- innodb_log_file_size            = 512M
-- innodb_log_buffer_size          = 64M
--
-- # ── InnoDB I/O ─────────────────────────────────────────────
-- # O_DIRECT bypasses OS page cache — avoids double-buffering.
-- innodb_flush_method             = O_DIRECT
-- innodb_io_capacity              = 400    # SSD: raise to 2000+
-- innodb_io_capacity_max          = 800    # SSD: raise to 4000+
-- innodb_read_io_threads          = 4
-- innodb_write_io_threads         = 4
--
-- # ── InnoDB Durability vs Throughput ────────────────────────
-- # 1 = fully durable (fsync per commit) — use for financials
-- # 2 = flush log per second — safe for IoT (lose ~1s on crash)
-- # 0 = no fsync — only for extreme throughput, risky
-- innodb_flush_log_at_trx_commit  = 2
--
-- # ── Temp Tables (for aggregation GROUP BY queries) ─────────
-- tmp_table_size                  = 256M
-- max_heap_table_size             = 256M
--
-- # ── Sort / Join Buffers (per connection) ───────────────────
-- # Keep these low — they multiply by max_connections.
-- sort_buffer_size                = 4M
-- join_buffer_size                = 4M
-- read_buffer_size                = 2M
-- read_rnd_buffer_size            = 4M
--
-- # ── Connections ────────────────────────────────────────────
-- max_connections                 = 150    # Laravel pool + IoT devices
-- thread_cache_size               = 32
-- wait_timeout                    = 300
-- interactive_timeout             = 300
--
-- # ── Binary Logging (enable for replication / point-in-time) ─
-- # Disable on a standalone VPS to reclaim I/O:
-- # skip-log-bin
-- # If enabled:
-- # log_bin                       = /var/log/mysql/mysql-bin.log
-- # binlog_format                 = ROW
-- # expire_logs_days              = 7
--
-- # ── Slow Query Log (enable during tuning) ──────────────────
-- slow_query_log                  = 1
-- slow_query_log_file             = /var/log/mysql/slow.log
-- long_query_time                 = 1      # seconds
-- log_queries_not_using_indexes   = 0      # too noisy with IoT writes
--
-- # ── Query Cache ─────────────────────────────────────────────
-- # Removed in MySQL 8.0 — no setting needed.
--
-- # ── Character Set ───────────────────────────────────────────
-- character_set_server            = utf8mb4
-- collation_server                = utf8mb4_unicode_ci
--
-- # ── Event Scheduler (for partition maintenance) ─────────────
-- event_scheduler                 = ON
--
-- # ── InnoDB File Per Table (required for COMPRESSED rows) ────
-- innodb_file_per_table           = ON     # default in MySQL 8
--
-- ─────────────────────────────────────────────────────────────
-- MONITORING QUERIES (run periodically)
-- ─────────────────────────────────────────────────────────────
--
-- Check buffer pool hit ratio (target > 99%):
--   SELECT
--     FORMAT(
--       (1 - (
--         SELECT variable_value FROM performance_schema.global_status
--         WHERE variable_name = 'Innodb_buffer_pool_reads'
--       ) / NULLIF((
--         SELECT variable_value FROM performance_schema.global_status
--         WHERE variable_name = 'Innodb_buffer_pool_read_requests'
--       ), 0)) * 100, 2
--     ) AS buffer_pool_hit_pct;
--
-- Check partition list:
--   SELECT partition_name, table_rows, data_length / 1024 / 1024 AS data_mb
--   FROM information_schema.partitions
--   WHERE table_schema = 'smartfactory'
--     AND table_name   = 'machine_logs'
--   ORDER BY partition_ordinal_position;
--
-- Check index usage (find unused indexes):
--   SELECT object_schema, object_name, index_name, count_read
--   FROM performance_schema.table_io_waits_summary_by_index_usage
--   WHERE object_schema = 'smartfactory'
--     AND count_read = 0
--     AND index_name IS NOT NULL
--   ORDER BY object_name;
--
-- ============================================================
-- END OF SCRIPT
-- ============================================================
