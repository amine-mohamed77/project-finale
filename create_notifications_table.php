<?php
// Include database connection
require_once './db.php';

try {
    // Create notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type VARCHAR(10) NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        related_id INT,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id, user_type),
        INDEX (is_read)
    )";
    
    $conn->exec($sql);
    echo "Notifications table created successfully!";
    
    // Create a trigger to automatically create notifications when a new message is sent
    $trigger_sql = "
    CREATE TRIGGER IF NOT EXISTS after_message_insert
    AFTER INSERT ON messages
    FOR EACH ROW
    BEGIN
        DECLARE sender_name VARCHAR(255);
        
        -- Get sender name based on sender_type
        IF NEW.sender_type = 'student' THEN
            SELECT student_name INTO sender_name 
            FROM student WHERE student_id = NEW.student_id;
            
            -- Create notification for owner
            INSERT INTO notifications (user_id, user_type, notification_type, related_id, message, is_read)
            VALUES (NEW.owner_id, 'owner', 'new_message', NEW.message_id, 
                   CONCAT(sender_name, ' sent you a new message: ', SUBSTRING(NEW.message_text, 1, 50), 
                         IF(LENGTH(NEW.message_text) > 50, '...', '')), FALSE);
        ELSE
            SELECT owner_name INTO sender_name 
            FROM owner WHERE owner_id = NEW.owner_id;
            
            -- Create notification for student
            INSERT INTO notifications (user_id, user_type, notification_type, related_id, message, is_read)
            VALUES (NEW.student_id, 'student', 'new_message', NEW.message_id, 
                   CONCAT(sender_name, ' sent you a new message: ', SUBSTRING(NEW.message_text, 1, 50), 
                         IF(LENGTH(NEW.message_text) > 50, '...', '')), FALSE);
        END IF;
    END;
    ";
    
    // Drop the trigger if it exists to avoid errors
    $conn->exec("DROP TRIGGER IF EXISTS after_message_insert");
    
    // Create the trigger
    $conn->exec($trigger_sql);
    echo "<br>Message notification trigger created successfully!";
    
} catch(PDOException $e) {
    echo "Error creating notifications table: " . $e->getMessage();
}
?>
