<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
$u = require_login('users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($act === 'save') {
            $name = trim((string)$_POST['name']);
            $username = strtolower(trim((string)$_POST['username']));
            $email = strtolower(trim((string)$_POST['email']));
            $role = isset(ROLES[$_POST['role'] ?? '']) ? $_POST['role'] : 'editor';
            $pass = (string)($_POST['password'] ?? '');
            if ($name === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a name, a username (3–40 letters, numbers, dots or dashes) and a valid email.');
            }
            if (val('SELECT 1 FROM users WHERE (username = ? OR email = ?) AND id <> ?', [$username, $email, $id])) {
                throw new RuntimeException('That username or email is already used.');
            }
            if ($id === (int)$u['id'] && $role !== 'admin') {
                throw new RuntimeException('You cannot remove your own administrator role.');
            }
            if ($pass !== '' && strlen($pass) < 8) {
                throw new RuntimeException('Passwords need at least 8 characters.');
            }
            if ($id) {
                q('UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE id = ?', [$name, $username, $email, $role, $id]);
                if ($pass !== '') {
                    q('UPDATE users SET password_hash = ?, must_change = 1 WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
                }
                log_activity('Updated user', $username . ' (' . ROLES[$role] . ')');
            } else {
                if ($pass === '') {
                    throw new RuntimeException('Set a first password for the new user.');
                }
                q('INSERT INTO users (name, username, email, password_hash, role, must_change) VALUES (?,?,?,?,?,1)', [$name, $username, $email, password_hash($pass, PASSWORD_DEFAULT), $role]);
                log_activity('Added user', $username . ' (' . ROLES[$role] . ')');
            }
            flash('success', 'User saved.' . ($pass !== '' ? ' They will be asked to choose their own password after signing in.' : ''));
        } elseif ($act === 'toggle' && $id !== (int)$u['id']) {
            q('UPDATE users SET active = 1 - active WHERE id = ?', [$id]);
            log_activity('Changed user access', (string)val('SELECT username FROM users WHERE id = ?', [$id]));
            flash('success', 'Access updated.');
        } elseif ($act === 'delete' && $id !== (int)$u['id']) {
            $name = val('SELECT username FROM users WHERE id = ?', [$id]);
            q('DELETE FROM users WHERE id = ?', [$id]);
            log_activity('Deleted user', (string)$name);
            flash('success', 'User deleted.');
        }
    } catch (Throwable $t) {
        flash('error', $t->getMessage());
        redirect('users.php?edit=' . ($id ?: 'new'));
    }
    redirect('users.php');
}

$list = rows('SELECT * FROM users ORDER BY active DESC, name');
$edit = $_GET['edit'] ?? null;
$e = $edit === 'new' ? ['id' => 0, 'name' => '', 'username' => '', 'email' => '', 'role' => 'editor'] : ($edit ? row('SELECT * FROM users WHERE id = ?', [(int)$edit]) : null);
page_start('Users', 'users', '<a class="btn btn--primary" href="users.php?edit=new">+ Add user</a>', count($list) . ' user' . (count($list) === 1 ? '' : 's'));
?>
<?php if ($e): ?>
<form method="post" class="card form-card">
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
  <h2 class="card__title"><?= $e['id'] ? 'Edit ' . h($e['name']) : 'Add a user' ?></h2>
  <div class="form-grid">
    <label class="field"><span>Name</span><input name="name" required value="<?= h($e['name']) ?>"></label>
    <label class="field"><span>Username</span><input name="username" required value="<?= h($e['username']) ?>" autocomplete="off"></label>
    <label class="field"><span>Email</span><input name="email" type="email" required value="<?= h($e['email']) ?>"></label>
    <label class="field"><span>Role</span><select name="role"><?php foreach (ROLES as $k => $l): ?><option value="<?= $k ?>"<?= $e['role'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span><?= $e['id'] ? 'New password' : 'First password' ?> <small><?= $e['id'] ? 'leave empty to keep' : 'at least 8 characters' ?></small></span><input name="password" type="text" autocomplete="new-password" minlength="8"></label>
  </div>
  <div class="role-help">
    <p><b>Administrator</b>: everything, including users, tracking codes and settings.</p>
    <p><b>Content editor</b>: pages, blog, media library and job postings.</p>
    <p><b>HR / recruitment</b>: career applications and job postings.</p>
    <p><b>Enquiries</b>: contact, vendor and development submissions.</p>
  </div>
  <div class="form-actions"><button class="btn btn--primary" type="submit">Save user</button><a class="btn btn--ghost" href="users.php">Cancel</a></div>
</form>
<?php endif; ?>
<section class="card card--flush">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Last sign-in</th><th></th></tr></thead>
    <tbody>
<?php foreach ($list as $x): ?>
      <tr class="<?= $x['active'] ? '' : 'is-muted' ?>">
        <td><b><?= h($x['name']) ?></b><small><?= h($x['email']) ?></small></td>
        <td class="mono"><?= h($x['username']) ?></td>
        <td><?= h(ROLES[$x['role']] ?? $x['role']) ?><?= $x['active'] ? '' : ' <span class="badge badge--closed">Disabled</span>' ?></td>
        <td><?= h(fmt_date($x['last_login'], true)) ?></td>
        <td class="row-actions">
          <a class="btn btn--ghost btn--sm" href="users.php?edit=<?= (int)$x['id'] ?>">Edit</a>
<?php if ((int)$x['id'] !== (int)$u['id']): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn--ghost btn--sm" name="action" value="toggle"><?= $x['active'] ? 'Disable' : 'Enable' ?></button><button class="btn btn--danger btn--sm" name="action" value="delete" data-confirm="Delete <?= h($x['name']) ?>?">Delete</button></form>
<?php endif; ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php page_end();
