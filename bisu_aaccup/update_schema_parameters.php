<?php
require_once 'config/db.php';

echo "<h2>Setting up Survey Parameters...</h2>";

// Create survey_parameters table
$sql = "CREATE TABLE IF NOT EXISTS `survey_parameters` (
  `param_id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `parameter_text` text NOT NULL,
  `parameter_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`param_id`),
  KEY `area_id` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql)) {
    echo "✅ Table 'survey_parameters' created.<br>";
    
    // Check if empty, then seed with default data
    if ($conn->query("SELECT COUNT(*) FROM survey_parameters")->fetch_row()[0] == 0) {
        $stmt = $conn->prepare("INSERT INTO survey_parameters (area_id, parameter_text, parameter_order) VALUES (?, ?, ?)");
        
        // Seed for 10 areas, 5 generic questions each
        for ($area = 1; $area <= 10; $area++) {
            $questions = [
                "System - The area has a well-defined system and procedure.",
                "Implementation - The procedures are effectively implemented.",
                "Outcomes - The program produces the desired outcomes for this area.",
                "Sustainability - There is evidence of sustainability and continuous improvement.",
                "Documentation - Supporting documents are complete and organized."
            ];
            foreach ($questions as $idx => $text) {
                $order = $idx + 1;
                $stmt->bind_param("isi", $area, $text, $order);
                $stmt->execute();
            }
        }
        echo "✅ Seeded default survey parameters for Areas 1-10.<br>";
    } else {
        echo "ℹ️ Table already has data. Skipping seed.<br>";
    }
} else {
    echo "❌ Error: " . $conn->error;
}
echo "<br><a href='accreditor_dashboard.php'>Return to Dashboard</a>";
?>