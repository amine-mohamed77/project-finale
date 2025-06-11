<?php
session_start();
require_once './db.php';
require_once './chat_notifications.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode([
        'success' => false,
        'error' => 'You must be logged in to access messages'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if we have the required parameters
if (isset($_GET['owner_id']) && isset($_GET['student_id']) && isset($_GET['last_id'])) {
    $owner_id = intval($_GET['owner_id']);
    $student_id = intval($_GET['student_id']);
    $last_id = intval($_GET['last_id']);
    
    // Validate that the user is either the owner or the student
    if (($user_type === 'owner' && $user_id != $owner_id) || 
        ($user_type === 'student' && $user_id != $student_id)) {
        echo json_encode([
            'success' => false,
            'error' => 'You are not authorized to access these messages'
        ]);
        exit();
    }
    
    try {
        // Get new messages
        $stmt = $conn->prepare("
            SELECT m.*, DATE_FORMAT(m.message_date, '%Y-%m-%d %H:%i:%s') as formatted_date
            FROM messages m
            WHERE m.owner_id = ? AND m.student_id = ? AND m.message_id > ?
            ORDER BY m.message_date ASC
        ");
        $stmt->execute([$owner_id, $student_id, $last_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read if they were sent by the other party
        if ($user_type == 'student') {
            $update_stmt = $conn->prepare("
                UPDATE messages 
                SET is_read = TRUE 
                WHERE owner_id = ? AND student_id = ? AND sender_type = 'owner' AND is_read = FALSE
            ");
            $update_stmt->execute([$owner_id, $student_id]);
        } else {
            $update_stmt = $conn->prepare("
                UPDATE messages 
                SET is_read = TRUE 
                WHERE owner_id = ? AND student_id = ? AND sender_type = 'student' AND is_read = FALSE
            ");
            $update_stmt->execute([$owner_id, $student_id]);
        }
        
        echo json_encode([
            'success' => true,
            'messages' => $messages
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
?>
