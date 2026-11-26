# Change log — 10 November 2026

**Project:** CityUMapNav (`/opt/lampp/htdocs/CityUMapNav`)
**Reference app (frozen, not modified):** `/opt/lampp/htdocs/MapNavagation`
**Purpose:** hand-off for merging this working copy with the version on the next handler's PC.

## How to use this log

1. Compare the md5/size of each file below with the copy on the other PC.
2. If a file is **identical**, nothing to do.
3. If a file differs, apply the changes described in §2 to that file instead of
   copying the whole tree — the other PC may contain newer unrelated work.
4. Files **not** listed here were not changed today (backend, database, schema,
   images, screenshots, `Nav/MapNavagation`). Do not overwrite them.
5. The 3D app (`Nav/MapNav3D`) and the shared preference contract are the
   riskiest area; read §2.9–§2.10 and §3 before merging.

### Environment used for verification

```bash
/opt/lampp/bin/php -l <file>                                  # every touched PHP file
/opt/lampp/bin/php -S 127.0.0.1:8123 -t public                # or Apache at
http://localhost/CityUMapNav/public/                          # the URL used below
google-chrome --headless=new --dump-dom <page>                # behaviour checks
```

No database, schema, API contract, `backend/` or `database/` file changed.
No screenshot was regenerated.

---

## 1. Files changed today (22)

`Modified` is the local mtime (HKT). md5 is informational: use it to detect which
files the other PC already has.

| # | File | Modified | Size | md5 | Change (short) |
|---|---|---|---|---|---|
| 1 | `includes/config.php` | 17:20 | 13,091 | `ff84dfe1a84ab23df3f32a5f2289c2d5` | Text tiers + labels, stepfree removed, `map3d_url()` no longer embeds settings |
| 2 | `includes/header.php` | 16:33 | 7,432 | `15e03cea455e14e97f91d6f067555264` | Pre-paint clamp to 100/125/150 |
| 3 | `includes/footer.php` | 11:34 | 3,406 | `e72f95cb092dee04e61aa51366c4b3a2` | Floating CTA + 3D links (morning round) |
| 4 | `includes/nav.php` | 11:34 | 2,649 | `f58547342ac141102eaeee2536365337` | **Map** item → `map3d_url()` (morning round) |
| 5 | `public/index.php` | 16:27 | 6,851 | `e916751f8bae7d23b1436682057d6d59` | Demo path gone, hero rename, one-question panel |
| 6 | `public/help.php` | 16:20 | 14,868 | `650b6ebb50a70e7f294ba6ff1d3b7876` | Manual accordion, shortcut table gone, 3D back link |
| 7 | `public/settings.php` | 16:27 | 15,634 | `8dab9f5e9c6153e94686e3002b20b5e2` | Three named size chips, labels, stepfree row removed |
| 8 | `public/icons.php` | 16:26 | 12,426 | `7cef3c40c4a8f69db9f896d774ba653f` | Step-free switch no longer pre-ticked from a cookie |
| 9 | `public/map.php` | 16:26 | 11,868 | `7c4d1514d6bfbc9d66d6a11762954728` | No stepfree route override |
| 10 | `public/api/prefs.php` | 16:26 | 4,115 | `4fd1ba11ac01bbed9ef9ac401c16e107` | `usertype` only; `stepfree` removed |
| 11 | `public/assets/js/settings.js` | 17:24 | 10,938 | `fd8841217a3fca96a4071428c6a95a83` | Named labels, single role store, `keepalive` |
| 12 | `public/assets/js/accessibility.js` | 17:24 | 13,786 | `5f91fadb031b3647b22709b3c8e34792` | 3 scales, label announcements, `storage` listener, `keepalive` |
| 13 | `public/assets/css/main.css` | 16:49 | 45,282 | `8d76f0a73e373c3c116ca9ff4ec8f5ab` | Removed flow/guide/shortcut CSS |
| 14 | `public/assets/css/mobile.css` | 16:31 | 15,102 | `8838ec4207dab560c83dbe3594e98c39` | `text-scale-200` → `text-scale-150` |
| 15 | `public/assets/css/accessibility.css` | 16:49 | 9,493 | `aae1c012a60048c00b7cd107ec5ed9af` | Removed `text-scale-200` rule |
| 16 | `Nav/MapNav3D/index.php` | 17:20 | 12,698 | `eb1b595f78898f713dc395424a1eb4de` | 3 scales, no stepfree, clean hand-off |
| 17 | `Nav/MapNav3D/app.js` | 17:38 | 79,857 | `761b252ad1221c7e92bb99062d8bd2dd` | Live inheritance (`applyInheritedSettings`) + listeners |
| 18 | `Nav/MapNav3D/style.css` | 16:49 | 21,875 | `61e30b47a903070715e37151e1ce92f3` | Brand px, toolbar clipping fix, suggest-list cap |
| 19 | `Nav/.htaccess` | 11:37 | 862 | `df0a6b22c363f6d1c78a152b4dbfef6a` | Deny source/data downloads (morning round) |
| 20 | `README.md` | 17:40 | 33,696 | `16ee2351868cec238995c3836dec1b79` | Docs: text tiers, 3D live inheritance |
| 21 | `docs/UI-GUIDE.md` | 16:56 | 49,688 | `68650b2b4992b2c3edb0eee103e3065d` | Docs: cookies, pages, settings |
| 22 | `docs/MODIFICATIONS.md` | 17:40 | 52,949 | `b4b20cda8d88088c9dfbc6744bc5a113` | §16 UI round + §17 inheritance fix |

