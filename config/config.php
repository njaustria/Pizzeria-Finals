<?php
define('SITE_NAME', 'Pizzeria');
define('SITE_URL', 'http://localhost/pizzeria');
define('ADMIN_EMAIL', 'admin@pizzeria.com');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'pizzeriagroup5@gmail.com');
define('SMTP_PASSWORD', 'ljub slqx stuc kzcr');
define('SMTP_FROM_EMAIL', 'pizzeriagroup5@gmail.com');
define('SMTP_FROM_NAME', 'Pizzeria - Order System');

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/database.php';
