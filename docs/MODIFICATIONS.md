# UI & User-Preference Rework — Modification Record

**Date:** 9 November 2026
**Scope:** public pages, shared includes, CSS/JS, mock APIs, reference schema, docs
**Status:** implemented and verified locally (`/opt/lampp/bin/php -S 127.0.0.1:8123 -t public`)

This document records what changed, why it changed, and every file that was
touched, so the next person can review, extend or roll the work back.

---

## 1. Summary of the 14 requested changes

| # | Request | Result | Primary files |
|---|---|---|---|
| 1 | Delete the demo-mode block | Demo banner markup, CSS and JS removed | `public/index.php`, `assets/css/main.css`, `assets/js/main.js` |
| 2 | "Start navigating" always visible | Fixed floating CTA on every page except the map | `includes/footer.php`, `assets/css/main.css`, `assets/css/mobile.css`, `accessibility.css`, `main.js` |
| 3 | Remove "Start with what you need" and fold it into Resources | Section deleted; the same resources already live on the Resources page | `public/index.php`, `assets/css/main.css`, `assets/css/mobile.css`, `accessibility.css` |
| 4 | How-it-works + demo path need a link to a user manual | New **User manual** section in Help Centre + two "Open the user manual" buttons | `public/help.php`, `public/index.php` |
| 5 | No public accessibility promise | Section removed from the landing page | `public/index.php` |
| 6 | "Need help right now?" must not be white; use red text | Transparent card, red border and red/pink text on the footer blue | `assets/css/main.css`, `assets/css/accessibility.css` |
| 7 | Remove the accessibility statement from the public site | Statement section + footer block/link deleted | `public/help.php`, `includes/footer.php` |
| 8 | Settings + info must show fully at any font size | Preview is no longer sticky; columns stack at 150–200 % text; rows wrap | `assets/css/mobile.css`, `assets/css/main.css` |
| 9 | Reduce motion → vibration toggle, cookie `vibration=1` | Preference renamed and repurposed; cookie written server- and client-side | `includes/config.php`, `includes/header.php`, `public/settings.php`, `public/api/prefs.php`, `assets/js/accessibility.js`, `assets/js/settings.js`, `assets/js/main.js` |
| 10 | User type + specific needs on first visit and in Settings, cookies `usertype` / `stepfree` | First-visit panel + "About you" group + cookie helpers + API support | `includes/config.php`, `public/index.php`, `public/settings.php`, `public/api/prefs.php`, `public/icons.php` |
| 11 | Icon size must affect every icon, including the top bar | Header brand, nav icons and Accessibility glyph now scale from `--icon-size` | `assets/css/accessibility.css` |
| 12 | Map: choose map preview or screenshot; right side has source, destination, route type (fastest / smoothest / step-free) | Map-view switch, screenshot figure, origin select, three route types | `public/map.php`, `assets/js/map.js`, `includes/config.php`, `public/api/routes.php`, `public/assets/img/map-preview.png` |
| 13 | Do not show "Features" on the map page | Stat row removed and JS write removed | `public/map.php`, `assets/js/map.js` |
| 14 | Replace "Directions in plain text" with an accessible-resource showcase | Section deleted; curated CityU resource cards added | `public/map.php`, `assets/css/main.css`, `assets/js/map.js` |

Supporting changes: `database/schema.sql`, `README.md`, `docs/UI-GUIDE.md`.

---

## 2. Files changed at a glance

| File | Type | What changed |
|---|---|---|
| `includes/config.php` | PHP | `vibration` replaces `reduce_motion`; new `USER_TYPE_OPTIONS`, `USER_COOKIE_DAYS`, `user_prefs()`, `set_user_cookie()`, `save_user_prefs()`; route types rewritten; `body_classes()` emits `vibration-on`; cookie fallback in `a11y_prefs()` |
| `includes/header.php` | PHP | Pre-paint script syncs `vibration-on` instead of `reduce-motion`; `<body>` now carries an inline `font-size` for the chosen percentage (and the pre-paint script updates it) |
| `includes/footer.php` | PHP | Floating "Start navigating" CTA added (hidden on the map); accessibility-statement block removed; `$cta_page` guard |
| `public/index.php` | PHP | Demo banner, quick links and accessibility promise removed; first-visit profile panel + POST handler; user-manual buttons; copy updates |
| `public/map.php` | PHP | Map/screenshot switch, screenshot figure, origin select, new route-type chips with `data-route-label`/`data-route-hint`, Features stat removed, plain-text section replaced by resource showcase |
| `public/help.php` | PHP | Guides renamed to **User manual** (`id="user-manual"`, 5 guides + shortcut table); statement section deleted; FAQ and focus-link copy updated |
| `public/settings.php` | PHP | `vibration` in the POST handler and Display group; new **About you** group; cookies written via `save_user_prefs()` / `set_user_cookie()`; switch On/Off labels removed; text size is a radio group; the separate **Large text** toggle removed (derived from the percentage) |
| `public/icons.php` | PHP | "Show only step-free" pre-ticked from the `stepfree` cookie |
| `public/api/prefs.php` | PHP | Accepts `vibration`, `usertype`, `stepfree`; sets cookies; response includes `profile`; `large_text` derived from `text_scale` (a legacy `large_text` payload still selects 125 %) |
| `public/api/routes.php` | PHP | Accepts `from` resource id and `route_type` (`fastest`/`smoothest`/`step_free`); response echoes both |
| `public/assets/css/main.css` | CSS | Demo banner and quick-link rules removed; welcome panel, floating CTA, map-view switch, screenshot, resource cards, quiet `#route-status`; footer emergency red palette; Leaflet marker centring fix; footer bottom padding for the CTA; `.settings-layout > *` min-width fix and `.setting-block` / `.text-size-field` rules |
| `public/assets/css/mobile.css` | CSS | CTA + welcome panel + map switch mobile spacing; settings form bottom padding; settings grid/preview fixes and 150/200 % stacking; setting rows wrap; chip-row `overflow-x` + settings chips wrap; `.setting-row__text` flex reset above 768 px (fixes the text overflow) |
| `public/assets/css/accessibility.css` | CSS | Focus/forced-colors lists updated; footer emergency high-contrast palette; icon-size rules now cover header icons; in-app reduced-motion block removed (OS media query kept); print hides the CTA; the `body.text-scale-*` font-size rules were replaced by the inline `font-size` (classes remain layout hooks) |
| `public/assets/js/main.js` | JS | Demo-banner handler removed; `initFloatingCta()` added (optional vibration) |
| `public/assets/js/accessibility.js` | JS | `vibration` in defaults/state/apply; `writeCookie()`; cookie write + haptic confirm in `setPref()`; `SMN.vibrate()`; applies the chosen percentage directly as `body.style.fontSize` and derives `large_text` from it |
| `public/assets/js/settings.js` | JS | New route labels; payload includes `usertype`/`stepfree`; Yes/No label sync; `vibration` default |
| `public/assets/js/map.js` | JS | Origin select, route-type hint, map/screenshot toggle, `from`/`route_type` parameters, Features write removed, dead `textStepsEl` references removed, haptic confirmation |
| `public/assets/img/map-preview.png` | PNG (new) | 1600×1000 screenshot of the map page used by the "Screenshot" view (≈760 KB) |
| `database/schema.sql` | SQL | `reduce_motion` → `vibration`; `default_route` CHECK now `fastest/smoothest/step_free`; new `usertype` and `stepfree` columns |
| `README.md` | Docs | Demo script, feature list, folder comment, layout tables updated |
| `docs/UI-GUIDE.md` | Docs | Mode table (Vibration), cookie note, page-by-page descriptions updated |
| `docs/MODIFICATIONS.md` | Docs (new) | This record |

