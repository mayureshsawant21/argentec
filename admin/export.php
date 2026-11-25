<?php
/* CSV export of a submission list, with the same filters as on screen.
   Opens in Excel (UTF-8 with BOM). */
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/submissions.php';
$form = (string)($_GET['form'] ?? '');
if (!isset(FORMS[$form])) {
    exit('Unknown form');
}
require_login($form === 'careers' ? 'careers' : 'enquiries');
[$where, $params] = submission_filters($form, $_GET);
$list = rows("SELECT * FROM submissions WHERE $where ORDER BY created_at DESC", $params);

// columns: fixed ones first, then every answer field that appears
$keys = [];
foreach ($list as $s) {
    foreach (array_keys(json_decode($s['data'], true) ?: []) as $k) {
        $keys[$k] = true;
    }
}
unset($keys['name'], $keys['email'], $keys['phone'], $keys['role'], $keys['role_title'], $keys['enquiry_key']);
$cols = array_keys($keys);

function cell($v): string {
    $v = (string)$v;
    if ($v !== '' && strpbrk($v[0], '=+-@') !== false) {
        $v = "'" . $v; // keep spreadsheets from running it as a formula
    }
    return '"' . str_replace('"', '""', $v) . '"';
}
log_activity('Exported CSV', FORMS[$form]['label'] . ' · ' . count($list) . ' rows');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="argentec-' . $form . '-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
$head = ['Reference', 'Received', 'Status', 'Name', 'Email', 'Mobile'];
if ($form === 'careers') {
    $head[] = 'Designation';
}
$head = array_merge($head, array_map('field_label', $cols), ['Resume', 'Notes']);
fwrite($out, implode(',', array_map('cell', $head)) . "\r\n");
foreach ($list as $s) {
    $d = json_decode($s['data'], true) ?: [];
    $line = [$s['reference'], $s['created_at'], statuses($form)[$s['status']] ?? $s['status'], $s['name'], $s['email'], $s['phone']];
    if ($form === 'careers') {
        $line[] = $s['designation'] ?: 'General application';
    }
    foreach ($cols as $k) {
        $line[] = $d[$k] ?? '';
    }
    $line[] = $s['file_path'] ? site_url('admin/download.php?id=' . $s['id']) : '';
    $line[] = $s['notes'];
    fwrite($out, implode(',', array_map('cell', $line)) . "\r\n");
}
fclose($out);
