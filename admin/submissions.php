<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/submissions.php';
$form = (string)($_GET['form'] ?? 'contact');
if (!isset(FORMS[$form])) {
    $form = 'contact';
}
$_GET['form'] = $form;
$u = require_login($form === 'careers' ? 'careers' : 'enquiries');
$statuses = statuses($form);

// bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $action = (string)($_POST['bulk'] ?? '');
    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'delete') {
            foreach (rows("SELECT file_path FROM submissions WHERE form = ? AND id IN ($marks)", array_merge([$form], $ids)) as $r) {
                if ($r['file_path']) {
                    @unlink(data_dir() . '/resumes/' . $r['file_path']);
                }
            }
            q("DELETE FROM submissions WHERE form = ? AND id IN ($marks)", array_merge([$form], $ids));
            log_activity('Deleted submissions', count($ids) . ' from ' . FORMS[$form]['label']);
            flash('success', count($ids) . ' deleted.');
        } elseif ($action === 'read') {
            q("UPDATE submissions SET read_at = COALESCE(read_at, ?) WHERE form = ? AND id IN ($marks)", array_merge([now(), $form], $ids));
            flash('success', count($ids) . ' marked as read.');
        } elseif (isset($statuses[$action])) {
            q("UPDATE submissions SET status = ?, updated_at = ? WHERE form = ? AND id IN ($marks)", array_merge([$action, now(), $form], $ids));
            log_activity('Changed status', count($ids) . ' ' . FORMS[$form]['short'] . ' → ' . $statuses[$action]);
            flash('success', count($ids) . ' set to ' . $statuses[$action] . '.');
        }
    }
    redirect('submissions.php?' . http_build_query(array_intersect_key($_GET, array_flip(['form', 'q', 'status', 'from', 'to', 'designation', 'experience', 'range', 'p']))));
}

[$where, $params, $f] = submission_filters($form, $_GET);
$total = (int)val("SELECT COUNT(*) FROM submissions WHERE $where", $params);
$per = 25;
$pages = max(1, (int)ceil($total / $per));
$page = min($pages, max(1, (int)($_GET['p'] ?? 1)));
$sort = ($_GET['sort'] ?? '') === 'oldest' ? 'ASC' : 'DESC';
$list = rows("SELECT * FROM submissions WHERE $where ORDER BY created_at $sort, id $sort LIMIT $per OFFSET " . (($page - 1) * $per), $params);