**Removed (no longer referenced anywhere):** `.demo-banner`,
`.demo-banner p`, `.quick-links`, `.quick-link`, `.quick-link__meta`,
`body.reduce-motion`, `#route-steps-text`, `#stat-features`,
`data-demo-banner` / `data-demo-dismiss`, `.footer-a11y`.

---

## 3. Requirement-by-requirement detail

### R1 — Demo mode removed
`public/index.php` no longer renders the `section.demo-banner`. The matching
rules (`.demo-banner`, `.demo-banner p`) were deleted from `main.css`, and
`initDemoBanner()` plus its `sessionStorage` key were removed from `main.js`.
No markup, style or script refers to the demo banner any more.

### R2 — "Start navigating" is always on screen
`includes/footer.php` now renders, on every page except the map:

```html
<a class="cta-float" href="…/map.php" data-cta-start>
    <span class="cta-float__icon" aria-hidden="true">➜</span>
    <span class="cta-float__text">Start navigating</span>
</a>
```

* `main.css` positions it `fixed` bottom-right (`z-index: 850`, 56 px tall,
  pill shape, 3 px border, honours `env(safe-area-inset-bottom)`).
* It is hidden by `body.page-map .cta-float` and `body.panel-open .cta-float`.
* `mobile.css` tightens the offsets on phones.
* `accessibility.css` gives it a `ButtonBorder` outline in forced-colours mode
  and hides it when printing.
* `main.js` → `initFloatingCta()` fires `SMN.vibrate(30)` when the visitor has
  Vibration on.
* `.site-footer__legal` gained 72 px bottom padding so the button never covers
  the legal text.

### R3 — "Start with what you need" removed
The whole `quick-links` section (and its `$quickIcons` loop) was deleted from
`index.php`. The same resources remain available on **Resources**
(`icons.php`) which lists all eight icons with filters. `.quick-links`,
`.quick-link`, `.quick-link__meta` and the `.quick-link` focus exception were
removed from the stylesheets.

### R4 — User manual in the Help Centre
* `help.php`: the former "Step-by-step guides" section is now
  `<section id="user-manual">` with `<h2>User manual</h2>` and five guides
  (plan a route, map vs screenshot, large icons, preferences, high contrast)
  plus the keyboard-shortcut table. `?focus=manual` focuses the section.
* `index.php`: **"Open the user manual"** buttons in "How Smart Map Nav works"
  and in "The demo path", both linking to
  `help.php?focus=manual#user-manual`.

### R5 — Accessibility promise removed
The `a11y-promise` section was deleted from `index.php`. Nothing else linked to
`?focus=statement` from the landing page afterwards.

### R6 — Red "Need help right now?" card
`main.css` `.footer-emergency` is no longer a white card:

```css
background: transparent;
border: 3px solid #ff8a8a;
color: #ffb3b8;               /* 6.9:1 on the #08386f footer */
```

The phone number uses `#ffd7d9` (8.8:1). `accessibility.css` keeps a
transparent card in high-contrast mode with the same red border and text
(≈11:1 on black).

### R7 — Accessibility statement removed
`help.php` no longer contains the statement section (its useful keyboard table
moved into the user manual). `footer.php` lost the `.footer-a11y` block and the
"Read the accessibility statement" link, so no dead link remains.

### R8 — Settings and preview always fully visible
* `mobile.css` ≥1024 px: `.settings-preview` is **static** (it used to be
  `position: sticky`, which made its bottom unreachable when it was taller than
  the viewport at 200 % text).
* Grid is `minmax(0, 1fr) minmax(300px, 420px)` with `min-width: 0` on children.
* `body.text-scale-150` / `text-scale-200` collapse the layout to one column and
  put the preview first.
* `.settings-form` gets bottom padding so Save/Reset clear the floating CTA.
* `.setting-row` wraps at ≥1024 px and `.switch-field` uses `flex: none;
  margin-left: auto`, so a long help line can no longer push the switch outside
  the block.

### R9 — Vibration replaces reduce motion
* `A11Y_DEFAULTS['reduce_motion']` → `'vibration'`;
  `body_classes()` emits `vibration-on`; `a11y_prefs()` also reads the
  `vibration` cookie when the session has no value.
* Settings row: **Vibration** — “Short buzzes for route and status changes on
  this device. Stored in the `vibration` cookie.”
* `accessibility.js`: `writeCookie('vibration', …)` inside `setPref()`, a short
  buzz when switched on, and `SMN.vibrate(pattern)` used by `map.js` after a
  route loads and by the floating CTA.
* `api/prefs.php` and the `settings.php` POST both call
  `set_user_cookie('vibration', …)`.
* The OS `prefers-reduced-motion` media query is unchanged, so motion is still
  reduced for anyone who asked the operating system.

### R10 — User type and specific needs
`includes/config.php` now defines:

```php
const USER_TYPE_OPTIONS = [
    'student' => ['label' => 'Student', …],
    'julac'   => ['label' => 'JULAC',   …],
    'guest'   => ['label' => 'Guest',   …],
];
const USER_COOKIE_DAYS = 180;

user_prefs(): array           // validated read of usertype / stepfree / vibration
set_user_cookie($n, $v)       // 180-day cookie, Path=/, SameSite=Lax, not HttpOnly
save_user_prefs($type, $bool) // writes usertype + stepfree
```

* **First visit** (`index.php`): when `$_COOKIE['usertype']` is missing, a
  `section.welcome-panel` asks *"Which describes you best?"* (Student / JULAC /
  Guest) and *"Do you have specific accessibility needs?"* (Yes → `stepfree=1`,
  No → `stepfree=0`). The form POSTs to the same page with `profile-form=1` and
  the CSRF token; the handler calls `save_user_prefs()` and the panel disappears
  immediately because `$_COOKIE` is updated too.
