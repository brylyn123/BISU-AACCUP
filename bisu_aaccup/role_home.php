<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Route only the active roles used in the repository workflow.
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (strpos($role, 'admin') !== false) {
    header('Location: repositories.php');
} elseif (strpos($role, 'focal') !== false || strpos($role, 'faculty') !== false) {
    header('Location: focal_dashboard.php');
} elseif (strpos($role, 'accreditor') !== false) {
    header('Location: accreditor_dashboard.php');
} else {
    header('Location: login.php');
}
exit;
