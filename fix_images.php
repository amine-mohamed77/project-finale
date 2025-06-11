<?php
// Fix all image paths in the database
session_start();
require_once './db.php';

// Function to check if a file exists
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

echo "<h1>Image Path Fixer</h1>";

// 1. First, check if the uploads/properties directory exists
$upload_dir = __DIR__ . '/uploads/properties';
if (!is_dir($upload_dir)) {
    echo "<p>Error: Upload directory doesn't exist at: $upload_dir</p>";
    exit;
}

// 2. Get all image records from the database
$stmt = $conn->query("SELECT proprety_pictures_id, proprety_pictures_name FROM proprety_pictures");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Processing " . count($images) . " images</h2>";
echo "<table border='1' style='width:100%'>";
echo "<tr><th>ID</th><th>Original Path</th><th>New Path</th><th>Status</th></tr>";

$fixed = 0;
$errors = 0;

foreach ($images as $image) {
    $id = $image['proprety_pictures_id'];
    $path = $image['proprety_pictures_name'];
    $new_path = $path;
    $status = "";
    
    // Skip external URLs
    if (strpos($path, 'http') === 0) {
        echo "<tr><td>$id</td><td>$path</td><td>$path</td><td>External URL - skipped</td></tr>";
        continue;
    }
    
    // Check if the file exists as is
    $full_path = __DIR__ . '/' . $path;
    if (file_exists($full_path)) {
        echo "<tr><td>$id</td><td>$path</td><td>$path</td><td>File exists - no change needed</td></tr>";
        continue;
    }
    
    // Try different path combinations
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
                $fixed++;
            } catch (Exception $e) {
                $status = "Error updating: " . $e->getMessage();
                $errors++;
            }
            break;
        }
    }
    
    if (!$found) {
        $status = "Error - could not find a valid file for this path";
        $errors++;
    }
    
    echo "<tr><td>$id</td><td>" . htmlspecialchars($path) . "</td><td>" . htmlspecialchars($new_path) . "</td><td>$status</td></tr>";
}

echo "</table>";
echo "<h2>Summary</h2>";
echo "<p>Total images processed: " . count($images) . "</p>";
echo "<p>Fixed: $fixed</p>";
echo "<p>Errors: $errors</p>";
echo "<p><a href='offers.php'>Go to Offers Page</a></p>";
?>
