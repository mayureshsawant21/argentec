<?php
/* Argentec admin: shared bootstrap.
   Database (SQLite), install, sessions, authentication, CSRF, settings,
   activity log and small helpers. Included by every admin page and by
   api/submit.php. */

declare(strict_types=1);

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('The Argentec admin panel needs PHP 7.4 or newer.');
}

const ADMIN_DIR = __DIR__ . '/..';
define('SITE_ROOT', realpath(__DIR__ . '/../..'));

$CONFIG = require __DIR__ . '/config.php';
date_default_timezone_set($CONFIG['timezone'] ?? 'Asia/Kolkata');

function cfg(string $key, $default = null) {
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

function data_dir(): string {
    static $real = null;
    if ($real) {
        return $real;
    }
    $dir = cfg('data_dir');
    if (!$dir) {
        // outside the public folder when the hosting allows it
        $outside = dirname(SITE_ROOT) . '/argentec-private';
        $inside = __DIR__ . '/../data';
        if (is_file($inside . '/argentec.sqlite')) {
            $dir = $inside; // an existing install keeps its data where it is
        } elseif (is_dir($outside) || @mkdir($outside, 0750, true)) {
            $dir = $outside;
        } else {
            $dir = $inside;
        }
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $real = realpath($dir);
    if (!$real) {
        throw new RuntimeException('Data folder could not be created: ' . $dir);
    }
    // keep the folder private even if it was moved or recreated
    if (!file_exists($real . '/.htaccess')) {
        @file_put_contents($real . '/.htaccess', "Require all denied\n");
    }
    foreach (['resumes', 'backups'] as $sub) {
        if (!is_dir($real . '/' . $sub)) {
            @mkdir($real . '/' . $sub, 0750, true);
        }
    }
    return $real;
}

/* ----------------------------------------------------------------- polyfills */
if (!function_exists('str_contains')) {
    function str_contains(string $h, string $n): bool { return $n === '' || strpos($h, $n) !== false; }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $h, string $n): bool { return strncmp($h, $n, strlen($n)) === 0; }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $h, string $n): bool { return $n === '' || substr($h, -strlen($n)) === $n; }
}

/* ------------------------------------------------------------------ database */
function db(): PDO {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        http_response_code(500);
        exit('The PHP SQLite extension (pdo_sqlite) is not enabled on this hosting. Enable it in hPanel → Advanced → PHP Configuration → PHP extensions.');
    }
    $file = data_dir() . '/argentec.sqlite';
    $fresh = !file_exists($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    if ($fresh) {
        @chmod($file, 0640);
    }
    install($pdo);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function row(string $sql, array $params = []): ?array {
    $r = q($sql, $params)->fetch();
    return $r ?: null;
}
function rows(string $sql, array $params = []): array {
    return q($sql, $params)->fetchAll();
}
function val(string $sql, array $params = []) {
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

const SCHEMA_VERSION = 1;

function install(PDO $pdo): void {
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= SCHEMA_VERSION) {
        return;
    }
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  username TEXT NOT NULL UNIQUE COLLATE NOCASE,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'admin',
  must_change INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1,
  last_login TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS submissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  form TEXT NOT NULL,
  reference TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'new',
  name TEXT, email TEXT, phone TEXT,
  subject TEXT,
  designation TEXT,
  data TEXT NOT NULL,
  file_path TEXT, file_name TEXT, file_size INTEGER,
  notes TEXT,
  ip TEXT, user_agent TEXT, page TEXT,
  read_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  updated_at TEXT
);
CREATE INDEX IF NOT EXISTS sub_form_date ON submissions (form, created_at);
CREATE INDEX IF NOT EXISTS sub_ip_date ON submissions (ip, created_at);
CREATE TABLE IF NOT EXISTS jobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  department TEXT NOT NULL,
  location TEXT NOT NULL,
  employment_type TEXT NOT NULL DEFAULT 'Full-time',
  experience TEXT,
  summary TEXT,
  duties TEXT NOT NULL DEFAULT '[]',
  requirements TEXT NOT NULL DEFAULT '[]',
  status TEXT NOT NULL DEFAULT 'open',
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  category TEXT NOT NULL DEFAULT 'Insights',
  excerpt TEXT,
  body TEXT NOT NULL DEFAULT '',
  cover TEXT, cover_alt TEXT,
  author TEXT NOT NULL DEFAULT 'Argentec Editorial',
  status TEXT NOT NULL DEFAULT 'draft',
  published_at TEXT,
  seo_title TEXT, seo_description TEXT,
  noindex INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  updated_at TEXT
);
CREATE TABLE IF NOT EXISTS content_edits (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  page TEXT NOT NULL,
  field TEXT NOT NULL,
  value TEXT NOT NULL,
  original TEXT,
  updated_by INTEGER,
  updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  UNIQUE (page, field)
);
CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT
);
CREATE TABLE IF NOT EXISTS activity (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  user_name TEXT,
  action TEXT NOT NULL,
  detail TEXT,
  ip TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip TEXT NOT NULL,
  username TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
SQL);
    // first admin account
    if (!(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
        $a = cfg('default_admin');
        $st = $pdo->prepare('INSERT INTO users (name, username, email, password_hash, role, must_change) VALUES (?,?,?,?,?,1)');
        $st->execute([$a['name'], $a['username'], $a['email'], password_hash($a['password'], PASSWORD_DEFAULT), 'admin']);
    }
    // jobs and blog posts that are on the website today
    require_once __DIR__ . '/seed.php';
    seed_content($pdo);
    $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
}

