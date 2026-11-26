const API = 'api.php?action=data';
let scene, camera, renderer, labelRenderer, controls, raycaster, pointer;
let nodes = [], edges = [], floors = [];
let allowedNodesSet = new Set();
let nodeMeshes = {};
let floorGroups = {};
let clickedNodeId = null;
let pointerDownPos = { x: 0, y: 0 };
let allLabels = [];
let currentFilteredFloor = 'all';
let idleTimeout = null;
let isInitialLoad = true;
let activeResourceType = null;
let nearestResults = [];        // sorted nearest resource nodes from the API
let nearestIndex = -1;          // currently shown index into nearestResults
let nearestHighlightId = null;  // node id currently highlighted as "nearest"
let lastRoutedStartId = null;   // start id the currently drawn route was for
let lastRoutedEndId = null;     // destination id the currently drawn route was for
let startPromptActive = false;  // red "set your start first" prompt is showing
let routeType = 'time';

// Resource filter key -> the node types it matches (nodetype table).
const RESOURCE_TYPES = {
    'toilet-male': [26],
    'toilet-female': [27],
    'toilet-accessible': [28],
    'vending': [29],
    'lift': [17],
    'escalator': [10],
    'stairs': [11],
    'water': [20],
    'printers': [13],
    'computers': [14],
    'microwaves': [16],
    'shops': [18],
    'banking': [19],
    'charging': [25],
};
let routeGroup = new THREE.Group();
let currentCurve = null;
let flowParticles = [];
let flowProgress = 0;
const FLOW_SPEED = 0.006;
let navPath = [];
let navIndex = -1;
let isNavigating = false;
let isFlying = false;
let targetCamPos = new THREE.Vector3();
let targetLookAt = new THREE.Vector3();
const floorY = { 17: 0, 21: 10000, 24: 20000 };
let baseScale = 30.0;

// =====================================================================
//  Indoor positioning: phone sensors + QR anchoring + particle filter
// =====================================================================
const DEVICE_HEADING_OFFSET = 0;   // developer: fixed compass -> map offset (degrees)
const ANDROID_ALPHA_INVERT = false; // set true only if the arrow turns opposite to the phone on your Android device
const COMPASS_ACCURACY_THRESHOLD = 30; // trust magnetometer only if its accuracy (deg) is within this
const STEP_LENGTH_METERS = 0.7;    // average step length, tune for your building
const STEP_THRESHOLD = 2.6;        // accelerometer magnitude (m/s^2) to count a step
const STEP_REARM_THRESHOLD = 1.4;  // magnitude must drop below this before next step
const STEP_COOLDOWN_MS = 260;      // min time between consecutive steps (ms)
const MOVEMENT_THRESHOLD_METERS = 0.4; // ignore pointer moves smaller than this (anti-jitter)
const TURN_THRESHOLD_DEG = 30;         // vibrate a "turn" alert above this angle
const VIBRATE_ARRIVE = [100];          // vibration when reaching a node
const VIBRATE_TURN = [70, 50, 70];     // vibration when a turn is needed
const PARTICLE_COUNT = 400;
const MAP_MATCH_SIGMA = 900;       // corridor snap tolerance (3D units)
const ROUTE_BIAS_SIGMA = 2500;     // "follow the line" bias (3D units)

let userMarker = null;
let userFloorId = null;
let userHeadingDeg = 0;            // facing direction on the map (0 = map north / +Z)
let compassHeadingDeg = null;      // raw phone compass heading (degrees)
let compassAccuracy = null;        // magnetometer accuracy in degrees (iOS webkitCompassAccuracy)
let compassReliable = false;       // whether we should trust the compass for heading
let deviceTilt = null;             // { beta, gamma } from the tilt sensor
let headingOffset = 0;             // runtime calibration offset (degrees)
let sensorsEnabled = false;
let pedometerSteps = 0;
let lastConsumedSteps = 0;
let lastStepTime = 0;
let stepArmed = true;              // step detector: wait for acceleration to drop before next count
let smoothedPos = null;            // { x, z, floorId } low-pass filtered pointer position
let pendingFacing = null;          // facing value waiting for first compass reading to calibrate
let routePathPoints3D = [];
let qrScanner = null;
let devicePlatform = 'other';       // 'android' | 'ios' | 'desktop' | 'other'
let sessionId = null;               // persistent cookie session id for this device
let hasAbsoluteHeading = false;     // whether an absolute compass source was seen
let searchHighlightIds = new Set(); // node ids highlighted by search disambiguation

// Client-side particle filter. Runs entirely in the browser so the server
// only ever serves the static graph - map matching cost stays on the device.
class ParticleFilter {
    constructor() {
        this.particles = [];
        this.initialized = false;
        this.N = PARTICLE_COUNT;
        this.walkableSegments = [];
        this.routePoints = [];
        this.lastResample = 0;
    }
    setSegments(segs) { this.walkableSegments = segs || []; }
    setRoutePoints(pts) { this.routePoints = pts || []; }
    init(x, z, headingRad, floorId, spread = 1200) {
        this.particles = [];
        for (let i = 0; i < this.N; i++) {
            const a = Math.random() * Math.PI * 2;
            const r = Math.sqrt(Math.random()) * spread;
            this.particles.push({
                x: x + Math.cos(a) * r,
                z: z + Math.sin(a) * r,
                heading: headingRad + (Math.random() - 0.5) * 0.6,
                floorId,
                w: 1 / this.N
            });
        }
        this.initialized = true;
        this.lastResample = 0;
    }
    predict(distance) {
        const baseHeading = deg2rad(userHeadingDeg);
        for (const p of this.particles) {
            p.heading = baseHeading + gaussianRandom() * 0.10;
            p.x += distance * Math.sin(p.heading);
            p.z += distance * Math.cos(p.heading);
        }
    }
    mapMatch() {
        const segs = this.walkableSegments;
        let sumW = 0;
        for (const p of this.particles) {
            let bestD = Infinity, bx = p.x, bz = p.z;
            for (const s of segs) {
                if (s.floorId !== p.floorId) continue;
                const dx = s.bx - s.ax, dz = s.bz - s.az;
                const len2 = dx * dx + dz * dz;
                if (len2 === 0) continue;
                let t = ((p.x - s.ax) * dx + (p.z - s.az) * dz) / len2;
                t = Math.max(0, Math.min(1, t));
                const px = s.ax + t * dx, pz = s.az + t * dz;
                const d = Math.hypot(p.x - px, p.z - pz);
                if (d < bestD) { bestD = d; bx = px; bz = pz; }
            }
            if (bestD < Infinity) { p.x = bx; p.z = bz; }
            let w = Math.exp(-(bestD * bestD) / (2 * MAP_MATCH_SIGMA * MAP_MATCH_SIGMA));
            if (this.routePoints.length > 1) {
                const rd = distanceToPolyline(p.x, p.z, this.routePoints);
                w *= Math.exp(-(rd * rd) / (2 * ROUTE_BIAS_SIGMA * ROUTE_BIAS_SIGMA));
            }
            p.w = w;
            sumW += w;
        }
        if (sumW > 0) this.particles.forEach(p => p.w /= sumW);
        else this.particles.forEach(p => p.w = 1 / this.N);
    }
    resample() {
        const N = this.N, acc = new Array(N);
        let running = 0;
        for (let i = 0; i < N; i++) { running += this.particles[i].w; acc[i] = running; }
        const newP = [];
        for (let i = 0; i < N; i++) {
            const r = Math.random();
            let lo = 0, hi = N - 1, idx = 0;
            while (lo <= hi) { const mid = (lo + hi) >> 1; if (acc[mid] < r) lo = mid + 1; else { hi = mid - 1; idx = mid; } }
            const src = this.particles[idx];
            newP.push({
                x: src.x + gaussianRandom() * 30,
                z: src.z + gaussianRandom() * 30,
                heading: src.heading + gaussianRandom() * 0.03,
                floorId: src.floorId,
                w: 1 / N
            });
        }
        this.particles = newP;
    }
    estimate() {
        if (this.particles.length === 0) return null;
        let x = 0, z = 0, sx = 0, cz = 0, wsum = 0;
        for (const p of this.particles) {
            x += p.x * p.w; z += p.z * p.w;
            sx += Math.sin(p.heading) * p.w; cz += Math.cos(p.heading) * p.w;
            wsum += p.w;
        }
        if (wsum === 0) return null;
        return { x: x / wsum, z: z / wsum, heading: Math.atan2(sx, cz), floorId: this.particles[0].floorId };
    }
}

const particleFilter = new ParticleFilter();

/* Toolbar/panel positioning must work even if WebGL is unavailable and
   init() throws: register these before init() and call it once immediately. */
updateUIPositions();
window.addEventListener('load', updateUIPositions, false);
if (window.ResizeObserver) {
    new ResizeObserver(function () { updateUIPositions(); })
        .observe(document.getElementById('top-bar'));
}
if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () { updateUIPositions(); }).catch(function () {});
}

/* Settings changed in another tab/page: apply it live (same origin). */
window.addEventListener('storage', function (event) {
    if (event.key === 'smartmapnav:prefs') { applyInheritedSettings(true); }
});
window.addEventListener('focus', function () { applyInheritedSettings(true); });
window.addEventListener('pageshow', function () { applyInheritedSettings(true); });
document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { applyInheritedSettings(true); }
});

/* Set the selects and font size even if WebGL/three.js fails before
   loadData() runs, so the page still reflects the saved settings. */
applyInheritedSettings();

