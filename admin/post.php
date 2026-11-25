<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/site.php';
$u = require_login('blog');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$post = $id ? row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
if ($id && !$post) {
    flash('error', 'That post no longer exists.');
    redirect('blog.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)$_POST['title']);
    if ($title === '') {
        flash('error', 'The post needs a title.');
        redirect('post.php' . ($id ? '?id=' . $id : ''));
    }
    $slug = slugify((string)($_POST['slug'] ?: $title));
    $base = $slug;
    $n = 2;
    while ($slug === 'index' || val('SELECT 1 FROM posts WHERE slug = ? AND id <> ?', [$slug, $id])) {
        $slug = $base . '-' . $n++;
    }
    $intent = (string)($_POST['intent'] ?? 'save');
    $status = $intent === 'publish' ? 'published' : ($intent === 'draft' ? 'draft' : (($post['status'] ?? 'draft') === 'published' ? 'published' : 'draft'));
    $date = (string)($_POST['published_at'] ?? '');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date . ' ' . date('H:i:s') : ($status === 'published' ? ($post['published_at'] ?? now()) : ($post['published_at'] ?? null));
    $cover = trim((string)($_POST['cover'] ?? ''));
    $cover = preg_match('#^assets/[A-Za-z0-9_./-]+\.(jpe?g|png|webp|gif|svg|avif)$#i', $cover) ? $cover : ($post['cover'] ?? '');
    $vals = [
        $slug, mb_substr($title, 0, 160), mb_substr(trim((string)$_POST['category']) ?: 'Insights', 0, 60),
        mb_substr(trim((string)$_POST['excerpt']), 0, 400), clean_rich((string)$_POST['body']),
        $cover ?: null, mb_substr(trim((string)$_POST['cover_alt']), 0, 200),
        mb_substr(trim((string)$_POST['author']) ?: 'Argentec Editorial', 0, 80), $status, $date,
        mb_substr(trim((string)$_POST['seo_title']), 0, 120) ?: null, mb_substr(trim((string)$_POST['seo_description']), 0, 300) ?: null,
        empty($_POST['noindex']) ? 0 : 1, now(),
    ];
    if ($post) {
        q('UPDATE posts SET slug=?, title=?, category=?, excerpt=?, body=?, cover=?, cover_alt=?, author=?, status=?, published_at=?, seo_title=?, seo_description=?, noindex=?, updated_at=? WHERE id = ?', array_merge($vals, [$id]));
    } else {
        q('INSERT INTO posts (slug, title, category, excerpt, body, cover, cover_alt, author, status, published_at, seo_title, seo_description, noindex, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $vals);
        $id = (int)db()->lastInsertId();
    }
    log_activity(($post ? 'Updated' : 'Created') . ' blog post', $title . ' (' . $status . ')');
    try {
        if ($status === 'published' || ($post['status'] ?? '') === 'published') {
            publish_blog();
        }
        flash('success', $status === 'published' ? 'Saved and live on the website.' : 'Draft saved. It is not on the website until you publish it.');
    } catch (Throwable $t) {
        flash('error', 'Saved, but the blog pages could not be updated: ' . $t->getMessage());
    }
    redirect('post.php?id=' . $id);
}

$p = $post ?: ['id' => 0, 'title' => '', 'slug' => '', 'category' => 'Insights', 'excerpt' => '', 'body' => '', 'cover' => '', 'cover_alt' => '',
    'author' => 'Argentec Editorial', 'status' => 'draft', 'published_at' => null, 'seo_title' => '', 'seo_description' => '', 'noindex' => 0];
$cats = array_column(rows('SELECT DISTINCT category FROM posts ORDER BY category'), 'category');
$live = $p['status'] === 'published';
$actions = '<a class="btn btn--ghost" href="blog.php">&larr; All posts</a>'
    . ($p['id'] ? '<a class="btn btn--ghost" href="' . ($live ? '../blog/' . h($p['slug']) . '.html' : 'preview.php?id=' . (int)$p['id']) . '" target="_blank" rel="noopener">' . ($live ? 'View live' : 'Preview') . '</a>' : '');
page_start($p['id'] ? 'Edit post' : 'New post', 'blog', $actions, $p['id'] ? ($live ? 'Published ' . h(fmt_date($p['published_at'])) : 'Draft · not on the website yet') : 'Write, add a cover image, then publish.');
?>
<form method="post" class="editor" data-post-form>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <div class="editor__main">
    <input class="editor__title" name="title" value="<?= h($p['title']) ?>" placeholder="Post title (the H1 on the article page)" required maxlength="160">
    <textarea class="editor__excerpt" name="excerpt" rows="2" maxlength="400" placeholder="Short summary shown on the blog card and under the title" data-count-max="200"><?= h($p['excerpt']) ?></textarea>
    <div class="rte" data-rte>
      <div class="rte__bar" role="toolbar" aria-label="Formatting">
        <button type="button" data-cmd="formatBlock" data-val="p" title="Paragraph">¶</button>
        <button type="button" data-cmd="formatBlock" data-val="h2" title="Heading">H2</button>
        <button type="button" data-cmd="formatBlock" data-val="h3" title="Sub-heading">H3</button>
        <span class="rte__sep"></span>
        <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"><b>B</b></button>
        <button type="button" data-cmd="italic" title="Italic (Ctrl+I)"><i>I</i></button>
        <button type="button" data-cmd="createLink" title="Link">Link</button>
        <button type="button" data-cmd="unlink" title="Remove link">Unlink</button>
        <span class="rte__sep"></span>
        <button type="button" data-cmd="insertUnorderedList" title="Bulleted list">• List</button>
        <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
        <button type="button" data-cmd="formatBlock" data-val="blockquote" title="Quote">“ Quote</button>
        <button type="button" data-cmd="image" title="Insert image">Image</button>
        <span class="rte__sep"></span>
        <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
        <button type="button" data-cmd="source" title="Edit HTML">&lt;/&gt;</button>
      </div>
      <div class="rte__area prose" contenteditable="true" data-placeholder="Start writing…"><?= $p['body'] ?></div>
      <textarea class="rte__source" hidden spellcheck="false"></textarea>
      <textarea name="body" hidden><?= h($p['body']) ?></textarea>
      <p class="rte__info" data-rte-info></p>
    </div>
  </div>
  <aside class="editor__side">
    <section class="card">
      <h2 class="card__title">Publish</h2>
      <p class="muted small">Status: <b><?= $live ? 'Published' : 'Draft' ?></b></p>
      <label class="field"><span>Publish date</span><input type="date" name="published_at" value="<?= h($p['published_at'] ? substr($p['published_at'], 0, 10) : '') ?>"></label>
      <div class="stack">
<?php if ($live): ?>
        <button class="btn btn--primary btn--block" name="intent" value="save">Update</button>
        <button class="btn btn--ghost btn--block" name="intent" value="draft" data-confirm="Move this post back to drafts? It will be removed from the website.">Unpublish</button>
<?php else: ?>
        <button class="btn btn--primary btn--block" name="intent" value="publish">Publish</button>
        <button class="btn btn--block" name="intent" value="draft">Save draft</button>
<?php endif; ?>
      </div>
    </section>
    <section class="card">
      <h2 class="card__title">Cover image</h2>
      <div class="picker" data-picker>
        <img class="picker__preview" src="<?= $p['cover'] ? '../' . h($p['cover']) : '' ?>" alt=""<?= $p['cover'] ? '' : ' hidden' ?>>
        <input type="hidden" name="cover" value="<?= h($p['cover']) ?>" data-picker-value>
        <div class="picker__btns"><button class="btn" type="button" data-pick="image">Choose or upload</button><button class="btn btn--ghost" type="button" data-pick-clear>Remove</button></div>
      </div>
      <label class="field"><span>Image description <small>for screen readers and Google</small></span><input name="cover_alt" value="<?= h($p['cover_alt']) ?>"></label>
    </section>
    <section class="card">
      <h2 class="card__title">Details</h2>
      <label class="field"><span>Category</span><input name="category" list="cats" value="<?= h($p['category']) ?>"><datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist></label>
      <label class="field"><span>Author</span><input name="author" value="<?= h($p['author']) ?>"></label>
      <label class="field"><span>Link <small>blog/…html</small></span><input name="slug" value="<?= h($p['slug']) ?>" placeholder="made from the title"></label>
    </section>
    <section class="card">
      <h2 class="card__title">Search engines</h2>
      <label class="field"><span>SEO title <small>optional</small></span><input name="seo_title" value="<?= h($p['seo_title']) ?>" placeholder="<?= h($p['title'] ? $p['title'] . ' | Argentec Blog' : '') ?>" data-count-max="60"></label>
      <label class="field"><span>SEO description <small>optional</small></span><textarea name="seo_description" rows="3" placeholder="Uses the summary if empty" data-count-max="160"><?= h($p['seo_description']) ?></textarea></label>
      <label class="check"><input type="checkbox" name="noindex" value="1"<?= $p['noindex'] ? ' checked' : '' ?>> Hide from search engines</label>
    </section>
  </aside>
</form>
<?php require __DIR__ . '/inc/picker.php'; page_end();
