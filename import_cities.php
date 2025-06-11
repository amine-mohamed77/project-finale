<?php
require_once './db.php';

try {
    // Get cities from JSON file
    $cities_json = file_get_contents('cities.json');
    $json_cities = json_decode($cities_json, true);
    
    if ($json_cities === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error parsing cities.json: " . json_last_error_msg());
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Check existing cities
    $stmt = $conn->prepare("SELECT city_id, city_name FROM city");
    $stmt->execute();
    $db_cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create lookup arrays
    $db_city_ids = array_column($db_cities, 'city_id');
    $db_city_names = array_column($db_cities, 'city_name');
    
    $inserted_count = 0;
    $updated_count = 0;
    $skipped_count = 0;
    
    foreach ($json_cities as $city) {
        $city_id = $city['id'];
        $city_name = $city['name'];
        
        // Check if city ID exists
        if (in_array($city_id, $db_city_ids)) {
            // City ID exists, check if name matches
            $db_index = array_search($city_id, $db_city_ids);
            if ($db_cities[$db_index]['city_name'] !== $city_name) {
                // Update city name
                $stmt = $conn->prepare("UPDATE city SET city_name = ? WHERE city_id = ?");
                $stmt->execute([$city_name, $city_id]);
                $updated_count++;
            } else {
                // City already exists with same name
                $skipped_count++;
            }
        } else {
            // Insert new city
            $stmt = $conn->prepare("INSERT INTO city (city_id, city_name) VALUES (?, ?)");
            $stmt->execute([$city_id, $city_name]);
            $inserted_count++;
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Display results
    echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Import Cities</title>\n<style>\nbody { font-family: Arial, sans-serif; margin: 20px; }\nh1, h2 { color: #2563eb; }\n.success { color: green; }\n.info { color: blue; }\n.back-link { margin-top: 20px; display: block; }\n.action-btn { display: inline-block; margin-top: 20px; padding: 10px 15px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; }\n</style>\n</head>\n<body>\n";
    
    echo "<h1>Import Cities</h1>";
    
    echo "<p><span class='success'>$inserted_count cities inserted successfully.</span>";
    if ($updated_count > 0) {
        echo " <span class='info'>$updated_count cities updated.</span>";
    }
    if ($skipped_count > 0) {
        echo " <span class='info'>$skipped_count cities skipped (already exist).</span>";
    }
    echo "</p>";
    
    echo "<p>The cities from your JSON file have been imported to the database. You can now create property listings using these city IDs.</p>";
    
    echo "<div>";
    echo "<a href='check_cities.php' class='action-btn'>Back to City Comparison</a> ";
    echo "<a href='create_offer.php' class='action-btn'>Create Property Listing</a>";
    echo "</div>";
    
    echo "</body>\n</html>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<h1>Error</h1>";
    echo "<p>Error importing cities: " . $e->getMessage() . "</p>";
    echo "<a href='check_cities.php'>Back to City Comparison</a>";
}
?>
