<?php
/* Launch gate. The root .htaccess sends every page request here.
   - Before the launch time: visitors get the under-construction page with
     a countdown (HTTP 503 + Retry-After, so search engines come back later).
   - Signed-in admin users always see the real site, with a small notice.
   - From the launch time on: the requested page is served as usual.
   Launch time and on/off live in the admin panel: Settings → Launch. */

declare(strict_types=1);
require_once __DIR__ . '/admin/inc/bootstrap.php';
require_once __DIR__ . '/admin/inc/coming-soon.php';

function launch_state(): array {
    $at = (string)cfg('launch_at', '');
    $enabled = true;
    try {
        $at = (string)(setting('launch_at', '') ?: $at);
        $enabled = setting('launch_enabled', '1') === '1';
    } catch (Throwable $t) {
        // no database yet: use config.php
    }
    $ts = $at !== '' ? strtotime($at) : false;
    return [$enabled && $ts !== false, (int)$ts];
}

/** Map the request to a public .html file, or null. */
function requested_file(): ?string {
    $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_ends_with($path, '/')) {
        $path .= 'index.html';
    }
    if ($path === 'launch.php') {
        $path = 'index.html';
    }
    if (!preg_match('#^[A-Za-z0-9_./-]+\.html$#', $path) || str_contains($path, '..') || preg_match('#^(admin|api|scripts|docs|content)/#', $path)) {
        return null;
    }
    $file = realpath(SITE_ROOT . '/' . $path);
    return $file && strpos($file, SITE_ROOT . DIRECTORY_SEPARATOR) === 0 && is_file($file) ? $file : null;
}

[$gated, $launchTs] = launch_state();
$now = time();
$beforeLaunch = $gated && $now < $launchTs;

start_session();
$admin = !empty($_SESSION['uid']) && user();

// admins can look at the countdown page: /?preview=coming-soon
if ($admin && ($_GET['preview'] ?? '') === 'coming-soon') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo render_coming_soon($launchTs ?: $now + 86400, $now, true);
    exit;
}

if ($beforeLaunch && !$admin) {
    http_response_code(503);
    header('Retry-After: ' . max(60, $launchTs - $now));
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('X-Robots-Tag: noindex');
    echo render_coming_soon($launchTs, $now);
    exit;
}

$file = requested_file();
if (!$file) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Page not found | Argentec</title>'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&family=Source+Serif+4:opsz,wght@8..60,600&display=swap"></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f5f8;color:#0e1b2e;font:400 1rem/1.6 Montserrat,Arial,sans-serif;text-align:center;padding:2rem">'
        . '<div><img src="/assets/img/argentec-logo.svg" alt="Argentec" width="64" height="64"><h1 style="font:600 2.5rem/1.1 \'Source Serif 4\',Georgia,serif;margin:1.5rem 0 .5rem">Page not found</h1>'
        . '<p style="color:#5b6676">The page you are looking for has moved or no longer exists.</p><p><a href="/" style="color:#194376;font-weight:600">Go to the home page</a></p></div></body></html>';
    exit;
}

$html = (string)file_get_contents($file);
header('Content-Type: text/html; charset=utf-8');
if ($beforeLaunch && $admin) {
    // admins see the real site before launch, with a reminder
    header('Cache-Control: no-store');
    $bar = '<div style="position:fixed;z-index:99999;left:50%;bottom:14px;transform:translateX(-50%);background:#0e1b2e;color:#fff;padding:9px 16px;border-radius:4px;font:600 12px/1.4 Montserrat,Arial,sans-serif;letter-spacing:.03em;box-shadow:0 10px 30px rgba(0,0,0,.3)">'
        . 'Admin preview · visitors see the countdown until ' . h(date('j M Y, g:i A', $launchTs)) . ' · <a href="/?preview=coming-soon" style="color:#c5ccd6">see it</a></div>';
    $html = preg_replace('#</body>#', $bar . '</body>', $html, 1);
    // tell js/launch.js (the static-hosting gate) to stand aside for admins
    $html = preg_replace('#<head>#', '<head><script>window.ARGENTEC_ADMIN_PREVIEW = true;</script>', $html, 1);
} else {
    header('Cache-Control: public, max-age=300');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int)filemtime($file)) . ' GMT');
}
echo $html;
