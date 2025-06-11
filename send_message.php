<?php
session_start();
require_once './db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode([
        'success' => false,
        'error' => 'You must be logged in to send messages'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if this is an AJAX request with POST method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get required parameters
    if (isset($_POST['owner_id']) && isset($_POST['student_id']) && isset($_POST['message'])) {
        $owner_id = intval($_POST['owner_id']);
        $student_id = intval($_POST['student_id']);
        $message_text = trim($_POST['message']);
        
        // Validate message text
        if (empty($message_text)) {
            echo json_encode([
                'success' => false,
                'error' => 'Message cannot be empty'
            ]);
            exit();
        }
        
        // Validate that the user is either the owner or the student
        if (($user_type === 'owner' && $user_id != $owner_id) || 
            ($user_type === 'student' && $user_id != $student_id)) {
            echo json_encode([
                'success' => false,
                'error' => 'You are not authorized to send messages in this conversation'
            ]);
            exit();
        }
        
        try {
            // Insert the message
            $stmt = $conn->prepare("INSERT INTO messages (owner_id, student_id, message_text, sender_type, message_date, is_read) 
                                  VALUES (?, ?, ?, ?, NOW(), FALSE)");
            $stmt->execute([$owner_id, $student_id, $message_text, $user_type]);
            $message_id = $conn->lastInsertId();
            
            // Get the inserted message with formatted date
            $get_stmt = $conn->prepare("SELECT message_id, message_text, sender_type, 
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
            'error' => 'Missing required parameters'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit();
}
?>