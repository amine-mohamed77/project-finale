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
        'error' => 'Not logged in',
        'unread_count' => 0
    ]);
    exit();
}

// Get unread message count
$unread_count = getUnreadMessageCount($conn, $_SESSION['user_id'], $_SESSION['user_type']);

// Return JSON response
echo json_encode([
    'success' => true,
    'unread_count' => $unread_count,
    'user_id' => $_SESSION['user_id'],
    'user_type' => $_SESSION['user_type']
]);
?>
