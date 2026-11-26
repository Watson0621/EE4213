<?php
/**
 * Live resources API — GET /api/resources.php
 *
 * Author: David (with Grok Premium)
 *
 * What was done:
 *   - Replaced MockData::resources() with LiveResources::load() (live MapNav DB).
 *   - Filters: icon, tag, group (accessibility|transport|general), step_free, floor_id.
 *   - Step-free is a flag on lifts only, not a group. Toilets are male, female, or accessible.
 *   - Keeps the {ok, count, resources} envelope; source is "mysql".
 *
 * Query parameters (all optional)
 *   icon       icon id (lifts, toilets, water, …)
 *   tag        primary tag id
 *   group      accessibility | general
 *   step_free  1 to return only step-free resources
 *   floor_id   floor node id
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, OPTIONS');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use GET.']);
    exit;
}

$loaded = LiveResources::load();
if (!$loaded['ok']) {
    http_response_code(503);
    echo json_encode([
        'ok'    => false,
        'error' => 'Database unavailable: ' . $loaded['error'],
        'source'=> 'mysql',
    ]);
    exit;
}

$validIcons = array_column(Icons::icons(), 'id');
$validTags  = array_keys(Icons::TAGS);
$validGroups = array_keys(Icons::GROUPS);

$icon = isset($_GET['icon']) && is_string($_GET['icon'])
    ? (string) preg_replace('/[^a-z_]/', '', strtolower($_GET['icon']))
    : '';
$tag = isset($_GET['tag']) && is_string($_GET['tag'])
    ? (string) preg_replace('/[^a-z_]/', '', strtolower($_GET['tag']))
    : '';
$group = isset($_GET['group']) && is_string($_GET['group'])
    ? (string) preg_replace('/[^a-z]/', '', strtolower($_GET['group']))
    : '';
$stepFreeOnly = isset($_GET['step_free'])
    && in_array((string) $_GET['step_free'], ['1', 'true', 'on'], true);
$floorId = isset($_GET['floor_id']) && is_numeric($_GET['floor_id'])
    ? (int) $_GET['floor_id']
    : 0;

if ($icon !== '' && !in_array($icon, $validIcons, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown icon id.', 'allowed' => $validIcons]);
    exit;
}
if ($tag !== '' && !in_array($tag, $validTags, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown tag.', 'allowed' => $validTags]);
    exit;
}
if ($group !== '' && !in_array($group, $validGroups, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown group.', 'allowed' => $validGroups]);
    exit;
}

$resources = array_values(array_filter(
    $loaded['resources'],
    static function (array $resource) use ($icon, $tag, $group, $stepFreeOnly, $floorId): bool {
        if ($stepFreeOnly && empty($resource['step_free'])) {
            return false;
        }
        if ($icon !== '' && $resource['icon'] !== $icon) {
            return false;
        }
        if ($tag !== '' && $resource['primary_tag'] !== $tag) {
            return false;
        }
        if ($group !== '' && !in_array($group, $resource['groups'] ?? [$resource['group']], true)) {
            return false;
        }
        if ($floorId > 0 && (int) ($resource['floor_id'] ?? 0) !== $floorId) {
            return false;
        }
        return true;
    }
));

echo json_encode([
    'ok'        => true,
    'count'     => count($resources),
    'resources' => $resources,
    'floors'    => $loaded['floors'],
    'source'    => 'mysql',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
