<?php
// Fix image paths in database
session_start();
require_once './db.php';

// Check if user is logged in as admin or owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    echo "You must be logged in as an owner to run this script.";
    exit();
}

echo "<h1>Image Path Fixer</h1>";

// Get all image records
$stmt = $conn->query("SELECT proprety_pictures_id, proprety_pictures_name FROM proprety_pictures");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Processing " . count($images) . " images</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Original Path</th><th>Fixed Path</th><th>Status</th></tr>";

$fixed_count = 0;
$already_correct = 0;
$error_count = 0;

foreach ($images as $image) {
    $id = $image['proprety_pictures_id'];
    $original_path = $image['proprety_pictures_name'];
    $fixed_path = $original_path;
    $status = "";
    
    // Case 1: Path contains uploads/properties/uploads/properties/ (doubled path)
    if (strpos($original_path, 'uploads/properties/uploads/properties/') !== false) {
        $fixed_path = str_replace('uploads/properties/uploads/properties/', 'uploads/properties/', $original_path);
        $status = "Fixed doubled path";
    } 
    // Case 2: Path is just a filename without uploads/properties/
    else if (strpos($original_path, 'uploads/properties/') === false && strpos($original_path, 'http') === false) {
        $fixed_path = 'uploads/properties/' . $original_path;
        $status = "Added path prefix";
    }
    // Case 3: Path already has uploads/properties/ once
    else if (strpos($original_path, 'uploads/properties/') === 0) {
        $fixed_path = $original_path;
        $status = "Already correct";
        $already_correct++;
        continue; // Skip updating
    }
    // Case 4: Path is a full URL (like https://...)
    else if (strpos($original_path, 'http') === 0) {
        $fixed_path = $original_path;
        $status = "External URL - not modified";
        $already_correct++;
        continue; // Skip updating
    }
    
    // Update the database if the path was changed
    if ($fixed_path !== $original_path) {
        try {
            $update = $conn->prepare("UPDATE proprety_pictures SET proprety_pictures_name = ? WHERE proprety_pictures_id = ?");
            $update->execute([$fixed_path, $id]);
            $fixed_count++;
        } catch (Exception $e) {
            $status .= " - ERROR: " . $e->getMessage();
            $error_count++;
        }
    }
    
    echo "<tr>";
    echo "<td>" . $id . "</td>";
    echo "<td>" . htmlspecialchars($original_path) . "</td>";
    echo "<td>" . htmlspecialchars($fixed_path) . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "<h3>Summary</h3>";
echo "<p>Total images: " . count($images) . "</p>";
echo "<p>Fixed: " . $fixed_count . "</p>";
echo "<p>Already correct: " . $already_correct . "</p>";
echo "<p>Errors: " . $error_count . "</p>";
echo "<p><a href='offers.php'>Return to Offers</a></p>";
?>
