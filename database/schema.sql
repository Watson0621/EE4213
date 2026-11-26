-- =========================================================================
-- Smart Map Nav — reference database schema
-- =========================================================================
-- THIS FILE IS DOCUMENTATION ONLY.
--
-- The prototype runs entirely from src/MockData.php; no connection is opened
-- and no driver is required. The schema is included so the coursework report
-- can show how the mock arrays map onto a real relational store, and so the
-- next iteration can migrate without redesigning the model.
--
-- Written for SQLite 3; also valid for MariaDB 10.4+ except where noted.
-- =========================================================================

PRAGMA foreign_keys = ON;

-- -------------------------------------------------------------------------
-- Accessibility tags: wheelchair, visual, hearing, quiet
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS accessibility_tags (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    slug        TEXT NOT NULL UNIQUE,          -- 'wheelchair'
    label       TEXT NOT NULL,                 -- 'Wheelchair'
    description TEXT NOT NULL DEFAULT ''
);

-- -------------------------------------------------------------------------
-- The eight large icons (Epic 2.1)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS icons (
    id             TEXT PRIMARY KEY,           -- 'toilets'
    name           TEXT NOT NULL,
    category       TEXT NOT NULL,
    description    TEXT NOT NULL,
    svg_path       TEXT NOT NULL,              -- 'toilets.svg'
    step_free      INTEGER NOT NULL DEFAULT 1 CHECK (step_free IN (0, 1)),
    step_free_note TEXT NOT NULL DEFAULT '',
    sort_order     INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS icon_tags (
    icon_id TEXT NOT NULL REFERENCES icons(id) ON DELETE CASCADE,
    tag_id  INTEGER NOT NULL REFERENCES accessibility_tags(id) ON DELETE CASCADE,
    PRIMARY KEY (icon_id, tag_id)
);

-- -------------------------------------------------------------------------
-- Accessible resources (Epic 2.2 markers)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resources (
    id          TEXT PRIMARY KEY,              -- 'toilets-blue'
    icon_id     TEXT NOT NULL REFERENCES icons(id),
    type        TEXT NOT NULL,
    name        TEXT NOT NULL,
    lat         REAL NOT NULL CHECK (lat BETWEEN -90 AND 90),
    lng         REAL NOT NULL CHECK (lng BETWEEN -180 AND 180),
    step_free   INTEGER NOT NULL DEFAULT 1 CHECK (step_free IN (0, 1)),
    description TEXT NOT NULL DEFAULT '',
    building    TEXT NOT NULL DEFAULT '',
    floor       TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS resource_tags (
    resource_id TEXT NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
    tag_id      INTEGER NOT NULL REFERENCES accessibility_tags(id) ON DELETE CASCADE,
    PRIMARY KEY (resource_id, tag_id)
);

-- "Nearest locations" shown in the icon detail panel.
CREATE TABLE IF NOT EXISTS icon_nearest (
    icon_id     TEXT NOT NULL REFERENCES icons(id) ON DELETE CASCADE,
    resource_id TEXT NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
    distance_m  INTEGER NOT NULL CHECK (distance_m >= 0),
    PRIMARY KEY (icon_id, resource_id)
);

-- -------------------------------------------------------------------------
-- Routes (produced by the Python service in the real system)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS routes (
    id           TEXT PRIMARY KEY,             -- 'r1'
    name         TEXT NOT NULL,
    from_id      TEXT REFERENCES resources(id),
    to_id        TEXT REFERENCES resources(id),
    step_free    INTEGER NOT NULL DEFAULT 1 CHECK (step_free IN (0, 1)),
    distance_m   INTEGER NOT NULL CHECK (distance_m > 0),
    duration_min INTEGER NOT NULL CHECK (duration_min > 0),
    features     TEXT NOT NULL DEFAULT ''      -- JSON array of feature strings
);

-- Ordered polyline vertices: one row per point.
CREATE TABLE IF NOT EXISTS route_points (
    route_id TEXT NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
    seq      INTEGER NOT NULL,
    lat      REAL NOT NULL,
    lng      REAL NOT NULL,
    PRIMARY KEY (route_id, seq)
);

-- Turn-by-turn instructions (the accessible text alternative).
CREATE TABLE IF NOT EXISTS route_steps (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    route_id           TEXT NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
    seq                INTEGER NOT NULL,
    instruction        TEXT NOT NULL,
    distance_m         INTEGER NOT NULL DEFAULT 0,
    accessibility_note TEXT NOT NULL DEFAULT '',
    UNIQUE (route_id, seq)
);

-- -------------------------------------------------------------------------
-- Accessibility preferences
-- No user accounts in this prototype: a row is keyed by session id instead.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS accessibility_prefs (
    session_id    TEXT PRIMARY KEY,
    high_contrast INTEGER NOT NULL DEFAULT 0 CHECK (high_contrast IN (0, 1)),
    large_text    INTEGER NOT NULL DEFAULT 0 CHECK (large_text IN (0, 1)),
    text_scale    INTEGER NOT NULL DEFAULT 100
                  CHECK (text_scale IN (100, 125, 150, 200)),
    vibration     INTEGER NOT NULL DEFAULT 0 CHECK (vibration IN (0, 1)),
    sr_hints      INTEGER NOT NULL DEFAULT 1 CHECK (sr_hints IN (0, 1)),
    icon_size     TEXT NOT NULL DEFAULT 'large'
                  CHECK (icon_size IN ('large', 'xlarge', 'max')),
    default_route TEXT NOT NULL DEFAULT 'step_free'
                  CHECK (default_route IN ('fastest', 'smoothest', 'step_free')),
    language      TEXT NOT NULL DEFAULT 'en' CHECK (language IN ('en', 'zh-HK')),
    usertype      TEXT NOT NULL DEFAULT ''
                  CHECK (usertype IN ('', 'student', 'julac', 'guest')),
    stepfree      INTEGER NOT NULL DEFAULT 0 CHECK (stepfree IN (0, 1)),
    updated_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------------------
-- Indexes for the queries the UI actually makes
-- -------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_resources_icon     ON resources(icon_id);
CREATE INDEX IF NOT EXISTS idx_resources_stepfree ON resources(step_free);
CREATE INDEX IF NOT EXISTS idx_steps_route        ON route_steps(route_id, seq);
CREATE INDEX IF NOT EXISTS idx_points_route       ON route_points(route_id, seq);
