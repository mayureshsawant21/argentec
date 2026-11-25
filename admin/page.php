<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/site.php';
$u = require_login('pages');
$rel = (string)($_GET['page'] ?? '');
$global = $rel === '*';

const TAG_NAMES = ['h1' => 'Main heading', 'h2' => 'Heading', 'h3' => 'Sub-heading', 'h4' => 'Small heading', 'h5' => 'Small heading', 'p' => 'Text',
    'li' => 'List item', 'dt' => 'Label', 'dd' => 'Value', 'figcaption' => 'Caption', 'blockquote' => 'Quote', 'td' => 'Table cell',
    'th' => 'Table heading', 'label' => 'Label', 'span' => 'Text', 'img' => 'Image', 'video' => 'Video cover image', 'source' => 'Video file',
    'address' => 'Address', 'a' => 'Link text'];

try {
    if ($global) {
        $sample = null;
        foreach (site_pages() as $pg) {
            if (str_contains((string)file_get_contents(SITE_ROOT . '/' . $pg), 'data-cms="footer-')) {
                $sample = $pg;
                break;
            }
        }
        if (!$sample) {
            throw new RuntimeException('No page has the shared footer.');
        }
        $html = read_page($sample);
        $fields = array_filter(cms_fields($html), function ($f) { return str_starts_with($f['key'], 'footer-'); });
        // the Development page has its own note
        if (is_file(SITE_ROOT . '/development/index.html')) {
            $dev = cms_fields(read_page('development/index.html'));
            if (isset($dev['footer-dev-note'])) {
                $fields['footer-dev-note'] = $dev['footer-dev-note'];
            }
        }
    } else {
        if (str_starts_with($rel, 'blog/')) {
            redirect('blog.php');
        }
        $html = read_page($rel);
        $fields = cms_fields($html);
    }
} catch (Throwable $t) {
    flash('error', $t->getMessage());
    redirect('pages.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    try {
        if ($action === 'restore' && !$global) {
            restore_page($rel, (string)$_POST['version']);
            log_activity('Restored page version', $rel . ' · ' . $_POST['version']);
            flash('success', 'The earlier version is live again.');
        } elseif ($global) {
            $n = 0;
            foreach ((array)($_POST['f'] ?? []) as $key => $value) {
                if (isset($fields[$key]) && is_string($value) && clean_inline($value) !== field_value($fields[$key])) {
                    $n += save_global_field((string)$key, $value);
                }
            }
            log_activity('Edited site-wide footer', $n . ' page updates');
            flash('success', $n ? 'Footer updated on ' . $n . ' page' . ($n === 1 ? '' : 's') . '.' : 'Nothing changed.');
        } else {
            $values = [];
            $originals = [];
            foreach (rows('SELECT field, original FROM content_edits WHERE page = ?', [$rel]) as $r) {
                $originals[$r['field']] = $r['original'];
            }
            foreach ((array)($_POST['f'] ?? []) as $key => $value) {
                $key = (string)$key;
                if (!empty($_POST['restore'][$key]) && isset($originals[$key])) {
                    $j = json_decode((string)$originals[$key], true);
                    $value = is_array($j) ? $j : $originals[$key];
                }
                $values[$key] = $value;
            }
            $meta = [
                'title' => trim((string)($_POST['meta']['title'] ?? '')),
                'description' => trim((string)($_POST['meta']['description'] ?? '')),
                'og_image' => trim((string)($_POST['meta']['og_image'] ?? '')),
                'noindex' => !empty($_POST['meta']['noindex']),
            ];
            $n = save_page_fields($rel, $values, $meta);
            if ($n) {
                write_sitemap();
                log_activity('Edited page', $rel . ' · ' . $n . ' change' . ($n === 1 ? '' : 's'));
            }
            flash('success', $n ? $n . ' change' . ($n === 1 ? '' : 's') . ' saved and live.' : 'Nothing changed.');
        }
    } catch (Throwable $t) {
        flash('error', 'Could not save: ' . $t->getMessage());
    }
    redirect('page.php?page=' . rawurlencode($rel));
}

