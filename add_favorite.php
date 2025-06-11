<?php
session_start();
require_once './db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "You must be logged in as a student to add favorites";
    header("Location: login.php");
    exit();
}

// Check if house_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No property specified";
    header("Location: offers.php");
    exit();
}

$house_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

try {
    // First check if the house exists
    $stmt = $conn->prepare("SELECT house_id FROM house WHERE house_id = ?");
    $stmt->execute([$house_id]);
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Property not found";
        header("Location: offers.php");
        exit();
    }
    
    // Check if already in favorites
    $stmt = $conn->prepare("SELECT * FROM student_house WHERE student_id = ? AND house_id = ?");
    $stmt->execute([$student_id, $house_id]);
    
    if ($stmt->rowCount() > 0) {
        // Already in favorites
        $_SESSION['info'] = "This property is already in your favorites";
    } else {
        // Add to favorites with current date
        $current_date = date('Y-m-d');
        
        // Begin transaction for safety
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("INSERT INTO student_house (student_id, house_id, student_house_date) VALUES (?, ?, ?)");
        $result = $stmt->execute([$student_id, $house_id, $current_date]);
        
        if ($result) {
            $conn->commit();
            $_SESSION['success'] = "Property added to favorites";
            
            // Log success for debugging
            error_log("Successfully added house ID $house_id to favorites for student ID $student_id");
        } else {
            $conn->rollBack();
            $_SESSION['error'] = "Failed to add property to favorites";
            error_log("Failed to add house ID $house_id to favorites for student ID $student_id");
        }
    }
} catch (PDOException $e) {
    // Rollback transaction if there was an error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    $_SESSION['error'] = "Error adding to favorites: " . $e->getMessage();
    error_log("Database error adding favorite: " . $e->getMessage());
}

// Redirect back to the property page or referring page
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'offers.php';
header("Location: $redirect");
exit();
