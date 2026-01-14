<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);
unset($_SESSION['role']);

setFlashMessage('You have been logged out successfully.', 'success');
header('Location: login.php');
exit();
