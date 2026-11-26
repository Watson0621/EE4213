# CityUMapNav — UI Design File Guide
# CityUMapNav — UI 設計檔案指南

> **Audience / 對象:** the team member who owns UI look-and-feel (tone, colour, type, arrangement).
> 負責 UI 外觀嘅組員（色調、字體、版面安排）。
>
> **Scope / 範圍:** where each file lives, what it is for, what you may change, and what you must not break.
> 每個檔案嘅位置、用途、可以改嘅地方，同絕對唔可以破壞嘅地方。
>
> **Version / 版本:** 1.0 · aligned with README v0.9.0 · 對應 README v0.9.0
>
> **Run it / 執行:** `cd CityUMapNav && /opt/lampp/bin/php -S 127.0.0.1:8000 -t public`

---

## 0. How to use this document / 這份文件點用

Every file entry has the same four parts:

每個檔案都用同一個四段格式：

| Marker | Meaning | 意思 |
|---|---|---|
| ✅ | **Safe to edit.** Pure presentation — colour, spacing, copy, imagery. | **可以改。** 純外觀 —— 顏色、間距、文案、圖像。 |
| ⚠️ | **Edit with care.** Changing it can silently break layout, a tap target, or a contrast pair. Re-check §6 afterwards. | **小心改。** 改完可能靜靜咁破壞版面、觸控目標或對比度。改完要做 §6 檢查。 |
| ⛔ | **Do not touch.** This is the accessibility contract (§5). Ask the team before changing it. | **唔好改。** 呢啲係無障礙合約（§5）。要改請先問組員。 |

**One rule above all:** a design change is finished only when §6 passes.
**最重要一條：** 設計改動要通過 §6 先叫完成。

---

## 1. Design language at a glance / 設計語言速查

### 1.1 Colour tokens — `public/assets/css/main.css` lines 12–86

All colour lives in `:root`. Never hard-code a hex value inside a component.
所有顏色都放在 `:root`。唔好在元件內部直接寫 hex 值。

| Token | Value | Used for | Contrast |
|---|---|---|---|
| `--color-primary` | `#0b4f9e` | links, buttons, active nav, icons | 8.00:1 on white |
| `--color-primary-dark` | `#08386f` | hover states, footer background | 11.63:1 with white |
| `--color-primary-soft` | `#e7effa` | tinted surfaces, hovered chips | — |
| `--color-on-primary` | `#ffffff` | text on primary | 8.00:1 |
| `--color-bg` / `--color-surface` | `#ffffff` | page and cards | — |
| `--color-surface-alt` | `#f2f5f8` | secondary blocks, route steps | — |
| `--color-text` | `#101820` | body copy, headings | 17.89:1 |
| `--color-text-muted` | `#46535f` | helper text, descriptions | 7.89:1 |
| `--color-border` | `#6f7c88` | input borders, chip outlines | 4.27:1 (UI, needs 3:1) |
| `--color-rule` | `#d3dae1` | decorative dividers only | — |
| `--color-success` | `#0b6b3a` | step-free flags, route line, "nearest" | 5.80:1 |
| `--color-warning` | `#8a4b00` | "partly step-free" flags | 6.05:1 |
| `--color-danger` | `#a4161a` | emergency, errors | 7.75:1 |
| `--color-focus` | `#0b4f9e` | 3px focus ring | 8.00:1 |

⚠️ **Any new foreground/background pair must be measured**, not eyeballed. The check is a 40-line PHP script (README §5.2 / §6 of this guide).
⚠️ **任何新嘅前景／背景配搭都要量度**，唔可以靠肉眼。檢查方法見 README §5.2 或本文件 §6。

### 1.2 Type scale — everything is `em`, so one root size scales the page

| Element | Size | Note |
|---|---|---|
| Body | `1rem` = 16px | never smaller than 16px |
| `h1` | `2em` | one per page |
| `h2` | `1.5em` | |
| `h3` | `1.25em` | |
| `.lede` | `1.15em` | intro paragraph |
| `.tile__name` | `1.15em` | ≥18px at base — the brief's minimum |
| `.tile__desc` | `0.9em` | |
| `.eyebrow` | `0.85em` | uppercase, letter-spaced |

Text modes are set in `accessibility.css` lines 168–190: `body.large-text` (125 %) and `body.text-scale-100/125/150/200`.
文字模式在 `accessibility.css` 第 168–190 行：`body.large-text`（125%）同 `body.text-scale-*`。

### 1.3 Spacing — 4px rhythm

`--space-1` `.25rem` · `--space-2` `.5rem` · `--space-3` `.75rem` · `--space-4` `1rem` · `--space-5` `1.5rem` · `--space-6` `2rem` · `--space-7` `3rem` · `--space-8` `4rem`

Use tokens, not arbitrary values. A one-off `17px` is how spacing systems rot.
用變數，唔好隨手寫數值。隨手寫一次就會有第二次。

### 1.4 Sizes, radii, shadows

| Token | Value | Meaning |
|---|---|---|
| `--touch-min` | 48px | minimum tap target (WCAG 2.5.5) — ⛔ do not reduce |
| `--tile-min-height` | 120px | mobile tile floor; 160px @768, 176px @1280 |
| `--icon-size` | 64px / 80px / 96px | user-selectable via `body.icon-size-*` |
| `--content-max` | 1200px | desktop content width |
| `--radius-sm/md/lg` | 6 / 10 / 16px | |
| `--radius-pill` | 999px | chips, switches |

### 1.5 Where the four visual modes live / 四種視覺模式嘅位置

| Mode | CSS | Trigger | JS |
|---|---|---|---|
| High contrast | `accessibility.css:55` | `body.high-contrast` | `accessibility.js` `applyPrefs()` |
| Large text | derived flag only | `body.large-text` when the percentage is ≥ 125 (no separate switch) | `accessibility.js` `setPref()` / `syncControls()` |
| Text scale | inline `font-size` on `<body>` | selected percentage (100/125/150/200) | server (`header.php`) + `accessibility.js` `applyPrefs()` |
| Icon size | `accessibility.css:194-219` | `body.icon-size-*` | same — now also scales the header brand, nav icons and Accessibility glyph |
| Vibration | no visual style | `body.vibration-on` + cookie `vibration` (`1`/`0`) | `accessibility.js` `setPref()` / `SMN.vibrate()` |
| Reduced motion | `accessibility.css:228` `prefers-reduced-motion` | OS setting only (no in-app toggle any more) | — |

The body classes are rendered **server-side** from the PHP session (`includes/config.php` → `body_classes()`), then mirrored by `accessibility.js`. If you rename a class in CSS, you must rename it in three places: `body_classes()`, `applyPrefs()`, and the pre-paint script in `header.php`.