> The morning round (11:34–11:37) is the 3D hand-off work: nav/footer links,
> floating CTA, and `Nav/.htaccess`. The afternoon round (16:20–17:40) contains
> the ten UI changes (§16 of `MODIFICATIONS.md`) and the inheritance fix (§17).

---

## 2. Per-file changes

### 2.1 `includes/config.php`

| Area | Before | After |
|---|---|---|
| Text sizes | `TEXT_SCALE_OPTIONS = [100, 125, 150, 200]` | `TEXT_SCALE_OPTIONS = [100, 125, 150]` |
| Text labels | none (percentages printed) | `TEXT_SCALE_LABELS = [100 => 'Normal', 125 => 'Large', 150 => 'Extra Large']` |
| Icon labels | `Large — 64 px`, `Extra large — 80 px`, `Maximum — 96 px` | `Normal`, `Large`, `Extra Large` (keys `large` / `xlarge` / `max` and `px` values unchanged) |
| Profile cookies | `user_prefs()` returned `usertype`, `stepfree`, `vibration` | returns `usertype`, `vibration` only |
| Save profile | `save_user_prefs(string $userType, bool $stepFree)` | `save_user_prefs(string $userType)`; no `stepfree` cookie |
| 3D route | `map3d_route_type()` forced `step_free` when the cookie was `1` | no override; maps `default_route` only |
| 3D link | `map3d_url()` appended `route_type`, `text_scale`, `usertype` | **clean URL**; only an explicitly passed `$query` is appended |

`map3d_url()` is the critical fix for the "two visits" bug: settings are now
resolved by the 3D app at runtime instead of being frozen into the href.

Callers to check on the other PC: any code calling `save_user_prefs($type, $bool)`
or reading `user_prefs()['stepfree']` must be updated. Current callers:
`public/index.php`, `public/settings.php`, `public/api/prefs.php`.

### 2.2 `includes/header.php`

* The pre-paint script now clamps the stored scale to the three valid values
  before adding the class:

```js
var scale = [100, 125, 150].indexOf(Number(saved.text_scale)) > -1
    ? Number(saved.text_scale) : 100;
b.add('text-scale-' + scale);
document.body.style.fontSize = scale + '%';
```

The class-removal list still mentions `text-scale-200` on purpose, so a stale
class from an old localStorage value is cleaned up.

### 2.3 `includes/footer.php`, `includes/nav.php`, `Nav/.htaccess` (morning round)

