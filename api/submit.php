<?php
/* Argentec: every website form posts here (see window.ArgentecForms in
   js/main.js). Saves the submission for the admin panel, stores an uploaded
   resume privately, emails a notification if one is configured, and
   answers with the reference number shown to the visitor. */

declare(strict_types=1);
require_once __DIR__ . '/../admin/inc/bootstrap.php';

header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$form = (string)($_POST['form'] ?? '');
if (!isset(FORMS[$form])) {
    json_out(['ok' => false, 'error' => 'Unknown form.'], 400);
}

function reference(string $form): string {
    do {
        $ref = FORMS[$form]['prefix'] . '-' . date('ymd') . '-' . random_int(1000, 9999);
    } while (val('SELECT 1 FROM submissions WHERE reference = ?', [$ref]));
    return $ref;
}

// Spam: the hidden "website" field is only filled in by bots, and people
// take more than two seconds to fill in a form. Bots get a normal-looking
// answer so they do not retry, but nothing is stored.
$elapsed = (int)($_POST['_elapsed'] ?? 99);
if (trim((string)($_POST['website'] ?? '')) !== '' || $elapsed < 2) {
    json_out(['ok' => true, 'reference' => FORMS[$form]['prefix'] . '-' . date('ymd') . '-' . random_int(1000, 9999)]);
}

// Rate limit: 8 submissions per address per hour.
$recent = (int)val("SELECT COUNT(*) FROM submissions WHERE ip = ? AND created_at > datetime('now','localtime','-1 hour')", [client_ip()]);
if ($recent >= 8) {
    json_out(['ok' => false, 'error' => 'Too many submissions from your connection. Please try again in an hour, or call us.'], 429);
}

// Collect fields: plain text, length-limited, at most 60 fields.
$data = [];
foreach ($_POST as $k => $v) {
    if (!is_string($k) || $k === '' || $k[0] === '_' || in_array($k, ['form', 'website'], true) || is_array($v)) {
        continue;
    }
    if (!preg_match('/^[a-z][a-z0-9_-]{0,40}$/i', $k) || count($data) >= 60) {
        continue;
    }
    $v = trim(str_replace("\0", '', (string)$v));
    if ($v === '') {
        continue;
    }
    $data[$k] = mb_substr($v, 0, $k === 'message' || $k === 'references' || $k === 'description' ? 5000 : 500);
}

$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
if ($name === '' || ($email === '' && $phone === '')) {
    json_out(['ok' => false, 'error' => 'Please fill in your name and contact details.'], 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Please check the email address.'], 422);
}
if ($phone !== '' && !preg_match('/^[0-9+ ()\-]{8,20}$/', $phone)) {
    json_out(['ok' => false, 'error' => 'Please check the mobile number.'], 422);
}

// Subject and designation, used for the admin panel's lists and filters
$subject = '';
$designation = null;
switch ($form) {
    case 'contact':
        $subject = $data['enquiry_type'] ?? 'Enquiry';
        break;
    case 'careers':
        $designation = $data['role_title'] ?? 'General application';
        $subject = $designation;
        break;
    case 'vendor':
        $subject = trim(($data['vendor_type'] ?? 'Vendor') . ' · ' . ($data['company'] ?? ''), ' ·');
        break;
    case 'development':
        $subject = ['homebuyer' => 'Homebuyer', 'broker' => 'Broker', 'society' => 'Housing society'][$data['role'] ?? ''] ?? 'Interest';
        break;
}

// Resume (careers): PDF or Word, checked by content, stored outside the public site.
$filePath = $fileName = null;
$fileSize = null;
if (!empty($_FILES['resume']) && ($_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['resume'];
    $max = (int)cfg('max_resume_mb', 5) * 1048576;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > $max || !is_uploaded_file($f['tmp_name'])) {
        json_out(['ok' => false, 'error' => 'The resume could not be uploaded. Please use a PDF or Word file up to ' . cfg('max_resume_mb', 5) . ' MB.'], 422);
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ok = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    ];
    if (!isset($ok[$ext]) || !in_array($mime, $ok[$ext], true)) {
        json_out(['ok' => false, 'error' => 'Please upload your resume as a PDF or Word file.'], 422);
    }
    $dir = data_dir() . '/resumes/' . date('Y-m');
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $stored = date('Y-m') . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], data_dir() . '/resumes/' . $stored)) {
        json_out(['ok' => false, 'error' => 'The resume could not be saved. Please try again.'], 500);
    }
    @chmod(data_dir() . '/resumes/' . $stored, 0640);
    $filePath = $stored;
    $fileName = mb_substr(preg_replace('/[^\w.\- ()]+/u', '_', basename((string)$f['name'])), -120);
    $fileSize = (int)$f['size'];
} elseif ($form === 'careers') {
    json_out(['ok' => false, 'error' => 'Please attach your resume.'], 422);
}

$ref = reference($form);
q('INSERT INTO submissions (form, reference, name, email, phone, subject, designation, data, file_path, file_name, file_size, ip, user_agent, page)
   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    $form, $ref, mb_substr($name, 0, 120), mb_substr($email, 0, 160), mb_substr($phone, 0, 20), mb_substr($subject, 0, 200), $designation,
    json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $filePath, $fileName, $fileSize,
    client_ip(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300), mb_substr((string)($_POST['_page'] ?? ''), 0, 200),
]);

// Email notification (Settings → Notifications)
$to = trim((string)setting('notify_' . $form, setting('notify_all', '')));
if ($to !== '') {
    $lines = [FORMS[$form]['label'] . ' — ' . $ref, ''];
    foreach ($data as $k => $v) {
        $lines[] = field_label($k) . ': ' . $v;
    }
    if ($fileName) {
        $lines[] = 'Resume: ' . $fileName . ' (download it from the admin panel)';
    }
    $lines[] = '';
    $lines[] = 'Open in the admin panel: ' . site_url('admin/submission.php?ref=' . rawurlencode($ref));
    $from = (string)cfg('mail_from', 'no-reply@argentec.com');
    $headers = 'From: Argentec Website <' . $from . ">\r\nContent-Type: text/plain; charset=UTF-8";
    if ($email !== '') {
        $headers .= "\r\nReply-To: " . preg_replace('/[\r\n]+/', '', $email);
    }
    $subjectLine = '=?UTF-8?B?' . base64_encode('[' . FORMS[$form]['short'] . '] ' . $name . ' — ' . $ref) . '?=';
    foreach (array_filter(array_map('trim', explode(',', $to)), function ($a) { return filter_var($a, FILTER_VALIDATE_EMAIL); }) as $addr) {
        @mail($addr, $subjectLine, implode("\n", $lines), $headers);
    }
}

json_out(['ok' => true, 'reference' => $ref]);
