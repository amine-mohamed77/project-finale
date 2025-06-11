<?php
// Image path debugging tool
session_start();
require_once './db.php';

echo "<h1>Image Path Debugging</h1>";

// Get all properties with their images
$stmt = $conn->query("
    SELECT h.house_id, h.house_title, 
           (SELECT hpp.proprety_pictures_id FROM house_property_pictures hpp 
            WHERE hpp.house_id = h.house_id LIMIT 1) as picture_id
    FROM house h
    ORDER BY h.house_id DESC
");
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Properties and Their Images</h2>";
echo "<table border='1' style='width:100%'>";
echo "<tr>
        <th>House ID</th>
        <th>Title</th>
        <th>Picture ID</th>
        <th>Picture Path in DB</th>
        <th>Image Test</th>
        <th>File Exists?</th>
      </tr>";

foreach ($properties as $property) {
    echo "<tr>";
    echo "<td>" . $property['house_id'] . "</td>";
    echo "<td>" . htmlspecialchars($property['house_title']) . "</td>";
    
    // Get picture details if exists
    if (!empty($property['picture_id'])) {
        $stmt = $conn->prepare("SELECT proprety_pictures_name FROM proprety_pictures WHERE proprety_pictures_id = ?");
        $stmt->execute([$property['picture_id']]);
        $picture = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($picture) {
            $path = $picture['proprety_pictures_name'];
            echo "<td>" . $property['picture_id'] . "</td>";
            echo "<td>" . htmlspecialchars($path) . "</td>";
            
            // Test image display
            echo "<td><img src='" . htmlspecialchars($path) . "' style='max-width:100px; max-height:100px;'></td>";
            
            // Check if file exists on server
            $file_path = __DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $file_exists = file_exists($file_path) ? "Yes" : "No";
            echo "<td>" . $file_exists . " (Path: " . htmlspecialchars($file_path) . ")</td>";
        } else {
            echo "<td colspan='3'>Picture ID exists but no record found</td>";
        }
    } else {
        echo "<td colspan='3'>No picture associated</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

// Check if uploads directory exists and is writable
echo "<h2>Directory Status</h2>";
$upload_dir = __DIR__ . '/uploads/properties';
echo "<p>Upload directory: " . $upload_dir . "</p>";
echo "<p>Directory exists: " . (is_dir($upload_dir) ? "Yes" : "No") . "</p>";
echo "<p>Directory writable: " . (is_writable($upload_dir) ? "Yes" : "No") . "</p>";

// List files in the directory
if (is_dir($upload_dir)) {
    echo "<h2>Files in Upload Directory</h2>";
    echo "<ul>";
    $files = scandir($upload_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>" . htmlspecialchars($file) . " (" . filesize($upload_dir . '/' . $file) . " bytes)</li>";
        }
    }
    echo "</ul>";
}
?>