/* ------------------------------------------------------------------ settings */
function setting(string $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (rows('SELECT key, value FROM settings') as $r) {
            $cache[$r['key']] = $r['value'];
        }
    }
    if (func_num_args() === 3) { // internal: refresh
        $cache = null;
        return null;
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}
function set_setting(string $key, $value): void {
    q('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, (string)$value]);
    setting('', '', true);
}
function site_url(string $path = ''): string {
    $base = rtrim((string)(setting('site_url', '') ?: cfg('site_url')), '/');
    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

/* ------------------------------------------------------------------- helpers */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function now(): string {
    return date('Y-m-d H:i:s');
}
function client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}
function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? substr($s, 0, 80) : 'item';
}
function fmt_date(?string $d, bool $time = false): string {
    if (!$d) {
        return '–';
    }
    $t = strtotime($d);
    return $time ? date('j M Y, g:i a', $t) : date('j M Y', $t);
}
function bytes(int $n): string {
    if ($n >= 1048576) {
        return round($n / 1048576, 1) . ' MB';
    }
    return max(1, (int)round($n / 1024)) . ' KB';
}
function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ------------------------------------------------------------------ sessions */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('argentec_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // sign out after 2 hours without activity
    if (!empty($_SESSION['uid']) && (time() - ($_SESSION['seen'] ?? 0)) > 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'] = ['info', 'You were signed out after 2 hours of inactivity.'];
    }
    $_SESSION['seen'] = time();
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}
function check_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}

function flash(string $type, string $msg): void {
    start_session();
    $_SESSION['flash'] = [$type, $msg];
}
function take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------------------------------------------------------------- auth */
const ROLES = [
    'admin'  => 'Administrator',
    'editor' => 'Content editor',
    'hr'     => 'HR / recruitment',
    'sales'  => 'Enquiries',
];
// which sections each role may open
const PERMS = [
    'admin'  => ['dashboard', 'enquiries', 'careers', 'jobs', 'blog', 'pages', 'media', 'tracking', 'settings', 'users', 'activity', 'backup'],
    'editor' => ['dashboard', 'blog', 'pages', 'media', 'jobs'],
    'hr'     => ['dashboard', 'careers', 'jobs'],
    'sales'  => ['dashboard', 'enquiries'],
];

