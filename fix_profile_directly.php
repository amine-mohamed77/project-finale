<?php
// Direct fix for Profile.php image display issues
require_once './db.php';

// Create the image helper function if it doesn't exist
$helper_dir = __DIR__ . '/includes';
if (!is_dir($helper_dir)) {
    mkdir($helper_dir, 0777, true);
}

$helper_file = $helper_dir . '/image_helper.php';
$helper_content = '<?php
/**
 * Helper functions for image path handling
 */

/**
 * Ensures an image path is valid and accessible
 * 
 * @param string $image_path The original image path from database
 * @return string The corrected image path or default image if not found
 */
function get_valid_image_path($image_path) {
    // Default image to use if no valid image is found
    $default_image = "https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80";
    
    // If empty, return default
    if (empty($image_path)) {
        return $default_image;
    }
    
    // If it\'s already a URL, return as is
    if (strpos($image_path, \'http\') === 0) {
        return $image_path;
    }
    
    // Check if the path already includes uploads/properties/
    $has_prefix = strpos($image_path, \'uploads/properties/\') === 0;
    
    // Try the path as is
    if (file_exists($image_path)) {
        return $image_path;
    }
    
    // Try with prefix if it doesn\'t have one
    if (!$has_prefix) {
        $with_prefix = \'uploads/properties/\' . $image_path;
        if (file_exists($with_prefix)) {
            return $with_prefix;
        }
    }
    
    // Try without prefix if it has one
    if ($has_prefix) {
        $filename = str_replace(\'uploads/properties/\', \'\', $image_path);
        if (file_exists(\'uploads/properties/\' . $filename)) {
            return \'uploads/properties/\' . $filename;
        }
    }
    
    // If we get here, no valid image was found
    return $default_image;
}
?>';
file_put_contents($helper_file, $helper_content);

// Now directly modify the Profile.php file
$profile_file = __DIR__ . '/Profile.php';
$profile_content = file_get_contents($profile_file);

// 1. Make sure the helper file is included
if (strpos($profile_content, "require_once './includes/image_helper.php';") === false) {
    $profile_content = str_replace(
        "require_once './db.php';", 
        "require_once './db.php';\nrequire_once './includes/image_helper.php';", 
        $profile_content
    );
}

// 2. Fix the image display in the favorites section
$favorites_pattern = '<div class="property-image">
                                        <?php if (!empty($property[\'image_url\'])): ?>
                                        <img src="<?php echo htmlspecialchars($property[\'image_url\']); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">
                                        <?php else: ?>
                                        <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">
                                        <?php endif; ?>';

$favorites_replacement = '<div class="property-image">
                                        <?php 
                                        // Use the helper function to get a valid image path
                                        $image_src = get_valid_image_path($property[\'image_url\']);
                                        ?>
                                        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">';

$profile_content = str_replace($favorites_pattern, $favorites_replacement, $profile_content);

// 3. Fix the image display in the owner's listings section (if it exists)
$listings_pattern = '<div class="property-image">
                                <img src="<?php echo htmlspecialchars($property[\'image_url\']); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">';

$listings_replacement = '<div class="property-image">
                                <?php 
                                // Use the helper function to get a valid image path
                                $image_src = get_valid_image_path($property[\'image_url\']);
                                ?>
                                <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">';

$profile_content = str_replace($listings_pattern, $listings_replacement, $profile_content);

// 4. Add a fallback function directly in the file in case the include doesn't work
$fallback_function = '
// Fallback image helper function in case the include doesn\'t work
if (!function_exists(\'get_valid_image_path\')) {
    function get_valid_image_path($image_path) {
        // Default image to use if no valid image is found
        $default_image = "https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80";
        
        // If empty, return default
        if (empty($image_path)) {
            return $default_image;
        }
        
        // If it\'s already a URL, return as is
        if (strpos($image_path, \'http\') === 0) {
            return $image_path;
        }
        
        // Check if the path already includes uploads/properties/
        $has_prefix = strpos($image_path, \'uploads/properties/\') === 0;
        
        // Try the path as is
        if (file_exists($image_path)) {
            return $image_path;
        }
        
        // Try with prefix if it doesn\'t have one
        if (!$has_prefix) {
            $with_prefix = \'uploads/properties/\' . $image_path;
            if (file_exists($with_prefix)) {
                return $with_prefix;
            }
        }
        
        // Try without prefix if it has one
        if ($has_prefix) {
            $filename = str_replace(\'uploads/properties/\', \'\', $image_path);
            if (file_exists(\'uploads/properties/\' . $filename)) {
                return \'uploads/properties/\' . $filename;
            }
        }
        
        // If we get here, no valid image was found
        return $default_image;
    }
}
';

// Add the fallback function after the authentication check
$auth_check_pattern = "if (!isset(\$_SESSION['user_id'])) {
    \$_SESSION['error'] = \"Please login to view your profile\";
    header(\"Location: login.php\");
    exit();
}";

$profile_content = str_replace(
    $auth_check_pattern, 
    $auth_check_pattern . "\n" . $fallback_function, 
    $profile_content
);

// 5. Save the updated file
file_put_contents($profile_file, $profile_content);

// 6. Fix the database image paths
try {
    // Get all property images
    $stmt = $conn->query("SELECT proprety_pictures_id, proprety_pictures_name FROM proprety_pictures");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed_count = 0;
    foreach ($images as $image) {
        $id = $image['proprety_pictures_id'];
        $path = $image['proprety_pictures_name'];
        
        // Skip external URLs
        if (strpos($path, 'http') === 0) {
            continue;
        }
        
        // Check if the path has the correct prefix
        $has_prefix = strpos($path, 'uploads/properties/') === 0;
        
        // If it doesn't have the prefix and the file exists with the prefix, update it
        if (!$has_prefix && file_exists('uploads/properties/' . basename($path))) {
            $new_path = 'uploads/properties/' . basename($path);
            $update = $conn->prepare("UPDATE proprety_pictures SET proprety_pictures_name = ? WHERE proprety_pictures_id = ?");
            $update->execute([$new_path, $id]);
            $fixed_count++;
        }
        // If it has a double prefix, fix it
        else if (strpos($path, 'uploads/properties/uploads/properties/') === 0) {
            $new_path = str_replace('uploads/properties/uploads/properties/', 'uploads/properties/', $path);
            $update = $conn->prepare("UPDATE proprety_pictures SET proprety_pictures_name = ? WHERE proprety_pictures_id = ?");
            $update->execute([$new_path, $id]);
            $fixed_count++;
        }
    }
    
    $db_result = "Fixed $fixed_count image paths in the database";
} catch (PDOException $e) {
    $db_result = "Error: " . $e->getMessage();
}

// Redirect to Profile.php
header("Location: Profile.php");
exit();
?>
