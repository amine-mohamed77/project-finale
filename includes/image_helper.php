<?php
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
    
    // If it's already a URL, return as is
    if (strpos($image_path, 'http') === 0) {
        return $image_path;
    }
    
    // Check if the path already includes uploads/properties/
    $has_prefix = strpos($image_path, 'uploads/properties/') === 0;
    
    // Try the path as is
    if (file_exists($image_path)) {
        return $image_path;
    }
    
    // Try with prefix if it doesn't have one
    if (!$has_prefix) {
        $with_prefix = 'uploads/properties/' . $image_path;
        if (file_exists($with_prefix)) {
            return $with_prefix;
        }
    }
    
    // Try without prefix if it has one
    if ($has_prefix) {
        $filename = str_replace('uploads/properties/', '', $image_path);
        if (file_exists('uploads/properties/' . $filename)) {
            return 'uploads/properties/' . $filename;
        }
    }
    
    // If we get here, no valid image was found
    return $default_image;
}
?>