function user(): ?array {
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    start_session();
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = row('SELECT * FROM users WHERE id = ? AND active = 1', [$_SESSION['uid']]);
    }
    return $u;
}
function can(string $section): bool {
    $u = user();
    return $u && in_array($section, PERMS[$u['role']] ?? [], true);
}
function require_login(?string $section = null): array {
    $u = user();
    if (!$u) {
        redirect('login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }
    if ($section && !can($section)) {
        http_response_code(403);
        require_once __DIR__ . '/layout.php';
        page_start('No access', '');
        echo '<div class="ad-empty-state"><h2>No access</h2><p>Your account does not have access to this section. Ask an administrator if you need it.</p></div>';
        page_end();
        exit;
    }
    check_csrf();
    return $u;
}

function too_many_attempts(): bool {
    q("DELETE FROM login_attempts WHERE created_at < datetime('now','localtime','-1 day')");
    return (int)val("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > datetime('now','localtime','-15 minutes')", [client_ip()]) >= 8;
}

function log_activity(string $action, string $detail = ''): void {
    $u = user();
    q('INSERT INTO activity (user_id, user_name, action, detail, ip) VALUES (?,?,?,?,?)',
        [$u['id'] ?? null, $u['name'] ?? 'System', $action, mb_substr($detail, 0, 500), client_ip()]);
}

/* ------------------------------------------------------------------- forms */
const FORMS = [
    'contact'     => ['label' => 'Contact enquiries',       'short' => 'Contact',     'prefix' => 'ARG'],
    'careers'     => ['label' => 'Career applications',     'short' => 'Careers',     'prefix' => 'CAR'],
    'vendor'      => ['label' => 'Vendor registrations',    'short' => 'Vendors',     'prefix' => 'VEN'],
    'development' => ['label' => 'Development interest',    'short' => 'Development', 'prefix' => 'ADV'],
];
const STATUSES = [
    'careers' => ['new' => 'New', 'shortlisted' => 'Shortlisted', 'interview' => 'Interview', 'offered' => 'Offered', 'hired' => 'Hired', 'rejected' => 'Rejected'],
    'default' => ['new' => 'New', 'contacted' => 'Contacted', 'in_progress' => 'In progress', 'qualified' => 'Qualified', 'closed' => 'Closed', 'spam' => 'Spam'],
];
function statuses(string $form): array {
    return STATUSES[$form] ?? STATUSES['default'];
}

// friendly labels for submitted fields
const FIELD_LABELS = [
    'enquiry_type' => 'Enquiry type', 'enquiry_key' => 'Enquiry key', 'name' => 'Name', 'email' => 'Email', 'phone' => 'Mobile',
    'company' => 'Company', 'role' => 'Role', 'role_title' => 'Applied for', 'location' => 'Location', 'experience' => 'Experience',
    'current_ctc' => 'Current CTC (lakhs p.a.)', 'expected_ctc' => 'Expected CTC (lakhs p.a.)', 'linkedin' => 'LinkedIn',
    'message' => 'Message', 'consent' => 'Consent', 'vendor_type' => 'Vendor type', 'gst' => 'GST number',
    'categories' => 'Categories', 'cities' => 'Cities served', 'years' => 'Years in business', 'capacity' => 'Capacity',
    'catalogue' => 'Catalogue link', 'references' => 'References', 'configuration' => 'Looking for', 'timeline' => 'Planning to buy',
    'firm' => 'Firm', 'rera' => 'MahaRERA no.', 'society' => 'Society', 'members' => 'Members', 'age' => 'Building age (years)',
    'materials' => 'Materials', 'project_type' => 'Project type', 'budget' => 'Budget', 'site_location' => 'Site location',
    'stage' => 'Project stage', 'start' => 'Planned start', 'scope' => 'Scope', 'property' => 'Property', 'plot_location' => 'Plot location',
    'plot_area' => 'Plot area', 'area' => 'Area', 'description' => 'Description',
];
function field_label(string $k): string {
    return FIELD_LABELS[$k] ?? ucfirst(str_replace(['_', '-'], ' ', $k));
}

/* ------------------------------------------------------------------ launch */
/** [countdown enabled, launch timestamp, site is live now] */
function launch_info(): array {
    $at = (string)(setting('launch_at', '') ?: cfg('launch_at', ''));
    $ts = $at !== '' ? (int)strtotime($at) : 0;
    $on = setting('launch_enabled', '1') === '1' && $ts > 0;
    return [$on, $ts, !$on || time() >= $ts];
}
