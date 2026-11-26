<?php
/**
 * Smart Map Nav — global configuration and bootstrap.
 *
 * Every public page and every API endpoint starts by requiring this file.
 * It is deliberately boring: constants, a base-path detector, accessibility
 * defaults, a session bootstrap and a handful of escaping helpers.
 *
 * Nothing here talks to a database itself. Campus content is loaded via
 * LiveResources when a page or API needs it. Routing remains mocked in
 * src/MockData.php. Also requires Icons.php, db.php and LiveResources.php
 * so live MapNav resources are available on every page.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

/** Guard: includes/*.php must never be executed directly over HTTP. */
define('APP_RUNNING', true);

const APP_NAME        = 'Smart Map Nav';
const APP_VERSION     = '0.9.0';
const APP_TAGLINE     = 'Accessible navigation and wayfinding for everyone';
const APP_COURSE_NOTE = 'Coursework prototype — campus coordinates are illustrative.';

/* Emergency / support contacts (footer + help centre). */
const EMERGENCY_LABEL = 'Campus Security (24 hours)';
const EMERGENCY_PHONE = '+852 3442 8888';
const EMERGENCY_TEL   = '+85234428888';
const HELP_EMAIL      = 'help@smartmapnav.example';

/**
 * Work out the URL path the application is served from.
 *
 * The prototype must run in two very different places:
 *   1. `php -S localhost:8000 -t public`  -> base path is '' (server root)
 *   2. XAMPP/Apache document root         -> '/CityUMapNav/public'
 *
 * We derive it from SCRIPT_NAME so no configuration is needed. Files inside
 * public/api/ report their own directory, so we step up one level for them.
 */
function app_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');

    if ($dir === '.' || $dir === '') {
        return '';
    }

    if (str_ends_with($dir, '/api')) {
        $dir = rtrim(substr($dir, 0, -4), '/');
    }

    return ($dir === '/' || $dir === '.') ? '' : $dir;
}

define('BASE_PATH', app_base_path());
define('PROJECT_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', PROJECT_ROOT . '/public');
define('ASSET_URL', BASE_PATH . '/assets');
define('API_URL', BASE_PATH . '/api');

/**
 * Default accessibility preferences.
 *
 * These are the values used before the user touches anything. They are
 * mirrored in public/assets/js/accessibility.js (localStorage) and in the PHP
 * session (see api/prefs.php) so a page load never flashes the wrong theme.
 */
const A11Y_DEFAULTS = [
    'high_contrast' => false,
    'large_text'    => false,
    'text_scale'    => 100,          // 100 | 125 | 150 (Normal | Large | Extra Large)
    'vibration'     => false,        // device vibration on route/status changes
    'sr_hints'      => true,
    'icon_size'     => 'large',      // large | xlarge | max
    'default_route' => 'step_free',  // fastest | smoothest | step_free
    'language'      => 'en',         // en | zh-HK
];

/**
 * Who is using the map?
 *
 * Chosen in the first-visit preferences panel and in Settings, then stored in
 * the `usertype` cookie so the choice survives the session.
 */
const USER_TYPE_OPTIONS = [
    'student' => ['label' => 'Student',  'help' => 'Current CityU student.'],
    'julac'   => ['label' => 'JULAC',    'help' => 'Staff or student from a JULAC partner library.'],
    'guest'   => ['label' => 'Guest',    'help' => 'Visitor, contractor or member of the public.'],
];

/** How long the profile cookies (usertype / vibration) live. */
const USER_COOKIE_DAYS = 180;

/**
 * The stand-alone 3D map ("CityU Nav") lives at Nav/MapNav3D inside this
 * project. It is a separate application and is never edited from here - we
 * only link to it. Override the URL with the MAP3D_URL environment variable if
 * the folder is renamed or deployed elsewhere.
 */
define(
    'MAP3D_URL',
    getenv('MAP3D_URL') ?: (BASE_PATH === '' ? '' : dirname(BASE_PATH)) . '/Nav/MapNav3D/index.php'
);

const TEXT_SCALE_OPTIONS = [100, 125, 150];

