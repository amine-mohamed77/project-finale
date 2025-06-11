<?php
session_start();
require_once './db.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode([
        'success' => false,
        'error' => 'You must be logged in to access notifications'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Determine the action to perform
$action = isset($_GET['action']) ? $_GET['action'] : 'get';

switch ($action) {
    case 'get':
        // Get unread notifications count and latest notifications
        getNotifications($conn, $user_id, $user_type);
        break;
        
    case 'mark_read':
        // Mark notification as read
        markNotificationAsRead($conn, $user_id, $user_type);
        break;
        
    case 'mark_all_read':
        // Mark all notifications as read
        markAllNotificationsAsRead($conn, $user_id, $user_type);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
        break;
}

/**
 * Get notifications for a user
 */
function getNotifications($conn, $user_id, $user_type) {
    try {
        // Get unread count
        $count_stmt = $conn->prepare("
            SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE user_id = ? AND user_type = ? AND is_read = FALSE
        ");
        $count_stmt->execute([$user_id, $user_type]);
        $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get latest notifications (limit to 5)
        $notifications_stmt = $conn->prepare("
            SELECT notification_id, notification_type, related_id, message, is_read, 
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as formatted_date
            FROM notifications 
            WHERE user_id = ? AND user_type = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $notifications_stmt->execute([$user_id, $user_type]);
        $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the dates as "time ago"
        foreach ($notifications as &$notification) {
            $notification['time_ago'] = getTimeAgo($notification['formatted_date']);
        }
        
        echo json_encode([
            'success' => true,
            'unread_count' => $count_result['unread_count'],
            'notifications' => $notifications
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error retrieving notifications: ' . $e->getMessage()
        ]);
    }
}

/**
 * Mark a notification as read
 */
function markNotificationAsRead($conn, $user_id, $user_type) {
    if (!isset($_GET['notification_id'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing notification_id parameter'
        ]);
        return;
    }
    
    $notification_id = intval($_GET['notification_id']);
    
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE notification_id = ? AND user_id = ? AND user_type = ?
        ");
        $stmt->execute([$notification_id, $user_id, $user_type]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error marking notification as read: ' . $e->getMessage()
        ]);
    }
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead($conn, $user_id, $user_type) {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND user_type = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id, $user_type]);
        
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error marking all notifications as read: ' . $e->getMessage()
        ]);
    }
}

/**
 * Format date as "time ago"
 */
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "Just now";
    } else if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } else if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } else if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else if ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
    } else if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . " month" . ($months > 1 ? "s" : "") . " ago";
    } else {
        $years = floor($diff / 31536000);
        return $years . " year" . ($years > 1 ? "s" : "") . " ago";
    }
}
?>
