<?php
/**
 * Main navigation.
 *
 * - role="navigation" + aria-label="Main" (there is a second nav in the footer,
 *   so both need distinct labels for screen-reader landmark lists).
 * - aria-current="page" marks the active destination.
 * - Every target is at least 48x48 CSS pixels.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}

$nav_current = Router::current();

$nav_items = [
    [
        'key'   => 'map',
        'label' => 'Map',
        'url'   => map3d_url(),
        'icon'  => '<path d="M4 6 L10 3 L16 6 L22 3 V21 L16 18 L10 21 L4 18 Z"/><path d="M10 3 V21"/><path d="M16 6 V18"/>',
    ],
    [
        'key'   => 'icons',
        'label' => 'Resources',
        'url'   => Router::url('icons'),
        'icon'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    ],
    [
        'key'   => 'help',
        'label' => 'Help Centre',
        'url'   => Router::url('help'),
        'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5 A2.5 2.5 0 1 1 12.5 12 V13"/><circle cx="12.5" cy="16.5" r="0.9" fill="currentColor"/>',
    ],
    [
        'key'   => 'settings',
        'label' => 'Settings',
        'url'   => Router::url('settings'),
        'icon'  => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.8 V5 M12 19 V21.2 M2.8 12 H5 M19 12 H21.2 M5.5 5.5 L7 7 M17 17 L18.5 18.5 M18.5 5.5 L17 7 M7 17 L5.5 18.5"/>',
    ],
];
?>
<nav id="site-nav" class="site-nav" role="navigation" aria-label="Main">
    <ul class="nav-list" role="list">
        <?php foreach ($nav_items as $item): ?>
            <?php $is_current = ($item['key'] === $nav_current); ?>
            <li class="nav-item">
                <a class="nav-link<?= $is_current ? ' is-active' : '' ?>"
                   href="<?= e($item['url']) ?>"<?= $is_current ? ' aria-current="page"' : '' ?>>
                    <svg class="nav-link__icon" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"
                         role="presentation" focusable="false" fill="none" stroke="currentColor"
                         stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <?= $item['icon'] /* static, trusted markup defined above */ ?>
                    </svg>
                    <span class="nav-link__label"><?= e($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