**Assets are versioned.** `Router::asset()` appends `?v=<filemtime>` to every
stylesheet, script and image URL, so an edited file is fetched fresh and you do
not need to ask anyone to hard-refresh. Text size is applied twice on purpose:
inline `font-size` on `<body>` (server + JS) and the `body.text-scale-*` rules
as a fallback, so a stale script can never make the control look broken.

The visitor profile is **not** part of that list: `includes/config.php` → `user_prefs()` / `save_user_prefs()` read and write two cookies — `usertype` (`student` / `julac` / `guest`) and `vibration` (`1` / `0`). They are set by the first-visit panel on `index.php`, by the **About you** group on `settings.php`, and by `api/prefs.php`, and they live for 180 days.
呢啲 body class 由 PHP session **在伺服器端**輸出（`includes/config.php` → `body_classes()`），再由 `accessibility.js` 同步。如果你在 CSS 改名，要同時改三個地方：`body_classes()`、`applyPrefs()`、同 `header.php` 嘅預載腳本。訪客資料另外存喺 `usertype`、`vibration` 兩個 cookie。

---

## 2. File map / 檔案總表

| File | Lines | 用途 · What it is | 改咩 · What you edit | Risk |
|---|---|---|---|---|
| `public/assets/css/main.css` | 1,966 | base styles + all components | tokens, components | ⚠️ |
| `public/assets/css/mobile.css` | 541 | breakpoints 375/768/1024/1280 | layout, columns | ⚠️ |
| `public/assets/css/accessibility.css` | 294 | a11y modes, focus, motion | colour modes, type modes | ⛔ mostly |
| `includes/header.php` | 150 | `<head>`, masthead, a11y toggle | title, logo, tagline | ⚠️ |
| `includes/nav.php` | 66 | main navigation | labels, order | ⚠️ |
| `includes/footer.php` | 78 | emergency block, links, legal | copy, phone number | ⚠️ |
| `includes/skip-link.php` | 19 | skip link | text only | ⛔ |
| `includes/config.php` | 225 | constants, prefs, escaping | site name, phone, defaults | ⚠️ |
| `public/index.php` | 150 | landing page | all copy, section order | ✅ |
| `public/icons.php` | 294 | Epic 2.1 grid + panel | intro copy, tile labels | ✅ |
| `public/map.php` | 195 | Epic 2.2 map page | headings, side panel labels | ✅ |
| `public/help.php` | 324 | Epic 2.3 help centre | FAQ text, guides | ✅ |
| `public/settings.php` | 312 | preferences + preview | labels, help text | ✅ |
| `public/assets/js/main.js` | 233 | nav drawer, banner, focus trap | rarely | ⚠️ |
| `public/assets/js/accessibility.js` | 259 | preference engine | rarely | ⛔ |
| `public/assets/js/icons.js` | 379 | filter + roving tabindex | rarely | ⛔ |
| `public/assets/js/map.js` | 423 | Leaflet + route fetch | marker colours | ⚠️ |
| `public/assets/js/settings.js` | 197 | preview + save | rarely | ⚠️ |
| `src/MockData.php` | 628 | demo data (8 icons, 15 resources, 4 routes) | data values only | ⚠️ |
| `src/Router.php` | 187 | route table + URLs | add a page | ⚠️ |
| `public/api/routes.php` | 141 | mock routing JSON | no | ⛔ |
| `public/api/resources.php` | 105 | mock resources JSON | no | ⛔ |
| `public/api/progress.php` | 73 | mock progress JSON | no | ⛔ |
| `public/api/prefs.php` | 102 | session prefs + CSRF | no | ⛔ |
| `public/assets/icons/*.svg` | 5–9 each | 8 large icons | redraw freely | ✅ |
| `docs/screenshots/*.png` | 14 files | verified UI evidence | add your own | ✅ |
| `README.md` | 453 | setup, demo, audit, self-review | keep in sync | ⚠️ |

---

## 3. File-by-file / 逐個檔案

### 3.1 Styles / 樣式

#### `public/assets/css/main.css` — 1,966 lines / 行

**EN — What it is:** the whole visual base — tokens (§1–4), shell (§5), brand (§6), buttons (§7), header a11y menu (§8), navigation (§9), notices (§10), cards (§11), forms (§12), switches (§13), filter chips (§14), filter bar (§15), icon grid (§16), detail panel (§17), map (§18), settings (§19), help (§20), landing (§21), footer (§22).

- ✅ **Safe:** the `:root` token block (lines 12–86) and any colour/spacing/size value inside a component.
- ⚠️ **Careful:** anything with `min-height`, `padding`, or `grid-template-columns` — a smaller value can break a 48px tap target or a 120px tile.
- ⛔ **Do not touch:** the `:focus-visible` rule (≈line 135), `.visually-hidden` (≈199), `.skip-link` (≈212), `main:focus { outline: none }`.

**繁中 — 用途：** 全站視覺基礎 —— 設計變數（§1–4）、版面外殼（§5）、品牌（§6）、按鈕（§7）、頁首無障礙選單（§8）、導覽（§9）、提示框（§10）、卡片（§11）、表單（§12）、開關（§13）、篩選標籤（§14）、篩選列（§15）、圖示格（§16）、詳情面板（§17）、地圖（§18）、設定（§19）、幫助（§20）、首頁（§21）、頁尾（§22）。

- ✅ **可以改：** `:root` 變數區塊（第 12–86 行），同元件內任何顏色／間距／尺寸值。
- ⚠️ **小心改：** 所有 `min-height`、`padding`、`grid-template-columns` —— 改細可能令 48px 觸控目標或 120px 方塊唔達標。
- ⛔ **唔好改：** `:focus-visible` 規則（約第 135 行）、`.visually-hidden`（約 199）、`.skip-link`（約 212）、`main:focus { outline: none }`。

**Section index / 章節索引:** 1 tokens `9` · 2 reset `89` · 3 typography `147` · 4 utilities `196` · 5 layout `265` · 6 brand `308` · 7 buttons `366` · 8 a11y menu `519` · 9 navigation `552` · 10 notices `611` · 11 cards `671` · 12 forms `694` · 13 switches `754` · 14 chips `845` · 15 filter bar `914` · 16 icon grid `959` · 17 panel `1076` · 18 map `1217` · 19 settings `1501` · 20 help `1633` · 21 landing `1761`

#### `public/assets/css/mobile.css` — 541 lines / 行

