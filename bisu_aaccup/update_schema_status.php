<?php
require_once 'config/db.php';

// Add 'status' column to documents table
$check = $conn->query("SHOW COLUMNS FROM documents LIKE 'status'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE documents ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER type_id";
    if ($conn->query($sql)) {
        echo "✅ Added 'status' column to documents table.<br>";
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Column 'status' already exists.<br>";
}
echo "Schema update complete. You can delete this file.";
?>