<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get email from form (handle both email and username fields for backwards compatibility)
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : (isset($_POST['username']) ? sanitize($_POST['username']) : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
        showError("Email and password are required.");
        redirect('login.php');
        exit;
    }

    // Join with roles to get the role_name immediately
    $sql = "SELECT u.*, r.role_name, CONCAT_WS(' ', u.firstname, NULLIF(u.middlename, ''), u.lastname) AS full_name
            FROM users u 
            JOIN roles r ON u.role_id = r.role_id 
            WHERE u.email = ? LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify the hashed password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['program_id'] = $user['program_id'];
            $_SESSION['avatar_path'] = $user['avatar_path'];
            
            // Set welcome flag for dashboard animation
            $_SESSION['show_welcome'] = true;

            // Redirect to role-aware home that routes to each role dashboard
            redirect('role_home.php');
        } else {
            showError("Invalid password.");
            redirect('login.php');
        }
    } else {
        showError("User not found.");
        redirect('login.php');
    }
}
?>
