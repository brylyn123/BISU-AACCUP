<?php
require_once 'config/db.php';

echo "<h2>Updating Document Types...</h2>";

// Check if 'Survey Instrument' exists
$check = $conn->query("SELECT type_id FROM document_types WHERE type_name = 'Survey Instrument'");
if ($check && $check->num_rows == 0) {
    $sql = "INSERT INTO document_types (type_name) VALUES ('Survey Instrument')";
    if ($conn->query($sql)) {
        echo "✅ Success: Added 'Survey Instrument' to document types.<br>";
    } else {
        echo "❌ Error: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ 'Survey Instrument' type already exists.<br>";
}
echo "<br><strong>Update complete. You can now upload Survey Instruments in the Documents page.</strong>";
?>