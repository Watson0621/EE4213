/* =========================================================================
   Smart Map Nav — icons.js   (Epic 2.1 — large-icon visual interface)

   Responsibilities
     1. Filter the tile grid instantly (tag chips + "step-free only"), with
        the same state mirrored into the URL query string.
     2. Roving tabindex: ONE tab stop for the whole grid, arrow keys move
        between tiles (WAI-ARIA grid pattern guidance).
     3. Open a detail panel (bottom sheet on phones, side panel on desktop),
        move focus into it, trap Tab while it is a modal sheet, and return
        focus to the tile that opened it.

   Everything degrades: without JS the form submits to the server, which
   renders the same filtered grid.
   ========================================================================= */

(function () {
    'use strict';

    var SMN = window.SMN = window.SMN || {};

    var grid = document.getElementById('icon-grid');
    if (!grid) { return; }

    var layout = document.getElementById('icons-layout');
    var panel = document.getElementById('icon-panel');
    var panelBody = document.getElementById('icon-panel-body');
    var panelClose = document.getElementById('icon-panel-close');
    var statusEl = document.getElementById('filter-status');
    var form = document.getElementById('filter-form');
    var stepFreeInput = document.getElementById('step-free');
    var chipInputs = Array.prototype.slice.call(
        document.querySelectorAll('#filter-form input[name="tags[]"]')
    );
    var clearLink = document.getElementById('clear-filters');

    var items = Array.prototype.slice.call(grid.querySelectorAll('.icon-grid__item'));
    var total = items.length;
    var releaseTrap = null;
    var returningFocus = null;

    /* ------------------------------------------------------------------
     * State <-> URL
     * ---------------------------------------------------------------- */
    function readStateFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var tagsParam = params.get('tags');
        var tags = tagsParam
            ? tagsParam.split(',').filter(Boolean)
            : params.getAll('tags[]').map(function (value) {
                return String(value).toLowerCase();
            });

        return {
            tags: tags,
            stepFree: params.get('step_free') === '1' || params.get('step_free') === 'true',
            open: params.get('open') || ''
        };
    }

    function writeStateToUrl(state, openId) {
        var params = new URLSearchParams(window.location.search);

        if (state.tags.length) {
            params.set('tags', state.tags.join(','));
        } else {
            params.delete('tags');
        }

        if (state.stepFree) {
            params.set('step_free', '1');
        } else {
            params.delete('step_free');
        }

        if (openId) {
            params.set('open', openId);
        } else {
            params.delete('open');
        }

        var query = params.toString();
        var url = window.location.pathname + (query ? '?' + query : '');
        window.history.replaceState({}, '', url);
    }

    function currentState() {
        return {
            tags: chipInputs.filter(function (input) { return input.checked; })
                .map(function (input) { return input.value; }),
            stepFree: !!(stepFreeInput && stepFreeInput.checked)
        };
    }

    /* ------------------------------------------------------------------
     * Filtering
     * ---------------------------------------------------------------- */
    function isVisible(item, state) {
        if (state.stepFree && item.getAttribute('data-step-free') !== '1') {
            return false;
        }

        var itemTags = (item.getAttribute('data-tags') || '').split(/\s+/).filter(Boolean);

        return state.tags.every(function (tag) {
            return itemTags.indexOf(tag) !== -1;
        });
    }

    function visibleTiles() {
        return items
            .filter(function (item) { return !item.hidden; })
            .map(function (item) { return item.querySelector('.tile'); });
    }

    function applyFilters(options) {
        options = options || {};
        var state = currentState();
        var shown = 0;

        items.forEach(function (item) {
            var visible = isVisible(item, state);
            item.hidden = !visible;
            if (visible) { shown += 1; }
        });

        /* Roving tabindex: exactly one tile sits in the tab order. */
        var tiles = visibleTiles();
        items.forEach(function (item) {
            var tile = item.querySelector('.tile');
            if (tile) { tile.setAttribute('tabindex', '-1'); }
        });
        if (tiles.length) { tiles[0].setAttribute('tabindex', '0'); }

        if (statusEl) {
            statusEl.textContent = shown + ' of ' + total + ' resources shown' +
                ((state.tags.length || state.stepFree) ? ' (filtered)' : '');
        }

        var noResults = document.getElementById('no-results');
        if (noResults) {
            noResults.hidden = shown > 0;
        }

        if (options.announce) {
            SMN.announce(shown + ' of ' + total + ' resources shown');
        }

        if (options.syncUrl !== false) {
            writeStateToUrl(
                state,
                (panel && !panel.hidden) ? panel.getAttribute('data-open-id') : ''
            );
        }

        return shown;
    }

    /* ------------------------------------------------------------------
     * Keyboard navigation (roving tabindex)
     * ---------------------------------------------------------------- */
    function columns() {
        var template = window.getComputedStyle(grid).gridTemplateColumns || '';
        var count = template.split(' ').filter(Boolean).length;
        return count > 0 ? count : 1;
    }

    function focusTile(index, tiles) {
        if (!tiles.length) { return; }
        var clamped = Math.max(0, Math.min(index, tiles.length - 1));
        tiles.forEach(function (tile) { tile.setAttribute('tabindex', '-1'); });
        tiles[clamped].setAttribute('tabindex', '0');
        tiles[clamped].focus();
    }

    function onGridKeydown(event) {
        var tiles = visibleTiles();
        if (!tiles.length) { return; }

        var current = tiles.indexOf(document.activeElement);

        /* Focus can land on a child of the button: walk up to the tile. */
        if (current === -1) {
            var tile = document.activeElement.closest
                ? document.activeElement.closest('.tile')
                : null;
            current = tile ? tiles.indexOf(tile) : 0;
        }

        var cols = columns();
        var next = null;

        switch (event.key) {
            case 'ArrowRight': next = current + 1; break;
            case 'ArrowLeft':  next = current - 1; break;
            case 'ArrowDown':  next = current + cols; break;
            case 'ArrowUp':    next = current - cols; break;
            case 'Home':       next = 0; break;
            case 'End':        next = tiles.length - 1; break;
            default: return;
        }

        if (next === null) { return; }

        event.preventDefault();
        focusTile(next, tiles);
    }

    /* ------------------------------------------------------------------
     * Detail panel
     * ---------------------------------------------------------------- */
    function isMobileSheet() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    function openPanel(iconId, trigger) {
        var template = document.getElementById('detail-' + iconId);
        if (!panel || !panelBody || !template) { return; }

        panelBody.innerHTML = '';
        panelBody.appendChild(template.content.cloneNode(true));
        panel.hidden = false;
        panel.setAttribute('data-open-id', iconId);

        if (layout) { layout.classList.add('has-panel'); }

        /* Lock the page behind the sheet only while it covers the screen. */
        if (isMobileSheet()) {
            panel.setAttribute('aria-modal', 'true');
            document.body.classList.add('panel-open');
            releaseTrap = SMN.trapFocus(panel);
        } else {
            panel.setAttribute('aria-modal', 'false');
        }

        Array.prototype.forEach.call(grid.querySelectorAll('.tile'), function (tile) {
            tile.setAttribute(
                'aria-pressed',
                tile.getAttribute('data-icon-id') === iconId ? 'true' : 'false'
            );
        });

        returningFocus = trigger || document.activeElement;

        var heading = panel.querySelector('.panel-title');
        if (heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus();
        }

        writeStateToUrl(currentState(), iconId);

        var name = heading ? heading.textContent.trim() : 'Details';

        /* Name the dialog after what it actually contains. The static
           aria-label is only a fallback for the moment before content loads. */
        panel.setAttribute('aria-label', name + ' details');

        SMN.announce(name + ' details opened. Press Escape to close.');
    }

    function closePanel() {
        if (!panel || panel.hidden) { return; }

        panel.hidden = true;
        panel.removeAttribute('data-open-id');
        panelBody.innerHTML = '';

        if (layout) { layout.classList.remove('has-panel'); }
        document.body.classList.remove('panel-open');

        if (releaseTrap) {
            releaseTrap();
            releaseTrap = null;
        }

        Array.prototype.forEach.call(grid.querySelectorAll('.tile'), function (tile) {
            tile.setAttribute('aria-pressed', 'false');
        });

        writeStateToUrl(currentState(), '');

        if (returningFocus && document.contains(returningFocus)) {
            returningFocus.focus();
        }

        SMN.announce('Details closed');
    }

    /* ------------------------------------------------------------------
     * Wiring
     * ---------------------------------------------------------------- */
    function init() {
        /* Restore filter state from the URL so a shared link still works. */
        var state = readStateFromUrl();
        chipInputs.forEach(function (input) {
            input.checked = state.tags.indexOf(input.value) !== -1;
        });
        if (stepFreeInput) { stepFreeInput.checked = state.stepFree; }

        applyFilters({ syncUrl: false });

        grid.addEventListener('keydown', onGridKeydown);

        Array.prototype.forEach.call(grid.querySelectorAll('.tile'), function (tile) {
            tile.addEventListener('click', function () {
                var id = tile.getAttribute('data-icon-id');
                if (panel && !panel.hidden && panel.getAttribute('data-open-id') === id) {
                    closePanel();
                } else {
                    openPanel(id, tile);
                }
            });
        });

        chipInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                applyFilters({ announce: true });
            });
        });

        if (stepFreeInput) {
            stepFreeInput.addEventListener('change', function () {
                applyFilters({ announce: true });
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                /* With JS the grid is already filtered, so stop the reload. */
                event.preventDefault();
                applyFilters({ announce: true });
            });
        }

        if (clearLink) {
            clearLink.addEventListener('click', function (event) {
                event.preventDefault();
                chipInputs.forEach(function (input) { input.checked = false; });
                if (stepFreeInput) { stepFreeInput.checked = false; }
                applyFilters({ announce: true });
                SMN.announce('Filters cleared');
            });
        }

        if (panelClose) {
            panelClose.addEventListener('click', closePanel);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { closePanel(); }
        });

        /* Deep link: icons.php?open=toilets opens that panel immediately. */
        if (state.open) {
            var deepTile = grid.querySelector('.tile[data-icon-id="' + state.open + '"]');
            if (deepTile && deepTile.closest('.icon-grid__item') && !deepTile.closest('.icon-grid__item').hidden) {
                openPanel(state.open, deepTile);
            }
        }

        /* If the viewport grows past the sheet breakpoint, drop the modal lock. */
        var mq = window.matchMedia('(max-width: 1023px)');
        var onChange = function () {
            if (!mq.matches && releaseTrap) {
                releaseTrap();
                releaseTrap = null;
                document.body.classList.remove('panel-open');
            }
        };
        if (mq.addEventListener) { mq.addEventListener('change', onChange); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
