<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/media.php';
start_session();
$ajax = isset($_GET['json']) || isset($_GET['ajax']);
if ($ajax) {
    if (!user() || !(can('media') || can('blog') || can('pages'))) {
        json_out(['ok' => false, 'error' => 'Please sign in again.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        try {
            $path = media_store($_FILES['file'] ?? []);
            log_activity('Uploaded media', $path);
            json_out(['ok' => true, 'path' => $path]);
        } catch (Throwable $t) {
            json_out(['ok' => false, 'error' => $t->getMessage()], 422);
        }
    }
    json_out(['ok' => true, 'items' => media_list((string)($_GET['type'] ?? ''))]);
}

$u = require_login('media');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'delete') {
        $rel = (string)$_POST['path'];
        $full = realpath(SITE_ROOT . '/' . $rel);
        $root = realpath(media_root());
        if ($full && $root && strpos($full, $root . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
            // refuse if a page still uses it
            $used = [];
            require_once __DIR__ . '/inc/site.php';
            foreach (site_pages() as $pg) {
                if (str_contains((string)file_get_contents(SITE_ROOT . '/' . $pg), basename($rel))) {
                    $used[] = $pg;
                }
            }
            if ($used && empty($_POST['force'])) {
                flash('error', basename($rel) . ' is used on ' . implode(', ', array_slice($used, 0, 4)) . (count($used) > 4 ? '…' : '') . '. Replace it there first.');
            } else {
                unlink($full);
                log_activity('Deleted media', $rel);
                flash('success', basename($rel) . ' deleted.');
            }
        } else {
            flash('error', 'Only uploaded files can be deleted here.');
        }
    } else {
        $n = 0;
        $errors = [];
        $files = $_FILES['files'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                try {
                    $p = media_store(['name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]]);
                    log_activity('Uploaded media', $p);
                    $n++;
                } catch (Throwable $t) {
                    $errors[] = $name . ': ' . $t->getMessage();
                }
            }
        }
        if ($n) {
            flash('success', $n . ' file' . ($n === 1 ? '' : 's') . ' uploaded.' . ($errors ? ' Not uploaded: ' . implode('; ', $errors) : ''));
        } else {
            flash('error', $errors ? implode('; ', $errors) : 'Choose at least one file.');
        }
    }
    redirect('media.php' . (isset($_GET['type']) ? '?type=' . rawurlencode((string)$_GET['type']) : ''));
}

$type = (string)($_GET['type'] ?? '');
$items = media_list(in_array($type, ['image', 'video', 'document'], true) ? $type : '');
page_start('Media library', 'media', '', count($items) . ' files · upload images and videos to use on pages and in blog posts');
?>
<form class="card dropzone" method="post" enctype="multipart/form-data" data-dropzone>
  <?= csrf_field() ?>
  <p><b>Drop files here</b> or <label class="link">choose files<input type="file" name="files[]" multiple hidden accept=".jpg,.jpeg,.png,.webp,.gif,.avif,.mp4,.webm,.pdf"></label></p>
  <p class="muted small">JPG, PNG, WebP, GIF, AVIF, MP4, WebM or PDF · up to <?= (int)cfg('max_media_mb', 25) ?> MB each · for photos, 2000 px wide is plenty</p>
  <noscript><button class="btn" type="submit">Upload</button></noscript>
</form>
<nav class="tabs">
<?php foreach (['' => 'All', 'image' => 'Images', 'video' => 'Videos', 'document' => 'Documents'] as $k => $l): ?>
  <a href="?type=<?= h($k) ?>"<?= $type === $k ? ' class="is-on"' : '' ?>><?= $l ?></a>
<?php endforeach; ?>
  <input type="search" class="tabs__search" placeholder="Filter by name" data-filter-grid=".media-grid">
</nav>
<ul class="media-grid">
<?php foreach ($items as $m): ?>
  <li class="media" data-name="<?= h(strtolower($m['name'])) ?>">
    <div class="media__thumb">
      <?php if ($m['kind'] === 'image'): ?><img src="../<?= h($m['path']) ?>" alt="" loading="lazy">
      <?php elseif ($m['kind'] === 'video'): ?><video src="../<?= h($m['path']) ?>" muted preload="metadata"></video><span class="media__tag">Video</span>
      <?php else: ?><span class="media__doc">PDF</span><?php endif; ?>
    </div>
    <div class="media__info">
      <b title="<?= h($m['path']) ?>"><?= h($m['name']) ?></b>
      <small><?= bytes((int)$m['size']) ?> · <?= $m['uploaded'] ? 'Uploaded ' . date('j M Y', $m['time']) : 'Site file' ?></small>
      <div class="media__btns">
        <button class="btn btn--ghost btn--sm" type="button" data-copy="<?= h(site_url($m['path'])) ?>">Copy link</button>
        <a class="btn btn--ghost btn--sm" href="../<?= h($m['path']) ?>" target="_blank" rel="noopener">Open</a>
        <?php if ($m['uploaded']): ?>
        <form method="post" data-confirm="Delete <?= h($m['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="path" value="<?= h($m['path']) ?>"><button class="btn btn--danger btn--sm">Delete</button></form>
        <?php endif; ?>
      </div>
    </div>
  </li>
<?php endforeach; ?>
</ul>
<?php page_end();
