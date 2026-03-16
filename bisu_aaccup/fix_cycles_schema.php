<?php
require_once 'config/db.php';

echo "<h2>Updating Cycles Table Schema...</h2>";

$cols = [
    'survey_date' => 'DATE NULL DEFAULT NULL', 
    'submission_deadline' => 'DATE NULL DEFAULT NULL'
];

foreach ($cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM cycles LIKE '$col'");
    if ($check->num_rows == 0) {
        if ($conn->query("ALTER TABLE cycles ADD COLUMN $col $def AFTER valid_from")) {
            echo "✅ Added '$col' column.<br>";
        } else {
            echo "❌ Error adding '$col': " . $conn->error . "<br>";
        }
    } else {
        echo "ℹ️ Column '$col' already exists.<br>";
    }
}
echo "<br><strong>Update complete. You can now return to <a href='admin_cycles.php'>Manage Levels</a>.</strong>";
?>