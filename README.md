# Smart Map Nav

An accessible campus navigation and wayfinding prototype for people with mobility,
visual and cognitive disabilities. Built as a university coursework prototype with
**plain PHP 8**, **vanilla JavaScript** and **Leaflet + OpenStreetMap** — no
framework, no Composer, no build step.

The interface is designed around one idea: **large icons, plain words, and a
text version of everything**. If the map fails, the directions still work.

> **Designers / 設計師：** start with [`docs/UI-GUIDE.md`](docs/UI-GUIDE.md) — a
> bilingual (English + 繁體中文) map of every file: what it is for, what you may
> change, and the seven things you must not break.
> 請先睇 [`docs/UI-GUIDE.md`](docs/UI-GUIDE.md) —— 中英雙語嘅檔案地圖。

---

## 1. Quick start

### Option A — PHP built-in server (recommended)

```bash
cd CityUMapNav
php -S localhost:8000 -t public
# then open http://localhost:8000
```

> On this machine PHP is not on `PATH`; use the XAMPP binary:
> ```bash
> /opt/lampp/bin/php -S localhost:8000 -t public
> ```

> **The 3D map needs Apache.** `Nav/MapNav3D` is a separate PHP application and
> the built-in server rooted at `public/` cannot reach it. Use Option B for the
> full experience (or start the dev server with `-t /opt/lampp/htdocs` and open
> `/CityUMapNav/public/`).

### Option B — XAMPP / Apache (already installed here)

The project already sits in the XAMPP web root, so with Apache running:

```
http://localhost/CityUMapNav/public/
```

No configuration needed: `includes/config.php` detects the base path from
`SCRIPT_NAME`, so every link works under either URL.

### Optional — 3D routing service

The 3D map's **Find Route** button asks `Nav/MapNav3D/api.php`, which proxies to
a Python service. Start it before the demo:

```bash
cd Nav/MapNav3D
python3 route.py          # Flask on http://127.0.0.1:5000
```

It needs the `MapNav` MySQL database (`flask`, `pymysql`) and an internet
connection for the page's CDN libraries (Three.js, Material Symbols).

### Requirements

| Requirement | Notes |
|---|---|
| PHP 8.0+ | Tested on 8.2.12. Uses `match`, `str_ends_with`, `never` return types |
| Extensions | `json`, `session`, `mbstring` (all standard) |
| Browser | Any modern browser. Tested in headless Chrome 375 / 768 / 1280px |
| Internet | Only for OpenStreetMap **tiles**. Everything else is local |

## Backend Quick Start (Epic 1.1)

The Flask routing API uses the MySQL database named `mapnev`. Make sure MySQL is
running and the database is available before starting the backend.

### Start the backend server

From the project root, run:

```bash
python backend/route.py
```

The server listens on `http://127.0.0.1:5000`. If Flask or PyMySQL is missing
from your Python environment, install those dependencies before starting it.
Keep this terminal open while testing the API.

### Routing API

```text
GET http://127.0.0.1:5000/api/route
```

| Parameter | Description |
|---|---|
| `from` | Start node ID |
| `to` | Destination node ID |
| `step_free` | `0` for a standard route; `1` for a step-free route |

Example request:

```text
http://127.0.0.1:5000/api/route?from=18&to=47&step_free=1
```

You can open that URL in a browser or request it from the frontend. The response
contains the ordered node path, node coordinates, and route edges.

### Run the automated tests

With the `mapnev` database available, run this from the project root:

```bash
python3 -m unittest backend/test_route.py
```

---

## 2. Folder structure

