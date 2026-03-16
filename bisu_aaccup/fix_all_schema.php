<?php
require_once 'config/db.php';

echo "<h1>Database Schema Repair</h1>";

// 1. Fix document_feedback table
$sql_feedback = "CREATE TABLE IF NOT EXISTS `document_feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feedback_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  KEY `doc_id` (`doc_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_feedback)) echo "✅ Checked/Created 'document_feedback' table.<br>";
else echo "❌ Error 'document_feedback': " . $conn->error . "<br>";

// 2. Fix doc_id column name
$res = $conn->query("SHOW COLUMNS FROM document_feedback LIKE 'document_id'");
if ($res && $res->num_rows > 0) {
    $conn->query("ALTER TABLE document_feedback CHANGE document_id doc_id INT(11) NOT NULL");
    echo "✅ Renamed 'document_id' to 'doc_id' in document_feedback.<br>";
}

// 3. Add status column to documents
$res = $conn->query("SHOW COLUMNS FROM documents LIKE 'status'");
if ($res && $res->num_rows == 0) {
    $conn->query("ALTER TABLE documents ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER type_id");
    echo "✅ Added 'status' column to documents.<br>";
}

// 4. Add reviewed_file_path to documents
$res = $conn->query("SHOW COLUMNS FROM documents LIKE 'reviewed_file_path'");
if ($res && $res->num_rows == 0) {
    $conn->query("ALTER TABLE documents ADD COLUMN reviewed_file_path VARCHAR(255) NULL AFTER file_path");
    echo "✅ Added 'reviewed_file_path' column to documents.<br>";
}

// 5. Add area_id to users
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'area_id'");
if ($res && $res->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN area_id INT(11) NULL AFTER program_id");
    echo "✅ Added 'area_id' column to users.<br>";
}

// 6. Ensure document_types exists
$sql_types = "CREATE TABLE IF NOT EXISTS `document_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if ($conn->query($sql_types)) {
    echo "✅ Checked/Created 'document_types' table.<br>";
    // Insert defaults if empty
    if ($conn->query("SELECT COUNT(*) FROM document_types")->fetch_row()[0] == 0) {
        $conn->query("INSERT INTO document_types (type_name) VALUES ('Compliance Report'), ('Evidence'), ('Narrative Report'), ('Capsule Presentation')");
        echo "✅ Inserted default document types.<br>";
    }
}
echo "<br><strong>All checks complete. Please try your dashboard now.</strong>";
?>