* `includes/nav.php`: the **Map** item uses `map3d_url()` (currently a clean URL).
* `includes/footer.php`: floating **Start navigating** CTA (`data-cta-start`,
  hidden on the map page via `$cta_page`), footer link **Map and routes (3D)**
  → `map3d_url()`, **Text-friendly map** → `Router::url('map')`.
* `Nav/.htaccess`: `Options -Indexes`; denies `.py|.pyc|.pyo|.sql|.md|.txt|.log|
  .ini|.yml|.yaml|.json|.sqlite|.db` and dot-files/caches under `/Nav/`.
  Keep this file when merging — it is a security control, not a UI change.

### 2.4 `public/index.php`

* Removed the whole `<section aria-labelledby="flow-title">` **The demo path**
  block (six steps + its **Open the user manual** button).
* Removed the duplicate **Browse resources** button from the how-it-works
  `.cluster`; only **Open the user manual** remains.
* Hero secondary button: **Browse large icons** → **Browse resources**.
* First-visit panel is now one question:
  * intro: “One quick question … `usertype` cookie”;
  * only the `<fieldset>` with `name="usertype"` remains; the
    `<fieldset>` with `name="stepfree"` is deleted;
  * submit button text is **Save my preference** (singular);
  * POST handler: `save_user_prefs($type)` (no `$needs`);
  * success notice: “Preferences saved. You can change them at any time in Settings.”
* The form still requires `profile-form=1` and the hidden `csrf` field.

### 2.5 `public/help.php`

* **Back to the map** now links to `map3d_url()` (3D map) instead of
  `Router::url('map')`.
* **User manual** converted from `<ul class="guide-list">` cards to the FAQ
  accordion pattern:

```html
<div class="faq">
    <details class="faq__item" open>
        <summary class="faq__question">Use the 3D campus map</summary>
        <div class="faq__answer"> … <ol>…</ol> … </div>
    </details>
    … five more …
</div>
```

* Six guides remain: 3D map, text-friendly map, map/screenshot switch, large
  icons, preferences (rewritten: one question, auto-save, default route used by
  the 3D map), high contrast. The 3D guide gained an **Exit** step.
* `<h3>Keyboard shortcuts</h3>` and the whole `.table-wrap` / `.shortcut-table`
  block were deleted. Keyboard support itself is unchanged.

### 2.6 `public/settings.php` + `public/assets/js/settings.js`

`settings.php`:

* POST handler now calls `save_user_prefs($userType)` (single argument).
* The **Text size** help text reads “Choose Normal, Large or Extra Large…”.
* Chips render the name from the new constant:
  `<?= e(TEXT_SCALE_LABELS[(int) $scale] ?? …) ?>` — three radios, values
  `100`, `125`, `150`.
* **Preferred icon size** help text no longer mentions `64 px`.
* **About you**: intro is singular (“The same question asked on your first
  visit … `usertype` cookie”); the **Specific accessibility needs** setting row
  and its `#stepfree` switch are deleted.
* File docblock updated (profile writes `usertype`, vibration stays separate).

`settings.js`:

* `LABELS` = `{ large: 'Normal', xlarge: 'Large', max: 'Extra Large' }`;
  new `SCALE_LABELS` = `{ 100: 'Normal', 125: 'Large', 150: 'Extra Large' }`.
* Preview shows the label (`Normal` / `Large` / `Extra Large`) instead of `%`.
* `payload()` no longer reads `#stepfree`; it still includes `usertype` from the
  checked radio and derives `large_text = text_scale >= 125`.
* `saveProfile()`:
  * returns early if no radio is checked;
  * writes the `usertype` cookie (180 days, `path=/`, `SameSite=Lax`);
  * mirrors the value with `SMN.prefs.set('usertype', value, { silent: true })`
    (fallback: manual localStorage merge), so `accessibility.js`'s in-memory
    copy cannot overwrite it later;
  * POSTs `{ usertype }` to `data-prefs-endpoint` with `keepalive: true`.
* `save()` also uses `keepalive: true` on its POST.
* `init()` no longer wires a `#stepfree` listener.

### 2.7 `public/assets/js/accessibility.js`

