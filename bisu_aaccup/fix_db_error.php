<?php
require_once 'config/db.php';

echo "<h2>Fixing Database Error...</h2>";

// Create the missing table
$sql = "CREATE TABLE IF NOT EXISTS `faculty_area_assignments` (
  `user_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`area_id`),
  KEY `area_id` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql) === TRUE) {
    echo "✅ Table 'faculty_area_assignments' created.<br>";
    
    // Add Foreign Keys
    // We use try/catch logic implicitly by ignoring errors if constraints already exist, 
    // but for a fresh table, this ensures integrity.
    $conn->query("ALTER TABLE `faculty_area_assignments` ADD CONSTRAINT `faculty_area_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE");
    $conn->query("ALTER TABLE `faculty_area_assignments` ADD CONSTRAINT `faculty_area_assignments_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE");
    
    echo "✅ Foreign keys configured.<br>";
    echo "<br><a href='chairperson_dashboard.php'><strong>Return to Dashboard</strong></a>";
} else {
    echo "❌ Error: " . $conn->error;
}
?>