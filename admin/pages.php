<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/site.php';
$u = require_login('pages');

$groups = ['Main pages' => [], 'Expertise' => [], 'Projects' => [], 'Development' => [], 'Vendors' => [], 'Blog' => []];
$edits = [];
foreach (rows('SELECT page, COUNT(*) n, MAX(updated_at) t FROM content_edits GROUP BY page') as $r) {
    $edits[$r['page']] = $r;
}
foreach (site_pages() as $rel) {
    $top = strpos($rel, '/') === false ? '' : explode('/', $rel)[0];
    $g = ['' => 'Main pages', 'expertise' => 'Expertise', 'projects' => 'Projects', 'development' => 'Development', 'vendors' => 'Vendors', 'blog' => 'Blog'][$top] ?? 'Main pages';
    $html = read_page($rel);
    $groups[$g][] = ['rel' => $rel, 'title' => page_title($html), 'fields' => count(cms_fields($html)), 'mtime' => filemtime(SITE_ROOT . '/' . $rel), 'edits' => $edits[$rel] ?? null];
}
page_start('Pages', 'pages', '', 'Edit the text, images, titles and search descriptions of every page. Changes go live when you save.');
?>
<section class="card card--row">
  <div><h2 class="card__title">Site-wide footer</h2><p class="muted">Tagline, office address and the note at the bottom, changed on every page at once.</p></div>
  <a class="btn btn--primary" href="page.php?page=*">Edit footer</a>
</section>
<input type="search" class="page-search" placeholder="Find a page" data-filter-grid=".page-list">
<?php foreach ($groups as $g => $list): if (!$list) { continue; } ?>
<section class="card card--flush">
  <header class="card__head card__head--pad"><h2><?= h($g) ?></h2><span class="muted"><?= count($list) ?> page<?= count($list) === 1 ? '' : 's' ?></span></header>
  <ul class="page-list">
<?php foreach ($list as $p): $isBlog = str_starts_with($p['rel'], 'blog/'); ?>
    <li data-name="<?= h(strtolower($p['title'] . ' ' . $p['rel'])) ?>">
      <a href="<?= $isBlog ? 'blog.php' : 'page.php?page=' . rawurlencode($p['rel']) ?>">
        <b><?= h(preg_replace('/\s*\|.*$/', '', $p['title']) ?: $p['rel']) ?></b>
        <small><?= h($p['rel']) ?></small>
      </a>
      <span class="muted small"><?= $isBlog ? 'Managed in Blog' : $p['fields'] . ' editable items' ?><?= $p['edits'] ? ' · ' . (int)$p['edits']['n'] . ' edited, last ' . h(fmt_date($p['edits']['t'])) : '' ?></span>
      <a class="btn btn--ghost btn--sm" href="../<?= h($p['rel']) ?>" target="_blank" rel="noopener">View</a>
    </li>
<?php endforeach; ?>
  </ul>
</section>
<?php endforeach; page_end();