init();
function init() {
    scene = new THREE.Scene();
    const canvasBg = document.createElement('canvas');
    canvasBg.width = 2; canvasBg.height = 2;
    const ctxBg = canvasBg.getContext('2d');
    const grd = ctxBg.createRadialGradient(1, 1, 0, 1, 1, 1);
    grd.addColorStop(0, '#1e293b');
    grd.addColorStop(1, '#0f172a');
    ctxBg.fillStyle = grd;
    ctxBg.fillRect(0, 0, 2, 2);
    scene.background = new THREE.CanvasTexture(canvasBg);
    camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 1, 500000);
    camera.position.set(25000, 25000, 25000);
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.toneMapping = THREE.NoToneMapping;
    document.getElementById('canvas-container').appendChild(renderer.domElement);
    labelRenderer = new THREE.CSS2DRenderer();
    labelRenderer.setSize(window.innerWidth, window.innerHeight);
    labelRenderer.domElement.style.position = 'absolute';
    labelRenderer.domElement.style.top = '0px';
    labelRenderer.domElement.style.pointerEvents = 'none';
    document.getElementById('canvas-container').appendChild(labelRenderer.domElement);
    scene.add(new THREE.AmbientLight(0xffffff, 0.6));
    const dirLight = new THREE.DirectionalLight(0xffffff, 1.0);
    dirLight.position.set(25000, 30000, 25000);
    scene.add(dirLight);
    scene.add(routeGroup);
    createUserMarker();
    setupSensors();
    detectPlatform();
    ensureSessionCookie();
    bindSensorPermissionPrompt();
    // Dismiss the red "set start first" prompt as soon as the user interacts.
    document.addEventListener('pointerdown', () => { if (startPromptActive) dismissStartPrompt(); }, true);
    const startInputEl = document.getElementById('start-input');
    if (startInputEl) startInputEl.addEventListener('focus', dismissStartPrompt);
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.target.set(0, 10000, 0);
    controls.autoRotate = true;
    controls.autoRotateSpeed = 1.0;
    controls.addEventListener('start', () => {
        clearTimeout(idleTimeout);
        controls.autoRotate = false;
        isFlying = false;
        isInitialLoad = false;
    });
    controls.addEventListener('end', () => {
        startIdleTimer();
    });
    raycaster = new THREE.Raycaster();
    pointer = new THREE.Vector2();
    renderer.domElement.addEventListener('pointerdown', onPointerDown, false);
    renderer.domElement.addEventListener('pointerup', onPointerUp, false);
    window.addEventListener('resize', () => {
        onWindowResize();
        updateUIPositions();
    }, false);
    setupAutocomplete('start-input', 'start-id', 'start-suggest');
    setupAutocomplete('end-input', 'end-id', 'end-suggest');
    animate();
    loadData();
    setTimeout(updateUIPositions, 100);
}
function changeRouteType(type) {
    routeType = type;
    updateURL();
}
function updateURL() {
    const startId = document.getElementById('start-id').value;
    const destId = document.getElementById('end-id').value;
    const url = new URL(window.location.href);
    if (startId) url.searchParams.set('start', startId); else url.searchParams.delete('start');
    if (destId) url.searchParams.set('dest', destId); else url.searchParams.delete('dest');
    if (activeResourceType) url.searchParams.set('resource', activeResourceType); else url.searchParams.delete('resource');
    url.searchParams.set('route_type', routeType);
    window.history.replaceState({}, '', url);
}
function applyURLParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const startId = urlParams.get('start');
    const destId = urlParams.get('dest');
    const resource = urlParams.get('resource');
    const routeTypeParam = urlParams.get('route_type');
    const stepFreeParam = urlParams.get('step_free');
    if (routeTypeParam === 'time' || routeTypeParam === 'smooth' || routeTypeParam === 'step_free') {
        routeType = routeTypeParam;
    } else if (stepFreeParam === '1') {
        routeType = 'step_free';
    } else {
        /* Inherited from CityU Map Nav settings (see index.php). */
        routeType = document.body.dataset.defaultRouteType || 'time';
    }
    const rtSelect = document.getElementById('route-type-select');
    if (rtSelect) rtSelect.value = routeType;
    if (startId) {
        const node = nodes.find(n => Number(n.id) === Number(startId));
        if (node) {
            document.getElementById('start-input').value = node.name;
            document.getElementById('start-id').value = node.id;
            // A QR/URL may also supply the initial facing direction.
            const facing = urlParams.get('facing');
            if (facing != null) {
                const fid = getFloorId(node);
                setUserAtNode(Number(startId), Number(facing));
                pendingFacing = Number(facing);
                filterFloor(fid);
            }
        }
    }
    if (destId) {
        const node = nodes.find(n => Number(n.id) === Number(destId));
        if (node) {
            document.getElementById('end-input').value = node.name;
            document.getElementById('end-id').value = node.id;
        }
    }
    if (startId && destId) {
        setTimeout(calculateRoute, 500);
    }
    if (resource) {
        toggleResource(resource);
    }
}

/* ---------------------------------------------------------------------
 * Live settings from the CityU Map Nav shell (same origin)
 * ------------------------------------------------------------------ */
function readSharedPrefs() {
    try {
        return JSON.parse(localStorage.getItem('smartmapnav:prefs') || 'null') || {};
    } catch (e) { return {}; }
}

function readSharedRole() {
    const m = document.cookie.match(/(?:^|;\s*)usertype=([^;]+)/);
    const cookieRole = m ? decodeURIComponent(m[1]).toLowerCase() : '';
    /* The cookie is authoritative; the shared store is a fallback for browsers
       that block cookies (settings.js mirrors the role there as well). */
    const key = cookieRole || String(readSharedPrefs().usertype || '').toLowerCase();
    return { student: 'Student', julac: 'JULAC', guest: 'Guest' }[key] || null;
}

/**
 * Apply the settings saved by the CityU Map Nav shell.
 */
function applyInheritedSettings(force) {
    const params = new URLSearchParams(window.location.search);
    const prefs = readSharedPrefs();
    const isExplicit = function (name) { return !force && params.has(name); };

    /* Font size (Normal / Large / Extra Large). */
    const scale = Number(prefs.text_scale);
    if (!isExplicit('text_scale') && [100, 125, 150].indexOf(scale) !== -1) {
        document.documentElement.style.fontSize = scale + '%';
        document.documentElement.setAttribute('data-text-scale', String(scale));
        updateUIPositions();
    }

    /* Default route type, unless the URL asked for one explicitly. */
    let routeChanged = false;
    if (!isExplicit('route_type')) {
        const map = { fastest: 'time', smoothest: 'smooth', step_free: 'step_free' };
        const route = map[prefs.default_route];
        const rtSelect = document.getElementById('route-type-select');
        if (route && rtSelect && rtSelect.value !== route) {
            rtSelect.value = route;
            routeType = route;
            routeChanged = true;
        }
    }

    /* A route that is already drawn must follow the new mode immediately. */
    if (routeChanged && force) {
        const fromId = document.getElementById('start-id').value;
        const toId = document.getElementById('end-id').value;
        if (fromId && toId) {
            try { calculateRoute(); } catch (e) { /* scene not ready */ }
        }
    }

    /* Role, unless the URL asked for one explicitly. */
    if (!isExplicit('usertype')) {
        const role = readSharedRole();
        const roleSel = document.getElementById('role-select');
        if (role && roleSel && roleSel.value !== role) {
            roleSel.value = role;
            /* Rebuild only if the scene already exists; on the very first
               load refreshMap() runs next and reads the select itself. */
            if (Object.keys(nodeMeshes).length) {
                try { changeRole(role); } catch (e) { /* scene not ready */ }
            }
        }
    }
}

function updateUIPositions() {
    const topBar = document.getElementById('top-bar');
    const resourcePanel = document.getElementById('resource-panel');
    if (!topBar) return;

    if (window.innerWidth > 768) {
        const topBarHeight = topBar.getBoundingClientRect().height;
        const collapsed = topBar.classList.contains('collapsed');
        if (resourcePanel) {
            resourcePanel.style.top = collapsed ? '20px' : (topBarHeight + 20) + 'px';
        }
        /* Keep the sensor panel below the toolbar as well, unless the visitor
           has dragged it to a position of their own. */
        const locPanel = document.getElementById('location-panel');
        if (locPanel && !hasSavedLocPanelPos()) {
            locPanel.style.top = collapsed ? '70px' : (topBarHeight + 20) + 'px';
        }
    } else {
        if (resourcePanel) resourcePanel.style.top = '';
    }
}

function hasSavedLocPanelPos() {
    try { return !!localStorage.getItem('locPanelPos'); } catch (e) { return false; }
}

function toggleSidePanel() {
    const panel = document.getElementById('side-panel');
    if (panel) panel.classList.toggle('collapsed');
}

// On phones: show the Directions panel only while a route is active, and hide
// the "Find the nearest" panel while a route is on screen.
function updateRoutePanelVisibility() {
    document.body.classList.toggle('route-active', isNavigating);
}

function toggleLocationPanel() {
    const panel = document.getElementById('location-panel');
    if (panel) panel.classList.toggle('collapsed');
}

// Collapse (hide) the sensor/location panel to free up screen space, e.g.
// after a successful QR anchor. Collapsing is idempotent, so it never
// re-opens a panel the user already hid.
function collapseLocationPanel() {
    const panel = document.getElementById('location-panel');
    if (panel) panel.classList.add('collapsed');
}

let confirmCallback = null;

function confirmCancelNavigation() {
    if (!isNavigating) {
        showLocStatus('No navigation to cancel');
        return;
    }
    openConfirmModal('Cancel navigation?', 'Are you sure you want to cancel the current navigation?', () => {
        resetNavigation();
    });
}

function openConfirmModal(title, message, onYes) {
    document.getElementById('confirm-title').textContent = title;
    document.getElementById('confirm-message').textContent = message;
    confirmCallback = onYes;
    document.getElementById('confirm-modal').classList.add('active');
}

function confirmYes() {
    document.getElementById('confirm-modal').classList.remove('active');
    const cb = confirmCallback;
    confirmCallback = null;
    if (cb) cb();
}

function confirmNo() {
    document.getElementById('confirm-modal').classList.remove('active');
    confirmCallback = null;
}
function toggleUI() {
    const topBar = document.getElementById('top-bar');
    const btn = document.getElementById('ui-toggle-btn');
    if (!topBar) return;
    topBar.classList.toggle('collapsed');
    if (topBar.classList.contains('collapsed')) {
        btn.innerHTML = '<span class="material-symbols-rounded">filter_list</span>';
    } else {
        btn.innerHTML = '<span class="material-symbols-rounded">menu_open</span>';
    }
    updateUIPositions();
}
// Red "set your start first" prompt on the Start box and Scan QR button.
function setStartPrompt(on) {
    startPromptActive = on;
    const startInput = document.getElementById('start-input');
    if (startInput) {
        const startBox = startInput.closest('.search-box');
        if (startBox) startBox.classList.toggle('highlight-required', on);
    }
    const qrBtn = document.getElementById('scan-qr-btn');
    if (qrBtn) qrBtn.classList.toggle('highlight-required', on);
}

function dismissStartPrompt() {
    setStartPrompt(false);
}

function updateStartPrompt() {
    setStartPrompt(activeResourceType !== null && !document.getElementById('start-id').value);
}

function toggleResource(res) {
    if (activeResourceType === res) {
        activeResourceType = null;
    } else {
        activeResourceType = res;
    }
    // A different filter invalidates any previous "nearest" result.
    clearNearestResults();
    updateURL();
    document.querySelectorAll('.res-btn').forEach(btn => {
        const isActive = btn.dataset.res === activeResourceType;
        btn.classList.toggle('active', isActive);
    });
    const findBlock = document.getElementById('find-nearest-block');
    if (findBlock) findBlock.style.display = activeResourceType ? 'flex' : 'none';
    updateStartPrompt();
    const types = RESOURCE_TYPES[res] || [];
    allLabels.forEach(label => {
        const iconDom = label.element.querySelector('.map-icon');
        if (!iconDom) return;
        if (activeResourceType === null) {
            iconDom.classList.remove('highlight-resource');
            label.element.style.opacity = '1';
            label.element.style.zIndex = '1';
            return;
        }
        const nodeId = label.userData.id;
        const node = nodes.find(n => Number(n.id) === Number(nodeId));
        const match = !!node && types.includes(node.type);
        if (match) {
            iconDom.classList.add('highlight-resource');
            label.element.style.opacity = '1';
            label.element.style.zIndex = '9999';
        } else {
            iconDom.classList.remove('highlight-resource');
            label.element.style.opacity = '0.08';
            label.element.style.zIndex = '1';
        }
    });
}
// ---- "Find the nearest" filtered resource ----
function clearNearestResults() {
    nearestResults = [];
    nearestIndex = -1;
    highlightNearestNode(null);
    const panel = document.getElementById('nearest-panel');
    if (panel) panel.style.display = 'none';
}

function highlightNearestNode(nodeId) {
    nearestHighlightId = nodeId == null ? null : Number(nodeId);
}

