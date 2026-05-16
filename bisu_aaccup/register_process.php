<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $middle_name = isset($_POST['middle_name']) ? sanitize($_POST['middle_name']) : '';
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $role_id = intval($_POST['role_id']);
    $program_id = null;
    $college_id = null;

    // Server-side password validation
    $raw_password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    if ($raw_password === '' || $confirm_password === '') {
        showError('Password and Confirm Password are required.');
        redirect('signup.php');
    }

    if (strlen($raw_password) < 8 || !preg_match('/\d/', $raw_password)) {
        showError('Password must be at least 8 characters and contain a number.');
        redirect('signup.php');
    }

    if ($raw_password !== $confirm_password) {
        showError('Passwords do not match.');
        redirect('signup.php');
    }

    $stmt_role = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt_role->bind_param("i", $role_id);
    $stmt_role->execute();
    $role_name = $stmt_role->get_result()->fetch_row()[0] ?? '';

    $normalized_role = strtolower($role_name);
    $allowed_roles = ['admin', 'focal person', 'faculty / focal person', 'accreditor', 'accreditor (internal)', 'accreditor (external)'];
    if ($role_name === '' || !in_array($normalized_role, $allowed_roles, true)) {
        showError('This role is not available in the repository workflow.');
        redirect('signup.php');
    }

    $password = password_hash($raw_password, PASSWORD_BCRYPT);

    // Check if email already exists
    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        showError("An account with this email already exists.");
        redirect('signup.php');
        exit;
    }

    $sql = "INSERT INTO users (firstname, middlename, lastname, email, password, role_id, program_id, college_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssiii", $first_name, $middle_name, $last_name, $email, $password, $role_id, $program_id, $college_id);

    if ($stmt->execute()) {
        redirect('login.php?success=Account Created');
    } else {
        showError("Error: " . $conn->error);
        redirect('signup.php');
    }
}
?>
