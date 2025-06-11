<?php
session_start();
require_once './db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode([
        'success' => false,
        'error' => 'You must be logged in to view messages'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if this is an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get owner_id and student_id from request parameters
    $request = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : $_POST;
    
    if (isset($request['owner_id']) && isset($request['student_id'])) {
        $owner_id = intval($request['owner_id']);
        $student_id = intval($request['student_id']);
        $last_message_id = isset($request['last_id']) ? intval($request['last_id']) : 0;
        
        // Validate that the user is either the owner or the student
        if (($user_type === 'owner' && $user_id != $owner_id) || 
            ($user_type === 'student' && $user_id != $student_id)) {
            echo json_encode([
                'success' => false,
                'error' => 'You are not authorized to view these messages'
            ]);
            exit();
        }
        
        try {
            // Get new messages
            $stmt = $conn->prepare("SELECT m.message_id, m.message_text, m.image_url, m.sender_type, 
                                  DATE_FORMAT(m.message_date, '%Y-%m-%d %H:%i:%s') as message_date, 
                                  DATE_FORMAT(m.message_date, '%h:%i %p') as formatted_time,
                                  m.is_read 
                                  FROM messages m 
                                  WHERE m.owner_id = ? AND m.student_id = ? AND m.message_id > ? 
                                  ORDER BY m.message_date ASC");
            $stmt->execute([$owner_id, $student_id, $last_message_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get profile pictures for each message
            foreach ($messages as &$message) {
                if ($message['sender_type'] == 'student') {
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
            }
            
            // Mark messages as read if the user is the recipient
            if ($user_type === 'owner') {
                $update_stmt = $conn->prepare("UPDATE messages 
                                            SET is_read = TRUE 
                                            WHERE owner_id = ? AND student_id = ? 
                                            AND sender_type = 'student' AND is_read = FALSE");
                $update_stmt->execute([$owner_id, $student_id]);
            } else {
                $update_stmt = $conn->prepare("UPDATE messages 
                                            SET is_read = TRUE 
                                            WHERE owner_id = ? AND student_id = ? 
                                            AND sender_type = 'owner' AND is_read = FALSE");
                $update_stmt->execute([$owner_id, $student_id]);
            }
            
            // Get unread count
            if ($user_type === 'owner') {
                $unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count 
                                            FROM messages 
                                            WHERE owner_id = ? AND sender_type = 'student' AND is_read = FALSE");
                $unread_stmt->execute([$owner_id]);
            } else {
                $unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count 
                                            FROM messages 
                                            WHERE student_id = ? AND sender_type = 'owner' AND is_read = FALSE");
                $unread_stmt->execute([$student_id]);
            }
            $unread_result = $unread_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'unread_count' => $unread_result['unread_count']
            ]);
            exit();
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error retrieving messages: ' . $e->getMessage()
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
        'error' => 'Invalid request'
    ]);
    exit();
}
?>