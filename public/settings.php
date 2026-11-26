<?php
/**
 * Accessibility settings.
 *
 * Works three ways:
 *   1. JS on  -> every control saves instantly to localStorage + the session
 *                (accessibility.js), and the Save button confirms it loudly
 *   2. JS off -> the form POSTs to this page and PHP stores the prefs in the
 *                session, so the body classes are correct on the next paint
 *   3. Reset  -> both paths restore A11Y_DEFAULTS
 *
 * The "About you" group is different from the display settings: it writes the
 * `usertype` cookie (includes/config.php -> save_user_prefs) and the vibration
 * toggle mirrors itself into the `vibration` cookie.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

$page         = 'settings';
$page_title   = 'Accessibility settings';
$page_scripts = ['settings.js'];

$saved      = false;
$formError  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_valid(isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null)) {
        $formError = 'Your security token expired. Please try saving again.';
    } else {
        $updated = a11y_prefs();

        $updated['high_contrast'] = isset($_POST['high_contrast']);
        $updated['vibration']     = isset($_POST['vibration']);
        $updated['sr_hints']      = isset($_POST['sr_hints']);

        $scale = isset($_POST['text_scale']) ? (int) $_POST['text_scale'] : 100;
        $updated['text_scale'] = in_array($scale, TEXT_SCALE_OPTIONS, true) ? $scale : 100;

        /* "Large text" is derived: choosing 125 % or more is what makes the text
           large, so there is no separate switch that could get out of sync. */
        $updated['large_text'] = $updated['text_scale'] >= 125;

        $iconSize = isset($_POST['icon_size']) ? (string) $_POST['icon_size'] : 'large';
        $updated['icon_size'] = array_key_exists($iconSize, ICON_SIZE_OPTIONS) ? $iconSize : 'large';

        $routeType = isset($_POST['default_route']) ? (string) $_POST['default_route'] : 'step_free';
        $updated['default_route'] = array_key_exists($routeType, ROUTE_TYPE_OPTIONS) ? $routeType : 'step_free';

        $language = isset($_POST['language']) ? (string) $_POST['language'] : 'en';
        $updated['language'] = array_key_exists($language, LANGUAGE_OPTIONS) ? $language : 'en';

        $_SESSION['prefs'] = $updated;

        /* Mirror the vibration choice into its own cookie, then store the
           visitor profile (usertype) chosen in "About you". */
        set_user_cookie('vibration', $updated['vibration'] ? '1' : '0');

        $userType = isset($_POST['usertype']) && is_string($_POST['usertype']) ? $_POST['usertype'] : '';
        if (array_key_exists($userType, USER_TYPE_OPTIONS)) {
            save_user_prefs($userType);
        }

        $saved             = true;

        /* a11y_prefs() memoises; force a refresh so this response already
           renders the new body classes instead of the previous ones. */
        $prefs = a11y_prefs(true);
    }
}

if (!isset($prefs)) {
    $prefs = a11y_prefs();
}

$profile = user_prefs();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Accessibility settings</h1>
    <p class="lede">
        Everything here applies immediately and is remembered on this device and in
        your session. There is no Save button — every change takes effect as you make it.
    </p>
</div>

<?php if ($saved): ?>
    <div class="notice notice--success" role="status">
        <p><strong>Settings saved.</strong> They now apply to every page in this session.</p>
    </div>
<?php endif; ?>

<?php if ($formError !== ''): ?>
    <div class="notice notice--error" role="alert">
        <p><strong>Could not save.</strong> <?= e($formError) ?></p>
    </div>
<?php endif; ?>

