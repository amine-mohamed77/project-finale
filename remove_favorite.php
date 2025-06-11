<?php
session_start();
require_once './db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "You must be logged in as a student to manage favorites";
    header("Location: login.php");
    exit();
}

// Check if house_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No property specified";
    header("Location: Profile.php#favorites");
    exit();
}

$house_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

try {
    // Remove from favorites
    $stmt = $conn->prepare("DELETE FROM student_house WHERE student_id = ? AND house_id = ?");
    $stmt->execute([$student_id, $house_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Property removed from favorites";
    } else {
        $_SESSION['info'] = "Property was not in your favorites";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error removing from favorites: " . $e->getMessage();
}

// Redirect back to the favorites section
header("Location: Profile.php#favorites");
exit();
