<?php
// Fix image display across the UniHousing platform
session_start();
require_once './db.php';

// Create the image helper file if it doesn't exist
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

// Function to update a PHP file to include the image helper
function update_php_file($file_path) {
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
    
    // Save the updated file
    if ($content !== $original_content) {
        file_put_contents($file_path, $content);
        return "Updated to include image helper";
    }
    
    return "Already includes image helper";
}

// Update all PHP files that might display property images
$files_to_update = [
    'home.php',
    'offers.php',
    'Profile.php',
    'view_property.php',
    'property.php'
];

$results = [];
foreach ($files_to_update as $file) {
    $file_path = __DIR__ . '/' . $file;
    $results[$file] = update_php_file($file_path);
}

// Now let's directly fix the Profile.php file
$profile_file = __DIR__ . '/Profile.php';
$profile_content = file_get_contents($profile_file);

// Add the following code to the top of the file, right after the opening PHP tag
$profile_helper_code = <<<'EOD'
// Function to get a valid image path (temporary inline version)
if (!function_exists('get_valid_image_path')) {
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
}

EOD;

// Add the helper function if it's not already there
if (strpos($profile_content, 'function get_valid_image_path') === false) {
    $profile_content = preg_replace('/^<\?php\s+/s', "<?php\n" . $profile_helper_code, $profile_content);
    file_put_contents($profile_file, $profile_content);
    $results['Profile.php'] .= " - Added inline helper function";
}

// Now let's run a database fix to ensure all image paths are correct
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
    
    $results['Database'] = "Fixed $fixed_count image paths in the database";
} catch (PDOException $e) {
    $results['Database'] = "Error: " . $e->getMessage();
}

// Output the results
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Image Display Fix - UniHousing</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4267B2;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
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
        pre {
            background: #f1f1f1;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .code-block {
            background: #f8f8f8;
            border-left: 4px solid #4267B2;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1><i class='fas fa-tools'></i> UniHousing Image Display Fix</h1>
        
        <h2>Results</h2>
        <table>
            <tr>
                <th>File</th>
                <th>Status</th>
            </tr>";

foreach ($results as $file => $status) {
    echo "<tr>
            <td>$file</td>
            <td>$status</td>
          </tr>";
}

echo "</table>

        <h2>Manual Fix for Profile.php</h2>
        <p>If you're still having issues with images in Profile.php, add this code to your property card:</p>
        
        <div class='code-block'>
            <pre>
&lt;div class=\"property-image\"&gt;
    &lt;?php 
    // Use the helper function to get a valid image path
    \$image_src = get_valid_image_path(\$property['image_url']);
    ?&gt;
    &lt;img src=\"&lt;?php echo htmlspecialchars(\$image_src); ?&gt;\" alt=\"&lt;?php echo htmlspecialchars(\$property['house_title']); ?&gt;\"&gt;
    &lt;div class=\"property-badge\"&gt;
        &lt;span&gt;&lt;i class=\"fas fa-heart\"&gt;&lt;/i&gt; Favorite&lt;/span&gt;
    &lt;/div&gt;
&lt;/div&gt;
            </pre>
        </div>
        
        <p>This will replace the conditional code that checks for empty image URLs and uses the helper function instead.</p>
        
        <div>
            <a href='home.php' class='btn'><i class='fas fa-home'></i> Go to Home Page</a>
            <a href='offers.php' class='btn'><i class='fas fa-list'></i> Go to Offers Page</a>
            <a href='Profile.php' class='btn'><i class='fas fa-user'></i> Go to Profile Page</a>
        </div>
    </div>
</body>
</html>";
?>
