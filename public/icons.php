<?php
/**
 * Epic 2.1 — Large-icon resource interface (live data).
 *
 * Author: David (with Grok Premium)
 *
 * What was done:
 *   - Groups: Accessibility, Transport, General. Step-free is a toggle, not a group.
 *   - Type chips appear immediately when a group is chosen; only that group's types show.
 *   - Step-free toggle greys out and clears group and type filters.
 *   - Apply button removed. Clear all sits with items-per-row on the right.
 *   - Floor filter: 3/F, 4/F, 5/F. Place tags (Library, AC1, …) show on the card only.
 *   - Cards are ordered by floor, then fewer parents, then node id.
 *   - Clicking a card opens MapNav3D with that node as the destination (?dest=id).
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

$page             = 'icons';
$page_title       = 'Campus resources';
$page_description = 'Browse campus resources by group and type.';
$page_scripts     = ['icons.js'];

$loaded    = LiveResources::load();
$resources = $loaded['resources'];
$dbError   = $loaded['ok'] ? null : $loaded['error'];

$validGroups = array_keys(Icons::GROUPS);
$selectedGroup = isset($_GET['group']) && is_string($_GET['group'])
    ? strtolower(trim($_GET['group']))
    : '';
if (!in_array($selectedGroup, $validGroups, true)) {
    $selectedGroup = '';
}

$groupTags = $selectedGroup !== '' ? array_keys(Icons::tagsInGroup($selectedGroup)) : [];

$selectedTag = isset($_GET['tag']) && is_string($_GET['tag'])
    ? strtolower(trim($_GET['tag']))
    : '';
if ($selectedTag !== '' && !in_array($selectedTag, $groupTags, true)) {
    $selectedTag = '';
}

$perRow = isset($_GET['per_row']) ? (int) $_GET['per_row'] : 3;
if (!in_array($perRow, [2, 3, 4, 5], true)) {
    $perRow = 3;
}

$floorOptions = array_values(LiveResources::FLOOR_LABELS);
$selectedFloor = isset($_GET['floor']) && is_string($_GET['floor']) ? trim($_GET['floor']) : '';
if (!in_array($selectedFloor, $floorOptions, true)) {
    $selectedFloor = '';
}

$stepFreeOnly = isset($_GET['step_free'])
    && in_array((string) $_GET['step_free'], ['1', 'true', 'on'], true);

$matches = static function (array $resource) use ($selectedTag, $selectedGroup, $stepFreeOnly, $selectedFloor): bool {
    if ($stepFreeOnly) {
        return !empty($resource['step_free']);
    }
    if ($selectedFloor !== '' && ($resource['floor'] ?? '') !== $selectedFloor) {
        return false;
    }
    if ($selectedGroup !== '' && !in_array($selectedGroup, $resource['groups'] ?? [], true)) {
        return false;
    }
    if ($selectedTag !== '' && $resource['primary_tag'] !== $selectedTag) {
        return false;
    }
    return true;
};

$visible = array_values(array_filter($resources, $matches));

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Campus resources</h1>
    <p class="lede">
        Pick a group to see its types immediately. Step-free shows lifts only and locks the other filters.
    </p>
</div>

<?php if ($dbError !== null): ?>
    <div class="notice notice--error" role="alert">
        <strong>Database unavailable.</strong>
        <p>Could not load live resources: <?= e($dbError) ?></p>
    </div>
<?php endif; ?>

<div class="icons-layout" id="icons-layout">
    <div class="icons-main">
        <form class="filter-bar" id="filter-form" method="get"
              action="<?= e(Router::url('icons')) ?>" aria-label="Filter resources">

            <div class="filter-bar__row">
                <div class="switch-field">
                    <input type="checkbox" id="step-free" name="step_free" value="1"
                           class="switch-input" <?= $stepFreeOnly ? 'checked' : '' ?>>
                    <label class="switch-label" for="step-free">
                        <span class="switch-track" aria-hidden="true"><span class="switch-thumb"></span></span>
                        <span class="switch-text">Show only step-free (lifts)</span>
                    </label>
                </div>
            </div>

            <fieldset class="chip-fieldset" id="group-fieldset" <?= $stepFreeOnly ? 'disabled' : '' ?>>
                <legend>Group</legend>
                <div class="chip-row" role="group" aria-label="Resource group">
                    <span class="chip">
                        <input type="radio" class="chip-input" name="group" id="group-all" value=""
                               <?= !$stepFreeOnly && $selectedGroup === '' ? 'checked' : '' ?>>
                        <label class="chip-label" for="group-all">All</label>
                    </span>
                    <?php foreach (Icons::GROUPS as $groupId => $groupLabel): ?>
                        <span class="chip">
                            <input type="radio" class="chip-input" name="group"
                                   id="group-<?= e($groupId) ?>" value="<?= e($groupId) ?>"
                                   <?= !$stepFreeOnly && $selectedGroup === $groupId ? 'checked' : '' ?>>
                            <label class="chip-label" for="group-<?= e($groupId) ?>"><?= e($groupLabel) ?></label>
                        </span>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="chip-fieldset" id="type-fieldset" <?= ($stepFreeOnly || $selectedGroup === '') ? 'hidden' : '' ?>>
                <legend>Type</legend>
                <div class="chip-row" role="group" aria-label="Resource type">
                    <span class="chip" data-type-chip="all">
                        <input type="radio" class="chip-input" name="tag" id="tag-all" value=""
                               <?= $selectedTag === '' ? 'checked' : '' ?>>
                        <label class="chip-label" for="tag-all">All</label>
                    </span>
                    <?php foreach (Icons::TAGS as $tagId => $tag): ?>
                        <span class="chip" data-type-chip="<?= e($tagId) ?>"
                              data-groups="<?= e(implode(' ', $tag['groups'])) ?>"
                              <?= ($selectedGroup === '' || !in_array($selectedGroup, $tag['groups'], true)) ? 'hidden' : '' ?>>
                            <input type="radio" class="chip-input" name="tag"
                                   id="tag-<?= e($tagId) ?>" value="<?= e($tagId) ?>"
                                   <?= $selectedTag === $tagId ? 'checked' : '' ?>>
                            <label class="chip-label" for="tag-<?= e($tagId) ?>"><?= e($tag['label']) ?></label>
                        </span>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="chip-fieldset" id="floor-fieldset" <?= $stepFreeOnly ? 'disabled' : '' ?>>
                <legend>Floor</legend>
                <div class="chip-row" role="group" aria-label="Floor">
                    <span class="chip">
                        <input type="radio" class="chip-input" name="floor" id="floor-all" value=""
                               <?= !$stepFreeOnly && $selectedFloor === '' ? 'checked' : '' ?>>
                        <label class="chip-label" for="floor-all">All</label>
                    </span>
                    <?php foreach ($floorOptions as $floorLabel): ?>
                        <?php $floorId = 'floor-' . strtolower(str_replace('/', '', $floorLabel)); ?>
                        <span class="chip">
                            <input type="radio" class="chip-input" name="floor"
                                   id="<?= e($floorId) ?>" value="<?= e($floorLabel) ?>"
                                   <?= !$stepFreeOnly && $selectedFloor === $floorLabel ? 'checked' : '' ?>>
                            <label class="chip-label" for="<?= e($floorId) ?>"><?= e($floorLabel) ?></label>
                        </span>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="filter-bar__actions">
                <a class="btn btn-secondary btn-small" id="clear-filters" href="<?= e(Router::url('icons')) ?>">Clear all</a>
                <div class="per-row-wrap">
                    <label for="per-row">Items per row</label>
                    <select id="per-row" name="per_row" class="per-row-select">
                        <?php foreach ([2, 3, 4, 5] as $n): ?>
                            <option value="<?= $n ?>" <?= $perRow === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <p id="filter-status" class="filter-status" aria-live="polite">
            <?= count($visible) ?> of <?= count($resources) ?> resources shown
        </p>

        <ul class="resource-grid" id="resource-grid" style="--cols: <?= (int) $perRow ?>;">
            <?php foreach ($resources as $resource): ?>
                <?php $show = $matches($resource); ?>
                <li class="resource-card"
                    data-groups="<?= e(implode(' ', $resource['groups'] ?? [])) ?>"
                    data-tag="<?= e((string) $resource['primary_tag']) ?>"
                    data-floor="<?= e((string) ($resource['floor'] ?? '')) ?>"
                    data-step-free="<?= !empty($resource['step_free']) ? '1' : '0' ?>"
                    <?= $show ? '' : 'hidden' ?>>
                    <a class="resource-card__link"
                       href="<?= e(map3d_url(['dest' => (string) $resource['id']])) ?>">
                    <span class="resource-card__icon" aria-hidden="true">
                        <?= Icons::iconSvg((string) $resource['icon'], true) ?>
                    </span>
                    <strong class="resource-card__name"><?= e($resource['name']) ?></strong>
                    <p class="resource-card__meta">
                        <?php if (!empty($resource['step_free'])): ?>
                            <span class="flag flag--go">Step-free</span>
                        <?php endif; ?>
                        <span class="flag"><?= e(Icons::tagLabel((string) $resource['primary_tag'])) ?></span>
                        <?php if (($resource['floor'] ?? '') !== ''): ?>
                            <span class="flag"><?= e((string) $resource['floor']) ?></span>
                        <?php endif; ?>
                        <?php foreach ($resource['place_tags'] ?? [] as $place): ?>
                            <span class="flag"><?= e((string) $place) ?></span>
                        <?php endforeach; ?>
                    </p>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($dbError === null): ?>
            <p id="empty-results" <?= $visible === [] ? '' : 'hidden' ?>>
                <strong>No resources match those filters.</strong>
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
.resource-grid {
    display: grid;
    grid-template-columns: repeat(var(--cols, 3), minmax(0, 1fr));
    gap: 1rem;
    list-style: none;
    padding: 0;
    margin: 1rem 0 2rem;
}
.resource-card {
    border: 1px solid var(--color-border, #6f7c88);
    border-radius: 12px;
    padding: 1rem;
    background: var(--color-surface, #fff);
    min-height: 140px;
}
#group-fieldset:disabled,
#group-fieldset.is-locked,
#floor-fieldset:disabled,
#floor-fieldset.is-locked,
#type-fieldset.is-locked {
    opacity: 0.4;
    filter: grayscale(0.5);
}
#group-fieldset:disabled .chip-label,
#group-fieldset.is-locked .chip-label,
#floor-fieldset:disabled .chip-label,
#floor-fieldset.is-locked .chip-label,
#type-fieldset.is-locked .chip-label {
    pointer-events: none;
    cursor: not-allowed;
}
.filter-bar__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 0.75rem;
}
.per-row-wrap {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.resource-card__link {
    display: block;
    color: inherit;
    text-decoration: none;
    min-height: 100%;
}
.resource-card:hover,
.resource-card:focus-within {
    outline: 3px solid var(--color-focus, #1d4e89);
    outline-offset: 2px;
}
.resource-card__name { display: block; margin: 0.5rem 0 0; }
.resource-card__meta { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; margin: 0.5rem 0 0; }
.per-row-select { min-height: 48px; padding: 0 0.75rem; font-size: 1rem; }
@media (max-width: 700px) {
    .resource-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

<script>
(function () {
    var form = document.getElementById('filter-form');
    var stepFree = document.getElementById('step-free');
    var groupField = document.getElementById('group-fieldset');
    var typeField = document.getElementById('type-fieldset');
    var floorField = document.getElementById('floor-fieldset');
    var statusEl = document.getElementById('filter-status');
    var emptyEl = document.getElementById('empty-results');
    var grid = document.getElementById('resource-grid');
    var perRow = document.getElementById('per-row');
    if (!form || !grid) { return; }

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.resource-card'));
    var typeChips = Array.prototype.slice.call(typeField.querySelectorAll('[data-type-chip]'));
    var total = cards.length;

    function checkedValue(name) {
        var el = form.querySelector('input[name="' + name + '"]:checked');
        return el ? el.value : '';
    }

    function showTypesFor(group) {
        typeField.hidden = group === '';
        typeChips.forEach(function (chip) {
            if (chip.getAttribute('data-type-chip') === 'all') {
                chip.hidden = false;
                return;
            }
            var groups = (chip.getAttribute('data-groups') || '').split(/\s+/);
            var match = group !== '' && groups.indexOf(group) !== -1;
            chip.hidden = !match;
            if (!match) {
                var input = chip.querySelector('input');
                if (input && input.checked) { input.checked = false; }
            }
        });
    }

    function lockOthers(locked) {
        groupField.disabled = locked;
        groupField.classList.toggle('is-locked', locked);
        typeField.classList.toggle('is-locked', locked);
        if (floorField) {
            floorField.disabled = locked;
            floorField.classList.toggle('is-locked', locked);
        }
        if (locked) {
            form.querySelectorAll('input[name="group"], input[name="tag"], input[name="floor"]').forEach(function (input) {
                input.checked = false;
            });
            typeField.hidden = true;
        }
    }

    function applyView() {
        var step = stepFree.checked;
        var group = step ? '' : checkedValue('group');
        var tag = step ? '' : checkedValue('tag');
        var floor = step ? '' : checkedValue('floor');
        var shown = 0;

        cards.forEach(function (card) {
            var groups = (card.getAttribute('data-groups') || '').split(/\s+/);
            var ok = true;
            if (step) {
                ok = card.getAttribute('data-step-free') === '1';
            } else {
                if (group && groups.indexOf(group) === -1) { ok = false; }
                if (tag && card.getAttribute('data-tag') !== tag) { ok = false; }
                if (floor && card.getAttribute('data-floor') !== floor) { ok = false; }
            }
            card.hidden = !ok;
            if (ok) { shown++; }
        });

        if (statusEl) {
            statusEl.textContent = shown + ' of ' + total + ' resources shown';
        }
        if (emptyEl) { emptyEl.hidden = shown !== 0; }
    }

    function syncUrl() {
        var params = new URLSearchParams();
        if (stepFree.checked) {
            params.set('step_free', '1');
        } else {
            var group = checkedValue('group');
            var tag = checkedValue('tag');
            var floor = checkedValue('floor');
            if (group) { params.set('group', group); }
            if (tag) { params.set('tag', tag); }
            if (floor) { params.set('floor', floor); }
        }
        if (perRow && perRow.value && perRow.value !== '3') {
            params.set('per_row', perRow.value);
        }
        var query = params.toString();
        history.replaceState(null, '', query ? ('?' + query) : location.pathname);
    }

    stepFree.addEventListener('change', function () {
        lockOthers(stepFree.checked);
        if (!stepFree.checked) {
            var allGroup = document.getElementById('group-all');
            var allTag = document.getElementById('tag-all');
            var allFloor = document.getElementById('floor-all');
            if (allGroup) { allGroup.checked = true; }
            if (allTag) { allTag.checked = true; }
            if (allFloor) { allFloor.checked = true; }
            showTypesFor('');
        }
        applyView();
        syncUrl();
    });

    form.querySelectorAll('input[name="group"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (stepFree.checked) { return; }
            var allTag = document.getElementById('tag-all');
            if (allTag) { allTag.checked = true; }
            showTypesFor(input.value);
            applyView();
            syncUrl();
        });
    });

    form.querySelectorAll('input[name="tag"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (stepFree.checked) { return; }
            applyView();
            syncUrl();
        });
    });

    form.querySelectorAll('input[name="floor"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (stepFree.checked) { return; }
            applyView();
            syncUrl();
        });
    });

    if (perRow) {
        perRow.addEventListener('change', function () {
            grid.style.setProperty('--cols', perRow.value);
            syncUrl();
        });
    }

    if (stepFree.checked) {
        lockOthers(true);
    } else {
        showTypesFor(checkedValue('group'));
    }
    applyView();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
