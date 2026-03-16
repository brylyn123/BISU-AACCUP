<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $middle_name = isset($_POST['middle_name']) ? sanitize($_POST['middle_name']) : '';
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $role_id = intval($_POST['role_id']); // Security: Cast to integer
    
    // Handle optional fields based on role selection
    $program_id = !empty($_POST['program_id']) ? intval($_POST['program_id']) : NULL;
    $college_id = !empty($_POST['college_id']) ? intval($_POST['college_id']) : NULL;

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

    // --- Server-Side Validation for Unique Roles ---
    // Get role name to check constraints
    $stmt_role = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $stmt_role->bind_param("i", $role_id);
    $stmt_role->execute();
    $role_name = $stmt_role->get_result()->fetch_row()[0] ?? '';

    if (stripos($role_name, 'Dean') !== false && $college_id) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE role_id = ? AND college_id = ?");
        $check->bind_param("ii", $role_id, $college_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            showError("This college already has an assigned Dean.");
            redirect('signup.php');
        }
    } elseif (stripos($role_name, 'Chairperson') !== false && $program_id) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE role_id = ? AND program_id = ?");
        $check->bind_param("ii", $role_id, $program_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            showError("This program already has an assigned Chairperson.");
            redirect('signup.php');
        }
    }
    // -----------------------------------------------

    $password = password_hash($raw_password, PASSWORD_BCRYPT);

    $full_name_pieces = array_filter([$first_name, $middle_name, $last_name], fn($part) => $part !== '');
    $full_name = implode(' ', $full_name_pieces);

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