* `SCALES = [100, 125, 150]`.
* Added `SIZE_LABELS` and `ICON_LABELS`; the text-size and icon-size
  announcements now say **Normal / Large / Extra Large** instead of
  “125 percent” / `xlarge`.
* `#text-scale` range `aria-valuetext` also uses the label.
* New cross-tab sync at the end of the file:

```js
window.addEventListener('storage', function (event) {
    if (event.key !== STORAGE_KEY || !event.newValue) { return; }
    try {
        current = Object.assign({}, DEFAULTS, JSON.parse(event.newValue) || {});
        applyPrefs(current);
        syncControls(current);
    } catch (err) { /* ignore malformed values */ }
});
```

* `syncServer()` adds `keepalive: true`.
* `applyPrefs()` still removes the legacy `text-scale-200` class, then applies
  one of the three valid classes and the matching `body.style.fontSize`.

### 2.8 `public/icons.php`, `public/map.php`, `public/api/prefs.php`

* `icons.php`: `$stepFreeOnly` is now only driven by `?step_free=`; the
  `user_prefs()` / `stepfree` cookie pre-tick was removed. The visible switch
  still works, it just starts off unless the URL says otherwise.
* `map.php`: deleted the `$profile['stepfree']` block that forced the route type
  to `step_free`; the route type is `pref('default_route')`, validated against
  `ROUTE_TYPE_OPTIONS`.
* `api/prefs.php`: the profile block only reacts to `usertype` and calls
  `save_user_prefs($type)`. `stepfree` in the JSON body is ignored (old clients
  sending it do not error).

### 2.9 `Nav/MapNav3D/index.php`

* `$validScales = [100, 125, 150]` (was `[100, 125, 150, 200]`).
* Removed the whole **specific accessibility needs → route type** block
  (`$stepFree`, the cookie read and the forced `step_free` branch). The route
  is now `?route_type=` (validated) → `$_SESSION['prefs']['default_route']` →
  `time`.
* `<body>` no longer carries `data-stepfree`; it still has `data-user-type`,
  `data-default-route-type` and `data-text-scale`.
* Pre-paint script accepts only `100|125|150` and skips localStorage when the
  URL has `text_scale` (explicit deep link).
* The role still resolves `?usertype` → `usertype` cookie → session → Student;
  that order is intentional for explicit links.
* Docblock updated to describe the three inherited values.

### 2.10 `Nav/MapNav3D/app.js`

This is the file with the live-sync fix.

* `readSharedPrefs()` reads `localStorage['smartmapnav:prefs']`.
* `readSharedRole()` reads the `usertype` cookie; falls back to the shared
  store's `usertype` when cookies are blocked; maps to `Student|JULAC|Guest`.
* `applyInheritedSettings(force)`:
  * `force === true` ignores the URL-parameter guards (a change made after the
    page loaded must win, even if the URL still has an old parameter);
  * applies `text_scale` (three tiers) to `<html>` and calls
    `updateUIPositions()`;
  * applies `default_route` → `#route-type-select` (`fastest→time`,
    `smoothest→smooth`, `step_free→step_free`);
  * if the route changed **and** start/end are filled, calls
    `calculateRoute()` so an already-drawn route follows the new mode;
  * applies the role to `#role-select`; rebuilds the scene with `changeRole()`
    only if `nodeMeshes` already exists (on first load `refreshMap()` reads the
    select instead).
* Call sites:
  * once at top level **before `init()`** (so the selects are correct even if
    three.js/WebGL fails);
  * at the start of `loadData()` (before `refreshMap()`), so the first build
    already uses the right role/route;
  * again after `applyURLParams()`.
* Listeners: `storage` (key `smartmapnav:prefs`), `focus`, `pageshow`,
  `visibilitychange` all call `applyInheritedSettings(true)`.
  This is what makes an already-open 3D tab update immediately.
* `applyURLParams()` still accepts the legacy `?step_free=1` alias for old
  deep links; no app-generated link uses it.
* Kept: `#role-select` / `#route-type-select` ids, `start-id` / `end-id`,
  `updateURL()`, `changeRouteType()`.

