<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Normalize role and use substring checks to handle variations like "Focal Person", "Focal", "faculty"
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (strpos($role, 'admin') !== false) {
    header('Location: admin_dashboard.php');
} elseif (strpos($role, 'dean') !== false) {
    header('Location: dean_dashboard.php');
} elseif (strpos($role, 'focal') !== false || strpos($role, 'faculty') !== false) {
    header('Location: focal_dashboard.php');
} elseif (strpos($role, 'accreditor') !== false) {
    header('Location: accreditor_dashboard.php');
} elseif (strpos($role, 'chairperson') !== false) {
    header('Location: chairperson_dashboard.php');
} else {
    header('Location: user_dashboard.php');
}
exit;
