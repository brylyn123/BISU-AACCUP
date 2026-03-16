<?php
require_once 'config/db.php';

echo "<h2>Updating 'survey_ratings' Table...</h2>";

$sql = "ALTER TABLE survey_ratings ADD COLUMN accreditor_type ENUM('internal', 'external') NOT NULL DEFAULT 'internal' AFTER rated_by";

if ($conn->query($sql)) {
    echo "✅ Success: 'accreditor_type' column added to 'survey_ratings' table. You can now delete this file.";
} else {
    if ($conn->errno == 1060) { // Error code for "Duplicate column name"
        echo "ℹ️ Info: The 'accreditor_type' column already exists in the table.";
    } else {
        echo "❌ Error adding column: " . $conn->error;
    }
}
?>