$edited = [];
if (!$global) {
    foreach (rows('SELECT field, original, updated_at FROM content_edits WHERE page = ?', [$rel]) as $r) {
        $edited[$r['field']] = $r;
    }
}
$groups = [];
foreach ($fields as $key => $f) {
    $groups[$global ? 'Footer' : field_section($html, $f['start'])][$key] = $f;
}
$meta = $global ? null : meta_get($html);
$title = $global ? 'Site-wide footer' : (preg_replace('/\s*\|.*$/', '', page_title($html)) ?: $rel);
$actions = '<a class="btn btn--ghost" href="pages.php">&larr; Pages</a>' . ($global ? '' : '<a class="btn btn--ghost" href="../' . h($rel) . '" target="_blank" rel="noopener">View page</a>');
page_start($title, 'pages', $actions, $global ? 'Changes apply to every page that shows the footer.' : '<span class="mono">' . h($rel) . '</span> · ' . count($fields) . ' editable items');

function field_input(string $key, array $f, ?array $edit): string {
    $kind = field_kind($f);
    $v = field_value($f);
    $restore = $edit && $edit['original'] !== null ? '<label class="restore"><input type="checkbox" name="restore[' . h($key) . ']" value="1"> Restore original</label>' : '';
    if ($kind === 'image') {
        return '<div class="picker picker--inline" data-picker>
          <img class="picker__preview" src="' . h(preview_src($v['src'])) . '" alt="">
          <div class="picker__body">
            <input type="hidden" name="f[' . h($key) . '][src]" value="' . h($v['src']) . '" data-picker-value data-relative-from="' . h($GLOBALS['rel']) . '">
            <div class="picker__btns"><button class="btn btn--sm" type="button" data-pick="image">Replace image</button>' . $restore . '</div>
            <label class="field field--sm"><span>Description (alt text)</span><input name="f[' . h($key) . '][alt]" value="' . h($v['alt']) . '"></label>
          </div></div>';
    }
    if ($kind === 'poster' || $kind === 'source') {
        $type = $kind === 'poster' ? 'image' : 'video';
        return '<div class="picker picker--inline" data-picker>
          ' . ($kind === 'poster' ? '<img class="picker__preview" src="' . h(preview_src($v['src'])) . '" alt="">' : '<video class="picker__preview" src="' . h(preview_src($v['src'])) . '" muted preload="metadata"></video>') . '
          <div class="picker__body">
            <input type="hidden" name="f[' . h($key) . '][src]" value="' . h($v['src']) . '" data-picker-value data-relative-from="' . h($GLOBALS['rel']) . '">
            <p class="mono small">' . h($v['src']) . '</p>
            <div class="picker__btns"><button class="btn btn--sm" type="button" data-pick="' . $type . '">Replace ' . ($type === 'video' ? 'video' : 'image') . '</button>' . $restore . '</div>
          </div></div>';
    }
    $plain = strip_tags($v) === $v;
    $long = mb_strlen(strip_tags($v)) > 90 || !$plain;
    $input = $long
        ? '<textarea name="f[' . h($key) . ']" rows="' . min(8, max(2, (int)ceil(mb_strlen($v) / 90))) . '" data-autosize>' . h($v) . '</textarea>'
        : '<input name="f[' . h($key) . ']" value="' . h(html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '"' . ($plain ? ' data-plain' : '') . '>';
    return $input . ($restore ? '<div class="field__foot">' . $restore . '</div>' : '');
}
function preview_src(string $src): string {
    if (preg_match('#^https?://#', $src)) {
        return $src;
    }
    // page-relative path -> path relative to /admin/
    $dir = dirname($GLOBALS['rel'] === '*' ? 'index.html' : $GLOBALS['rel']);
    $parts = [];
    foreach (explode('/', ($dir === '.' ? '' : $dir . '/') . $src) as $seg) {
        if ($seg === '..') {
            array_pop($parts);
        } elseif ($seg !== '' && $seg !== '.') {
            $parts[] = $seg;
        }
    }
    return '../' . implode('/', $parts);
}
?>
<form method="post" class="page-editor" data-page-form>
  <?= csrf_field() ?>
  <div class="page-editor__bar">
    <input type="search" placeholder="Find text on this page" data-field-search>
    <span class="muted small" data-dirty-note hidden>Unsaved changes</span>
    <button class="btn btn--primary" type="submit">Save changes</button>
  </div>
<?php if (!$global): ?>
  <details class="card collapsible" open>
    <summary><h2>Search &amp; sharing</h2><span class="muted">How the page appears in Google and when shared</span></summary>
    <div class="form-grid">
      <label class="field field--wide"><span>Page title</span><input name="meta[title]" value="<?= h($meta['title']) ?>" data-count-max="60"></label>
      <label class="field field--wide"><span>Search description</span><textarea name="meta[description]" rows="2" data-count-max="160"><?= h($meta['description']) ?></textarea></label>
      <div class="field">
        <span>Share image <small>shown on WhatsApp, LinkedIn and Facebook</small></span>
        <div class="picker picker--inline" data-picker>
          <img class="picker__preview" src="<?= h($meta['og_image'] ? (preg_match('#^https?://#', $meta['og_image']) && str_starts_with($meta['og_image'], site_url()) ? '../' . substr($meta['og_image'], strlen(site_url()) + 1) : $meta['og_image']) : '') ?>" alt=""<?= $meta['og_image'] ? '' : ' hidden' ?>>
          <div class="picker__body"><input type="hidden" name="meta[og_image]" value="<?= h($meta['og_image']) ?>" data-picker-value data-absolute><div class="picker__btns"><button class="btn btn--sm" type="button" data-pick="image">Choose image</button></div></div>
        </div>
      </div>
      <label class="check"><input type="checkbox" name="meta[noindex]" value="1"<?= $meta['noindex'] ? ' checked' : '' ?>> Hide this page from search engines</label>
      <div class="serp field--wide" aria-hidden="true">
        <span class="serp__url"><?= h(site_url(preg_replace('#(^|/)index\.html$#', '$1', $rel))) ?></span>
        <span class="serp__title" data-serp-title><?= h($meta['title']) ?></span>
        <span class="serp__desc" data-serp-desc><?= h($meta['description']) ?></span>
      </div>
    </div>
  </details>
<?php endif; ?>
<?php if ($rel === 'careers.html'): ?>
  <div class="alert alert--info">Open roles, the role list in the form and the team counts on this page are managed in <a href="jobs.php">Job postings</a>.</div>
<?php endif; ?>
<?php $i = 0; foreach ($groups as $section => $list): $i++; ?>
  <details class="card collapsible fields-group"<?= $i <= 2 ? ' open' : '' ?>>
    <summary><h2><?= h($section) ?></h2><span class="muted"><?= count($list) ?> item<?= count($list) === 1 ? '' : 's' ?></span></summary>
    <div class="fields">
<?php foreach ($list as $key => $f): $e = $edited[$key] ?? null; ?>
      <div class="cms-field<?= $e ? ' is-edited' : '' ?>" data-text="<?= h(strtolower(strip_tags(is_array(field_value($f)) ? implode(' ', field_value($f)) : field_value($f)))) ?>">
        <label class="cms-field__label"><?= h(TAG_NAMES[$f['tag']] ?? ucfirst($f['tag'])) ?><?= isset($f['attrs']['data-count']) ? ' <small>(counter)</small>' : '' ?><?= $e ? ' <span class="badge badge--edited" title="Edited ' . h(fmt_date($e['updated_at'], true)) . '">Edited</span>' : '' ?></label>
        <div class="cms-field__input"><?= field_input($key, $f, $e) ?></div>
      </div>
<?php endforeach; ?>
    </div>
  </details>
<?php endforeach; ?>
  <p class="muted small">Tip: wrap words in <code>&lt;em&gt;…&lt;/em&gt;</code> for the italic accent, <code>&lt;strong&gt;</code> for bold and <code>&lt;br&gt;</code> for a line break.</p>
</form>
<?php if (!$global && ($history = page_history($rel))): ?>
<details class="card collapsible">
  <summary><h2>Earlier versions</h2><span class="muted">The page is saved before every change; restore one if something went wrong</span></summary>
  <ul class="history">
<?php foreach (array_slice($history, 0, 15) as $v): ?>
    <li><span><?= $v['time'] ? h($v['time']->format('j M Y, g:i a')) : h($v['file']) ?></span>
      <form method="post" data-confirm="Restore this version? The current page is kept in this list too."><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="version" value="<?= h($v['file']) ?>"><button class="btn btn--ghost btn--sm">Restore</button></form></li>
<?php endforeach; ?>
  </ul>
</details>
<?php endif; ?>
<?php require __DIR__ . '/inc/picker.php'; page_end();
