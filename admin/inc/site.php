<?php
/* Argentec admin: the publishing engine. Everything that writes to the
   public website lives here:
   - page content edits (elements marked data-cms="key" by scripts/cms_tags.py)
   - page titles, descriptions and share images
   - tracking codes (GTM, GA4, Meta Pixel, Clarity, LinkedIn, custom code)
   - the openings and team counts on careers.html
   - blog pages (blog/index.html and one page per post)
   - sitemap.xml and robots.txt
   Every write keeps the previous version of the page in data/backups/pages. */

declare(strict_types=1);

/* -------------------------------------------------------------- page files */
const PRIVATE_DIRS = ['admin', 'api', 'scripts', 'docs', 'content', 'assets', 'css', 'js', 'node_modules', 'data'];

function site_path(string $rel): string {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || str_contains($rel, '..') || !preg_match('#^[A-Za-z0-9_./-]+\.html$#', $rel)) {
        throw new InvalidArgumentException('Invalid page path');
    }
    $top = explode('/', $rel)[0];
    if (in_array($top, PRIVATE_DIRS, true)) {
        throw new InvalidArgumentException('Not a public page');
    }
    return SITE_ROOT . '/' . $rel;
}

/** All public pages, relative to the site root, e.g. "index.html", "projects/vogue.html". */
function site_pages(): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(SITE_ROOT, FilesystemIterator::SKIP_DOTS),
        function ($f, $key, $it) {
            $rel = ltrim(substr($f->getPathname(), strlen(SITE_ROOT)), '/');
            $top = explode('/', $rel)[0];
            if ($f->getFilename()[0] === '.' || in_array($top, PRIVATE_DIRS, true)) {
                return false;
            }
            return $it->hasChildren() || str_ends_with($f->getFilename(), '.html');
        }
    ));
    foreach ($it as $f) {
        $out[] = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(SITE_ROOT))), '/');
    }
    usort($out, function ($a, $b) {
        $da = substr_count($a, '/');
        $db = substr_count($b, '/');
        return $da === $db ? strcmp($a, $b) : $da - $db;
    });
    return $out;
}

function read_page(string $rel): string {
    $p = site_path($rel);
    if (!is_file($p)) {
        throw new RuntimeException('Page not found: ' . $rel);
    }
    return (string)file_get_contents($p);
}

/** Write a page atomically and keep the previous version (last 15). */
function write_page(string $rel, string $html, bool $backup = true): void {
    $p = site_path($rel);
    if (is_file($p) && $backup) {
        $old = (string)file_get_contents($p);
        if ($old === $html) {
            return;
        }
        $dir = data_dir() . '/backups/pages/' . str_replace('/', '__', $rel);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        file_put_contents($dir . '/' . date('Ymd-His') . '-' . substr(md5($old), 0, 6) . '.html', $old);
        $keep = glob($dir . '/*.html') ?: [];
        rsort($keep);
        foreach (array_slice($keep, 15) as $f) {
            @unlink($f);
        }
    }
    if (!is_dir(dirname($p))) {
        @mkdir(dirname($p), 0755, true);
    }
    $tmp = $p . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $html) === false || !rename($tmp, $p)) {
        @unlink($tmp);
        throw new RuntimeException('Could not write ' . $rel . ' (check file permissions).');
    }
    @chmod($p, 0644);
}

function page_history(string $rel): array {
    $dir = data_dir() . '/backups/pages/' . str_replace('/', '__', $rel);
    $files = glob($dir . '/*.html') ?: [];
    rsort($files);
    return array_map(function ($f) {
        $b = basename($f);
        return ['file' => $b, 'time' => DateTime::createFromFormat('Ymd-His', substr($b, 0, 15))];
    }, $files);
}

function restore_page(string $rel, string $file): void {
    if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{6}\.html$/', $file)) {
        throw new InvalidArgumentException('Invalid version');
    }
    $src = data_dir() . '/backups/pages/' . str_replace('/', '__', $rel) . '/' . $file;
    if (!is_file($src)) {
        throw new RuntimeException('Version not found');
    }
    write_page($rel, (string)file_get_contents($src));
}

