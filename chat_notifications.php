<?php
/**
 * Chat notification helper functions for UniHousing platform
 */

/**
 * Get the count of unread messages for a specific user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param string $user_type User type (student or owner)
 * @return int Count of unread messages
 */
function getUnreadMessageCount($conn, $user_id, $user_type) {
    try {
        if ($user_type == 'student') {
            // For students, count unread messages from owners
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unread_count 
                FROM messages m
                JOIN owner_student os ON m.owner_id = os.owner_id AND m.student_id = os.student_id
                WHERE m.student_id = ? 
                AND m.sender_type = 'owner' 
                AND m.is_read = FALSE
            ");
            $stmt->execute([$user_id]);
        } else {
            // For owners, count unread messages from students
            $stmt = $conn->prepare("
                SELECT COUNT(*) as unread_count 
                FROM messages m
                JOIN owner_student os ON m.owner_id = os.owner_id AND m.student_id = os.student_id
                WHERE m.owner_id = ? 
                AND m.sender_type = 'student' 
                AND m.is_read = FALSE
            ");
            $stmt->execute([$user_id]);
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['unread_count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting unread message count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get a list of active chats with unread message counts for a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param string $user_type User type (student or owner)
 * @return array List of active chats with unread message counts
 */
function getActiveChatsWithUnreadCounts($conn, $user_id, $user_type) {
    try {
        if ($user_type == 'student') {
            $stmt = $conn->prepare("
                SELECT DISTINCT os.owner_id as contact_id, o.owner_name as contact_name,
                       (SELECT COUNT(*) FROM messages m 
                        WHERE m.owner_id = os.owner_id AND m.student_id = os.student_id 
                        AND m.sender_type = 'owner' AND m.is_read = FALSE) as unread_count
                FROM owner_student os
                JOIN owner o ON os.owner_id = o.owner_id
                WHERE os.student_id = ?
                ORDER BY (
                    SELECT MAX(m.message_date) FROM messages m 
                    WHERE m.owner_id = os.owner_id AND m.student_id = os.student_id
                ) DESC
            ");
            $stmt->execute([$user_id]);
        } else {
            $stmt = $conn->prepare("
                SELECT DISTINCT os.student_id as contact_id, s.student_name as contact_name,
                       (SELECT COUNT(*) FROM messages m 
                        WHERE m.owner_id = os.owner_id AND m.student_id = os.student_id 
                        AND m.sender_type = 'student' AND m.is_read = FALSE) as unread_count
                FROM owner_student os
                JOIN student s ON os.student_id = s.student_id
                WHERE os.owner_id = ?
                ORDER BY (
                    SELECT MAX(m.message_date) FROM messages m 
                    WHERE m.owner_id = os.owner_id AND m.student_id = os.student_id
                ) DESC
            ");
            $stmt->execute([$user_id]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active chats: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate HTML for a notification badge
 * 
 * @param int $count Number to display in the badge
 * @return string HTML for the notification badge
 */
function getNotificationBadgeHtml($count) {
    if ($count <= 0) {
        return '';
    }
    
    return '<span class="notification-badge">' . $count . '</span>';
}
?>
