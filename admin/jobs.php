<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/site.php';
$u = require_login('jobs');
const JOB_STATUS = ['open' => 'Open', 'draft' => 'Draft', 'closed' => 'Closed'];
const JOB_TYPES = ['Full-time', 'Part-time', 'Contract', 'Internship'];

function lines_of(string $s): array {
    return array_values(array_filter(array_map(function ($x) { return trim(preg_replace('/^[\s•*\-–]+/u', '', $x)); }, preg_split('/\R/', $s))));
}
function republish(): void {
    try {
        publish_careers();
        write_sitemap();
    } catch (Throwable $t) {
        flash('error', 'Saved, but the careers page could not be updated: ' . $t->getMessage());
        redirect('jobs.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'save') {
        $title = trim((string)$_POST['title']);
        $dept = trim((string)$_POST['department']);
        $loc = trim((string)$_POST['location']);
        if ($title === '' || $dept === '' || $loc === '') {
            flash('error', 'Job title, department and location are required.');
            redirect('jobs.php?edit=' . ($id ?: 'new'));
        }
        $slug = slugify((string)($_POST['slug'] ?: $title));
        $base = $slug;
        $n = 2;
        while (val('SELECT 1 FROM jobs WHERE slug = ? AND id <> ?', [$slug, $id])) {
            $slug = $base . '-' . $n++;
        }
        $status = isset(JOB_STATUS[$_POST['status'] ?? '']) ? $_POST['status'] : 'draft';
        $type = in_array($_POST['employment_type'] ?? '', JOB_TYPES, true) ? $_POST['employment_type'] : 'Full-time';
        $vals = [$slug, mb_substr($title, 0, 120), mb_substr($dept, 0, 80), mb_substr($loc, 0, 120), $type,
            mb_substr(trim((string)$_POST['experience']), 0, 60) ?: null, mb_substr(trim((string)$_POST['summary']), 0, 600) ?: null,
            json_encode(lines_of((string)$_POST['duties']), JSON_UNESCAPED_UNICODE), json_encode(lines_of((string)$_POST['requirements']), JSON_UNESCAPED_UNICODE),
            $status, (int)($_POST['sort_order'] ?? 0), now()];
        if ($id) {
            q('UPDATE jobs SET slug=?, title=?, department=?, location=?, employment_type=?, experience=?, summary=?, duties=?, requirements=?, status=?, sort_order=?, updated_at=? WHERE id = ?', array_merge($vals, [$id]));
            log_activity('Updated job posting', $title . ' (' . JOB_STATUS[$status] . ')');
        } else {
            q('INSERT INTO jobs (slug, title, department, location, employment_type, experience, summary, duties, requirements, status, sort_order, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', $vals);
            log_activity('Created job posting', $title . ' (' . JOB_STATUS[$status] . ')');
        }
        // keep the department in the Teams list
        $depts = careers_departments();
        if (!in_array($dept, $depts, true)) {
            $depts[] = $dept;
            set_setting('careers_departments', json_encode($depts, JSON_UNESCAPED_UNICODE));
        }
        republish();
        flash('success', $status === 'open' ? '“' . $title . '” is live on the careers page.' : '“' . $title . '” saved as ' . strtolower(JOB_STATUS[$status]) . '.');
        redirect('jobs.php');
    }
    $job = row('SELECT * FROM jobs WHERE id = ?', [$id]);
    if ($job && in_array($act, ['open', 'closed', 'draft'], true)) {
        q('UPDATE jobs SET status = ?, updated_at = ? WHERE id = ?', [$act, now(), $id]);
        log_activity('Job posting ' . ($act === 'open' ? 'published' : $act), $job['title']);
        republish();
        flash('success', '“' . $job['title'] . '” is ' . ($act === 'open' ? 'live on the careers page.' : strtolower(JOB_STATUS[$act]) . '.'));
    } elseif ($job && $act === 'duplicate') {
        $slug = $job['slug'] . '-copy';
        $n = 2;
        while (val('SELECT 1 FROM jobs WHERE slug = ?', [$slug])) {
            $slug = $job['slug'] . '-copy-' . $n++;
        }
        q('INSERT INTO jobs (slug, title, department, location, employment_type, experience, summary, duties, requirements, status, sort_order, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$slug, $job['title'] . ' (copy)', $job['department'], $job['location'], $job['employment_type'], $job['experience'], $job['summary'], $job['duties'], $job['requirements'], 'draft', $job['sort_order'], now()]);
        flash('success', 'Copy created as a draft.');
        redirect('jobs.php?edit=' . db()->lastInsertId());
    } elseif ($job && $act === 'delete') {
        q('DELETE FROM jobs WHERE id = ?', [$id]);
        log_activity('Deleted job posting', $job['title']);
        republish();
        flash('success', '“' . $job['title'] . '” deleted. Applications already received are kept.');
    } elseif ($act === 'departments') {
        $list = array_values(array_unique(lines_of((string)$_POST['departments'])));
        set_setting('careers_departments', json_encode($list, JSON_UNESCAPED_UNICODE));
        republish();
        flash('success', 'Teams updated on the careers page.');
    }
    redirect('jobs.php');
}

$edit = $_GET['edit'] ?? null;
if ($edit !== null) {
    $job = $edit === 'new' ? null : row('SELECT * FROM jobs WHERE id = ?', [(int)$edit]);
    $j = $job ?: ['id' => 0, 'title' => '', 'slug' => '', 'department' => '', 'location' => 'Mumbai', 'employment_type' => 'Full-time', 'experience' => '', 'summary' => '', 'duties' => '[]', 'requirements' => '[]', 'status' => 'open', 'sort_order' => (int)val('SELECT COALESCE(MAX(sort_order),0)+1 FROM jobs')];
    page_start($job ? 'Edit job posting' : 'New job posting', 'jobs', '<a class="btn btn--ghost" href="jobs.php">&larr; All postings</a>', 'Open postings appear on the careers page as soon as you save.');
    ?>
<form method="post" class="card form-card">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
  <div class="form-grid">
    <label class="field field--wide"><span>Job title *</span><input name="title" required maxlength="120" value="<?= h($j['title']) ?>" placeholder="e.g. Site Engineer, Civil"></label>
    <label class="field"><span>Department *</span><input name="department" required list="depts" value="<?= h($j['department']) ?>" placeholder="Choose or type a new one">
      <datalist id="depts"><?php foreach (careers_departments() as $d): ?><option value="<?= h($d) ?>"><?php endforeach; ?></datalist></label>
    <label class="field"><span>Location *</span><input name="location" required value="<?= h($j['location']) ?>"></label>
    <label class="field"><span>Employment type</span><select name="employment_type"><?php foreach (JOB_TYPES as $t): ?><option<?= $j['employment_type'] === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Experience</span><input name="experience" value="<?= h($j['experience']) ?>" placeholder="e.g. 2 to 5 years"></label>
    <label class="field field--wide"><span>Short summary</span><textarea name="summary" rows="2" maxlength="600" placeholder="One or two sentences shown at the top of the role"><?= h($j['summary']) ?></textarea></label>
    <label class="field"><span>What you will do <small>one point per line</small></span><textarea name="duties" rows="6"><?= h(implode("\n", json_decode($j['duties'], true) ?: [])) ?></textarea></label>
    <label class="field"><span>What you bring <small>one point per line</small></span><textarea name="requirements" rows="6"><?= h(implode("\n", json_decode($j['requirements'], true) ?: [])) ?></textarea></label>
    <fieldset class="field"><legend>Status</legend>
      <label class="radio"><input type="radio" name="status" value="open"<?= $j['status'] === 'open' ? ' checked' : '' ?>> Open <small>live on the website</small></label>
      <label class="radio"><input type="radio" name="status" value="draft"<?= $j['status'] === 'draft' ? ' checked' : '' ?>> Draft <small>hidden</small></label>
      <label class="radio"><input type="radio" name="status" value="closed"<?= $j['status'] === 'closed' ? ' checked' : '' ?>> Closed <small>hidden</small></label>
    </fieldset>
    <div class="field-row">
      <label class="field"><span>Order <small>lower shows first</small></span><input name="sort_order" type="number" value="<?= (int)$j['sort_order'] ?>"></label>
      <label class="field"><span>Link name <small>careers.html#…</small></span><input name="slug" value="<?= h($j['slug']) ?>" placeholder="made from the title"></label>
    </div>
  </div>
  <div class="form-actions"><button class="btn btn--primary" type="submit">Save</button><a class="btn btn--ghost" href="jobs.php">Cancel</a></div>
</form>
<?php
    page_end();
    exit;
}

$jobs = rows("SELECT * FROM jobs ORDER BY CASE status WHEN 'open' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END, sort_order, id");
$apps = [];
foreach (rows("SELECT designation, COUNT(*) n, SUM(status='new') nn FROM submissions WHERE form='careers' GROUP BY designation") as $r) {
    $apps[(string)$r['designation']] = $r;
}
$open = count(array_filter($jobs, function ($j) { return $j['status'] === 'open'; }));
page_start('Job postings', 'jobs', '<a class="btn btn--ghost" href="../careers.html#openings" target="_blank" rel="noopener">View careers page</a><a class="btn btn--primary" href="jobs.php?edit=new">+ New job posting</a>', count($jobs) . ' postings · ' . $open . ' live on the website');
?>
<?php if (!$jobs): ?><section class="card"><p class="empty">No job postings yet. Use “New job posting” to add one.</p></section><?php endif; ?>
<ul class="jobs">
<?php foreach ($jobs as $j): $a = $apps[$j['title']] ?? null; ?>
  <li class="job job--<?= h($j['status']) ?>">
    <div class="job__main">
      <span class="badge badge--<?= h($j['status']) ?>"><?= JOB_STATUS[$j['status']] ?></span>
      <h3><a href="jobs.php?edit=<?= (int)$j['id'] ?>"><?= h($j['title']) ?></a></h3>
      <p><?= h(implode(' · ', array_filter([$j['department'], $j['location'], $j['employment_type'], $j['experience']]))) ?></p>
      <small>Updated <?= h(fmt_date($j['updated_at'] ?: $j['created_at'])) ?> ·
        <?php if ($a): ?><a href="submissions.php?form=careers&amp;designation=<?= rawurlencode($j['title']) ?>"><?= (int)$a['n'] ?> application<?= $a['n'] == 1 ? '' : 's' ?><?= $a['nn'] ? ' (' . (int)$a['nn'] . ' new)' : '' ?></a><?php else: ?>0 applications<?php endif; ?></small>
    </div>
    <form method="post" class="job__actions">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
      <a class="btn btn--ghost" href="jobs.php?edit=<?= (int)$j['id'] ?>">Edit</a>
      <?php if ($j['status'] === 'open'): ?><button class="btn btn--ghost" name="action" value="closed">Close</button>
      <?php else: ?><button class="btn btn--ghost" name="action" value="open">Publish</button><?php endif; ?>
      <button class="btn btn--ghost" name="action" value="duplicate">Duplicate</button>
      <button class="btn btn--danger" name="action" value="delete" data-confirm="Delete “<?= h($j['title']) ?>”? Applications already received are kept.">Delete</button>
    </form>
  </li>
<?php endforeach; ?>
</ul>

<details class="card collapsible">
  <summary><h2>Teams on the careers page</h2><span class="muted">The “Six teams, one site” list and its open-role counts</span></summary>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="departments">
    <label class="field"><span>One team per line, in display order</span><textarea name="departments" rows="7"><?= h(implode("\n", careers_departments())) ?></textarea></label>
    <button class="btn btn--primary" type="submit">Save teams</button>
  </form>
</details>
<?php page_end();
