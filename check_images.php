<?php
// Check image paths in database
session_start();
require_once './db.php';

echo "<h1>Image Path Check</h1>";

// Check property_pictures table
$stmt = $conn->query("SELECT proprety_pictures_id, proprety_pictures_name FROM proprety_pictures ORDER BY proprety_pictures_id DESC LIMIT 20");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Property Pictures Table</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Image Path</th><th>Preview</th></tr>";

foreach ($images as $image) {
    echo "<tr>";
    echo "<td>" . $image['proprety_pictures_id'] . "</td>";
    echo "<td>" . $image['proprety_pictures_name'] . "</td>";
    echo "<td><img src='" . $image['proprety_pictures_name'] . "' style='max-width: 100px;'></td>";
    echo "</tr>";
}

echo "</table>";

// Check property cards image query
echo "<h2>Property Cards Image Query</h2>";

$stmt = $conn->query("
    SELECT h.house_id, h.house_title, 
           (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
            JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
            WHERE hpp.house_id = h.house_id LIMIT 1) as image_url
    FROM house h
    ORDER BY h.house_id DESC
    LIMIT 10
");
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>House ID</th><th>Title</th><th>Image Path</th><th>Preview</th></tr>";

foreach ($properties as $property) {
    echo "<tr>";
    echo "<td>" . $property['house_id'] . "</td>";
    echo "<td>" . $property['house_title'] . "</td>";
    echo "<td>" . ($property['image_url'] ?? 'No image') . "</td>";
    echo "<td>";
    if (!empty($property['image_url'])) {
        echo "<img src='" . $property['image_url'] . "' style='max-width: 100px;'>";
    } else {
        echo "No image";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";
?>
