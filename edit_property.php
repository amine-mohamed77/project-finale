<?php
session_start();
require_once './db.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    $_SESSION['error'] = "You don't have permission to edit properties";
    header("Location: login.php");
    exit();
}

// Check if property ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No property specified for editing";
    header("Location: Profile.php#my-listings");
    exit();
}

$property_id = $_GET['id'];
$owner_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Load property types and cities directly from the database for reliability
$property_types = [];
$cities = [];

try {
    // First check if the database has property types and cities
    $stmt = $conn->prepare("SELECT COUNT(*) FROM proprety_type");
    $stmt->execute();
    $property_type_count = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM city");
    $stmt->execute();
    $city_count = $stmt->fetchColumn();
    
    // If database has property types, use them
    if ($property_type_count > 0) {
        $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type ORDER BY proprety_type_name");
        $stmt->execute();
        $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Otherwise try to use JSON file
        if (file_exists('property_types.json')) {
            $property_types_json = file_get_contents('property_types.json');
            $property_types = json_decode($property_types_json, true);
            
            if ($property_types === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error parsing property_types.json: " . json_last_error_msg());
            }
            
            // Import property types to database
            $conn->beginTransaction();
            foreach ($property_types as $type) {
                if (isset($type['proprety_type_id']) && isset($type['proprety_type_name'])) {
                    $stmt = $conn->prepare("INSERT INTO proprety_type (proprety_type_id, proprety_type_name) VALUES (?, ?)");
                    $stmt->execute([$type['proprety_type_id'], $type['proprety_type_name']]);
                }
            }
            $conn->commit();
            
            // Get property types from database after import
            $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type ORDER BY proprety_type_name");
            $stmt->execute();
            $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "No property types found in the database or JSON file.";
        }
    }
    
    // If database has cities, use them
    if ($city_count > 0) {
        $stmt = $conn->prepare("SELECT city_id, city_name FROM city ORDER BY city_name");
        $stmt->execute();
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Otherwise try to use JSON file
        if (file_exists('cities.json')) {
            $cities_json = file_get_contents('cities.json');
            $cities = json_decode($cities_json, true);
            
            if ($cities === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error parsing cities.json: " . json_last_error_msg());
            }
            
            // Import cities to database
            $conn->beginTransaction();
            foreach ($cities as $city) {
                if (isset($city['city_id']) && isset($city['city_name'])) {
                    $stmt = $conn->prepare("INSERT INTO city (city_id, city_name) VALUES (?, ?)");
                    $stmt->execute([$city['city_id'], $city['city_name']]);
                }
            }
            $conn->commit();
            
            // Get cities from database after import
            $stmt = $conn->prepare("SELECT city_id, city_name FROM city ORDER BY city_name");
            $stmt->execute();
            $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "No cities found in the database or JSON file.";
        }
    }
    
    // If we still don't have property types or cities, create a helpful error message
    if (empty($property_types)) {
        $error = "No property types found. Please add property types first.";
    }
    
    if (empty($cities)) {
        $error = "No cities found. Please add cities first.";
    }
    
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
}

