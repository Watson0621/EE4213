<?php
/**
 * Smart Map Nav — mock data store.
 *
 * This class is the ONLY place campus content lives. Pages, the icon grid, the
 * map and the JSON APIs all read from here, which is what keeps the demo
 * coherent: the toilets icon on icons.php, the marker on map.php and the
 * record returned by api/resources.php are always the same object.
 *
 * In the real system:
 *   - icons/assets -> CMS / design system (Epic 2.1 owner)
 *   - resources    -> campus GIS database (Epic 2.2 owner)
 *   - routes       -> Python pathfinding service (Epic 1.1-1.3, mocked here)
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

final class MockData
{
    /**
     * Accessibility filter chips, shared by icons.php, settings.php and the APIs.
     */
    public const TAGS = [
        'wheelchair' => [
            'label'       => 'Wheelchair',
            'description' => 'Step-free, ramp or lift access.',
            'icon'        => 'ramps',
        ],
        'visual' => [
            'label'       => 'Visual aid',
            'description' => 'Tactile paving, braille signage, high-contrast markings.',
            'icon'        => 'navigation',
        ],
        'hearing' => [
            'label'       => 'Hearing aid',
            'description' => 'Induction loops, visual alarms, captioned screens.',
            'icon'        => 'help_points',
        ],
        'quiet' => [
            'label'       => 'Quiet route',
            'description' => 'Low noise and low crowd, sensory friendly.',
            'icon'        => 'entrances',
        ],
    ];

    /**
     * The eight large-icon tiles for Epic 2.1.
     *
     * `step_free` is deliberately FALSE for two icons: parts of the real campus
     * (kerbed shuttle stops, sloped parking bays) are not step-free, and the
     * "Show only step-free" switch needs something to filter for the demo.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function icons(): array
    {
        return [
            [
                'id'                 => 'navigation',
                'name'               => 'Navigation',
                'category'           => 'Wayfinding',
                'description'        => 'Turn-by-turn directions with a step-free alternative and live route updates.',
                'accessibility_tags' => ['wheelchair', 'visual', 'hearing'],
                'svg_path'           => 'navigation.svg',
                'step_free'          => true,
                'step_free_note'     => '',
                'nearest_locations'  => [
                    ['id' => 'lib',   'distance_m' => 40],
                    ['id' => 'yeung', 'distance_m' => 70],
                ],
            ],
            [
                'id'                 => 'toilets',
                'name'               => 'Accessible Toilets',
                'category'           => 'Facilities',
                'description'        => 'Wheelchair-accessible toilets with grab rails, an emergency cord and turning space.',
                'accessibility_tags' => ['wheelchair', 'visual'],
                'svg_path'           => 'toilets.svg',
                'step_free'          => true,
                'step_free_note'     => '',
                'nearest_locations'  => [
                    ['id' => 'toilets-blue', 'distance_m' => 50],
                    ['id' => 'boc',          'distance_m' => 120],
                ],
            ],
            [
                'id'                 => 'elevators',
                'name'               => 'Elevators & Lifts',
                'category'           => 'Vertical access',
                'description'        => 'Lifts with braille buttons, audible floor announcements and low-level call panels.',
                'accessibility_tags' => ['wheelchair', 'visual', 'hearing'],
                'svg_path'           => 'elevators.svg',
                'step_free'          => true,
                'step_free_note'     => '',
                'nearest_locations'  => [
                    ['id' => 'lift-lobby', 'distance_m' => 30],
                    ['id' => 'yeung',      'distance_m' => 60],
                ],
            ],
            [
                'id'                 => 'ramps',
                'name'               => 'Ramps & Level Access',
                'category'           => 'Vertical access',
                'description'        => 'Gradual ramps with handrails and non-slip surfaces between levels.',
                'accessibility_tags' => ['wheelchair', 'visual'],
                'svg_path'           => 'ramps.svg',
                'step_free'          => true,
                'step_free_note'     => '',
                'nearest_locations'  => [
                    ['id' => 'ramp-library', 'distance_m' => 20],
                    ['id' => 'lib',          'distance_m' => 45],
                ],
            ],
            [
                'id'                 => 'entrances',
                'name'               => 'Accessible Entrances',
                'category'           => 'Wayfinding',
                'description'        => 'Step-free entrances with automatic doors, wide thresholds and clear signage.',
                'accessibility_tags' => ['wheelchair', 'visual'],
                'svg_path'           => 'entrances.svg',
                'step_free'          => true,
                'step_free_note'     => '',
                'nearest_locations'  => [
                    ['id' => 'lib',   'distance_m' => 35],
                    ['id' => 'yeung', 'distance_m' => 55],
                ],
            ],
            [
                'id'                 => 'help_points',
                'name'               => 'Help Points',
                'category'           => 'Support',
                'description'        => 'Staffed desks and call points with hearing loops and spoken instructions.',
                'accessibility_tags' => ['wheelchair', 'hearing', 'visual'],
                'svg_path'           => 'help-points.svg',
                'step_free'          => true,
                'step_free_note'     => '',
                'nearest_locations'  => [
                    ['id' => 'help-main', 'distance_m' => 25],
                    ['id' => 'mtr',       'distance_m' => 180],
                ],
            ],
            [
                'id'                 => 'transport',
                'name'               => 'Transport & Shuttle',
                'category'           => 'Getting here',
                'description'        => 'Step-free MTR access, shuttle bus stops and accessible taxi drop-off.',
                'accessibility_tags' => ['wheelchair', 'visual', 'hearing'],
                'svg_path'           => 'transport.svg',
                'step_free'          => false,
                'step_free_note'     => 'Two shuttle stops have a 4 cm kerb. The MTR exit is fully step-free.',
                'nearest_locations'  => [
                    ['id' => 'mtr',      'distance_m' => 150],
                    ['id' => 'festival', 'distance_m' => 95],
                ],
            ],
            [
                'id'                 => 'parking',
                'name'               => 'Accessible Parking',
                'category'           => 'Getting here',
                'description'        => 'Wider bays beside step-free entrances, with level routes to the lifts.',
                'accessibility_tags' => ['wheelchair'],
                'svg_path'           => 'parking.svg',
                'step_free'          => false,
                'step_free_note'     => 'P1 is level; the P2 overflow bays sit on a 1:14 slope.',
                'nearest_locations'  => [
                    ['id' => 'parking-p1', 'distance_m' => 80],
                    ['id' => 'sr',         'distance_m' => 130],
                ],
            ],
        ];
    }

    /**
     * Accessible campus resources (Epic 2.2 markers + Epic 2.1 "nearest" list).
     *
     * Coordinates are illustrative CityU-style positions used for the demo;
     * they are approximations, not survey data (stated in the README).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function resources(): array
    {
        return [
            [
                'id' => 'lib', 'type' => 'entrances', 'name' => 'Run Run Shaw Library',
                'lat' => 22.3372, 'lng' => 114.1725, 'step_free' => true, 'icon' => 'entrances',
                'description' => 'Main library entrance with automatic doors and level access from the podium.',
                'accessibility_tags' => ['wheelchair', 'visual', 'quiet'],
            ],
            [
                'id' => 'yeung', 'type' => 'entrances', 'name' => 'Yeung Kin Man Academic Building (Blue Zone)',
                'lat' => 22.3375, 'lng' => 114.1729, 'step_free' => true, 'icon' => 'entrances',
                'description' => 'Blue Zone lobby: level entrance, wide lifts and a staffed reception.',
                'accessibility_tags' => ['wheelchair', 'visual'],
            ],
            [
                'id' => 'boc', 'type' => 'entrances', 'name' => 'Bank of China (Hong Kong) Complex (Red Zone)',
                'lat' => 22.3368, 'lng' => 114.1742, 'step_free' => true, 'icon' => 'entrances',
                'description' => 'Red Zone entrance with induction loop at reception and step-free turnstiles.',
                'accessibility_tags' => ['wheelchair', 'hearing'],
            ],
            [
                'id' => 'cheng', 'type' => 'entrances', 'name' => 'Cheng Yick-chi Building',
                'lat' => 22.3381, 'lng' => 114.1735, 'step_free' => true, 'icon' => 'entrances',
                'description' => 'Teaching block with a level side entrance and lift access to all floors.',
                'accessibility_tags' => ['wheelchair'],
            ],
            [
                'id' => 'cmc', 'type' => 'help_points', 'name' => 'Chinese Civilisation Centre',
                'lat' => 22.3362, 'lng' => 114.1738, 'step_free' => false, 'icon' => 'help_points',
                'description' => 'Exhibition space; the main door has two steps, the side ramp is signposted.',
                'accessibility_tags' => ['quiet', 'visual'],
            ],
            [
                'id' => 'sr', 'type' => 'entrances', 'name' => 'Student Residence Halls',
                'lat' => 22.3392, 'lng' => 114.1712, 'step_free' => true, 'icon' => 'entrances',
                'description' => 'Hall entrance with accessible rooms on the ground floor and a quiet study room.',
                'accessibility_tags' => ['wheelchair', 'quiet'],
            ],
            [
                'id' => 'sports', 'type' => 'help_points', 'name' => 'CityU Sports Centre',
                'lat' => 22.3355, 'lng' => 114.1722, 'step_free' => true, 'icon' => 'help_points',
                'description' => 'Sports hall with a level entrance, hearing loop and accessible changing room.',
                'accessibility_tags' => ['wheelchair', 'hearing'],
            ],
            [
                'id' => 'mtr', 'type' => 'transport', 'name' => 'MTR Kowloon Tong Station — Exit C',
                'lat' => 22.3370, 'lng' => 114.1762, 'step_free' => true, 'icon' => 'transport',
                'description' => 'Lift-served station exit with tactile paving leading to the campus footbridge.',
                'accessibility_tags' => ['wheelchair', 'visual', 'hearing'],
            ],
            [
                'id' => 'festival', 'type' => 'transport', 'name' => 'Festival Walk Link Bridge',
                'lat' => 22.3378, 'lng' => 114.1750, 'step_free' => true, 'icon' => 'transport',
                'description' => 'Covered link bridge to Festival Walk with lifts at both ends.',
                'accessibility_tags' => ['wheelchair'],
            ],
            [
                'id' => 'sus', 'type' => 'entrances', 'name' => 'Student Amenities Building — Canteen',
                'lat' => 22.3366, 'lng' => 114.1745, 'step_free' => true, 'icon' => 'entrances',
                'description' => 'Canteen with lowered counters, tray slides and a quieter seating area.',
                'accessibility_tags' => ['wheelchair', 'quiet'],
            ],
            [
                'id' => 'toilets-blue', 'type' => 'toilets', 'name' => 'Accessible Toilet — Blue Zone 3/F',
                'lat' => 22.3374, 'lng' => 114.1730, 'step_free' => true, 'icon' => 'toilets',
                'description' => 'Left-hand transfer, grab rails both sides, emergency cord and a changing bench.',
                'accessibility_tags' => ['wheelchair', 'visual'],
            ],
            [
                'id' => 'parking-p1', 'type' => 'parking', 'name' => 'Accessible Parking — P1',
                'lat' => 22.3380, 'lng' => 114.1715, 'step_free' => true, 'icon' => 'parking',
                'description' => 'Six wider bays beside the lift lobby, level transfer space on both sides.',
                'accessibility_tags' => ['wheelchair'],
            ],
            [
                'id' => 'help-main', 'type' => 'help_points', 'name' => 'Help Point — Main Entrance',
                'lat' => 22.3372, 'lng' => 114.1728, 'step_free' => true, 'icon' => 'help_points',
                'description' => 'Staffed help desk with a hearing loop, low counter and a call button at 90 cm.',
                'accessibility_tags' => ['wheelchair', 'hearing', 'visual', 'quiet'],
            ],
            [
                'id' => 'lift-lobby', 'type' => 'elevators', 'name' => 'Lift Lobby — Academic Building',
                'lat' => 22.3376, 'lng' => 114.1726, 'step_free' => true, 'icon' => 'elevators',
                'description' => 'Four lifts with braille buttons, spoken floor announcements and a mirror for reversing.',
                'accessibility_tags' => ['wheelchair', 'visual', 'hearing'],
            ],
            [
                'id' => 'ramp-library', 'type' => 'ramps', 'name' => 'Step-free Ramp — Library Entrance',
                'lat' => 22.3371, 'lng' => 114.1724, 'step_free' => true, 'icon' => 'ramps',
                'description' => '1:20 gradient ramp with handrails both sides and tactile edging.',
                'accessibility_tags' => ['wheelchair', 'visual'],
            ],
        ];
    }

    /**
     * Hardcoded campus routes.
     *
     * These stand in for the Python pathfinding service (Epic 1.1-1.3). Each
     * route carries both a polyline for Leaflet and plain-text steps, because
     * the map view must offer a text alternative (WCAG 1.1.1).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function routes(): array
    {
        return [
            [
                'id'           => 'r1',
                'name'         => 'MTR Exit C → Run Run Shaw Library',
                'from'         => 'mtr',
                'to'           => 'lib',
                'step_free'    => true,
                'distance_m'   => 520,
                'duration_min' => 8,
                'features'     => ['Step-free', 'Lift access', 'Tactile paving', 'Rest points'],
                'route'        => [
                    [22.3370, 114.1762], [22.3371, 114.1756], [22.3371, 114.1748],
                    [22.3373, 114.1739], [22.3376, 114.1733], [22.3374, 114.1728],
                    [22.3372, 114.1725],
                ],
                'steps' => [
                    ['instruction' => 'Leave the MTR at Exit C and face the campus footbridge.', 'distance_m' => 40,
                     'accessibility_note' => 'Level entrance, tactile paving at the threshold.'],
                    ['instruction' => 'Follow the covered footbridge towards the Red Zone and keep left.', 'distance_m' => 120,
                     'accessibility_note' => '1.8 m wide walkway, no steps.'],
                    ['instruction' => 'Take Lift L1 to Level 3. The call panel is at 90 cm.', 'distance_m' => 30,
                     'accessibility_note' => 'Braille buttons and spoken floor announcements.'],
                    ['instruction' => 'Turn right out of the lift and follow the atrium walkway.', 'distance_m' => 180,
                     'accessibility_note' => 'Two benches with armrests on this stretch.'],
                    ['instruction' => 'Enter the Library through the automatic doors on your right.', 'distance_m' => 150,
                     'accessibility_note' => 'Automatic doors, no threshold, staffed desk 6 m inside.'],
                ],
            ],
            [
                'id'           => 'r2',
                'name'         => 'Academic Building (Blue Zone) → Bank of China Complex',
                'from'         => 'yeung',
                'to'           => 'boc',
                'step_free'    => true,
                'distance_m'   => 480,
                'duration_min' => 7,
                'features'     => ['Step-free', 'Quiet route', 'Seating', 'Hearing loop at reception'],
                'route'        => [
                    [22.3375, 114.1729], [22.3374, 114.1734], [22.3372, 114.1739],
                    [22.3370, 114.1741], [22.3368, 114.1742],
                ],
                'steps' => [
                    ['instruction' => 'Exit the Blue Zone lobby and head east along the podium.', 'distance_m' => 90,
                     'accessibility_note' => 'Level surface, 2 % cross-fall only.'],
                    ['instruction' => 'Continue past the lift lobby and keep the courtyard on your left.', 'distance_m' => 160,
                     'accessibility_note' => 'Quiet corridor, away from the canteen.'],
                    ['instruction' => 'Use the level link corridor into the Red Zone.', 'distance_m' => 150,
                     'accessibility_note' => 'Wide enough for two wheelchairs to pass.'],
                    ['instruction' => 'The Bank of China Complex entrance is straight ahead.', 'distance_m' => 80,
                     'accessibility_note' => 'Induction loop at reception, step-free turnstiles.'],
                ],
            ],
            [
                'id'           => 'r3',
                'name'         => 'Student Residence → CityU Sports Centre',
                'from'         => 'sr',
                'to'           => 'sports',
                'step_free'    => false,
                'distance_m'   => 650,
                'duration_min' => 9,
                'features'     => ['Fastest route', '24 steps at the slope', 'Outdoor section'],
                'route'        => [
                    [22.3392, 114.1712], [22.3388, 114.1716], [22.3382, 114.1718],
                    [22.3375, 114.1719], [22.3367, 114.1720], [22.3355, 114.1722],
                ],
                'steps' => [
                    ['instruction' => 'Leave the residence and walk south down the service road.', 'distance_m' => 140,
                     'accessibility_note' => 'Pavement narrows to 1.1 m at the barrier.'],
                    ['instruction' => 'Take the outdoor stairway beside the slope (24 steps).', 'distance_m' => 60,
                     'accessibility_note' => 'Not step-free. Step-free alternative is route r1 plus 6 minutes.'],
                    ['instruction' => 'Continue past the tennis courts on the level path.', 'distance_m' => 260,
                     'accessibility_note' => 'Level but unsheltered.'],
                    ['instruction' => 'Go through the Sports Centre side door.', 'distance_m' => 190,
                     'accessibility_note' => 'Door requires 2 kg push force; staffed between 08:00 and 20:00.'],
                ],
            ],
            [
                'id'           => 'r4',
                'name'         => 'Accessible Parking P1 → Cheng Yick-chi Building',
                'from'         => 'parking-p1',
                'to'           => 'cheng',
                'step_free'    => true,
                'distance_m'   => 380,
                'duration_min' => 6,
                'features'     => ['Step-free', 'Ramp', 'Drop-off point', 'Rest points'],
                'route'        => [
                    [22.3380, 114.1715], [22.3382, 114.1722], [22.3383, 114.1728],
                    [22.3381, 114.1735],
                ],
                'steps' => [
                    ['instruction' => 'From the P1 accessible bay, follow the 1:20 ramp to the podium.', 'distance_m' => 80,
                     'accessibility_note' => 'Ramp has handrails both sides.'],
                    ['instruction' => 'Head east, keeping the residence garden on your left.', 'distance_m' => 130,
                     'accessibility_note' => 'Level route with two rest points.'],
                    ['instruction' => 'Cross the quiet service lane at the raised crossing.', 'distance_m' => 60,
                     'accessibility_note' => 'Raised table crossing, no kerb.'],
                    ['instruction' => 'The Cheng Yick-chi level side entrance is on your right.', 'distance_m' => 110,
                     'accessibility_note' => 'Level door, 90 cm clear opening.'],
                ],
            ],
        ];
    }

    /* -----------------------------------------------------------------
     * Lookups
     * -------------------------------------------------------------- */

    /** @return array<string, mixed>|null */
    public static function resourceById(string $id): ?array
    {
        foreach (self::resources() as $resource) {
            if ($resource['id'] === $id) {
                return $resource;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function iconById(string $id): ?array
    {
        foreach (self::icons() as $icon) {
            if ($icon['id'] === $id) {
                return $icon;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function routeById(string $id): ?array
    {
        foreach (self::routes() as $route) {
            if ($route['id'] === $id) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Resolve an icon's `nearest_locations` (ids + distances) into full records.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function nearestFor(string $iconId): array
    {
        $icon = self::iconById($iconId);

        if ($icon === null) {
            return [];
        }

        $resolved = [];

        foreach ($icon['nearest_locations'] as $entry) {
            $resource = self::resourceById((string) $entry['id']);

            if ($resource === null) {
                continue;
            }

            $resource['distance_m'] = (int) $entry['distance_m'];
            $resolved[]             = $resource;
        }

        return $resolved;
    }

    /**
     * Stand-in for the Python pathfinding service.
     *
     * Matching order: explicit destination id -> nearest polyline endpoints ->
     * first route of the requested type. The returned `match` key tells the UI
     * how confident the result is, so the map can say "closest available route".
     *
     * @return array{route: array<string, mixed>, match: string}
     */
    public static function findRoute(
        ?string $toId = null,
        bool $stepFreeOnly = true,
        ?float $fromLat = null,
        ?float $fromLng = null,
        ?float $toLat = null,
        ?float $toLng = null
    ): array {
        $routes     = self::routes();
        $candidates = array_values(array_filter(
            $routes,
            static fn (array $route): bool => !$stepFreeOnly || (bool) $route['step_free']
        ));

        // A step-free request must never silently return stairs, but we still
        // need something to draw: fall back to all routes and report it.
        $relaxed = false;
        if ($candidates === []) {
            $candidates = $routes;
            $relaxed    = true;
        }

        if ($toId !== null && $toId !== '') {
            foreach ($candidates as $route) {
                if ($route['to'] === $toId || $route['from'] === $toId) {
                    return ['route' => $route, 'match' => 'destination'];
                }
            }
        }

        $best      = $candidates[0];
        $bestScore = PHP_FLOAT_MAX;

        if ($fromLat !== null && $fromLng !== null && $toLat !== null && $toLng !== null) {
            foreach ($candidates as $route) {
                $points = $route['route'];
                $start  = $points[0];
                $end    = $points[count($points) - 1];
                $score  = self::haversine($fromLat, $fromLng, (float) $start[0], (float) $start[1])
                        + self::haversine($toLat, $toLng, (float) $end[0], (float) $end[1]);

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best      = $route;
                }
            }

            return ['route' => $best, 'match' => $bestScore < 150.0 ? 'coordinates' : 'nearest'];
        }

        return ['route' => $best, 'match' => $relaxed ? 'relaxed' : 'default'];
    }

    /**
     * Progression along a route (mock of the Epic 1.3 progress service).
     *
     * @return array<string, mixed>
     */
    public static function progress(?string $routeId = null, int $segment = 3): array
    {
        $route = self::routeById((string) ($routeId ?? 'r1')) ?? (self::routes()[0]);
        $steps = $route['steps'];
        $total = count($steps);

        $current   = max(1, min($segment, $total));
        $completed = $current - 1;
        $percent   = (int) round(($completed / $total) * 100);

        return [
            'route_id'            => $route['id'],
            'route_name'          => $route['name'],
            'completed_segments'  => $completed,
            'current_segment'     => $current,
            'next_segment'        => $current < $total ? $current + 1 : null,
            'total_segments'      => $total,
            'progress_pct'        => $percent,
            'current_instruction' => $steps[$current - 1]['instruction'],
            'next_instruction'    => $current < $total ? $steps[$current]['instruction'] : null,
            'remaining_m'         => array_sum(array_column(array_slice($steps, $current - 1), 'distance_m')),
        ];
    }

    /* -----------------------------------------------------------------
     * Rendering helpers
     * -------------------------------------------------------------- */

    /**
     * Inline an SVG icon file with correct accessibility plumbing.
     *
     * The brief asks for role="img" + <title> on every SVG. That is correct for
     * a standalone icon, but WRONG inside a tile that already has a descriptive
     * aria-label: a screen reader would announce the icon twice. So:
     *
     *   $decorative = false -> role="img" + <title> + aria-labelledby
     *   $decorative = true  -> role="presentation" + aria-hidden="true"
     *
     * Both variants keep the <title> element so every SVG file stays valid.
     *
     * The same icon can appear several times on one page (tile, detail panel,
     * preview), so the title id is made unique with a per-request counter.
     * Duplicate ids break aria-labelledby and fail HTML validation.
     */
    public static function iconSvg(string $iconId, bool $decorative = false, ?string $label = null): string
    {
        static $instance = 0;

        $icon = self::iconById($iconId);
        $name = $label ?? (string) ($icon['name'] ?? ucfirst($iconId));
        $file = PUBLIC_ROOT . '/assets/icons/' . basename((string) ($icon['svg_path'] ?? $iconId . '.svg'));

        if (!is_file($file)) {
            return '<svg class="icon icon--fallback" viewBox="0 0 64 64" role="img"'
                . ' aria-label="' . e($name) . ' (icon unavailable)" focusable="false">'
                . '<title>' . e($name) . '</title>'
                . '<rect x="4" y="4" width="56" height="56" rx="10" fill="none" stroke="currentColor" stroke-width="4"/>'
                . '<text x="32" y="42" text-anchor="middle" font-size="26" fill="currentColor">?</text></svg>';
        }

        $svg        = (string) file_get_contents($file);
        $instance++;
        $slug       = (string) preg_replace('/[^a-z0-9_-]/i', '-', $iconId);
        $titleId    = 'svg-title-' . $slug . '-' . $instance . ($decorative ? '-decor' : '');

        $attributes = $decorative
            ? 'role="presentation" aria-hidden="true" focusable="false"'
            : 'role="img" aria-labelledby="' . $titleId . '" focusable="false"';

        $svg = (string) preg_replace('/<svg\b/', '<svg ' . $attributes, $svg, 1);
        $svg = (string) preg_replace(
            '/(<svg\b[^>]*>)/',
            '$1<title id="' . $titleId . '">' . e($name) . '</title>',
            $svg,
            1
        );

        return $svg;
    }

    /** Human label for a tag id ("wheelchair" -> "Wheelchair"). */
    public static function tagLabel(string $tag): string
    {
        return (string) (self::TAGS[$tag]['label'] ?? ucfirst($tag));
    }

    /** Great-circle distance in metres, used to pick the closest mock route. */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $deltaLat    = deg2rad($lat2 - $lat1);
        $deltaLng    = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

