<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
start_session();
$s = isset($_GET['ref']) ? row('SELECT * FROM submissions WHERE reference = ?', [(string)$_GET['ref']]) : row('SELECT * FROM submissions WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$s) {
    require_login('dashboard');
    flash('error', 'That submission no longer exists.');
    redirect('index.php');
}
$form = $s['form'];
$_GET['form'] = $form;
$u = require_login($form === 'careers' ? 'careers' : 'enquiries');
$statuses = statuses($form);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'delete') {
        if ($s['file_path']) {
            @unlink(data_dir() . '/resumes/' . $s['file_path']);
        }
        q('DELETE FROM submissions WHERE id = ?', [$s['id']]);
        log_activity('Deleted submission', $s['reference'] . ' · ' . $s['name']);
        flash('success', 'Deleted ' . $s['reference'] . '.');
        redirect('submissions.php?form=' . $form);
    }
    $status = (string)($_POST['status'] ?? $s['status']);
    if (!isset($statuses[$status])) {
        $status = $s['status'];
    }
    $notes = trim((string)($_POST['notes'] ?? ''));
    q('UPDATE submissions SET status = ?, notes = ?, updated_at = ? WHERE id = ?', [$status, $notes === '' ? null : mb_substr($notes, 0, 5000), now(), $s['id']]);
    if ($status !== $s['status']) {
        log_activity('Changed status', $s['reference'] . ' → ' . $statuses[$status]);
    }
    flash('success', 'Saved.');
    redirect('submission.php?id=' . $s['id']);
}
if (!$s['read_at']) {
    q('UPDATE submissions SET read_at = ? WHERE id = ?', [now(), $s['id']]);
}
$d = json_decode($s['data'], true) ?: [];
$prev = val('SELECT id FROM submissions WHERE form = ? AND id > ? ORDER BY id ASC LIMIT 1', [$form, $s['id']]);
$next = val('SELECT id FROM submissions WHERE form = ? AND id < ? ORDER BY id DESC LIMIT 1', [$form, $s['id']]);
$actions = '<a class="btn btn--ghost" href="submissions.php?form=' . h($form) . '">&larr; ' . h(FORMS[$form]['short']) . '</a>'
    . ($prev ? '<a class="btn btn--ghost" href="?id=' . (int)$prev . '" title="Newer">‹</a>' : '')
    . ($next ? '<a class="btn btn--ghost" href="?id=' . (int)$next . '" title="Older">›</a>' : '');
page_start($s['name'], $form === 'careers' ? 'careers' : 'enquiries', $actions,
    h($s['subject']) . ' · ' . h(fmt_date($s['created_at'], true)) . ' · <span class="mono">' . h($s['reference']) . '</span>');
$skip = ['name', 'role', 'role_title', 'enquiry_key'];
?>
<div class="detail">
  <section class="card">
    <div class="contact-row">
<?php if ($s['email']): ?><a class="btn" href="mailto:<?= h($s['email']) ?>?subject=<?= rawurlencode('Re: your ' . strtolower(FORMS[$form]['short']) . ' submission ' . $s['reference']) ?>">Email <?= h($s['email']) ?></a><?php endif; ?>
<?php if ($s['phone']): ?><a class="btn" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $s['phone'])) ?>">Call <?= h($s['phone']) ?></a>
      <a class="btn btn--ghost" href="https://wa.me/<?= h(ltrim(preg_replace('/[^0-9]/', '', strlen(preg_replace('/[^0-9]/', '', $s['phone'])) === 10 ? '91' . $s['phone'] : $s['phone']), '0')) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
<?php if ($s['file_path']): ?><a class="btn btn--primary" href="download.php?id=<?= (int)$s['id'] ?>">Download resume</a><a class="btn btn--ghost" href="download.php?id=<?= (int)$s['id'] ?>&amp;view=1" target="_blank" rel="noopener">View</a><?php endif; ?>
    </div>
    <dl class="dl">
<?php if ($form === 'careers'): ?>
      <div><dt>Applied for</dt><dd><?= h($s['designation'] ?: 'General application') ?></dd></div>
<?php endif; ?>
<?php foreach ($d as $k => $v): if (in_array($k, $skip, true)) { continue; }
        $isUrl = preg_match('#^https?://#i', $v); ?>
      <div<?= strlen($v) > 80 ? ' class="dl--wide"' : '' ?>><dt><?= h(field_label($k)) ?></dt><dd><?= $isUrl ? '<a href="' . h($v) . '" target="_blank" rel="noopener noreferrer">' . h($v) . '</a>' : nl2br(h($v)) ?></dd></div>
<?php endforeach; ?>
<?php if ($s['file_path']): ?>
      <div><dt>Resume</dt><dd><?= h($s['file_name']) ?> <span class="muted">(<?= bytes((int)$s['file_size']) ?>)</span></dd></div>
<?php endif; ?>
    </dl>
    <p class="meta-line">Sent from <?= h($s['page'] ?: '–') ?> · <?= h($s['ip'] ?: '–') ?></p>
  </section>

  <aside class="card">
    <form method="post">
      <?= csrf_field() ?>
      <h2 class="card__title">Review</h2>
      <label class="field"><span>Status</span><select name="status">
<?php foreach ($statuses as $k => $l): ?><option value="<?= h($k) ?>"<?= $s['status'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
      </select></label>
      <label class="field"><span>Internal notes</span><textarea name="notes" rows="7" placeholder="Visible only in the admin panel"><?= h($s['notes']) ?></textarea></label>
      <button class="btn btn--primary btn--block" type="submit">Save</button>
      <?php if ($s['updated_at']): ?><p class="muted small">Last updated <?= h(fmt_date($s['updated_at'], true)) ?></p><?php endif; ?>
    </form>
    <form method="post" class="danger-zone" data-confirm="Delete this submission<?= $s['file_path'] ? ' and its resume' : '' ?>? This cannot be undone.">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <button class="btn btn--danger btn--block" type="submit">Delete submission</button>
    </form>
  </aside>
</div>
<?php page_end();
