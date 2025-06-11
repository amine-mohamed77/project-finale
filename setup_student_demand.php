<?php
// Script to create the student_demand table
require_once './db.php';

try {
    // Create the student_demand table
    $sql = "CREATE TABLE IF NOT EXISTS `student_demand` (
      `demand_id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` int(11) NOT NULL,
      `university` varchar(255) NOT NULL,
      `location` varchar(255) NOT NULL,
      `property_type` varchar(50) NOT NULL,
      `budget` decimal(10,2) NOT NULL,
      `move_in_date` date NOT NULL,
      `duration` varchar(50) NOT NULL,
      `requirements` text,
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`demand_id`),
      KEY `student_id` (`student_id`),
      CONSTRAINT `student_demand_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    // Execute the SQL
    $conn->exec($sql);
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
    echo "<h1 style='color: #1b54cf;'>Success!</h1>";
    echo "<p style='font-size: 18px;'>The student_demand table has been created successfully.</p>";
    echo "<p><a href='demands.php' style='display: inline-block; background-color: #1b54cf; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Demands Page</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); background-color: #ffebee;'>";
    echo "<h1 style='color: #d32f2f;'>Error</h1>";
    echo "<p style='font-size: 18px;'>Error creating table: " . $e->getMessage() . "</p>";
    echo "<p><a href='demands.php' style='display: inline-block; background-color: #1b54cf; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go Back</a></p>";
    echo "</div>";
}
?>