function page_title(string $html): string {
    return preg_match('#<title>(.*?)</title>#s', $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
}

/** Short version of the CSS/JS content hash used for ?v= cache busting. */
function asset_v(string $rel): string {
    $p = SITE_ROOT . '/' . $rel;
    return is_file($p) ? substr(sha1_file($p), 0, 8) : '0';
}

/* ------------------------------------------------- data-cms content fields */
const VOID_TAGS = ['img', 'source', 'br', 'input', 'meta', 'link', 'hr', 'area', 'base', 'col', 'embed', 'param', 'track', 'wbr'];

/** Locate every data-cms element: key, tag, offsets, attributes and inner HTML. */
function cms_fields(string $html): array {
    $out = [];
    if (!preg_match_all('/<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*\sdata-cms="([^"]+)"[^>]*>/', $html, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        return $out;
    }
    foreach ($m as $hit) {
        $tag = strtolower($hit[1][0]);
        $start = $hit[0][1];
        $openEnd = $start + strlen($hit[0][0]);
        $f = ['key' => $hit[2][0], 'tag' => $tag, 'start' => $start, 'open' => $hit[0][0], 'open_end' => $openEnd];
        if (in_array($tag, VOID_TAGS, true)) {
            $f['inner'] = null;
            $f['end'] = $openEnd;
        } else {
            $close = find_close($html, $tag, $openEnd);
            if ($close === null) {
                continue;
            }
            $f['inner'] = substr($html, $openEnd, $close[0] - $openEnd);
            $f['inner_end'] = $close[0];
            $f['end'] = $close[1];
        }
        $f['attrs'] = parse_attrs($hit[0][0]);
        $out[$f['key']] = $f;
    }
    return $out;
}

function find_close(string $html, string $tag, int $from): ?array {
    $depth = 1;
    $re = '#<(/?)' . preg_quote($tag, '#') . '\b[^>]*>#i';
    while (preg_match($re, $html, $m, PREG_OFFSET_CAPTURE, $from)) {
        $depth += $m[1][0] === '/' ? -1 : 1;
        $from = $m[0][1] + strlen($m[0][0]);
        if ($depth === 0) {
            return [$m[0][1], $from];
        }
    }
    return null;
}

function parse_attrs(string $openTag): array {
    $attrs = [];
    if (preg_match_all('/\s([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?/', $openTag, $m, PREG_SET_ORDER)) {
        foreach ($m as $a) {
            $attrs[strtolower($a[1])] = html_entity_decode($a[2] ?? ($a[3] ?? ($a[4] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    return $attrs;
}

function set_attr(string $openTag, string $name, string $value): string {
    $v = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $re = '/(\s' . preg_quote($name, '/') . '\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s>]+)/i';
    if (preg_match($re, $openTag)) {
        return preg_replace_callback($re, function ($m) use ($v) { return $m[1] . '"' . $v . '"'; }, $openTag, 1);
    }
    $close = str_ends_with($openTag, '/>') ? '/>' : '>';
    return rtrim(substr($openTag, 0, -strlen($close))) . ' ' . $name . '="' . $v . '"' . $close;
}

/** Editor type for a field: text (inline HTML), image, poster or media source. */
function field_kind(array $f): string {
    if ($f['tag'] === 'img') {
        return 'image';
    }
    if ($f['tag'] === 'video') {
        return 'poster';
    }
    if ($f['tag'] === 'source') {
        return 'source';
    }
    return 'text';
}

/** Current editable value of a field, as shown in the editor. */
function field_value(array $f) {
    switch (field_kind($f)) {
        case 'image':
            return ['src' => $f['attrs']['src'] ?? '', 'alt' => $f['attrs']['alt'] ?? ''];
        case 'poster':
            return ['src' => $f['attrs']['poster'] ?? ''];
        case 'source':
            return ['src' => $f['attrs']['src'] ?? ''];
    }
    return clean_inline((string)$f['inner']);
}

/** Apply one field value to the page HTML. $value is a string (text) or array (media). */
function apply_field(string $html, string $key, $value): string {
    $fields = cms_fields($html);
    if (!isset($fields[$key])) {
        return $html;
    }
    $f = $fields[$key];
    $kind = field_kind($f);
    if ($kind === 'text') {
        $inner = clean_inline((string)$value);
        $open = $f['open'];
        if (isset($f['attrs']['data-count'])) {
            $num = preg_replace('/[^0-9.]/', '', strip_tags($inner));
            if ($num !== '') {
                $open = set_attr($open, 'data-count', $num);
            }
        }
        return substr($html, 0, $f['start']) . $open . $inner . substr($html, $f['inner_end']);
    }
    $value = is_array($value) ? $value : ['src' => (string)$value];
    $open = $f['open'];
    $src = safe_url((string)($value['src'] ?? ''));
    if ($src === '') {
        return $html;
    }
    if ($kind === 'image') {
        $open = set_attr($open, 'src', $src);
        $open = set_attr($open, 'alt', (string)($value['alt'] ?? ''));
        $open = preg_replace('/\s(srcset|sizes)="[^"]*"/i', '', $open);
    } elseif ($kind === 'poster') {
        $open = set_attr($open, 'poster', $src);
    } else {
        $open = set_attr($open, 'src', $src);
    }
    return substr($html, 0, $f['start']) . $open . substr($html, $f['open_end']);
}

/** Section a field belongs to, for grouping in the page editor. */
function field_section(string $html, int $offset): string {
    $before = substr($html, 0, $offset);
    if (preg_match_all('/<(section|article|aside|footer)\b([^>]*)>/i', $before, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        $last = end($m);
        $attrs = parse_attrs($last[0][0]);
        if (!empty($attrs['aria-label'])) {
            return $attrs['aria-label'];
        }
        if (!empty($attrs['aria-labelledby'])) {
            $id = $attrs['aria-labelledby'];
            if (preg_match('/id="' . preg_quote($id, '/') . '"[^>]*>(.*?)<\/h[1-6]>/s', $html, $t)) {
                return trim(html_entity_decode(strip_tags($t[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
        if (!empty($attrs['id'])) {
            return ucfirst(str_replace('-', ' ', $attrs['id']));
        }
        if (!empty($attrs['class'])) {
            return ucfirst(str_replace(['-', '_'], ' ', preg_replace('/^[a-z]{2}-/', '', explode(' ', $attrs['class'])[0])));
        }
        return ucfirst($last[1][0]);
    }
    return 'Top of page';
}

/* -------------------------------------------------------- meta (SEO) fields */
function meta_get(string $html): array {
    $get = function (string $re) use ($html) {
        return preg_match($re, $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    };
    return [
        'title' => $get('#<title>(.*?)</title>#s'),
        'description' => $get('#<meta name="description" content="([^"]*)"#'),
        'og_image' => $get('#<meta property="og:image" content="([^"]*)"#'),
        'noindex' => (bool)preg_match('#<meta name="robots" content="[^"]*noindex#', $html),
    ];
}

function meta_set(string $html, array $meta): string {
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
    if (isset($meta['title']) && $meta['title'] !== '') {
        $html = preg_replace('#<title>.*?</title>#s', '<title>' . $e($meta['title']) . '</title>', $html, 1);
        $html = preg_replace('#(<meta property="og:title" content=")[^"]*(")#', '${1}' . $e($meta['title']) . '${2}', $html, 1);
    }
    if (isset($meta['description'])) {
        $d = $e($meta['description']);
        if (preg_match('#<meta name="description" content="[^"]*"#', $html)) {
            $html = preg_replace('#(<meta name="description" content=")[^"]*(")#', '${1}' . $d . '${2}', $html, 1);
        } else {
            $html = preg_replace('#</title>#', "</title>\n  <meta name=\"description\" content=\"$d\">", $html, 1);
        }
        $html = preg_replace('#(<meta property="og:description" content=")[^"]*(")#', '${1}' . $d . '${2}', $html, 1);
    }
    if (isset($meta['og_image']) && $meta['og_image'] !== '') {
        $img = $e(absolute_url($meta['og_image']));
        if (preg_match('#<meta property="og:image" content="[^"]*"#', $html)) {
            $html = preg_replace('#(<meta property="og:image" content=")[^"]*(")#', '${1}' . $img . '${2}', $html, 1);
        } else {
            $html = preg_replace('#(<meta property="og:url"[^>]*>)#', "$1\n  <meta property=\"og:image\" content=\"$img\">", $html, 1);
        }
    }
    if (isset($meta['noindex'])) {
        $has = (bool)preg_match('#<meta name="robots" content="[^"]*"\s*/?>#', $html);
        if ($meta['noindex'] && !$has) {
            $html = preg_replace('#</title>#', "</title>\n  <meta name=\"robots\" content=\"noindex\">", $html, 1);
        } elseif (!$meta['noindex'] && $has) {
            $html = preg_replace('#\s*<meta name="robots" content="[^"]*"\s*/?>#', '', $html, 1);
        }
    }
    return $html;
}

function absolute_url(string $u): string {
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    return site_url(ltrim(preg_replace('#^(\.\./)+#', '', $u), '/'));
}

/* --------------------------------------------------------------- sanitising */
function safe_url(string $u): string {
    $u = trim($u);
    if ($u === '' || preg_match('#^\s*(javascript|data|vbscript):#i', $u)) {
        return '';
    }
    return $u;
}

/** Keep only harmless inline markup (for page text fields). */
function clean_inline(string $html): string {
    return clean_html($html, [
        'em' => [], 'strong' => [], 'b' => [], 'i' => [], 'br' => [], 'small' => [], 'sup' => [], 'sub' => [], 'u' => [], 'mark' => [],
        'span' => ['class'], 'a' => ['href', 'target', 'rel', 'class'], 'time' => ['datetime'], 'abbr' => ['title'],
    ]);
}

/** Rich text for blog posts. */
function clean_rich(string $html): string {
    return clean_html($html, [
        'p' => [], 'h2' => ['id'], 'h3' => ['id'], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'em' => [], 'strong' => [], 'b' => [], 'i' => [], 'u' => [], 'br' => [], 'hr' => [], 'small' => [], 'sup' => [], 'sub' => [],
        'a' => ['href', 'target', 'rel'], 'figure' => ['class'], 'figcaption' => [], 'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [], 'span' => [],
    ]);
}

function clean_html(string $html, array $allowed): string {
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = null;
    foreach ($doc->getElementsByTagName('div') as $d) {
        if ($d->getAttribute('id') === '__root') {
            $root = $d;
            break;
        }
    }
    if (!$root) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }
    clean_node($root, $allowed);
    $out = '';
    foreach (iterator_to_array($root->childNodes) as $c) {
        $out .= $doc->saveHTML($c);
    }
    return trim($out);
}

function clean_node(DOMNode $node, array $allowed): void {
    foreach (iterator_to_array($node->childNodes) as $c) {
        if ($c instanceof DOMComment) {
            $node->removeChild($c);
            continue;
        }
        if (!($c instanceof DOMElement)) {
            continue;
        }
        $tag = strtolower($c->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'link', 'meta', 'base'], true)) {
            $node->removeChild($c);
            continue;
        }
        if (!isset($allowed[$tag])) {
            clean_node($c, $allowed);
            while ($c->firstChild) {
                $node->insertBefore($c->firstChild, $c);
            }
            $node->removeChild($c);
            continue;
        }
        foreach (iterator_to_array($c->attributes) as $a) {
            $name = strtolower($a->name);
            if (!in_array($name, $allowed[$tag], true)) {
                $c->removeAttribute($a->name);
            } elseif (in_array($name, ['href', 'src'], true) && safe_url($a->value) === '') {
                $c->removeAttribute($a->name);
            }
        }
        if ($tag === 'a' && $c->getAttribute('target') === '_blank') {
            $c->setAttribute('rel', 'noopener');
        }
        clean_node($c, $allowed);
    }
}

/* ------------------------------------------------------------ content edits */
/** Save changed fields of one page. Returns the number of changes. */
function save_page_fields(string $rel, array $values, ?array $meta = null): int {
    $html = read_page($rel);
    $orig = $html;
    $fields = cms_fields($html);
    $changed = 0;
    $uid = user()['id'] ?? null;
    foreach ($values as $key => $value) {
        if (!isset($fields[$key])) {
            continue;
        }
        $before = field_value($fields[$key]);
        $norm = is_array($value) ? array_map('strval', $value) : clean_inline((string)$value);
        if ($norm == $before) {
            continue;
        }
        $html = apply_field($html, $key, $norm);
        $fields = cms_fields($html);
        $stored = is_array($norm) ? json_encode($norm, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $norm;
        $origVal = is_array($before) ? json_encode($before, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $before;
        q('INSERT INTO content_edits (page, field, value, original, updated_by, updated_at) VALUES (?,?,?,?,?,?)
           ON CONFLICT(page, field) DO UPDATE SET value = excluded.value, updated_by = excluded.updated_by, updated_at = excluded.updated_at',
            [$rel, $key, $stored, $origVal, $uid, now()]);
        $changed++;
    }
    if ($meta !== null) {
        $before = meta_get($html);
        $html = meta_set($html, $meta);
        $after = meta_get($html);
        if ($after != $before) {
            q('INSERT INTO content_edits (page, field, value, original, updated_by, updated_at) VALUES (?,?,?,?,?,?)
               ON CONFLICT(page, field) DO UPDATE SET value = excluded.value, updated_by = excluded.updated_by, updated_at = excluded.updated_at',
                [$rel, '_meta', json_encode($after, JSON_UNESCAPED_UNICODE), json_encode($before, JSON_UNESCAPED_UNICODE), $uid, now()]);
            $changed++;
        }
    }
    if ($html !== $orig) {
        write_page($rel, $html);
    }
    return $changed;
}

/** Site-wide fields (footer-*) are applied to every page that has them. */
function save_global_field(string $key, string $value): int {
    $n = 0;
    foreach (site_pages() as $rel) {
        $html = read_page($rel);
        $f = cms_fields($html);
        if (isset($f[$key]) && field_value($f[$key]) !== clean_inline($value)) {
            write_page($rel, apply_field($html, $key, $value));
            $n++;
        }
    }
    q('INSERT INTO content_edits (page, field, value, updated_by, updated_at) VALUES (?,?,?,?,?)
       ON CONFLICT(page, field) DO UPDATE SET value = excluded.value, updated_by = excluded.updated_by, updated_at = excluded.updated_at',
        ['*', $key, clean_inline($value), user()['id'] ?? null, now()]);
    return $n;
}

/** Re-apply every saved edit (use after pages were regenerated by the build scripts). */
function reapply_edits(): int {
    $n = 0;
    $byPage = [];
    foreach (rows('SELECT * FROM content_edits ORDER BY page') as $e) {
        $byPage[$e['page']][] = $e;
    }
    foreach ($byPage as $page => $edits) {
        $targets = $page === '*' ? site_pages() : [$page];
        foreach ($targets as $rel) {
            try {
                $html = read_page($rel);
            } catch (Throwable $t) {
                continue;
            }
            $orig = $html;
            foreach ($edits as $e) {
                if ($e['field'] === '_meta') {
                    $html = meta_set($html, json_decode($e['value'], true) ?: []);
                    continue;
                }
                $v = $e['value'];
                $j = json_decode($v, true);
                $html = apply_field($html, $e['field'], is_array($j) ? $j : $v);
            }
            if ($html !== $orig) {
                write_page($rel, $html);
                $n++;
            }
        }
    }
    return $n;
}

/* ----------------------------------------------------------------- tracking */
function tracking_snippets(): array {
    $head = $body = [];
    $gtm = strtoupper(trim((string)setting('gtm_id')));
    $ga4 = strtoupper(trim((string)setting('ga4_id')));
    $pixel = trim((string)setting('meta_pixel_id'));
    $clarity = trim((string)setting('clarity_id'));
    $linkedin = trim((string)setting('linkedin_partner_id'));
    $gsc = trim((string)setting('gsc_verification'));
    $bing = trim((string)setting('bing_verification'));

    if ($gsc !== '' && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $gsc)) {
        $head[] = '<meta name="google-site-verification" content="' . $gsc . '">';
    }
    if ($bing !== '' && preg_match('/^[A-Za-z0-9]{10,64}$/', $bing)) {
        $head[] = '<meta name="msvalidate.01" content="' . $bing . '">';
    }
    if (preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm)) {
        $head[] = "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','$gtm');</script>";
        $body[] = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
    }
    if (preg_match('/^G-[A-Z0-9]{4,15}$/', $ga4)) {
        $head[] = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ga4 . '"></script>';
        $head[] = "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','$ga4');</script>";
    }
    if (preg_match('/^\d{8,20}$/', $pixel)) {
        $head[] = "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','$pixel');fbq('track','PageView');</script>";
        $body[] = '<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=' . $pixel . '&amp;ev=PageView&amp;noscript=1"></noscript>';
    }
    if (preg_match('/^[a-z0-9]{6,20}$/', $clarity)) {
        $head[] = "<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','$clarity');</script>";
    }
    if (preg_match('/^\d{4,12}$/', $linkedin)) {
        $head[] = "<script>_linkedin_partner_id='$linkedin';window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(_linkedin_partner_id);(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName('script')[0];var b=document.createElement('script');b.type='text/javascript';b.async=true;b.src='https://snap.licdn.com/li.lms-analytics/insight.min.js';s.parentNode.insertBefore(b,s);})(window.lintrk);</script>";
        $body[] = '<noscript><img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid=' . $linkedin . '&amp;fmt=gif"></noscript>';
    }
    $customHead = trim((string)setting('custom_head'));
    $customBody = trim((string)setting('custom_body'));
    if ($customHead !== '') {
        $head[] = $customHead;
    }
    if ($customBody !== '') {
        $body[] = $customBody;
    }
    return [implode("\n  ", $head), implode("\n  ", $body)];
}

function apply_tracking_html(string $html, array $snip): string {
    [$head, $body] = $snip;
    $html = preg_replace('#\n?\s*<!-- tracking:head -->.*?<!-- /tracking:head -->#s', '', $html);
    $html = preg_replace('#\n?\s*<!-- tracking:body -->.*?<!-- /tracking:body -->#s', '', $html);
    if ($head !== '') {
        $block = "  <!-- tracking:head -->\n  " . $head . "\n  <!-- /tracking:head -->\n";
        $html = preg_replace('#</head>#', str_replace('$', '\$', $block) . '</head>', $html, 1);
    }
    if ($body !== '') {
        $block = "\n  <!-- tracking:body -->\n  " . $body . "\n  <!-- /tracking:body -->";
        $html = preg_replace('#(<body\b[^>]*>)#', '$1' . str_replace('$', '\$', $block), $html, 1);
    }
    return $html;
}

/** Put the current tracking codes on every page. Returns pages changed. */
function apply_tracking_all(): int {
    $snip = tracking_snippets();
    $n = 0;
    foreach (site_pages() as $rel) {
        $html = read_page($rel);
        $new = apply_tracking_html($html, $snip);
        if ($new !== $html) {
            write_page($rel, $new, false);
            $n++;
        }
    }
    return $n;
}

/* ------------------------------------------------------------------ careers */
const PLUS_SVG = '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2v12M2 8h12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';

function careers_departments(): array {
    $d = json_decode((string)setting('careers_departments', '[]'), true);
    return is_array($d) && $d ? $d : ['Projects & site execution', 'Engineering & MEP', 'Planning, QS & contracts', 'Procurement', 'Safety & quality', 'Corporate functions'];
}

function replace_region(string $html, string $name, string $inner): string {
    $re = '#(<!-- cms:' . preg_quote($name, '#') . ' -->).*?(<!-- /cms:' . preg_quote($name, '#') . ' -->)#s';
    if (!preg_match($re, $html)) {
        throw new RuntimeException('Marker cms:' . $name . ' is missing from careers.html. Rebuild careers.html with scripts/build_company.py.');
    }
    return preg_replace_callback($re, function ($m) use ($inner) { return $m[1] . $inner . $m[2]; }, $html, 1);
}

/** Rebuild the openings, role options, team counts and JobPosting data on careers.html. */
function publish_careers(): void {
    $jobs = rows("SELECT * FROM jobs WHERE status = 'open' ORDER BY sort_order, id");
    $html = read_page('careers.html');
    $e = 'h';
    $counts = [];
    foreach ($jobs as $j) {
        $counts[$j['department']] = ($counts[$j['department']] ?? 0) + 1;
    }
    // roles + filter chips
    $chips = '<button class="co-filter is-active" type="button" data-filter="all" aria-pressed="true">All <span>' . count($jobs) . '</span></button>';
    foreach ($counts as $dept => $n) {
        $chips .= '<button class="co-filter" type="button" data-filter="' . $e(slugify($dept)) . '" aria-pressed="false">' . $e($dept) . ' <span>' . $n . '</span></button>';
    }
    $items = [];
    foreach ($jobs as $j) {
        $duties = json_decode($j['duties'], true) ?: [];
        $needs = json_decode($j['requirements'], true) ?: [];
        $meta = array_filter([$j['department'], $j['location'], $j['experience']]);
        $facts = array_filter([$j['employment_type'], $j['location'], $j['experience'] ? $j['experience'] . ' experience' : '']);
        $cols = '';
        if ($duties) {
            $cols .= '<div><h4>What you will do</h4><ul>' . implode('', array_map(function ($x) use ($e) { return '<li>' . $e($x) . '</li>'; }, $duties)) . '</ul></div>';
        }
        if ($needs) {
            $cols .= '<div><h4>What you bring</h4><ul>' . implode('', array_map(function ($x) use ($e) { return '<li>' . $e($x) . '</li>'; }, $needs)) . '</ul></div>';
        }
        $items[] = '          <li class="co-role" data-dept="' . $e(slugify($j['department'])) . '">
            <details>
              <summary>
                <span class="co-role__title">' . $e($j['title']) . '</span>
                <span class="co-role__meta">' . implode('', array_map(function ($m) use ($e) { return '<span>' . $e($m) . '</span>'; }, $meta)) . '</span>
                <span class="co-role__toggle">' . PLUS_SVG . '</span>
              </summary>
              <div class="co-role__body">
' . ($j['summary'] ? '                <p class="co-role__summary">' . $e($j['summary']) . "</p>\n" : '') . '                <div class="co-role__cols">' . $cols . '</div>
                <p class="co-role__facts">' . implode(' &middot; ', array_map($e, $facts)) . '</p>
                <a class="btn btn--navy co-role__apply" href="#apply" data-apply="' . $e($j['slug']) . '">Apply for this role</a>
              </div>
            </details>
          </li>';
    }
    if (!$items) {
        $items[] = '          <li class="co-empty">There are no open roles right now. Send a general application below and we will keep your resume on file.</li>';
    }
    $html = replace_region($html, 'roles', "\n        <div class=\"co-filters\" role=\"group\" aria-label=\"Filter by team\"" . ($jobs ? '' : ' hidden') . ">$chips</div>\n        <ul class=\"co-roles\" data-roles>\n" . implode("\n", $items) . "\n        </ul>\n        ");
    // form options
    $opts = '<option value="general">General application</option>';
    foreach ($jobs as $j) {
        $opts .= '<option value="' . $e($j['slug']) . '">' . $e($j['title']) . '</option>';
    }
    $html = replace_region($html, 'role-options', $opts);
    // teams with counts
    $teams = [];
    foreach (careers_departments() as $i => $dept) {
        $n = $counts[$dept] ?? 0;
        $teams[] = '          <li class="co-dept" data-reveal style="--i:' . ($i % 3) . '"><span>' . $e($dept) . '</span><b>' . ($n ? $n . ' open' : '&nbsp;') . '</b></li>';
    }
    $html = replace_region($html, 'teams', "\n" . implode("\n", $teams) . "\n");
    // JobPosting structured data for Google Jobs
    $ld = [];
    foreach ($jobs as $j) {
        $desc = '<p>' . $e($j['summary']) . '</p>';
        foreach (['What you will do' => 'duties', 'What you bring' => 'requirements'] as $label => $col) {
            $list = json_decode($j[$col], true) ?: [];
            if ($list) {
                $desc .= '<p>' . $label . ':</p><ul>' . implode('', array_map(function ($x) use ($e) { return '<li>' . $e($x) . '</li>'; }, $list)) . '</ul>';
            }
        }
        $types = ['Full-time' => 'FULL_TIME', 'Part-time' => 'PART_TIME', 'Contract' => 'CONTRACTOR', 'Internship' => 'INTERN'];
        $ld[] = [
            '@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $j['title'], 'description' => $desc,
            'datePosted' => substr($j['created_at'], 0, 10),
            'validThrough' => date('Y-m-d', strtotime($j['updated_at'] ?: $j['created_at']) + 86400 * 90),
            'employmentType' => $types[$j['employment_type']] ?? 'FULL_TIME',
            'hiringOrganization' => ['@type' => 'Organization', 'name' => 'Argentec', 'sameAs' => site_url(), 'logo' => site_url('assets/img/argentec-logo.svg')],
            'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $j['location'], 'addressRegion' => 'Maharashtra', 'addressCountry' => 'IN']],
            'directApply' => true,
        ];
    }
    $ldHtml = '';
    foreach ($ld as $x) {
        $ldHtml .= "\n  <script type=\"application/ld+json\">" . json_encode($x, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
    }
    if (str_contains($html, '<!-- cms:jobs-ld -->')) {
        $html = replace_region($html, 'jobs-ld', $ldHtml . "\n  ");
    }
    write_page('careers.html', $html);
}

/* --------------------------------------------------------------------- blog */
function blog_template(): array {
    $src = is_file(SITE_ROOT . '/blog/index.html') ? (string)file_get_contents(SITE_ROOT . '/blog/index.html') : '';
    $hStart = strpos($src, '<header class="site-header"');
    $hEnd = strpos($src, '<main id="main">');
    $fStart = strpos($src, '<!-- ============ FOOTER ============ -->');
    $fEnd = strpos($src, '</footer>');
    if ($hStart === false || $hEnd === false || $fStart === false || $fEnd === false) {
        throw new RuntimeException('blog/index.html is missing its header or footer; it is used as the template for blog pages.');
    }
    return [
        'header' => rtrim(substr($src, $hStart, $hEnd - $hStart)),
        'footer' => substr($src, $fStart, $fEnd + 9 - $fStart),
    ];
}

const ARROW_SVG = '<svg viewBox="0 0 32 12" width="32" height="12" aria-hidden="true"><path d="M0 6h30M25 1l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.3"/></svg>';

function blog_head(string $title, string $desc, string $canonical, ?string $image, bool $noindex, string $ogType): string {
    $e = 'h';
    return '<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>' . $e($title) . '</title>
  <meta name="description" content="' . $e($desc) . '">' . ($noindex ? "\n  <meta name=\"robots\" content=\"noindex\">" : '') . '
  <meta name="theme-color" content="#ffffff">
  <link rel="canonical" href="' . $e($canonical) . '">
  <link rel="icon" type="image/svg+xml" href="../assets/img/argentec-logo.svg">

  <meta property="og:type" content="' . $ogType . '">
  <meta property="og:title" content="' . $e($title) . '">
  <meta property="og:description" content="' . $e($desc) . '">
  <meta property="og:url" content="' . $e($canonical) . '">' . ($image ? "\n  <meta property=\"og:image\" content=\"" . $e(absolute_url($image)) . '">' : '') . '

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,600;1,8..60,500;1,8..60,600&display=swap">
  <link rel="stylesheet" href="../css/style.css?v=' . asset_v('css/style.css') . '">
  <link rel="stylesheet" href="../css/blog.css?v=' . asset_v('css/blog.css') . '">
  <link rel="stylesheet" href="../css/footer.css?v=' . asset_v('css/footer.css') . '">
</head>';
}

function blog_scripts(): string {
    return '  <script src="../js/main.js?v=' . asset_v('js/main.js') . '"></script>
  <script src="../js/expertise.js?v=' . asset_v('js/expertise.js') . '"></script>
  <script src="../js/blog.js?v=' . asset_v('js/blog.js') . '"></script>';
}

function blog_crumbs(array $trail): array {
    $parts = [];
    $items = [];
    foreach ($trail as $i => [$label, $href, $abs]) {
        $parts[] = $href ? '<li><a href="' . h($href) . '">' . h($label) . '</a></li>' : '<li><span aria-current="page">' . h($label) . '</span></li>';
        $it = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $label];
        if ($abs !== null) {
            $it['item'] = site_url($abs);
        }
        $items[] = $it;
    }
    return [
        '<nav class="xp-crumbs" aria-label="Breadcrumb"><ol>' . implode('', $parts) . '</ol></nav>',
        '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>',
    ];
}

function post_view(array $p): array {
    $body = (string)$p['body'];
    $words = str_word_count(strip_tags($body));
    $ts = strtotime($p['published_at'] ?: $p['created_at']);
    // ids on h2 for the "In this article" list
    $headings = [];
    $used = [];
    $body = preg_replace_callback('#<h2(?:\s[^>]*)?>(.*?)</h2>#s', function ($m) use (&$headings, &$used) {
        $text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $id = slugify($text);
        while (isset($used[$id])) {
            $id .= '-2';
        }
        $used[$id] = true;
        $headings[] = [$id, $text];
        return '<h2 id="' . $id . '">' . $m[1] . '</h2>';
    }, $body);
    return $p + [
        'html' => $body,
        'headings' => $headings,
        'minutes' => max(1, (int)ceil($words / 200)),
        'iso' => date('Y-m-d', $ts),
        'date_label' => date('j F Y', $ts),
        'cover_src' => $p['cover'] ?: 'assets/img/projects/placeholder-1.svg',
    ];
}

function blog_card(array $p, int $i, bool $featured = false): string {
    $e = 'h';
    return '          <li class="bl-card' . ($featured ? ' bl-card--featured' : '') . '" data-reveal style="--i:' . ($i % 3) . '">
            <a href="' . $e($p['slug']) . '.html">
              <div class="bl-card__media"><img src="../' . $e($p['cover_src']) . '" alt="' . $e($p['cover_alt']) . '" width="1200" height="750" loading="lazy"></div>
              <div class="bl-card__body">
                <p class="bl-meta"><span>' . $e($p['category']) . '</span><time datetime="' . $p['iso'] . '">' . $p['date_label'] . '</time><span>' . $p['minutes'] . ' min read</span></p>
                <h2 class="bl-card__title">' . $e($p['title']) . '</h2>
                <p class="bl-card__excerpt">' . $e($p['excerpt']) . '</p>
                <span class="bl-card__more">Read article ' . ARROW_SVG . '</span>
              </div>
            </a>
          </li>';
}

/** Regenerate blog/index.html and every published post; remove pages of unpublished posts. */
function publish_blog(): int {
    $t = blog_template();
    $posts = array_map('post_view', rows("SELECT * FROM posts WHERE status = 'published' ORDER BY COALESCE(published_at, created_at) DESC, id DESC"));
    $snip = tracking_snippets();

    // hub
    [$crumbs, $crumbsLd] = blog_crumbs([['Home', '../index.html', ''], ['Blog', null, null]]);
    $cards = [];
    foreach ($posts as $i => $p) {
        $cards[] = blog_card($p, $i, $i === 0);
    }
    $ld = ['@context' => 'https://schema.org', '@type' => 'Blog', 'name' => 'Argentec Blog', 'url' => site_url('blog/'),
        'blogPost' => array_values(array_map(function ($p) {
            return ['@type' => 'BlogPosting', 'headline' => $p['title'], 'datePublished' => $p['iso'], 'url' => site_url('blog/' . $p['slug'] . '.html')];
        }, array_filter($posts, function ($p) { return !$p['noindex']; })))];
    $hubTitle = setting('blog_title', 'Blog | Argentec');
    $hubDesc = setting('blog_description', 'Articles from Argentec on planning, building and delivering construction projects in Mumbai.');
    $hub = blog_head($hubTitle, $hubDesc, site_url('blog/'), null, false, 'website') . '

<body class="page-blog header-solid">

  ' . $t['header'] . '
  <main id="main">
    <section class="bl-hero" aria-labelledby="bl-title">
      <div class="bl-inner">
        ' . $crumbs . '
        <p class="bl-eyebrow">Blog</p>
        <h1 class="bl-hero__title" id="bl-title">' . clean_inline(setting('blog_heading', 'Notes from <em>the site.</em>')) . '</h1>
        <p class="bl-hero__lead">' . h(setting('blog_lead', 'Practical thinking on planning, building and delivering projects in Mumbai, from the people who run them.')) . '</p>
      </div>
    </section>

    <section class="bl-list" aria-label="Articles">
      <div class="bl-inner">
        <ul class="bl-grid">
' . ($cards ? implode("\n", $cards) : '          <li class="bl-empty">Articles are on their way.</li>') . '
        </ul>
      </div>
    </section>
  </main>

  ' . $t['footer'] . '

' . blog_scripts() . '
  ' . $crumbsLd . '
  <script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>
</body>
</html>
';
    write_page('blog/index.html', apply_tracking_html($hub, $snip));

    // articles
    $keep = ['index.html' => true];
    foreach ($posts as $p) {
        $keep[$p['slug'] . '.html'] = true;
        write_page('blog/' . $p['slug'] . '.html', apply_tracking_html(render_post($p, $posts, $t), $snip));
    }
    // remove pages of posts that were unpublished, deleted or renamed
    foreach (glob(SITE_ROOT . '/blog/*.html') ?: [] as $f) {
        if (!isset($keep[basename($f)])) {
            @unlink($f);
        }
    }
    write_sitemap();
    return count($posts);
}

/** Full HTML of one blog post page. $posts = published posts (for "More articles"). */
function render_post(array $p, array $posts, ?array $t = null): string {
    $t = $t ?: blog_template();
    [$crumbs, $crumbsLd] = blog_crumbs([['Home', '../index.html', ''], ['Blog', 'index.html', 'blog/'], [$p['title'], null, null]]);
    $others = array_slice(array_values(array_filter($posts, function ($q) use ($p) { return $q['id'] !== $p['id']; })), 0, 3);
    $more = '';
    if ($others) {
        $oc = [];
        foreach ($others as $k => $q) {
            $oc[] = blog_card($q, $k);
        }
        $more = '    <section class="bl-more" aria-labelledby="more-title">
  <div class="bl-inner">
    <h2 class="bl-more__title" id="more-title">More articles</h2>
    <ul class="bl-grid">
' . implode("\n", $oc) . '
    </ul>
  </div>
</section>
';
    }
    $toc = '';
    if (count($p['headings']) >= 3) {
        $toc = '<nav class="bl-toc" aria-label="In this article"><p>In this article</p><ol>' . implode('', array_map(function ($hd) {
            return '<li><a href="#' . h($hd[0]) . '">' . h($hd[1]) . '</a></li>';
        }, $p['headings'])) . '</ol></nav>';
    }
    $url = site_url('blog/' . $p['slug'] . '.html');
    $ld = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $p['title'], 'description' => (string)$p['excerpt'],
        'datePublished' => $p['iso'], 'dateModified' => substr((string)($p['updated_at'] ?: $p['published_at']), 0, 10),
        'image' => absolute_url($p['cover_src']), 'author' => ['@type' => 'Organization', 'name' => $p['author']],
        'publisher' => ['@type' => 'Organization', 'name' => 'Argentec', 'logo' => ['@type' => 'ImageObject', 'url' => site_url('assets/img/argentec-logo.svg')]],
        'mainEntityOfPage' => $url];
    $page = blog_head(($p['seo_title'] ?: $p['title'] . ' | Argentec Blog'), (string)($p['seo_description'] ?: $p['excerpt']), $url, $p['cover_src'], (bool)$p['noindex'], 'article') . '

<body class="page-blog header-solid">

  ' . $t['header'] . '
  <main id="main">
<article class="bl-article">
  <header class="bl-article__head">
    <div class="bl-inner bl-inner--narrow">
      ' . $crumbs . '
      <p class="bl-meta"><span>' . h($p['category']) . '</span><time datetime="' . $p['iso'] . '">' . $p['date_label'] . '</time><span>' . $p['minutes'] . ' min read</span></p>
      <h1 class="bl-article__title">' . h($p['title']) . '</h1>
      <p class="bl-article__lead">' . h($p['excerpt']) . '</p>
      <p class="bl-article__byline">By ' . h($p['author']) . '</p>
    </div>
    <div class="bl-inner">
      <figure class="bl-article__cover"><img src="../' . h($p['cover_src']) . '" alt="' . h($p['cover_alt']) . '" width="1200" height="750"></figure>
    </div>
  </header>

  <div class="bl-inner bl-article__layout">
    <aside class="bl-article__aside">
      ' . $toc . '
      <button class="bl-share" type="button" data-copy-link>
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <span data-copy-label>Copy link</span>
      </button>
    </aside>
    <div class="bl-prose">
      ' . $p['html'] . '
    </div>
  </div>

  <div class="bl-inner bl-inner--narrow bl-article__foot">
    <a class="bl-back" href="index.html"><svg viewBox="0 0 32 12" width="32" height="12" aria-hidden="true"><path d="M32 6H2M7 1L2 6l5 5" fill="none" stroke="currentColor" stroke-width="1.3"/></svg><span>All articles</span></a>
  </div>
</article>
' . $more . '  </main>

  ' . $t['footer'] . '

' . blog_scripts() . '
  ' . $crumbsLd . '
  <script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>
</body>
</html>
';
    return $page;
}

/* ----------------------------------------------------------- sitemap/robots */
function write_sitemap(): int {
    $urls = [];
    foreach (site_pages() as $rel) {
        $html = (string)@file_get_contents(SITE_ROOT . '/' . $rel);
        if (preg_match('#<meta name="robots" content="[^"]*noindex#', $html)) {
            continue;
        }
        $path = preg_replace('#(^|/)index\.html$#', '$1', $rel);
        $prio = $rel === 'index.html' ? '1.0' : (substr_count($rel, '/') === 0 || str_ends_with($rel, '/index.html') ? '0.8' : '0.6');
        $urls[] = '  <url><loc>' . h(site_url($path)) . '</loc><lastmod>' . date('Y-m-d', (int)filemtime(SITE_ROOT . '/' . $rel)) . '</lastmod><priority>' . $prio . '</priority></url>';
    }
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" . implode("\n", $urls) . "\n</urlset>\n";
    file_put_contents(SITE_ROOT . '/sitemap.xml', $xml);
    $robots = "User-agent: *\nDisallow: /admin/\nDisallow: /api/\n\nSitemap: " . site_url('sitemap.xml') . "\n";
    file_put_contents(SITE_ROOT . '/robots.txt', $robots);
    return count($urls);
}