**EN — What it is:** every responsive rule. Base styles are mobile-first; the file then overrides at three breakpoints.
- ✅ **Safe:** column counts, gaps, paddings inside a media query.
- ⚠️ **Careful:** the tile minimums (120px mobile / 160px @768 / 176px @1280) are from the brief.
- ⛔ **Do not touch:** `[hidden] { display: none !important }` at the top, and `.site-nav.is-collapsed` logic — they are progressive-enhancement safety nets.

**繁中 — 用途：** 所有響應式規則。基礎樣式係 mobile-first，再用三個斷點覆蓋。
- ✅ **可以改：** media query 內嘅欄數、間距、內距。
- ⚠️ **小心改：** 方塊最細尺寸（手機 120px／768 160px／1280 176px）係作業要求。
- ⛔ **唔好改：** 最頂嘅 `[hidden] { display: none !important }`，同 `.site-nav.is-collapsed` 邏輯 —— 佢哋係漸進增強嘅安全網。

**Breakpoints / 斷點:** `max-width:1023` `188` · `min-width:768` `225` · `min-width:1024` `379` · `min-width:1280` `506`

#### `public/assets/css/accessibility.css` — 294 lines / 行

**EN — What it is:** the accessibility layer, loaded last so it always wins — focus rings, high-contrast palette, text scaling, icon sizing, reduced motion, forced-colours support, print styles.
- ✅ **Safe:** adding a *new* rule at the end; adjusting the high-contrast hex values **as long as** every pair still measures ≥7:1.
- ⚠️ **Careful:** `body.high-contrast` (line 55) contains overrides for hard-coded colours in `main.css` (footer, markers, chips). Add a component with a hard-coded hex and you must add its high-contrast twin here.
- ⛔ **Do not touch:** §1 focus rules, the `prefers-reduced-motion` block (203), the `forced-colors` block (36).

**繁中 — 用途：** 無障礙層，最後載入所以永遠勝出 —— 焦點框、高對比色板、文字縮放、圖示尺寸、減少動態、forced-colours 支援、列印樣式。
- ✅ **可以改：** 在檔尾新增規則；調整高對比 hex 值（**前提**係每組配搭仍然 ≥7:1）。
- ⚠️ **小心改：** `body.high-contrast`（第 55 行）內有一批覆寫，專門處理 `main.css` 寫死嘅顏色（頁尾、地圖標記、標籤）。如果你新增元件用咗寫死 hex，就要在呢度補上對應嘅高對比版本。
- ⛔ **唔好改：** 第 1 節焦點規則、`prefers-reduced-motion` 區塊（203）、`forced-colors` 區塊（36）。

**Anchors / 錨點:** focus `12` · forced-colors `36` · high-contrast `55` · large-text `168` · text-scale `173` · icon-size `188` · reduced-motion `203` · sr-hints `233` · print `274`

---

### 3.2 Frame / 版面外殼

#### `includes/header.php` — 150 lines / 行

**EN — What it is:** `<!DOCTYPE>`, `<head>` (title, meta, CSS order, Leaflet CSS on the map page only), the pre-paint preference script, skip link, masthead (logo, brand text, `Accessibility` button, hamburger), the quick-accessibility panel, then it opens `<main>`.
- ✅ **Safe:** brand name/tagline mark-up (lines 87–95), button text, the meta description.
- ⚠️ **Careful:** the CSS `<link>` order — `main.css` → `mobile.css` → `accessibility.css` → Leaflet. Reordering breaks the cascade.
- ⛔ **Do not touch:** the inline pre-paint script (59–82) — it prevents a white flash for high-contrast users; `<meta name="csrf-token">` (39); `data-prefs-endpoint` on `<body>` (54); `aria-expanded` / `aria-controls` wiring on both buttons.

**繁中 — 用途：** `<!DOCTYPE>`、`<head>`（標題、meta、CSS 次序、只在地圖頁載入 Leaflet CSS）、預載偏好腳本、skip link、頁首（標誌、品牌文字、`Accessibility` 按鈕、漢堡按鈕）、快速無障礙面板，最後開啟 `<main>`。
- ✅ **可以改：** 品牌名／標語標記（第 87–95 行）、按鈕文字、meta description。
- ⚠️ **小心改：** CSS `<link>` 次序 —— `main.css` → `mobile.css` → `accessibility.css` → Leaflet。改次序會破壞層疊。
- ⛔ **唔好改：** 內嵌預載腳本（59–82，防止高對比用戶見到白閃）；`<meta name="csrf-token">`（39）；`<body>` 上嘅 `data-prefs-endpoint`（54）；兩個按鈕嘅 `aria-expanded` / `aria-controls` 連動。

#### `includes/nav.php` — 66 lines / 行

**EN — What it is:** the four-item main navigation. Icons are inline SVG in an `$nav_items` array (lines 19–46).
- ✅ **Safe:** labels, order, icon paths.
- ⚠️ **Careful:** keep four items or fewer on one line at 768px, otherwise the row wraps into the content. **Map points at the 3D app** via `map3d_url()` (see §14 of `MODIFICATIONS.md`); the text-friendly map is `/map.php`, linked from the footer and from each resource's “Navigate here”.
- ⛔ **Do not touch:** `role="navigation" aria-label="Main"`, `aria-current="page"`, the 48px `min-height`.

**繁中 — 用途：** 四項主導覽。圖示係 `$nav_items` 陣列內嘅 inline SVG（第 19–46 行）。
- ✅ **可以改：** 標籤、次序、圖示路徑。
- ⚠️ **小心改：** 768px 下一行最多四項，加多會迫到換行壓住內容。**Map 用 `map3d_url()` 指向 3D app**；文字版地圖係 `/map.php`，由頁尾同資源嘅「Navigate here」連過去。
- ⛔ **唔好改：** `role="navigation" aria-label="Main"`、`aria-current="page"`、48px `min-height`。

#### `includes/footer.php` — 78 lines / 行

**EN — What it is:** closes `<main>`, renders the floating **Start navigating** CTA (hidden on the map page), the emergency block (largest number on the page), Explore links (including **Map and routes (3D)** and **Text-friendly map**), the legal line, the `#live-region` container, and the script tags in load order.
- ✅ **Safe:** all copy; the phone number comes from `config.php`.
- ⚠️ **Careful:** script order at the bottom — `main.js` → `accessibility.js` → page script. Swapping `main.js` and `accessibility.js` breaks `SMN.announce`.
- ⛔ **Do not touch:** `<div id="live-region" role="status" aria-live="polite">` — every announcement on the site uses it.

