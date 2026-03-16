<?php
require_once 'config/db.php';

echo "<h2>Fixing Database Schema...</h2>";

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM survey_ratings LIKE 'accreditor_type'");
if ($check && $check->num_rows == 0) {
    // Add the missing column
    $sql = "ALTER TABLE survey_ratings ADD COLUMN accreditor_type ENUM('internal', 'external') NOT NULL DEFAULT 'internal' AFTER rated_by";
    if ($conn->query($sql)) {
        echo "✅ Successfully added 'accreditor_type' column to 'survey_ratings' table.<br>";
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Column 'accreditor_type' already exists or table not found.<br>";
}

echo "<br><a href='dean_dashboard.php?view=self_survey'>Click here to return to Dean Dashboard</a>";
?>