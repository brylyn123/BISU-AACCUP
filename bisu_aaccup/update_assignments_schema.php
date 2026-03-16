<?php
require_once 'config/db.php';

echo "<h2>Updating Database for Multiple Assignments...</h2>";

// 1. Create the new junction table
$sql_create = "
CREATE TABLE IF NOT EXISTS `faculty_area_assignments` (
  `user_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`area_id`),
  KEY `area_id` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql_create) === TRUE) {
    echo "✅ Table 'faculty_area_assignments' created or already exists.<br>";
} else {
    die("❌ Error creating table: " . $conn->error . "<br>");
}

// Add foreign keys if they don't exist
$fk_check_user = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'faculty_area_assignments' AND CONSTRAINT_NAME = 'faa_ibfk_1'");
if ($fk_check_user->num_rows == 0) {
    $conn->query("ALTER TABLE `faculty_area_assignments` ADD CONSTRAINT `faa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;");
    echo "✅ Foreign key for user_id added.<br>";
}
$fk_check_area = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'faculty_area_assignments' AND CONSTRAINT_NAME = 'faa_ibfk_2'");
if ($fk_check_area->num_rows == 0) {
    $conn->query("ALTER TABLE `faculty_area_assignments` ADD CONSTRAINT `faa_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE;");
    echo "✅ Foreign key for area_id added.<br>";
}

// 2. Migrate existing data from users.area_id to the new table
$col_check = $conn->query("SHOW COLUMNS FROM `users` LIKE 'area_id'");
if ($col_check->num_rows > 0) {
    $sql_migrate = "INSERT IGNORE INTO faculty_area_assignments (user_id, area_id) SELECT user_id, area_id FROM users WHERE area_id IS NOT NULL";
    if ($conn->query($sql_migrate) === TRUE) {
        echo "✅ Existing assignments migrated successfully.<br>";
    }

    // 3. Drop the old area_id column from users table
    $fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'area_id' AND REFERENCED_TABLE_NAME = 'areas'");
    if ($fk_check->num_rows > 0) {
        $fk_name = $fk_check->fetch_assoc()['CONSTRAINT_NAME'];
        $conn->query("ALTER TABLE `users` DROP FOREIGN KEY `$fk_name`");
        echo "✅ Dropped foreign key constraint on users.area_id.<br>";
    }
    
    $index_check = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'area_id'");
    if ($index_check->num_rows > 0) {
        $conn->query("ALTER TABLE `users` DROP INDEX `area_id`");
        echo "✅ Dropped index on users.area_id.<br>";
    }

    if ($conn->query("ALTER TABLE `users` DROP COLUMN `area_id`") === TRUE) {
        echo "✅ Column 'area_id' dropped from 'users' table.<br>";
    }
} else {
    echo "ℹ️ Column 'area_id' does not exist in 'users' table. No action needed.<br>";
}

echo "<h3>Database schema update complete!</h3>";
?>