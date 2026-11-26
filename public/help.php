<?php
/**
 * Epic 2.3 — Help centre.
 *
 * Emergency contact block, FAQ accordion (<details>/<summary>: keyboard and
 * screen-reader support for free), step-by-step guides, a guarded contact form
 * and the accessibility statement the footer links to.
 *
 * The contact form only logs the message — there is no mail server in a
 * coursework prototype. That limitation is stated on the page itself.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

$page         = 'help';
$page_title   = 'Help centre';
$page_scripts = [];

$focus = isset($_GET['focus']) && is_string($_GET['focus'])
    ? (string) preg_replace('/[^a-z_-]/', '', strtolower((string) $_GET['focus']))
    : '';

$formStatus = null;   // ['type' => 'success'|'error', 'message' => string]
$old        = ['name' => '', 'email' => '', 'message' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old['name']    = trim((string) ($_POST['name'] ?? ''));
    $old['email']   = trim((string) ($_POST['email'] ?? ''));
    $old['message'] = trim((string) ($_POST['message'] ?? ''));

    /* Honeypot: a real person never fills a hidden field. Bots do. */
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $formStatus = ['type' => 'success', 'message' => 'Thank you — your message has been logged.'];
    } elseif (!csrf_valid(isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : null)) {
        $formStatus = ['type' => 'error', 'message' => 'Your form session expired. Please send it again.'];
    } else {
        $errors = [];

        if ($old['name'] === '') {
            $errors[] = 'Please tell us your name.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address so we can reply.';
        }
        if (mb_strlen($old['message']) < 10) {
            $errors[] = 'Please describe what you need in at least 10 characters.';
        }

        if ($errors !== []) {
            $formStatus = ['type' => 'error', 'message' => implode(' ', $errors)];
        } else {
            /* Mock handler: log it, never echo unescaped input back. */
            error_log(sprintf(
                '[SmartMapNav contact] %s <%s> %s',
                $old['name'],
                $old['email'],
                mb_substr($old['message'], 0, 300)
            ));

            $formStatus = [
                'type'    => 'success',
                'message' => 'Thank you, ' . $old['name'] . '. Your message has been logged. '
                    . 'In the real system this would reach the help desk by email.',
            ];
            $old = ['name' => '', 'email' => '', 'message' => ''];
        }
    }
}

$faqs = [
    [
        'q' => 'How do I find a step-free route?',
        'a' => 'Open the map, set the route type to Step-free — lifts only, then choose your '
            . 'start point and destination. Green lines are step-free. Every route is also printed '
            . 'as plain-text steps in the route sidebar.',
    ],
    [
        'q' => 'Where are the nearest accessible toilets?',
        'a' => 'Open Resources and choose Accessible Toilets. The panel lists the two closest, '
            . 'with distances, and a Navigate here button that opens the route with step-free routing on.',
    ],
    [
        'q' => 'Can I use this with a screen reader?',
        'a' => 'Yes. Every control has a name, the icon grid is a single tab stop navigated with '
            . 'arrow keys, status changes are announced through a polite live region, and the map has '
            . 'a complete plain-text alternative.',
    ],
    [
        'q' => 'How do I make the text bigger?',
        'a' => 'Press the Accessibility button in the header and turn on Large text, or open Settings '
            . 'and use the slider for 100, 125, 150 or 200 percent. The layout reflows rather than clipping.',
    ],
    [
        'q' => 'What do the coloured flags mean?',
        'a' => 'Green Step-free means the whole resource is reachable without stairs. '
            . 'Amber Partly step-free means some parts of that resource are not — the detail panel explains which.',
    ],
    [
        'q' => 'Is my location tracked?',
        'a' => 'No. This prototype uses a fixed demo position and stores nothing outside your own browser '
            . 'and session. There is no account and no analytics.',
    ],
];

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Help centre</h1>
    <p class="lede">
        Emergency contacts, answers to the questions people ask most, and a form to reach
        the accessibility team.
    </p>
</div>

<section class="emergency-block" id="emergency" aria-labelledby="emergency-title"
         <?= $focus === 'emergency' ? 'tabindex="-1"' : '' ?>>
    <h2 id="emergency-title">Need help now?</h2>
    <p>
        If you are lost, unwell or stranded, call <?= e(EMERGENCY_LABEL) ?>.
        Tell them the building code on the nearest sign and which entrance you are at.
    </p>
    <a class="emergency-phone" href="tel:<?= e(EMERGENCY_TEL) ?>"><?= e(EMERGENCY_PHONE) ?></a>
    <p class="cluster">
        <a class="btn btn-emergency btn-large" href="tel:<?= e(EMERGENCY_TEL) ?>">
            Call for navigation help
        </a>
        <a class="btn btn-secondary btn-large" href="<?= e(map3d_url()) ?>">
            Back to the map
        </a>
    </p>
    <p class="muted">Text relay: dial 18001 before the number. The line is staffed 24 hours.</p>
</section>

