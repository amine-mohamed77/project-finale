<?php
session_start();
require_once './db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Redirect to login page with a return URL
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Get owner_id and student_id from URL parameters
$owner_id = isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$demand_id = isset($_GET['demand_id']) ? intval($_GET['demand_id']) : 0;

// If we have a property_id but no owner_id, get the owner of the property
if ($property_id > 0 && $owner_id == 0) {
    try {
        $stmt = $conn->prepare("SELECT owner_id FROM house WHERE house_id = ?");
        $stmt->execute([$property_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $owner_id = $result['owner_id'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching property owner: " . $e->getMessage());
    }
}

// If we have a demand_id but no student_id, get the student of the demand
if ($demand_id > 0 && $student_id == 0) {
    try {
        $stmt = $conn->prepare("SELECT student_id FROM student_demand WHERE demand_id = ?");
        $stmt->execute([$demand_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $student_id = $result['student_id'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching demand student: " . $e->getMessage());
    }
}

// Set the IDs based on user type
if ($user_type == 'student') {
    $student_id = $user_id;
    // We need an owner_id
    if ($owner_id == 0) {
        // Redirect back with error
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=no_owner');
        exit();
    }
} else if ($user_type == 'owner') {
    $owner_id = $user_id;
    // We need a student_id
    if ($student_id == 0) {
        // Redirect back with error
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=no_student');
        exit();
    }
}

// Check if there are any existing messages between these users
// If not, create an initial system message to start the conversation
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as message_count FROM messages WHERE owner_id = ? AND student_id = ?");
    $stmt->execute([$owner_id, $student_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['message_count'] == 0) {
        // No existing messages, create an initial system message
        $system_message = "Conversation started";
        
        // Get property or demand details if available
        if ($property_id > 0) {
            $stmt = $conn->prepare("SELECT house_name FROM house WHERE house_id = ?");
            $stmt->execute([$property_id]);
            $property = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($property) {
                $system_message = "Inquiry about property: " . $property['house_name'];
            }
        } else if ($demand_id > 0) {
            $stmt = $conn->prepare("SELECT title FROM student_demand WHERE demand_id = ?");
            $stmt->execute([$demand_id]);
            $demand = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($demand) {
                $system_message = "Inquiry about demand: " . $demand['title'];
            }
        }
        
        // Insert the system message
        $stmt = $conn->prepare("INSERT INTO messages (owner_id, student_id, message_text, sender_type, message_date, is_read) 
                              VALUES (?, ?, ?, 'system', NOW(), TRUE)");
        $stmt->execute([$owner_id, $student_id, $system_message]);
    }
} catch (PDOException $e) {
    error_log("Error checking/creating initial message: " . $e->getMessage());
}

// Redirect to chat page
header("Location: chat.php?owner_id=$owner_id&student_id=$student_id");
exit();
?>
