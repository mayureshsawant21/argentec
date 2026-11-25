<?php
/* Media library helpers: uploads go to assets/uploads/YYYY/MM/. */

const MEDIA_TYPES = [
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
    'gif' => ['image/gif'], 'avif' => ['image/avif'],
    'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
    'pdf' => ['application/pdf'],
];

function media_root(): string {
    return SITE_ROOT . '/assets/uploads';
}

/** Store an uploaded file; returns its path relative to the site root, e.g. assets/uploads/2026/11/site-photo-3fa2.jpg */
function media_store(array $f): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $codes = [UPLOAD_ERR_INI_SIZE => 'The file is larger than the hosting allows.', UPLOAD_ERR_FORM_SIZE => 'The file is too large.', UPLOAD_ERR_NO_FILE => 'No file was chosen.'];
        throw new RuntimeException($codes[$f['error'] ?? 0] ?? 'The upload failed.');
    }
    if ($f['size'] > (int)cfg('max_media_mb', 25) * 1048576) {
        throw new RuntimeException('Files can be up to ' . cfg('max_media_mb', 25) . ' MB.');
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(MEDIA_TYPES[$ext]) || !in_array($mime, MEDIA_TYPES[$ext], true)) {
        throw new RuntimeException('Use JPG, PNG, WebP, GIF or AVIF images, MP4 or WebM video, or PDF.');
    }
    $dir = media_root() . '/' . date('Y/m');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $base = slugify(pathinfo((string)$f['name'], PATHINFO_FILENAME));
    $name = substr($base, 0, 60) . '-' . substr(bin2hex(random_bytes(3)), 0, 5) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('The file could not be saved. Check folder permissions.');
    }
    @chmod($dir . '/' . $name, 0644);
    return 'assets/uploads/' . date('Y/m') . '/' . $name;
}

/** Uploaded files plus the images already used on the site. */
function media_list(string $type = ''): array {
    $out = [];
    foreach ([media_root(), SITE_ROOT . '/assets/img', SITE_ROOT . '/assets/video'] as $root) {
        if (!is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $ext = strtolower($f->getExtension());
            if (!isset(MEDIA_TYPES[$ext]) && $ext !== 'svg') {
                continue;
            }
            $kind = in_array($ext, ['mp4', 'webm'], true) ? 'video' : ($ext === 'pdf' ? 'document' : 'image');
            if ($type !== '' && $kind !== $type) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen(SITE_ROOT))), '/');
            $out[] = ['path' => $rel, 'name' => $f->getFilename(), 'kind' => $kind, 'size' => $f->getSize(), 'time' => $f->getMTime(),
                'uploaded' => str_starts_with($rel, 'assets/uploads/')];
        }
    }
    usort($out, function ($a, $b) { return $b['time'] <=> $a['time']; });
    return $out;
}
