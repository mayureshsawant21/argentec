<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
$u = require_login('backup');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!class_exists('ZipArchive')) {
        flash('error', 'The hosting does not have the PHP zip extension enabled.');
        redirect('backup.php');
    }
    $what = (array)($_POST['what'] ?? []);
    $tmp = data_dir() . '/backups/argentec-backup-' . date('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        flash('error', 'Could not create the backup file.');
        redirect('backup.php');
    }
    // a consistent copy of the database
    $dbCopy = data_dir() . '/backups/db-' . bin2hex(random_bytes(4)) . '.sqlite';
    try {
        db()->exec('VACUUM INTO ' . db()->quote($dbCopy));
    } catch (Throwable $t) {
        copy(data_dir() . '/argentec.sqlite', $dbCopy); // older SQLite
    }
    $zip->addFile($dbCopy, 'database/argentec.sqlite');
    $add = function (string $dir, string $prefix) use ($zip) {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), $prefix . '/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($dir))), '/'));
            }
        }
    };
    if (in_array('resumes', $what, true)) {
        $add(data_dir() . '/resumes', 'resumes');
    }
    if (in_array('uploads', $what, true)) {
        $add(SITE_ROOT . '/assets/uploads', 'assets/uploads');
    }
    if (in_array('pages', $what, true)) {
        foreach (glob(SITE_ROOT . '/{*.html,*/*.html,sitemap.xml,robots.txt}', GLOB_BRACE) ?: [] as $f) {
            $rel = ltrim(substr($f, strlen(SITE_ROOT)), '/');
            if (!preg_match('#^(admin|api|data)/#', $rel)) {
                $zip->addFile($f, 'website/' . $rel);
            }
        }
    }
    $zip->close();
    @unlink($dbCopy);
    log_activity('Downloaded backup', implode(', ', array_merge(['database'], $what)));
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . basename($tmp) . '"');
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
$dbSize = filesize(data_dir() . '/argentec.sqlite');
$resumes = 0;
$resumeBytes = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(data_dir() . '/resumes', FilesystemIterator::SKIP_DOTS)) as $f) {
    $resumes++;
    $resumeBytes += $f->getSize();
}
page_start('Backup', 'backup', '', 'Download a copy of everything the admin panel stores.');
?>
<form method="post" class="card form-card form-card--narrow">
  <?= csrf_field() ?>
  <p>The backup is a zip file. Keep it somewhere safe, such as Google Drive. It contains personal data of applicants and customers.</p>
  <label class="check"><input type="checkbox" checked disabled> Database: submissions, job postings, blog posts, page edits, users and settings (<?= bytes((int)$dbSize) ?>)</label>
  <label class="check"><input type="checkbox" name="what[]" value="resumes" checked> Resumes (<?= $resumes ?> files, <?= bytes($resumeBytes) ?>)</label>
  <label class="check"><input type="checkbox" name="what[]" value="uploads" checked> Media library uploads</label>
  <label class="check"><input type="checkbox" name="what[]" value="pages"> Website pages as they are now</label>
  <div class="form-actions"><button class="btn btn--primary" type="submit">Download backup</button></div>
  <p class="muted small">Hostinger also keeps automatic backups (hPanel → Files → Backups).</p>
</form>
<?php page_end();
