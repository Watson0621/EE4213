<?php
/**
 * Live resource loader.
 *
 * Author: David (with Grok Premium)
 *
 * What was done:
 *   - Loads nodes from the live MapNav database (same connection as MapNav3D).
 *   - Skips ignored nodetypes (including generic Toilet 12, Corner, Area, Kiosk, Food Counter).
 *   - Maps each remaining node to the resource shape (icon, group, primary tag, step_free).
 *   - Resolves floor via parent chain: 17 = 3/F, 21 = 4/F, 24 = 5/F.
 *   - Tags every named parent and subparent (Library, AC1, HA Area, …).
 *   - Sort: floor (3/F, 4/F, 5/F), then fewer place-parents, then node id.
 *   - Returns local posx/posy (not lat/lng).
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

final class LiveResources
{
    /** Floor node id → label used by the Floor filter. */
    public const FLOOR_LABELS = [
        17 => '3/F',
        21 => '4/F',
        24 => '5/F',
    ];
    /**
     * @return array{ok: bool, error: ?string, resources: array<int, array<string, mixed>>, floors: array<int, array<string, mixed>>}
     */
    public static function load(): array
    {
        try {
            $pdo = db();

            $stmt = $pdo->query(
                'SELECT id, name, detail, type, parant, layer, posx, posy
                 FROM node
                 ORDER BY type, name'
            );
            $rows = $stmt->fetchAll();

            $floorStmt = $pdo->query(
                'SELECT id, name FROM node WHERE type = 2 ORDER BY id'
            );
            $floors = $floorStmt->fetchAll();

            $byId = [];
            foreach ($rows as $row) {
                $byId[(int) $row['id']] = $row;
            }

            $resources = [];
            foreach ($rows as $row) {
                $type = (int) $row['type'];
                if (in_array($type, Icons::IGNORED_NODETYPES, true)) {
                    continue;
                }
                $icon = Icons::iconForNodeType($type);
                if ($icon === null) {
                    continue;
                }

                $floorId = self::resolveFloorId($row, $byId);
                $places  = self::placeTags($row, $byId);
                $tag     = Icons::primaryTagForIcon($icon);
                $groups  = Icons::groupsForIcon($icon);

                $resources[] = [
                    'id'                 => (string) $row['id'],
                    // Node id used as the ?start=<id> query param when this
                    // resource is the starting point of a route / QR anchor.
                    'start'              => (int) $row['id'],
                    'type'               => $icon,
                    'name'               => (string) $row['name'],
                    'description'        => (string) ($row['detail'] !== '' ? $row['detail'] : $row['name']),
                    'lat'                => null,
                    'lng'                => null,
                    'posx'               => (float) $row['posx'],
                    'posy'               => (float) $row['posy'],
                    'layer'              => (int) $row['layer'],
                    'floor_id'           => $floorId,
                    'floor'              => $places['floor'],
                    'place_tags'         => $places['places'],
                    'step_free'          => Icons::isStepFreeType($type),
                    'icon'               => $icon,
                    'accessibility_tags' => [$tag],
                    'group'              => $groups[0],
                    'groups'             => $groups,
                    'primary_tag'        => $tag,
                    'node_type'          => $type,
                ];
            }

            usort($resources, static function (array $a, array $b): int {
                $floorRank = ['3/F' => 0, '4/F' => 1, '5/F' => 2];
                $fa = $floorRank[$a['floor'] ?? ''] ?? 9;
                $fb = $floorRank[$b['floor'] ?? ''] ?? 9;
                if ($fa !== $fb) {
                    return $fa <=> $fb;
                }
                $pa = count($a['place_tags'] ?? []);
                $pb = count($b['place_tags'] ?? []);
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
                return (int) $a['id'] <=> (int) $b['id'];
            });

            return [
                'ok'        => true,
                'error'     => null,
                'resources' => $resources,
                'floors'    => $floors,
            ];
        } catch (Throwable $e) {
            return [
                'ok'        => false,
                'error'     => $e->getMessage(),
                'resources' => [],
                'floors'    => [],
            ];
        }
    }

    /**
     * Walk parent chain to find the floor node (type = 2), same idea as teammate.
     *
     * @param array<string, mixed> $node
     * @param array<int, array<string, mixed>> $byId
     */
    private static function resolveFloorId(array $node, array $byId): ?int
    {
        if ((int) $node['type'] === 2) {
            return (int) $node['id'];
        }

        $parentId = (int) $node['parant'];
        $guard    = 0;
        while ($parentId > 0 && isset($byId[$parentId]) && $guard < 8) {
            $parent = $byId[$parentId];
            if ((int) $parent['type'] === 2) {
                return (int) $parent['id'];
            }
            $parentId = (int) $parent['parant'];
            $guard++;
        }

        return null;
    }

    /**
     * Floor label plus every named parent / subparent above this node.
     * Building (type 1) is skipped. Floor nodes become 3/F, 4/F, or 5/F.
     * A child of Library (18) is tagged Library and 3/F. A child of HA Area (47)
     * is tagged HA Area, Library, and 3/F.
     *
     * @param array<string, mixed> $node
     * @param array<int, array<string, mixed>> $byId
     * @return array{floor: string, places: array<int, string>}
     */
    private static function placeTags(array $node, array $byId): array
    {
        $floor  = '';
        $places = [];
        $id     = (int) $node['parant'];
        $guard  = 0;
        $seen   = [];

        while ($id > 0 && isset($byId[$id]) && !isset($seen[$id]) && $guard < 8) {
            $seen[$id] = true;
            $parent    = $byId[$id];
            $type      = (int) $parent['type'];

            if ($type === 2 || isset(self::FLOOR_LABELS[$id])) {
                $floor = self::FLOOR_LABELS[$id] ?? $floor;
            } elseif ($type !== 1) {
                $name = trim((string) $parent['name']);
                if ($name !== '' && !in_array($name, $places, true)) {
                    $places[] = $name;
                }
            }

            $next = (int) $parent['parant'];
            if ($next === $id) {
                break;
            }
            $id = $next;
            $guard++;
        }

        return ['floor' => $floor, 'places' => array_reverse($places)];
    }
}