// Get property data
try {
    // First check if the property exists and belongs to this owner
    $stmt = $conn->prepare("SELECT * FROM house WHERE house_id = ? AND owner_id = ?");
    $stmt->execute([$property_id, $owner_id]);
    
    if ($stmt->rowCount() === 0) {
        // Property doesn't exist or doesn't belong to this owner
        $_SESSION['error'] = "You don't have permission to edit this property";
        header("Location: Profile.php#my-listings");
        exit();
    }
    
    // Get full property data with safer queries that won't cause warnings
    $stmt = $conn->prepare("
        SELECT h.*, c.city_name, pt.proprety_type_name
        FROM house h
        JOIN city c ON h.city_id = c.city_id
        JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
        WHERE h.house_id = ?
    ");
    $stmt->execute([$property_id]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get property images separately
    $stmt = $conn->prepare("
        SELECT pp.proprety_pictures_id, pp.proprety_pictures_name
        FROM proprety_pictures pp 
        JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
        WHERE hpp.house_id = ?
    ");
    $stmt->execute([$property_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process image data
    $property['image_urls'] = [];
    $property['image_ids'] = [];
    
    foreach ($images as $image) {
        $property['image_urls'][] = $image['proprety_pictures_name'];
        $property['image_ids'][] = $image['proprety_pictures_id'];
    }
    
} catch (PDOException $e) {
    $error = "Error loading property: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_property'])) {
    $title = trim($_POST['title']);
    $price = trim($_POST['price']);
    $location = trim($_POST['location']);
    $property_type_id = $_POST['property_type'];
    $city_id = $_POST['city'];
    $bedrooms = $_POST['bedrooms'];
    $bathrooms = $_POST['bathrooms'];
    $description = trim($_POST['description']);
    
    // Validation
    if (empty($title) || empty($price) || empty($location) || empty($description)) {
        $error = "All fields are required";
    } else if (!is_numeric($price) || $price <= 0) {
        $error = "Price must be a positive number";
    } else if (!is_numeric($bedrooms) || $bedrooms <= 0) {
        $error = "Bedrooms must be a positive number";
    } else if (!is_numeric($bathrooms) || $bathrooms <= 0) {
        $error = "Bathrooms must be a positive number";
    } else {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Update house record
            $stmt = $conn->prepare("
                UPDATE house 
                SET house_title = ?, house_price = ?, house_location = ?, 
                    proprety_type_id = ?, house_badroom = ?, house_bathroom = ?, 
                    house_description = ?, city_id = ? 
                WHERE house_id = ? AND owner_id = ?
            ");
            $stmt->execute([
                $title, $price, $location,
                $property_type_id, $bedrooms, $bathrooms,
                $description, $city_id, $property_id, $owner_id
            ]);
            
            // Handle image uploads if any
            if (isset($_FILES['property_images']) && !empty($_FILES['property_images']['name'][0])) {
                $upload_dir = 'uploads/properties/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $max_images = 5;
                
                // Count existing images
                $existing_images_count = count($property['image_ids']);
                $available_slots = $max_images - $existing_images_count;
                
                if ($available_slots > 0) {
                    $uploaded_files = 0;
                    
                    for ($i = 0; $i < count($_FILES['property_images']['name']) && $uploaded_files < $available_slots; $i++) {
                        $file_name = $_FILES['property_images']['name'][$i];
                        $file_tmp = $_FILES['property_images']['tmp_name'][$i];
                        $file_type = $_FILES['property_images']['type'][$i];
                        $file_size = $_FILES['property_images']['size'][$i];
                        $file_error = $_FILES['property_images']['error'][$i];
                        
                        if ($file_error === 0 && in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                            $new_file_name = 'property_' . $property_id . '_' . uniqid() . '.' . $file_ext;
                            $destination = $upload_dir . $new_file_name;
                            
                            if (move_uploaded_file($file_tmp, $destination)) {
                                // Generate a unique ID for the picture
                                // First, find the maximum existing ID
                                $stmt = $conn->prepare("SELECT MAX(proprety_pictures_id) as max_id FROM proprety_pictures");
                                $stmt->execute();
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                $new_picture_id = ($result['max_id'] ?? 0) + 1;
                                
                                // Insert into proprety_pictures with the new ID
                                $stmt = $conn->prepare("INSERT INTO proprety_pictures (proprety_pictures_id, proprety_pictures_name) VALUES (?, ?)");
                                $stmt->execute([$new_picture_id, $new_file_name]);
                                $picture_id = $new_picture_id;
                                
                                // Only proceed if we got a valid picture_id
                                if ($picture_id) {
                                    // Link to house
                                    $stmt = $conn->prepare("INSERT INTO house_property_pictures (house_id, proprety_pictures_id) VALUES (?, ?)");
                                    $stmt->execute([$property_id, $picture_id]);
                                } else {
                                    // Log error if picture_id is not valid
                                    error_log("Failed to get valid picture_id for property $property_id");
                                }
                                
                                $uploaded_files++;
                            }
                        }
                    }
                }
            }
            
            // Handle image deletions if any
            if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $image_id) {
                    // Get image filename
                    $stmt = $conn->prepare("SELECT proprety_pictures_name FROM proprety_pictures WHERE proprety_pictures_id = ?");
                    $stmt->execute([$image_id]);
                    $image_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($image_data) {
                        // First remove from house_property_pictures (the relationship table)
                        $stmt = $conn->prepare("DELETE FROM house_property_pictures WHERE house_id = ? AND proprety_pictures_id = ?");
                        $stmt->execute([$property_id, $image_id]);
                        
                        // Then delete from proprety_pictures
                        $stmt = $conn->prepare("DELETE FROM proprety_pictures WHERE proprety_pictures_id = ?");
                        $stmt->execute([$image_id]);
                        
                        // Finally delete the image file if it exists
                        $image_path = 'uploads/properties/' . $image_data['proprety_pictures_name'];
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }
                    }
                }
            }
            
            // Commit the transaction
            $conn->commit();
            
            // Set success message in session and redirect to profile page
            $_SESSION['success'] = "Property has been successfully updated";
            header("Location: Profile.php#my-listings");
            exit();
            
        } catch (PDOException $e) {
            // Rollback the transaction if something failed
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "Error updating property: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Edit your property listing on UniHousing">
    <meta name="theme-color" content="#2563eb">
    <title>Edit Property - UniHousing</title>
    
    <!-- Favicon -->
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="profile-responsive.css">
    <style>
        /* Edit Property Page Styles */
        body {
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            line-height: 1.5;
        }
        
        .edit-property-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #1e293b;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 2.25rem;
            margin-bottom: 1.25rem;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #475569;
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease;
            background-color: #ffffff;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            border-color: #4f7df9;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 125, 249, 0.2);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.5rem;
        }
        
        /* Property Images Styles */
        .property-images-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .property-image-item {
            position: relative;
            height: 150px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        
        .property-image-item:hover {
            transform: translateY(-3px);
        }
        
        .property-image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .delete-image {
            position: absolute;
            top: 8px;
            right: 8px;
            background-color: rgba(239, 68, 68, 0.9);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
        }
        
        .delete-image:hover {
            background-color: #ef4444;
            transform: scale(1.1);
        }
        
        .marked-for-deletion {
            opacity: 0.5;
        }
        
        /* File Upload Styling */
        input[type="file"] {
            background-color: #f1f5f9;
            padding: 1rem;
            border-radius: 8px;
            width: 100%;
            cursor: pointer;
        }
        
        /* Button Styles */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
        }
        
        .btn-primary {
            background-color: #4f7df9;
            color: white;
            box-shadow: 0 2px 4px rgba(79, 125, 249, 0.2);
        }
        
        .btn-primary:hover {
            background-color: #3a68e0;
            box-shadow: 0 4px 8px rgba(79, 125, 249, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: transparent;
            color: #4f7df9;
            border: 1px solid #4f7df9;
        }
        
        .btn-outline:hover {
            background-color: rgba(79, 125, 249, 0.1);
        }
        
        /* Back Link Styling */
        .back-link {
            display: inline-flex;
            align-items: center;
            margin-bottom: 1.5rem;
            color: #4f7df9;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .back-link:hover {
            color: #3a68e0;
        }
        
        .back-link i {
            margin-right: 0.5rem;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .edit-property-container {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader">
       <dotlottie-player src="https://lottie.host/11f7d3b7-bf99-4f72-be1e-e72f766c5d3d/KJ6V4IJRpi.lottie" background="transparent" speed="1" style="width: 300px; height: 300px" loop autoplay></dotlottie-player>
    </div>
    
   
    
    <section class="profile-section">
        <div class="container">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="edit-property-container">
                <a href="Profile.php#my-listings" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to My Listings
                </a>
                
                <h1>Edit Property</h1>
                
                <form action="edit_property.php?id=<?php echo $property_id; ?>" method="POST" enctype="multipart/form-data" class="property-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Property Title</label>
                            <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($property['house_title']); ?>" placeholder="e.g. Modern Studio Apartment Near Campus">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Monthly Rent (USD)</label>
                            <input type="number" id="price" name="price" required value="<?php echo htmlspecialchars($property['house_price']); ?>" placeholder="e.g. 800">
                        </div>
                        <div class="form-group">
                            <label for="location">Address</label>
                            <input type="text" id="location" name="location" required value="<?php echo htmlspecialchars($property['house_location']); ?>" placeholder="e.g. 123 University Ave">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type</label>
                            <select id="property_type" name="property_type" required>
                                <?php if (!empty($property_types)): ?>
                                    <?php foreach ($property_types as $type): ?>
                                        <?php if (isset($type['proprety_type_id']) && isset($type['proprety_type_name'])): ?>
                                            <option value="<?php echo $type['proprety_type_id']; ?>" <?php echo ($property['proprety_type_id'] == $type['proprety_type_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['proprety_type_name']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="<?php echo $property['proprety_type_id']; ?>" selected>
                                        <?php echo htmlspecialchars($property['proprety_type_name']); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="city">City</label>
                            <select id="city" name="city" required>
                                <?php if (!empty($cities)): ?>
                                    <?php foreach ($cities as $city): ?>
                                        <?php if (isset($city['city_id']) && isset($city['city_name'])): ?>
                                            <option value="<?php echo $city['city_id']; ?>" <?php echo ($property['city_id'] == $city['city_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($city['city_name']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="<?php echo $property['city_id']; ?>" selected>
                                        <?php echo htmlspecialchars($property['city_name']); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bedrooms">Bedrooms</label>
                            <input type="number" id="bedrooms" name="bedrooms" required value="<?php echo htmlspecialchars($property['house_badroom']); ?>" min="1" max="10">
                        </div>
                        <div class="form-group">
                            <label for="bathrooms">Bathrooms</label>
                            <input type="number" id="bathrooms" name="bathrooms" required value="<?php echo htmlspecialchars($property['house_bathroom']); ?>" min="1" max="10">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 100%;">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="6" required placeholder="Describe your property in detail..."><?php echo htmlspecialchars($property['house_description']); ?></textarea>
                        </div>
                    </div>
                    
                    <?php if (!empty($property['image_urls'])): ?>
                    <div class="form-row">
                        <div class="form-group" style="flex: 100%;">
                            <label>Current Images</label>
                            <div class="property-images-preview">
                                <?php for ($i = 0; $i < count($property['image_urls']); $i++): ?>
                                    <?php if (isset($property['image_urls'][$i]) && isset($property['image_ids'][$i])): ?>
                                        <div class="property-image-item">
                                            <?php $image_path = 'uploads/properties/' . htmlspecialchars($property['image_urls'][$i]); ?>
                                            <?php if (file_exists($image_path)): ?>
                                                <img src="<?php echo $image_path; ?>" alt="Property Image">
                                            <?php else: ?>
                                                <div style="height: 100%; display: flex; align-items: center; justify-content: center; background-color: #f1f5f9; color: #64748b;">
                                                    <i class="fas fa-image" style="font-size: 2rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <label class="delete-image" title="Delete this image">
                                                <input type="checkbox" name="delete_images[]" value="<?php echo $property['image_ids'][$i]; ?>" style="display: none;">
                                                <i class="fas fa-times"></i>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <p class="form-help">Check the images you want to delete.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 100%;">
                            <label for="property_images">Add More Images</label>
                            <input type="file" id="property_images" name="property_images[]" multiple accept="image/*">
                            <?php $remaining_slots = isset($property['image_urls']) ? max(0, 5 - count($property['image_urls'])) : 5; ?>
                            <p class="form-help">You can upload up to <?php echo $remaining_slots; ?> more images. Recommended size: 1200x800 pixels.</p>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_property" class="btn btn-primary">Update Property</button>
                        <a href="Profile.php#my-listings" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
    
   
    
    <!-- Scripts -->
    <script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script>
    <script>
        // Preloader
        window.addEventListener('load', function() {
            document.querySelector('.preloader').style.display = 'none';
        });
        
        // Image deletion toggle
        document.querySelectorAll('.delete-image').forEach(function(deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                this.parentElement.classList.toggle('marked-for-deletion', checkbox.checked);
                if (checkbox.checked) {
                    this.parentElement.style.opacity = '0.5';
                } else {
                    this.parentElement.style.opacity = '1';
                }
            });
        });
    </script>
</body>
</html>
