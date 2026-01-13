<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_login_time']);

setFlashMessage('You have been securely logged out.', 'dark');

header('Location: login.php');
exit();