```
CityUMapNav/
├── public/                        # web root
│   ├── index.php                  # landing page + first-visit profile panel
│   ├── icons.php                  # EPIC 2.1 — large-icon interface
│   ├── map.php                    # EPIC 2.2 — Leaflet map
│   ├── help.php                   # EPIC 2.3 — help centre
│   ├── settings.php               # accessibility preferences
│   ├── .htaccess                  # optional pretty URLs / headers
│   ├── api/
│   │   ├── routes.php             # mock routing service (Epic 1.1-1.3)
│   │   ├── resources.php          # mock campus resource list
│   │   ├── progress.php           # mock route progression
│   │   └── prefs.php              # session-backed preferences (+ CSRF)
│   └── assets/
│       ├── css/  main.css · mobile.css · accessibility.css
│       ├── js/   main.js · accessibility.js · icons.js · map.js · settings.js
│       ├── icons/ 8 SVG icons, 64×64, currentColor
│       └── vendor/leaflet/       # Leaflet 1.9.4, vendored (BSD-2)
├── includes/
│   ├── config.php                 # constants, base path, prefs, escaping
│   ├── header.php                 # <head>, skip link, masthead, a11y toggle
│   ├── nav.php                    # main navigation
│   ├── footer.php                 # emergency contact + secondary nav
│   └── skip-link.php              # first focusable element
├── src/
│   ├── Router.php                 # route table, URL builder, 404
│   └── MockData.php               # icons, resources, routes, progress
├── Nav/                           # stand-alone 3D campus map (linked, not edited)
│   ├── .htaccess                  # no listings, blocks source/cache files
│   └── MapNav3D/                  # index.php · app.js · style.css · api.php · route.py
├── database/schema.sql            # reference schema (documentation only)
├── docs/
│   ├── UI-GUIDE.md                # designer's file map (EN + 繁體中文)
│   └── screenshots/               # 14 verified UI captures (phone + desktop)
└── README.md
```

---

## 3. Six-step demo script (≈12 minutes)

Open in this order. Every step exercises a different epic.

| # | URL | Do this | Show |
|---|---|---|---|
| 1 | `/` | Answer the two **first-visit** questions, then press the floating **Start navigating** | Profile panel (student/JULAC/guest + needs), one `h1`, skip link appears on first `Tab`; the CTA opens the 3D map in the saved route mode |
| 2 | `Nav/MapNav3D/` | Pick a **start** and **destination**, choose Fastest / Smooth / Step-Free, press **Find Route** | 3D building view, Directions panel, floor filters, role select; opens with your saved **text size, role and route mode**, and the **Exit** button returns home |
| 3 | `/icons.php` | Turn on **Show only step-free**, then click **Wheelchair** | Count drops `8 → 6`, URL becomes `?step_free=1&tags=wheelchair`, chips keep focus |
| 4 | same page | Press the **Accessible Toilets** tile | Bottom sheet on mobile / side panel on desktop; focus moves to the panel heading |
| 5 | same panel | Press **Navigate here** | The **text-friendly map** opens with `?to=toilets-blue&step_free=1` and a step-free route (the 3D app uses its own numeric node IDs) |
| 6 | `/help.php` | Press the **Accessibility** button in the header first, then read the FAQ and the **3D map** guide | High contrast + Extra Large text still work; emergency number is a `tel:` link |

**Failure path to show off** (30 seconds): open
`/map.php?simulate_error=1` — the API returns HTTP 503 and the UI shows
"Route unavailable — call the help centre on 3442 8888" with a `tel:` button,
instead of a blank map.

**Deep links worth keeping open in tabs**

- `/icons.php?open=toilets&step_free=1` — opens the panel on load
- `/help.php?focus=emergency` — jumps to and focuses the emergency block
- `/api/routes.php?to=lib&step_free=1` — raw JSON
- `/api/prefs.php` — current session preferences

---

## 4. Who owns what

| Epic | Owner | Where it lives | Status |
|---|---|---|---|
| **2.1 Large-icon visual interface** | **me** | `public/icons.php`, `assets/js/icons.js`, `assets/icons/*.svg` | Complete |
| **Frame + settings** | **me** | `includes/*`, `public/settings.php`, `css/*`, `js/accessibility.js` | Complete |
| 1.1–1.3 Python algorithms | teammate | Mocked in `src/MockData.php`, served by `public/api/*.php` | **Mocked** |
| 2.2 Map visualisation | teammate | `public/map.php`, `assets/js/map.js` | Complete (mock data) |
| 2.3 Help centre | teammate | `public/help.php` | Complete |

---

## 5. Accessibility: what was implemented and how it was verified

### 5.1 Implemented

