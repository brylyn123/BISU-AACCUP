<?php
require_once 'config/db.php';

echo "<h2>Updating Assignment Schema for Document Types...</h2>";

// 1. Add type_id column if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM faculty_area_assignments LIKE 'type_id'");
if ($check->num_rows == 0) {
    // Add type_id, default to 0 (General/All)
    $sql_add = "ALTER TABLE faculty_area_assignments ADD COLUMN type_id INT(11) NOT NULL DEFAULT 0";
    if ($conn->query($sql_add)) {
        echo "✅ Added 'type_id' column.<br>";
        
        // 2. Update Primary Key to include type_id (allowing multiple assignments per user/area)
        // First drop existing PK
        $conn->query("ALTER TABLE faculty_area_assignments DROP PRIMARY KEY");
        // Add new PK
        $sql_pk = "ALTER TABLE faculty_area_assignments ADD PRIMARY KEY (user_id, area_id, type_id)";
        if ($conn->query($sql_pk)) {
            echo "✅ Updated Primary Key to support multiple task types.<br>";
        } else {
            echo "❌ Error updating PK: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Schema already updated.<br>";
}
echo "<br><a href='chairperson_dashboard.php?view=faculty'>Return to Dashboard</a>";
?>