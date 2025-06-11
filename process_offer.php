<?php
session_start();
require_once './db.php';

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    $_SESSION['error'] = "You must be logged in as an owner to create property listings";
    header("Location: offers.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $title = trim($_POST['title'] ?? '');
    $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $location = trim($_POST['location'] ?? '');
    $property_type_id = filter_var($_POST['property_type_id'] ?? 0, FILTER_VALIDATE_INT);
    
    // Skip 'All Types' option (ID 1) if selected
    if ($property_type_id == 1) {
        $_SESSION['error'] = "Please select a specific property type (not 'All Types')";
        header("Location: create_offer.php");
        exit();
    }
    
    $bedrooms = filter_var($_POST['bedrooms'] ?? 0, FILTER_VALIDATE_INT);
    $bathrooms = filter_var($_POST['bathrooms'] ?? 0, FILTER_VALIDATE_INT);
    $description = trim($_POST['description'] ?? '');
    $city_id = filter_var($_POST['city_id'] ?? 0, FILTER_VALIDATE_INT);
    $owner_id = $_SESSION['user_id'];
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than zero";
    }
    
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    
    if ($property_type_id <= 0) {
        $errors[] = "Please select a property type";
    }
    
    if ($bedrooms < 0) {
        $errors[] = "Bedrooms cannot be negative";
    }
    
    if ($bathrooms < 0) {
        $errors[] = "Bathrooms cannot be negative";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if ($city_id <= 0) {
        $errors[] = "Please select a city";
    }
    
    // Check if images were uploaded
    if (empty($_FILES['property_images']['name'][0])) {
        $errors[] = "At least one property image is required";
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: offers.php");
        exit();
    }
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Get the next house_id
        $stmt = $conn->prepare("SELECT MAX(house_id) as max_id FROM house");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $house_id = ($result['max_id'] ?? 0) + 1;
        
        // Insert into house table
        $stmt = $conn->prepare("
            INSERT INTO house (
                house_id, house_title, house_price, house_location, 
                proprety_type_id, house_badroom, house_bathroom, 
                house_description, city_id, owner_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $house_id, $title, $price, $location,
            $property_type_id, $bedrooms, $bathrooms,
            $description, $city_id, $owner_id
        ]);
        
        // Process and save images
        $upload_dir = 'uploads/properties/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Handle multiple image uploads
        $image_count = count($_FILES['property_images']['name']);
        $max_images = min($image_count, 5); // Limit to 5 images
        
        for ($i = 0; $i < $max_images; $i++) {
            if ($_FILES['property_images']['error'][$i] === 0) {
                $file_extension = pathinfo($_FILES['property_images']['name'][$i], PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array(strtolower($file_extension), $allowed_extensions)) {
                    // Generate unique filename
                    $filename = 'property_' . $house_id . '_' . ($i + 1) . '_' . uniqid() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['property_images']['tmp_name'][$i], $filepath)) {
                        // Get next proprety_pictures_id
                        $stmt = $conn->prepare("SELECT MAX(proprety_pictures_id) as max_id FROM proprety_pictures");
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $picture_id = ($result['max_id'] ?? 0) + 1;
                        
                        // Insert into proprety_pictures table
                        $stmt = $conn->prepare("INSERT INTO proprety_pictures (proprety_pictures_id, proprety_pictures_name) VALUES (?, ?)");
                        $stmt->execute([$picture_id, $filepath]);
                        
                        // Link picture to house
                        $stmt = $conn->prepare("INSERT INTO house_property_pictures (house_id, proprety_pictures_id) VALUES (?, ?)");
                        $stmt->execute([$house_id, $picture_id]);
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Your property listing has been created successfully!";
        header("Location: offers.php");
        exit();
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $_SESSION['error'] = "Error creating property listing: " . $e->getMessage();
        header("Location: offers.php");
        exit();
    }
}

// If not a POST request, redirect to offers page
header("Location: offers.php");
exit();
