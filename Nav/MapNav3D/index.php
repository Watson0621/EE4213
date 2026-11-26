<?php
/**
 * Stand-alone 3D campus map (copy of the MapNav3D app).
 *
 * CityU Map Nav links here. So the visitor does not have to choose the same
 * things twice, this entry point inherits their saved preferences:
 *   - text_scale  (font size)  ?text_scale -> session prefs -> 100
 *   - usertype    (role)       ?usertype -> cookie -> session -> Student
 *   - route_type  (route)      ?route_type -> session default_route -> time
 *
 * Every value is validated before it reaches the markup.
 */
declare(strict_types=1);

/* Same host and PHP session store as the main app, so this resumes the
   visitor's CityU Map Nav session (the session cookie path is "/"). */
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

/** Escape for HTML text and attribute contexts. */
function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* --- Text size (font scale) ------------------------------------------- */
$validScales = [100, 125, 150];
$scaleParam  = filter_input(INPUT_GET, 'text_scale', FILTER_VALIDATE_INT);
$scale       = ($scaleParam !== null && in_array($scaleParam, $validScales, true))
    ? $scaleParam
    : (int) ($_SESSION['prefs']['text_scale'] ?? 100);

if (!in_array($scale, $validScales, true)) {
    $scale = 100;
}

/* --- Type of user -> role --------------------------------------------- */
$roles    = ['student' => 'Student', 'julac' => 'JULAC', 'guest' => 'Guest'];
$userType = strtolower(trim((string) (
    filter_input(INPUT_GET, 'usertype')
    ?: ($_COOKIE['usertype'] ?? ($_SESSION['prefs']['usertype'] ?? ''))
)));
$role     = $roles[$userType] ?? 'Student';

/* --- Default route type -> the 3D map's route ------------------------- */
$routeMap     = ['fastest' => 'time', 'smoothest' => 'smooth', 'step_free' => 'step_free'];
$savedRoute   = (string) ($_SESSION['prefs']['default_route'] ?? 'fastest');
$routeParam   = (string) (filter_input(INPUT_GET, 'route_type') ?: '');

/* An explicit ?route_type= wins (matches app.js), then the saved default. */
$routeDefault = in_array($routeParam, ['time', 'smooth', 'step_free'], true)
    ? $routeParam
    : ($routeMap[$savedRoute] ?? 'time');

/* --- Exit target: back to the CityU Map Nav home page ------------------ */
$script  = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/Nav/MapNav3D/index.php'));
$homeUrl = rtrim(dirname($script, 3), '/') . '/public/index.php';
?>
<!DOCTYPE html>
<html lang="en" data-text-scale="<?= $scale ?>" style="font-size: <?= $scale ?>%">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CityU Smart Nav</title>
<link rel="stylesheet" href="style.css?v=107">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
<script src="https://unpkg.com/three@0.128.0/build/three.min.js"></script>
<script src="https://unpkg.com/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://unpkg.com/three@0.128.0/examples/js/renderers/CSS2DRenderer.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
/*
 * Pre-paint font scale.
 * The server already rendered the percentage from the session. A visitor who
 * changed the size very recently may have a newer value in localStorage (same
 * origin as CityU Map Nav), so prefer it unless the URL says otherwise.
 */
(function () {
    try {
        if (new URLSearchParams(window.location.search).has('text_scale')) { return; }
        var saved = JSON.parse(localStorage.getItem('smartmapnav:prefs') || 'null');
        var scale = saved && Number(saved.text_scale);
        if ([100, 125, 150].indexOf(scale) === -1) { return; }
        document.documentElement.style.fontSize = scale + '%';
        document.documentElement.setAttribute('data-text-scale', String(scale));
    } catch (err) { /* storage blocked: keep the server-rendered value */ }
})();
</script>
</head>
<body data-user-type="<?= h($userType) ?>"
      data-default-route-type="<?= h($routeDefault) ?>" data-text-scale="<?= $scale ?>">

<button id="ui-toggle-btn" class="ui-toggle-btn" onclick="toggleUI()">
    <span class="material-symbols-rounded">menu_open</span>
</button>

<a class="exit-btn" href="<?= h($homeUrl) ?>"
   aria-label="Exit to Smart Map Nav home" title="Exit to Smart Map Nav home">
    <span class="material-symbols-rounded">logout</span>
    <span class="exit-text">Exit</span>
</a>

