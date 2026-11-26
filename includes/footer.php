<?php
/**
 * Shared page footer: emergency contact, secondary navigation, copyright and
 * the deferred scripts.
 *
 * The emergency number is deliberately the largest text on the page (>= 32px)
 * and a real tel: link, because that is the one thing a lost, distressed user
 * must be able to hit.
 *
 * The floating "Start navigating" call to action is rendered here so it is
 * present on every page (except the map itself) without every page repeating
 * the markup.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Direct access to includes/footer.php is not allowed.');
}

$cta_page = isset($page) && is_string($page) ? $page : '';
?>
</main><!-- /#main-content -->

<?php if ($cta_page !== 'map'): ?>
    <a class="cta-float" href="<?= e(map3d_url()) ?>" data-cta-start>
        <span class="cta-float__icon" aria-hidden="true">➜</span>
        <span class="cta-float__text">Start navigating</span>
    </a>
<?php endif; ?>

<footer class="site-footer" role="contentinfo">
    <div class="site-footer__inner">

        <section class="footer-emergency" aria-labelledby="footer-emergency-title">
            <h2 class="footer-emergency__title" id="footer-emergency-title">Need help right now?</h2>
            <p class="footer-emergency__text">
                Call <?= e(EMERGENCY_LABEL) ?>. Tell them the building code on the nearest sign.
            </p>
            <a class="footer-emergency__phone" href="tel:<?= e(EMERGENCY_TEL) ?>">
                <?= e(EMERGENCY_PHONE) ?>
            </a>
            <a class="btn btn-emergency" href="<?= e(Router::url('help', ['focus' => 'emergency'])) ?>">
                Open the help centre
            </a>
        </section>

        <nav class="footer-nav" aria-label="Footer">
            <h2 class="footer-nav__title">Explore</h2>
            <ul class="footer-nav__list" role="list">
                <li><a href="<?= e(Router::url('')) ?>">Home</a></li>
                <li><a href="<?= e(map3d_url()) ?>">Map and routes (3D)</a></li>
                <li><a href="<?= e(Router::url('icons')) ?>">Large-icon resources</a></li>
                <li><a href="<?= e(Router::url('help')) ?>">Help centre</a></li>
                <li><a href="<?= e(Router::url('settings')) ?>">Accessibility settings</a></li>
            </ul>
        </nav>
    </div>

    <div class="site-footer__legal">
        <p>
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. CityU coursework prototype, version <?= e(APP_VERSION) ?>.
            Navigation algorithms are <strong>mocked in PHP</strong> for this prototype.
            Map data &copy; OpenStreetMap contributors.
        </p>
        <p class="footer-note"><?= e(APP_COURSE_NOTE) ?></p>
    </div>
</footer>

<?php /* One polite live region per page, used by every script to announce state changes. */ ?>
<div class="visually-hidden" id="live-region" role="status" aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(Router::asset('js/main.js')) ?>" defer></script>
<script src="<?= e(Router::asset('js/accessibility.js')) ?>" defer></script>
<?php foreach ($page_scripts as $script): ?>
    <script src="<?= e(Router::asset('js/' . basename((string) $script))) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
