/* =========================================================================
   Smart Map Nav — accessibility.js
   The preference engine. One source of truth for high contrast, text size,
   reduced motion, screen-reader hints, icon size, default route type and
   language.

   Persistence is belt-and-braces:
     * localStorage  -> instant, per device
     * api/prefs.php -> PHP session, so the server can render the right
                        classes on the next load (no flash of wrong theme)

   Every change is announced through the shared live region.
   ========================================================================= */

(function () {
    'use strict';

    var SMN = window.SMN = window.SMN || {};

    var STORAGE_KEY = 'smartmapnav:prefs';
    var SCALES = [100, 125, 150];

    /* Friendly names used in announcements. */
    var SIZE_LABELS = { 100: 'Normal', 125: 'Large', 150: 'Extra Large' };
    var ICON_LABELS = { large: 'Normal', xlarge: 'Large', max: 'Extra Large' };

    var DEFAULTS = {
        high_contrast: false,
        large_text: false,
        text_scale: 100,
        vibration: false,
        sr_hints: true,
        icon_size: 'large',
        default_route: 'step_free',
        language: 'en'
    };

    /**
     * Read the state the SERVER rendered into <body>.
     *
     * Why: PHP renders classes from the session so a high-contrast user never
     * sees a white flash. If localStorage is empty (first visit, new device,
     * private mode) we must adopt those classes instead of overwriting them
     * with hard-coded defaults — otherwise the server's work is undone on the
     * first script tick and an in-app "high contrast" choice appears to revert.
     */
    function readServerState() {
        var classes = document.body.classList;
        var classList = ' ' + document.body.className + ' ';
        var scaleMatch = classList.match(/text-scale-(\d{3})/);
        var sizeMatch = classList.match(/icon-size-([a-z]+)/);
        var serverScale = scaleMatch ? Number(scaleMatch[1]) : 100;

        return Object.assign({}, DEFAULTS, {
            high_contrast: classes.contains('high-contrast'),
            /* Derived: 125 % or more IS large text (no separate toggle). */
            large_text: serverScale >= 125,
            vibration: classes.contains('vibration-on'),
            sr_hints: classes.contains('sr-hints'),
            text_scale: serverScale,
            icon_size: sizeMatch ? sizeMatch[1] : 'large',
            default_route: document.body.getAttribute('data-default-route') || 'step_free',
            language: document.documentElement.getAttribute('lang') === 'zh-HK' ? 'zh-HK' : 'en'
        });
    }

    function readPrefs() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            if (raw) {
                return Object.assign({}, DEFAULTS, JSON.parse(raw) || {});
            }
        } catch (err) {
            /* fall through to the server state */
        }

        return readServerState();
    }

    function writePrefs(prefs) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (err) {
            /* Private mode or quota exceeded: the session copy still works. */
        }
    }

    /**
     * Mirror a preference into its own long-lived cookie.
     *
     * Only `vibration` needs this: the cookie is the documented storage for the
     * toggle, and the server reads it on the next load to render the body class.
     */
    function writeCookie(name, value) {
        try {
            document.cookie = name + '=' + value +
                ';path=/;max-age=' + (180 * 24 * 60 * 60) + ';SameSite=Lax';
        } catch (err) {
            /* cookies disabled: localStorage and the session still work */
        }
    }

    /* ------------------------------------------------------------------
     * Server sync (best effort — never blocks the UI)
     * ---------------------------------------------------------------- */
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function syncServer(prefs) {
        var endpoint = document.body.getAttribute('data-prefs-endpoint');
        if (!endpoint || !window.fetch) { return; }

        window.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            /* Keep the request alive if the visitor navigates away at once. */
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken()
            },
            body: JSON.stringify(prefs)
        }).catch(function () {
            /* Offline demo: localStorage already holds the change. */
        });
    }

    /* ------------------------------------------------------------------
     * Apply to the DOM
     * ---------------------------------------------------------------- */
    function applyPrefs(prefs) {
        var body = document.body;
        if (!body) { return; }

        body.classList.toggle('high-contrast', !!prefs.high_contrast);
        body.classList.toggle('large-text', !!prefs.large_text);
        body.classList.toggle('vibration-on', !!prefs.vibration);
        body.classList.toggle('sr-hints', !!prefs.sr_hints);

        body.classList.remove('text-scale-100', 'text-scale-125', 'text-scale-150', 'text-scale-200');
        var scale = SCALES.indexOf(Number(prefs.text_scale)) > -1 ? Number(prefs.text_scale) : 100;
        body.classList.add('text-scale-' + scale);

        /* The percentage IS the font size: apply it straight to <body> instead
           of relying on a class rule or a separate "large text" switch. */
        body.style.fontSize = scale + '%';

        body.classList.remove('icon-size-large', 'icon-size-xlarge', 'icon-size-max');
        var size = ['large', 'xlarge', 'max'].indexOf(prefs.icon_size) > -1 ? prefs.icon_size : 'large';
        body.classList.add('icon-size-' + size);

        body.setAttribute('data-default-route', prefs.default_route || 'step_free');
        document.documentElement.setAttribute('lang', prefs.language === 'zh-HK' ? 'zh-HK' : 'en');
    }

    /* ------------------------------------------------------------------
     * Reflect state back into every control that represents it
     * ---------------------------------------------------------------- */
    function syncControls(prefs) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-pref]'), function (input) {
            var key = input.getAttribute('data-pref');
            if (Object.prototype.hasOwnProperty.call(prefs, key)) {
                input.checked = !!prefs[key];
            }
        });

        var range = document.getElementById('text-scale');
        if (range) {
            range.value = String(SCALES.indexOf(Number(prefs.text_scale)) > -1 ? prefs.text_scale : 100);
            range.setAttribute('aria-valuetext', (SIZE_LABELS[prefs.text_scale] || 'Normal') + ' text size');
        }

        /* Text size may be the legacy range slider or the Settings radio group. */
        Array.prototype.forEach.call(
            document.querySelectorAll('input[name="text_scale"]'),
            function (radio) {
                radio.checked = String(prefs.text_scale) === radio.value;
            }
        );
        var iconSize = document.getElementById('icon-size');
        if (iconSize) { iconSize.value = prefs.icon_size; }

        var routeType = document.getElementById('default-route');
        if (routeType) { routeType.value = prefs.default_route; }

        var language = document.getElementById('language');
        if (language) { language.value = prefs.language; }
    }

    /* ------------------------------------------------------------------
     * Public setter
     * ---------------------------------------------------------------- */
    var current = readPrefs();

    function setPref(key, value, options) {
        options = options || {};
        current[key] = value;

        /* Keep the toggle and the text-size choice consistent with each other. */
        if (key === 'large_text') {
            current.text_scale = value ? Math.max(Number(current.text_scale) || 100, 125) : 100;
        }
        if (key === 'text_scale') {
            current.large_text = Number(value) >= 125;
        }

        applyPrefs(current);
        syncControls(current);
        writePrefs(current);

        /* The vibration toggle is also stored in a cookie so the server can
           render body.vibration-on before the first paint next time. */
        if (key === 'vibration') {
            writeCookie('vibration', value ? '1' : '0');
        }

        /* A short buzz confirms the toggle when it is switched on. */
        if (key === 'vibration' && value && navigator.vibrate) {
            try { navigator.vibrate(40); } catch (err) { /* unsupported */ }
        }

        if (!options.silent) { syncServer(current); }
        if (!options.silent && options.message) { SMN.announce(options.message); }

        return current;
    }

    /* ------------------------------------------------------------------
     * Wiring
     * ---------------------------------------------------------------- */
    function init() {
        applyPrefs(current);
        syncControls(current);

        Array.prototype.forEach.call(document.querySelectorAll('[data-pref]'), function (input) {
            var key = input.getAttribute('data-pref');
            input.checked = !!current[key];

            input.addEventListener('change', function () {
                var label = input.getAttribute('data-pref-label') || key.replace(/_/g, ' ');
                var next = setPref(key, input.checked);
                var state = input.checked ? 'on' : 'off';

                if (key === 'large_text' || key === 'text_scale') {
                    SMN.announce(label + ' ' + state + '. Text size is now ' +
                        (SIZE_LABELS[next.text_scale] || 'Normal') + '.');
                } else {
                    SMN.announce(label + ' ' + state);
                }
            });
        });

        var range = document.getElementById('text-scale');
        if (range) {
            range.addEventListener('input', function () {
                setPref('text_scale', Number(range.value), { silent: true });
            });
            range.addEventListener('change', function () {
                var next = setPref('text_scale', Number(range.value));
                SMN.announce('Text size set to ' + (SIZE_LABELS[next.text_scale] || 'Normal'));
            });
        }

        /* Text-size radio group (Settings): one choice per option, no dragging. */
        Array.prototype.forEach.call(
            document.querySelectorAll('input[name="text_scale"]'),
            function (radio) {
                radio.addEventListener('change', function () {
                    if (!radio.checked) { return; }
                    var next = setPref('text_scale', Number(radio.value));
                    SMN.announce('Text size set to ' + (SIZE_LABELS[next.text_scale] || 'Normal'));
                });
            }
        );

        var iconSize = document.getElementById('icon-size');
        if (iconSize) {
            iconSize.addEventListener('change', function () {
                setPref('icon_size', iconSize.value);
                SMN.announce('Icon size set to ' + (ICON_LABELS[iconSize.value] || iconSize.value));
            });
        }

        var routeType = document.getElementById('default-route');
        if (routeType) {
            routeType.addEventListener('change', function () {
                setPref('default_route', routeType.value);
                SMN.announce('Default route type set to ' + routeType.options[routeType.selectedIndex].text);
            });
        }

        var language = document.getElementById('language');
        if (language) {
            language.addEventListener('change', function () {
                setPref('language', language.value);
                SMN.announce(language.value === 'zh-HK'
                    ? '介面語言將於下一版本支援繁體中文'
                    : 'Language set to English');
            });
        }
    }

    SMN.prefs = {
        get: function () { return Object.assign({}, current); },
        set: setPref,
        apply: applyPrefs,
        defaults: DEFAULTS
    };

    /**
     * Another tab changed a preference: mirror it here straight away.
     *
     * localStorage is shared by every page of the origin, so the `storage`
     * event is what makes a change on Settings reach an open page (or the 3D
     * map) without a reload.
     */
    window.addEventListener('storage', function (event) {
        if (event.key !== STORAGE_KEY || !event.newValue) { return; }
        try {
            current = Object.assign({}, DEFAULTS, JSON.parse(event.newValue) || {});
            applyPrefs(current);
            syncControls(current);
        } catch (err) { /* ignore malformed values */ }
    });

    /**
     * Haptic feedback, used by the map when a route loads.
     *
     * Does nothing unless the visitor switched Vibration on, and does nothing
     * on devices without a vibration motor (most desktops).
     */
    SMN.vibrate = function (pattern) {
        if (!current.vibration || !navigator.vibrate) { return; }
        try { navigator.vibrate(pattern || 40); } catch (err) { /* unsupported */ }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
