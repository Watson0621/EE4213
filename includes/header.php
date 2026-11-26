<?php
/**
 * Shared page header: document head, skip link, masthead and navigation.
 *
 * Pages set these variables before requiring this file:
 *   $page, $page_title, $page_description, $page_scripts[], $main_class
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Direct access to includes/header.php is not allowed.');
}

require_once __DIR__ . '/config.php';

$page             = isset($page) && is_string($page) ? $page : Router::current();
$page_title       = isset($page_title) ? (string) $page_title : Router::title($page);
$page_description = isset($page_description) ? (string) $page_description : APP_TAGLINE;
$page_scripts     = isset($page_scripts) && is_array($page_scripts) ? $page_scripts : [];
$main_class       = isset($main_class) ? (string) $main_class : '';

$is_home        = in_array($page, ['index', ''], true);
$document_title = $is_home ? APP_NAME . ' — ' . APP_TAGLINE : $page_title . ' — ' . APP_NAME;
$body_class     = body_classes() . ' page-' . (string) preg_replace('/[^a-z0-9_-]/i', '', $page);
$prefs          = a11y_prefs();
?>
<!DOCTYPE html>
<html lang="<?= e(pref('language') === 'zh-HK' ? 'zh-HK' : 'en') ?>" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($page_description) ?>">
    <meta name="theme-color" content="#0b4f9e">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($document_title) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= e(Router::asset('icons/navigation.svg')) ?>">

    <?php /* Custom CSS only: no CDN, no build step, total control of contrast. */ ?>
    <link rel="stylesheet" href="<?= e(Router::asset('css/main.css')) ?>">
    <link rel="stylesheet" href="<?= e(Router::asset('css/mobile.css')) ?>">
    <link rel="stylesheet" href="<?= e(Router::asset('css/accessibility.css')) ?>">
</head>
<body class="<?= e($body_class) ?>" data-page="<?= e($page) ?>"
      data-prefs-endpoint="<?= e(Router::api('prefs')) ?>"
      style="font-size: <?= (int) $prefs['text_scale'] ?>%">
<script>
/*
 * Pre-paint preference sync.
 *
 * The server renders the classes and the inline font-size from the PHP session,
 * but a returning visitor may have newer values in localStorage. This runs
 * before any page content paints, so a high-contrast user never gets a white
 * flash and the chosen text size is already applied. Kept inline and tiny on
 * purpose: an external file with defer would run too late.
 */
(function () {
    try {
        var saved = JSON.parse(localStorage.getItem('smartmapnav:prefs') || 'null');
        if (!saved) { return; }
        var b = document.body.classList;
        b.remove('high-contrast', 'large-text', 'vibration-on', 'sr-hints',
                 'icon-size-large', 'icon-size-xlarge', 'icon-size-max',
                 'text-scale-100', 'text-scale-125', 'text-scale-150', 'text-scale-200');
        if (saved.high_contrast) { b.add('high-contrast'); }
        if (saved.large_text) { b.add('large-text'); }
        if (saved.vibration) { b.add('vibration-on'); }
        if (saved.sr_hints) { b.add('sr-hints'); }
        b.add('icon-size-' + (saved.icon_size || 'large'));
        var scale = [100, 125, 150].indexOf(Number(saved.text_scale)) > -1
            ? Number(saved.text_scale) : 100;
        b.add('text-scale-' + scale);
        document.body.style.fontSize = scale + '%';
    } catch (err) {
        /* Private mode / storage disabled: server-rendered classes still apply. */
    }
})();
</script>

<?php require __DIR__ . '/skip-link.php'; ?>

<header class="site-header" role="banner">
    <div class="site-header__inner">
        <a class="brand" href="<?= e(Router::url('')) ?>">
            <span class="brand__mark" aria-hidden="true">
                <?= MockData::iconSvg('navigation', true) ?>
            </span>
            <span class="brand__text">
                <span class="brand__name"><?= e(APP_NAME) ?></span>
                <span class="brand__tagline">Accessible campus navigation</span>
            </span>
        </a>

        <div class="site-header__actions">
            <button type="button" class="a11y-button" id="a11y-toggle"
                    aria-expanded="false" aria-controls="a11y-quick-menu">
                <span class="a11y-button__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" role="presentation" focusable="false"
                         fill="none" stroke="currentColor" stroke-width="1.9">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 3 A9 9 0 0 0 12 21 Z" fill="currentColor" stroke="none"/>
                    </svg>
                </span>
                <span class="a11y-button__text">Accessibility</span>
            </button>

            <button type="button" class="nav-toggle" id="nav-toggle"
                    aria-expanded="false" aria-controls="site-nav">
                <span class="nav-toggle__bars" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
                <span class="nav-toggle__text">Menu</span>
            </button>
        </div>
    </div>

    <div class="a11y-menu" id="a11y-quick-menu" hidden>
        <div class="a11y-menu__inner">
            <h2 class="a11y-menu__title" id="a11y-menu-title">Quick accessibility options</h2>
            <div class="a11y-menu__grid" role="group" aria-labelledby="a11y-menu-title">
                <div class="mini-switch">
                    <input type="checkbox" id="quick-high-contrast" class="mini-switch__input"
                           data-pref="high_contrast" <?= $prefs['high_contrast'] ? 'checked' : '' ?>>
                    <label class="mini-switch__label" for="quick-high-contrast">
                        <span class="mini-switch__track" aria-hidden="true"><span class="mini-switch__thumb"></span></span>
                        <span class="mini-switch__text">High contrast</span>
                    </label>
                </div>
                <div class="mini-switch">
                    <input type="checkbox" id="quick-large-text" class="mini-switch__input"
                           data-pref="large_text" <?= $prefs['large_text'] ? 'checked' : '' ?>>
                    <label class="mini-switch__label" for="quick-large-text">
                        <span class="mini-switch__track" aria-hidden="true"><span class="mini-switch__thumb"></span></span>
                        <span class="mini-switch__text">Large text</span>
                    </label>
                </div>
                <a class="btn btn-secondary btn-small" href="<?= e(Router::url('settings')) ?>">All settings</a>
            </div>
            <p class="a11y-menu__hint">Changes are saved on this device and announced to screen readers.</p>
        </div>
    </div>

    <?php require __DIR__ . '/nav.php'; ?>
</header>

<main id="main-content" class="site-main <?= e($main_class) ?>" role="main" tabindex="-1">
