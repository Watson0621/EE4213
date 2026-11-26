/* =========================================================================
   Smart Map Nav — main.js
   Shell behaviour shared by every page: navigation drawer, the header
   accessibility menu, the floating Start navigating button, the skip link and
   two small utilities the page scripts reuse.
   ========================================================================= */

(function () {
    'use strict';

    var SMN = window.SMN = window.SMN || {};

    /* ------------------------------------------------------------------
     * Live region announcements (WCAG 4.1.3 Status Messages).
     * Dynamic state changes are announced instead of moving focus, which
     * would disorient a screen-reader user mid-task.
     * ---------------------------------------------------------------- */
    SMN.announce = function (message) {
        var region = document.getElementById('live-region');
        if (!region) { return; }
        /* Clearing first makes a repeated message actually fire again. */
        region.textContent = '';
        window.setTimeout(function () {
            region.textContent = message;
        }, 80);
    };

    /* ------------------------------------------------------------------
     * Focus trap for sheets and dialogs. Returns a release function.
     * ---------------------------------------------------------------- */
    SMN.trapFocus = function (container) {
        var selector = 'a[href], button:not([disabled]), input:not([disabled]), ' +
            'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

        function onKeydown(event) {
            if (event.key !== 'Tab') { return; }

            var focusable = Array.prototype.filter.call(
                container.querySelectorAll(selector),
                function (el) {
                    return el.offsetWidth > 0 || el.offsetHeight > 0 || el === document.activeElement;
                }
            );

            if (focusable.length === 0) { return; }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        container.addEventListener('keydown', onKeydown);

        return function release() {
            container.removeEventListener('keydown', onKeydown);
        };
    };

    /* ------------------------------------------------------------------
     * Mobile navigation drawer
     * ---------------------------------------------------------------- */
    function initNavDrawer() {
        var toggle = document.getElementById('nav-toggle');
        var nav = document.getElementById('site-nav');

        if (!toggle || !nav) { return; }

        var mobileQuery = window.matchMedia('(max-width: 767px)');

        function setOpen(open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            nav.classList.toggle('is-collapsed', !open);
        }

        /* Progressive enhancement: the list is visible without JS, and we only
           collapse it once we know the viewport is small. */
        if (mobileQuery.matches) {
            setOpen(false);
        }

        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') !== 'true';
            setOpen(open);
            SMN.announce(open ? 'Main menu opened' : 'Main menu closed');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
                SMN.announce('Main menu closed');
            }
        });

        var onChange = function () {
            if (!mobileQuery.matches) {
                toggle.setAttribute('aria-expanded', 'false');
                nav.classList.remove('is-collapsed');
            }
        };

        if (mobileQuery.addEventListener) {
            mobileQuery.addEventListener('change', onChange);
        } else if (mobileQuery.addListener) {
            mobileQuery.addListener(onChange);
        }
    }

    /* ------------------------------------------------------------------
     * Header accessibility menu (the always-visible toggle)
     * ---------------------------------------------------------------- */
    function initA11yMenu() {
        var toggle = document.getElementById('a11y-toggle');
        var menu = document.getElementById('a11y-quick-menu');

        if (!toggle || !menu) { return; }

        toggle.addEventListener('click', function () {
            var open = menu.hasAttribute('hidden');
            if (open) {
                menu.removeAttribute('hidden');
            } else {
                menu.setAttribute('hidden', '');
            }
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            SMN.announce(open ? 'Accessibility options opened' : 'Accessibility options closed');

            if (open) {
                var first = menu.querySelector('input, a, button');
                if (first) { first.focus(); }
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !menu.hasAttribute('hidden')) {
                menu.setAttribute('hidden', '');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
                SMN.announce('Accessibility options closed');
            }
        });
    }

    /* ------------------------------------------------------------------
     * Floating "Start navigating" button.
     *
     * The link itself works without JS. This only adds a short vibration
     * when the visitor enabled Vibration, so the tap is confirmed without
     * looking at the screen.
     * ---------------------------------------------------------------- */
    function initFloatingCta() {
        var cta = document.querySelector('[data-cta-start]');
        if (!cta) { return; }

        cta.addEventListener('click', function () {
            if (SMN.vibrate) { SMN.vibrate(30); }
        });
    }

    /* ------------------------------------------------------------------
     * Skip link: move focus to <main>, not just the scroll position.
     * ---------------------------------------------------------------- */
    function initSkipLink() {
        var link = document.querySelector('.skip-link');
        var main = document.getElementById('main-content');

        if (!link || !main) { return; }

        link.addEventListener('click', function (event) {
            event.preventDefault();
            main.focus();
            main.scrollIntoView({ block: 'start', behavior: 'auto' });
            SMN.announce('Skipped to main content');
        });
    }

    /* ------------------------------------------------------------------
     * Mobile collapsible sheets. Collapsed only when JS is present, so the
     * content stays readable if a script fails to load.
     * ---------------------------------------------------------------- */
    function initSheets() {
        var sheets = document.querySelectorAll('[data-sheet]');

        Array.prototype.forEach.call(sheets, function (sheet) {
            var handle = sheet.querySelector('[data-sheet-handle]');
            if (!handle) { return; }

            if (window.matchMedia('(max-width: 1023px)').matches) {
                sheet.classList.add('is-collapsed');
                handle.setAttribute('aria-expanded', 'false');
            }

            handle.addEventListener('click', function () {
                var collapsed = sheet.classList.toggle('is-collapsed');
                handle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                SMN.announce(collapsed ? 'Route details collapsed' : 'Route details expanded');
            });
        });
    }

    function init() {
        initNavDrawer();
        initA11yMenu();
        initFloatingCta();
        initSkipLink();
        initSheets();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
