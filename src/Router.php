<?php
/**
 * Smart Map Nav — Router.
 *
 * WHY THIS EXISTS
 * ---------------
 * The brief asks for a router that "handles /, /map, /icons, /help, /settings"
 * and also for real files at public/index.php, public/map.php and so on.
 * Instead of choosing one and breaking the other, this class is the single
 * source of truth for the route table: it resolves the current page, builds
 * every internal URL (so links work under any base path) and renders the 404.
 *
 * The physical page files stay in public/ so the prototype runs on a plain
 * XAMPP install with zero rewrite configuration. public/.htaccess additionally
 * maps pretty URLs (/icons -> icons.php) when mod_rewrite is available.
 *
 * @package SmartMapNav
 */

declare(strict_types=1);

final class Router
{
    /**
     * Route table. Keys are the page slugs used in URLs and in `data-page`.
     *
     * @var array<string, array{file:string, title:string, nav:?string}>
     */
    private const PAGES = [
        ''         => ['file' => 'index.php',    'title' => 'Home',                   'nav' => null],
        'index'    => ['file' => 'index.php',    'title' => 'Home',                   'nav' => null],
        'icons'    => ['file' => 'icons.php',    'title' => 'Large-icon resources',   'nav' => 'icons'],
        'help'     => ['file' => 'help.php',     'title' => 'Help centre',            'nav' => 'help'],
        'settings' => ['file' => 'settings.php', 'title' => 'Accessibility settings', 'nav' => 'settings'],
    ];

    /** @return array<string, array{file:string, title:string, nav:?string}> */
    public static function pages(): array
    {
        return self::PAGES;
    }

    /** Does this slug exist in the route table? */
    public static function exists(string $page): bool
    {
        return isset(self::PAGES[self::normalise($page)]);
    }

    /** Human title for a slug, used for the <title> element. */
    public static function title(string $page): string
    {
        return self::PAGES[self::normalise($page)]['title'] ?? APP_NAME;
    }

    /**
     * Build an internal URL for a page slug.
     *
     * Router::url('icons', ['step_free' => 1])
     *   -> /CityUMapNav/public/icons.php?step_free=1
     *
     * @param array<string, mixed> $query
     */
    public static function url(string $page = '', array $query = []): string
    {
        $slug  = self::normalise($page);
        $entry = self::PAGES[$slug] ?? self::PAGES['index'];

        // "Home" gets a directory URL, everything else points at its file.
        $path = $slug === '' ? BASE_PATH . '/' : BASE_PATH . '/' . $entry['file'];

        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }

        return $path;
    }

    /**
     * Absolute URL for a file inside public/assets/.
     *
     * A `?v=<mtime>` suffix is appended so a changed stylesheet or script is
     * never served from a browser cache. This matters for the accessibility
     * layer in particular: an old JS file combined with a new CSS file can
     * otherwise make the text-size control appear to do nothing.
     */
    public static function asset(string $path): string
    {
        $relative = ltrim($path, '/');
        $url      = ASSET_URL . '/' . $relative;
        $file     = PUBLIC_ROOT . '/assets/' . $relative;

        if (is_file($file)) {
            $url .= '?v=' . filemtime($file);
        }

        return $url;
    }

    /**
     * Absolute URL for a mock API endpoint.
     *
     * @param array<string, mixed> $query
     */
    public static function api(string $endpoint, array $query = []): string
    {
        $file = str_ends_with($endpoint, '.php') ? $endpoint : $endpoint . '.php';
        $url  = API_URL . '/' . ltrim($file, '/');

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * Which page is rendering right now?
     *
     * Primary signal is the executing script name, which is reliable under
     * both `php -S` and Apache. `?page=` is honoured as a fallback for hosts
     * that funnel everything through index.php.
     */
    public static function current(): string
    {
        $script = basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));

        foreach (self::PAGES as $slug => $entry) {
            if ($slug === '' || $slug === 'index') {
                continue;
            }
            if ($entry['file'] === $script) {
                return $slug;
            }
        }

        if ($script === 'index.php') {
            $requested = $_GET['page'] ?? null;
            if (is_string($requested) && isset(self::PAGES[self::normalise($requested)])) {
                return self::normalise($requested);
            }

            return 'index';
        }

        return 'index';
    }

    /** Convenience for nav.php: is this slug the current page? */
    public static function isActive(string $page): bool
    {
        $slug    = self::normalise($page);
        $current = self::current();

        return self::PAGES[$slug]['nav'] !== null
            && self::PAGES[$slug]['nav'] === (self::PAGES[$current]['nav'] ?? null);
    }

    /**
     * Render a 404 page using the normal layout and stop.
     *
     * Never echoes the requested URI — reflecting unvalidated input is how
     * XSS bugs get in.
     */
    public static function notFound(): never
    {
        if (!headers_sent()) {
            http_response_code(404);
        }

        $page             = '404';
        $page_title       = 'Page not found';
        $page_description = 'That page does not exist. The links below go back to the map and the help centre.';

        require PROJECT_ROOT . '/includes/header.php';
        ?>
        <div class="page-head">
            <h1>Page not found</h1>
            <p class="lede">We could not find that page. It may have moved, or the link may be out of date.</p>
        </div>
        <div class="notice notice--warn" role="alert">
            <p><strong>Error 404.</strong> Nothing was deleted — this prototype only has five pages.</p>
        </div>
        <p class="cluster">
            <a class="btn btn-primary" href="<?= e(map3d_url()) ?>">Open the map</a>
            <a class="btn btn-secondary" href="<?= e(Router::url('icons')) ?>">Browse large icons</a>
            <a class="btn btn-ghost" href="<?= e(Router::url('help')) ?>">Help centre</a>
        </p>
        <?php
        require PROJECT_ROOT . '/includes/footer.php';
        exit;
    }

    /** Lowercase + trim + strip anything that is not a slug character. */
    private static function normalise(string $page): string
    {
        $page = strtolower(trim($page));
        $page = (string) preg_replace('/[^a-z0-9_-]/', '', $page);

        return $page;
    }
}
