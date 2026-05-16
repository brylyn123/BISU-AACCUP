<?php
require_once __DIR__ . '/config/db.php';
requireAdminOrCliForMaintenance();

$migration_file = __DIR__ . '/db/migrations/20260516-repository-workflow.sql';

if (!file_exists($migration_file)) {
    die('Repository migration file not found.');
}

$sql = file_get_contents($migration_file);
if ($sql === false) {
    die('Unable to read repository migration file.');
}

echo "<h2>Applying Repository Workflow Schema</h2>";

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    echo "<p>Repository tables created or already available.</p>";
    echo "<p><a href='admin_dashboard.php'>Return to Admin Dashboard</a></p>";
} else {
    echo "<p>Error applying repository schema: " . htmlspecialchars($conn->error) . "</p>";
}