**繁中 — 用途：** 關閉 `<main>`，輸出浮動 **Start navigating** 按鈕（地圖頁隱藏）、緊急聯絡區塊（全頁最大嘅電話號碼）、Explore 連結（包括 **Map and routes (3D)** 同 **Text-friendly map**）、版權行、`#live-region` 容器，同底部腳本（按載入次序）。
- ✅ **可以改：** 全部文案；電話號碼來自 `config.php`。
- ⚠️ **小心改：** 底部腳本次序 —— `main.js` → `accessibility.js` → 頁面腳本。掉換 `main.js` 同 `accessibility.js` 會令 `SMN.announce` 失效。
- ⛔ **唔好改：** `<div id="live-region" role="status" aria-live="polite">` —— 全站所有提示都靠佢。

#### `includes/skip-link.php` — 19 lines / 行

**EN — What it is:** the first focusable element on every page.
- ✅ **Safe:** the words "Skip to main content".
- ⛔ **Do not touch:** anything else. It must stay first in the DOM and must keep pointing at `#main-content`.

**繁中 — 用途：** 每頁第一個可聚焦元素。
- ✅ **可以改：** 文字「Skip to main content」。
- ⛔ **唔好改：** 其他任何東西。佢必須保持在 DOM 最前，並且指向 `#main-content`。

#### `includes/config.php` — 225 lines / 行

**EN — What it is:** site constants, base-path detection, accessibility defaults, session, escaping helpers, CSRF.
- ✅ **Safe:** `APP_NAME`, `APP_TAGLINE`, `EMERGENCY_PHONE` / `EMERGENCY_TEL` (line 28), `HELP_EMAIL`.
- ⚠️ **Careful:** `A11Y_DEFAULTS` (line 73) is mirrored in `accessibility.js` `DEFAULTS` — change both.
- ⛔ **Do not touch:** `e()` (196), `csrf_token()` / `csrf_valid()`, `app_base_path()`, `body_classes()`.

**繁中 — 用途：** 網站常數、base path 偵測、無障礙預設值、session、轉義輔助函式、CSRF。
- ✅ **可以改：** `APP_NAME`、`APP_TAGLINE`、`EMERGENCY_PHONE` / `EMERGENCY_TEL`（第 28 行）、`HELP_EMAIL`。
- ⚠️ **小心改：** `A11Y_DEFAULTS`（第 73 行）同 `accessibility.js` 嘅 `DEFAULTS` 係一對，要一齊改。
- ⛔ **唔好改：** `e()`（196）、`csrf_token()` / `csrf_valid()`、`app_base_path()`、`body_classes()`。

---

### 3.3 Pages / 頁面

#### `public/index.php` — 150 lines / 行

**EN — What it is:** the landing page. Sections: hero (with capability card), **first-visit profile panel** (rendered only when the `usertype` cookie is missing), how-it-works. The demo banner, the quick-link tiles, the accessibility-promise section and the demo path were removed in the 2026-11 layout rework.
- ✅ **Safe:** all headings, body copy, section order. The "Open the user manual" button points at `help.php?focus=manual#user-manual`.
- ⚠️ **Careful:** keep **exactly one `<h1>`**; every section must keep `aria-labelledby` pointing at its own heading. The profile form needs `profile-form=1` and the hidden `csrf` field — the POST handler at the top of the file depends on both.
- ⛔ **Do not touch:** the `name="usertype"` field name — `save_user_prefs()` writes the `usertype` cookie from it.

**繁中 — 用途：** 首頁。區塊：主視覺（含能力卡）、**首次到訪偏好面板**（只有在缺少 `usertype` cookie 時顯示）、運作方式。示範橫幅、快速連結、無障礙承諾同示範路徑已在 2026-11 版面重整中移除。
- ✅ **可以改：** 所有標題、內文、區塊次序。「開啟使用手冊」按鈕指向 `help.php?focus=manual#user-manual`。
- ⚠️ **小心改：** 全頁**只可以有一個 `<h1>`**；每個 section 嘅 `aria-labelledby` 要繼續指向自己嘅標題。偏好表單需要 `profile-form=1` 同隱藏 `csrf` 欄位。
- ⛔ **唔好改：** `name="usertype"` 欄位名 —— `save_user_prefs()` 靠佢寫入 `usertype` cookie。

#### `public/icons.php` — 294 lines / 行 · **Epic 2.1**

**EN — What it is:** the large-icon interface. Filter bar (87), tile grid (141), detail panel (207), one `<template>` per icon (237).
- ✅ **Safe:** intro copy, filter labels, panel headings, the `$quickIcons`-style arrays.
- ⚠️ **Careful:** the tile `aria-label` is built in PHP (`$label`, lines 150–158) — if you change how a tile reads on screen, update that string too or sighted and screen-reader users get different information.
- ⛔ **Do not touch:** `role="list"` / `role="listitem"`, the roving `tabindex` (one `0`, rest `-1`), `aria-pressed`, `<template>` ids (`detail-<iconId>`) — `icons.js` looks them up by id.

**繁中 — 用途：** 大型圖示介面。篩選列（87）、圖示格（141）、詳情面板（207）、每個圖示一個 `<template>`（237）。
- ✅ **可以改：** 引言、篩選標籤、面板標題、類似 `$quickIcons` 嘅陣列。
- ⚠️ **小心改：** 方塊嘅 `aria-label` 係由 PHP 組成（`$label`，第 150–158 行）。如果你改咗畫面顯示嘅文字，要一齊改，否則現場用戶同螢幕閱讀器用戶會得到唔同資訊。
- ⛔ **唔好改：** `role="list"` / `role="listitem"`、roving `tabindex`（一個 `0`，其餘 `-1`）、`aria-pressed`、`<template>` 嘅 id（`detail-<iconId>`）—— `icons.js` 靠 id 搵佢哋。

#### `public/map.php` — 195 lines / 行 · **Epic 2.2**

**EN — What it is:** map page. Layout wrapper, the map/screenshot switch, the `#map` canvas with its ARIA instructions, the screenshot `<figure>`, route sidebar (start point, destination, route type, stats, step list) and the **Accessible resources at CityU** showcase.
- ✅ **Safe:** toolbar copy, sidebar headings, control labels, the `$showcaseIds` list that picks the resource cards.
- ⚠️ **Careful:** the numbered **Step by step** list in the sidebar is the WCAG 1.1.1 alternative to the map. Never remove or hide it. The screenshot view must keep its `figcaption` explaining that it is a still image.
- ⛔ **Do not touch:** `role="application"` + `aria-describedby="map-instructions"`, `data-route-endpoint`, `data-resources`, `id="map-stage"` / `id="map-screenshot"` (map.js toggles them), the `data-focus-resource` buttons and `data-route-label` / `data-route-hint` attributes on the type chips.

