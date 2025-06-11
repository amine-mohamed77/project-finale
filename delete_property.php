<?php
session_start();
require_once './db.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    $_SESSION['error'] = "You don't have permission to delete properties";
    header("Location: login.php");
    exit();
}

// Check if property ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No property specified for deletion";
    header("Location: Profile.php#my-listings");
    exit();
}

$property_id = $_GET['id'];
$owner_id = $_SESSION['user_id'];

try {
    // First verify that the property belongs to this owner
    $stmt = $conn->prepare("SELECT house_id FROM house WHERE house_id = ? AND owner_id = ?");
    $stmt->execute([$property_id, $owner_id]);
    
    if ($stmt->rowCount() === 0) {
        // Property doesn't exist or doesn't belong to this owner
        $_SESSION['error'] = "You don't have permission to delete this property";
        header("Location: Profile.php#my-listings");
        exit();
    }
    
    // Begin transaction for safe deletion
    $conn->beginTransaction();
    
    // Delete related records from house_property_pictures
    $stmt = $conn->prepare("DELETE FROM house_property_pictures WHERE house_id = ?");
    $stmt->execute([$property_id]);
    
    // Delete from student_house (favorites)
    $stmt = $conn->prepare("DELETE FROM student_house WHERE house_id = ?");
    $stmt->execute([$property_id]);
    
    // Finally delete the house record
    $stmt = $conn->prepare("DELETE FROM house WHERE house_id = ?");
    $stmt->execute([$property_id]);
    
    // Commit the transaction
    $conn->commit();
    
    $_SESSION['success'] = "Property has been successfully deleted";
    
} catch (PDOException $e) {
    // Rollback the transaction if something failed
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['error'] = "Error deleting property: " . $e->getMessage();
}

// Redirect back to profile page
header("Location: Profile.php#my-listings");
exit();