### 2.11 `Nav/MapNav3D/style.css`

* `.brand` is fixed at `19px` (was `1.2rem`), `.brand-icon` at `28px`
  (was `1.75rem`), new `.brand h1 { margin: 0; font-size: 2em; }`. Fixed `px`
  is deliberate: the brand must not inflate with the text-size setting.
* Mobile block: `.brand` `16px`, `.brand-icon` `22px`.
* `.top-bar` no longer has `max-height: calc(100vh - 20px)` or
  `overflow-y: auto`; that combination clipped the `.suggest-list` dropdown.
* `.suggest-list` `max-height` is `min(50vh, 360px)` (was `260px`).
* Comment on `.route-type-select` updated (largest text size, not “150–200 %”).

### 2.12 `public/assets/css/main.css`, `mobile.css`, `accessibility.css`

`main.css` — deleted (no longer used by any page):

* `.guide-list`, `.guide`, `.guide h3`, `.guide ol`
* `.shortcut-table`, `.shortcut-table th/td`, `.shortcut-table thead th`
* `.table-wrap`
* `.flow-list`, `.flow-list li`, `.flow-list li::before`

`mobile.css`:

* `body.text-scale-200 .icon-grid` → `body.text-scale-150 .icon-grid`
  (single column at the largest size).
* The settings stacking rules now use only `body.text-scale-150`
  (`-200` selectors removed).
* Comments updated.

`accessibility.css`:

* Removed `body.text-scale-200 { font-size: 2rem; }`; the remaining rules are
  `text-scale-100` / `125` / `150`.
* Two comments updated (“large text”, not “200 % text”).

### 2.13 Documentation

* `docs/MODIFICATIONS.md`: appended **§16 “UI cleanup round”** (the ten
  presentation changes, with the file table) and **§17 “Fix: the 3D map lagged
  one visit behind Settings”** (symptom, root cause, fix, verification output).
  These two sections are the long-form reference for everything in this log.
* `docs/UI-GUIDE.md`:
  * profile cookies: two (`usertype`, `vibration`), not three;
  * landing page: demo path removed; one fieldset; no `name="stepfree"`;
  * help page: six guides in an accordion; keyboard-shortcut table removed;
  * settings: three named size radios, no specific-needs switch;
  * reflow test row now says **Extra Large**, not “200 %”.
* `README.md`:
  * accessibility summary: three named sizes (100/125/150);
  * responsive/screenshot/interaction tables: “Extra Large” instead of “200 %”;
  * landing page-by-page: no demo list, one fieldset;
  * help: manual is an accordion;
  * settings: stacks at Extra Large;
  * 3D page: settings are read live (`localStorage` → cookie → session), an
    explicit `?route_type=` still overrides.

---

## 3. Contracts that must not change when merging

| Item | Value |
|---|---|
| Shared store | `localStorage['smartmapnav:prefs']` — JSON with at least `text_scale`, `default_route`, `icon_size`, `vibration`, `sr_hints`, `high_contrast`, `language`; `usertype` is mirrored in when the role changes |
| Profile cookies | `usertype` (`student` / `julac` / `guest`), `vibration` (`0` / `1`); `stepfree` is obsolete and ignored |
| 3D link | `map3d_url()` must stay parameter-free for automatic links; only explicit `$query` is appended |
| 3D values | `route_type`: `time` / `smooth` / `step_free` (`fastest` / `smoothest` / `step_free` in Settings) |
| 3D DOM ids | `#role-select`, `#route-type-select`, `#start-id`, `#end-id` |
| First-visit form | `profile-form=1`, hidden `csrf`, `name="usertype"` |
| Settings controls | `data-pref="…"` switches, `name="text_scale"` (3 radios), `name="usertype"`, `id="default-route"`, `id="icon-size"`, `id="language"` |
| Endpoint | `<body data-prefs-endpoint="…">`, POST JSON + `X-CSRF-Token` |
| Same origin | The 3D app must stay under the same scheme/host/port as the main app, otherwise localStorage/cookies and the `storage` event do not apply |

