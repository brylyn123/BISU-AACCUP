<?php
require_once 'config/db.php';

$sql = "CREATE TABLE IF NOT EXISTS `survey_ratings` (
  `rating_id` int(11) NOT NULL AUTO_INCREMENT,
  `program_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `parameter_index` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `rated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`rating_id`),
  KEY `program_area` (`program_id`, `area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) echo "✅ Table 'survey_ratings' created successfully. You can now refresh your dashboard.";
else echo "❌ Error: " . $conn->error;
?>