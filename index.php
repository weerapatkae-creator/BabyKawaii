<?php
require_once __DIR__ . '/config/database.php';
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . '/dashboard.php');
} else {
    header('Location: ' . SITE_URL . '/login.php');
}
exit;
