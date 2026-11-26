<?php
/**
 * Smart Map Nav — icon catalogue and nodetype mapping.
 *
 * Author: David (with Grok Premium)
 *
 * What was done:
 *   - Groups: Accessibility (lifts + accessible toilets), Transport (lifts, escalators, stairs),
 *     General (everything else). Step-free is a toggle, not a group.
 *   - Toilets split into male / female / accessible — no generic "toilets" type.
 *   - Step-free flag applies to lifts only.
 *   - Generic Toilet (12) ignored. Corner/Area/Kiosk/Food Counter excluded.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

final class Icons
{
    /**
     * Tag catalogue. An icon can belong to more than one group.
     * Groups: accessibility | transport | general
     * Step-free is a flag on lifts only, not a group.
     */
    public const TAGS = [
        'lifts' => [
            'label'       => 'Lifts',
            'description' => 'Step-free vertical access.',
            'groups'      => ['accessibility', 'transport'],
            'icon'        => 'lifts',
        ],
        'stairs' => [
            'label'       => 'Stairs',
            'description' => 'Stairs.',
            'groups'      => ['transport'],
            'icon'        => 'stairs',
        ],
        'escalators' => [
            'label'       => 'Escalators',
            'description' => 'Escalators.',
            'groups'      => ['transport'],
            'icon'        => 'escalators',
        ],
        'toilets_accessible' => [
            'label'       => 'Accessible toilets',
            'description' => 'Wheelchair-accessible toilets.',
            'groups'      => ['accessibility'],
            'icon'        => 'toilets_accessible',
        ],
        'toilets_male' => [
            'label'       => 'Male toilets',
            'description' => 'Male toilets.',
            'groups'      => ['general'],
            'icon'        => 'toilets_male',
        ],
        'toilets_female' => [
            'label'       => 'Female toilets',
            'description' => 'Female toilets.',
            'groups'      => ['general'],
            'icon'        => 'toilets_female',
        ],
        'water' => [
            'label'       => 'Water',
            'description' => 'Water stations.',
            'groups'      => ['general'],
            'icon'        => 'water',
        ],
        'shops' => [
            'label'       => 'Shops',
            'description' => 'Shops and retail.',
            'groups'      => ['general'],
            'icon'        => 'shops',
        ],
        'banking' => [
            'label'       => 'Banking',
            'description' => 'Banks and ATMs.',
            'groups'      => ['general'],
            'icon'        => 'banking',
        ],
        'printers' => [
            'label'       => 'Printers',
            'description' => 'Printing stations.',
            'groups'      => ['general'],
            'icon'        => 'printers',
        ],
        'computers' => [
            'label'       => 'Computers',
            'description' => 'Computer stations.',
            'groups'      => ['general'],
            'icon'        => 'computers',
        ],
        'charging' => [
            'label'       => 'Charging',
            'description' => 'Charging stations.',
            'groups'      => ['general'],
            'icon'        => 'charging',
        ],
        'vending' => [
            'label'       => 'Vending',
            'description' => 'Vending machines.',
            'groups'      => ['general'],
            'icon'        => 'vending',
        ],
        'microwaves' => [
            'label'       => 'Microwaves',
            'description' => 'Microwave ovens.',
            'groups'      => ['general'],
            'icon'        => 'microwaves',
        ],
    ];

    public const GROUPS = [
        'accessibility' => 'Accessibility',
        'transport'     => 'Transport',
        'general'       => 'General',
    ];

    public const NODETYPE_TO_ICON = [
        10 => 'escalators',
        11 => 'stairs',
        13 => 'printers',
        14 => 'computers',
        16 => 'microwaves',
        17 => 'lifts',
        18 => 'shops',
        19 => 'banking',
        20 => 'water',
        25 => 'charging',
        26 => 'toilets_male',
        27 => 'toilets_female',
        28 => 'toilets_accessible',
        29 => 'vending',
    ];

    public const IGNORED_NODETYPES = [1, 2, 3, 4, 5, 9, 12, 21, 22, 23, 24];

    /** Only lifts are step-free. */
    public const STEP_FREE_TYPES = [17];

    public static function icons(): array
    {
        $out = [];
        foreach (self::TAGS as $id => $tag) {
            $out[] = [
                'id'          => $id,
                'name'        => $tag['label'],
                'category'    => self::GROUPS[$tag['groups'][0]] ?? 'General',
                'description' => $tag['description'],
                'tags'        => [$id],
                'groups'      => $tag['groups'],
                'group'       => $tag['groups'][0],
                'svg_path'    => $id . '.svg',
                'step_free'   => $id === 'lifts',
            ];
        }
        return $out;
    }

    public static function iconById(string $id): ?array
    {
        foreach (self::icons() as $icon) {
            if ($icon['id'] === $id) {
                return $icon;
            }
        }
        return null;
    }

    public static function tagLabel(string $tag): string
    {
        return (string) (self::TAGS[$tag]['label'] ?? ucfirst($tag));
    }

    public static function tagsInGroup(string $group): array
    {
        return array_filter(
            self::TAGS,
            static fn (array $tag): bool => in_array($group, $tag['groups'] ?? [], true)
        );
    }

    public static function iconForNodeType(int $type): ?string
    {
        return self::NODETYPE_TO_ICON[$type] ?? null;
    }

    public static function groupsForIcon(string $iconId): array
    {
        return self::TAGS[$iconId]['groups'] ?? ['general'];
    }

    public static function isStepFreeType(int $type): bool
    {
        return in_array($type, self::STEP_FREE_TYPES, true);
    }

    public static function primaryTagForIcon(string $iconId): string
    {
        $icon = self::iconById($iconId);
        return (string) ($icon['tags'][0] ?? $iconId);
    }

    public static function groupForIcon(string $iconId): string
    {
        $icon = self::iconById($iconId);
        return (string) ($icon['group'] ?? 'general');
    }

    /**
     * Inject accessible title / role into an SVG file.
     * Reuses the same approach as the original MockData::iconSvg().
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

        $svg     = (string) file_get_contents($file);
        $instance++;
        $slug    = (string) preg_replace('/[^a-z0-9_-]/i', '-', $iconId);
        $titleId = 'svg-title-' . $slug . '-' . $instance . ($decorative ? '-decor' : '');

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
}