- **Semantic landmarks** on every page: `header` / `nav[aria-label="Main"]` / `main#main-content` / `footer`.
- **One `h1` per page**, then `h2` → `h3`. No skipped levels.
- **Skip link** as the very first focusable element, moving real focus to `<main tabindex="-1">`.
- **3 px focus rings** on every interactive element, including inside Leaflet (`outline: none` never ships without a replacement).
- **48 px minimum touch targets** (56 px map buttons, 64 px primary CTAs).
- **Roving tabindex** in the icon grid: one tab stop, arrow keys move, `Home`/`End` jump, `Enter`/`Space` open.
- **Bottom sheet / side panel** with focus moved to the heading, `Escape` to close, focus returned to the originating tile, and a Tab trap while the sheet is modal.
- **`aria-live` status region** on every page; all filter, route and settings changes are announced.
- **Real form controls only** — `<input type="checkbox">`, `<input type="range">`, `<select>` — styled, never replaced by `div`s.
- **High-contrast mode** reaching **≥ 7:1**, and three named text sizes — **Normal / Large / Extra Large** (100 / 125 / 150 %) — via one `font-size` on `<body>` (everything is sized in `em`).
- **Reduced motion** honoured from the OS setting; the in-app toggle now controls **vibration** and stores `vibration=1` in a cookie.
- **Step-free text alternative** for the map (WCAG 1.1.1): the numbered route steps in the sidebar are generated from the same route object as the polyline.
- **No account, no tracking, no analytics.**

### 5.2 Automated verification (run during this build)

**Contrast maths — 27 colour pairs, all passing**

| Mode | Lowest ratio found | Requirement | Result |
|---|---|---|---|
| Light (AA) | 5.80:1 — success text on tinted background | 4.5:1 | PASS |
| Light (non-text UI) | 4.27:1 — input borders | 3:1 | PASS |
| High contrast (AAA) | 8.42:1 — danger text on a dark red tint | 7:1 | PASS |

**DOM audit — 5 pages, 0 issues**

Checked per page with `DOMDocument`: `lang`, `<title>`, landmarks, skip-link order,
duplicate IDs, unlabelled form controls, unnamed buttons/links, `img alt`, SVG
naming, dangling `aria-labelledby`/`describedby`/`controls` references, and
heading-level jumps.

**Functional tests — 41 assertions, all passing**

Page status codes (5), 404 routing, absence of PHP warnings, JSON validity and
shape for 3 endpoints, `405` on a bad method, `400` on bad query input, `404` on
an unknown route, **`403` on a preferences POST without the CSRF token**, and XSS
attempts in `?tags=` / `?focus=` being stripped rather than reflected.

**Visual verification — headless Chrome screenshots**

| Capture | Shows |
|---|---|
| `icons` @ 375px | 2-column grid, ≈165px tiles, sticky filter bar, horizontal chips |
| `icons` @ 768px | 3-column grid, ≈229px tiles, nav always visible |
| `icons` @ 1280px | 4-column grid, ≈282px tiles, wrapping chips |
| `icons?open=toilets` @ 1280px | Side panel; grid narrows to 3 columns |
| `icons?open=toilets` @ 375px | Bottom sheet over the grid, focus ring on the heading |
| `icons` @ 375px, Extra Large text | Falls back to **one column** — no clipping, no horizontal page scroll |
| High contrast @ 1280px | Black background, white text, yellow accents and icons |
| `map.php` @ 1280px | 70/30 split, green route, custom markers, text steps |

### 5.3 Sizing proof for Epic 2.1

| Viewport | Padding | Columns | Gap | Tile width | Spec floor |
|---|---|---|---|---|---|
| 375px | 16×2 | 2 | 8px | ≈165px | ≥120px ✅ |
| 768px | 24×2 | 3 | 16px | ≈229px | ≥160px ✅ |
| 1280px | 24×2 | 4 | 24px | ≈282px | ≥160px ✅ |
| 375px @ Extra Large text | 16×2 | **1** | 8px | ≈343px | readability ✅ |

Desktop uses **4 columns, not 6**: six columns at 1280px leave ≈180px tiles and the
≥18px labels wrap badly. Readable beats dense.

### 5.4 Keyboard map

