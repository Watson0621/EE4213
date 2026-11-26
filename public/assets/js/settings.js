/* =========================================================================
   Smart Map Nav — settings.js
   The Settings page needs three things from JS:
     1. Keep the live preview text in sync with the controls.
     2. Save every change automatically (localStorage + PHP session/cookies),
        with no Save button, and say so out loud.
     3. Reset everything back to the documented defaults.

   The visual changes themselves belong to accessibility.js, which owns the
   body classes — settings.js never touches them directly.
   ========================================================================= */

(function () {
    'use strict';

    var SMN = window.SMN = window.SMN || {};

    var form = document.getElementById('settings-form');
    if (!form) { return; }

    var statusEl = document.getElementById('settings-status');
    var scaleOutput = document.getElementById('preview-scale');
    var iconOutput = document.getElementById('preview-icon-size');
    var routeOutput = document.getElementById('preview-route');
    var resetBtn = document.getElementById('settings-reset');

    var LABELS = {
        large: 'Normal',
        xlarge: 'Large',
        max: 'Extra Large'
    };

    var SCALE_LABELS = {
        100: 'Normal',
        125: 'Large',
        150: 'Extra Large'
    };

    var ROUTE_LABELS = {
        step_free: 'Step-free (lifts only)',
        smoothest: 'Smoothest (no stairs)',
        fastest: 'Fastest'
    };

    function prefs() {
        return (SMN.prefs && SMN.prefs.get) ? SMN.prefs.get() : {};
    }

    function setStatus(message) {
        if (statusEl) { statusEl.textContent = message; }
    }

    /* ------------------------------------------------------------------
     * Live preview text
     * ---------------------------------------------------------------- */
    function refreshPreview() {
        var current = prefs();

        if (scaleOutput) {
            scaleOutput.textContent = SCALE_LABELS[Number(current.text_scale)] || 'Normal';
        }
        if (iconOutput) {
            iconOutput.textContent = LABELS[current.icon_size] || LABELS.large;
        }
        if (routeOutput) {
            routeOutput.textContent = ROUTE_LABELS[current.default_route] || ROUTE_LABELS.step_free;
        }
    }

    /* ------------------------------------------------------------------
     * Persistence
     * ---------------------------------------------------------------- */
    function payload() {
        var data = prefs();

        var range = document.getElementById('text-scale');
        var scaleChoice = document.querySelector('input[name="text_scale"]:checked');
        var iconSize = document.getElementById('icon-size');
        var routeType = document.getElementById('default-route');
        var language = document.getElementById('language');
        var userType = document.querySelector('input[name="usertype"]:checked');

        if (scaleChoice) {
            data.text_scale = Number(scaleChoice.value);
        } else if (range) {
            data.text_scale = Number(range.value);
        }
        if (iconSize) { data.icon_size = iconSize.value; }
        if (routeType) { data.default_route = routeType.value; }
        if (language) { data.language = language.value; }
        if (userType) { data.usertype = userType.value; }
        data.large_text = Number(data.text_scale) >= 125;

        return data;
    }

    function save() {
        var data = payload();
        var endpoint = document.body.getAttribute('data-prefs-endpoint');
        var meta = document.querySelector('meta[name="csrf-token"]');
        var token = meta ? meta.getAttribute('content') : '';

        /* Apply locally first so the user sees the result immediately. */
        Object.keys(data).forEach(function (key) {
            if (SMN.prefs && SMN.prefs.set) {
                SMN.prefs.set(key, data[key], { silent: true });
            }
        });

        try {
            window.localStorage.setItem('smartmapnav:prefs', JSON.stringify(data));
        } catch (err) { /* storage blocked: the session copy still saves */ }

        setStatus('Saving…');

        if (!endpoint || !window.fetch) {
            setStatus('Saved on this device. Server storage is unavailable in this browser.');
            SMN.announce('Settings saved on this device');
            return;
        }

        window.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            /* Survives an immediate navigation (e.g. straight to the 3D map). */
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token
            },
            body: JSON.stringify(data)
        })
            .then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                setStatus('Settings saved automatically. They apply to every page in this session.');
                SMN.announce('Settings saved');
            })
            .catch(function () {
                setStatus('Saved on this device only — the server could not be reached.');
                SMN.announce('Settings saved on this device only');
            });
    }

    /**
     * "About you" saves on its own.
     *
     * There is no Save button any more, so the user-type radios write the
     * cookie immediately (and tell the server, which keeps the PHP session in
     * sync). The value is also mirrored into the shared preferences object so
     * other pages and the 3D map can pick it up.
     */
    function saveProfile() {
        var userType = document.querySelector('input[name="usertype"]:checked');
        if (!userType) { return; }

        var data = { usertype: userType.value };

        /* Cookie first: instant, and survives a failed fetch. */
        try {
            var maxAge = ';path=/;max-age=' + (180 * 24 * 60 * 60) + ';SameSite=Lax';
            document.cookie = 'usertype=' + encodeURIComponent(userType.value) + maxAge;
        } catch (err) { /* cookies blocked: the server call below still saves */ }

        /* Keep the shared store (and accessibility.js's in-memory copy) in
           sync, so a later preference change cannot overwrite the role. */
        if (SMN.prefs && SMN.prefs.set) {
            SMN.prefs.set('usertype', userType.value, { silent: true });
        } else {
            try {
                var stored = JSON.parse(window.localStorage.getItem('smartmapnav:prefs') || '{}') || {};
                stored.usertype = userType.value;
                window.localStorage.setItem('smartmapnav:prefs', JSON.stringify(stored));
            } catch (err) { /* storage blocked: the cookie still works */ }
        }

        var endpoint = document.body.getAttribute('data-prefs-endpoint');
        var meta = document.querySelector('meta[name="csrf-token"]');
        var token = meta ? meta.getAttribute('content') : '';

        if (!endpoint || !window.fetch) { return; }

        window.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            /* Survives an immediate navigation (e.g. straight to the 3D map). */
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': token
            },
            body: JSON.stringify(data)
        })
            .then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                setStatus('Profile saved automatically.');
                SMN.announce('Profile saved');
            })
            .catch(function () {
                setStatus('Profile saved on this device only — the server could not be reached.');
                SMN.announce('Profile saved on this device only');
            });
    }

    function reset() {
        var defaults = (SMN.prefs && SMN.prefs.defaults)
            ? SMN.prefs.defaults
            : {
                high_contrast: false, large_text: false, text_scale: 100,
                vibration: false, sr_hints: true, icon_size: 'large',
                default_route: 'step_free', language: 'en'
            };

        Object.keys(defaults).forEach(function (key) {
            if (SMN.prefs && SMN.prefs.set) {
                SMN.prefs.set(key, defaults[key], { silent: true });
            }
            var control = document.querySelector('[data-pref="' + key + '"]');
            if (control) { control.checked = !!defaults[key]; }
        });

        var range = document.getElementById('text-scale');
        if (range) { range.value = String(defaults.text_scale); }

        var scaleChoice = document.querySelector(
            'input[name="text_scale"][value="' + defaults.text_scale + '"]'
        );
        if (scaleChoice) { scaleChoice.checked = true; }

        var iconSize = document.getElementById('icon-size');
        if (iconSize) { iconSize.value = defaults.icon_size; }

        var routeType = document.getElementById('default-route');
        if (routeType) { routeType.value = defaults.default_route; }

        var language = document.getElementById('language');
        if (language) { language.value = defaults.language; }

        refreshPreview();
        save();
        setStatus('Reset to the default accessibility settings.');
        SMN.announce('Settings reset to defaults');
    }

    /* ------------------------------------------------------------------
     * Wiring
     * ---------------------------------------------------------------- */
    function init() {
        refreshPreview();

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            save();
        });

        var range = document.getElementById('text-scale');
        if (range) {
            range.addEventListener('input', refreshPreview);
            range.addEventListener('change', refreshPreview);
        }

        Array.prototype.forEach.call(
            document.querySelectorAll(
                '#icon-size, #default-route, #language, [data-pref], input[name="text_scale"]'
            ),
            function (control) {
                control.addEventListener('change', refreshPreview);
            }
        );

        /* "About you" changes save straight away (no Save button). */
        Array.prototype.forEach.call(
            document.querySelectorAll('input[name="usertype"]'),
            function (radio) {
                radio.addEventListener('change', function () {
                    if (radio.checked) { saveProfile(); }
                });
            }
        );

        if (resetBtn) { resetBtn.addEventListener('click', reset); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
