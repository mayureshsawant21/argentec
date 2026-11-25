<?php
/* Filters shared by the submission list and the CSV export. */

function submission_filters(string $form, array $in): array {
    $where = ['form = ?'];
    $p = [$form];
    $f = [
        'q' => trim((string)($in['q'] ?? '')),
        'status' => (string)($in['status'] ?? ''),
        'from' => (string)($in['from'] ?? ''),
        'to' => (string)($in['to'] ?? ''),
        'designation' => (string)($in['designation'] ?? ''),
        'experience' => (string)($in['experience'] ?? ''),
        'range' => (string)($in['range'] ?? ''),
    ];
    // quick ranges
    if ($f['range'] !== '' && $f['from'] === '' && $f['to'] === '') {
        $map = ['today' => 'today', '7d' => '-6 days', '30d' => '-29 days', 'month' => 'first day of this month'];
        if (isset($map[$f['range']])) {
            $f['from'] = date('Y-m-d', strtotime($map[$f['range']]));
            $f['to'] = date('Y-m-d');
        }
    }
    if ($f['q'] !== '') {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR reference LIKE ? OR subject LIKE ? OR data LIKE ?)';
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
        array_push($p, $like, $like, $like, $like, $like, $like);
        $where[count($where) - 1] = str_replace('LIKE ?', "LIKE ? ESCAPE '\\'", $where[count($where) - 1]);
    }
    if ($f['status'] !== '' && isset(statuses($form)[$f['status']])) {
        $where[] = 'status = ?';
        $p[] = $f['status'];
    } elseif ($f['status'] === '') {
        // spam is hidden unless asked for
        $where[] = "status <> 'spam'";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
        $where[] = 'date(created_at) >= ?';
        $p[] = $f['from'];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
        $where[] = 'date(created_at) <= ?';
        $p[] = $f['to'];
    }
    if ($form === 'careers' && $f['designation'] !== '') {
        $where[] = "COALESCE(designation, 'General application') = ?";
        $p[] = $f['designation'];
    }
    if ($form === 'careers' && $f['experience'] !== '') {
        $where[] = "json_extract(data, '$.experience') = ?";
        $p[] = $f['experience'];
    }
    return [implode(' AND ', $where), $p, $f];
}