* **Settings** (`settings.php`): an **About you** group repeats the same two
  controls inside the main settings form; the POST handler stores them.
* **Effect of `stepfree=1`**: the map defaults to the step-free route type and
  `icons.php` pre-ticks "Show only step-free" when the URL does not say
  otherwise.
* **JS path**: `settings.js` adds `usertype` / `stepfree` to the save payload and
  `api/prefs.php` accepts them, so the instant-save path and the no-JS POST path
  stay in sync.

### R11 — Icon size affects every icon
`accessibility.css` no longer exempts header icons. All three options derive
from `--icon-size`:

| Element | Rule | At 64 px | At 96 px |
|---|---|---|---|
| Icon grid / tiles | `svg.icon` (`main.css`) | 64 px | 96 px |
| Brand tile | `.brand__mark` `0.75 ×` | 48 px | 72 px |
| Brand glyph | `.brand__mark svg.icon` `0.5 ×` | 32 px | 48 px |
| Nav icons | `.nav-link__icon` `0.4375 ×` | 28 px | 42 px |
| Accessibility glyph | `.a11y-button__icon svg` `0.4375 ×` | 28 px | 42 px |
| Resource cards | inherits `svg.icon` | 64 px | 96 px |

### R12 — Map view switch, start point, three route types
* `map.php` adds a `<fieldset class="map-view-switch">` with two radio chips:
  **Map preview** (default) and **Screenshot**. The screenshot is
  `public/assets/img/map-preview.png` inside `figure#map-screenshot[hidden]`,
  with alt text and a caption explaining it is a still image.
* `map.js` → `setMapView(mode)` toggles `#map`, the `hidden` attribute and
  `.map-controls`, adds `.is-screenshot` to `#map-stage`, calls
  `map.invalidateSize()` when returning, and announces the change. "Show on map"
  in the resource cards switches back to the live map automatically.
* The sidebar now has **Start point** (`#origin`) and **Destination**
  (`#destination`) selects plus three route-type radios:
  **Fastest** (stairs and escalators allowed), **Smoothest — no stairs**
  (escalators allowed) and **Step-free — lifts only**.
* `ROUTE_TYPE_OPTIONS` in `config.php` is the single source: labels,
  descriptions and the `data-route-label` / `data-route-hint` attributes.
* `map.js` sends `from=<id>`, `to=<id>`, `step_free=0|1` and `route_type=…`.
  `api/routes.php` validates `route_type`, resolves the `from` resource to
  coordinates and returns `route_type`, `from` and `to` in the JSON.

### R13 — No "Features" on the map page
The `<dt>Features</dt>` row in `dl.route-stats` was removed and `map.js` no
longer writes `#stat-features`. The API still returns `features` for other
consumers.

### R14 — Resource showcase instead of plain-text directions
The `section.map-text-routes` (including `#route-steps-text`) was deleted. In
its place, `section.map-resource-list` now renders **Accessible resources at
CityU**: eight curated places (`toilets-blue`, `lift-lobby`, `ramp-library`,
`help-main`, `mtr`, `parking-p1`, `sus`, `lib`) as cards with the icon, name,
description, step-free flag, a **Show on map** button (`data-focus-resource`)
and a **Navigate here** link (`?to=`). The numbered **Step by step** list in the
sidebar remains the WCAG 1.1.1 text alternative, and `map.js` no longer
references the removed list.

---

## 4. Cookies and storage

| Cookie | Values | Lifetime | Written by | Read by |
|---|---|---|---|---|
| `usertype` | `student` \| `julac` \| `guest` | 180 days | `index.php` (first visit), `settings.php`, `api/prefs.php` | `user_prefs()`, `index.php`, `settings.php` |
| `stepfree` | `1` \| `0` | 180 days | same as above | `user_prefs()`, `index.php`, `settings.php`, `icons.php`, `map.php` |
| `vibration` | `1` \| `0` | 180 days | `settings.php`, `api/prefs.php`, `accessibility.js` (`document.cookie`) | `a11y_prefs()`, `user_prefs()` |

All three use `Path=/`, `SameSite=Lax`, and `Secure` when the request is HTTPS.
They are intentionally **not** `HttpOnly` because `accessibility.js` must read
and write `vibration` instantly. `localStorage['smartmapnav:prefs']` still holds
the display preferences; the PHP session still holds the authoritative copy.

---

## 5. API changes

### `GET /api/routes.php`
New / changed parameters:

| Parameter | Values | Behaviour |
|---|---|---|
| `from` | resource id | Its coordinates become the route start |
| `route_type` | `fastest` \| `smoothest` \| `step_free` | Overrides the legacy `step_free` boolean: `fastest` allows stairs, the other two filter to no-stairs routes |
| `step_free` | `1` \| `0` | Still supported for old links |

New response keys: `route_type`, `from`, `to` (existing keys unchanged).

### `POST /api/prefs.php`
Accepts `vibration` (bool), `usertype` (validated against `USER_TYPE_OPTIONS`)
and `stepfree` (bool), writes the three cookies and returns `profile` alongside
`prefs`. The CSRF header requirement is unchanged.

---

## 6. Configuration changes (`includes/config.php`)

* `A11Y_DEFAULTS`: `vibration => false` (replaces `reduce_motion`);
  `default_route` comment now lists `fastest | smoothest | step_free`.
* `ROUTE_TYPE_OPTIONS`: `fastest`, `smoothest`, `step_free` (the old `quiet`
  option is gone).
* `USER_TYPE_OPTIONS`, `USER_COOKIE_DAYS`, `user_prefs()`, `set_user_cookie()`,
  `save_user_prefs()`.
* Flag loop and `body_classes()` use `vibration` / `vibration-on`.
* `a11y_prefs()` falls back to the `vibration` cookie when the session has not
  stored one yet.

---

## 7. New asset

`public/assets/img/map-preview.png` — 1600×1000 PNG (≈760 KB), captured with
headless Chrome from the running map page (header, map, route line and route
summary). It is loaded lazily and only visible when **Screenshot** is selected.
**Regenerate it if the map page layout changes**, for example:

```bash
/opt/lampp/bin/php -S 127.0.0.1:8123 -t public &
google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars \
  --window-size=1600,1000 --virtual-time-budget=18000 \
  --screenshot=public/assets/img/map-preview.png \
  http://127.0.0.1:8123/map.php
```

---

## 8. Verification performed

