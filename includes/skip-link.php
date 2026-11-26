<?php
/**
 * Skip link — the first focusable element on every page (WCAG 2.4.1).
 *
 * It is visually hidden until focused, then appears as a large, high-contrast
 * button. `#main-content` carries tabindex="-1" so the target can actually
 * receive focus in every browser.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}
?>
<a class="skip-link" href="#main-content">Skip to main content</a>
