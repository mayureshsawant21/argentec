<?php
/* Preview of a blog post (draft or published) exactly as it will look. */
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/site.php';
require_login('blog');
$p = row('SELECT * FROM posts WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!$p) {
    http_response_code(404);
    exit('Post not found');
}
$posts = array_map('post_view', rows("SELECT * FROM posts WHERE status = 'published' ORDER BY COALESCE(published_at, created_at) DESC"));
$html = render_post(post_view($p), $posts);
// make relative links work from /admin/ and mark it as a preview
$html = str_replace('<head>', "<head>\n  <base href=\"../blog/\">\n  <meta name=\"robots\" content=\"noindex\">", $html);
$html = preg_replace('#(<body\b[^>]*>)#', '$1<div style="position:fixed;z-index:9999;left:50%;bottom:16px;transform:translateX(-50%);background:#0e1b2e;color:#fff;padding:10px 16px;font:600 13px/1.3 Montserrat,sans-serif;letter-spacing:.04em">PREVIEW · ' . ($p['status'] === 'published' ? 'published' : 'draft, not on the website') . '</div>', $html, 1);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
echo $html;
