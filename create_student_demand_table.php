<?php
// Script to create the student_demand table
require_once './db.php';

try {
    // Read the SQL from the file
    $sql = file_get_contents('create_student_demand_table.sql');
    
    // Execute the SQL
    $conn->exec($sql);
    
    echo "Student demand table created successfully!";
    echo "<p><a href='demands.php'>Go back to demands page</a></p>";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