// Pulse the "Find Route" button when both endpoints are set but the route
// currently shown on the map is stale (no route yet, or a node changed since
// it was drawn).
function updateFindRoutePulse(startIdVal, destIdVal) {
    const btn = document.getElementById('find-route-btn');
    if (!btn) return;
    const bothSet = startIdVal !== '' && destIdVal !== '';
    const routeStale = !(String(lastRoutedStartId) === String(startIdVal) && String(lastRoutedEndId) === String(destIdVal));
    btn.classList.toggle('pulse-ready', bothSet && routeStale);
}

function findNearest() {
    const startId = document.getElementById('start-id').value;
    if (!startId) {
        alert('Please set a start point first. Type it in the "Start…" field or scan a location QR code.');
        const panel = document.getElementById('location-panel');
        if (panel) panel.classList.remove('collapsed');
        const input = document.getElementById('start-input');
        if (input) input.focus();
        return;
    }
    const types = RESOURCE_TYPES[activeResourceType] || [];
    if (types.length === 0) {
        alert('Please choose a resource filter first.');
        return;
    }
    // Remove the current destination point: the nearest resource will be the
    // new destination.
    document.getElementById('end-input').value = '';
    document.getElementById('end-id').value = '';
    updateURL();
    // Hide the "Go" button until the user selects a filter again.
    const findBlock = document.getElementById('find-nearest-block');
    if (findBlock) findBlock.style.display = 'none';
    document.getElementById('nearest-status').textContent = 'Finding the nearest…';
    document.getElementById('nearest-info').textContent = '';
    document.getElementById('nearest-panel').style.display = 'flex';
    fetch(`api.php?action=nearest&from=${encodeURIComponent(startId)}&types=${encodeURIComponent(types.join(','))}&route_type=${encodeURIComponent(routeType)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.ok) {
                document.getElementById('nearest-status').textContent = data.error || 'No nearest resource found.';
                return;
            }
            nearestResults = data.data.results || [];
            nearestIndex = 0;
            showNearest();
        })
        .catch(err => {
            document.getElementById('nearest-status').textContent = 'Failed to find the nearest resource: ' + err;
        });
}

function showNearest() {
    const result = nearestResults[nearestIndex];
    const panel = document.getElementById('nearest-panel');
    if (!result) {
        if (panel) panel.style.display = 'none';
        highlightNearestNode(null);
        return;
    }
    const node = nodes.find(n => Number(n.id) === Number(result.node_id));
    const name = node ? node.name : ('Node ' + result.node_id);
    const seconds = Math.round(result.total_weight);
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    const eta = mins > 0 ? (secs > 0 ? `${mins} min ${secs} sec` : `${mins} min`) : `${secs} sec`;
    document.getElementById('nearest-status').textContent = `Nearest ${nearestIndex + 1} of ${nearestResults.length}`;
    document.getElementById('nearest-info').textContent = `${name} — about ${eta} away`;
    highlightNearestNode(result.node_id);
    if (panel) panel.style.display = 'flex';

    // Zoom the camera in to show the nearest node's location.
    if (node) {
        const fid = getFloorId(node);
        if (currentFilteredFloor !== 'all' && Number(currentFilteredFloor) !== Number(fid)) {
            filterFloor(fid);
        }
        const pos = getNodePosition3D(node.id);
        if (pos) {
            const offset = window.innerWidth <= 768 ? 5000 : 4000;
            flyTo(pos.x + offset, pos.y + offset, pos.z + offset, pos.x, pos.y, pos.z);
        }
    }
}

function nextNearest() {
    if (nearestResults.length === 0) return;
    nearestIndex = (nearestIndex + 1) % nearestResults.length;
    showNearest();
}

function findRouteToNearest() {
    const result = nearestResults[nearestIndex];
    if (!result) return;
    const node = nodes.find(n => Number(n.id) === Number(result.node_id));
    if (!node) return;
    document.getElementById('end-input').value = node.name;
    document.getElementById('end-id').value = node.id;
    clearNearestResults();
    calculateRoute();
}

function startIdleTimer() {
    clearTimeout(idleTimeout);
    idleTimeout = setTimeout(() => {
        if (!isNavigating && !isFlying) {
            controls.autoRotate = true;
        }
    }, 10000);
}
function disposeObject(obj) {
    if (!obj) return;
    obj.traverse(child => {
        if (child.isMesh || child.isLine || child.isSprite) {
            if (child.geometry) child.geometry.dispose();
            if (child.material) {
                if (Array.isArray(child.material)) child.material.forEach(m => m.dispose());
                else {
                    if (child.material.map) child.material.map.dispose();
                    child.material.dispose();
                }
            }
        }
    });
}
function onPointerDown(event) { pointerDownPos = { x: event.clientX, y: event.clientY }; }
function onPointerUp(event) {
    if (isNavigating) return;
    const dx = event.clientX - pointerDownPos.x;
    const dy = event.clientY - pointerDownPos.y;
    if (Math.sqrt(dx * dx + dy * dy) > 15) return;
    const rect = renderer.domElement.getBoundingClientRect();
    pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
    raycaster.setFromCamera(pointer, camera);
    const visibleHitboxes = Object.values(nodeMeshes).filter(m => m.parent && m.parent.visible);
    const intersects = raycaster.intersectObjects(visibleHitboxes);
    if (intersects.length === 0) { closeNodePopup(); return; }

    // Choose by screen-space proximity instead of 3D ray distance. When floors
    // are stacked (e.g. viewing 4/F from above), this lets a lower-floor node
    // you are actually aiming at win over an upper-floor node that is merely
    // closer to the camera along the ray. Search-highlighted nodes win first.
    const sx = event.clientX - rect.left;
    const sy = event.clientY - rect.top;
    let bestId = null, bestDist = Infinity, bestIsHighlighted = false;
    const worldPos = new THREE.Vector3();
    intersects.forEach(i => {
        const isHighlighted = searchHighlightIds.has(Number(i.object.userData.id));
        const sp = i.object.getWorldPosition(worldPos).project(camera);
        const px = (sp.x * 0.5 + 0.5) * rect.width;
        const py = (-sp.y * 0.5 + 0.5) * rect.height;
        const d = Math.hypot(px - sx, py - sy);
        if (isHighlighted && !bestIsHighlighted) {
            bestId = i.object.userData.id;
            bestDist = d;
            bestIsHighlighted = true;
        } else if (isHighlighted === bestIsHighlighted && d < bestDist) {
            bestId = i.object.userData.id;
            bestDist = d;
        }
    });
    if (bestId != null) openNodePopup(bestId);
    else closeNodePopup();
}
function openNodePopup(id) {
    const nodeData = nodes.find(n => Number(n.id) === Number(id));
    if (!nodeData) return;
    clickedNodeId = Number(nodeData.id);
    document.getElementById('popup-title').textContent = nodeData.name;
    document.getElementById('popup-desc').textContent = nodeData.detail || nodeData.description || 'No description available.';
    document.getElementById('node-popup').classList.add('active');
}
function closeNodePopup() {
    document.getElementById('node-popup').classList.remove('active');
    clickedNodeId = null;
}
function setAsDestination() {
    if (!clickedNodeId) return;
    const nodeData = nodes.find(n => Number(n.id) === Number(clickedNodeId));
    if (!nodeData) return;
    document.getElementById('end-input').value = nodeData.name;
    document.getElementById('end-id').value = nodeData.id;
    updateURL();
    closeNodePopup();
    const startId = document.getElementById('start-id').value;
    if (startId) {
        calculateRoute();
    }
}

function setAsSource() {
    if (!clickedNodeId) return;
    const nodeData = nodes.find(n => Number(n.id) === Number(clickedNodeId));
    if (!nodeData) return;
    document.getElementById('start-input').value = nodeData.name;
    document.getElementById('start-id').value = nodeData.id;
    updateURL();
    closeNodePopup();
    const destId = document.getElementById('end-id').value;
    if (destId) {
        calculateRoute();
    }
}
async function loadData() {
    try {
        const res = await fetch(API);
        const data = await res.json();
        nodes = data.nodes;
        edges = data.edges;
        floors = data.floors;
        /* Set the role/route selects before the first build, so the first
           visit already uses the saved values (no second reload needed). */
        applyInheritedSettings();
        refreshMap();
        applyURLParams();
        applyInheritedSettings();
    } catch (err) { alert("Data load failed: " + err); }
}
function computeAllowedNodes() {
    allowedNodesSet.clear();
    const role = document.getElementById('role-select').value;
    if (role === 'Student' || role === 'Guest') {
        nodes.forEach(n => allowedNodesSet.add(Number(n.id)));
    } else if (role === 'JULAC') {
        nodes.forEach(n => {
            if (getFloorId(n) === 17) allowedNodesSet.add(Number(n.id));
        });
        let libNodes = nodes.filter(n => Number(n.id) === 18 || Number(n.parant) === 18);
        let queue = [...libNodes];
        while (queue.length > 0) {
            let curr = queue.shift();
            allowedNodesSet.add(Number(curr.id));
            let children = nodes.filter(n => Number(n.parant) === Number(curr.id));
            queue.push(...children);
        }
    }
}
function changeRole(newRole) {
    closeNodePopup();
    resetNavigation();
    resetUserTracking();
    refreshMap();
}
function refreshMap() {
    if (labelRenderer && labelRenderer.domElement) labelRenderer.domElement.innerHTML = '';
    nodeMeshes = {};
    allLabels = [];
    for (let i = scene.children.length - 1; i >= 0; i--) {
        const obj = scene.children[i];
        if (obj !== scene && !obj.isLight && obj !== routeGroup && obj !== userMarker) {
            disposeObject(obj);
            scene.remove(obj);
        }
    }
    computeAllowedNodes();
    buildBuilding();
    buildWalkableSegments();
    setupSearch();
    const role = document.getElementById('role-select').value;
    const btns = document.querySelectorAll('.filter-btn');
    if (role === 'JULAC') {
        btns.forEach(btn => {
            btn.classList.remove('active');
            const text = btn.textContent.trim();
            if (text === 'F3') btn.classList.add('active');
            if (text !== 'F3') {
                btn.style.opacity = '0.3'; btn.style.pointerEvents = 'none';
            } else {
                btn.style.opacity = '1'; btn.style.pointerEvents = 'auto';
            }
        });
        filterFloor(17);
    } else {
        btns.forEach(btn => {
            btn.style.opacity = '1'; btn.style.pointerEvents = 'auto';
            if (btn.textContent.trim() === 'All') btn.classList.add('active');
            else btn.classList.remove('active');
        });
        filterFloor('all');
    }
}
function getFloorId(node) {
    if (node.type === 2) return Number(node.id);
    let parent = nodes.find(n => Number(n.id) === Number(node.parant));
    if (parent && parent.type === 2) return Number(parent.id);
    if (parent) {
        let grandparent = nodes.find(n => Number(n.id) === Number(parent.parant));
        if (grandparent && grandparent.type === 2) return Number(grandparent.id);
    }
    return 17;
}

function getBuildingInfo(node) {
    let current = node;
    const seen = new Set();
    while (current) {
        const id = Number(current.id);
        if (seen.has(id)) break;
        seen.add(id);
        if (current.type === 1) return { id: id, name: current.name };
        const pid = Number(current.parant);
        if (!pid || pid === id) break;
        current = nodes.find(n => Number(n.id) === pid);
    }
    return null;
}

function getFloorName(node) {
    const fid = getFloorId(node);
    const f = nodes.find(n => Number(n.id) === Number(fid));
    return f ? f.name : ('Floor ' + fid);
}

function highlightSearchNodes(ids) {
    searchHighlightIds = new Set(ids.map(Number));
}

function clearSearchHighlight() {
    searchHighlightIds.clear();
}
function getFloorCenter(fid) {
    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    nodes.forEach(n => {
        if (getFloorId(n) === fid && allowedNodesSet.has(Number(n.id))) {
            if (n.posx < minX) minX = n.posx;
            if (n.posx > maxX) maxX = n.posx;
            if (n.posy < minY) minY = n.posy;
            if (n.posy > maxY) maxY = n.posy;
        }
    });
    if (minX === Infinity) return { cx: 0, cy: 0, width: 0, height: 0 };
    return { cx: (minX + maxX) / 2, cy: (minY + maxY) / 2, width: maxX - minX, height: maxY - minY };
}
function getNodePosition3D(id) {
    const n = nodes.find(x => Number(x.id) === Number(id));
    if (!n) return null;
    const fid = getFloorId(n);
    const { cx, cy } = getFloorCenter(fid);
    let scale = (fid === 21) ? baseScale * 2 : baseScale;
    return new THREE.Vector3((n.posx - cx) * scale, floorY[fid] + 50, (n.posy - cy) * scale);
}
function buildBuilding() {
    [17, 21, 24].forEach(fid => {
        floorGroups[fid] = new THREE.Group();
        scene.add(floorGroups[fid]);
    });
    floors.forEach(f => {
        if (![17, 21, 24].includes(f.id)) return;
        let hasVisibleNodes = false;
        nodes.forEach(n => {
            if (getFloorId(n) === f.id && allowedNodesSet.has(Number(n.id))) hasVisibleNodes = true;
        });
        if (!hasVisibleNodes) return;
        const bounds = getFloorCenter(f.id);
        if (bounds.width === 0) return;
        let scale = (f.id === 21) ? baseScale * 2 : baseScale;
        const geo = new THREE.BoxGeometry((bounds.width * scale) + 5000, 100, (bounds.height * scale) + 5000);
        const mat = new THREE.MeshStandardMaterial({ color: 0x1e293b, transparent: true, opacity: 0.8 });
        const plate = new THREE.Mesh(geo, mat);
        plate.position.set(0, floorY[f.id] - 50, 0);
        floorGroups[f.id].add(plate);
    });
    edges.forEach(e => {
        const n1 = nodes.find(n => Number(n.id) === Number(e.node1));
        const n2 = nodes.find(n => Number(n.id) === Number(e.node2));
        if (n1 && n2 && getFloorId(n1) === getFloorId(n2)) {
            if (!allowedNodesSet.has(n1.id) && !allowedNodesSet.has(n2.id)) return;
            const fid = getFloorId(n1);
            const p1 = getNodePosition3D(n1.id);
            const p2 = getNodePosition3D(n2.id);
            if (!p1 || !p2) return;
            const dist = Math.sqrt((p2.x - p1.x) ** 2 + (p2.z - p1.z) ** 2);
            const angle = Math.atan2(p2.z - p1.z, p2.x - p1.x);
            const corridor = new THREE.Mesh(new THREE.BoxGeometry(dist, 2, 60), new THREE.MeshStandardMaterial({ color: 0x334155 }));
            corridor.position.set((p1.x + p2.x) / 2, floorY[fid] + 1, (p1.z + p2.z) / 2);
            corridor.rotation.y = -angle;
            floorGroups[fid].add(corridor);
        }
    });
    // Node ids that belong to the walkable graph (they have at least one
    // enabled edge). Supernodes/containers with no link are hidden below.
    const linkedIds = new Set();
    edges.forEach(e => { linkedIds.add(Number(e.node1)); linkedIds.add(Number(e.node2)); });

    const ignoredTypes = [4, 5, 21];
    const ignoredNames = ['Fork', 'Corner', 'Road'];
    const role = document.getElementById('role-select').value;
    nodes.forEach(n => {
        if (n.type === 2 || ignoredTypes.includes(n.type) || ignoredNames.some(kw => n.name.includes(kw))) return;
        if (!allowedNodesSet.has(Number(n.id))) return;
        if (role === 'Guest' && n.type === 3) return;
        // Hide supernodes that have no link (no enabled edge): they are
        // containers (building/floor/area) not part of the walkable graph.
        if (!linkedIds.has(Number(n.id))) return;
        const fid = getFloorId(n);
        const pos = getNodePosition3D(n.id);
        const iconInfo = nodeIconInfo(n.type);
        const baseColor = iconInfo.color;
        const iconName = iconInfo.icon;
        const iconClass = iconInfo.cls;
        const visualMesh = new THREE.Mesh(new THREE.CylinderGeometry(40, 40, 80, 16), new THREE.MeshStandardMaterial({ color: baseColor }));
        visualMesh.position.set(pos.x, pos.y, pos.z);
        floorGroups[fid].add(visualMesh);
        const hitbox = new THREE.Mesh(
            new THREE.SphereGeometry(1000, 8, 8),
            new THREE.MeshBasicMaterial({ transparent: true, opacity: 0, depthWrite: false })
        );
        hitbox.position.set(pos.x, pos.y, pos.z);
        hitbox.userData = { id: n.id };
        floorGroups[fid].add(hitbox);
        nodeMeshes[n.id] = hitbox;
        const div = document.createElement('div');
        div.innerHTML = `
            <div class="map-label">
                <div class="map-icon ${iconClass}">
                    <span class="material-symbols-rounded">${iconName}</span>
                </div>
            </div>`;
        const tooltip = document.createElement('div');
        tooltip.className = 'map-tooltip';
        tooltip.textContent = n.name;
        div.querySelector('.map-label').appendChild(tooltip);
        div.addEventListener('click', (e) => {
            e.stopPropagation();
            openNodePopup(n.id);
        });
        div.addEventListener('pointerdown', () => { pointerDownPos = { x: -99999, y: -99999 }; });
        const label = new THREE.CSS2DObject(div);
        label.position.set(pos.x, pos.y + 100, pos.z);
        label.userData = { id: n.id, floorId: fid, baseY: pos.y };
        floorGroups[fid].add(label);
        allLabels.push(label);
    });
}
function setupSearch() {
    document.getElementById('start-input').value = '';
    document.getElementById('start-id').value = '';
    document.getElementById('end-input').value = '';
    document.getElementById('end-id').value = '';
}

function clearStartInput() {
    const input = document.getElementById('start-input');
    input.value = '';
    document.getElementById('start-id').value = '';
    clearSearchHighlight();
    updateURL();
    input.focus();
}

function clearEndInput() {
    const input = document.getElementById('end-input');
    input.value = '';
    document.getElementById('end-id').value = '';
    clearSearchHighlight();
    updateURL();
    input.focus();
}
// True when every character of `q` appears in `name` in order, allowing gaps
// in between. e.g. "lt3" matches "LT-3" even though the characters are not
// consecutive.
function isSubsequence(q, name) {
    let i = 0;
    for (let j = 0; j < name.length && i < q.length; j++) {
        if (name[j] === q[i]) i++;
    }
    return i === q.length;
}

function setupAutocomplete(inputId, idId, suggestId) {
    const input = document.getElementById(inputId);
    const suggest = document.getElementById(suggestId);
    const hiddenId = document.getElementById(idId);
    function renderSuggestions() {
        const q = input.value.trim().toLowerCase();
        suggest.innerHTML = '';
        clearSearchHighlight();
        const role = document.getElementById('role-select').value;
        const validTypes = [3, 9, 12, 18, 19, 24, 26, 27, 28];
        const pool = nodes.filter(n => {
            if (!validTypes.includes(n.type)) return false;
            if (!allowedNodesSet.has(Number(n.id))) return false;
            if (role === 'Guest' && n.type === 3) return false;
            return true;
        });
        let all;
        if (q) {
            // Rank results: a continuous substring match (rank 2) comes before
            // a loose in-order match (rank 1), e.g. "lt3" -> "LT-3".
            const ranked = [];
            for (const n of pool) {
                const name = n.name.toLowerCase();
                if (name.includes(q)) ranked.push([2, n]);
                else if (isSubsequence(q, name)) ranked.push([1, n]);
            }
            ranked.sort((a, b) => (b[0] - a[0]) || a[1].name.localeCompare(b[1].name));
            all = ranked.map(x => x[1]);
        } else {
            all = pool;
        }
        const matches = q ? all : all.slice(0, 16);
        if (matches.length === 0) { suggest.classList.remove('show'); return; }

        // If the query narrows down to a single node, pick it automatically so
        // the user does not have to click it.
        if (q && matches.length === 1) {
            const only = matches[0];
            input.value = only.name;
            hiddenId.value = only.id;
            highlightSearchNodes([Number(only.id)]);
            suggest.classList.remove('show');
            updateURL();
            return;
        }

        // If a typed name is ambiguous (appears more than once), highlight all matches.
        if (q) {
            const counts = {};
            matches.forEach(n => { counts[n.name] = (counts[n.name] || 0) + 1; });
            if (Object.keys(counts).some(k => counts[k] > 1)) {
                highlightSearchNodes(matches.map(n => Number(n.id)));
            }
        }

        // Group results: Building -> Floor -> node name.
        const groups = {};
        matches.forEach(n => {
            const b = getBuildingInfo(n);
            const bname = b ? b.name : 'Unknown Building';
            const fname = getFloorName(n);
            groups[bname] = groups[bname] || {};
            groups[bname][fname] = groups[bname][fname] || [];
            groups[bname][fname].push(n);
        });

        Object.keys(groups).forEach(bname => {
            const bHead = document.createElement('div');
            bHead.className = 'suggest-building';
            bHead.innerHTML = `<span class="material-symbols-rounded">apartment</span>${bname}`;
            suggest.appendChild(bHead);
            Object.keys(groups[bname]).forEach(fname => {
                const fHead = document.createElement('div');
                fHead.className = 'suggest-floor';
                fHead.innerHTML = `<span class="material-symbols-rounded">layers</span>${fname}`;
                suggest.appendChild(fHead);
                groups[bname][fname].forEach(n => {
                    const item = document.createElement('div');
                    item.className = 'suggest-item suggest-node';
                    const iconInfo = nodeIconInfo(n.type);
                    item.innerHTML = `<span class="material-symbols-rounded">${iconInfo.icon}</span><span>${n.name}</span>`;
                    item.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        input.value = n.name;
                        hiddenId.value = n.id;
                        highlightSearchNodes([Number(n.id)]);
                        suggest.classList.remove('show');
                        updateURL();
                    });
                    suggest.appendChild(item);
                });
            });
        });

        suggest.classList.add('show');
    }
    input.addEventListener('input', renderSuggestions);
    input.addEventListener('focus', () => { renderSuggestions(); });
    input.addEventListener('blur', () => { setTimeout(() => { suggest.classList.remove('show'); clearSearchHighlight(); }, 120); });
    input.addEventListener('keydown', (e) => { if (e.key === 'Escape') { suggest.classList.remove('show'); clearSearchHighlight(); input.blur(); } });
}
function filterFloor(fid) {
    const role = document.getElementById('role-select').value;
    if (role === 'JULAC') fid = 17;
    currentFilteredFloor = fid;
    controls.autoRotate = false;
    startIdleTimer();
    const btns = document.querySelectorAll('.filter-btn');
    btns.forEach(btn => btn.classList.remove('active'));
    if (role === 'JULAC') {
        btns.forEach(btn => {
            const text = btn.textContent.trim();
            if (text === 'F3') btn.classList.add('active');
            if (text !== 'F3') { btn.style.opacity = '0.3'; btn.style.pointerEvents = 'none'; }
            else { btn.style.opacity = '1'; btn.style.pointerEvents = 'auto'; }
        });
    } else {
        btns.forEach(btn => {
            btn.style.opacity = '1'; btn.style.pointerEvents = 'auto';
            const text = btn.textContent.trim();
            if (fid === 'all' && text === 'All') btn.classList.add('active');
            if (fid === 17 && text === 'F3') btn.classList.add('active');
            if (fid === 21 && text === 'F4') btn.classList.add('active');
            if (fid === 24 && text === 'F5') btn.classList.add('active');
        });
    }
    [17, 21, 24].forEach(id => {
        if (floorGroups[id]) floorGroups[id].visible = (fid === 'all' || fid === id);
    });
    allLabels.forEach(label => {
        if (fid !== 'all' && Number(label.userData.floorId) !== Number(fid)) {
            label.visible = false;
        } else {
            label.visible = true;
        }
    });
    if (fid === 'all') {
        flyTo(35000, 35000, 35000, 0, 10000, 0);
    } else {
        const bounds = getFloorCenter(fid);
        if (bounds.width > 0) {
            const dist = Math.max(10000, bounds.width * 30 * 1.2);
            flyTo(bounds.cx + dist, floorY[fid] + dist, bounds.cy + dist, bounds.cx, floorY[fid], bounds.cy);
        } else {
            flyTo(10000, floorY[fid] + 10000, 10000, 0, floorY[fid], 0);
        }
    }
}
function makeGlowTexture(hexColor, size = 256) {
    const canvas = document.createElement('canvas');
    canvas.width = size; canvas.height = size;
    const ctx = canvas.getContext('2d');
    const c = new THREE.Color(hexColor);
    const r = Math.round(c.r * 255), g = Math.round(c.g * 255), b = Math.round(c.b * 255);
    const grd = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
    grd.addColorStop(0.00, 'rgba(255,255,255,1)');
    grd.addColorStop(0.12, `rgba(${r},${g},${b},1)`);
    grd.addColorStop(0.35, `rgba(${r},${g},${b},0.45)`);
    grd.addColorStop(1.00, `rgba(${r},${g},${b},0)`);
    ctx.fillStyle = grd; ctx.fillRect(0, 0, size, size);
    return new THREE.CanvasTexture(canvas);
}
function clearRouteLight() {
    disposeObject(routeGroup); routeGroup.clear();
    currentCurve = null; flowParticles = []; flowProgress = 0;
}
function flyTo(camX, camY, camZ, lookX, lookY, lookZ) {
    targetCamPos.set(camX, camY, camZ); targetLookAt.set(lookX, lookY, lookZ);
    isFlying = true;
    controls.autoRotate = false;
    clearTimeout(idleTimeout);
}
function resetNavigation() {
    isNavigating = false; navPath = []; navIndex = -1; clearRouteLight();
    updateRoutePanelVisibility();
    routePathPoints3D = [];
    particleFilter.setRoutePoints([]);
    document.getElementById('nav-controls').style.display = 'none';
    document.getElementById('route-steps').innerHTML = 'Select a start and end point.';
    const summaryEl = document.getElementById('route-summary');
    if (summaryEl) summaryEl.textContent = '';
    document.querySelectorAll('.nav-step').forEach(li => { li.classList.remove('active-step', 'passed-step'); });
    updateUIPositions();
    startIdleTimer();
}
function jumpToStep(index) {
    if (!isNavigating) return;
    navIndex = index;
    nextStep();
}
function nextStep() {
    if (!isNavigating || navIndex >= navPath.length) return;
    const currentId = navPath[navIndex];
    const node = nodes.find(n => Number(n.id) === Number(currentId));
    const pos = getNodePosition3D(currentId);
    moveUserToStep(navIndex);
    vibrateUser(VIBRATE_ARRIVE);
    if (turnAngleAt(navIndex) > TURN_THRESHOLD_DEG) vibrateUser(VIBRATE_TURN);
    document.getElementById('nav-status').innerText = `Step ${navIndex + 1} / ${navPath.length}: ${node.name}`;
    document.querySelectorAll('.nav-step').forEach((div, idx) => {
        div.classList.remove('active-step');
        if (idx < navIndex) div.classList.add('passed-step');
    });
    const activeDiv = document.getElementById(`step-${navIndex}`);
    if (activeDiv) {
        activeDiv.classList.add('active-step');
        activeDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    if (pos) {
        const offset = window.innerWidth <= 768 ? 5000 : 4000;
        flyTo(pos.x + offset, pos.y + offset, pos.z + offset, pos.x, pos.y, pos.z);
    }
    const btn = document.getElementById('nav-next-btn');
    if (navIndex === navPath.length - 1) {
        btn.innerText = "🏁 Finish";
        btn.onclick = resetNavigation;
    } else {
        btn.innerText = "Next Step ➔";
        btn.onclick = () => { navIndex++; nextStep(); };
    }
}
function calculateRoute() {
    const fromId = document.getElementById('start-id').value;
    const toId = document.getElementById('end-id').value;
    updateURL();
    if (!fromId || !toId) return alert("Please select start and end.");
    const fromNode = nodes.find(n => Number(n.id) === Number(fromId));
    const toNode = nodes.find(n => Number(n.id) === Number(toId));
    const summaryEl = document.getElementById('route-summary');
    if (summaryEl) summaryEl.textContent = `${fromNode ? fromNode.name : fromId} → ${toNode ? toNode.name : toId}`;
    if (window.innerWidth <= 768) {
        const topBar = document.getElementById('top-bar');
        if (!topBar.classList.contains('collapsed')) toggleUI();
    }
    filterFloor('all');
    resetNavigation();
    document.getElementById('route-steps').innerHTML = "Calculating...";
    fetch(`api.php?action=route&from=${fromId}&to=${toId}&route_type=${routeType}`)
        .then(res => res.json())
        .then(data => {
            if (!data.ok) { document.getElementById('route-steps').innerHTML = data.error; return; }
            lastRoutedStartId = fromId;
            lastRoutedEndId = toId;
            const rawPath = data.data.path;
            const routePoints = [];
            rawPath.forEach(id => {
                const p = getNodePosition3D(id);
                if (p) { p.y += 30; routePoints.push(p); }
            });
            routePathPoints3D = routePoints;
            particleFilter.setRoutePoints(routePoints);
            if (routePoints.length > 1) {
                currentCurve = new THREE.CatmullRomCurve3(routePoints);
                const glowGeo = new THREE.TubeGeometry(currentCurve, 256, 45, 16, false);
                const glowMat = new THREE.MeshBasicMaterial({ color: 0x00ffd5, transparent: true, opacity: 0.12, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide });
                routeGroup.add(new THREE.Mesh(glowGeo, glowMat));
                const coreGeo = new THREE.TubeGeometry(currentCurve, 256, 14, 16, false);
                const coreMat = new THREE.MeshBasicMaterial({ color: 0x14b8a6, transparent: true, opacity: 0.5, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide });
                routeGroup.add(new THREE.Mesh(coreGeo, coreMat));
                const headGlowTex = makeGlowTexture(0xffffff);
                const tailGlowTex = makeGlowTexture(0x00ffd5);
                const particleCount = 15;
                for (let i = 0; i < particleCount; i++) {
                    const isHead = i === 0;
                    const radius = isHead ? 60 : Math.max(20, 50 - i * 2);
                    const sphereGeo = new THREE.SphereGeometry(radius, 16, 16);
                    const sphereMat = new THREE.MeshBasicMaterial({ color: isHead ? 0xffffff : 0x5eead4, blending: THREE.AdditiveBlending, transparent: true, opacity: isHead ? 1.0 : Math.max(0.1, 1 - i / particleCount), depthWrite: false });
                    const sphere = new THREE.Mesh(sphereGeo, sphereMat);
                    const glowSize = isHead ? 800 : Math.max(200, 600 - i * 30);
                    const glowMat = new THREE.SpriteMaterial({ map: isHead ? headGlowTex : tailGlowTex, blending: THREE.AdditiveBlending, transparent: true, opacity: isHead ? 1.0 : Math.max(0.2, 0.9 - i * 0.04), depthWrite: false });
                    const glow = new THREE.Sprite(glowMat);
                    glow.scale.set(glowSize, glowSize, 1);
                    const group = new THREE.Group();
                    group.add(sphere); group.add(glow);
                    if (isHead) {
                        const light = new THREE.PointLight(0x2dd4bf, 1.5, 5000, 2);
                        group.add(light);
                    }
                    routeGroup.add(group);
                    flowParticles.push({ group, sphere, glow, isHead });
                }
                const startPos = routePoints[0];
                const endPos = routePoints[routePoints.length - 1];
                const startGlow = new THREE.Sprite(new THREE.SpriteMaterial({ map: headGlowTex, blending: THREE.AdditiveBlending, transparent: true, opacity: 0.6, depthWrite: false }));
                startGlow.scale.set(800, 800, 1);
                startGlow.position.set(startPos.x, startPos.y + 60, startPos.z);
                routeGroup.add(startGlow);
                const endGlowTex = makeGlowTexture(0xff3355);
                const endGlow = new THREE.Sprite(new THREE.SpriteMaterial({ map: endGlowTex, blending: THREE.AdditiveBlending, transparent: true, opacity: 0.6, depthWrite: false }));
                endGlow.scale.set(800, 800, 1);
                endGlow.position.set(endPos.x, endPos.y + 60, endPos.z);
                routeGroup.add(endGlow);
                flowProgress = 0;
            }

            // —— Calculate ETA ——
            const totalDistance = data.data.total_distance != null ? data.data.total_distance : data.data.total_weight;
            const totalSeconds = Math.round(data.data.total_weight);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;

            let etaText;
            if (minutes > 0) {
                etaText = seconds > 0 ? `${minutes} min ${seconds} sec` : `${minutes} min`;
            } else {
                etaText = `${seconds} sec`;
            }

            // —— Render panel header with distance + ETA ——
            let html = `
<p style="margin-bottom:6px; font-size: 0.9rem;">
    📏 Total Distance: <strong>${Math.round(totalDistance)} m</strong>
</p>
<p style="margin-bottom:12px; font-size: 0.9rem; color: #10b981;">
    ⏱️ Estimated Time: <strong>${etaText}</strong>
</p>
`;

            // Hide any node whose name contains "fork" from the step-by-step guide.
            // The 3D route line still passes through them; only the text directions
            // skip them. Start and end nodes are always kept.
            const guidePath = rawPath.filter((id, i) => {
                const n = nodes.find(x => Number(x.id) === Number(id));
                if (n && /fork/i.test(n.name) && i !== 0 && i !== rawPath.length - 1) return false;
                return true;
            });
            guidePath.forEach((id, index) => {
                const n = nodes.find(x => Number(x.id) === Number(id));
                if (n) {
                    html += `<div id="step-${index}" class="nav-step" onclick="jumpToStep(${index})">
                        <span class="step-num">${index + 1}.</span>
                        <span class="step-name">${n.name}</span>
                    </div>`;
                }
            });
            document.getElementById('route-steps').innerHTML = html;
            navPath = guidePath;
            navIndex = 0;
            isNavigating = true;
            updateRoutePanelVisibility();
            document.getElementById('nav-controls').style.display = 'block';
            nextStep();
            updateUIPositions();
        })
        .catch(err => { document.getElementById('route-steps').innerHTML = 'Error: ' + err; });
}
function onWindowResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
    labelRenderer.setSize(window.innerWidth, window.innerHeight);
}
function animate() {
    requestAnimationFrame(animate);
    if (isFlying) {
        camera.position.lerp(targetCamPos, 0.1);
        controls.target.lerp(targetLookAt, 0.1);
        if (camera.position.distanceToSquared(targetCamPos) < 10000) {
            camera.position.copy(targetCamPos);
            controls.target.copy(targetLookAt);
            isFlying = false;
            if (!isNavigating) {
                if (isInitialLoad) {
                    controls.autoRotate = true;
                    isInitialLoad = false;
                } else {
                    startIdleTimer();
                }
            }
        }
    }
    if (currentCurve && flowParticles.length > 0) {
        flowProgress += FLOW_SPEED;
        if (flowProgress > 1.0) flowProgress = 0;
        const pulse = 1 + Math.sin(performance.now() / 180) * 0.15;
        flowParticles.forEach((p, index) => {
            let t = flowProgress - (index * 0.012);
            if (t < 0) t += 1.0;
            const pos = currentCurve.getPointAt(t);
            p.group.position.copy(pos);
            if (p.isHead) p.glow.scale.set(800 * pulse, 800 * pulse, 1);
        });
    }
    const camPos = camera.position;
    const startIdVal = document.getElementById('start-id').value;
    const destIdVal = document.getElementById('end-id').value;
    updateFindRoutePulse(startIdVal, destIdVal);
    if (startPromptActive && startIdVal !== '') dismissStartPrompt();
    allLabels.forEach(label => {
        if (currentFilteredFloor !== 'all' && Number(label.userData.floorId) !== Number(currentFilteredFloor)) {
            label.visible = false;
            return;
        }
        const dist = camPos.distanceTo(label.position);
        if (dist > 150000) {
            label.visible = false;
            return;
        }
        label.visible = true;
        const iconDom = label.element.querySelector('.map-icon');
        if (iconDom) {
            iconDom.classList.toggle('highlight-search', searchHighlightIds.has(Number(label.userData.id)));
            // The node the user clicked/pressed gets its own yellow highlight,
            // visually distinct from the search/filter highlight.
            iconDom.classList.toggle('highlight-clicked', clickedNodeId != null && Number(label.userData.id) === clickedNodeId);
            // The nearest filtered resource gets its own green highlight.
            iconDom.classList.toggle('highlight-nearest', nearestHighlightId != null && Number(label.userData.id) === nearestHighlightId);
            // The start and destination nodes get their own blue/red highlights.
            iconDom.classList.toggle('highlight-start', startIdVal !== '' && Number(label.userData.id) === Number(startIdVal));
            iconDom.classList.toggle('highlight-dest', destIdVal !== '' && Number(label.userData.id) === Number(destIdVal));
        }
        const isClickedNode = clickedNodeId != null && Number(label.userData.id) === clickedNodeId;
        const isNearestNode = nearestHighlightId != null && Number(label.userData.id) === nearestHighlightId;
        const isStartNode = startIdVal !== '' && Number(label.userData.id) === Number(startIdVal);
        const isDestNode = destIdVal !== '' && Number(label.userData.id) === Number(destIdVal);
        if (isClickedNode) {
            label.element.style.opacity = '1';
            label.element.style.pointerEvents = 'auto';
        } else if (isNearestNode) {
            label.element.style.opacity = '1';
            label.element.style.pointerEvents = 'auto';
        } else if (isStartNode || isDestNode) {
            label.element.style.opacity = '1';
            label.element.style.pointerEvents = 'auto';
        } else if (isNavigating) {
            label.element.style.opacity = '0.05';
            label.element.style.pointerEvents = 'none';
        } else if (searchHighlightIds.size > 0) {
            if (searchHighlightIds.has(Number(label.userData.id))) {
                label.element.style.opacity = '1';
                label.element.style.pointerEvents = 'auto';
            } else {
                label.element.style.opacity = '0.1';
                label.element.style.pointerEvents = 'none';
            }
        } else if (iconDom && iconDom.classList.contains('highlight-resource')) {
            label.element.style.opacity = '1';
            label.element.style.pointerEvents = 'auto';
        } else if (activeResourceType !== null) {
            label.element.style.opacity = '0.08';
            label.element.style.pointerEvents = 'none';
        } else {
            label.element.style.opacity = '1';
            if (window.innerWidth > 768) label.element.style.pointerEvents = 'auto';
            else label.element.style.pointerEvents = 'none';
        }
    });
    updateUserTracking();
    controls.update();
    renderer.render(scene, camera);
    labelRenderer.render(scene, camera);
}

function deg2rad(d) { return d * Math.PI / 180; }

function gaussianRandom() {
    let u = 0, v = 0;
    while (u === 0) u = Math.random();
    while (v === 0) v = Math.random();
    return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * v);
}

function distanceToPolyline(x, z, pts) {
    let min = Infinity;
    for (let i = 0; i < pts.length - 1; i++) {
        const a = pts[i], b = pts[i + 1];
        const dx = b.x - a.x, dz = b.z - a.z;
        const len2 = dx * dx + dz * dz;
        if (len2 === 0) { min = Math.min(min, Math.hypot(x - a.x, z - a.z)); continue; }
        let t = ((x - a.x) * dx + (z - a.z) * dz) / len2;
        t = Math.max(0, Math.min(1, t));
        const px = a.x + t * dx, pz = a.z + t * dz;
        min = Math.min(min, Math.hypot(x - px, z - pz));
    }
    return min;
}

// Centralised icon/colour mapping so the map and search share one source of truth.
// Node types: 26 = male toilet, 27 = female toilet, 28 = accessible toilet.
function nodeIconInfo(type) {
    if (type === 17) return { color: 0x8b5cf6, icon: 'elevator', cls: 'icon-elevator' };
    if (type === 10) return { color: 0x06b6d4, icon: 'escalator', cls: 'icon-escalator' };
    if (type === 11) return { color: 0xf59e0b, icon: 'stairs', cls: 'icon-stair' };
    if (type === 26) return { color: 0x60a5fa, icon: 'man', cls: 'icon-toilet-male' };
    if (type === 27) return { color: 0xf472b6, icon: 'woman', cls: 'icon-toilet-female' };
    if (type === 28) return { color: 0x10b981, icon: 'accessible', cls: 'icon-toilet-accessible' };
    if (type === 12) return { color: 0x10b981, icon: 'wc', cls: 'icon-toilet' };
    if (type === 29) return { color: 0xec4899, icon: 'local_convenience_store', cls: 'icon-vending' };
    if (type === 20) return { color: 0x38bdf8, icon: 'water_drop', cls: 'icon-water' };
    if (type === 13) return { color: 0x94a3b8, icon: 'print', cls: 'icon-printers' };
    if (type === 14) return { color: 0x6366f1, icon: 'computer', cls: 'icon-computers' };
    if (type === 16) return { color: 0xf97316, icon: 'microwave', cls: 'icon-microwaves' };
    if (type === 18) return { color: 0xec4899, icon: 'shopping_bag', cls: 'icon-shops' };
    if (type === 19) return { color: 0x10b981, icon: 'account_balance', cls: 'icon-banking' };
    if (type === 25) return { color: 0xeab308, icon: 'bolt', cls: 'icon-charging' };
    return { color: 0x3b82f6, icon: 'meeting_room', cls: 'icon-room' };
}

// ---- User location pointer (ring + direction arrow) ----
function createUserMarker() {
    userMarker = new THREE.Group();

    const disc = new THREE.Mesh(
        new THREE.CircleGeometry(140, 32),
        new THREE.MeshBasicMaterial({ color: 0x22d3ee, transparent: true, opacity: 0.35, depthWrite: false })
    );
    disc.rotation.x = -Math.PI / 2;
    userMarker.add(disc);

    const ring = new THREE.Mesh(
        new THREE.TorusGeometry(140, 22, 12, 40),
        new THREE.MeshBasicMaterial({ color: 0x22d3ee })
    );
    ring.rotation.x = -Math.PI / 2;
    ring.position.y = 2;
    userMarker.add(ring);

    const arrow = new THREE.Mesh(
        new THREE.ConeGeometry(70, 300, 16),
        new THREE.MeshBasicMaterial({ color: 0xff3355 })
    );
    arrow.rotation.x = Math.PI / 2;   // cone tip +Y -> +Z (forward), lies flat on the map
    arrow.position.y = 160;
    userMarker.add(arrow);

    const glow = new THREE.Sprite(new THREE.SpriteMaterial({
        map: makeGlowTexture(0x22d3ee), blending: THREE.AdditiveBlending, transparent: true, opacity: 0.5, depthWrite: false
    }));
    glow.scale.set(700, 700, 1);
    glow.position.y = 60;
    userMarker.add(glow);

    userMarker.visible = false;
    scene.add(userMarker);
}

// Build walkable line segments per floor (used for map matching / wall avoidance).
function buildWalkableSegments() {
    const segs = [];
    edges.forEach(e => {
        const n1 = nodes.find(n => Number(n.id) === Number(e.node1));
        const n2 = nodes.find(n => Number(n.id) === Number(e.node2));
        if (!n1 || !n2) return;
        const f1 = getFloorId(n1), f2 = getFloorId(n2);
        if (f1 !== f2) return;                       // vertical connectors are handled on their own floor
        if (!allowedNodesSet.has(Number(n1.id)) && !allowedNodesSet.has(Number(n2.id))) return;
        const p1 = getNodePosition3D(n1.id), p2 = getNodePosition3D(n2.id);
        if (!p1 || !p2) return;
        segs.push({ floorId: f1, ax: p1.x, az: p1.z, bx: p2.x, bz: p2.z });
    });
    particleFilter.setSegments(segs);
}

// ---- Cross-OS sensor helpers ----
function setupSensors() {
    window.addEventListener('deviceorientation', onOrientation, true);
    window.addEventListener('deviceorientationabsolute', onOrientationAbsolute, true);
    window.addEventListener('devicemotion', onMotion, true);
}

async function enableSensors() {
    await requestSensorPermission();
}

// Ask for sensor access. On iOS this must be triggered by a user gesture,
// so it is also bound to the first tap/click anywhere on the page.
async function requestSensorPermission() {
    if (sensorsEnabled) return;
    let needed = false;
    try {
        if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
            needed = true;
            const res = await DeviceOrientationEvent.requestPermission();
            if (res !== 'granted') { showLocStatus('⚠️ Orientation permission denied'); return; }
        }
        if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission === 'function') {
            needed = true;
            await DeviceMotionEvent.requestPermission();
        }
        sensorsEnabled = true;
        setupSensors();
        if (needed) showLocStatus('📍 Sensors enabled — walk to update your location');
    } catch (err) {
        showLocStatus('⚠️ Sensor error: ' + err.message);
    }
}

// iOS requires a user gesture before permission can be requested, so we hook
// the first tap/click anywhere and ask automatically.
function bindSensorPermissionPrompt() {
    const needsPrompt = (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function')
        || (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission === 'function');
    if (!needsPrompt) return;
    const statusEl = document.getElementById('loc-status');
    if (statusEl) statusEl.textContent = '📍 Tap anywhere to enable sensors';
    const trigger = () => {
        document.removeEventListener('pointerdown', trigger, true);
        document.removeEventListener('touchstart', trigger, true);
        document.removeEventListener('click', trigger, true);
        requestSensorPermission();
    };
    document.addEventListener('pointerdown', trigger, true);
    document.addEventListener('touchstart', trigger, true);
    document.addEventListener('click', trigger, true);
}

// Make the floating location panel draggable by its handle.
function makePanelDraggable(panelId, handleId) {
    const panel = document.getElementById(panelId);
    const handle = document.getElementById(handleId);
    if (!panel || !handle) return;

    const STORAGE_KEY = 'locPanelPos';
    let saved = null;
    try { saved = JSON.parse(localStorage.getItem(STORAGE_KEY)); } catch (e) {}
    if (saved && typeof saved.left === 'number' && typeof saved.top === 'number') {
        const w = panel.offsetWidth || 230;
        const h = panel.offsetHeight || 200;
        const left = Math.max(0, Math.min(window.innerWidth - w, saved.left));
        const top = Math.max(0, Math.min(window.innerHeight - h, saved.top));
        panel.style.right = 'auto';
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    let dragging = false, offsetX = 0, offsetY = 0;

    handle.addEventListener('pointerdown', (e) => {
        if (e.target.closest && e.target.closest('button')) return; // allow buttons inside the handle
        dragging = true;
        const rect = panel.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
        panel.style.right = 'auto';
        panel.style.left = rect.left + 'px';
        panel.style.top = rect.top + 'px';
        panel.style.cursor = 'grabbing';
        if (handle.setPointerCapture) handle.setPointerCapture(e.pointerId);
        e.preventDefault();
    });
    handle.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const left = Math.max(0, Math.min(window.innerWidth - panel.offsetWidth, e.clientX - offsetX));
        const top = Math.max(0, Math.min(window.innerHeight - panel.offsetHeight, e.clientY - offsetY));
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    });
    const endDrag = () => {
        if (!dragging) return;
        dragging = false;
        panel.style.cursor = 'grab';
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ left: panel.offsetLeft, top: panel.offsetTop })); } catch (e) {}
    };
    handle.addEventListener('pointerup', endDrag);
    handle.addEventListener('pointercancel', endDrag);
}

// ---- Device platform detection + persistent session cookie ----
function detectPlatform() {
    const ua = navigator.userAgent || '';
    if (/android/i.test(ua)) devicePlatform = 'android';
    else if (/iphone|ipad|ipod/i.test(ua)) devicePlatform = 'ios';
    else if (/windows|macintosh|cros|linux/i.test(ua)) devicePlatform = 'desktop';
    else devicePlatform = 'other';

    const statusEl = document.getElementById('loc-status');
    if (statusEl) {
        if (devicePlatform === 'ios') statusEl.textContent = '📍 iOS — tap anywhere to enable sensors';
        else if (devicePlatform === 'android') statusEl.textContent = '📍 Android — sensors ready';
        else if (devicePlatform === 'desktop') statusEl.textContent = '📍 Desktop — sensors may be unavailable';
        else statusEl.textContent = '📍 Sensors may be unavailable on this device';
    }
    refreshReadout();
}

function refreshReadout() {
    const readout = document.getElementById('loc-readout');
    if (!readout) return;
    const parts = ['OS: ' + devicePlatform];
    if (sessionId) parts.push('ID ' + sessionId.slice(0, 8));
    if (particleFilter.initialized) {
        parts.push('Heading ' + Math.round(userHeadingDeg) + '°');
        parts.push('Steps ' + pedometerSteps);
    }
    readout.textContent = parts.join(' · ');
}

// Persistent cookie session so the same phone always keeps the same id.
function ensureSessionCookie() {
    const COOKIE = 'nav_sid';
    sessionId = getCookie(COOKIE);
    if (!sessionId) {
        sessionId = generateUUID();
        setCookie(COOKIE, sessionId, 730); // ~2 years
    }
    refreshReadout();
}

function getCookie(name) {
    const m = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
}

function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
}

function generateUUID() {
    if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = Math.random() * 16 | 0;
        return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
}

function applyHeading(heading) {
    compassHeadingDeg = ((heading % 360) + 360) % 360;
    updateCompassReliability();
    // If a QR/URL supplied a "facing" value before any compass reading,
    // calibrate the magnetometer to it on the first reliable reading.
    if (pendingFacing != null && compassReliable) {
        headingOffset = ((pendingFacing - compassHeadingDeg - DEVICE_HEADING_OFFSET) % 360 + 360) % 360;
        pendingFacing = null;
    }
    updateUserHeading();
}

function onOrientation(e) {
    // iOS exposes an absolute compass on the regular event via webkitCompassHeading.
    let heading = null;
    if (typeof e.webkitCompassHeading === 'number' && isFinite(e.webkitCompassHeading) && e.webkitCompassHeading !== null) {
        heading = e.webkitCompassHeading;
        hasAbsoluteHeading = true;
    } else if (e.absolute === true && typeof e.alpha === 'number') {
        // Some browsers deliver absolute alpha on the regular event.
        heading = ANDROID_ALPHA_INVERT ? (360 - e.alpha) : e.alpha;
        hasAbsoluteHeading = true;
    } else if (!hasAbsoluteHeading && typeof e.alpha === 'number') {
        // Last-resort relative fallback (no absolute source available yet).
        heading = e.alpha;
    }
    if (typeof e.webkitCompassAccuracy === 'number') compassAccuracy = e.webkitCompassAccuracy;
    if (typeof e.beta === 'number') deviceTilt = { beta: e.beta, gamma: e.gamma };
    if (heading != null) applyHeading(heading);
}

function onOrientationAbsolute(e) {
    // Android: DeviceOrientationAbsoluteEvent.alpha is the compass heading.
    // ANDROID_ALPHA_INVERT is only needed if the arrow turns opposite the phone.
    if (typeof e.alpha === 'number') {
        hasAbsoluteHeading = true;
        if (typeof e.webkitCompassAccuracy === 'number') compassAccuracy = e.webkitCompassAccuracy;
        if (typeof e.beta === 'number') deviceTilt = { beta: e.beta, gamma: e.gamma };
        applyHeading(ANDROID_ALPHA_INVERT ? (360 - e.alpha) : e.alpha);
    }
}

function onMotion(e) {
    const acc = e.acceleration || e.accelerationIncludingGravity;
    if (!acc) return;
    const ax = acc.x || 0, ay = acc.y || 0, az = acc.z || 0;
    let mag;
    if (e.acceleration && typeof e.acceleration.x === 'number') {
        mag = Math.sqrt(ax * ax + ay * ay + az * az);   // linear acceleration
    } else {
        mag = Math.sqrt(ax * ax + ay * ay + az * az) - 9.8; // gravity removed
    }
    const now = performance.now();
    if (mag > STEP_THRESHOLD && stepArmed && now - lastStepTime > STEP_COOLDOWN_MS) {
        pedometerSteps++;
        lastStepTime = now;
        stepArmed = false;
    } else if (mag < STEP_REARM_THRESHOLD && !stepArmed) {
        stepArmed = true;
    }
}

function updateCompassReliability() {
    if (typeof compassHeadingDeg !== 'number') { compassReliable = false; return; }
    compassReliable = (typeof compassAccuracy === 'number') ? (compassAccuracy <= COMPASS_ACCURACY_THRESHOLD) : true;
}

function updateUserHeading() {
    if (compassReliable && typeof compassHeadingDeg === 'number') {
        userHeadingDeg = ((compassHeadingDeg + DEVICE_HEADING_OFFSET + headingOffset) % 360 + 360) % 360;
    }
}

// Heading (degrees) the user should face to follow the current route: the
// direction from the current step toward the next one. Falls back to 0° when
// there is no active route/path.
function pathFacingHeading() {
    if (isNavigating && navPath && navPath.length > 0) {
        const idx = Math.max(0, Math.min(navIndex, navPath.length - 1));
        const cur = getNodePosition3D(navPath[idx]);
        let nxtPos = null;
        if (idx + 1 < navPath.length) {
            nxtPos = getNodePosition3D(navPath[idx + 1]);
        } else if (idx - 1 >= 0) {
            nxtPos = getNodePosition3D(navPath[idx - 1]);
        }
        if (cur && nxtPos) {
            const dx = nxtPos.x - cur.x;
            const dz = nxtPos.z - cur.z;
            if (dx !== 0 || dz !== 0) {
                return ((Math.atan2(dx, dz) * 180 / Math.PI) % 360 + 360) % 360;
            }
        }
    }
    return 0;
}

// Ask the user before resetting their facing direction (a small block pops
// out from the bottom of the screen).
function confirmResetDirection() {
    openConfirmModal('Reset direction?', 'Do you want to reset your direction to face the path?', () => {
        resetDirection();
    });
}

function resetDirection() {
    const target = pathFacingHeading();
    if (typeof compassHeadingDeg === 'number') {
        headingOffset = ((target - compassHeadingDeg - DEVICE_HEADING_OFFSET) % 360 + 360) % 360;
    }
    userHeadingDeg = target;
    updateUserHeading();
    if (particleFilter.initialized) {
        particleFilter.particles.forEach(p => p.heading = deg2rad(userHeadingDeg));
    }
    showLocStatus(`🧭 Direction reset to ${Math.round(target)}°`);
}

// =====================================================================
//  QR code content generation
// =====================================================================
function buildQRFullURL(nodeId) {
    const url = new URL(window.location.href);
    // Clean entry point: strip any filename (index.php) plus the query/hash
    // so the QR encodes the canonical directory URL.
    const dir = url.pathname.replace(/\/[^/]*\.php$/, '/');
    const out = new URL(url.origin + dir);
    if (nodeId != null && nodeId !== '') out.searchParams.set('start', nodeId);
    return out.toString();
}

// Website QR: reuse the current URL and only add/update ?start=…, keeping
// every other existing query parameter untouched.
function buildQRSiteURL(nodeId) {
    const url = new URL(window.location.href);
    if (nodeId != null && nodeId !== '') url.searchParams.set('start', nodeId);
    else url.searchParams.delete('start');
    return url.toString();
}

// ---- QR anchor (source location + facing direction) ----
async function startQRScan() {
    const modal = document.getElementById('qr-modal');

    if (!window.Html5Qrcode) {
        showLocStatus('⚠️ QR library not loaded (check internet/CDN)');
        return;
    }

    // Camera access on a phone requires a secure context (https or localhost).
    if (window.isSecureContext === false) {
        showLocStatus('⚠️ Camera needs HTTPS or localhost');
        alert('Camera access is blocked over plain http on a phone.\nOpen this site via https:// or http://localhost instead.');
        return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showLocStatus('⚠️ Camera API not available on this device');
        return;
    }

    modal.classList.add('active');

    // Trigger the browser camera-permission prompt inside this user gesture.
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        stream.getTracks().forEach(t => t.stop());
    } catch (err) {
        if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError')) {
            showLocStatus('⚠️ Camera permission denied — enable it in browser settings');
        } else if (err && err.name === 'NotFoundError') {
            showLocStatus('⚠️ No camera found on this device');
        } else {
            showLocStatus('⚠️ Camera error: ' + (err && err.message ? err.message : err));
        }
        modal.classList.remove('active');
        return;
    }

    try {
        if (!qrScanner) qrScanner = new Html5Qrcode('qr-reader');
        await qrScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            onQRSuccess,
            () => {}   // decode misses happen between frames; ignore
        );
    } catch (err) {
        showLocStatus('⚠️ QR camera error: ' + err);
        stopQRScan();
    }
}

function stopQRScan() {
    if (qrScanner) {
        try {
            qrScanner.stop()
                .then(() => { try { qrScanner.clear(); } catch (e) {} })
                .catch(() => {});
        } catch (e) {}
    }
    const modal = document.getElementById('qr-modal');
    if (modal) modal.classList.remove('active');
}

function onQRSuccess(decodedText) {
    const text = String(decodedText).trim();

    // Scanning a location QR inside the website never navigates away. It just
    // adds/edits the ?start= node in the current URL while every other
    // parameter (e.g. ?dest=301) stays untouched, so a destination that is
    // already in the URL is preserved.
    const anchor = parseQRPayload(text);
    if (!anchor) { showLocStatus('⚠️ Unknown QR content'); return; }
    applyAnchor(anchor.node, anchor.heading);
    stopQRScan();
}

// Accepts JSON {"node":12,"heading":90} or {"start":12}, "node=12&heading=90",
// "start=294", a bare node id "294", "NAV:12:90", "12:90", "12,90" or "12 90".
// Heading (facing) is optional: a QR that only carries a start node still
// anchors the user to that node.
function parseQRPayload(text) {
    if (!text) return null;
    const t = String(text).trim();
    if (!t) return null;

    const headingOf = (h) => (h != null && h !== '' ? Number(h) : null);

    // 1) JSON object
    if (t[0] === '{' || t[0] === '[') {
        try {
            const o = JSON.parse(t);
            const node = o.node ?? o.source ?? o.id ?? o.n ?? o.start;
            const heading = o.heading ?? o.dir ?? o.direction ?? o.h ?? o.facing;
            if (node != null) return { node: Number(node), heading: headingOf(heading) };
        } catch (e) {}
    }

    // 2) URL with query string, e.g. https://.../?start=282&facing=270
    if (/^https?:\/\//i.test(t) || t.includes('?')) {
        try {
            const u = new URL(t, window.location.href);
            const node = u.searchParams.get('start') ?? u.searchParams.get('node') ?? u.searchParams.get('source') ?? u.searchParams.get('id');
            const heading = u.searchParams.get('facing') ?? u.searchParams.get('heading') ?? u.searchParams.get('dir') ?? u.searchParams.get('direction') ?? u.searchParams.get('h');
            if (node != null) return { node: Number(node), heading: headingOf(heading) };
        } catch (e) {}
    }

    // 3) key=value pairs (node=12&heading=90) or start=294
    const kv = {};
    let hasKV = false;
    for (const part of t.split(/[&;]+/)) {
        const eq = part.indexOf('=');
        if (eq > 0) { kv[part.slice(0, eq).trim().toLowerCase()] = part.slice(eq + 1).trim(); hasKV = true; }
    }
    if (hasKV) {
        const node = kv.node ?? kv.source ?? kv.id ?? kv.n ?? kv.start;
        const heading = kv.heading ?? kv.dir ?? kv.direction ?? kv.h ?? kv.facing;
        if (node != null) return { node: Number(node), heading: headingOf(heading) };
    }

    // 4) "NAV:node:heading", "node:heading", "node,heading", "node heading"
    //    or a bare node id "294"
    const stripped = t.replace(/^NAV\s*[:;\s]*/i, '');
    const m = stripped.match(/^(\d+)\s*[:;,]\s*(\d+)\s*$/);
    if (m) return { node: Number(m[1]), heading: Number(m[2]) };

    const m2 = stripped.match(/^(\d+)\s+(\d+)\s*$/);
    if (m2) return { node: Number(m2[1]), heading: Number(m2[2]) };

    const m3 = stripped.match(/^(\d+)\s*$/);
    if (m3) return { node: Number(m3[1]), heading: null };

    return null;
}

function applyAnchor(nodeId, headingDeg) {
    const node = nodes.find(n => Number(n.id) === Number(nodeId));
    if (!node) { showLocStatus('⚠️ QR node not found: ' + nodeId); return; }
    const fid = getFloorId(node);
    const pos = getNodePosition3D(node.id);
    if (!pos) { showLocStatus('⚠️ Node has no position'); return; }

    // The scanned node becomes the route source.
    document.getElementById('start-input').value = node.name;
    document.getElementById('start-id').value = node.id;

    userFloorId = fid;

    // "facing" is optional. When present it marks the initial direction: if the
    // magnetometer is reliable, calibrate it against "facing" (a guideline),
    // otherwise fall back to "facing" as a fixed heading. A start-only QR
    // leaves the current heading untouched.
    const hasHeading = typeof headingDeg === 'number' && !Number.isNaN(headingDeg);
    if (hasHeading) {
        updateCompassReliability();
        if (compassReliable && typeof compassHeadingDeg === 'number') {
            headingOffset = ((headingDeg - compassHeadingDeg - DEVICE_HEADING_OFFSET) % 360 + 360) % 360;
        }
        userHeadingDeg = ((headingDeg % 360) + 360) % 360;
        updateUserHeading();
    }

    particleFilter.init(pos.x, pos.z, deg2rad(userHeadingDeg), fid, 200);
    if (userMarker) userMarker.visible = true;
    smoothedPos = { x: pos.x, z: pos.z, floorId: fid };
    filterFloor(fid);

    // Update only the ?start= param. Every other query parameter (dest,
    // resource, route_type, …) is left exactly as it is, so a destination
    // already in the URL is preserved.
    const url = new URL(window.location.href);
    url.searchParams.set('start', node.id);
    window.history.replaceState({}, '', url);

    const facingNote = hasHeading ? ` (facing ${Math.round(userHeadingDeg)}°)` : '';
    showLocStatus(`📍 Anchored at "${node.name}"${facingNote}`);
}

function showLocStatus(msg) {
    const el = document.getElementById('loc-status');
    if (el) el.textContent = msg;
}

// Snap the user pointer to a node (used by the route guide).
function setUserAtNode(nodeId, headingDeg) {
    const node = nodes.find(n => Number(n.id) === Number(nodeId));
    if (!node) return null;
    const fid = getFloorId(node);
    const pos = getNodePosition3D(nodeId);
    if (!pos) return null;
    userFloorId = fid;
    userHeadingDeg = ((headingDeg % 360) + 360) % 360;
    particleFilter.init(pos.x, pos.z, deg2rad(userHeadingDeg), fid, 200);
    if (userMarker) userMarker.visible = true;
    smoothedPos = { x: pos.x, z: pos.z, floorId: fid };
    return { node, pos, fid };
}

// Move the pointer to the step at `index`, facing the following step.
function moveUserToStep(index) {
    if (!navPath || index < 0 || index >= navPath.length) return;
    const pos = getNodePosition3D(navPath[index]);
    if (!pos) return;
    let heading = userHeadingDeg;
    if (index + 1 < navPath.length) {
        const next = getNodePosition3D(navPath[index + 1]);
        if (next) heading = Math.atan2(next.x - pos.x, next.z - pos.z) * 180 / Math.PI;
    }
    setUserAtNode(navPath[index], heading);
}

function shouldVibrate() {
    const m = document.cookie.match(/(?:^|;\s*)vibrate=([^;]*)/);
    if (!m) return false;
    const v = decodeURIComponent(m[1]);
    return v === '1' || v === 'true';
}

function vibrateUser(pattern) {
    if (!shouldVibrate()) return;
    if (navigator.vibrate) navigator.vibrate(pattern);
}

function turnAngleAt(index) {
    if (index <= 0 || index + 1 >= navPath.length) return 0;
    const p0 = getNodePosition3D(navPath[index - 1]);
    const p1 = getNodePosition3D(navPath[index]);
    const p2 = getNodePosition3D(navPath[index + 1]);
    if (!p0 || !p1 || !p2) return 0;
    const a1 = Math.atan2(p1.x - p0.x, p1.z - p0.z);
    const a2 = Math.atan2(p2.x - p1.x, p2.z - p1.z);
    let diff = Math.abs(a2 - a1) * 180 / Math.PI;
    if (diff > 180) diff = 360 - diff;
    return diff;
}

function resetUserTracking() {
    particleFilter.initialized = false;
    particleFilter.particles = [];
    pedometerSteps = 0;
    lastConsumedSteps = 0;
    lastStepTime = 0;
    stepArmed = true;
    smoothedPos = null;
    userHeadingDeg = 0;
    headingOffset = 0;
    compassHeadingDeg = null;
    compassAccuracy = null;
    compassReliable = false;
    pendingFacing = null;
    userFloorId = null;
    routePathPoints3D = [];
    particleFilter.setRoutePoints([]);
    if (userMarker) userMarker.visible = false;
}

function updateUserTracking() {
    if (!particleFilter.initialized || !userMarker) return;

    // 1) Consume pedometer steps -> distance -> predict.
    //    Map units are 1:1 with the graph weights (weight 1 = 1 m):
    //    1 m = baseScale units (or baseScale*2 on floor 21).
    const steps = pedometerSteps - lastConsumedSteps;
    lastConsumedSteps = pedometerSteps;
    const unitsPerMeter = (userFloorId === 21) ? baseScale * 2 : baseScale;
    const distance = steps * STEP_LENGTH_METERS * unitsPerMeter;
    const moving = distance > 0;
    if (moving) particleFilter.predict(distance);

    // 2) Map match + resample only while moving (plus a slow idle refresh).
    const now = performance.now();
    if (moving || now - particleFilter.lastResample > 1500) {
        particleFilter.mapMatch();
        particleFilter.resample();
        particleFilter.lastResample = now;
    }

    // 3) Estimate + smooth + minimum-movement threshold.
    const est = particleFilter.estimate();
    if (est) {
        if (!smoothedPos || smoothedPos.floorId !== userFloorId) {
            smoothedPos = { x: est.x, z: est.z, floorId: userFloorId };
        } else {
            const dx = est.x - smoothedPos.x;
            const dz = est.z - smoothedPos.z;
            if (Math.hypot(dx, dz) > MOVEMENT_THRESHOLD_METERS * unitsPerMeter) {
                const alpha = 0.25; // smoothing factor
                smoothedPos.x += dx * alpha;
                smoothedPos.z += dz * alpha;
            }
        }
        const fy = (floorY[userFloorId] != null) ? floorY[userFloorId] : 0;
        userMarker.position.set(smoothedPos.x, fy + 140, smoothedPos.z);
        userMarker.rotation.y = deg2rad(userHeadingDeg);
        userMarker.visible = (currentFilteredFloor === 'all' || Number(currentFilteredFloor) === Number(userFloorId));
    }

    // 4) HUD readout.
    refreshReadout();
}