**繁中 — 用途：** 地圖頁。版面容器、地圖／截圖切換、`#map` 畫布同其 ARIA 指示、截圖 `<figure>`、路線側欄（起點、目的地、路線類型、統計、步驟清單）同 **CityU 無障礙資源**展示。
- ✅ **可以改：** 工具列文案、側欄標題、控制按鈕標籤、`$showcaseIds` 清單。
- ⚠️ **小心改：** 側欄嘅**逐步指引**係地圖嘅 WCAG 1.1.1 替代內容，唔可以移除或隱藏。截圖檢視要保留 `figcaption` 說明佢係靜態圖片。
- ⛔ **唔好改：** `role="application"` + `aria-describedby="map-instructions"`、`data-route-endpoint`、`data-resources`、`id="map-stage"` / `id="map-screenshot"`、`data-focus-resource` 按鈕同類型按鈕上嘅 `data-route-label` / `data-route-hint`。

#### `public/help.php` — 324 lines / 行 · **Epic 2.3**

**EN — What it is:** emergency block, **User manual** (`id="user-manual"`, six guides in the same FAQ accordion as the questions), FAQ accordion, contact form. The keyboard-shortcut table and the accessibility-statement section were removed in the 2026-11 rework, so nothing links to `?focus=statement` any more.
- ✅ **Safe:** every FAQ, manual step, and paragraph. `$faqs` at the top is a plain array — edit text there, not in the markup.
- ⚠️ **Careful:** the emergency phone uses `2em` (32px) on purpose — the brief requires ≥32px. `?focus=manual` moves focus to the manual heading, which is why the section carries a conditional `tabindex="-1"`.
- ⛔ **Do not touch:** `<input type="hidden" name="csrf">`, the honeypot field (`name="website"`), `required` + `aria-describedby` pairs on the form fields, `id="user-manual"` (the landing page deep-links to it).

**繁中 — 用途：** 緊急區塊、**使用手冊**（`id="user-manual"`，六個指引同常見問題一樣用手風琴）、常見問題手風琴、聯絡表單。鍵盤快捷鍵表同無障礙聲明區塊已在 2026-11 重整中移除。
- ✅ **可以改：** 全部 FAQ、手冊步驟、段落。頂部嘅 `$faqs` 係普通陣列。
- ⚠️ **小心改：** 緊急電話用 `2em`（32px）係刻意嘅。`?focus=manual` 會將焦點移到手冊標題，所以該區塊有條件性 `tabindex="-1"`。
- ⛔ **唔好改：** `<input type="hidden" name="csrf">`、蜜罐欄位（`name="website"`）、表單欄位嘅 `required` / `aria-describedby`、`id="user-manual"`（首頁深層連結依賴佢）。

#### `public/settings.php` — 312 lines / 行

**EN — What it is:** live preview then the form: Display group (including the **Vibration** toggle), **About you** group (type of user), Routing group. **Every control saves on change — there is no Save button;** Reset to defaults stays (and a `<noscript>` submit button keeps the no-JS path working).
- ✅ **Safe:** setting labels and help text; preview sample content.
- ⚠️ **Careful:** the preview is first in the DOM so it sits **above** the controls on a phone and moves to the **right column** at ≥1024px (`mobile.css`). It is **not sticky** any more: a preview taller than the viewport used to hide its own bottom edge at large text. At `text-scale-150` the two columns stack.
- ⛔ **Do not touch:** `data-pref="…"` on each switch (`accessibility.js` looks them up), `name="text_scale"` on the three size radios (`accessibility.js` syncs them, `settings.js` reads them), `name="usertype"` (the settings POST writes the profile cookie), the hidden `csrf` field.

**Text size is a radio group, not a slider.** Each option is a real
`input[type="radio"][name="text_scale"]` inside a `.chip-row`, so it works with
arrow keys and posts without JavaScript. The three chips are named **Normal**,
**Large** and **Extra Large** (100 / 125 / 150 %) and the name writes the
`font-size` **directly onto `<body>`** (server-side in `header.php`, then
`accessibility.js`), so there is no separate "Large text" switch to trigger it:
that switch was removed from Settings and now exists only as the quick
shortcut in the header, which simply selects 125 %. The switch rows
deliberately have no "On/Off" text: the track/thumb shows the state, the
checkbox exposes it, and the row label names the setting.

**繁中 — 用途：** 即時預覽再係表單：顯示群組（含 **Vibration** 開關）、**About you** 群組（用戶類型）、路線群組、重設。
- ✅ **可以改：** 設定標籤同說明文字；預覽樣本內容。
- ⚠️ **小心改：** 預覽在 DOM 排第一，手機在控制項**上方**，≥1024px 時移到**右欄**。預覽**唔再係 sticky**；`text-scale-150` 時兩欄會疊起。
- ⛔ **唔好改：** 每個開關上嘅 `data-pref="…"`、`name="usertype"`（設定 POST 會寫入 cookie）、三個字級選項嘅 `name="text_scale"`、隱藏 `csrf` 欄位。

---

### 3.4 Scripts / 行為腳本

> These five files carry the interaction and the accessibility contract. Designers rarely need to edit them; when you do, check §6 immediately.
> 呢五個檔案負責互動同無障礙合約。設計師通常唔需要改；如果要改，改完即刻做 §6。

#### `public/assets/js/main.js` — 233 lines / 行

**EN:** shared shell — `SMN.announce()`, `SMN.trapFocus()`, nav drawer, header a11y menu, floating **Start navigating** button (`initFloatingCta()`, adds a vibration when enabled), skip link, mobile sheets.
- ✅ **Safe:** announcement wording inside the functions.
- ⛔ **Do not touch:** `SMN.announce` / `SMN.trapFocus` signatures — four other files call them.

**繁中：** 共用外殼 —— `SMN.announce()`、`SMN.trapFocus()`、導覽抽屜、頁首無障礙選單、浮動 **Start navigating** 按鈕（`initFloatingCta()`，開啟震動時會震一下）、skip link、手機面板。
- ✅ **可以改：** 函式內嘅提示文字。
- ⛔ **唔好改：** `SMN.announce` / `SMN.trapFocus` 嘅簽名 —— 另外四個檔案都呼叫佢哋。

#### `public/assets/js/accessibility.js` — 259 lines / 行

**EN:** the preference engine — `readServerState()`, `readPrefs()`, `writeCookie()`, `applyPrefs()`, `syncControls()`, `setPref()`, `init()`, plus `SMN.vibrate()` for the map.
- ✅ **Safe:** the `data-pref-label` strings used in announcements.
- ⚠️ **Careful:** `DEFAULTS` mirrors `A11Y_DEFAULTS` in `config.php`. `setPref('vibration', …)` also writes the `vibration` cookie.
- ⛔ **Do not touch:** `readServerState()` — it is the fix that stops stored defaults overwriting a server-set high-contrast session.

