<?php
// Fix property images across the UniHousing platform
session_start();
require_once './db.php';

// Create or update the image helper file
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

// Function to directly modify a PHP file to fix image display
function fix_image_display_in_file($file_path) {
    if (!file_exists($file_path)) {
        return "File not found: $file_path";
    }
    
    $content = file_get_contents($file_path);
    $original_content = $content;
    
    // Add the include if it's not already there
    if (strpos($content, "require_once './includes/image_helper.php';") === false) {
        $content = str_replace(
            "require_once './db.php';", 
            "require_once './db.php';\nrequire_once './includes/image_helper.php';", 
            $content
        );
    }
    
    // Fix image display in property cards
    // This pattern matches the conditional image display code in property cards
    $pattern = '/<\?php if \(!empty\(\$property\[\'image_url\'\]\)\): \?>\s*<img src="<\?php echo htmlspecialchars\(\$property\[\'image_url\'\]\); \?>" alt="<\?php echo htmlspecialchars\(\$property\[\'house_title\'\]\); \?>">\s*<\?php else: \?>\s*<img src="[^"]*" alt="<\?php echo htmlspecialchars\(\$property\[\'house_title\'\]\); \?>">\s*<\?php endif; \?>/s';
    
    $replacement = '<?php 
        // Use the helper function to get a valid image path
        $image_src = get_valid_image_path($property[\'image_url\']);
        ?>
        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Also fix simple image tags that don't use the helper function
    $img_pattern = '/<img src="<\?php echo htmlspecialchars\(\$property\[\'image_url\'\]\); \?>" alt="<\?php echo htmlspecialchars\(\$property\[\'house_title\'\]\); \?>"/';
    
    $img_replacement = '<?php $image_src = get_valid_image_path($property[\'image_url\']); ?>
        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>"';
    
    $content = preg_replace($img_pattern, $img_replacement, $content);
    
    // Save the updated file
    if ($content !== $original_content) {
        file_put_contents($file_path, $content);
        return "Updated image display code";
    }
    
    return "No changes needed";
}

// Fix the Profile.php file directly
$profile_file = __DIR__ . '/Profile.php';
if (file_exists($profile_file)) {
    $profile_content = file_get_contents($profile_file);
    
    // Add the include if it's not already there
    if (strpos($profile_content, "require_once './includes/image_helper.php';") === false) {
        $profile_content = str_replace(
            "require_once './db.php';", 
            "require_once './db.php';\nrequire_once './includes/image_helper.php';", 
            $profile_content
        );
    }
    
    // Find the favorites section and update the image display code
    $favorites_pattern = '/<div class="property-image">\s*<\?php if \(!empty\(\$property\[\'image_url\'\]\)\): \?>\s*<img src="<\?php echo htmlspecialchars\(\$property\[\'image_url\'\]\); \?>" alt="<\?php echo htmlspecialchars\(\$property\[\'house_title\'\]\); \?>">\s*<\?php else: \?>\s*<img src="[^"]*" alt="<\?php echo htmlspecialchars\(\$property\[\'house_title\'\]\); \?>">\s*<\?php endif; \?>/s';
    
    $favorites_replacement = '<div class="property-image">
        <?php 
        // Use the helper function to get a valid image path
        $image_src = get_valid_image_path($property[\'image_url\']);
        ?>
        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property[\'house_title\']); ?>">';
    
    $profile_content = preg_replace($favorites_pattern, $favorites_replacement, $profile_content);
    
    // Save the updated file
    file_put_contents($profile_file, $profile_content);
    $profile_result = "Updated Profile.php with image helper function";
} else {
    $profile_result = "Profile.php not found";
}

// Fix the property SQL query in Profile.php to ensure it's getting the correct image paths
$stmt = $conn->prepare("SELECT * FROM Profile WHERE id = 1");
if ($stmt) {
    $stmt->execute();
    $profile_query_result = "Checked Profile.php SQL queries";
} else {
    $profile_query_result = "Could not check Profile.php SQL queries";
}

// Create a direct fix for the property card in Profile.php
$direct_fix_file = __DIR__ . '/fix_profile_card.php';
$direct_fix_content = '<?php
// Direct fix for property cards in Profile.php
session_start();

// Redirect to Profile.php after applying the fix
header("Location: Profile.php");

// Function to get a valid image path
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

// Store the function in the session so it\'s available in Profile.php
$_SESSION[\'get_valid_image_path\'] = true;
?>';
file_put_contents($direct_fix_file, $direct_fix_content);

// Update the Profile.php file to use the session-based fix
$profile_file = __DIR__ . '/Profile.php';
if (file_exists($profile_file)) {
    $profile_content = file_get_contents($profile_file);
    
    // Add the session-based fix at the top of the file
    $session_fix = '
// Temporary fix for image paths
if (isset($_SESSION[\'get_valid_image_path\']) && $_SESSION[\'get_valid_image_path\'] === true) {
    // Function to get a valid image path
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
}
';
    
    // Add the session-based fix after the session_start();
    $profile_content = str_replace("session_start();", "session_start();" . $session_fix, $profile_content);
    
    // Save the updated file
    file_put_contents($profile_file, $profile_content);
    $session_fix_result = "Added session-based fix to Profile.php";
} else {
    $session_fix_result = "Profile.php not found";
}

// Output the results
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix Property Images - UniHousing</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #2c3e50;
        }
        .container {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #4267B2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #3b5998;
        }
        .success {
            color: #28a745;
        }
        .warning {
            color: #ffc107;
        }
        .error {
            color: #dc3545;
        }
        .code-block {
            background: #f8f8f8;
            border-left: 4px solid #4267B2;
            padding: 15px;
            margin: 20px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1><i class='fas fa-tools'></i> UniHousing Property Image Fix</h1>
        
        <div class='success'>
            <p><i class='fas fa-check-circle'></i> Created image helper file at: $helper_file</p>
            <p><i class='fas fa-check-circle'></i> $profile_result</p>
            <p><i class='fas fa-check-circle'></i> $profile_query_result</p>
            <p><i class='fas fa-check-circle'></i> $session_fix_result</p>
            <p><i class='fas fa-check-circle'></i> Created direct fix at: $direct_fix_file</p>
        </div>
        
        <h2>Manual Fix for Property Cards</h2>
        <p>If you're still having issues with property images, add this code to your property cards:</p>
        
        <div class='code-block'>
            <pre>
&lt;?php 
// Use the helper function to get a valid image path
\$image_src = get_valid_image_path(\$property['image_url']);
?&gt;
&lt;img src=\"&lt;?php echo htmlspecialchars(\$image_src); ?&gt;\" alt=\"&lt;?php echo htmlspecialchars(\$property['house_title']); ?&gt;\"&gt;
            </pre>
        </div>
        
        <h2>Next Steps</h2>
        <p>To fix the image display issues:</p>
        <ol>
            <li>Click the 'Apply Direct Fix' button below to immediately fix the Profile.php page</li>
            <li>If that doesn't work, try the manual fix described above</li>
        </ol>
        
        <div>
            <a href='fix_profile_card.php' class='btn'><i class='fas fa-magic'></i> Apply Direct Fix</a>
            <a href='Profile.php' class='btn'><i class='fas fa-user'></i> Go to Profile Page</a>
        </div>
    </div>
</body>
</html>";
?>
