<?php
/* Argentec admin: page shell (sidebar, top bar, flash messages). */

function nav_items(): array {
    $new = [];
    foreach (rows("SELECT form, COUNT(*) n FROM submissions WHERE status = 'new' GROUP BY form") as $r) {
        $new[$r['form']] = (int)$r['n'];
    }
    return [
        ['', [['dashboard', 'index.php', 'Dashboard', 'grid', null]]],
        ['Enquiries', [
            ['enquiries', 'submissions.php?form=contact', 'Contact', 'mail', $new['contact'] ?? 0, 'contact'],
            ['enquiries', 'submissions.php?form=vendor', 'Vendors', 'truck', $new['vendor'] ?? 0, 'vendor'],
            ['enquiries', 'submissions.php?form=development', 'Development', 'home', $new['development'] ?? 0, 'development'],
        ]],
        ['Careers', [
            ['careers', 'submissions.php?form=careers', 'Applications', 'user', $new['careers'] ?? 0, 'careers'],
            ['jobs', 'jobs.php', 'Job postings', 'brief', null],
        ]],
        ['Content', [
            ['pages', 'pages.php', 'Pages', 'file', null],
            ['blog', 'blog.php', 'Blog', 'pen', null],
            ['media', 'media.php', 'Media library', 'image', null],
        ]],
        ['Marketing', [
            ['tracking', 'tracking.php', 'Tracking & SEO', 'chart', null],
        ]],
        ['System', [
            ['settings', 'settings.php', 'Settings', 'cog', null],
            ['users', 'users.php', 'Users', 'users', null],
            ['activity', 'activity.php', 'Activity log', 'clock', null],
            ['backup', 'backup.php', 'Backup', 'archive', null],
        ]],
    ];
}

function icon(string $name): string {
    $p = [
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="M3 7l9 6 9-6"/>',
        'truck' => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>',
        'home' => '<path d="M4 11 12 4l8 7v9H4z"/><path d="M10 20v-5h4v5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
        'brief' => '<rect x="3" y="7" width="18" height="13" rx="1"/><path d="M9 7V4h6v3M3 12h18"/>',
        'file' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        'pen' => '<path d="M4 20l4-1 11-11-3-3L5 16z"/><path d="M14 6l3 3"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="1"/><circle cx="9" cy="10" r="2"/><path d="M21 17l-6-6-9 9"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c.8-3.5 3.4-5 7-5s6.2 1.5 7 5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 15c2 .6 3.4 2.2 4 5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'archive' => '<rect x="3" y="4" width="18" height="5"/><path d="M5 9v11h14V9M10 13h4"/>',
        'out' => '<path d="M15 4h4v16h-4M10 17l5-5-5-5M15 12H3"/>',
        'ext' => '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
    ][$name] ?? '';
    return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}

function page_start(string $title, string $active, string $actions = '', string $sub = ''): void {
    $u = user();
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $form = $_GET['form'] ?? '';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf" content="<?= h(csrf_token()) ?>">
  <title><?= h($title) ?> · Argentec Admin</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/argentec-logo.svg">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600&display=swap">
  <link rel="stylesheet" href="assets/admin.css?v=<?= substr(sha1_file(__DIR__ . '/../assets/admin.css'), 0, 8) ?>">
</head>
<body>
<?php if ($u): ?>
  <aside class="sb" id="sidebar">
    <a class="sb__brand" href="index.php"><img src="../assets/img/argentec-logo.svg" alt="" width="34" height="34"><span>Argentec <em>Admin</em></span></a>
    <nav class="sb__nav" aria-label="Admin">
<?php foreach (nav_items() as [$group, $items]):
        $visible = array_filter($items, function ($i) { return can($i[0]); });
        if (!$visible) { continue; } ?>
      <?php if ($group): ?><p class="sb__group"><?= h($group) ?></p><?php endif; ?>
<?php foreach ($visible as $i):
          // submission lists share a section key, so they also match on the form
          $isActive = isset($i[5]) ? in_array($current, ['submissions.php', 'submission.php'], true) && $form === $i[5] : $active === $i[0]; ?>
      <a class="sb__link<?= $isActive ? ' is-active' : '' ?>" href="<?= h($i[1]) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= icon($i[3]) ?><span><?= h($i[2]) ?></span><?php if (!empty($i[4])): ?><b class="sb__count"><?= (int)$i[4] ?></b><?php endif; ?></a>
<?php endforeach; endforeach; ?>
    </nav>
    <div class="sb__foot">
      <a class="sb__link" href="../index.html" target="_blank" rel="noopener"><?= icon('ext') ?><span>View website</span></a>
      <a class="sb__link<?= $current === 'account.php' ? ' is-active' : '' ?>" href="account.php"><?= icon('user') ?><span><?= h($u['name']) ?></span></a>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="sb__link" type="submit"><?= icon('out') ?><span>Sign out</span></button></form>
    </div>
  </aside>
  <div class="main">
    <header class="tb">
      <button class="tb__menu" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Menu"><span></span><span></span></button>
      <div class="tb__title">
        <h1><?= h($title) ?></h1>
        <?php if ($sub): ?><p><?= $sub ?></p><?php endif; ?>
      </div>
      <div class="tb__actions"><?= $actions ?></div>
    </header>
    <main class="content">
<?php if ($u['must_change']): ?>
      <div class="alert alert--warn"><b>You are using the default password.</b> Anyone who knows it can sign in. <a href="account.php">Change it now</a>.</div>
<?php endif; ?>
<?php else: ?>
  <div class="main main--bare"><main class="content">
<?php endif; ?>
<?php if ($f = take_flash()): ?>
      <div class="alert alert--<?= h($f[0]) ?>" role="status"><?= h($f[1]) ?></div>
<?php endif;
}

function page_end(): void {
    ?>
    </main>
  </div>
  <script src="assets/admin.js?v=<?= substr(sha1_file(__DIR__ . '/../assets/admin.js'), 0, 8) ?>"></script>
</body>
</html>
<?php
}

/** Pagination links keeping the current query string. */
function pager(int $page, int $pages): string {
    if ($pages <= 1) {
        return '';
    }
    $q = $_GET;
    $out = '<nav class="pager" aria-label="Pages">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($pages > 9 && $i > 2 && $i < $pages - 1 && abs($i - $page) > 2) {
            if (!isset($gap[$i < $page ? 0 : 1])) {
                $out .= '<span>…</span>';
                $gap[$i < $page ? 0 : 1] = 1;
            }
            continue;
        }
        $q['p'] = $i;
        $out .= $i === $page ? '<span class="is-current">' . $i . '</span>' : '<a href="?' . h(http_build_query($q)) . '">' . $i . '</a>';
    }
    return $out . '</nav>';
}

function status_badge(string $status, string $form = 'default'): string {
    $label = statuses($form)[$status] ?? ucfirst($status);
    return '<span class="badge badge--' . h($status) . '">' . h($label) . '</span>';
}
