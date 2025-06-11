<?php
session_start();
require_once './db.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "You are not authorized to delete demands";
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Check if demand ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['demand_error'] = "Invalid demand ID";
    header("Location: Profile.php#my-demands");
    exit();
}

$demand_id = intval($_GET['id']);

try {
    // First check if the demand belongs to the logged-in student
    $stmt = $conn->prepare("SELECT student_id FROM student_demand WHERE demand_id = ?");
    $stmt->execute([$demand_id]);
    $demand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$demand) {
        $_SESSION['demand_error'] = "Demand not found";
        header("Location: Profile.php#my-demands");
        exit();
    }
    
    if ($demand['student_id'] != $student_id) {
        $_SESSION['demand_error'] = "You can only delete your own demands";
        header("Location: Profile.php#my-demands");
        exit();
    }
    
    // Delete the demand
    $stmt = $conn->prepare("DELETE FROM student_demand WHERE demand_id = ?");
    $stmt->execute([$demand_id]);
    
    $_SESSION['demand_success'] = "Your housing demand has been deleted successfully";
    header("Location: Profile.php#my-demands");
    exit();
    
} catch (PDOException $e) {
    $_SESSION['demand_error'] = "Error deleting demand: " . $e->getMessage();
    header("Location: Profile.php#my-demands");
    exit();
}
?>