<section id="user-manual" class="landing-section" aria-labelledby="manual-title"
         <?= $focus === 'manual' ? 'tabindex="-1"' : '' ?>>
    <h2 id="manual-title">User manual</h2>
    <p class="muted">
        Short guides for every part of Smart Map Nav. The floating
        <strong>Start navigating</strong> button is always on screen and opens the
        3D campus map.
    </p>

    <div class="faq">
        <details class="faq__item" open>
            <summary class="faq__question">Use the 3D campus map</summary>
            <div class="faq__answer">
                <ol>
                    <li>Press the floating <strong>Start navigating</strong> button or
                        <strong>Map</strong> in the main menu.</li>
                    <li>Type a <strong>start</strong> and a <strong>destination</strong>, then choose
                        <strong>Fastest</strong>, <strong>Smooth</strong> (no stairs) or
                        <strong>Step-Free</strong> (lifts only).</li>
                    <li>Press <strong>Find Route</strong> and read the <strong>Directions</strong>
                        panel. Use <strong>Next Step</strong> as you walk.</li>
                    <li>Filter floors with <strong>All / F3 / F4 / F5</strong>. Your role
                        (Student, JULAC or Guest) controls which rooms are shown.</li>
                    <li>On a phone, press <strong>Enable</strong> for motion sensors,
                        <strong>Scan QR</strong> to set your exact position, and
                        <strong>Calibrate</strong> if the arrow points the wrong way.</li>
                    <li>Press <strong>Exit</strong> (bottom-left) to come back here.</li>
                </ol>
                <p class="hint-extra">
                    The 3D map needs an internet connection and a WebGL-capable browser.
                </p>
            </div>
        </details>
        <details class="faq__item">
            <summary class="faq__question">Navigate with large icons</summary>
            <div class="faq__answer">
                <ol>
                    <li>Choose <strong>Resources</strong> in the main menu.</li>
                    <li>Turn on <strong>Show only step-free</strong> if stairs are not an option.</li>
                    <li>Add a filter chip such as <strong>Wheelchair</strong>.</li>
                    <li>Press a tile to open its detail panel.</li>
                    <li>Press <strong>Navigate here</strong> to draw the route.</li>
                </ol>
            </div>
        </details>
        <details class="faq__item">
            <summary class="faq__question">Set your preferences</summary>
            <div class="faq__answer">
                <ol>
                    <li>On your first visit, choose the type of user (student, JULAC or guest).
                        It is stored in the <code>usertype</code> cookie.</li>
                    <li>Open <strong>Settings</strong> to choose the text size, icon size,
                        <strong>default route type</strong> and vibration. Every change saves
                        automatically — there is no Save button.</li>
                    <li>The default route type (Fastest, Smoothest or Step-Free) is also used
                        when the 3D map opens.</li>
                    <li>Vibration is stored in the <code>vibration</code> cookie.</li>
                </ol>
            </div>
        </details>
        <details class="faq__item">
            <summary class="faq__question">Enable high contrast</summary>
            <div class="faq__answer">
                <ol>
                    <li>Press the <strong>Accessibility</strong> button in the header.</li>
                    <li>Turn on <strong>High contrast</strong>.</li>
                    <li>The change is announced and saved on this device.</li>
                    <li>Open <strong>Settings</strong> for text size, icon size and vibration.</li>
                </ol>
            </div>
        </details>
    </div>
</section>

<section aria-labelledby="faq-title" class="landing-section">
    <h2 id="faq-title">Frequently asked questions</h2>

    <div class="faq">
        <?php foreach ($faqs as $index => $faq): ?>
            <details class="faq__item" <?= $index === 0 ? 'open' : '' ?>>
                <summary class="faq__question"><?= e($faq['q']) ?></summary>
                <div class="faq__answer"><p><?= e($faq['a']) ?></p></div>
            </details>
        <?php endforeach; ?>
    </div>
</section>

<section aria-labelledby="contact-title" class="landing-section">
    <h2 id="contact-title">Contact the accessibility team</h2>
    <p>
        Tell us what went wrong or what you need. Fields marked “required” must be filled in.
        This prototype writes your message to the server log instead of sending email.
    </p>

    <?php if ($formStatus !== null): ?>
        <div class="notice <?= $formStatus['type'] === 'success' ? 'notice--success' : 'notice--error' ?>"
             role="<?= $formStatus['type'] === 'success' ? 'status' : 'alert' ?>">
            <p><?= e($formStatus['message']) ?></p>
        </div>
    <?php endif; ?>

    <form class="contact-form card" method="post" action="<?= e(Router::url('help')) ?>#contact-title">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="field">
            <label for="contact-name">Your name <span aria-hidden="true">*</span></label>
            <p class="field__hint" id="name-hint">Required. We only use it to reply to you.</p>
            <input type="text" id="contact-name" name="name" autocomplete="name" required
                   aria-required="true" aria-describedby="name-hint"
                   value="<?= e($old['name']) ?>">
        </div>

        <div class="field">
            <label for="contact-email">Email address <span aria-hidden="true">*</span></label>
            <p class="field__hint" id="email-hint">Required. For example name@example.com.</p>
            <input type="email" id="contact-email" name="email" autocomplete="email" required
                   aria-required="true" aria-describedby="email-hint"
                   value="<?= e($old['email']) ?>">
        </div>

        <div class="field">
            <label for="contact-message">What do you need? <span aria-hidden="true">*</span></label>
            <p class="field__hint" id="message-hint">
                Required. Include the building and floor if you can.
            </p>
            <textarea id="contact-message" name="message" rows="5" required
                      aria-required="true" aria-describedby="message-hint"><?= e($old['message']) ?></textarea>
        </div>

        <?php /* Honeypot: hidden from sight and from assistive tech. */ ?>
        <div class="visually-hidden" aria-hidden="true">
            <label for="contact-website">Leave this field empty</label>
            <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="btn btn-primary btn-large">Send message</button>
    </form>
</section>

<?php if ($focus !== ''): ?>
    <?php /* Deep links from the landing page and footer: help.php?focus=manual moves focus there. */ ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var target = document.getElementById(<?= json_encode($focus, JSON_THROW_ON_ERROR) ?>);
        if (!target) { return; }
        target.setAttribute('tabindex', '-1');
        target.focus();
        target.scrollIntoView({ block: 'start' });
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