/** Friendly names for the text-size choices. */
const TEXT_SCALE_LABELS = [
    100 => 'Normal',
    125 => 'Large',
    150 => 'Extra Large',
];

const ICON_SIZE_OPTIONS = [
    'large'  => ['label' => 'Normal',      'px' => 64],
    'xlarge' => ['label' => 'Large',       'px' => 80],
    'max'    => ['label' => 'Extra Large', 'px' => 96],
];

const ROUTE_TYPE_OPTIONS = [
    'fastest'   => [
        'label'       => 'Fastest',
        'description' => 'Shortest walking time. May include stairs and escalators.',
    ],
    'smoothest' => [
        'label'       => 'Smoothest — no stairs',
        'description' => 'No stairs. Escalators and slopes are allowed.',
    ],
    'step_free' => [
        'label'       => 'Step-free — lifts only',
        'description' => 'Lifts and ramps only. Never a stair or an escalator.',
    ],
];

const LANGUAGE_OPTIONS = [
    'en'    => 'English',
    'zh-HK' => '繁體中文',
];

/* ---------------------------------------------------------------------
 * Session bootstrap
 * ------------------------------------------------------------------ */

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------------------------------------------------------------------
 * Preference helpers
 * ------------------------------------------------------------------ */

/**
 * Current accessibility preferences: session values merged over the defaults,
 * then sanitised so a tampered session can never inject arbitrary CSS classes.
 *
 * The result is memoised because it is read by header, footer and every page
 * component. Pass $refresh = true after writing to $_SESSION['prefs'] (e.g. at
 * the end of a settings POST) or the render will use the values from before
 * the write.
 *
 * @return array<string, mixed>
 */
function a11y_prefs(bool $refresh = false): array
{
    static $prefs = null;

    if ($prefs !== null && !$refresh) {
        return $prefs;
    }

    $prefs = A11Y_DEFAULTS;

    if (isset($_SESSION['prefs']) && is_array($_SESSION['prefs'])) {
        $prefs = array_merge($prefs, $_SESSION['prefs']);
    }

    /* The vibration toggle is mirrored into a cookie. A visitor who chose it
       before a session existed keeps the choice on the next page load. */
    if (!isset($_SESSION['prefs']['vibration']) && isset($_COOKIE['vibration'])) {
        $prefs['vibration'] = (string) $_COOKIE['vibration'] === '1';
    }

    foreach (['high_contrast', 'large_text', 'vibration', 'sr_hints'] as $flag) {
        $prefs[$flag] = (bool) $prefs[$flag];
    }

    $prefs['text_scale']    = in_array((int) $prefs['text_scale'], TEXT_SCALE_OPTIONS, true)
        ? (int) $prefs['text_scale'] : 100;
    $prefs['icon_size']     = array_key_exists((string) $prefs['icon_size'], ICON_SIZE_OPTIONS)
        ? (string) $prefs['icon_size'] : 'large';
    $prefs['default_route'] = array_key_exists((string) $prefs['default_route'], ROUTE_TYPE_OPTIONS)
        ? (string) $prefs['default_route'] : 'step_free';
    $prefs['language']      = array_key_exists((string) $prefs['language'], LANGUAGE_OPTIONS)
        ? (string) $prefs['language'] : 'en';

    /* The chosen percentage is the single source of truth for text size. The
       old "Large text" switch is only a shortcut that selects 125 %, so the
       flag is derived here instead of being stored separately. */
    $prefs['large_text'] = $prefs['text_scale'] >= 125;

    return $prefs;
}

/** Read a single preference. */
function pref(string $key, mixed $fallback = null): mixed
{
    $prefs = a11y_prefs();

    return $prefs[$key] ?? $fallback;
}

/**
 * Body classes that switch the visual modes on. Rendered server-side from the
 * session so there is no flash of light mode for a high-contrast user; the
 * client mirrors localStorage values into the same class names.
 */
