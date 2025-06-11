<?php
session_start();
require_once './db.php';
header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in as a student to add favorites',
        'redirect' => 'login.php'
    ]);
    exit();
}

// Check if house_id is provided
if (!isset($_POST['house_id']) || empty($_POST['house_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No property specified'
    ]);
    exit();
}

$house_id = $_POST['house_id'];
$student_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : 'add';

try {
    // First check if the house exists
    $stmt = $conn->prepare("SELECT house_id FROM house WHERE house_id = ?");
    $stmt->execute([$house_id]);
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Property not found'
        ]);
        exit();
    }
    
    // Check if already in favorites
    $stmt = $conn->prepare("SELECT * FROM student_house WHERE student_id = ? AND house_id = ?");
    $stmt->execute([$student_id, $house_id]);
    $exists = $stmt->rowCount() > 0;
    
    if ($action == 'add') {
        if ($exists) {
            // Already in favorites
            echo json_encode([
                'success' => true,
                'message' => 'This property is already in your favorites',
                'status' => 'exists'
            ]);
        } else {
            // Add to favorites with current date
            $current_date = date('Y-m-d');
            
            // Begin transaction for safety
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("INSERT INTO student_house (student_id, house_id, student_house_date) VALUES (?, ?, ?)");
            $result = $stmt->execute([$student_id, $house_id, $current_date]);
            
            if ($result) {
                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Property added to favorites',
                    'status' => 'added'
                ]);
            } else {
                $conn->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to add property to favorites'
                ]);
            }
        }
    } else if ($action == 'remove') {
        if ($exists) {
            // Remove from favorites
            $stmt = $conn->prepare("DELETE FROM student_house WHERE student_id = ? AND house_id = ?");
            $result = $stmt->execute([$student_id, $house_id]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Property removed from favorites',
                    'status' => 'removed'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to remove property from favorites'
                ]);
            }
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Property was not in your favorites',
                'status' => 'not_exists'
            ]);
        }
    } else if ($action == 'check') {
        echo json_encode([
            'success' => true,
            'is_favorite' => $exists
        ]);
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
