<?php
require_once './db.php';

try {
    // First ensure the owner_student table exists and has the correct structure
    $sql = "CREATE TABLE IF NOT EXISTS owner_student (
        owner_id INT NOT NULL,
        student_id INT NOT NULL,
        owner_student_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (owner_id, student_id)
    )";
    
    $conn->exec($sql);
    echo "owner_student table verified/created successfully<br>";
    
    // Create messages table without foreign key constraint first
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT NOT NULL,
        student_id INT NOT NULL,
        sender_type ENUM('owner', 'student') NOT NULL,
        message_text TEXT NOT NULL,
        message_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE
    )";
    
    $conn->exec($sql);
    echo "Messages table created successfully<br>";
    
    // Add indexes for better performance
    $sql = "CREATE INDEX IF NOT EXISTS idx_messages_owner_student 
            ON messages(owner_id, student_id)";
    $conn->exec($sql);
    echo "Added index on messages table<br>";
    
} catch(PDOException $e) {
    echo "Error setting up chat tables: " . $e->getMessage() . "<br>";
}
?>
