<?php
require_once './db.php';

// Property types to add from the image
$property_types = [
    'Apartment',
    'House',
    'Room',
    'Dormitory'
];

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Check existing property types
    $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type");
    $stmt->execute();
    $existing_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a lookup array of existing type names
    $existing_type_names = [];
    foreach ($existing_types as $type) {
        $existing_type_names[strtolower($type['proprety_type_name'])] = $type['proprety_type_id'];
    }
    
    // Get the next ID
    $stmt = $conn->prepare("SELECT MAX(proprety_type_id) as max_id FROM proprety_type");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_id = ($result['max_id'] ?? 0) + 1;
    
    // Add new property types
    $added_types = [];
    foreach ($property_types as $type_name) {
        // Skip if already exists
        if (isset($existing_type_names[strtolower($type_name)])) {
            echo "<p>Property type '{$type_name}' already exists with ID: {$existing_type_names[strtolower($type_name)]}</p>";
            continue;
        }
        
        $stmt = $conn->prepare("INSERT INTO proprety_type (proprety_type_id, proprety_type_name) VALUES (?, ?)");
        $stmt->execute([$next_id, $type_name]);
        
        $added_types[] = ["id" => $next_id, "name" => $type_name];
        $next_id++;
    }
    
    // Commit transaction
    $conn->commit();
    
    // Display results
    echo "<h2>Added Property Types:</h2>";
    if (empty($added_types)) {
        echo "<p>No new property types were added. All types already exist in the database.</p>";
    } else {
        echo "<ul>";
        foreach ($added_types as $type) {
            echo "<li>ID: {$type['id']} - Name: {$type['name']}</li>";
        }
        echo "</ul>";
    }
    
    echo "<h2>All Property Types:</h2>";
    $stmt = $conn->prepare("SELECT * FROM proprety_type ORDER BY proprety_type_id");
    $stmt->execute();
    $all_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th></tr>";
    foreach ($all_types as $type) {
        echo "<tr>";
        echo "<td>{$type['proprety_type_id']}</td>";
        echo "<td>{$type['proprety_type_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><a href='create_offer.php'>Go back to Create Offer page</a></p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    echo "<h2>Error:</h2>";
    echo "<p>{$e->getMessage()}</p>";
}
?>
