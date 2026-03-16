<?php
require_once 'config/db.php';

echo "<h2>Checking 'document_feedback' Table...</h2>";

// Get current columns
$result = $conn->query("SHOW COLUMNS FROM document_feedback");
if (!$result) {
    die("❌ Table 'document_feedback' does not exist. Please run fix_db_schema.php first.");
}

$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

echo "Current columns: <code>" . implode(", ", $columns) . "</code><br><br>";

// Fix: document_id -> doc_id
if (in_array('document_id', $columns) && !in_array('doc_id', $columns)) {
    $conn->query("ALTER TABLE document_feedback CHANGE document_id doc_id INT(11) NOT NULL");
    echo "✅ Renamed 'document_id' to 'doc_id'.<br>";
}

// Fix: comment -> feedback_text
if ((in_array('comment', $columns) || in_array('comments', $columns)) && !in_array('feedback_text', $columns)) {
    $old_name = in_array('comment', $columns) ? 'comment' : 'comments';
    $conn->query("ALTER TABLE document_feedback CHANGE $old_name feedback_text TEXT NOT NULL");
    echo "✅ Renamed '$old_name' to 'feedback_text'.<br>";
}

echo "<br><strong>Done!</strong> Your table is now compatible with the dashboard.";
?>