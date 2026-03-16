<?php
// c:\xampp\htdocs\bisu_aaccup\bisu_aaccup\fix_db_schema.php
require_once 'config/db.php';

// Create document_feedback table
$sql = "CREATE TABLE IF NOT EXISTS `document_feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feedback_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  KEY `doc_id` (`doc_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`) ON DELETE CASCADE,
  CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if ($conn->query($sql) === TRUE) {
    echo "✅ Table 'document_feedback' created successfully.<br>";
    echo "You can now delete this file and access the Accreditor Dashboard.";
} else {
    echo "❌ Error creating table: " . $conn->error;
}
?>
