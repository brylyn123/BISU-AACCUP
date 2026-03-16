<?php
require_once 'config/db.php';

echo "<h2>Updating Database Schema for Avatars...</h2>";

// Check if 'avatar_path' column exists in 'users' table
$check = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar_path'");
if ($check && $check->num_rows == 0) {
    // Add the missing column
    $sql = "ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL AFTER email";
    if ($conn->query($sql)) {
        echo "✅ Success: 'avatar_path' column added to 'users' table.<br>";
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Info: The 'avatar_path' column already exists in the 'users' table.<br>";
}

echo "<br><strong>Update complete. You can now refresh your profile page.</strong>";
?>