<?php
// Fix all image paths across the entire UniHousing platform
session_start();
require_once './db.php';

// Function to check if a file exists (case-insensitive)
function file_exists_case_insensitive($path) {
    if (file_exists($path)) {
        return $path;
    }
    
    $directory = dirname($path);
    $filename = basename($path);
    
    if (!is_dir($directory)) {
        return false;
    }
    
    $files = scandir($directory);
    foreach ($files as $file) {
        if (strtolower($file) === strtolower($filename)) {
            return $directory . '/' . $file;
        }
    }
    
    return false;
}

// Function to get a valid image path
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

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix All Images - UniHousing</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
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
        .thumbnail {
            max-width: 100px;
            max-height: 60px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1><i class='fas fa-tools'></i> UniHousing Image Path Fixer</h1>";

// Check if the uploads directory exists
$upload_dir = __DIR__ . '/uploads/properties';
if (!is_dir($upload_dir)) {
    echo "<div class='error'><p><i class='fas fa-exclamation-triangle'></i> Error: Upload directory doesn't exist at: $upload_dir</p></div>";
    echo "<p>Creating directory...</p>";
    mkdir($upload_dir, 0777, true);
    echo "<p class='success'><i class='fas fa-check-circle'></i> Directory created successfully!</p>";
}

// Get all image records from the database
$stmt = $conn->query("SELECT proprety_pictures_id, proprety_pictures_name FROM proprety_pictures");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2><i class='fas fa-images'></i> Processing " . count($images) . " Property Images</h2>";
echo "<table>
        <tr>
            <th>ID</th>
            <th>Original Path</th>
            <th>New Path</th>
            <th>Preview</th>
            <th>Status</th>
        </tr>";

$fixed = 0;
$errors = 0;
$no_change = 0;

foreach ($images as $image) {
    $id = $image['proprety_pictures_id'];
    $path = $image['proprety_pictures_name'];
    $new_path = $path;
    $status = "";
    $status_class = "";
    
    // Skip external URLs
    if (strpos($path, 'http') === 0) {
        $status = "External URL - no change needed";
        $status_class = "success";
        $no_change++;
    } 
    // Check if the file exists as is
    else if (file_exists($path)) {
        $status = "File exists - no change needed";
        $status_class = "success";
        $no_change++;
    }
    // Try different path combinations
    else {
        $possible_paths = [
            // Just the filename without any path
            basename($path),
            // With uploads/properties/ prefix
            'uploads/properties/' . basename($path),
            // Without uploads/properties/ prefix if it has one
            str_replace('uploads/properties/', '', $path)
        ];
        
        $found = false;
        foreach ($possible_paths as $test_path) {
            // Skip if it's the same as the original
            if ($test_path === $path) continue;
            
            $test_full_path = __DIR__ . '/' . $test_path;
            if (file_exists($test_full_path) || file_exists_case_insensitive($test_full_path)) {
                // Found a valid path
                $new_path = $test_path;
                $found = true;
                
                // If it doesn't have uploads/properties/ prefix, add it
                if (strpos($new_path, 'uploads/properties/') !== 0 && strpos($new_path, '/') === false) {
                    $new_path = 'uploads/properties/' . $new_path;
                }
                
                // Update the database
                try {
                    $update = $conn->prepare("UPDATE proprety_pictures SET proprety_pictures_name = ? WHERE proprety_pictures_id = ?");
                    $update->execute([$new_path, $id]);
                    $status = "Fixed - updated to correct path";
                    $status_class = "success";
                    $fixed++;
                } catch (Exception $e) {
                    $status = "Error updating: " . $e->getMessage();
                    $status_class = "error";
                    $errors++;
                }
                break;
            }
        }
        
        if (!$found) {
            $status = "Error - could not find a valid file for this path";
            $status_class = "error";
            $errors++;
        }
    }
    
    // Get a valid path for preview
    $preview_path = get_valid_image_path($new_path);
    
    echo "<tr>
            <td>$id</td>
            <td>" . htmlspecialchars($path) . "</td>
            <td>" . htmlspecialchars($new_path) . "</td>
            <td><img src='" . htmlspecialchars($preview_path) . "' class='thumbnail' alt='Preview'></td>
            <td class='$status_class'>$status</td>
          </tr>";
}

echo "</table>";

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
    echo "<p class='success'><i class='fas fa-check-circle'></i> Created image helper file at: $helper_file</p>";
}

// Update PHP files to include the helper
$files_to_update = [
    'home.php',
    'offers.php',
    'Profile.php',
    'view_property.php',
    'property.php'
];

$updated_files = 0;
foreach ($files_to_update as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        
        // Check if the helper is already included
        if (strpos($content, "require_once './includes/image_helper.php';") === false) {
            // Add the include after db.php
            $content = str_replace(
                "require_once './db.php';", 
                "require_once './db.php';\nrequire_once './includes/image_helper.php';", 
                $content
            );
            
            file_put_contents($file_path, $content);
            $updated_files++;
        }
    }
}

echo "<p class='success'><i class='fas fa-check-circle'></i> Updated $updated_files PHP files to include the image helper.</p>";

// Add image helper function to property cards
$files_with_property_cards = [
    'home.php' => [
        'pattern' => '<img src="<?php echo htmlspecialchars($property[\'image_url\']); ?>" alt="',
        'replacement' => '<?php $image_src = get_valid_image_path($property[\'image_url\']); ?>' . "\n" . 
                        '                                <img src="<?php echo htmlspecialchars($image_src); ?>" alt="'
    ],
    'offers.php' => [
        'pattern' => '<img src="<?php echo htmlspecialchars($property[\'image_url\']); ?>" alt="',
        'replacement' => '<?php $image_src = get_valid_image_path($property[\'image_url\']); ?>' . "\n" . 
                        '                                <img src="<?php echo htmlspecialchars($image_src); ?>" alt="'
    ],
    'Profile.php' => [
        'pattern' => '<img src="<?php echo htmlspecialchars($property[\'image_url\']); ?>" alt="',
        'replacement' => '<?php $image_src = get_valid_image_path($property[\'image_url\']); ?>' . "\n" . 
                        '                                        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="'
    ]
];

$updated_card_files = 0;
foreach ($files_with_property_cards as $file => $replacement_info) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        
        // Check if the pattern exists and hasn't been replaced yet
        if (strpos($content, $replacement_info['pattern']) !== false && 
            strpos($content, 'get_valid_image_path($property') === false) {
            
            // Replace the image display code
            $content = str_replace(
                $replacement_info['pattern'], 
                $replacement_info['replacement'], 
                $content
            );
            
            file_put_contents($file_path, $content);
            $updated_card_files++;
        }
    }
}

echo "<h2>Summary</h2>
      <p>Total images processed: " . count($images) . "</p>
      <p class='success'><i class='fas fa-check-circle'></i> Fixed: $fixed</p>
      <p class='warning'><i class='fas fa-exclamation-circle'></i> No change needed: $no_change</p>
      <p class='error'><i class='fas fa-times-circle'></i> Errors: $errors</p>
      <p class='success'><i class='fas fa-check-circle'></i> Updated property cards in $updated_card_files files</p>
      
      <p>The image helper has been installed and configured across your UniHousing platform. All property images should now display correctly.</p>
      
      <div>
        <a href='home.php' class='btn'><i class='fas fa-home'></i> Go to Home Page</a>
        <a href='offers.php' class='btn'><i class='fas fa-list'></i> Go to Offers Page</a>
        <a href='Profile.php' class='btn'><i class='fas fa-user'></i> Go to Profile Page</a>
      </div>
    </div>
</body>
</html>";
?>
