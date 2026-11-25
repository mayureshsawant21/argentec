<?php
/* Streams a resume to signed-in users with access to Careers. Files are
   kept outside the public website, so this is the only way to reach them. */
require_once __DIR__ . '/inc/bootstrap.php';
start_session();
$s = row('SELECT * FROM submissions WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
if (!user()) {
    redirect('login.php');
}
if (!$s || !$s['file_path'] || !can($s['form'] === 'careers' ? 'careers' : 'enquiries')) {
    http_response_code(404);
    exit('File not found.');
}
$path = realpath(data_dir() . '/resumes/' . $s['file_path']);
$base = realpath(data_dir() . '/resumes');
if (!$path || !$base || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
// a tidy file name: "Asha Patil - Site Engineer - CAR-261125-1234.pdf"
$nice = preg_replace('/[^\w\- .()]+/u', '', $s['name'] . ' - ' . ($s['designation'] ?: 'Application') . ' - ' . $s['reference']) . '.' . $ext;
$inline = !empty($_GET['view']) && $ext === 'pdf';
log_activity('Downloaded resume', $s['reference'] . ' · ' . $s['name']);
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $nice) . '"; filename*=UTF-8\'\'' . rawurlencode($nice));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