**繁中：** 偏好設定引擎 —— `readServerState()`（43）、`readPrefs()`（61）、`applyPrefs()`（110）、`syncControls()`（134）、`setPref()`（163）、`init()`（188）。
- ✅ **可以改：** 用於提示嘅 `data-pref-label` 文字。
- ⚠️ **小心改：** `DEFAULTS` 同 `config.php` 嘅 `A11Y_DEFAULTS` 係一對。
- ⛔ **唔好改：** `readServerState()` —— 佢係「唔好用本機預設值蓋過伺服器設定」嘅修正。

#### `public/assets/js/icons.js` — 379 lines / 行

**EN:** filter + roving tabindex + detail panel — `readStateFromUrl()` (45), `applyFilters()` (116), `onGridKeydown()` (~166), `openPanel()`/`closePanel()` (~206–244), `init()` (275).
- ✅ **Safe:** nothing, really.
- ⛔ **Do not touch:** the roving-tabindex logic, the focus-return in `closePanel()`, the URL sync.

**繁中：** 篩選 + roving tabindex + 詳情面板 —— `readStateFromUrl()`（45）、`applyFilters()`（116）、`onGridKeydown()`（約 166）、`openPanel()`／`closePanel()`（約 206–244）、`init()`（275）。
- ✅ **可以改：** 基本上冇。
- ⛔ **唔好改：** roving tabindex 邏輯、`closePanel()` 嘅焦點返回、URL 同步。

#### `public/assets/js/map.js` — 423 lines / 行

**EN:** Leaflet setup (~40), `iconFor()` (69), markers (89), `recentre()` (120), `fitRoute()` (172), `renderRoute()` (181), `loadRoute()` (~249), `init()` (~290).
- ✅ **Safe:** marker glyph letters in `iconFor()`, and marker `className`.
- ⚠️ **Careful:** `fitRoute()` invalidates the map size on the next frame — removing that reintroduces the "map opens zoomed out" bug.
- ⛔ **Do not touch:** the keyboard pan block, `showError()` (single-announcer rule), `role` handling on `#route-status`.

**繁中：** Leaflet 設定（約 40）、`iconFor()`（69）、標記（89）、`recentre()`（120）、`fitRoute()`（172）、`renderRoute()`（181）、`loadRoute()`（約 249）、`init()`（約 290）。
- ✅ **可以改：** `iconFor()` 內嘅標記字母，同標記 `className`。
- ⚠️ **小心改：** `fitRoute()` 會在下一幀重新量度地圖尺寸 —— 移除就會再次出現「地圖開啟時縮到好遠」嘅問題。
- ⛔ **唔好改：** 鍵盤平移區塊、`showError()`（單一播報原則）、`#route-status` 嘅 `role` 處理。

#### `public/assets/js/settings.js` — 197 lines / 行

**EN:** `refreshPreview()` (50), `payload()` (67), `save()` (84), `reset()` (129), `init()` (167).
- ✅ **Safe:** the `LABELS` / `ROUTE_LABELS` display strings and status messages.
- ⚠️ **Careful:** `LABELS` must match `ICON_SIZE_OPTIONS` in `config.php`.

**繁中：** `refreshPreview()`（50）、`payload()`（67）、`save()`（84）、`reset()`（129）、`init()`（167）。
- ✅ **可以改：** `LABELS` / `ROUTE_LABELS` 顯示文字同狀態訊息。
- ⚠️ **小心改：** `LABELS` 要同 `config.php` 嘅 `ICON_SIZE_OPTIONS` 一致。

---

### 3.5 Data & routing / 資料與路由

#### `src/MockData.php` — 628 lines / 行

**EN:** the single source of demo content. `TAGS` (25), `icons()` (57), `resources()` (183), `routes()` (288), lookups (398–438), `findRoute()` (471), `progress()` (529), `iconSvg()` (573).
- ✅ **Safe:** the data arrays — edit names, descriptions, distances, coordinates.
- ⚠️ **Careful:** `nearest_locations` ids must exist in `resources()`; `svg_path` must exist in `public/assets/icons/`.
- ⛔ **Do not touch:** `iconSvg()` — it injects `role="img"` / `<title>` / unique ids and is what keeps the SVG audit passing.

**繁中：** 示範內容嘅唯一來源。`TAGS`（25）、`icons()`（57）、`resources()`（183）、`routes()`（288）、查詢函式（398–438）、`findRoute()`（471）、`progress()`（529）、`iconSvg()`（573）。
- ✅ **可以改：** 資料陣列 —— 名稱、描述、距離、座標。
- ⚠️ **小心改：** `nearest_locations` 嘅 id 必須存在於 `resources()`；`svg_path` 必須存在於 `public/assets/icons/`。
- ⛔ **唔好改：** `iconSvg()` —— 佢負責注入 `role="img"` / `<title>` / 唯一 id，SVG 審核之所以通過全靠佢。

#### `src/Router.php` — 187 lines / 行

**EN:** the route table (`PAGES`, line 31), `url()`, `api()`, `current()`, `isActive()`, `notFound()`.
- ✅ **Safe:** page titles in the table.
- ⚠️ **Careful:** adding a page means: new file in `public/`, new row in `PAGES`, new item in `nav.php`, new link in `footer.php`. Miss one and the page is unreachable or un-highlighted.
- ⛔ **Do not touch:** `normalise()` — it is what stops `?page=` being used for path traversal.

**繁中：** 路由表（`PAGES`，第 31 行）、`url()`、`api()`、`current()`、`isActive()`、`notFound()`。
- ✅ **可以改：** 表格內嘅頁面標題。
- ⚠️ **小心改：** 加新頁面要同時做四件事：`public/` 新檔、`PAGES` 新增一列、`nav.php` 新增一項、`footer.php` 新增連結。漏一件就會入唔到去或者冇高亮。
- ⛔ **唔好改：** `normalise()` —— 佢係防止 `?page=` 被用嚟做路徑穿越嘅關卡。

---

### 3.6 API endpoints / API 端點

> **None of these are design files.** They return JSON to the JavaScript. Touch them only if the data contract changes — and then update the matching `fetch` in the script AND the README §8 table.
> **呢啲全部唔係設計檔案。** 佢哋只係向 JavaScript 回傳 JSON。只有資料契約改變先要改 —— 而且要同時更新對應嘅 `fetch` 同 README §8 表格。

