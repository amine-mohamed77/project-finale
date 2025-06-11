<?php
session_start();
require_once './db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode([
        'success' => false,
        'error' => 'You must be logged in to access message details'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if message_id is provided
if (!isset($_GET['message_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing message_id parameter'
    ]);
    exit();
}

$message_id = intval($_GET['message_id']);

// For debugging
$debug = [];
$debug['user_id'] = $user_id;
$debug['user_type'] = $user_type;
$debug['message_id'] = $message_id;

try {
    // First try to get the message directly by message_id
    $stmt = $conn->prepare("
        SELECT owner_id, student_id 
        FROM messages 
        WHERE message_id = ?
    ");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug['direct_lookup'] = $message ? true : false;
    
    // If message not found, check if the related_id is actually a conversation identifier
    if (!$message) {
        $debug['trying_alternative_lookup'] = true;
        
        // If user is a student, look for conversations where they are the student
        if ($user_type === 'student') {
            $stmt = $conn->prepare("
                SELECT DISTINCT owner_id, student_id
                FROM messages
                WHERE student_id = ?
                ORDER BY message_date DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
        } else { // User is an owner
            $stmt = $conn->prepare("
                SELECT DISTINCT owner_id, student_id
                FROM messages
                WHERE owner_id = ?
                ORDER BY message_date DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
        }
        
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug['alternative_lookup_result'] = $message ? true : false;
    }
    
    if ($message) {
        $debug['found_message'] = true;
        $debug['owner_id'] = $message['owner_id'];
        $debug['student_id'] = $message['student_id'];
        
        // For student users, always ensure they are the student_id in the conversation
        if ($user_type === 'student' && $user_id != $message['student_id']) {
            $message['student_id'] = $user_id;
            $debug['adjusted_student_id'] = true;
        }
        // For owner users, always ensure they are the owner_id in the conversation
        else if ($user_type === 'owner' && $user_id != $message['owner_id']) {
            $message['owner_id'] = $user_id;
            $debug['adjusted_owner_id'] = true;
        }
        
        echo json_encode([
            'success' => true,
            'owner_id' => $message['owner_id'],
            'student_id' => $message['student_id'],
            'debug' => $debug
        ]);
    } else {
        // If all else fails, create a default response based on user type
        $debug['using_fallback'] = true;
        
        if ($user_type === 'student') {
            // For students, use their ID as student_id and get the first owner they've messaged
            $stmt = $conn->prepare("
                SELECT owner_id 
                FROM messages 
                WHERE student_id = ? 
                GROUP BY owner_id 
                ORDER BY MAX(message_date) DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $owner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($owner) {
                echo json_encode([
                    'success' => true,
                    'owner_id' => $owner['owner_id'],
                    'student_id' => $user_id,
                    'debug' => $debug
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'No conversation found for this student',
                    'debug' => $debug
                ]);
            }
        } else { // User is an owner
            // For owners, use their ID as owner_id and get the first student they've messaged
            $stmt = $conn->prepare("
                SELECT student_id 
                FROM messages 
                WHERE owner_id = ? 
                GROUP BY student_id 
                ORDER BY MAX(message_date) DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                echo json_encode([
                    'success' => true,
                    'owner_id' => $user_id,
                    'student_id' => $student['student_id'],
                    'debug' => $debug
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'No conversation found for this owner',
                    'debug' => $debug
                ]);
            }
        }
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error retrieving message details: ' . $e->getMessage()
    ]);
}
?>
