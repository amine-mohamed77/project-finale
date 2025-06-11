<?php
// Script to update Profile.php to use the image helper function

// Make sure the image helper file exists
$helper_dir = __DIR__ . '/includes';
if (!is_dir($helper_dir)) {
    mkdir($helper_dir, 0777, true);
}

$helper_file = $helper_dir . '/image_helper.php';
if (!file_exists($helper_file)) {
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
    echo "<p>Created image helper file at: $helper_file</p>";
}

// Update Profile.php
$profile_file = __DIR__ . '/Profile.php';
if (file_exists($profile_file)) {
    $content = file_get_contents($profile_file);
    
    // Add the include if it's not already there
    if (strpos($content, "require_once './includes/image_helper.php';") === false) {
        $content = str_replace(
            "require_once './db.php';", 
            "require_once './db.php';\nrequire_once './includes/image_helper.php';", 
            $content
        );
    }
    
    // Update the image display code in the favorites section
    $pattern = '/<\?php if \(!empty\(\$property\[\'image_url\'\]\)\): \?>\s*<img src="\<\?php echo htmlspecialchars\(\$property\[\'image_url\'\]\); \?>" alt="\<\?php echo htmlspecialchars\(\$property\[\'house_title\'\]\); \?>">\s*<\?php else: \?>\s*<img src="https:\/\/images\.unsplash\.com\/.*?" alt="\<\?php echo htmlspecialchars\(\$property\[\'house_title\'\]\); \?>">\s*<\?php endif; \?>/s';
    
    $replacement = '<?php 
        // Use the helper function to get a valid image path
        $image_src = get_valid_image_path($property[\'image_url\']);
        ?>
        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">';
    
    $new_content = preg_replace($pattern, $replacement, $content);
    
    // If preg_replace didn't work (pattern not found), try a different approach
    if ($new_content === $content) {
        // Look for the property image section and replace it
        $start_marker = '<div class="property-image">';
        $end_marker = '</div>';
        
        $pos_start = strpos($content, $start_marker);
        if ($pos_start !== false) {
            $pos_end = strpos($content, $end_marker, $pos_start + strlen($start_marker));
            if ($pos_end !== false) {
                $image_section = substr($content, $pos_start, $pos_end - $pos_start + strlen($end_marker));
                
                // Create new image section
                $new_image_section = '<div class="property-image">
        <?php 
        // Use the helper function to get a valid image path
        $image_src = get_valid_image_path($property[\'image_url\']);
        ?>
        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">
        <div class="property-badge">
            <span><i class="fas fa-heart"></i> Favorite</span>
        </div>
    </div>';
                
                // Replace the old section with the new one
                $new_content = str_replace($image_section, $new_image_section, $content);
            }
        }
    }
    
    // Save the updated file
    if ($new_content !== $content) {
        file_put_contents($profile_file, $new_content);
        echo "<p>Successfully updated Profile.php to use the image helper function!</p>";
    } else {
        echo "<p>Could not update Profile.php automatically. Please make the changes manually.</p>";
        
        // Provide instructions for manual update
        echo "<h2>Manual Update Instructions:</h2>";
        echo "<p>1. Make sure this line is at the top of your Profile.php file (after require_once './db.php'):</p>";
        echo "<pre>require_once './includes/image_helper.php';</pre>";
        
        echo "<p>2. Find the property card image section in Profile.php and replace:</p>";
        echo "<pre>
&lt;?php if (!empty(\$property['image_url'])): ?&gt;
&lt;img src=\"&lt;?php echo htmlspecialchars(\$property['image_url']); ?&gt;\" alt=\"&lt;?php echo htmlspecialchars(\$property['house_title']); ?&gt;\"&gt;
&lt;?php else: ?&gt;
&lt;img src=\"https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80\" alt=\"&lt;?php echo htmlspecialchars(\$property['house_title']); ?&gt;\"&gt;
&lt;?php endif; ?&gt;
</pre>";
        
        echo "<p>With:</p>";
        echo "<pre>
&lt;?php 
// Use the helper function to get a valid image path
\$image_src = get_valid_image_path(\$property['image_url']);
?&gt;
&lt;img src=\"&lt;?php echo htmlspecialchars(\$image_src); ?&gt;\" alt=\"&lt;?php echo htmlspecialchars(\$property['house_title']); ?&gt;\"&gt;
</pre>";
    }
} else {
    echo "<p>Error: Profile.php not found at $profile_file</p>";
}

echo "<p><a href='Profile.php'>Go to Profile Page</a> to see if the images are now displaying correctly.</p>";
?>