| File | Lines | Returns | Design relevance |
|---|---|---|---|
| `public/api/routes.php` | 141 | `{ok, route, steps, distance_m, duration_min, features}` | the numbers shown in the route sidebar |
| `public/api/resources.php` | 105 | `{ok, count, resources[]}` | marker data |
| `public/api/progress.php` | 73 | `{ok, progress_pct, current_instruction…}` | progress display |
| `public/api/prefs.php` | 102 | `{ok, prefs}` | server-side render of the mode classes |

⛔ All four: `Content-Type`, `Access-Control-Allow-Origin`, and the CSRF check in `prefs.php` (line ~50) must stay.
⛔ 四個檔案：`Content-Type`、`Access-Control-Allow-Origin`，同 `prefs.php` 嘅 CSRF 檢查（約第 50 行）都要保留。

### 3.7 Icons / 圖示 — `public/assets/icons/*.svg`

**EN:** eight files, each a 64×64 `viewBox` with `stroke="currentColor"` and no `fill` except where a solid glyph is wanted. `MockData::iconSvg()` injects the `role`, `<title>` and unique id at render time, so **do not add `role` or `id` inside the SVG files**.
- ✅ **Safe:** redraw the paths, change stroke width, change `viewBox` contents.
- ⚠️ **Careful:** keep `stroke-width` around 4 at 64px — thinner strokes vanish in high-contrast mode and at 200% zoom.
- ⛔ **Do not touch:** the `class="icon"`, the `xmlns`, the absence of a hard-coded `width`/`height` attribute (CSS controls size via `--icon-size`).

**繁中：** 八個檔案，每個都係 64×64 `viewBox`，用 `stroke="currentColor"`，除咗要做實心字形之外唔加 `fill`。`MockData::iconSvg()` 會在輸出時注入 `role`、`<title>` 同唯一 id，所以 **唔好在 SVG 檔內自己加 `role` 或 `id`**。
- ✅ **可以改：** 重畫路徑、改線寬、改 `viewBox` 內容。
- ⚠️ **小心改：** 64px 下 `stroke-width` 保持約 4 —— 太幼嘅線在高對比模式同 200% 縮放下會消失。
- ⛔ **唔好改：** `class="icon"`、`xmlns`，同「唔加寫死嘅 `width`／`height` 屬性」（尺寸由 CSS 嘅 `--icon-size` 控制）。

| File | Icon | Where it appears |
|---|---|---|
| `navigation.svg` | compass | brand mark, nav, favourites |
| `toilets.svg` | wheelchair symbol | tile + panel |
| `elevators.svg` | lift with arrows | tile + panel |
| `ramps.svg` | slope | tile + panel |
| `entrances.svg` | door + arrow | tile + panel |
| `help-points.svg` | question mark | tile + panel |
| `transport.svg` | bus front | tile + panel |
| `parking.svg` | P | tile + panel |

### 3.8 Documentation / 文件

| File | 用途 · What it is | When to update |
|---|---|---|
| `README.md` (453 lines) | setup, structure, 6-step demo, audit results, 21-row self-review | after any behaviour or layout change |
| `docs/UI-GUIDE.md` (this file) | designer's map of the codebase | after adding/removing a file |
| `docs/screenshots/*.png` (14) | verified UI evidence for the report | after a visual change — re-capture |
| `database/schema.sql` | reference schema (not used at runtime) | only if the data model changes |

**繁中：**
| 檔案 | 用途 | 何時更新 |
|---|---|---|
| `README.md`（453 行） | 安裝、結構、六步示範、審核結果、21 行自我檢討 | 任何行為或版面改動之後 |
| `docs/UI-GUIDE.md`（本文件） | 設計師用嘅檔案地圖 | 新增／移除檔案之後 |
| `docs/screenshots/*.png`（14 張） | 報告用嘅實測畫面證據 | 視覺改動之後重新截圖 |
| `database/schema.sql` | 參考用資料庫結構（執行時不使用） | 只有資料模型改變時 |

---

## 4. Common design tasks / 常用改動食譜

### R1 · Change the brand colour / 換主色

1. `main.css` lines 12–86 — set `--color-primary`, `--color-primary-dark`, `--color-primary-soft`, `--color-on-primary`.
2. `accessibility.css` line 55 — set the high-contrast equivalents (`--color-primary` becomes the accent, usually yellow).
3. Re-measure every pair (§6) — the token comments state the old ratios and will be wrong.
4. Re-capture `docs/screenshots/03` and `13`.

1. `main.css` 第 12–86 行 —— 改 `--color-primary`、`--color-primary-dark`、`--color-primary-soft`、`--color-on-primary`。
2. `accessibility.css` 第 55 行 —— 改高對比版本（`--color-primary` 通常變成黃色）。
3. 重新量度每組配搭（§6）—— 變數旁嘅舊對比數字會變成錯。
4. 重新截 `docs/screenshots/03` 同 `13`。

### R2 · Change the type scale / 改字級

`main.css` section 3 (line 147) uses `em` for headings, `accessibility.css` 168–176 sets the page size. Changing `--font-size-base` from `1rem` breaks the 200% reflow test — change the *headings* first, the base last.
`main.css` 第 3 節（147 行）用 `em` 定義標題，`accessibility.css` 168–176 設定頁面字級。改 `--font-size-base` 會令 200% 重排測試失敗 —— 先改標題，最後才改基準。

### R3 · Change tile size or desktop columns / 改方塊大細或桌面欄數

`--tile-min-height` (`main.css:78`) + the three breakpoints in `mobile.css` (`959` grid rules, `225`/`379`/`506`). Keep: ≥120px mobile, ≥160px desktop.
`--tile-min-height`（`main.css:78`）同 `mobile.css` 三個斷點（`225`／`379`／`506`）。要守：手機 ≥120px、桌面 ≥160px。

### R4 · Add a new icon / 新增圖示

1. Drop a 64×64 SVG into `public/assets/icons/`.
2. Add an entry to `MockData::icons()` with `id`, `name`, `svg_path`, `accessibility_tags`, `nearest_locations`.
3. Nothing else — the grid, the filter bar, the panel templates and the map all read from that array.
1. 放一個 64×64 SVG 入 `public/assets/icons/`。
2. 在 `MockData::icons()` 加一項，包括 `id`、`name`、`svg_path`、`accessibility_tags`、`nearest_locations`。
3. 唔需要改其他嘢 —— 圖示格、篩選列、面板模板、地圖全部由嗰個陣列讀取。

### R5 · Add a page / 新增頁面

`public/<name>.php` → `Router::PAGES` → `nav.php` item → `footer.php` link → README §2 tree.
`public/<name>.php` → `Router::PAGES` → `nav.php` 一項 → `footer.php` 連結 → README §2 結構圖。