| Where | Key | Action |
|---|---|---|
| Any page | `Tab` | Skip link → masthead → nav → main |
| Any page | `Esc` | Close menu / detail panel / route sheet |
| Icon grid | Arrows | Move between tiles |
| Icon grid | `Home` / `End` | First / last tile |
| Icon grid | `Enter` / `Space` | Open detail panel |
| Map | Arrows | Pan |
| Map | `+` / `−` | Zoom |
| Map | `Home` | Recentre on your location |

---

## 6. Assumptions

Documented because the brief said "make reasonable assumptions and note them".

1. **"CityU" means City University of Hong Kong.** Campus building names are real
   (Run Run Shaw Library, Bank of China (HK) Complex, Cheng Yick-chi Building,
   Student Residence, MTR Kowloon Tong Exit C). **Coordinates are invented**,
   clustered around 22.337 N, 114.173 E, and are not survey-grade.
2. **The five pages are physical files**, not pretty URLs. `src/Router.php` owns the
   route table, builds every link, resolves the active page for `aria-current`, and
   renders the 404. `public/.htaccess` adds cosmetic `/icons` URLs when
   `mod_rewrite` is available, but nothing depends on it.
3. **Two icons are deliberately not step-free** (`transport`, `parking`). Real
   campuses have kerbs and sloped bays, and the "Show only step-free" switch needs
   something to filter out for the demo (8 → 6 tiles).
4. **Preferences live in two places**: `localStorage` (per device, instant) and the
   PHP session via `api/prefs.php` (so the server renders the correct body classes
   and prevents a flash). On first visit the server state wins; after that the
   device state wins.
5. **`api/prefs.php` is an addition** to the folder structure in the brief, which
   asks for "localStorage + PHP session" — a session needs a write endpoint.
6. **Leaflet is vendored, not CDN-linked.** The brief allowed CDNs; a local copy
   means the demo cannot fail because of venue Wi-Fi.
7. **No Tailwind.** The brief said it was "acceptable"; custom CSS gives exact
   control over the 7:1 palette, needs no network and has no build step to explain.
8. **`database/schema.sql` is documentation.** No driver is loaded and no connection
   is opened; the prototype reads `src/MockData.php`.
9. **Testing was automated plus manual keyboard checks**, not moderated testing with
   disabled users. That is the biggest honest gap in this prototype.

---

## 7. Known limitations

- **The routing algorithms are mocked.** `MockData::findRoute()` picks the closest
  hardcoded route by haversine distance to its endpoints. There is no real
  pathfinding, no live traffic, no lift-out-of-service data, and no indoor routing.
  Epics 1.1–1.3 belong to a teammate.
- **繁體中文 is a placeholder.** The option is stored and switches `<html lang>`, but
  no strings are translated.
- **Map tiles cannot follow the high-contrast palette.** The map is decorative and
  fully duplicated in text, but the tiles stay in OpenStreetMap's own colours.
- **Tiles require internet.** Without it the map shows a flat background, markers
  still render, and the text directions are unaffected.
- **No persistence beyond the session.** A new session loses server-side
  preferences unless `localStorage` still holds them.
- **The contact form logs instead of sending.** `error_log()` only — there is no
  mail transport in the XAMPP VM.
- **`prefers-reduced-transparency` / `prefers-contrast`** are not honoured; the
  in-app toggles cover the same need.

---

## 8. Swapping the mocks for the real Python services

The JSON contracts are the integration point. Each API file repeats this in its
header comment; the short version:

```php
// public/api/routes.php — replace the MockData call with:
$ch = curl_init('http://localhost:5000/api/route?' . http_build_query([
    'from_lat' => $fromLat, 'from_lng' => $fromLng,
    'to_lat'   => $toLat,   'to_lng'   => $toLng,
    'step_free'=> $stepFree ? 1 : 0,
]));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
$raw = curl_exec($ch);

if ($raw === false) {
    // Keep the same error envelope the front-end already handles.
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Routing service unreachable']);
    exit;
}

echo $raw;   // Python returns {route, step_free, distance_m, duration_min, features}
```

Keep the PHP file as the public endpoint rather than calling Flask from the browser:
one error format, no CORS surprises, and any API key stays server-side.

