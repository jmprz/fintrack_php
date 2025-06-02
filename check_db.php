<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'connection.php';

echo "<pre>";

// Check if the expenses table exists
$table_check = $con->query("SHOW TABLES LIKE 'expenses'");
echo "Expenses table exists: " . ($table_check->num_rows > 0 ? "Yes" : "No") . "\n";

if ($table_check->num_rows > 0) {
    // Show table structure
    $structure = $con->query("DESCRIBE expenses");
    echo "\nTable structure:\n";
    while ($row = $structure->fetch_assoc()) {
        echo print_r($row, true) . "\n";
    }
    
    // Show any existing records
    $records = $con->query("SELECT COUNT(*) as count FROM expenses");
    $count = $records->fetch_assoc()['count'];
    echo "\nNumber of records: " . $count . "\n";
}

echo "</pre>";

$con->close();
?> 