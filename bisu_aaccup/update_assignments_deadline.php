<?php
require_once 'config/db.php';

echo "<h2>Updating Assignment Schema for Deadlines...</h2>";

// Check if 'deadline' column exists
$check = $conn->query("SHOW COLUMNS FROM faculty_area_assignments LIKE 'deadline'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE faculty_area_assignments ADD COLUMN deadline DATE NULL DEFAULT NULL";
    if ($conn->query($sql)) {
        echo "✅ Added 'deadline' column.<br>";
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Column 'deadline' already exists.<br>";
}
echo "<br><a href='chairperson_dashboard.php?view=faculty'>Return to Dashboard</a>";
?>