| Check | Command / action | Result |
|---|---|---|
| PHP syntax | `/opt/lampp/bin/php -l` on every edited PHP file | all “No syntax errors detected” |
| Pages respond | `curl` `/index.php`, `/map.php`, `/help.php`, `/settings.php`, `/icons.php` | HTTP 200 for all five |
| Demo/promise/statement gone | `grep` for “Demo mode”, “Start with what you need”, “accessibility promise”, “Accessibility statement” | no matches |
| Plain-text section + Features gone | `grep` for “Directions in plain text”, “Features”, `route-steps-text` in `map.php` | no matches |
| First-visit panel | `curl` without cookies | `section.welcome-panel` present |
| Profile POST | `curl -c jar -d "csrf=…&profile-form=1&usertype=student&stepfree=1"` | `Set-Cookie: usertype=student`, `Set-Cookie: stepfree=1`; panel absent from the response |
| Cookie effects | `curl -b jar /icons.php` and `/index.php` | step-free switch pre-checked; `<body class="… vibration-on …">` when the cookie is 1 |
| Settings POST | full form POST | `Set-Cookie` for `vibration`, `usertype`, `stepfree`; body classes update in the same response |
| Prefs API | `POST` JSON `{vibration:true, usertype:"julac", stepfree:false}` | `ok: true`, three `Set-Cookie` headers, `profile` echoed |
| Routes API | `?from=mtr&to=lib&route_type=smoothest` and legacy `?to=sports&step_free=0` | JSON with `route_type`, `from`, `to`; correct route for each |
| Map rendering | headless Chrome screenshot | green step-free polyline, lettered markers, no route error |
| Settings layout | headless Chrome screenshot at 1600 px | preview column and controls fully visible; Vibration switch inside the block |
| Removed CSS/JS | `grep -rn` for `reduce_motion`, `textStepsEl`, `stat-features`, `demo-banner`, `quick-link` | only a historical comment remains |

---

## 9. Known limitations and follow-ups

1. **`smoothest` is mocked.** The PHP mock only knows “no stairs”, so
   `smoothest` and `step_free` currently return the same candidate routes. A
   real router should treat “escalator allowed” as a separate edge cost.
2. **The screenshot is static.** It shows the map page at capture time; it does
   not follow the currently selected origin/destination. Regenerate it after
   layout changes (see §7).
3. **`docs/screenshots/` is historical.** Those 14 captures predate this rework
   (they show the demo banner, quick links and the statement). They were left
   untouched as evidence of the earlier build; `README.md` now says so.
4. **No accessibility statement or promise is public any more**, by request. If
   the project later needs a WCAG conformance statement, it must be re-added;
   the removed text is available in the previous version of `help.php`.
5. **User type does not change content yet.** `usertype` is stored and shown in
   Settings but does not filter resources; only `stepfree` changes behaviour.
6. **Traditional Chinese** is still a stored placeholder, unchanged by this
   work.
7. **`setcookie()` must run before output.** The helpers guard with
   `headers_sent()`; a future page that echoes before the handler will silently
   not set the cookies.
8. **The "Screenshot" view is decorative.** It carries alt text and a caption,
   but the authoritative route description stays in the sidebar. Do not present
   the image as the only route information.

---

## 10. Rollback notes

Most changes are isolated. To revert selectively:

| Want to undo | Revert these |
|---|---|
| First-visit panel only | remove the `welcome-panel` block + POST handler in `index.php` and the **About you** group in `settings.php` (the cookies can stay harmlessly) |
| Floating CTA only | delete the `$cta_page` block in `footer.php` and the `.cta-float` rules in the three stylesheets |
| Map screenshot only | remove `figure#map-screenshot`, the map-view fieldset and `setMapView()` in `map.js`; delete `assets/img/map-preview.png` |
| Vibration only | restore `reduce_motion` in `config.php`, `header.php`, `settings.php`, `prefs.php`, the two JS files and `accessibility.css` |
| Route types only | restore the old `ROUTE_TYPE_OPTIONS` array and the old `match()` block in `map.php` |

Use the repository's normal diff tooling for the exact line-level changes; this
file records intent and locations rather than duplicating every line.

---

## 11. Follow-up round — same day (settings fixes + cookie verification)

Four requests were handled after the first round: remove the On/Off labels,
stop the Vibration help text overflowing its block, replace the text-size
slider with a multiple-choice control, and confirm the cookies work.

### 11.1 Settings switches no longer show "On" / "Off"

`public/settings.php`: the `<span class="switch-text">…</span>` inside every
`.switch-label` was removed (High contrast, Large text, Vibration, Extra
guidance text, and the Yes/No on "Specific accessibility needs"). The switch
track/thumb still shows the state visually, the checkbox still exposes it to
assistive technology, and the row label still names the control, so nothing was
lost for screen-reader users.

`public/assets/js/settings.js`: the code that updated the Yes/No text on change
was deleted (its target element no longer exists).

The `.switch-text` CSS class is still used by **Resources**
("Show only step-free"), so it was kept.

### 11.2 Vibration text overflowing the block — root cause and fix

Two independent causes were found and fixed:

1. **Phone width (≤767 px): the whole settings column was 416 px wide inside a
   360 px viewport.** The "Type of user" chip row could not shrink, and because
   the grid track was `1fr` its automatic minimum grew with the row, pushing
   every settings block outside the screen. Fixes:
   * `mobile.css`: `.chip-row` now has `overflow-x: auto` (the comment always
     claimed horizontal scrolling, but the rule was missing), so a long chip
     row can no longer widen its parent.
   * `mobile.css`: `.settings-group .chip-row` wraps (`flex-wrap: wrap;
     overflow-x: visible`) so every preference option is visible without a
     sideways swipe.
   * `main.css`: `.settings-layout > * { min-width: 0 }` lets grid items shrink.
2. **Tablet/desktop at large text: the help text would not wrap.** `mobile.css`
   sets `.setting-row__text { flex: 0 0 auto }` for the stacked phone layout,
   and the ≥768 px row layout never reset it. With `flex-shrink: 0` the text box
   stayed at its max-content width, so at 200 % text the Vibration sentence ran
   out of the card (and off the page). Fix: in the ≥768 px block,
   `.setting-row__text { flex: 1 1 240px; min-width: 0 }`.

Measured result (`document.documentElement.scrollWidth` vs `clientWidth`):
before `1268 / 1009` at 1024 px + 200 % text; after `1009 / 1009` — no
horizontal overflow. At 375 px: `360 / 360` at both 100 % and 200 %.

### 11.3 Text size is now a multiple-choice group

Replaced the `<input type="range">` slider with four radio chips
(100 % / 125 % / 150 % / 200 %).

* `public/settings.php`: new `.setting-block` containing a
  `<fieldset class="text-size-field">` with `<legend class="visually-hidden">`
  and a `.chip-row` of `input[type="radio"][name="text_scale"][data-text-scale]`
  (one per `TEXT_SCALE_OPTIONS` value). No JS is needed to submit: the radio
  value posts as `text_scale`, which the existing handler already validates.
