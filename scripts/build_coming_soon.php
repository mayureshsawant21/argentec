<?php
/* Write coming-soon.html and the launch time in js/launch.js from
   admin/inc/config.php (launch_at), for static hosting such as GitHub Pages.
   Run from the repository root:  php scripts/build_coming_soon.php
   On Hostinger, Settings → Launch in the admin panel does the same. */
require __DIR__ . '/../admin/inc/bootstrap.php';
require __DIR__ . '/../admin/inc/coming-soon.php';
$ts = strtotime((string)cfg('launch_at'));
if (!$ts) {
    fwrite(STDERR, "launch_at in admin/inc/config.php is not a valid date\n");
    exit(1);
}
write_static_launch($ts, true);
echo 'coming-soon.html and js/launch.js set to ' . date('j M Y, g:i A T', $ts) . "\n";