function body_classes(): string
{
    $prefs   = a11y_prefs();
    $classes = [];

    if ($prefs['high_contrast']) { $classes[] = 'high-contrast'; }
    if ($prefs['large_text'])    { $classes[] = 'large-text'; }
    if ($prefs['vibration'])     { $classes[] = 'vibration-on'; }
    if ($prefs['sr_hints'])      { $classes[] = 'sr-hints'; }

    $classes[] = 'text-scale-' . (int) $prefs['text_scale'];
    $classes[] = 'icon-size-' . (string) $prefs['icon_size'];

    return implode(' ', $classes);
}

/* ---------------------------------------------------------------------
 * Escaping / CSRF helpers
 * ------------------------------------------------------------------ */

/** Escape for HTML text and attribute contexts. Every echo of data goes through this. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Per-session CSRF token for the contact form and the prefs endpoint. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

/** Constant-time CSRF validation. */
function csrf_valid(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], $token);
}

/* ---------------------------------------------------------------------
 * Visitor profile cookies (usertype / vibration)
 *
 * These are deliberately separate from the accessibility preferences: they
 * answer "who are you?" and "should this device vibrate?", which the
 * first-visit panel and the Settings page both collect.
 * ------------------------------------------------------------------ */

/**
 * Read the profile cookies. Every value is validated, so a hand-edited cookie
 * can never inject arbitrary text or markup.
 *
 * @return array{usertype:string, vibration:bool}
 */
function user_prefs(): array
{
    $type = isset($_COOKIE['usertype']) && is_string($_COOKIE['usertype'])
        ? strtolower(trim($_COOKIE['usertype']))
        : '';

    return [
        'usertype'  => array_key_exists($type, USER_TYPE_OPTIONS) ? $type : '',
        'vibration' => (($_COOKIE['vibration'] ?? '') === '1'),
    ];
}

/** Write one long-lived profile cookie and mirror it into $_COOKIE. */
function set_user_cookie(string $name, string $value): void
{
    if (headers_sent()) {
        return;
    }

    setcookie($name, $value, [
        'expires'  => time() + (USER_COOKIE_DAYS * 86400),
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,   // accessibility.js reads and writes these instantly
        'samesite' => 'Lax',
    ]);

    /* Keep the current request in sync with what the browser will send next. */
    $_COOKIE[$name] = $value;
}

/**
 * Store the visitor profile chosen in the first-visit panel or in Settings.
 *
 * @param string $userType student | julac | guest (anything else becomes guest)
 */
function save_user_prefs(string $userType): void
{
    $type = array_key_exists($userType, USER_TYPE_OPTIONS) ? $userType : 'guest';

    set_user_cookie('usertype', $type);
}

/* ---------------------------------------------------------------------
 * 3D map hand-off
 * ------------------------------------------------------------------ */

/**
 * Translate our route types to the 3D map's values.
 * fastest -> time, smoothest -> smooth, step_free -> step_free.
 */
function map3d_route_type(): string
{
    $type = (string) pref('default_route', 'step_free');

    $map = [
        'fastest'   => 'time',
        'smoothest' => 'smooth',
        'step_free' => 'step_free',
    ];

    return $map[$type] ?? 'step_free';
}

/**
 * URL for the 3D map.
 *
 * The saved settings are deliberately NOT baked into the query string any
 * more. They used to be appended here, which froze them at page-render time:
 * a link rendered before a Settings change still carried the old
 * `route_type` / `usertype` / `text_scale`, and the 3D app treats those
 * parameters as explicit overrides - so the first click after a change showed
 * the old values and only a second visit (with a re-rendered link) picked up
 * the new ones. The 3D app now resolves them live (localStorage -> cookie ->
 * session), and only a caller that passes `$query` explicitly overrides that.
 *
 * @param array<string, mixed> $query Explicit overrides (e.g. a deep link)
 */
function map3d_url(array $query = []): string
{
    return $query === [] ? MAP3D_URL : MAP3D_URL . '?' . http_build_query($query);
}

/* ---------------------------------------------------------------------
 * Shared classes
 * ------------------------------------------------------------------ */

require_once PROJECT_ROOT . '/src/Router.php';
require_once PROJECT_ROOT . '/src/MockData.php';
require_once PROJECT_ROOT . '/src/Icons.php';
require_once PROJECT_ROOT . '/includes/db.php';
require_once PROJECT_ROOT . '/src/LiveResources.php';