* `public/assets/js/accessibility.js`: `syncControls()` now also checks the
  matching radio, and `init()` listens for `change` on
  `input[name="text_scale"]` and calls `setPref('text_scale', …)` with the same
  announcement as before. The legacy slider handling is kept, so an old page
  with a range still works.
* `public/assets/js/settings.js`: `payload()` reads the checked radio (falling
  back to the range), `reset()` checks the 100 % radio, and `refreshPreview()`
  listens to the radios so the live preview updates immediately.
* `public/assets/css/main.css`: added `.setting-block` / `.text-size-field`
  rules and removed the now-unused `.range-field`, `.range-labels` and
  `input[type="range"]` rules.

Runtime check (headless Chrome, real click on the 150 % chip):

```
before = sr-hints page-settings text-scale-100 icon-size-large
checked150 = true
after  = sr-hints page-settings large-text text-scale-150 icon-size-large
preview = 150%
localStorage = {"high_contrast":false,"large_text":true,"text_scale":150,…}
onOffLabelsInSettings = 0
```

No-JS check (plain POST `text_scale=150`):

```
Set-Cookie: vibration=1; … usertype=julac; … stepfree=1; …
<body class="vibration-on text-scale-150 icon-size-max page-settings">
<input … id="text-scale-150" name="text_scale" value="150" data-text-scale checked>
<dd id="preview-scale">150%</dd>
```

### 11.4 Cookie verification (confirmed working)

Every cookie was exercised end-to-end against the running app
(`php -S 127.0.0.1:8123 -t public`, fresh cookie jars, real HTTP headers).

| Test | Request | Observed result | Verdict |
|---|---|---|---|
| Set the profile | `POST /index.php` with `profile-form=1&usertype=student&stepfree=1` + CSRF | `Set-Cookie: usertype=student; Max-Age=15552000; path=/; SameSite=Lax` and `Set-Cookie: stepfree=1; …`; the welcome panel disappeared from the response | ✅ |
| Profile read back | `GET /icons.php` with that jar | "Show only step-free" rendered `checked` because `stepfree=1` | ✅ |
| Cookie → map routing | `GET /map.php` with the jar | `id="route-step_free" … checked` (the `stepfree` cookie forces the lifts-only route type) | ✅ |
| Settings write | `POST /settings.php` with `vibration=on&usertype=julac&stepfree=on&text_scale=150&icon_size=max&default_route=smoothest` | `Set-Cookie: vibration=1`, `usertype=julac`, `stepfree=1`; `<body class="vibration-on text-scale-150 icon-size-max page-settings">` | ✅ |
| Profile read back (Settings) | `GET /settings.php` with the jar | `usertype-julac` radio `checked`, "Specific accessibility needs" switch `checked` | ✅ |
| Vibration off | `POST /api/prefs.php` `{"vibration":false}` | `Set-Cookie: vibration=0`; next page has **no** `vibration-on` class | ✅ |
| Vibration on | `POST /api/prefs.php` `{"vibration":true}` | `Set-Cookie: vibration=1`; next page has `vibration-on` | ✅ |
| Tampered cookie ignored | `GET /index.php` with `usertype=admin` | Value rejected, welcome panel shown again | ✅ |
| Valid cookie respected | `GET /index.php` with `usertype=student` | No welcome panel | ✅ |
| Expiry / flags | every `Set-Cookie` header above | `Max-Age=15552000` (180 days), `path=/`, `SameSite=Lax`; `Secure` is added automatically over HTTPS | ✅ |

Cookie names and values are as designed: `usertype` = `student` / `julac` /
`guest`, `stepfree` = `1` / `0`, `vibration` = `1` / `0`.

---

## 12. Text size now sets the font size directly (Large text toggle removed)

The percentage chips are now the **single** text-size control. Choosing a
percentage writes the font size straight onto `<body>`, instead of a separate
on/off "Large text" switch triggering it through a CSS class.

### What changed

| File | Change |
|---|---|
| `includes/header.php` | `<body … style="font-size: <?= scale ?>%">` rendered server-side; the pre-paint script also sets `document.body.style.fontSize` so a localStorage value applies before paint |
| `public/settings.php` | **"Large text" row removed** from Display. The POST handler derives `large_text = text_scale >= 125`; the Text size help text says the percentage is applied straight to the page font size |
| `includes/config.php` | `a11y_prefs()` derives `large_text` from `text_scale` (one source of truth), so a stale session cannot disagree with the chosen percentage |
| `public/api/prefs.php` | `large_text` derived from `text_scale`; a legacy `large_text: true` payload selects at least 125 %, `false` selects 100 % |
| `public/assets/js/accessibility.js` | `applyPrefs()` sets `body.style.fontSize = scale + '%'`; `readServerState()` derives `large_text` from the parsed scale; `setPref('large_text', …)` still works for the header shortcut |
| `public/assets/css/accessibility.css` | Removed `body.large-text` / `body.text-scale-*` `font-size` rules (now redundant). The classes are still emitted because `mobile.css` uses `text-scale-150/200` as layout hooks |
| `docs/UI-GUIDE.md` | Mode table updated: text scale is an inline `font-size`; large text is a derived flag |

The **header Accessibility quick menu still has a "Large text" switch** — it is
now purely a shortcut: turning it on selects 125 %, turning it off selects
100 %, and the radio group, preview and stored percentage all follow.

### Verification (headless Chrome, real clicks inside the page)

```
initial                     | style=100% | computed=16px | scale=100 | preview=100%
click 200%                  | style=200% | computed=32px | scale=200 | preview=200%
click 100%                  | style=100% | computed=16px | scale=100 | preview=100%
header Large text ON        | style=125% | computed=20px | scale=125 | preview=125%
header Large text OFF       | style=100% | computed=16px | scale=100 | preview=100%
```

Server-side (no JavaScript needed), plain POST `text_scale=150`:

```
<body class="large-text text-scale-150 icon-size-large page-settings" … style="font-size: 150%">
<input … id="text-scale-150" name="text_scale" value="150" checked>
```

API checks:

| Payload | Result |
|---|---|
| `{"large_text":true}` | `large_text: true`, `text_scale` at least 125 |
| `{"large_text":false}` | `large_text: false`, `text_scale: 100` |
| `{"text_scale":200}` | `large_text: true`, `text_scale: 200` |
| `{"text_scale":100}` | `large_text: false`, `text_scale: 100` |

### Rollback

To restore the previous behaviour, re-add the "Large text" setting row in
`settings.php`, restore the `body.large-text` / `body.text-scale-*` font-size
rules in `accessibility.css`, and remove the two `body.style.fontSize` writes
(`header.php`, `accessibility.js`). Nothing else depends on the inline style.

