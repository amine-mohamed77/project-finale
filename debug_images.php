<?php
// Debug image paths
session_start();
require_once './db.php';

echo "<h1>Image Path Debug</h1>";

// Get all properties with their images
$stmt = $conn->query("
    SELECT h.house_id, h.house_title, 
           (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
            JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
            WHERE hpp.house_id = h.house_id LIMIT 1) as image_url
    FROM house h
    ORDER BY h.house_id DESC
");
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Property Images</h2>";
echo "<table border='1' style='width:100%'>";
echo "<tr>
        <th>House ID</th>
        <th>Title</th>
        <th>Image Path in DB</th>
        <th>Image Display Test</th>
      </tr>";

foreach ($properties as $property) {
    echo "<tr>";
    echo "<td>" . $property['house_id'] . "</td>";
    echo "<td>" . htmlspecialchars($property['house_title']) . "</td>";
    echo "<td>" . (isset($property['image_url']) ? htmlspecialchars($property['image_url']) : 'No image') . "</td>";
    
    // Test image display with various path combinations
    echo "<td>";
    if (!empty($property['image_url'])) {
        // 1. Try direct path as stored in DB
        echo "<div style='margin-bottom:10px'>";
        echo "<strong>1. Direct path:</strong><br>";
        echo "<img src='" . htmlspecialchars($property['image_url']) . "' style='max-height:50px; border:1px solid blue;'>";
        echo "</div>";
        
        // 2. Try with uploads/properties/ prefix
        if (strpos($property['image_url'], 'uploads/properties/') !== 0 && strpos($property['image_url'], 'http') !== 0) {
            echo "<div style='margin-bottom:10px'>";
            echo "<strong>2. With prefix:</strong><br>";
            echo "<img src='uploads/properties/" . htmlspecialchars($property['image_url']) . "' style='max-height:50px; border:1px solid green;'>";
            echo "</div>";
        }
        
        // 3. Try without uploads/properties/ prefix if it exists
        if (strpos($property['image_url'], 'uploads/properties/') === 0) {
            $filename = str_replace('uploads/properties/', '', $property['image_url']);
            echo "<div style='margin-bottom:10px'>";
            echo "<strong>3. Without prefix:</strong><br>";
            echo "<img src='" . htmlspecialchars($filename) . "' style='max-height:50px; border:1px solid red;'>";
            echo "</div>";
        }
    } else {
        echo "No image to test";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// List all image records in the database
$stmt = $conn->query("SELECT proprety_pictures_id, proprety_pictures_name FROM proprety_pictures ORDER BY proprety_pictures_id DESC LIMIT 20");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Raw Image Records</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Path in DB</th></tr>";

foreach ($images as $image) {
    echo "<tr>";
    echo "<td>" . $image['proprety_pictures_id'] . "</td>";
    echo "<td>" . htmlspecialchars($image['proprety_pictures_name']) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check uploads directory
$upload_dir = __DIR__ . '/uploads/properties';
echo "<h2>Upload Directory</h2>";
echo "<p>Path: " . $upload_dir . "</p>";
echo "<p>Exists: " . (is_dir($upload_dir) ? "Yes" : "No") . "</p>";

if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    echo "<p>File count: " . (count($files) - 2) . "</p>"; // Subtract . and ..
    
    echo "<h3>Files in directory:</h3>";
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != "." && $file != "..") {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
}
?>