If the other PC's version still has `name="stepfree"`, `#stepfree`,
`user_prefs()['stepfree']` or `save_user_prefs($type, $bool)`, those are the
places to reconcile — they belong to the removed feature, not to new work.

---

## 4. Verification performed

### 4.1 PHP lint

Every modified PHP file passes:

```text
/opt/lampp/bin/php -l includes/config.php
/opt/lampp/bin/php -l includes/header.php
/opt/lampp/bin/php -l public/index.php
/opt/lampp/bin/php -l public/help.php
/opt/lampp/bin/php -l public/settings.php
/opt/lampp/bin/php -l public/icons.php
/opt/lampp/bin/php -l public/map.php
/opt/lampp/bin/php -l public/api/prefs.php
/opt/lampp/bin/php -l Nav/MapNav3D/index.php
→ No syntax errors detected (all)
```

### 4.2 `map3d_url()` output

```text
/Nav/MapNav3D/index.php
/Nav/MapNav3D/index.php?route_type=smooth   (explicit caller query only)
```

### 4.3 Headless Chrome — real click flow

Open Settings, click **JULAC**, **Extra Large**, select **Smoothest**, then
click the page's own **Start navigating** link **once**:

```text
stored_role=julac
stored_route=smoothest
stored_scale=150
cookie_role=julac
settings_cta=/CityUMapNav/Nav/MapNav3D/index.php
first_click_role=JULAC
first_click_route=smooth
first_click_scale=150%
done=yes
```

### 4.4 Headless Chrome — first load + live tab change

```text
links_found=3
links_clean=true
load_role=JULAC
load_route=smooth          (server had rendered data-default-route-type=time)
load_scale=150%
live_role=Guest            (second tab wrote guest/fastest/100 %)
live_route=time
live_scale=100%
done=yes
```

### 4.5 UI round re-check (same harness run)

```text
demo_path_gone=true
hero_renamed=true
manual_accordion=12        (6 manual guides + 6 FAQ items)
shortcuts_gone=true
text_options=3
stepfree_gone=true
```

The temporary harness pages (`public/__verify_*.html`) were deleted after the
runs. `/opt/lampp/htdocs/MapNavagation` was not modified.

---

## 5. Known limits and follow-ups

* Screenshots in `docs/screenshots/` were **not** regenerated; the existing
  `14-text-200-percent-phone.png` still shows the old 200 % label.
* Old bookmarks/links that already contain `?route_type=…` or `?usertype=…`
  are still honoured as explicit overrides on first load (by design).
* `Nav/MapNav3D/app.js` still accepts `?step_free=1` as a legacy deep-link alias.
* Obsolete `stepfree` cookies on visitors' devices are ignored; they are not
  deleted (no need to invalidate anyone's browser state).
* If the other PC changed `includes/config.php`, `public/settings.php`,
  `public/assets/js/accessibility.js`, `public/assets/js/settings.js` or
  `Nav/MapNav3D/app.js` after these edits, merge by function/block, not by
  whole-file copy — those files contain both today's changes and earlier work.
* With the local XAMPP layout the project root is web-served, so this log is
  downloadable at `/CityUMapNav/log_10Nov.md` (like the existing `docs/*.md`).
  For production, point the document root at `public/`; do not copy this log
  into the deployed docroot.

### Quick merge checklist

- [ ] `includes/config.php`: 3 text tiers + labels, no stepfree, clean `map3d_url()`
- [ ] `public/index.php`: demo path gone, hero rename, one-question panel
- [ ] `public/help.php`: accordion manual, no shortcut table, 3D back link
- [ ] `public/settings.php` + `settings.js`: 3 chips, no stepfree, single role store
- [ ] `public/assets/js/accessibility.js`: 3 scales, labels, storage listener
- [ ] `public/icons.php` / `map.php` / `api/prefs.php`: no stepfree
- [ ] `Nav/MapNav3D/index.php` / `app.js` / `style.css`: live inheritance, brand px, dropdown fix
- [ ] CSS: no `flow-list` / `guide-list` / `shortcut-table` / `text-scale-200`
- [ ] Docs updated; screenshots still to refresh
