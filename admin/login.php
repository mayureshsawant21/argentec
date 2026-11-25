<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
start_session();
if (user()) {
    redirect('index.php');
}
$error = '';
$next = (string)($_GET['next'] ?? $_POST['next'] ?? 'index.php');
if (!preg_match('#^(/[^/]|[a-z])#i', $next) || str_contains($next, '//') || str_contains($next, ':')) {
    $next = 'index.php';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $login = trim((string)($_POST['login'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (too_many_attempts()) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $u = row('SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1', [$login, $login]);
        if ($u && password_verify($pass, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
            q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
            q('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
            log_activity('Signed in');
            redirect($next);
        }
        q('INSERT INTO login_attempts (ip, username) VALUES (?, ?)', [client_ip(), mb_substr($login, 0, 80)]);
        $error = 'Username or password is incorrect.';
    }
}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Sign in · Argentec Admin</title>
  <link rel="icon" type="image/svg+xml" href="../assets/img/argentec-logo.svg">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600&display=swap">
  <link rel="stylesheet" href="assets/admin.css?v=<?= substr(sha1_file(__DIR__ . '/assets/admin.css'), 0, 8) ?>">
</head>
<body class="gate">
  <form class="gate__card" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <img src="../assets/img/argentec-logo.svg" alt="" width="56" height="56">
    <h1>Argentec admin</h1>
    <p class="muted">Sign in to manage enquiries, careers, pages and the blog.</p>
    <?php if ($f = take_flash()): ?><div class="alert alert--<?= h($f[0]) ?>"><?= h($f[1]) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert--error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <label class="field"><span>Username or email</span><input name="login" autocomplete="username" required value="<?= h($_POST['login'] ?? '') ?>" autofocus></label>
    <label class="field"><span>Password</span>
      <span class="pass"><input name="password" type="password" autocomplete="current-password" required><button type="button" class="pass__toggle" data-toggle-pass>Show</button></span>
    </label>
    <button class="btn btn--primary btn--block" type="submit">Sign in</button>
    <a class="gate__back" href="../index.html">&larr; Back to website</a>
  </form>
  <script src="assets/admin.js?v=<?= substr(sha1_file(__DIR__ . '/assets/admin.js'), 0, 8) ?>"></script>
</body>
</html>