<div class="settings-layout">

    <?php /* Preview comes first in the DOM so it sits ABOVE the controls on a phone. */ ?>
    <section class="settings-preview" aria-labelledby="preview-title">
        <h2 id="preview-title">Live preview</h2>
        <p class="muted">This sample updates as you change the settings.</p>

        <div class="preview-tile">
            <span class="tile__icon" aria-hidden="true">
                <?= MockData::iconSvg('toilets', true) ?>
            </span>
            <span class="tile__name">Accessible Toilets</span>
            <span class="tile__desc">
                Grab rails, emergency cord, turning space. Blue Zone, 3/F.
            </span>
        </div>

        <p class="preview-text">
            Turn left at the lift lobby and follow the tactile paving for 30 metres.
            The nearest accessible toilet is on your right.
        </p>

        <dl class="preview-meta">
            <div><dt>Text size</dt><dd id="preview-scale"><?= (int) $prefs['text_scale'] ?>%</dd></div>
            <div>
                <dt>Icon size</dt>
                <dd id="preview-icon-size"><?= e(ICON_SIZE_OPTIONS[$prefs['icon_size']]['label']) ?></dd>
            </div>
            <div>
                <dt>Default route</dt>
                <dd id="preview-route"><?= e(ROUTE_TYPE_OPTIONS[$prefs['default_route']]['label']) ?></dd>
            </div>
        </dl>

        <p class="hint-extra">
            Tip: if this text is still too small, raise the browser zoom as well —
            the layout reflows to 200&nbsp;% without horizontal scrolling.
        </p>
    </section>

    <form class="settings-form" id="settings-form" method="post"
          action="<?= e(Router::url('settings')) ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <p class="visually-hidden" role="status" aria-live="polite" id="settings-status"></p>

        <section class="settings-group" aria-labelledby="display-title">
            <h2 id="display-title">Display</h2>

            <div class="setting-row">
                <span class="setting-row__text">
                    <label class="setting-row__label" for="high_contrast">High contrast</label>
                    <span class="setting-row__help">
                        Black background, white text and yellow highlights (7:1 or better).
                    </span>
                </span>
                <span class="switch-field">
                    <input type="checkbox" class="switch-input" id="high_contrast"
                           name="high_contrast" data-pref="high_contrast"
                           data-pref-label="High contrast"
                           <?= $prefs['high_contrast'] ? 'checked' : '' ?>>
                    <label class="switch-label" for="high_contrast">
                        <span class="switch-track" aria-hidden="true">
                            <span class="switch-thumb"></span>
                        </span>
                    </label>
                </span>
            </div>

            <div class="setting-block">
                <span class="setting-row__text">
                    <span class="setting-row__label" id="text-size-label">Text size</span>
                    <span class="setting-row__help" id="text-size-help">
                        Choose Normal, Large or Extra Large. It is applied straight to the
                        page font size; the whole layout scales and nothing is cut off.
                    </span>
                </span>
                <fieldset class="text-size-field" aria-describedby="text-size-help">
                    <legend class="visually-hidden">Text size</legend>
                    <div class="chip-row" role="radiogroup" aria-labelledby="text-size-label">
                        <?php foreach (TEXT_SCALE_OPTIONS as $scale): ?>
                            <span class="chip">
                                <input type="radio" class="chip-input"
                                       id="text-scale-<?= (int) $scale ?>"
                                       name="text_scale" value="<?= (int) $scale ?>"
                                       data-text-scale
                                       <?= (int) $prefs['text_scale'] === (int) $scale ? 'checked' : '' ?>>
                                <label class="chip-label" for="text-scale-<?= (int) $scale ?>">
                                    <?= e(TEXT_SCALE_LABELS[(int) $scale] ?? (string) (int) $scale) ?>
                                </label>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>

            <div class="setting-row">
                <span class="setting-row__text">
                    <label class="setting-row__label" for="icon-size">Preferred icon size</label>
                    <span class="setting-row__help">
                        All options stay at or above the accessibility minimum.
                    </span>
                </span>
                <select id="icon-size" name="icon_size">
                    <?php foreach (ICON_SIZE_OPTIONS as $sizeId => $size): ?>
                        <option value="<?= e($sizeId) ?>" <?= $prefs['icon_size'] === $sizeId ? 'selected' : '' ?>>
                            <?= e($size['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="setting-row">
                <span class="setting-row__text">
                    <label class="setting-row__label" for="vibration">Vibration</label>
                    <span class="setting-row__help">
                        Short buzzes for route and status changes on this device.
                        Stored in the <code>vibration</code> cookie.
                    </span>
                </span>
                <span class="switch-field">
                    <input type="checkbox" class="switch-input" id="vibration"
                           name="vibration" data-pref="vibration"
                           data-pref-label="Vibration"
                           <?= $prefs['vibration'] ? 'checked' : '' ?>>
                    <label class="switch-label" for="vibration">
                        <span class="switch-track" aria-hidden="true">
                            <span class="switch-thumb"></span>
                        </span>
                    </label>
                </span>
            </div>

            <div class="setting-row">
                <span class="setting-row__text">
                    <label class="setting-row__label" for="sr_hints">Extra guidance text</label>
                    <span class="setting-row__help">
                        Shows the small tips under headings. Status messages are always announced.
                    </span>
                </span>
                <span class="switch-field">
                    <input type="checkbox" class="switch-input" id="sr_hints"
                           name="sr_hints" data-pref="sr_hints"
                           data-pref-label="Extra guidance"
                           <?= $prefs['sr_hints'] ? 'checked' : '' ?>>
                    <label class="switch-label" for="sr_hints">
                        <span class="switch-track" aria-hidden="true">
                            <span class="switch-thumb"></span>
                        </span>
                    </label>
                </span>
            </div>
        </section>

        <section class="settings-group" aria-labelledby="about-title">
            <h2 id="about-title">About you</h2>
            <p class="setting-row__help">
                The same question asked on your first visit. Your answer is stored in the
                <code>usertype</code> cookie on this device.
            </p>

            <fieldset class="field">
                <legend>Type of user</legend>
                <div class="chip-row" role="group" aria-label="Type of user">
                    <?php foreach (USER_TYPE_OPTIONS as $typeId => $type): ?>
                        <span class="chip">
                            <input type="radio" class="chip-input" id="usertype-<?= e($typeId) ?>"
                                   name="usertype" value="<?= e($typeId) ?>"
                                   <?= $profile['usertype'] === $typeId ? 'checked' : '' ?>>
                            <label class="chip-label" for="usertype-<?= e($typeId) ?>">
                                <?= e($type['label']) ?>
                            </label>
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="field__hint">Student, JULAC partner library member, or guest.</p>
            </fieldset>
        </section>

        <section class="settings-group" aria-labelledby="routing-title">
            <h2 id="routing-title">Routing</h2>

            <div class="setting-row">
                <span class="setting-row__text">
                    <label class="setting-row__label" for="default-route">Default route type</label>
                    <span class="setting-row__help">
                        Used when the map opens without a specific request.
                    </span>
                </span>
                <select id="default-route" name="default_route">
                    <?php foreach (ROUTE_TYPE_OPTIONS as $typeId => $type): ?>
                        <option value="<?= e($typeId) ?>" <?= $prefs['default_route'] === $typeId ? 'selected' : '' ?>>
                            <?= e($type['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="setting-row">
                <span class="setting-row__text">
                    <label class="setting-row__label" for="language">Language</label>
                    <span class="setting-row__help">
                        English is complete; 繁體中文 is a placeholder for the next iteration.
                    </span>
                </span>
                <select id="language" name="language">
                    <?php foreach (LANGUAGE_OPTIONS as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $prefs['language'] === $code ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="notice notice--info">
                <strong>Placeholder.</strong> Only English strings exist in this prototype.
                Choosing 繁體中文 is stored, but the interface is not translated yet.
            </p>
        </section>

        <p class="settings-autosave">
            <strong>Changes save automatically.</strong> Every switch, size and choice
            applies straight away on this device and in your session — there is no
            Save button.
        </p>

        <div class="settings-actions">
            <button type="button" class="btn btn-secondary btn-large" id="settings-reset">
                Reset to defaults
            </button>
            <noscript>
                <button type="submit" class="btn btn-primary btn-large">
                    Save settings
                </button>
            </noscript>
        </div>

        <p class="hint-extra">
            Tip: the header <strong>Accessibility</strong> button opens the two most
            used options — high contrast and large text — from any page.
        </p>

    </form>

</div><!-- /.settings-layout -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