| Endpoint | Mock source | Real source | Front-end impact |
|---|---|---|---|
| `routes.php` | `MockData::findRoute()` | Python 1.1–1.3 | none |
| `resources.php` | `MockData::resources()` | campus GIS | none |
| `progress.php` | `MockData::progress()` | Python 1.3 | none |
| `prefs.php` | `$_SESSION['prefs']` | stays PHP | none |

---

## 9. Attributions

- **Leaflet 1.9.4** — © 2010–2023 Volodymyr Agafonkin, © 2010–2011 CloudMade.
  BSD-2-Clause. Licence text shipped at `public/assets/vendor/leaflet/LICENSE`.
- **OpenStreetMap** tiles and data — © OpenStreetMap contributors, ODbL.
  <https://www.openstreetmap.org/copyright>
- **Icons** — drawn for this prototype as plain SVG, `viewBox="0 0 64 64"`,
  `currentColor`, no third-party icon font.
- **Fonts** — system stack only (`system-ui`, Segoe UI, Roboto, Noto Sans). No
  webfont requests.

---

## 10. AI self-review table

An honest record of what went wrong while building this, with the fixes, written
for the "AI Collaboration Assessment" section of the report.

| # | Issue | File | Severity | Fix | What the AI initially got wrong |
|---|---|---|---|---|---|
| 1 | `MockData::routes()` left unclosed during a chunked edit → `PHP Parse error: unexpected token "public"` broke every page | `src/MockData.php` | **Critical** | Re-added the missing `];` and method brace; ran `php -l` after every structural edit | Generated a 600-line class in six slices and never re-parsed at a slice boundary. The 6 000-character tool limit made me "finish" a slice and move on without validating the seam. |
| 2 | Duplicate `id`s on `icons.php`: `svg-title-*-decor` once per tile *and* per detail template, plus `icon-panel-title` 8× | `src/MockData.php`, `public/icons.php` | High | Per-request instance counter in `iconSvg()`; per-icon `id="panel-title-<id>"` | Forgot the same icon appears twice on one page (tile + detail panel). Duplicate IDs break `aria-labelledby` and were caught by the DOM audit, not by reading the code. |
| 3 | Dialog used `aria-labelledby` pointing at an id inside a `<template>` → dangling reference until JS cloned it | `public/icons.php`, `assets/js/icons.js` | Medium | Static `aria-label="Resource details"` fallback; `icons.js` renames the dialog to "<Icon> details" on open | Treated `<template>` content as if it were in the document. It is inert — I only found this by writing a checker that resolves every `aria-*` ID reference. |
| 4 | `accessibility.js` restored hard-coded defaults over the server-rendered classes, silently switching a session-set high-contrast mode back off | `assets/js/accessibility.js` | High | `readServerState()` — when `localStorage` is empty, adopt the classes PHP rendered | Assumed localStorage is always the newer truth. On a first visit it is empty, so "no preference" was being treated as "light mode, 100 %". Found only because the high-contrast screenshot came out identical to the normal one. |
| 5 | Map step text collapsed to one character per line — three `<span>`s appended into a 2-column grid, so the third landed in the 44 px badge column | `assets/css/main.css`, `assets/js/map.js` | High | `::before` pinned to `grid-column: 1; grid-row: 1 / span 4`; all spans to `grid-column: 2; min-width: 0` | Wrote a "badge + text" grid then let JS append three children. Code review would never have caught it; the screenshot did. |
| 6 | Settings rows had a ~220 px empty gap between label and switch on mobile | `assets/css/mobile.css` | Medium | `flex: 0 0 auto` on `.setting-row__text` inside the column layout | `flex: 1 1 220px` is fine in a row but becomes a **height** in `column` direction. Reused a desktop rule in a mobile context. |
| 7 | At 200 % text on a 375 px screen, tile labels clipped mid-word ("Navigatio") | `assets/css/main.css`, `assets/css/mobile.css` | High (WCAG 1.4.4) | `max-width: 100%` + `overflow-wrap: anywhere` + `hyphens: auto`, and the grid drops to **one column** at `text-scale-200` | Tested the grid only at 100 % and assumed `overflow-wrap` on `<body>` was enough. Flex items default to `min-width: auto`, so they refuse to shrink below their content width. |
| 8 | `POST /api/prefs.php` had no CSRF protection in the first draft — any third-party page could flip a visitor's contrast mode | `public/api/prefs.php`, `assets/js/accessibility.js` | High (security) | `X-CSRF-Token` header compared with `hash_equals()`; returns **403** without it; asserted in the test suite | Treated preferences as "harmless UI state" and skipped the token. It is still state-changing, and CSRF is CSRF. |
| 9 | `database/schema.sql` lost the `route_steps` closing `);` and its `UNIQUE` constraint during a chunked edit | `database/schema.sql` | Low (doc file) | Re-inserted the constraint and closing parenthesis | Same root cause as #1 — slice-boundary editing without re-reading the seam. Documentation-only, but it would have been embarrassing in the report. |
| 10 | `includes/` and `src/` were reachable over HTTP when the project is served from the XAMPP document root | `includes/.htaccess`, `src/.htaccess`, `includes/config.php` | Medium | `Require all denied` in both folders plus an `APP_RUNNING` guard that exits 403 on direct hits | Designed for `php -S -t public`, where those folders sit outside the web root, and forgot the second supported deployment. |
| 11 | Filter chips re-flowed the row when selected, shifting neighbouring chips sideways | `assets/css/main.css` | Low | Fixed-size `::before` checkbox glyph that reserves space whether checked or not | Put the tick in the label text, so the box appeared out of nowhere on selection. Layout shift on a filter is a real annoyance for motor-impaired users. |
| 12 | First plan used the Tailwind CDN and a CDN-hosted Leaflet, making the demo depend on venue Wi-Fi and 7:1 contrast harder to guarantee | `includes/header.php` | Medium | Vendored Leaflet, hand-written CSS, no webfont | Defaulted to "CDN for speed" without asking whether the demo machine is online. Self-corrected at planning time, not at runtime. |
| 13 | Chose 6 grid columns at desktop in the first CSS pass | `assets/css/mobile.css` | Low | 4 columns at 1280 px (≈282 px tiles) | Optimised for density instead of the ≥18 px label and ≥160 px tile constraints. The maths looked fine; the mock-up did not. |
| 14 | `role="application"` on the map without a documented keyboard model would trap screen-reader users in application mode | `public/map.php`, `assets/js/map.js` | Medium | `aria-describedby` instructions, explicit arrow / `+` / `−` / `Home` handlers, live-region announcements, and a full text alternative below the map | `role="application"` disables browse mode. I used it because the brief asked for it, and only made it defensible by implementing and documenting the keyboard contract. |
| 15 | Nav was hidden on mobile by default, so **no-JS users had no menu at all** | `assets/js/main.js`, `assets/css/mobile.css` | High | Nav renders expanded server-side; JS *collapses* it on small viewports, so it degrades to visible | Assumed JavaScript always runs. Progressive enhancement is easy to say and easy to break by writing `.site-nav { display: none }` for the mobile default. |
| 16 | On a phone the map opened **zoomed out to the whole district** instead of the campus | `assets/js/map.js` | High | `fitRoute()` invalidates the Leaflet size on the next animation frame, then fits bounds; plus a `ResizeObserver` on the container | Fitted the route immediately after drawing it. Leaflet caches container dimensions, and the map's height comes from `calc(100vh - 220px)` — if the fit runs before layout settles, it picks a far lower zoom. Only a 375×812 capture exposed it. |
| 17 | The mobile route sheet was **invisible**: Leaflet's panes (200–800) painted over a sheet set to `z-index: 120` | `assets/css/mobile.css` | High | Sheet raised to `z-index: 900`, above Leaflet's highest control | Assumed a fixed-position element with a z-index beats a positioned child without one. Leaflet's internal panes *are* the positioned children, with much higher z-index values. |
| 18 | Collapsed sheet cut the "Route summary" heading in half | `assets/css/mobile.css` | Medium | Collapsed state hides everything except the handle, the live status line and any error | Set a 96px max-height and hoped. Hiding instead of clipping turns the collapsed sheet into a useful summary ("520 m, about 8 min, 5 steps"). |
| 19 | Map +/− buttons ended up behind the sheet and unhittable on mobile | `assets/css/mobile.css` | Medium | Controls move to the top-right under 1024px, where no sheet can cover them | Positioned controls bottom-right for every viewport, then added a bottom sheet that owns exactly that corner. |
| 20 | Route load and route failure were announced **two or three times** (status region + alert block + live region) | `assets/js/map.js` | Medium (a11y) | One speaker per event: `setStatus()` writes to `role="status"` for success, the `role="alert"` block speaks for failures, and `SMN.announce()` is reserved for pan/zoom/recentre | Added `SMN.announce()` as a belt-and-braces extra on top of `aria-live` elements. Duplicate announcements are worse than none for a screen-reader user. |
| 21 | Landing page: quick-start section had no top margin, and the "what you can do" card touched the tip text on phones | `public/index.php`, `assets/css/mobile.css` | Low | Added the missing `landing-section` class; `margin-top` on `.hero__aside` under 768px, reset above | Styled four sections with a shared class and forgot the fifth. Visual capture showed an obviously tighter gap than its neighbours. |