<div class="top-bar" id="top-bar">
    <div class="brand">
        <span class="material-symbols-rounded brand-icon">explore</span>
        <h1>CityU Nav</h1>
    </div>
    
    <div class="floor-filters">
        <button class="filter-btn active" onclick="filterFloor('all')">All</button>
        <button class="filter-btn" onclick="filterFloor(17)">F3</button>
        <button class="filter-btn" onclick="filterFloor(21)">F4</button>
        <button class="filter-btn" onclick="filterFloor(24)">F5</button>
    </div>

    <select id="role-select" onchange="changeRole(this.value)"
            title="Your role (inherited from CityU Map Nav settings)">
        <option value="Student" <?= $role === 'Student' ? 'selected' : '' ?>>🎓 Student</option>
        <option value="JULAC" <?= $role === 'JULAC' ? 'selected' : '' ?>>📚 JULAC</option>
        <option value="Guest" <?= $role === 'Guest' ? 'selected' : '' ?>>👤 Guest</option>
    </select>

    <div class="controls">
        <select id="route-type-select" class="route-type-select" onchange="changeRouteType(this.value)"
                title="Route type (inherited from your accessibility needs)">
            <option value="time" <?= $routeDefault === 'time' ? 'selected' : '' ?>>⚡ Fastest</option>
            <option value="smooth" <?= $routeDefault === 'smooth' ? 'selected' : '' ?>>🚶 Smooth (no stairs)</option>
            <option value="step_free" <?= $routeDefault === 'step_free' ? 'selected' : '' ?>>♿ Step-Free</option>
        </select>

        <div class="search-box">
            <span class="material-symbols-rounded search-icon start-icon">radio_button_checked</span>
            <input type="text" id="start-input" placeholder="Start..." autocomplete="off">
            <button type="button" class="clear-btn" onclick="clearStartInput()" title="Clear start point" aria-label="Clear start point">
                <span class="material-symbols-rounded">close</span>
            </button>
            <input type="hidden" id="start-id" value="">
            <div class="suggest-list" id="start-suggest"></div>
        </div>

        <button type="button" id="scan-qr-btn" class="scan-qr-btn" onclick="startQRScan()" title="Set start point by scanning a QR code">
            <span class="material-symbols-rounded">qr_code_scanner</span> Scan QR
        </button>

        <div class="search-box">
            <span class="material-symbols-rounded search-icon end-icon">location_on</span>
            <input type="text" id="end-input" placeholder="Destination..." autocomplete="off">
            <button type="button" class="clear-btn" onclick="clearEndInput()" title="Clear destination" aria-label="Clear destination">
                <span class="material-symbols-rounded">close</span>
            </button>
            <input type="hidden" id="end-id" value="">
            <div class="suggest-list" id="end-suggest"></div>
        </div>

        <button id="find-route-btn" class="btn-main" onclick="calculateRoute()">Find Route</button>
    </div>
</div>

<!-- 🚨 左側資源面板 -->
<div class="resource-panel" id="resource-panel">
    <div class="resource-panel-title">Find the nearest</div>
    <div class="resource-filters">
    <div class="res-btn" data-res="toilet-male" onclick="toggleResource('toilet-male')">
        <span class="material-symbols-rounded">man</span> <span>Male Toilet</span>
    </div>
    <div class="res-btn" data-res="toilet-female" onclick="toggleResource('toilet-female')">
        <span class="material-symbols-rounded">woman</span> <span>Female Toilet</span>
    </div>
    <div class="res-btn" data-res="toilet-accessible" onclick="toggleResource('toilet-accessible')">
        <span class="material-symbols-rounded">accessible</span> <span>Accessible Toilet</span>
    </div>
    <div class="res-btn" data-res="vending" onclick="toggleResource('vending')">
        <span class="material-symbols-rounded">local_convenience_store</span> <span>Vending</span>
    </div>
    <div class="res-btn" data-res="lift" onclick="toggleResource('lift')">
        <span class="material-symbols-rounded">elevator</span> <span>Lift</span>
    </div>
    <div class="res-btn" data-res="escalator" onclick="toggleResource('escalator')">
        <span class="material-symbols-rounded">escalator</span> <span>Escalator</span>
    </div>
    <div class="res-btn" data-res="stairs" onclick="toggleResource('stairs')">
        <span class="material-symbols-rounded">stairs</span> <span>Stairs</span>
    </div>
    <div class="res-btn" data-res="water" onclick="toggleResource('water')">
        <span class="material-symbols-rounded">water_drop</span> <span>Water</span>
    </div>
    <div class="res-btn" data-res="printers" onclick="toggleResource('printers')">
        <span class="material-symbols-rounded">print</span> <span>Printers</span>
    </div>
    <div class="res-btn" data-res="computers" onclick="toggleResource('computers')">
        <span class="material-symbols-rounded">computer</span> <span>Computers</span>
    </div>
    <div class="res-btn" data-res="microwaves" onclick="toggleResource('microwaves')">
        <span class="material-symbols-rounded">microwave</span> <span>Microwaves</span>
    </div>
    <div class="res-btn" data-res="shops" onclick="toggleResource('shops')">
        <span class="material-symbols-rounded">shopping_bag</span> <span>Shops</span>
    </div>
    <div class="res-btn" data-res="banking" onclick="toggleResource('banking')">
        <span class="material-symbols-rounded">account_balance</span> <span>Banking</span>
    </div>
    <div class="res-btn" data-res="charging" onclick="toggleResource('charging')">
        <span class="material-symbols-rounded">bolt</span> <span>Charging</span>
    </div>
    </div>
    <div id="find-nearest-block" class="find-nearest-block" style="display:none;">
        <button type="button" class="find-nearest-btn" onclick="findNearest()">
            <span class="material-symbols-rounded">near_me</span> Go
        </button>
    </div>
