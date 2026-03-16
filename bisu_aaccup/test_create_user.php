<?php
// Temporary test script — remove after use
require_once 'config/db.php';

$email = 'test@bisu.test';
$passwordPlain = 'Test123!';
$first_name = 'Test';
$middle_name = '';
$last_name = 'User';
$role_id = 2; // adjust if needed (use existing role_id in your DB)
$program_id = 1; // adjust if needed

// Hash password
$hash = password_hash($passwordPlain, PASSWORD_BCRYPT);

// Check existing
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->fetch_assoc()) {
    echo "User already exists: $email";
    exit;
}

// Insert
$stmt = $conn->prepare("INSERT INTO users (firstname, middlename, lastname, email, password, role_id, program_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssssii', $first_name, $middle_name, $last_name, $email, $hash, $role_id, $program_id);
if ($stmt->execute()) {
    echo "Test user created:\nEmail: $email\nPassword: $passwordPlain";
} else {
    echo "Insert failed: " . $conn->error;
}

?>
