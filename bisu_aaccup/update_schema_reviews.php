<?php
require_once 'config/db.php';

// Add reviewed_file_path column to documents table
$check = $conn->query("SHOW COLUMNS FROM documents LIKE 'reviewed_file_path'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE documents ADD COLUMN reviewed_file_path VARCHAR(255) NULL AFTER file_path";
    if ($conn->query($sql)) {
        echo "✅ Added 'reviewed_file_path' column to documents table.<br>";
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Column 'reviewed_file_path' already exists.<br>";
}
echo "Schema update complete. You can delete this file.";
?>