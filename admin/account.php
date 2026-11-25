<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)$_POST['name']);
    $email = strtolower(trim((string)$_POST['email']));
    $cur = (string)$_POST['current'];
    $new = (string)$_POST['new'];
    $again = (string)$_POST['again'];
    try {
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter your name and a valid email.');
        }
        if (val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) {
            throw new RuntimeException('That email belongs to another user.');
        }
        q('UPDATE users SET name = ?, email = ? WHERE id = ?', [$name, $email, $u['id']]);
        if ($new !== '' || $u['must_change']) {
            if (!password_verify($cur, $u['password_hash'])) {
                throw new RuntimeException('Your current password is not correct.');
            }
            if (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
                throw new RuntimeException('Choose a password of at least 10 characters with letters and numbers.');
            }
            if ($new !== $again) {
                throw new RuntimeException('The two new passwords do not match.');
            }
            if (password_verify($new, $u['password_hash']) || $new === (cfg('default_admin')['password'] ?? '')) {
                throw new RuntimeException('Choose a password different from the current and default ones.');
            }
            q('UPDATE users SET password_hash = ?, must_change = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            log_activity('Changed password');
        }
        flash('success', 'Account updated.');
    } catch (Throwable $t) {
        flash('error', $t->getMessage());
    }
    redirect('account.php');
}
page_start('My account', '', '', h(ROLES[$u['role']] ?? $u['role']) . ' · signed in as <span class="mono">' . h($u['username']) . '</span>');
?>
<form method="post" class="card form-card form-card--narrow">
  <?= csrf_field() ?>
  <label class="field"><span>Name</span><input name="name" required value="<?= h($u['name']) ?>"></label>
  <label class="field"><span>Email <small>for sign-in and test emails</small></span><input name="email" type="email" required value="<?= h($u['email']) ?>"></label>
  <h2 class="card__title"><?= $u['must_change'] ? 'Choose your own password' : 'Change password' ?></h2>
  <label class="field"><span>Current password</span><input name="current" type="password" autocomplete="current-password"<?= $u['must_change'] ? ' required' : '' ?>></label>
  <label class="field"><span>New password <small>10+ characters, letters and numbers</small></span><input name="new" type="password" autocomplete="new-password" minlength="10"<?= $u['must_change'] ? ' required' : '' ?>></label>
  <label class="field"><span>New password again</span><input name="again" type="password" autocomplete="new-password"<?= $u['must_change'] ? ' required' : '' ?>></label>
  <div class="form-actions"><button class="btn btn--primary" type="submit">Save</button></div>
</form>
<?php page_end();
