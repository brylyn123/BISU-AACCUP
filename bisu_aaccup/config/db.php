<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'bisu_aaccup');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
date_default_timezone_set('Asia/Manila');

function sanitize($input) {
    global $conn;
    return $conn->real_escape_string(trim($input));
}

function ensureSessionStarted() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function isAdminSession() {
    ensureSessionStarted();
    return isset($_SESSION['user_id']) && strpos(strtolower($_SESSION['role'] ?? ''), 'admin') !== false;
}

function csrfToken() {
    ensureSessionStarted();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    ensureSessionStarted();
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireValidCsrfToken() {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid request token.');
    }
}

function requireAdminSessionOrExit() {
    if (!isAdminSession()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdminOrCliForMaintenance() {
    if (PHP_SAPI === 'cli') {
        return;
    }

    requireAdminSessionOrExit();
}

function detectMimeType($file_path) {
    if (!is_file($file_path)) {
        return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return '';
    }

    $mime = finfo_file($finfo, $file_path) ?: '';
    finfo_close($finfo);

    return $mime;
}

function moveValidatedUpload($file, $target_dir, $allowed_types, &$error_message = null) {
    if (!isset($file['tmp_name'], $file['name']) || !is_uploaded_file($file['tmp_name'])) {
        $error_message = 'Invalid upload payload.';
        return null;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension === '' || !isset($allowed_types[$extension])) {
        $error_message = 'Unsupported file type.';
        return null;
    }

    $mime_type = detectMimeType($file['tmp_name']);
    if (!in_array($mime_type, $allowed_types[$extension], true)) {
        $error_message = 'Uploaded file contents do not match the file type.';
        return null;
    }

    if (!is_dir($target_dir) && !mkdir($target_dir, 0777, true)) {
        $error_message = 'Upload directory is not writable.';
        return null;
    }

    $safe_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $target_path = rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR . $safe_filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        $error_message = 'Failed to store uploaded file.';
        return null;
    }

    return str_replace('\\', '/', $target_path);
}

function redirect($location) {
    header('Location: ' . $location);
    exit;
}

function showError($message) {
    ensureSessionStarted();
    $_SESSION['error'] = $message;
}
