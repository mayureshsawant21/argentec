<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/site.php';
$u = require_login('blog');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    $p = row('SELECT * FROM posts WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    try {
        if ($act === 'settings') {
            foreach (['blog_heading', 'blog_lead', 'blog_title', 'blog_description'] as $k) {
                set_setting($k, trim((string)($_POST[$k] ?? '')));
            }
            publish_blog();
            log_activity('Updated blog page settings');
            flash('success', 'Blog page updated.');
        } elseif ($p && $act === 'publish') {
            q("UPDATE posts SET status = 'published', published_at = COALESCE(published_at, ?), updated_at = ? WHERE id = ?", [now(), now(), $p['id']]);
            publish_blog();
            log_activity('Published blog post', $p['title']);
            flash('success', '“' . $p['title'] . '” is live.');
        } elseif ($p && $act === 'unpublish') {
            q("UPDATE posts SET status = 'draft', updated_at = ? WHERE id = ?", [now(), $p['id']]);
            publish_blog();
            log_activity('Unpublished blog post', $p['title']);
            flash('success', '“' . $p['title'] . '” moved to drafts and removed from the website.');
        } elseif ($p && $act === 'delete') {
            q('DELETE FROM posts WHERE id = ?', [$p['id']]);
            publish_blog();
            log_activity('Deleted blog post', $p['title']);
            flash('success', '“' . $p['title'] . '” deleted.');
        }
    } catch (Throwable $t) {
        flash('error', 'The blog pages could not be updated: ' . $t->getMessage());
    }
    redirect('blog.php');
}

$filter = (string)($_GET['status'] ?? '');
$qtext = trim((string)($_GET['q'] ?? ''));
$where = '1=1';
$params = [];
if (in_array($filter, ['draft', 'published'], true)) {
    $where .= ' AND status = ?';
    $params[] = $filter;
}
if ($qtext !== '') {
    $where .= ' AND (title LIKE ? OR excerpt LIKE ? OR category LIKE ?)';
    array_push($params, "%$qtext%", "%$qtext%", "%$qtext%");
}
$posts = rows("SELECT * FROM posts WHERE $where ORDER BY CASE status WHEN 'draft' THEN 0 ELSE 1 END, COALESCE(published_at, created_at) DESC", $params);
$counts = ['published' => (int)val("SELECT COUNT(*) FROM posts WHERE status='published'"), 'draft' => (int)val("SELECT COUNT(*) FROM posts WHERE status='draft'")];
page_start('Blog', 'blog', '<a class="btn btn--ghost" href="../blog/index.html" target="_blank" rel="noopener">View blog</a><a class="btn btn--primary" href="post.php">+ New post</a>',
    $counts['published'] . ' published · ' . $counts['draft'] . ' draft' . ($counts['draft'] === 1 ? '' : 's'));
?>
<form class="filters filters--slim" method="get">
  <label class="filters__search"><span class="sr">Search</span><input type="search" name="q" value="<?= h($qtext) ?>" placeholder="Search posts"></label>
  <label><span>Status</span><select name="status" data-autosubmit><option value="">All</option><option value="published"<?= $filter === 'published' ? ' selected' : '' ?>>Published</option><option value="draft"<?= $filter === 'draft' ? ' selected' : '' ?>>Drafts</option></select></label>
  <div class="filters__btns"><button class="btn" type="submit">Search</button></div>
</form>

<?php if (!$posts): ?><section class="card"><p class="empty">No posts yet. Use “New post” to write the first one.</p></section><?php endif; ?>
<ul class="posts">
<?php foreach ($posts as $p): $cover = $p['cover'] ?: 'assets/img/projects/placeholder-1.svg'; ?>
  <li class="post">
    <a class="post__cover" href="post.php?id=<?= (int)$p['id'] ?>"><img src="../<?= h($cover) ?>" alt="" loading="lazy"></a>
    <div class="post__main">
      <p class="post__meta"><span class="badge badge--<?= h($p['status']) ?>"><?= $p['status'] === 'published' ? 'Published' : 'Draft' ?></span> <?= h($p['category']) ?> · <?= h(fmt_date($p['published_at'] ?: $p['created_at'])) ?><?= $p['noindex'] ? ' · <span class="muted">hidden from search</span>' : '' ?></p>
      <h3><a href="post.php?id=<?= (int)$p['id'] ?>"><?= h($p['title']) ?></a></h3>
      <p class="muted"><?= h(mb_strimwidth((string)$p['excerpt'], 0, 160, '…')) ?></p>
    </div>
    <form method="post" class="post__actions">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <a class="btn btn--ghost" href="post.php?id=<?= (int)$p['id'] ?>">Edit</a>
      <?php if ($p['status'] === 'published'): ?>
        <a class="btn btn--ghost" href="../blog/<?= h($p['slug']) ?>.html" target="_blank" rel="noopener">View</a>
        <button class="btn btn--ghost" name="action" value="unpublish">Unpublish</button>
      <?php else: ?>
        <a class="btn btn--ghost" href="preview.php?id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">Preview</a>
        <button class="btn btn--ghost" name="action" value="publish">Publish</button>
      <?php endif; ?>
      <button class="btn btn--danger" name="action" value="delete" data-confirm="Delete “<?= h($p['title']) ?>”? This cannot be undone.">Delete</button>
    </form>
  </li>
<?php endforeach; ?>
</ul>

<details class="card collapsible">
  <summary><h2>Blog page settings</h2><span class="muted">Heading and search listing of the blog's main page</span></summary>
  <form method="post" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <label class="field field--wide"><span>Heading <small>wrap words in &lt;em&gt;…&lt;/em&gt; for the italic accent</small></span><input name="blog_heading" value="<?= h(setting('blog_heading', 'Notes from <em>the site.</em>')) ?>"></label>
    <label class="field field--wide"><span>Intro line</span><textarea name="blog_lead" rows="2"><?= h(setting('blog_lead', 'Practical thinking on planning, building and delivering projects in Mumbai, from the people who run them.')) ?></textarea></label>
    <label class="field"><span>Page title for search engines</span><input name="blog_title" value="<?= h(setting('blog_title', 'Blog | Argentec')) ?>" data-count-max="60"></label>
    <label class="field"><span>Description for search engines</span><textarea name="blog_description" rows="2" data-count-max="160"><?= h(setting('blog_description', 'Articles from Argentec on planning, building and delivering construction projects in Mumbai.')) ?></textarea></label>
    <div class="form-actions field--wide"><button class="btn btn--primary" type="submit">Save and update blog page</button></div>
  </form>
</details>
<?php page_end();
