<?php
session_start();
require_once './db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode([
        'success' => false,
        'error' => 'You must be logged in to upload images'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if this is a POST request with a file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    // Get required parameters
    if (isset($_POST['owner_id']) && isset($_POST['student_id'])) {
        $owner_id = intval($_POST['owner_id']);
        $student_id = intval($_POST['student_id']);
        
        // Validate that the user is either the owner or the student
        if (($user_type === 'owner' && $user_id != $owner_id) || 
            ($user_type === 'student' && $user_id != $student_id)) {
            echo json_encode([
                'success' => false,
                'error' => 'You are not authorized to send messages in this conversation'
            ]);
            exit();
        }
        
        // Check if file is an actual image
        $check = getimagesize($_FILES['image']['tmp_name']);
        if ($check === false) {
            echo json_encode([
                'success' => false,
                'error' => 'File is not an image'
            ]);
            exit();
        }
        
        // Define allowed file types
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            echo json_encode([
                'success' => false,
                'error' => 'Only JPG, PNG, GIF, and WEBP files are allowed'
            ]);
            exit();
        }
        
        // Check file size (max 5MB)
        if ($_FILES['image']['size'] > 5000000) {
            echo json_encode([
                'success' => false,
                'error' => 'File is too large (max 5MB)'
            ]);
            exit();
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/chat_images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate a unique filename
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'chat_' . time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $upload_dir . $filename;
        
        // Try to upload the file
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            try {
                // Debug information
                error_log("Attempting to insert image: " . $target_file);
                
                // Insert the message with image URL
                $stmt = $conn->prepare("INSERT INTO messages (owner_id, student_id, message_text, image_url, sender_type, message_date, is_read) 
                                      VALUES (?, ?, ?, ?, ?, NOW(), FALSE)");
                $message_text = isset($_POST['message']) && !empty($_POST['message']) ? trim($_POST['message']) : '';
                
                try {
                    $stmt->execute([$owner_id, $student_id, $message_text, $target_file, $user_type]);
                    error_log("Image inserted successfully with path: " . $target_file);
                } catch (PDOException $e) {
                    error_log("Database error during image insert: " . $e->getMessage());
                    throw $e; // Re-throw to be caught by the outer catch block
                }
                $message_id = $conn->lastInsertId();
                
                // Get the inserted message with formatted date
                $get_stmt = $conn->prepare("SELECT message_id, message_text, image_url, sender_type, 
                                          DATE_FORMAT(message_date, '%Y-%m-%d %H:%i:%s') as message_date, 
                                          is_read 
                                          FROM messages 
                                          WHERE message_id = ?");
                $get_stmt->execute([$message_id]);
                $message = $get_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get profile picture for the sender
                if ($user_type == 'student') {
                    // Get student profile picture
                    $pic_stmt = $conn->prepare("SELECT p.picture_url 
                                               FROM student s 
                                               LEFT JOIN picture p ON s.picture_id = p.picture_id 
                                               WHERE s.student_id = ?");
                    $pic_stmt->execute([$student_id]);
                    $pic_result = $pic_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($pic_result && !empty($pic_result['picture_url'])) {
                        $message['profile_pic'] = $pic_result['picture_url'];
                    } else {
                        $message['profile_pic'] = 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';
                    }
                } else {
                    // Get owner profile picture
                    $pic_stmt = $conn->prepare("SELECT p.picture_url 
                                               FROM owner o 
                                               LEFT JOIN picture p ON p.owner_id = o.owner_id 
                                               WHERE o.owner_id = ?");
                    $pic_stmt->execute([$owner_id]);
                    $pic_result = $pic_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($pic_result && !empty($pic_result['picture_url'])) {
                        $message['profile_pic'] = $pic_result['picture_url'];
                    } else {
                        $message['profile_pic'] = 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $message
                ]);
                exit();
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Error sending message: ' . $e->getMessage()
                ]);
                exit();
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to upload image'
            ]);
            exit();
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Missing required parameters'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method or no image provided'
    ]);
    exit();
}
?>
