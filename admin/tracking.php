<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/site.php';
$u = require_login('tracking');

$fields = [
    'gtm_id' => ['Google Tag Manager', 'Container ID', 'GTM-XXXXXXX', '/^GTM-[A-Z0-9]{4,12}$/', 'Recommended: add Google Ads, GA4 and other tags inside GTM, without code changes.'],
    'ga4_id' => ['Google Analytics 4', 'Measurement ID', 'G-XXXXXXXXXX', '/^G-[A-Z0-9]{4,15}$/', 'Leave empty if GA4 is already set up inside Google Tag Manager, or visits are counted twice.'],
    'meta_pixel_id' => ['Meta Pixel (Facebook & Instagram)', 'Pixel ID', '123456789012345', '/^\d{8,20}$/', 'Tracks page views; form submissions are sent as “Lead” (careers: “SubmitApplication”).'],
    'clarity_id' => ['Microsoft Clarity', 'Project ID', 'abcd1234ef', '/^[a-z0-9]{6,20}$/', 'Free heatmaps and session recordings.'],
    'linkedin_partner_id' => ['LinkedIn Insight Tag', 'Partner ID', '1234567', '/^\d{4,12}$/', 'Useful for B2B campaigns aimed at developers and companies.'],
    'gsc_verification' => ['Google Search Console', 'Verification code', 'the content="…" value of the HTML tag', '/^[A-Za-z0-9_-]{10,100}$/', 'Choose “HTML tag” as the verification method and paste only the code.'],
    'bing_verification' => ['Bing Webmaster Tools', 'Verification code', 'msvalidate.01 value', '/^[A-Za-z0-9]{10,64}$/', ''],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    foreach ($fields as $k => $f) {
        $v = trim((string)($_POST[$k] ?? ''));
        if ($k === 'gtm_id' || $k === 'ga4_id') {
            $v = strtoupper($v);
        }
        if ($k === 'gsc_verification' && preg_match('/content="([^"]+)"/', $v, $m)) {
            $v = $m[1]; // pasted the whole tag
        }
        if ($v !== '' && !preg_match($f[3], $v)) {
            $errors[] = $f[0] . ': “' . $v . '” does not look right (expected ' . $f[2] . ').';
            continue;
        }
        set_setting($k, $v);
    }
    if ($u['role'] === 'admin') {
        set_setting('custom_head', (string)($_POST['custom_head'] ?? ''));
        set_setting('custom_body', (string)($_POST['custom_body'] ?? ''));
    }
    try {
        $n = apply_tracking_all();
        log_activity('Updated tracking codes', $n . ' pages updated');
        if ($errors) {
            flash('error', implode(' ', $errors) . ' The other codes were saved.');
        } else {
            flash('success', 'Tracking codes saved on all pages' . ($n ? ' (' . $n . ' updated)' : '') . '.');
        }
    } catch (Throwable $t) {
        flash('error', 'Saved, but the pages could not be updated: ' . $t->getMessage());
    }
    redirect('tracking.php');
}

$active = array_filter(array_keys($fields), function ($k) { return setting($k) !== ''; });
page_start('Tracking & SEO', 'tracking', '', 'Analytics, ads pixels and site verification, added to every page of the website.');
?>
<form method="post" class="tracking">
  <?= csrf_field() ?>
  <div class="track-grid">
<?php foreach ($fields as $k => $f): $v = (string)setting($k); ?>
    <section class="card track<?= $v !== '' ? ' is-on' : '' ?>">
      <header><h2><?= h($f[0]) ?></h2><span class="badge <?= $v !== '' ? 'badge--open' : 'badge--draft' ?>"><?= $v !== '' ? 'Active' : 'Off' ?></span></header>
      <label class="field"><span><?= h($f[1]) ?></span><input name="<?= h($k) ?>" value="<?= h($v) ?>" placeholder="<?= h($f[2]) ?>" autocomplete="off" spellcheck="false"></label>
      <?php if ($f[4]): ?><p class="muted small"><?= h($f[4]) ?></p><?php endif; ?>
    </section>
<?php endforeach; ?>
  </div>

<?php if ($u['role'] === 'admin'): ?>
  <details class="card collapsible"<?= setting('custom_head') !== '' || setting('custom_body') !== '' ? ' open' : '' ?>>
    <summary><h2>Custom code</h2><span class="muted">Any other tag or script (for example Google Ads remarketing, Hotjar or a chat widget)</span></summary>
    <div class="alert alert--warn">Code here runs on every page. Paste only code from a provider you trust.</div>
    <label class="field"><span>Inside &lt;head&gt;</span><textarea name="custom_head" rows="6" class="mono" spellcheck="false"><?= h(setting('custom_head')) ?></textarea></label>
    <label class="field"><span>Start of &lt;body&gt;</span><textarea name="custom_body" rows="4" class="mono" spellcheck="false"><?= h(setting('custom_body')) ?></textarea></label>
  </details>
<?php endif; ?>

  <details class="card collapsible">
    <summary><h2>Conversion events</h2><span class="muted">Sent automatically when a form is submitted</span></summary>
    <table class="table table--plain">
      <thead><tr><th>Form</th><th>GTM dataLayer</th><th>GA4</th><th>Meta Pixel</th></tr></thead>
      <tbody>
<?php foreach (FORMS as $k => $f): ?>
        <tr><td><?= h($f['short']) ?></td><td class="mono small">event: form_submission<br>form_type: <?= h($k) ?></td><td class="mono small">generate_lead</td><td class="mono small"><?= $k === 'careers' ? 'SubmitApplication' : 'Lead' ?></td></tr>
<?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted small">In Google Tag Manager, create a “Custom Event” trigger named <code>form_submission</code> and use the <code>form_type</code> variable to tell forms apart.</p>
  </details>

  <div class="form-actions form-actions--sticky"><button class="btn btn--primary" type="submit">Save and apply to all pages</button><span class="muted small"><?= count($active) ?> active</span></div>
</form>
<?php page_end();
