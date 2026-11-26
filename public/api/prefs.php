<?php
/**
 * Accessibility preferences endpoint — /api/prefs.php
 *
 * GET  returns the current session preferences:
 *        { ok, prefs: {...}, defaults: {...} }
 * POST accepts a JSON body and merges whitelisted keys into the PHP session.
 *      Requires the X-CSRF-Token header to match the session token rendered
 *      into <meta name="csrf-token"> — without it a third-party page could
 *      flip a visitor's contrast mode.
 *
 * Why this exists: the brief asks for preferences in "localStorage + PHP
 * session". localStorage alone cannot influence the server-rendered body
 * classes, and rendering those server-side is what prevents the flash of a
 * white page for a high-contrast user.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    echo json_encode([
        'ok'       => true,
        'prefs'    => a11y_prefs(),
        'defaults' => A11Y_DEFAULTS,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use GET or POST.']);
    exit;
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!csrf_valid(is_string($token) ? $token : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid or missing CSRF token.']);
    exit;
}

$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Body must be a JSON object.']);
    exit;
}

/** @var array<string, mixed> $updated */
$updated = a11y_prefs();

foreach (['high_contrast', 'vibration', 'sr_hints'] as $flag) {
    if (array_key_exists($flag, $data)) {
        $updated[$flag] = filter_var($data[$flag], FILTER_VALIDATE_BOOL);
    }
}

if (array_key_exists('text_scale', $data)) {
    $scale = (int) $data['text_scale'];
    $updated['text_scale'] = in_array($scale, TEXT_SCALE_OPTIONS, true) ? $scale : 100;
}

/* `large_text` is a derived shortcut: ON selects at least 125 %, OFF selects
   100 %. The percentage stays the single source of truth. */
if (array_key_exists('large_text', $data) && !array_key_exists('text_scale', $data)) {
    $large = filter_var($data['large_text'], FILTER_VALIDATE_BOOL);
    $updated['text_scale'] = $large
        ? max(125, (int) $updated['text_scale'])
        : 100;
}

$updated['large_text'] = (int) $updated['text_scale'] >= 125;

if (array_key_exists('icon_size', $data)) {
    $size = (string) $data['icon_size'];
    $updated['icon_size'] = array_key_exists($size, ICON_SIZE_OPTIONS) ? $size : 'large';
}

if (array_key_exists('default_route', $data)) {
    $type = (string) $data['default_route'];
    $updated['default_route'] = array_key_exists($type, ROUTE_TYPE_OPTIONS) ? $type : 'step_free';
}

if (array_key_exists('language', $data)) {
    $lang = (string) $data['language'];
    $updated['language'] = array_key_exists($lang, LANGUAGE_OPTIONS) ? $lang : 'en';
}

$_SESSION['prefs'] = $updated;

/* The vibration toggle has its own cookie as well as the session entry. */
if (array_key_exists('vibration', $data)) {
    set_user_cookie('vibration', $updated['vibration'] ? '1' : '0');
    $updated['vibration'] = (bool) $updated['vibration'];
}

/* Visitor profile: type of user. */
if (array_key_exists('usertype', $data)) {
    $type = strtolower(trim((string) $data['usertype']));

    if (array_key_exists($type, USER_TYPE_OPTIONS)) {
        save_user_prefs($type);
    }
}

echo json_encode([
    'ok'      => true,
    'prefs'   => $updated,
    'profile' => user_prefs(),
    'saved'   => true,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
