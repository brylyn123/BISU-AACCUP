<?php
require_once 'config/db.php';

echo "<h2>Creating Notifications Table...</h2>";

$sql = "
CREATE TABLE IF NOT EXISTS `user_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'The user who receives the notification',
  `doc_id` int(11) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql)) {
    echo "✅ Table 'user_notifications' created or already exists.<br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br>";
}

echo "<br><strong>Schema update complete. <a href='focal_dashboard.php'>Return to Dashboard</a></strong>";
?>