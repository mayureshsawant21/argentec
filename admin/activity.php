<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
$u = require_login('activity');
$per = 50;
$who = (string)($_GET['user'] ?? '');
$where = $who !== '' ? 'WHERE user_name = ?' : '';
$params = $who !== '' ? [$who] : [];
$total = (int)val("SELECT COUNT(*) FROM activity $where", $params);
$pages = max(1, (int)ceil($total / $per));
$page = min($pages, max(1, (int)($_GET['p'] ?? 1)));
$list = rows("SELECT * FROM activity $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
$users = array_column(rows('SELECT DISTINCT user_name FROM activity ORDER BY user_name'), 'user_name');
page_start('Activity log', 'activity', '', 'Who changed what, and when.');
?>
<form class="filters filters--slim" method="get">
  <label><span>User</span><select name="user" data-autosubmit><option value="">Everyone</option><?php foreach ($users as $n): ?><option<?= $who === $n ? ' selected' : '' ?>><?= h($n) ?></option><?php endforeach; ?></select></label>
</form>
<section class="card card--flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>When</th><th>Who</th><th>What</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
<?php foreach ($list as $a): ?>
      <tr><td class="nowrap"><?= h(fmt_date($a['created_at'], true)) ?></td><td><?= h($a['user_name']) ?></td><td><?= h($a['action']) ?></td><td class="muted"><?= h($a['detail']) ?></td><td class="mono small"><?= h($a['ip']) ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?= pager($page, $pages) ?>
<?php page_end();