---

## 13. "Text size not working" — cause and hardening

**Reported after §12:** selecting a percentage appeared to do nothing in the
browser.

**Root cause:** a stale-asset mismatch, not a logic bug. `accessibility.css` had
lost its `body.text-scale-*` font-size rules (because the size was now written
inline by `accessibility.js`). A browser that still had the **previous**
`accessibility.js` cached — the one that only toggled the class — ended up with
*new CSS without class rules* + *old JS without the inline style*, so clicking a
chip changed the radio and the class but not the font size. A hard refresh
fixed it, which is the classic symptom.

**Fixes applied (both, deliberately):**

1. **The class rules are back** in `accessibility.css` as a fallback, with the
   same values as the inline style:

   ```css
   body.text-scale-100 { font-size: 1rem; }       /* 16 px */
   body.text-scale-125 { font-size: 1.25rem; }    /* 20 px */
   body.text-scale-150 { font-size: 1.5rem; }     /* 24 px */
   body.text-scale-200 { font-size: 2rem; }       /* 32 px */
   ```

   The inline `font-size` still wins, but both paths agree, so an old cached
   script (class-only) and a new one (inline) both scale the page.

2. **Asset cache-busting** in `src/Router.php` → `Router::asset()` now appends
   `?v=<filemtime>` to every CSS/JS/image URL. Any edited asset gets a new URL,
   so browsers cannot serve a stale copy after an update. No manual cache
   clearing or hard refresh is needed.

**Verified (headless Chrome):**

| Scenario | Result |
|---|---|
| Normal click on 200 % | inline `font-size: 200%`, body 32 px, `h1` 64 px |
| Simulated old JS (remove inline style, class only) | body 24 px at `text-scale-150`, `h1` 48 px |
| Asset URLs | `/assets/css/accessibility.css?v=1794232854`, `/assets/js/accessibility.js?v=1794231844`, … |
| All five pages | HTTP 200, `<body … style="font-size: 100%">` |

**If it still looks wrong:** a full reload (`Ctrl`/`Cmd` + `Shift` + `R`) or
clearing the site data will pick up the versioned assets. After that, changing
the percentage takes effect on the same click and persists in the session,
localStorage and on every page via the server-rendered inline style.

---

## 14. Link-up with the 3D campus map (`Nav/MapNav3D`)

The team's 3D map (`MapNavagation/MapNav3D`) was copied into this project as
`CityUMapNav/Nav/MapNav3D`. The copy was verified **byte-identical** (md5 on
`index.php`, `app.js`, `api.php`, `style.css`, `route.py`); its application code
was **not edited**, and the original folder is untouched. It is a stand-alone
app, so CityU Map Nav only **links** to it.

### URL helpers (`includes/config.php`)

```php
define('MAP3D_URL', getenv('MAP3D_URL') ?: (BASE_PATH === '' ? '' : dirname(BASE_PATH)) . '/Nav/MapNav3D/index.php');

function map3d_route_type(): string   // fastest→time, smoothest→smooth, step_free→step_free
function map3d_url(array $query = []) // MAP3D_URL . '?route_type=…'
```

`map3d_route_type()` uses the visitor's saved `default_route`; the `stepfree`
cookie forces `step_free`, matching the rest of the app. The 3D app already
reads `route_type` (`time` / `smooth` / `step_free`) from the query string, so
it opens in the right mode. `MAP3D_URL` can be overridden with an environment
variable if the folder moves.

### Entry points changed

| Where | File | Target |
|---|---|---|
| Top-bar **Map** | `includes/nav.php` | `map3d_url()` |
| Floating **Start navigating** | `includes/footer.php` | `map3d_url()` |
| Hero **Start navigating** | `public/index.php` | `map3d_url()` |
| Footer “Map and routes (3D)” | `includes/footer.php` | `map3d_url()` |
| Footer “Text-friendly map” (new) | `includes/footer.php` | `Router::url('map')` |
| **Navigate here** / **Show route** | `public/icons.php` | unchanged: `map.php?to=<id>` |

The last row is deliberate: the 3D app routes between numeric node IDs from its
own `MapNav` database, so our mock place IDs (`toilets-blue`, `mtr`, …) cannot
be handed over. Destination-aware links therefore stay on the text-friendly map.

### Fallback kept visible

`public/map.php` is still the accessible, offline-capable map. Its toolbar and
sidebar now both carry an **“Open the full 3D map”** button, and it explains
that it is the text-friendly view. The 3D page is canvas-based and sets
`user-scalable=no`, so keeping this page one click away is intentional.

### User manual and demo copy

* `public/help.php`: the User manual gains **“Use the 3D campus map”** (start /
  destination, Find Route, Directions, floors, role, sensors/QR/calibrate) and
  the old guide was renamed **“Use the text-friendly map”**. The
  **Open the user manual** buttons are unchanged (`help.php?focus=manual#user-manual`).
* `public/index.php`: “How Smart Map Nav works” card 3 and demo-path steps 2, 5
  and 6 now describe the 3D map + text-friendly map.

### Protecting the copied app (`Nav/.htaccess`)

The copy sits under the web root, so Apache was serving `route.py`,
`__pycache__`, `.vs` and directory listings. A new `Nav/.htaccess` sets
`Options -Indexes`, denies source/notes/data extensions (`.py`, `.pyc`, `.txt`,
`.json`, `.sql`, …) and blocks `.vs` / `__pycache__`. No app file was changed.

| URL | Before | After |
|---|---|---|
| `Nav/MapNav3D/index.php`, `app.js`, `style.css`, `api.php?action=data` | 200 | **200** |
| `Nav/` (directory listing) | 200 | **403** |
| `route.py`, `qrcode.txt` | 200 | **403** |
| `.vs/VSWorkspaceState.json`, `__pycache__/route.cpython-314.pyc` | 200 | **403** |

### Prerequisites for the demo

| Item | State |
|---|---|
| `http://localhost/CityUMapNav/Nav/MapNav3D/` | 200 (Apache), UI renders |
| `api.php?action=data` (MySQL `MapNav`) | returns nodes / edges / floors |
| `python route.py` on `127.0.0.1:5000` | **not running** — Find Route shows the app's “cannot connect to Python” message until it is started |
| Three.js / Material Symbols / html5-qrcode | loaded from unpkg + Google Fonts → internet required |
| WebGL | required for the building view (the UI works without it) |
| `php -S -t public` | cannot serve the sibling `Nav/` folder — use Apache or `php -S -t <htdocs>` |

### Verification

