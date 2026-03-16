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

function redirect($location) {
    header('Location: ' . $location);
    exit;
}

function showError($message) {
    if(!isset($_SESSION)) session_start();
    $_SESSION['error'] = $message;
}