<?php
require_once 'config/db.php';

echo "<h2>Fixing Database Error...</h2>";

// Check if 'type_id' column exists in 'faculty_area_assignments' table
$check = $conn->query("SHOW COLUMNS FROM faculty_area_assignments LIKE 'type_id'");

if ($check && $check->num_rows == 0) {
    // Add the missing column
    $sql = "ALTER TABLE faculty_area_assignments ADD COLUMN type_id INT(11) NOT NULL DEFAULT 0";
    
    if ($conn->query($sql)) {
        echo "✅ Success: Added 'type_id' column to 'faculty_area_assignments'.<br>";
        
        // Update Primary Key to allow multiple assignments (same user, same area, different type)
        // We drop the old primary key (user_id, area_id) and add the new one (user_id, area_id, type_id)
        $conn->query("ALTER TABLE faculty_area_assignments DROP PRIMARY KEY");
        $conn->query("ALTER TABLE faculty_area_assignments ADD PRIMARY KEY (user_id, area_id, type_id)");
        echo "✅ Success: Updated table keys to support specific document assignments.<br>";
        
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ The database is already up to date.<br>";
}

echo "<br><a href='chairperson_dashboard.php?view=faculty'><strong>Return to Dashboard</strong></a>";
?>