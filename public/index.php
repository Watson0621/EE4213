<?php
/**
 * Landing page.
 *
 * One <h1>, then one <h2> per section.
 *
 * First visit: when the `usertype` cookie is missing, a short profile panel
 * asks who the visitor is (student / JULAC / guest). The answer is stored in
 * the `usertype` cookie by includes/config.php -> save_user_prefs(), so the
 * same controls in Settings always agree with this panel.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

$profile      = user_prefs();
$showWelcome  = $profile['usertype'] === '';
$welcomeSaved = false;
$welcomeError = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['profile-form'])) {
    if (!csrf_valid(isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null)) {
        $welcomeError = 'Your security token expired. Please choose again.';
    } else {
        $type = isset($_POST['usertype']) && is_string($_POST['usertype']) ? $_POST['usertype'] : '';

        if (!array_key_exists($type, USER_TYPE_OPTIONS)) {
            $welcomeError = 'Please choose the option that describes you.';
        } else {
            save_user_prefs($type);

            $profile      = user_prefs();
            $showWelcome  = false;
            $welcomeSaved = true;
        }
    }
}

$page             = 'index';
$page_description = 'Smart Map Nav finds step-free, accessible routes across campus using large icons, plain language and keyboard-friendly controls.';

require __DIR__ . '/../includes/header.php';
?>

<div class="hero">
    <div class="hero__main">
        <p class="eyebrow">Campus wayfinding</p>
        <h1>Campus navigation, simplified.</h1>
        <p class="lede">
            Smart Map Nav uses large visual icons, 
            plain language, and step-free routing to make campus travel effortless. 
            Built for wheelchair accessibility, vision and hearing needs, 
            and anyone who wants a clearer path forward.
        </p>

        <div class="hero__actions">
            <a class="btn btn-primary btn-large" href="<?= e(map3d_url()) ?>">
                Start navigating
            </a>
            <a class="btn btn-secondary btn-large" href="<?= e(Router::url('icons')) ?>">
                Browse resources
            </a>
        </div>

        <p class="hint-extra">
            Tip: your accessibility choices are saved on this device. You can change them
            at any time with the <strong>Accessibility</strong> button in the header or the
            <a href="<?= e(Router::url('settings')) ?>">Settings page</a>.
        </p>
    </div>

    <aside class="hero__aside" aria-labelledby="hero-aside-title">
        <h2 id="hero-aside-title">What you can do right now</h2>
        <ul>
            <li>See every step of a route as plain text, not just a line on a map.</li>
            <li>Filter resources by wheelchair, visual, hearing or quiet needs.</li>
            <li>Switch to high contrast or 200&nbsp;% text without losing any content.</li>
            <li>Call campus security in one tap from any page.</li>
        </ul>
        <p class="muted">Everything works with a keyboard and a screen reader.</p>
    </aside>
</div>

<?php if ($welcomeSaved): ?>
    <div class="notice notice--success" role="status">
        <p><strong>Preferences saved.</strong> You can change them at any time in Settings.</p>
    </div>
<?php endif; ?>

<?php if ($showWelcome): ?>
    <section class="welcome-panel" aria-labelledby="welcome-title">
        <h2 id="welcome-title">First time here? Tell us about you</h2>
        <p>
            One quick question. Your answer stays on this device in the
            <code>usertype</code> cookie, and you can change it at any time in
            <a href="<?= e(Router::url('settings')) ?>">Settings</a>.
        </p>

        <?php if ($welcomeError !== ''): ?>
            <div class="notice notice--error" role="alert">
                <p><strong>Could not save.</strong> <?= e($welcomeError) ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(Router::url('')) ?>" class="welcome-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="profile-form" value="1">

            <fieldset class="field">
                <legend>Which describes you best?</legend>
                <div class="chip-row" role="group" aria-label="Type of user">
                    <?php foreach (USER_TYPE_OPTIONS as $typeId => $type): ?>
                        <span class="chip">
                            <input type="radio" class="chip-input" id="welcome-<?= e($typeId) ?>"
                                   name="usertype" value="<?= e($typeId) ?>"
                                   <?= $profile['usertype'] === $typeId ? 'checked' : '' ?>>
                            <label class="chip-label" for="welcome-<?= e($typeId) ?>">
                                <?= e($type['label']) ?>
                            </label>
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="field__hint">
                    Student, JULAC partner library member, or guest. Saved as the
                    <code>usertype</code> cookie.
                </p>
            </fieldset>

            <button type="submit" class="btn btn-primary btn-large">Save my preference</button>
        </form>
    </section>
<?php endif; ?>

<section aria-labelledby="how-title" class="landing-section">
    <h2 id="how-title">How Smart Map Nav works</h2>

    <div class="card-grid">
        <article class="card">
            <h3>1. Pick a large icon</h3>
            <p>
                Eight oversized icons replace dense map labels. Each one says what it is,
                what accessibility it offers, and how far away the nearest example is.
            </p>
        </article>
        <article class="card">
            <h3>2. Check the details</h3>
            <p>
                A detail panel lists the nearest locations, the features available, and a
                single large “Navigate here” button.
            </p>
        </article>
        <article class="card">
            <h3>3. Follow a step-free route</h3>
            <p>
                The 3D campus map draws the route and the Directions panel lists every step.
            </p>
        </article>
    </div>

    <p class="cluster">
        <a class="btn btn-primary btn-large"
           href="<?= e(Router::url('help', ['focus' => 'manual'])) ?>#user-manual">
            Open the user manual
        </a>
    </p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