### Process notes for the report

- **The 6 000-character edit limit shaped the code, and two parse errors came
  directly from it** (#1, #9). Mitigation after the first failure: `php -l` after
  every structural edit, never after a batch.
- **Reading code did not find the layout bugs; screenshots did** (#5, #6, #7).
  My "this looks fine" judgement was wrong three times in a row — the clearest
  evidence in this project that automated visual checks are not optional.
- **Writing the audit scripts changed the code.** Three fixes (#2, #3, #4) exist
  only because I built checkers that resolve `aria-*` references and compare the
  rendered body classes against the session state. Confirmation bias is real: I
  believed all three were already correct.
- **The brief's literal wording was wrong twice and I chose differently** (#12): a
  7:1 palette is easier to prove with hand-written CSS, and a vendored Leaflet
  cannot fail on a venue network. Both are documented as deliberate deviations.
- **What I still cannot claim:** no moderated testing with disabled users, no
  NVDA/VoiceOver pass, no lift-out-of-service data, no real routing. Those are the
  honest gaps in an otherwise verified build.

---

## 11. Test commands used in this build

```bash
# 1. Syntax check every PHP file
for f in $(find . -name '*.php' -not -path './public/assets/vendor/*'); do
  /opt/lampp/bin/php -l "$f"
done

# 2. Serve and smoke-test
/opt/lampp/bin/php -S 127.0.0.1:8000 -t public &
for p in index icons map help settings; do
  curl -s -o /dev/null -w "$p %{http_code}\n" "http://127.0.0.1:8000/$p.php"
done

# 3. API contract + error paths
curl -s 'http://127.0.0.1:8000/api/routes.php?to=lib&step_free=1' | head -20
curl -s -o /dev/null -w '%{http_code}\n' 'http://127.0.0.1:8000/api/routes.php?simulate_error=1'  # 503
curl -s -o /dev/null -w '%{http_code}\n' -X POST 'http://127.0.0.1:8000/api/prefs.php'            # 403

# 4. Responsive screenshots (headless Chrome)
google-chrome --headless=new --no-sandbox --window-size=375,2000 \
  --user-data-dir=/tmp/chrome-375 --screenshot=/tmp/icons-375.png \
  'http://127.0.0.1:8000/icons.php'
```

The contrast and DOM audits are ~120 lines of PHP each; they were run against the
live server during the build and are described in section 5.2. They are not shipped
in `public/` because they are development tools, not application code.

---

## 12. UI specification — phone vs computer

Verified visually at **375×812** (phone) and **1280×900** (computer) with headless
Chrome in isolated profiles, so stored preferences could not leak between captures.

The evidence is committed in **`docs/screenshots/`** (14 files):

| File | Shows |
|---|---|
| `01-landing-phone.png` / `02-landing-desktop.png` | Hero and capability card (the demo banner, quick links and demo path shown were removed in the 2026-11 rework) |
| `03-resources-phone.png` / `04-resources-desktop.png` | 2-col vs 4-col grid, filter bar treatment |
| `05-detail-sheet-phone.png` / `06-detail-panel-desktop.png` | Bottom sheet vs sticky side panel, selected tile |
| `07-map-phone.png` / `08-map-desktop.png` | Collapsed route sheet + top-right controls vs 70/30 split |
| `09-help-phone.png` / `10-help-desktop.png` | Emergency block, guides, FAQ |
| `11-settings-phone.png` / `12-settings-desktop.png` | Preview above controls vs sticky right column |
| `13-high-contrast.png` | 7:1+ palette, icons recoloured |
| `14-text-200-percent-phone.png` | Extra Large text, single-column tiles, nothing clipped |

### Shared frame

| Element | Phone (≤767px) | Computer (≥768px) |
|---|---|---|
| Masthead | Static (scrolls away), 2 rows: logo + name, then 48px `Accessibility` and ☰ buttons | Sticky, single row: logo + name + tagline, `Accessibility` button, nav always visible |
| Navigation | Collapsible under ☰ (visible by default if JS fails) | Horizontal row; active item is solid blue with white text |
| Accessibility button | Opens a panel with **High contrast** + **Large text** + "All settings" | Identical, top-right |
| Content width | 1 column, 12–16px gutters | Centred, max 1200px, 24–48px gutters |
| Footer | Stacked: red emergency card (32px `tel:` number) → Explore → legal | 2 columns, then a legal row; the accessibility-statement block was removed |

### Page-by-page

| Page | Phone | Computer |
|---|---|---|
| **Landing** `/` | `h1` → full-width stacked CTAs → capability card → first-visit profile panel → how-it-works cards | Hero splits 2-up, CTAs inline, profile panel with one fieldset, how-it-works 3-across |
| **Resources** `/icons.php` | Sticky filter bar (switch + horizontal chips) → **2-col grid**, ~165px tiles, 64px icon, 18px label → tile tap opens a **bottom sheet** | Filter bar becomes a card → **4-col grid**, ~282px tiles → detail opens a **sticky side panel** (grid narrows to 3 columns) |
| **Map (3D)** `Nav/MapNav3D/` | Stand-alone 3D view: top bar (floors, role, route type, start/destination), resource buttons, location sensor panel, Directions sheet, fixed **Exit** button | Same layout, 3D building canvas; inherits **text size, role and route type** from Settings, read live on load (`localStorage` → cookie → session); an explicit `?route_type=time|smooth|step_free` still pre-selects the mode |
| **Text-friendly map** `/map.php` | Map fills the screen under the toolbar; map/screenshot switch top-left, controls (◎ + −) top-right; route summary is a **collapsed sheet** showing live status; curated resource cards below | **70/30 split**: map left, route sidebar right (start point, destination, route type, stats, numbered steps) |
| **Help** `/help.php` | Emergency block first (2em phone number, full-width call button) → **User manual** as an accordion → FAQ accordion → contact form | Same accordion, capped at 78ch for line length |
| **Settings** `/settings.php` | **Live preview above** the controls; Display, About you and Routing groups stack; **changes save as you tap them** (no Save button; Reset stays) | Preview is a **static right column** (grows with the page at Extra Large text); controls left; auto-save notice and Reset inline |

### Interaction states worth showing in the demo

| State | Where |
|---|---|
| Selected tile (4px blue border + tint) | `icons.php`, after opening a panel |
| Filtered count "6 of 8 resources shown (filtered)" | `icons.php` with step-free on |
| Focus ring on a programmatically focused heading | `icons.php` bottom sheet |
| Error path: "Route unavailable" + `tel:` button | `map.php?simulate_error=1` |
| High contrast: black / white / yellow, icons recoloured | `icons.php` with the header toggle on |
| Extra Large text: single-column tiles, nothing clipped | `icons.php` at 375px with the **Extra Large** text chip |

### Responsive breakpoints actually used

| Width | Effect |
|---|---|
| < 768px | Static masthead (so the filter bar can stick), collapsed nav, 2-col grid, bottom sheet, sheet-owned map bottom |
| ≥ 768px | Sticky masthead, inline nav, 3-col grid, 160px tiles, non-sticky filter card |
| ≥ 1024px | Side panel instead of bottom sheet, 70/30 map split, sticky settings preview, guides 3-across |
| ≥ 1280px | 4-col grid, 176px tiles, 3-col footer |