</div>

<!-- 🎯 最近資源面板 -->
<div id="nearest-panel" class="nearest-panel" style="display:none;">
    <div class="nearest-panel-header">
        <span id="nearest-status">Finding the nearest…</span>
        <button type="button" class="nearest-close" onclick="clearNearestResults()" title="Close">✕</button>
    </div>
    <p id="nearest-info" class="nearest-info"></p>
    <div class="nearest-actions">
        <button type="button" class="btn-main nearest-route" onclick="findRouteToNearest()">
            <span class="material-symbols-rounded">route</span> Find route
        </button>
        <button type="button" class="btn-mini nearest-next" onclick="nextNearest()">
            <span class="material-symbols-rounded">skip_next</span> Next nearest
        </button>
    </div>
</div>

<!-- 右側導航面板 -->
<div class="side-panel" id="side-panel">
    <div class="side-panel-header">
        <div class="side-panel-title">
            <h2>Directions</h2>
            <span id="route-summary" class="route-summary"></span>
        </div>
        <div class="side-panel-actions">
            <button type="button" id="reset-direction-btn" class="side-panel-toggle" onclick="confirmResetDirection()" title="Reset direction" aria-label="Reset direction">
                <span class="material-symbols-rounded">explore</span>
            </button>
            <button type="button" id="nav-cancel-btn" class="side-panel-toggle danger" onclick="confirmCancelNavigation()" title="Cancel navigation">
                <span class="material-symbols-rounded">close</span>
            </button>
            <button type="button" id="side-panel-toggle" class="side-panel-toggle" onclick="toggleSidePanel()" title="Hide / Show directions">
                <span class="material-symbols-rounded">keyboard_arrow_down</span>
            </button>
        </div>
    </div>
    <div class="side-panel-body" id="side-panel-body">
        <div id="nav-controls" style="display: none; margin-bottom: 15px;">
            <div id="nav-status" style="font-weight: bold; color: #60a5fa; margin-bottom: 10px;">Ready</div>
            <button id="nav-next-btn" class="btn-main" style="width: 100%; justify-content: center;">Next Step ➔</button>
        </div>
        <div id="route-steps">Select a start and end point.</div>
    </div>
</div>

<div id="canvas-container"></div>

<div id="node-popup" class="node-popup">
    <div class="popup-content">
        <h3 id="popup-title">Room Name</h3>
        <p id="popup-desc">Description</p>
        <button class="btn-main" style="width: 100%; margin-top: 10px; justify-content: center;" onclick="setAsSource()">
            <span class="material-symbols-rounded">radio_button_checked</span> Set as Source
        </button>
        <button class="btn-main" style="width: 100%; margin-top: 10px; justify-content: center;" onclick="setAsDestination()">
            <span class="material-symbols-rounded">my_location</span> Set as Destination
        </button>
        <button class="btn-close" onclick="closeNodePopup()">✕ Close</button>
    </div>
</div>

<!-- 📷 QR 掃描彈窗 -->
<div id="qr-modal" class="qr-modal">
    <div class="qr-modal-content">
        <h3>Scan location QR code</h3>
        <div id="qr-reader"></div>
        <p class="qr-hint">The QR sets your source (location) and facing direction.</p>
        <button type="button" class="btn-main" onclick="stopQRScan()">✕ Cancel</button>
    </div>
</div>

<!-- ✔️ 確認彈窗 (tick = yes, cross = no) -->
<div id="confirm-modal" class="qr-modal bottom-sheet">
    <div class="qr-modal-content confirm-content">
        <h3 id="confirm-title">Cancel navigation?</h3>
        <p id="confirm-message" class="qr-hint">Are you sure you want to cancel the current navigation?</p>
        <div class="confirm-actions">
            <button type="button" class="confirm-btn confirm-yes" onclick="confirmYes()">
                <span class="material-symbols-rounded">check</span> Yes
            </button>
            <button type="button" class="confirm-btn confirm-no" onclick="confirmNo()">
                <span class="material-symbols-rounded">close</span> No
            </button>
        </div>
    </div>
</div>

<script src="app.js?v=107"></script>
</body>
</html>