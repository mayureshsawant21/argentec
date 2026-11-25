<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
$u = require_login('dashboard');

// forms this user may see
$visible = array_filter(array_keys(FORMS), function ($f) { return can($f === 'careers' ? 'careers' : 'enquiries'); });
$in = $visible ? "'" . implode("','", $visible) . "'" : "''";

$cards = [];
foreach ($visible as $f) {
    $cards[$f] = [
        'total' => (int)val('SELECT COUNT(*) FROM submissions WHERE form = ?', [$f]),
        'new' => (int)val("SELECT COUNT(*) FROM submissions WHERE form = ? AND status = 'new'", [$f]),
        'week' => (int)val("SELECT COUNT(*) FROM submissions WHERE form = ? AND created_at >= datetime('now','localtime','-7 days')", [$f]),
    ];
}
// last 30 days, all visible forms
$days = [];
for ($i = 29; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = 0;
}
foreach (rows("SELECT date(created_at) d, COUNT(*) n FROM submissions WHERE form IN ($in) AND created_at >= date('now','localtime','-29 days') GROUP BY d") as $r) {
    if (isset($days[$r['d']])) {
        $days[$r['d']] = (int)$r['n'];
    }
}
$maxDay = max(1, max($days));
$recent = rows("SELECT * FROM submissions WHERE form IN ($in) ORDER BY id DESC LIMIT 8");
$openJobs = (int)val("SELECT COUNT(*) FROM jobs WHERE status = 'open'");
$posts = (int)val("SELECT COUNT(*) FROM posts WHERE status = 'published'");
$drafts = (int)val("SELECT COUNT(*) FROM posts WHERE status = 'draft'");
$byRole = can('careers') ? rows("SELECT COALESCE(designation,'General application') d, COUNT(*) n FROM submissions WHERE form = 'careers' AND created_at >= date('now','localtime','-29 days') GROUP BY d ORDER BY n DESC LIMIT 6") : [];

page_start('Dashboard', 'dashboard', '', 'Welcome back, ' . h(explode(' ', $u['name'])[0]) . '. Here is what came in recently.');
?>
<section class="stats">
<?php foreach ($cards as $f => $c): ?>
  <a class="stat" href="submissions.php?form=<?= h($f) ?>">
    <span class="stat__label"><?= h(FORMS[$f]['label']) ?></span>
    <b class="stat__num"><?= $c['total'] ?></b>
    <span class="stat__meta"><?php if ($c['new']): ?><em><?= $c['new'] ?> new</em> · <?php endif; ?><?= $c['week'] ?> this week</span>
  </a>
<?php endforeach; ?>
<?php if (can('jobs')): ?>
  <a class="stat stat--quiet" href="jobs.php"><span class="stat__label">Open job postings</span><b class="stat__num"><?= $openJobs ?></b><span class="stat__meta">Live on the careers page</span></a>
<?php endif; ?>
<?php if (can('blog')): ?>
  <a class="stat stat--quiet" href="blog.php"><span class="stat__label">Blog posts</span><b class="stat__num"><?= $posts ?></b><span class="stat__meta"><?= $drafts ?> draft<?= $drafts === 1 ? '' : 's' ?></span></a>
<?php endif; ?>
</section>

<?php if ($visible): ?>
<div class="grid2">
  <section class="card">
    <header class="card__head"><h2>Submissions, last 30 days</h2><span class="muted"><?= array_sum($days) ?> in total</span></header>
    <div class="bars" role="img" aria-label="Submissions per day over the last 30 days">
<?php foreach ($days as $d => $n): ?>
      <span class="bars__bar" style="--h:<?= round($n / $maxDay * 100) ?>%" title="<?= h(date('j M', strtotime($d)) . ': ' . $n) ?>"><i><?= $n ?: '' ?></i></span>
<?php endforeach; ?>
    </div>
    <div class="bars__axis"><span><?= date('j M', strtotime(array_key_first($days))) ?></span><span>Today</span></div>
  </section>
<?php if ($byRole): ?>
  <section class="card">
    <header class="card__head"><h2>Applications by designation</h2><span class="muted">Last 30 days</span></header>
    <ul class="hbars">
<?php $maxR = max(array_column($byRole, 'n')); foreach ($byRole as $r): ?>
      <li><a href="submissions.php?form=careers&amp;designation=<?= rawurlencode($r['d']) ?>"><span><?= h($r['d']) ?></span><b><?= (int)$r['n'] ?></b><i style="--w:<?= round($r['n'] / $maxR * 100) ?>%"></i></a></li>
<?php endforeach; ?>
    </ul>
  </section>
<?php else: ?>
  <section class="card">
    <header class="card__head"><h2>Quick actions</h2></header>
    <div class="quick">
      <?php if (can('blog')): ?><a class="btn" href="post.php">+ New blog post</a><?php endif; ?>
      <?php if (can('jobs')): ?><a class="btn" href="jobs.php?edit=new">+ New job posting</a><?php endif; ?>
      <?php if (can('pages')): ?><a class="btn" href="pages.php">Edit page content</a><?php endif; ?>
      <?php if (can('tracking')): ?><a class="btn" href="tracking.php">Tracking codes</a><?php endif; ?>
    </div>
  </section>
<?php endif; ?>
</div>

<section class="card">
  <header class="card__head"><h2>Latest submissions</h2></header>
<?php if (!$recent): ?>
  <p class="empty">Nothing yet. Submissions from the website's forms will appear here.</p>
<?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Received</th><th>Form</th><th>Name</th><th>About</th><th>Status</th></tr></thead>
    <tbody>
<?php foreach ($recent as $s): ?>
      <tr class="<?= $s['read_at'] ? '' : 'is-unread' ?>" data-href="submission.php?id=<?= (int)$s['id'] ?>">
        <td><b><?= h(fmt_date($s['created_at'], true)) ?></b><small><?= h($s['reference']) ?></small></td>
        <td><?= h(FORMS[$s['form']]['short']) ?></td>
        <td><a href="submission.php?id=<?= (int)$s['id'] ?>"><?= h($s['name']) ?></a><small><?= h($s['email'] ?: $s['phone']) ?></small></td>
        <td><?= h($s['subject']) ?></td>
        <td><?= status_badge($s['status'], $s['form']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>
<?php else: ?>
<section class="card"><div class="quick">
  <?php if (can('blog')): ?><a class="btn" href="post.php">+ New blog post</a><?php endif; ?>
  <?php if (can('pages')): ?><a class="btn" href="pages.php">Edit page content</a><?php endif; ?>
  <?php if (can('jobs')): ?><a class="btn" href="jobs.php?edit=new">+ New job posting</a><?php endif; ?>
</div></section>
<?php endif;
page_end();