| Check | Result |
|---|---|
| Route-type mapping | `fastest → time`, `smoothest → smooth`, `step_free → step_free` |
| `stepfree=1` cookie beats a “fastest” default | `route_type=step_free` |
| Index links to the 3D app | 4 (nav, CTA, hero, footer) |
| Map page links | 4 (nav, toolbar, sidebar, footer) |
| Resources “Navigate here” | still `map.php?to=…` |
| Manual card | “Use the 3D campus map” present |
| PHP lint | clean on all edited files |
| Original `MapNavagation` folder | md5 unchanged |

---

## 15. Round three — bigger CTA, 3D font/role inheritance, exit button, auto-save

The team allowed edits inside `Nav/MapNav3D`, so the 3D app now follows the
main app's accessibility settings. The original `MapNavagation` folder is still
untouched (md5 verified).

### 15.1 "Start navigating" is larger

`public/assets/css/main.css`, `mobile.css`:

| | Before | Now |
|---|---|---|
| Desktop `min-height` | 56 px | **64 px** (measured 65 px) |
| Desktop padding / font | 12/24 px, 1.05 em | **14/30 px, 1.2 em** (19.2 px) |
| Phone `min-height` | 56 px | **60 px**, 12/24 px, 1.08 em |

The footer legal padding grew to `space-6 + 92px` so the larger pill never
covers the last line. The 768 px media query needed an explicit override
because the mobile-first rule would otherwise win on desktop.

### 15.2 Text size reaches the 3D map

* `Nav/MapNav3D/style.css`: **all 13 `px` font sizes converted to `rem`**
  (12→0.75, 14→0.875, 16→1, 18→1.125, 20→1.25, 22→1.375, 28→1.75). The other
  26 font sizes were already `rem`, so one root value now scales every label,
  button, select and map label.
* `Nav/MapNav3D/index.php` renders
  `<html data-text-scale="150" style="font-size: 150%">`.
  Priority: `?text_scale` → session `prefs['text_scale']` → 100 (validated
  against 100/125/150/200). A pre-paint script prefers the same-origin
  `localStorage['smartmapnav:prefs']` value unless the URL sets it.
* `map3d_url()` in `includes/config.php` now passes `text_scale` explicitly,
  so it also works when the session is not shared (e.g. dev server).
* Safeguard: `.top-bar` gets `max-height: calc(100vh - 20px); overflow-y: auto`
  so a very large text size scrolls the toolbar instead of clipping it.

Measured with `?text_scale=150`: root **24 px**, `.brand h1` 57.6 px,
`.side-panel h2` 26.4 px, `.res-btn` 20.4 px, `#route-type-select` 21.6 px.

### 15.3 Exit button

`Nav/MapNav3D/index.php` + `style.css`: a fixed **Exit** pill at the bottom-left
(free in every breakpoint, visible even when the top bar is collapsed):

```html
<a class="exit-btn" href="…/public/index.php" aria-label="Exit to Smart Map Nav home">
    <span class="material-symbols-rounded">logout</span><span class="exit-text">Exit</span>
</a>
```

The href is computed from `SCRIPT_NAME` (three `dirname()` steps) so it works at
any deployment depth. Verified: `/CityUMapNav/public/index.php`.

### 15.4 Type of user and specific needs are inherited

`Nav/MapNav3D/index.php` reads, in order, **URL → cookie → session**:

| Value | From | Applied as |
|---|---|---|
| `usertype` | `?usertype` → `usertype` cookie → session | `#role-select` = Student / JULAC / Guest (`selected` in PHP, so `refreshMap()` builds the right node set; JULAC keeps its F3/library limit) |
| `stepfree` | `?stepfree` → `stepfree` cookie | forces the route type to `step_free` |
| `route_type` | `?route_type` (from `map3d_url()`) → needs cookie → session `default_route` | `time` / `smooth` / `step_free`, rendered as the `selected` option (so there is no flash before `applyURLParams()` runs) |

`<body>` carries `data-user-type`, `data-stepfree`, `data-default-route-type`;
`app.js` → `applyURLParams()` uses
`document.body.dataset.defaultRouteType || 'time'` instead of hard-coding
`time`. An explicit `?route_type=` still wins. `map3d_url()` also appends
`usertype` (when known) and `stepfree=1|0`.

Verified with real sessions:

| Session / cookies | Rendered |
|---|---|
| `text_scale=200`, `default_route=smoothest`, `usertype=student` | `font-size: 200%`, Student selected, smooth selected |
| `text_scale=125`, `default_route=fastest`, `usertype=julac`, `stepfree=1` | `font-size: 125%`, JULAC selected, **step_free** selected (the needs cookie wins) |

The controls remain editable if someone wants to override the inherited value.

### 15.5 Settings save automatically (no Save button)

`public/settings.php`
* `#settings-save` removed; a `<noscript>` submit button keeps the no-JS path
  working (the form still posts to itself and the existing handler validates it).
* New `.settings-autosave` notice (“Changes save automatically…”), and the lede
  now says every change applies immediately.
* **Reset to defaults** stays; it saves immediately.

`public/assets/js/settings.js`
* `saveBtn` wiring removed; the submit handler is kept for the no-JS fallback.
* New `saveProfile()` for the **About you** controls: writes the
  `usertype` / `stepfree` cookies immediately (so it works even if the network
  call fails) and POSTs `{usertype, stepfree}` to `api/prefs.php`, then updates
  the status line and announces it.
* `usertype` radios and the `#stepfree` switch now auto-save; all other
  controls already saved through `accessibility.js` → `syncPrefs`.

`public/assets/css/main.css`: `.settings-autosave` style (green info panel).

Verified in headless Chrome (clicking, not submitting):

```
saveButton=0 | autosaveNote=yes
click Vibration | click JULAC | click Specific needs
bodyClass=… vibration-on …
cookies=vibration=1; usertype=julac; stepfree=1
status=Profile saved automatically.
```

### 15.6 Files changed in this round

`includes/config.php`, `public/settings.php`, `public/assets/js/settings.js`,
`public/assets/css/main.css`, `public/assets/css/mobile.css`,
`Nav/MapNav3D/index.php`, `Nav/MapNav3D/app.js`, `Nav/MapNav3D/style.css`,
`README.md`, `docs/UI-GUIDE.md`, this file.

Nav's API/DB/routing were not touched; `api.php`, `route.py` and the MySQL
`MapNav` data remain as the team wrote them.

### 15.7 Panel positioning at large text

Two layout follow-ups were needed once the root font size could reach 200 %:

* `updateUIPositions()` in `Nav/MapNav3D/app.js` now also moves the **sensor
  panel** below the toolbar (unless the visitor has dragged it, detected via
  the existing `locPanelPos` localStorage key). It is registered **before**
  `init()` and re-run on `load`, on toolbar resize (`ResizeObserver`) and on
  `document.fonts.ready`, so a WebGL failure or late font load can no longer
  leave the panels overlapping a tall toolbar.
