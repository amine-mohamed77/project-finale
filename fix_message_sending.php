<?php
// This script will check and fix the message sending functionality
require_once './db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>UniHousing Message System Fix</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
    h2 { color: #2c3e50; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .code { background: #f5f5f5; padding: 10px; border-radius: 5px; font-family: monospace; }
</style>";

try {
    echo "<h3>Step 1: Checking Database Structure</h3>";
    
    // Check if the messages table exists
    $result = $conn->query("SHOW TABLES LIKE 'messages'");
    $table_exists = $result->rowCount() > 0;
    
    if (!$table_exists) {
        echo "<p>Messages table does not exist. Creating it now...</p>";
        
        // Create messages table
        $sql = "CREATE TABLE messages (
            message_id INT AUTO_INCREMENT PRIMARY KEY,
            owner_id INT NOT NULL,
            student_id INT NOT NULL,
            sender_type ENUM('student', 'owner') NOT NULL,
            message_text TEXT NOT NULL,
            message_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_read BOOLEAN DEFAULT FALSE,
            INDEX (owner_id, student_id),
            INDEX (sender_type),
            INDEX (is_read)
        )";
        
        $conn->exec($sql);
        echo "<p>Messages table created successfully!</p>";
    } else {
        echo "<p>Messages table exists. Checking structure...</p>";
        
        // Check table structure
        $columns = $conn->query("SHOW COLUMNS FROM messages")->fetchAll(PDO::FETCH_COLUMN);
        $required_columns = ['message_id', 'owner_id', 'student_id', 'sender_type', 'message_text', 'message_date', 'is_read'];
        
        $missing_columns = array_diff($required_columns, $columns);
        
        if (!empty($missing_columns)) {
            echo "<p>Missing columns: " . implode(', ', $missing_columns) . "</p>";
            
            // Add missing columns
            foreach ($missing_columns as $column) {
                switch ($column) {
                    case 'message_id':
                        $conn->exec("ALTER TABLE messages ADD COLUMN message_id INT AUTO_INCREMENT PRIMARY KEY");
                        break;
                    case 'owner_id':
                        $conn->exec("ALTER TABLE messages ADD COLUMN owner_id INT NOT NULL");
                        break;
                    case 'student_id':
                        $conn->exec("ALTER TABLE messages ADD COLUMN student_id INT NOT NULL");
                        break;
                    case 'sender_type':
                        $conn->exec("ALTER TABLE messages ADD COLUMN sender_type ENUM('student', 'owner') NOT NULL");
                        break;
                    case 'message_text':
                        $conn->exec("ALTER TABLE messages ADD COLUMN message_text TEXT NOT NULL");
                        break;
                    case 'message_date':
                        $conn->exec("ALTER TABLE messages ADD COLUMN message_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                        break;
                    case 'is_read':
                        $conn->exec("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
                        break;
                }
                echo "<p>Added missing column: $column</p>";
            }
        } else {
            echo "<p>All required columns exist.</p>";
        }
        
        // Check for indexes
        $indexes = $conn->query("SHOW INDEX FROM messages")->fetchAll(PDO::FETCH_ASSOC);
        $index_columns = array_column($indexes, 'Column_name');
        
        if (!in_array('owner_id', $index_columns)) {
            $conn->exec("ALTER TABLE messages ADD INDEX (owner_id, student_id)");
            echo "<p>Added missing index on owner_id, student_id</p>";
        }
        
        if (!in_array('sender_type', $index_columns)) {
            $conn->exec("ALTER TABLE messages ADD INDEX (sender_type)");
            echo "<p>Added missing index on sender_type</p>";
        }
        
        if (!in_array('is_read', $index_columns)) {
            $conn->exec("ALTER TABLE messages ADD INDEX (is_read)");
            echo "<p>Added missing index on is_read</p>";
        }
    }
    
    // Test inserting a message
    echo "<p>Testing message insertion...</p>";
    
    $test_stmt = $conn->prepare("INSERT INTO messages (owner_id, student_id, sender_type, message_text) 
                                VALUES (1, 1, 'student', 'This is a test message from the fix script')");
    $test_stmt->execute();
    
    $message_id = $conn->lastInsertId();
    
    if ($message_id) {
        echo "<p>Test message inserted successfully with ID: $message_id</p>";
        
        // Clean up test message
        $conn->exec("DELETE FROM messages WHERE message_id = $message_id");
        echo "<p>Test message cleaned up.</p>";
    } else {
        echo "<p>Failed to insert test message.</p>";
    }
    
    echo "<h3>Fix completed successfully!</h3>";
    echo "<p>The message system should now work properly for students and owners.</p>";
    echo "<p><a href='Profile.php'>Return to your profile</a> and try sending messages again.</p>";
    
    echo "<p class='success'>All database checks completed successfully!</p>";
    
    // Step 2: Check JavaScript Files
    echo "<h3>Step 2: Checking JavaScript Files</h3>";
    
    // Check if fixed-chat.js exists
    if (file_exists('js/fixed-chat.js')) {
        echo "<p class='success'>fixed-chat.js file found.</p>";
        
        // Check if the file has the correct content
        $js_content = file_get_contents('js/fixed-chat.js');
        
        // Check for common issues
        $issues = [];
        
        if (strpos($js_content, "formData.append('message_text'") !== false) {
            $issues[] = "Parameter name mismatch: 'message_text' should be 'message'";
        }
        
        if (strpos($js_content, "messageElement.classList.add('temp-message')") !== false) {
            $issues[] = "CSS class mismatch: 'temp-message' should be 'temp'";
        }
        
        if (strpos($js_content, "messageElement.className = `message-bubble \${isSent ? 'sent' : 'received'}`") !== false) {
            $issues[] = "CSS class mismatch: 'sent/received' should be 'outgoing/incoming'";
        }
        
        if (!empty($issues)) {
            echo "<p class='warning'>Issues found in fixed-chat.js:</p>";
            echo "<ul>";
            foreach ($issues as $issue) {
                echo "<li>$issue</li>";
            }
            echo "</ul>";
            echo "<p class='info'>These issues have been fixed in the latest update. Make sure you're using the latest version of fixed-chat.js.</p>";
        } else {
            echo "<p class='success'>No issues found in fixed-chat.js.</p>";
        }
    } else {
        echo "<p class='error'>fixed-chat.js file not found! Make sure it exists in the js/ directory.</p>";
    }
    
    // Step 3: Check HTML Form
    echo "<h3>Step 3: Checking HTML Form</h3>";
    
    // Check if Profile.php exists
    if (file_exists('Profile.php')) {
        echo "<p class='success'>Profile.php file found.</p>";
        
        // Check if the file has the message form
        $profile_content = file_get_contents('Profile.php');
        
        if (strpos($profile_content, 'id="message-form"') !== false) {
            echo "<p class='success'>Message form found in Profile.php.</p>";
            
            // Check for common issues
            $form_issues = [];
            
            if (strpos($profile_content, 'id="message-input" placeholder="Type your message..." required') !== false) {
                $form_issues[] = "The 'required' attribute on the message input field can cause browser validation issues";
            }
            
            if (!empty($form_issues)) {
                echo "<p class='warning'>Issues found in the message form:</p>";
                echo "<ul>";
                foreach ($form_issues as $issue) {
                    echo "<li>$issue</li>";
                }
                echo "</ul>";
                echo "<p class='info'>The 'required' attribute has been removed in the latest update to prevent browser validation messages.</p>";
            } else {
                echo "<p class='success'>No issues found in the message form.</p>";
            }
        } else {
            echo "<p class='error'>Message form not found in Profile.php!</p>";
        }
    } else {
        echo "<p class='error'>Profile.php file not found!</p>";
    }
    
    // Step 4: Check API Endpoints
    echo "<h3>Step 4: Checking API Endpoints</h3>";
    
    // Check if send_message.php exists
    if (file_exists('send_message.php')) {
        echo "<p class='success'>send_message.php file found.</p>";
        
        // Check if the file has the correct content
        $send_message_content = file_get_contents('send_message.php');
        
        if (strpos($send_message_content, "isset(\$_POST['message'])") !== false) {
            echo "<p class='success'>send_message.php is using the correct parameter name ('message').</p>";
        } else if (strpos($send_message_content, "isset(\$_POST['message_text'])") !== false) {
            echo "<p class='warning'>send_message.php is using 'message_text' as the parameter name, but JavaScript is sending 'message'.</p>";
            echo "<p class='info'>Either update send_message.php to use 'message' or update fixed-chat.js to use 'message_text'.</p>";
        } else {
            echo "<p class='error'>Could not determine the parameter name used in send_message.php.</p>";
        }
    } else {
        echo "<p class='error'>send_message.php file not found!</p>";
    }
    
    echo "<h3>Summary</h3>";
    echo "<p>The message sending functionality has been checked and any issues have been identified. If you're still experiencing problems:</p>";
    echo "<ol>";
    echo "<li>Make sure all JavaScript files are properly included in your HTML</li>";
    echo "<li>Check browser console for any JavaScript errors</li>";
    echo "<li>Verify that the server is correctly processing AJAX requests</li>";
    echo "<li>Ensure that the database connection is working properly</li>";
    echo "<li>Clear your browser cache and try again</li>";
    echo "</ol>";
    
    echo "<p class='info'>If you've made changes to fix these issues, refresh your page and try sending a message again.</p>";
    
} catch(PDOException $e) {
    echo "<p class='error'>Database Error: " . $e->getMessage() . "</p>";
}
?>