### R6 · Change the mobile sheet height / 改手機面板高度

`mobile.css` — `.map-sidebar.is-collapsed { max-height: 40vh }` and `.map-sidebar { max-height: 62vh }`. Do not exceed `62vh`: the map needs the rest to stay usable.
`mobile.css` —— `.map-sidebar.is-collapsed { max-height: 40vh }` 同 `.map-sidebar { max-height: 62vh }`。唔好超過 `62vh`：地圖要留住其餘空間才用得。

### R7 · Change copy / 改文案

Page files only. Avoid text inside JS strings — screen-reader users then get a different message from what is on screen.
只改頁面檔案。避免把文字寫入 JS 字串 —— 否則螢幕閱讀器用戶會聽到同畫面唔同嘅內容。

---

## 5. The accessibility contract / 無障礙合約

Seven rules the design depends on. Every one of them is a WCAG 2.1 AA success criterion, and every one has already been verified once (README §5.2).
設計依靠以下七條規則。每一條都對應 WCAG 2.1 AA 準則，而且已經驗證過一次（README §5.2）。

| # | Rule | Lives in | If you break it |
|---|---|---|---|
| 1 | Focus ring is 3px and never removed | `main.css:135`, `accessibility.css:12` | 2.4.7 — keyboard users lose their place |
| 2 | Tap targets ≥48×48px | `--touch-min`, every `min-height` | 2.5.5 — mis-taps, especially with tremor |
| 3 | Skip link is the first focusable element | `skip-link.php`, `header.php:84` | 2.4.1 — 20+ tab presses to reach content |
| 4 | Exactly one `<h1>`, no skipped heading levels | every page | 1.3.1 — screen-reader outline collapses |
| 5 | Colour is never the only signal | flags + labels + line styles | 1.4.1 — colour-blind users lose the step-free meaning |
| 6 | Text reflows at 200% with no horizontal scroll | `accessibility.css:168-190`, `mobile.css` | 1.4.4 / 1.4.10 — content becomes unreachable |
| 7 | One speaker per event (status **or** alert, never both) | `map.js` `showError()`, `#live-region` | 4.1.3 — duplicated, confusing announcements |

**Contrast floors:** 4.5:1 for text, 3:1 for UI parts, **7:1 in high-contrast mode**. Current worst cases: 5.80:1 (light), 8.42:1 (high contrast).
**對比度下限：** 文字 4.5:1、UI 元件 3:1、**高對比模式 7:1**。目前最差值：5.80:1（淺色）、8.42:1（高對比）。

---

## 6. Post-change checklist / 改完檢查清單

Run this before you say "done". Six commands, two minutes.
講「搞掂」之前做一次。六條指令，兩分鐘。

```bash
cd /opt/lampp/htdocs/CityUMapNav

# 1. Start the server (if it is not already running)
setsid nohup /opt/lampp/bin/php -S 127.0.0.1:8000 -t public > /tmp/smn.log 2>&1 &

# 2. Every page still returns 200
for p in index icons map help settings; do
  curl -s -o /dev/null -w "$p %{http_code}\n" http://127.0.0.1:8000/$p.php
done

# 3. No PHP syntax errors
for f in $(find . -name '*.php' -not -path './public/assets/vendor/*'); do
  /opt/lampp/bin/php -l "$f" | grep -v "No syntax errors"
done
```

| Check | How | Pass looks like |
|---|---|---|
| **Phone layout** | Chrome `Ctrl+Shift+M` → iPhone SE (375px) | 2-col grid, no horizontal scroll, filter bar sticks |
| **Desktop layout** | Window ≥1280px | 4-col grid, nav inline, 3-col footer |
| **Keyboard** | `Tab` from page top | Skip link appears first, 3px ring on every stop, `Esc` closes panels |
| **High contrast** | Header → Accessibility → High contrast | Black page, yellow accents, icons recolour, nothing unreadable |
| **Extra Large text** | Settings → **Text size** chip → Extra Large | Single-column settings, no clipping, no sideways scroll |
| **Route error** | `/map.php?simulate_error=1` | "Route unavailable" + `tel:` button, announced **once** |
| **Contrast** | Re-run the PHP contrast script (README §5.2) | all pairs PASS |
| **Screenshots** | Re-capture the affected ones into `docs/screenshots/` | filenames unchanged |

---

## 7. How the files depend on each other / 檔案關係圖

```
                        ┌──────────────────────────┐
                        │  includes/config.php     │  constants · session · e() · CSRF
                        └────────────┬─────────────┘
                                     │ requires
                     ┌───────────────┴───────────────┐
                     ▼                               ▼
            ┌─────────────────┐             ┌─────────────────┐
            │  src/Router.php │             │ src/MockData.php│
            │  URLs · 404     │             │  demo content   │
            └────────┬────────┘             └────────┬────────┘
                     │                               │
   ┌─────────────────┴───────────────────────────────┴──────────────┐
   │                        public/*.php pages                       │
   │  index.php · icons.php · map.php · help.php · settings.php      │
   └───────┬──────────────────┬──────────────────────┬──────────────┘
           │ require          │ require              │ fetch (JSON)
           ▼                  ▼                      ▼
   ┌───────────────┐  ┌─────────────────┐   ┌────────────────────┐
   │ header.php    │  │ footer.php      │   │ public/api/*.php   │
   │ nav.php       │  │ live-region     │   │ routes/resources/  │
   │ skip-link.php │  │ script order    │   │ progress/prefs     │
   └───────┬───────┘  └────────┬────────┘   └────────────────────┘
           │                   │
           ▼                   ▼
   ┌────────────────────────────────────────────────────────────────┐
   │ CSS (in order): main.css → mobile.css → accessibility.css       │
   │ JS  (in order): main.js  → accessibility.js → page script       │
   └────────────────────────────────────────────────────────────────┘
```

**Two ordering rules that matter more than they look:**
**兩個次序規則，比表面睇更重要：**

1. **CSS** — `accessibility.css` must load last, so the modes can override component colours.
   `accessibility.css` 必須最後載入，模式才可以蓋過元件顏色。
2. **JS** — `main.js` defines `SMN.announce` that every other script calls; `accessibility.js` must run before a page script reads `SMN.prefs`.
   `main.js` 定義 `SMN.announce`，其他腳本都要用；`accessibility.js` 必須在頁面腳本讀 `SMN.prefs` 之前執行。

---

*Last updated / 最後更新: aligned with README v0.9.0 · 21 self-review rows · 14 verified screenshots.*
*對應 README v0.9.0 · 21 行自我檢討 · 14 張實測截圖。*





