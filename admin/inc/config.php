<?php
/* Argentec admin panel: configuration.
   Change the default password from the admin panel (My account) after the
   first sign-in; the panel shows a warning until you do. */
return [
    // Public address of the website, used for the sitemap, blog links and emails.
    'site_url' => 'https://argentec.com',

    // Where the database, resumes and other private files are kept.
    // null = automatic: a folder next to public_html ("argentec-private"),
    // which the web server can never serve. If that folder cannot be
    // created, admin/data is used instead (blocked by its .htaccess).
    // To choose yourself, give a full path, e.g. '/home/u123/argentec-private'
    'data_dir' => null,

    // First admin account, created automatically on the first visit.
    'default_admin' => [
        'name'     => 'Administrator',
        'username' => 'admin',
        'email'    => 'admin@argentec.com',
        'password' => 'Argentec@123',
    ],

    // Launch: until this moment (Indian time) visitors see the
    // under-construction page with a countdown. Change it in the admin panel
    // (Settings → Launch); this is only the starting value.
    'launch_at'      => '2026-11-28 12:12',

    'timezone'       => 'Asia/Kolkata',
    'max_resume_mb'  => 5,
    'max_media_mb'   => 25,

    // Sender for notification emails (should be an address on your domain).
    'mail_from'      => 'no-reply@argentec.com',
];