* `.route-type-select` uses `max-width: 14rem` instead of `190px`, so the
  selected route label stays readable when the text is enlarged.

Measured at `?text_scale=200` in a 1000 px-tall window: toolbar 361 px,
resource and sensor panels both at `top: 380.9px` (no overlap).

---

## 16. UI cleanup round (10 November 2026)

Ten presentation changes, applied after the settings-sync work in §15.

| # | Change | Result | Files |
|---|---|---|---|
| 1 | Remove the demo path | Landing page ends after the how-it-works cards; the six-step `<section id="flow-title">` and `.flow-list` CSS are gone | `public/index.php`, `public/assets/css/main.css` |
| 2 | Rename the hero button | **Browse large icons** → **Browse resources** (same `icons` target) | `public/index.php` |
| 3 | Remove the duplicate button | The second **Browse resources** in the how-it-works cluster is deleted; only **Open the user manual** remains | `public/index.php` |
| 4 | Manual as an accordion | The six guides use the same `<details class="faq__item">` pattern as the FAQ; the `.guide-list` cards are gone | `public/help.php`, `public/assets/css/main.css` |
| 5 | Remove the keyboard-shortcut table | `<h3>Keyboard shortcuts</h3>` + `.table-wrap` + `.shortcut-table` removed (the shortcuts still work) | `public/help.php`, `public/assets/css/main.css` |
| 6 | Manual's back link | **Back to the map** now points at the 3D map (`map3d_url()`) like every other "map" link | `public/help.php` |
| 7 | Text size: three named tiers | `TEXT_SCALE_OPTIONS = [100, 125, 150]` + `TEXT_SCALE_LABELS` (Normal / Large / Extra Large); 200 % removed from the API validators, `mobile.css`, `accessibility.css` and the pre-paint scripts | `includes/config.php`, `includes/header.php`, `public/settings.php`, `public/api/prefs.php`, `public/assets/js/*.js`, CSS, `Nav/MapNav3D/index.php` |
| 8 | Icon size labels | Same keys (`large` / `xlarge` / `max`) so the CSS is untouched; labels are now Normal / Large / Extra Large and no `px` is shown | `includes/config.php`, `public/settings.php`, `public/assets/js/settings.js` |
| 9 | Delete "Specific accessibility needs" | Removed from the first-visit panel and Settings, plus the `stepfree` cookie, `user_prefs()['stepfree']`, the icons pre-tick, the map.php override and the 3D hand-off. Route choice is now solely **Default route type** | `includes/config.php`, `public/index.php`, `public/settings.php`, `public/api/prefs.php`, `public/icons.php`, `public/map.php`, `public/assets/js/settings.js`, `Nav/MapNav3D/index.php` |
| 10 | 3D layout fixes | `.brand` / `.brand-icon` pinned to `px` so the text-size setting cannot inflate the header; `.top-bar` no longer has `max-height`/`overflow-y` (the cause of clipped `.suggest-list`); `.suggest-list` is capped at `min(50vh, 360px)` | `Nav/MapNav3D/style.css` |

Settings now reach every page immediately: `accessibility.js` listens for the
`storage` event, and the 3D app re-reads the shared prefs (see §17).

First-visit panel: one question (type of user). The POST handler calls
`save_user_prefs($type)`; `user_prefs()` returns `usertype` + `vibration` only.
Old `stepfree` cookies are simply ignored, so existing visitors are unaffected.

---

## 17. Fix: the 3D map lagged one visit behind Settings (10 November 2026)

### Symptom

Changing **About you** or **Default route type** did not reach
`Nav/MapNav3D` on the first visit. Going back to the site and clicking again
(or opening the map twice) showed the new value.

### Root cause

`map3d_url()` appended the current settings to **every** 3D link
(`?route_type=…&usertype=…&text_scale=…`) when the page was rendered. A link
rendered before the change therefore carried the **old** values, and both the
3D entry point (`?usertype` before the cookie, `?route_type` before the
session) and `app.js` (`applyInheritedSettings()` skipped URL parameters)
treated those parameters as authoritative. The second visit re-rendered the
link, which is why it appeared to need two refreshes. Refreshing the 3D page
did not help because the stale parameters stayed in its URL.

A second, smaller defect: `settings.js` → `saveProfile()` wrote `usertype`
directly to `localStorage` without updating `accessibility.js`'s in-memory
copy, so a later preference change could write the old object back.

### Fix

* `includes/config.php` → `map3d_url()` no longer adds any settings; it only
  appends a query that a caller passes explicitly (deep links). The 3D app
  resolves the values live: `localStorage` → cookie → session.
* `Nav/MapNav3D/app.js`:
  * `applyInheritedSettings(force)`: `force` ignores the URL-parameter guards,
    because a value changed after load must win. Explicit URL values still win
    on first load.
  * Called once before `init()` (so the selects are right even if WebGL fails),
    before the first `refreshMap()` (correct role node set), and after
    `applyURLParams()`.
  * `storage`, `focus`, `visibilitychange` and `pageshow` call it with
    `force = true`; a role change rebuilds the scene, a route-type change
    redraws an existing route immediately.
  * `readSharedRole()` falls back to the shared store when cookies are blocked.
* `public/assets/js/settings.js`: `saveProfile()` now uses
  `SMN.prefs.set('usertype', …, { silent: true })` (one shared store) and both
  POSTs use `keepalive: true`, so the session update survives an immediate
  click on the CTA.
* `public/assets/js/accessibility.js`: `syncServer()` also uses
  `keepalive: true`.
* `Nav/MapNav3D/index.php`: `$validScales` is `[100, 125, 150]`.

### Verification (headless Chrome 148)

Real click flow — open Settings, click **JULAC**, click **Extra Large**, set
**Smoothest**, then click the page's own **Start navigating** link once:

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

Live sync — a second tab writes the shared prefs (`guest` / `fastest` / 100 %):

```text
links_found=3
links_clean=true
load_role=JULAC
load_route=smooth      (server had rendered route_type=time)
load_scale=150%
live_role=Guest
live_route=time
live_scale=100%
done=yes
```

`load_route=smooth` while `server_route=time` proves the runtime override now
works on the **first** load; the live lines prove the `storage` event path.

Both harness pages were temporary (`public/__verify_*.html`) and were deleted
after the runs. The original reference app in `/opt/lampp/htdocs/MapNavagation`
was not touched.

### Files changed in this round

`includes/config.php`, `Nav/MapNav3D/index.php`, `Nav/MapNav3D/app.js`,
`public/assets/js/settings.js`, `public/assets/js/accessibility.js`,
`README.md`, this file.
