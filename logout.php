<?php
session_start();
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    $auth = new Auth();
    $auth->logout();
    set_flash_message('success', 'Logout berhasil');
}

redirect('login.php');
?>