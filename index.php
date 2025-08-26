<?php
require_once 'config/config.php';
require_once 'includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . 'modules/' . $_SESSION['role'] . '/dashboard.php');
} else {
    header('Location: ' . BASE_URL . 'login.php');
}
exit();
?>