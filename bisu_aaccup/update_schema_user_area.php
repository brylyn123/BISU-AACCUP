<?php
require_once 'config/db.php';

// Add 'area_id' column to users table
$check = $conn->query("SHOW COLUMNS FROM users LIKE 'area_id'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN area_id INT(11) NULL AFTER program_id";
    if ($conn->query($sql)) {
        echo "✅ Added 'area_id' column to users table.<br>";
        // Add foreign key if areas table exists
        $conn->query("ALTER TABLE users ADD CONSTRAINT fk_users_area FOREIGN KEY (area_id) REFERENCES areas(area_id) ON DELETE SET NULL");
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Column 'area_id' already exists.<br>";
}
echo "Schema update complete. You can delete this file.";
?>