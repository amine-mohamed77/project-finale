<?php
session_start();
require_once './db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode([
        'success' => false,
        'error' => 'You must be logged in to mark messages as read'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if this is an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if owner_id and student_id are provided
    if (isset($_POST['owner_id']) && isset($_POST['student_id'])) {
        $owner_id = intval($_POST['owner_id']);
        $student_id = intval($_POST['student_id']);
        
        // Validate that the user is either the owner or the student
        if (($user_type === 'owner' && $user_id != $owner_id) || 
            ($user_type === 'student' && $user_id != $student_id)) {
            echo json_encode([
                'success' => false,
                'error' => 'You are not authorized to mark these messages as read'
            ]);
            exit();
        }
        
        try {
            if ($user_type === 'owner') {
                // Mark messages from student as read
                $stmt = $conn->prepare("UPDATE messages 
                                      SET is_read = TRUE 
                                      WHERE owner_id = ? AND student_id = ? 
                                      AND sender_type = 'student' AND is_read = FALSE");
                $stmt->execute([$owner_id, $student_id]);
            } else {
                // Mark messages from owner as read
                $stmt = $conn->prepare("UPDATE messages 
                                      SET is_read = TRUE 
                                      WHERE owner_id = ? AND student_id = ? 
                                      AND sender_type = 'owner' AND is_read = FALSE");
                $stmt->execute([$owner_id, $student_id]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Messages marked as read'
            ]);
            exit();
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Error marking messages as read: ' . $e->getMessage()
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