$designations = $form === 'careers' ? rows("SELECT COALESCE(designation,'General application') d, COUNT(*) n FROM submissions WHERE form = 'careers' GROUP BY d ORDER BY d") : [];
$experiences = $form === 'careers' ? array_column(rows("SELECT DISTINCT json_extract(data,'$.experience') e FROM submissions WHERE form = 'careers' AND e IS NOT NULL ORDER BY e"), 'e') : [];
$counts = [];
foreach (rows('SELECT status, COUNT(*) n FROM submissions WHERE form = ? GROUP BY status', [$form]) as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$exportQ = http_build_query(array_filter(['form' => $form] + $f, 'strlen'));
$actions = '<a class="btn" href="export.php?' . h($exportQ) . '">Export CSV</a>';
$filtered = array_filter($f, 'strlen');
page_start(FORMS[$form]['label'], $form === 'careers' ? 'careers' : 'enquiries', $actions,
    $total . ' ' . ($filtered ? 'matching' : 'in total') . (!empty($counts['new']) ? ' · <b>' . $counts['new'] . ' new</b>' : ''));
?>
<form class="filters" method="get">
  <input type="hidden" name="form" value="<?= h($form) ?>">
  <label class="filters__search"><span class="sr">Search</span><input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="Search name, email, phone, reference or any answer"></label>
<?php if ($form === 'careers'): ?>
  <label><span>Designation</span><select name="designation"><option value="">All designations</option>
<?php foreach ($designations as $d): ?>
    <option value="<?= h($d['d']) ?>"<?= $f['designation'] === $d['d'] ? ' selected' : '' ?>><?= h($d['d']) ?> (<?= (int)$d['n'] ?>)</option>
<?php endforeach; ?>
  </select></label>
  <label><span>Experience</span><select name="experience"><option value="">Any experience</option>
<?php foreach ($experiences as $e): ?>
    <option<?= $f['experience'] === $e ? ' selected' : '' ?>><?= h($e) ?></option>
<?php endforeach; ?>
  </select></label>
<?php endif; ?>
  <label><span>Status</span><select name="status"><option value="">All (except spam)</option>
<?php foreach ($statuses as $k => $l): ?>
    <option value="<?= h($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= h($l) ?><?= isset($counts[$k]) ? ' (' . $counts[$k] . ')' : '' ?></option>
<?php endforeach; ?>
  </select></label>
  <label><span>From</span><input type="date" name="from" value="<?= h($f['from']) ?>" max="<?= date('Y-m-d') ?>"></label>
  <label><span>To</span><input type="date" name="to" value="<?= h($f['to']) ?>" max="<?= date('Y-m-d') ?>"></label>
  <div class="filters__btns"><button class="btn btn--primary" type="submit">Apply</button><?php if ($filtered): ?><a class="btn btn--ghost" href="?form=<?= h($form) ?>">Clear</a><?php endif; ?></div>
  <div class="filters__quick">
    <span>Quick:</span>
<?php foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month'] as $k => $l): ?>
    <a href="?<?= h(http_build_query(['form' => $form, 'range' => $k] + array_filter(['designation' => $f['designation'], 'status' => $f['status']], 'strlen'))) ?>"<?= ($_GET['range'] ?? '') === $k ? ' class="is-on"' : '' ?>><?= $l ?></a>
<?php endforeach; ?>
  </div>
</form>

<form method="post" class="card card--flush" data-bulk>
  <?= csrf_field() ?>
  <div class="bulk" hidden>
    <span data-bulk-count>0 selected</span>
    <select name="bulk" aria-label="Bulk action"><option value="">Choose an action…</option>
      <optgroup label="Set status"><?php foreach ($statuses as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?></optgroup>
      <option value="read">Mark as read</option>
      <option value="delete">Delete</option>
    </select>
    <button class="btn" type="submit" data-confirm-bulk>Apply</button>
  </div>
<?php if (!$list): ?>
  <p class="empty"><?= $filtered ? 'Nothing matches these filters.' : 'No submissions yet. They will appear here as soon as someone sends the form on the website.' ?></p>
<?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr>
      <th class="col-check"><input type="checkbox" data-check-all aria-label="Select all"></th>
      <th><a href="?<?= h(http_build_query(['sort' => $sort === 'DESC' ? 'oldest' : ''] + $_GET)) ?>">Received <?= $sort === 'DESC' ? '↓' : '↑' ?></a></th>
      <th>Name</th>
<?php if ($form === 'careers'): ?><th>Designation</th><th>Experience</th><th>CTC (L)</th><th>Resume</th>
<?php else: ?><th>About</th><th>Location</th><?php endif; ?>
      <th>Status</th>
    </tr></thead>
    <tbody>
<?php foreach ($list as $s): $d = json_decode($s['data'], true) ?: []; ?>
      <tr class="<?= $s['read_at'] ? '' : 'is-unread' ?>" data-href="submission.php?id=<?= (int)$s['id'] ?>">
        <td class="col-check"><input type="checkbox" name="ids[]" value="<?= (int)$s['id'] ?>" aria-label="Select <?= h($s['name']) ?>"></td>
        <td><b><?= h(fmt_date($s['created_at'], true)) ?></b><small><?= h($s['reference']) ?></small></td>
        <td><a href="submission.php?id=<?= (int)$s['id'] ?>"><?= h($s['name']) ?></a><small><?= h($s['email'] ?: $s['phone']) ?></small></td>
<?php if ($form === 'careers'): ?>
        <td><?= h($s['designation'] ?: 'General application') ?></td>
        <td><?= h($d['experience'] ?? '–') ?></td>
        <td class="nowrap"><?= h($d['current_ctc'] ?? '–') ?> → <?= h($d['expected_ctc'] ?? '–') ?></td>
        <td><?php if ($s['file_path']): ?><a class="link-dl" href="download.php?id=<?= (int)$s['id'] ?>" title="<?= h($s['file_name']) ?>">Download</a><?php else: ?>–<?php endif; ?></td>
<?php else: ?>
        <td><?= h($s['subject']) ?></td>
        <td><?= h($d['location'] ?? $d['plot_location'] ?? $d['cities'] ?? '–') ?></td>
<?php endif; ?>
        <td><?= status_badge($s['status'], $form) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</form>
<?= pager($page, $pages) ?>
<?php page_end();
