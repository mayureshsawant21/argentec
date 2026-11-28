<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/site.php';
$u = require_login('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? 'save');
    try {
        switch ($act) {
            case 'save':
                $url = rtrim(trim((string)$_POST['site_url']), '/');
                if ($url !== '' && !preg_match('#^https?://[a-z0-9.-]+(:\d+)?(/.*)?$#i', $url)) {
                    throw new RuntimeException('The website address should look like https://argentec.com');
                }
                set_setting('site_url', $url);
                foreach (array_merge(['all'], array_keys(FORMS)) as $f) {
                    $list = array_filter(array_map('trim', explode(',', (string)($_POST['notify_' . $f] ?? ''))));
                    foreach ($list as $a) {
                        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) {
                            throw new RuntimeException('“' . $a . '” is not a valid email address.');
                        }
                    }
                    set_setting('notify_' . $f, implode(', ', $list));
                }
                set_setting('spam_days', (string)max(0, (int)$_POST['spam_days']));
                log_activity('Updated settings');
                flash('success', 'Settings saved.');
                break;
            case 'test_mail':
                $to = trim((string)($_POST['to'] ?? $u['email']));
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid email address.');
                }
                $ok = @mail($to, 'Argentec admin: test email', "This is a test from the Argentec admin panel.\nIf you received it, form notifications will reach you too.", 'From: Argentec Website <' . cfg('mail_from') . ">\r\nContent-Type: text/plain; charset=UTF-8");
                flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $to . '. Check the inbox and the spam folder.' : 'The hosting refused to send email. Check the email settings in hPanel.');
                break;
            case 'reapply':
                $n = reapply_edits();
                log_activity('Re-applied page edits', $n . ' pages');
                flash('success', 'Saved edits re-applied to ' . $n . ' page' . ($n === 1 ? '' : 's') . '.');
                break;
            case 'rebuild':
                publish_careers();
                $posts = publish_blog();
                $t = apply_tracking_all();
                $s = write_sitemap();
                log_activity('Rebuilt website', "careers, blog ($posts posts), tracking ($t pages), sitemap ($s URLs)");
                flash('success', "Careers page, blog ($posts posts), tracking codes and sitemap ($s pages) are up to date.");
                break;
            case 'sitemap':
                $s = write_sitemap();
                flash('success', 'sitemap.xml and robots.txt written (' . $s . ' pages).');
                break;
            case 'purge_spam':
                $days = max(1, (int)setting('spam_days', '30'));
                $n = q("DELETE FROM submissions WHERE status IN ('spam','rejected','closed') AND created_at < datetime('now','localtime', ?)", ['-' . $days . ' days'])->rowCount();
                log_activity('Purged old submissions', (string)$n);
                flash('success', $n . ' old spam, rejected or closed submission' . ($n === 1 ? '' : 's') . ' deleted.');
                break;
        }
    } catch (Throwable $t) {
        flash('error', $t->getMessage());
    }
    redirect('settings.php');
}

$checks = [
    ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>=')],
    ['SQLite database', true],
    ['Pages can be written', is_writable(SITE_ROOT . '/index.html') && is_writable(SITE_ROOT)],
    ['Uploads folder writable', is_writable(SITE_ROOT . '/assets/uploads')],
    ['Private data folder writable', is_writable(data_dir())],
    ['File type detection (fileinfo)', class_exists('finfo')],
    ['Backups (ZipArchive)', class_exists('ZipArchive')],
    ['HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')],
];
page_start('Settings', 'settings');
?>
<form method="post" class="card form-card">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <h2 class="card__title">Website</h2>
  <label class="field"><span>Website address <small>used for the sitemap, share links and emails</small></span><input name="site_url" value="<?= h(setting('site_url', '') ?: cfg('site_url')) ?>" placeholder="https://argentec.com"></label>

  <h2 class="card__title">Email notifications</h2>
  <p class="muted small">Get an email for every new submission. Separate several addresses with commas. Leave empty for no emails.</p>
  <div class="form-grid">
    <label class="field field--wide"><span>All forms</span><input name="notify_all" value="<?= h(setting('notify_all')) ?>" placeholder="info@argentec.com"></label>
<?php foreach (FORMS as $k => $f): ?>
    <label class="field"><span><?= h($f['label']) ?> <small>instead of the above</small></span><input name="notify_<?= h($k) ?>" value="<?= h(setting('notify_' . $k)) ?>" placeholder="<?= $k === 'careers' ? 'hr@argentec.com' : ($k === 'vendor' ? 'procurement@argentec.com' : '') ?>"></label>
<?php endforeach; ?>
  </div>

  <h2 class="card__title">Housekeeping</h2>
  <label class="field field--narrow"><span>Delete spam, rejected and closed submissions older than (days)</span><input name="spam_days" type="number" min="1" value="<?= h(setting('spam_days', '90')) ?>"></label>
  <div class="form-actions"><button class="btn btn--primary" type="submit">Save settings</button></div>
</form>

<div class="grid2">
  <section class="card">
    <h2 class="card__title">Test email</h2>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="test_mail">
      <input name="to" type="email" value="<?= h($u['email']) ?>" aria-label="Send to"><button class="btn" type="submit">Send test</button></form>
    <p class="muted small">Emails are sent from <?= h(cfg('mail_from')) ?>. Create that mailbox (or change it in admin/inc/config.php) so messages are not marked as spam.</p>
  </section>
  <section class="card">
    <h2 class="card__title">Health check</h2>
    <ul class="checks">
<?php foreach ($checks as [$label, $ok]): ?>
      <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✕' ?> <?= h($label) ?></li>
<?php endforeach; ?>
    </ul>
  </section>
</div>

<section class="card">
  <h2 class="card__title">Maintenance</h2>
  <div class="tools">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rebuild"><button class="btn" type="submit">Rebuild website</button><p class="muted small">Regenerates the careers openings, the blog, the tracking codes on every page and the sitemap.</p></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reapply"><button class="btn" type="submit">Re-apply page edits</button><p class="muted small">After a developer uploads new page files, puts your saved text and image edits back.</p></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sitemap"><button class="btn" type="submit">Update sitemap</button><p class="muted small">Writes <a href="../sitemap.xml" target="_blank">sitemap.xml</a> and robots.txt for search engines.</p></form>
    <form method="post" data-confirm="Delete old spam, rejected and closed submissions now?"><?= csrf_field() ?><input type="hidden" name="action" value="purge_spam"><button class="btn btn--danger" type="submit">Clean up old submissions</button><p class="muted small">Uses the number of days above.</p></form>
  </div>
</section>
<?php page_end();
