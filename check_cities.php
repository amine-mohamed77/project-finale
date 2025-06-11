<?php
require_once './db.php';

try {
    // Check cities in the database
    $stmt = $conn->prepare("SELECT city_id, city_name FROM city");
    $stmt->execute();
    $db_cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get cities from JSON file
    $cities_json = file_get_contents('cities.json');
    $json_cities = json_decode($cities_json, true);
    
    if ($json_cities === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error parsing cities.json: " . json_last_error_msg());
    }
    
    echo "<!DOCTYPE html>\n<html>\n<head>\n<title>City ID Comparison</title>\n<style>\nbody { font-family: Arial, sans-serif; margin: 20px; }\nh1, h2 { color: #2563eb; }\ntable { border-collapse: collapse; width: 100%; margin-top: 20px; }\nth, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\nth { background-color: #f2f2f2; }\ntr:nth-child(even) { background-color: #f9f9f9; }\n.warning { color: red; }\n.success { color: green; }\n.action-btn { display: inline-block; margin-top: 20px; padding: 10px 15px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; }\n</style>\n</head>\n<body>\n";
    
    echo "<h1>City ID Comparison</h1>";
    
    // Display database cities
    echo "<h2>Cities in Database (" . count($db_cities) . "):</h2>";
    if (empty($db_cities)) {
        echo "<p class='warning'>No cities found in the database!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th></tr>";
        foreach ($db_cities as $city) {
            echo "<tr><td>{$city['city_id']}</td><td>{$city['city_name']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Display JSON cities
    echo "<h2>Cities in JSON File (" . count($json_cities) . "):</h2>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th></tr>";
    
    // Create lookup array for database cities
    $db_city_ids = array_column($db_cities, 'city_id');
    
    foreach ($json_cities as $city) {
        $exists = in_array($city['id'], $db_city_ids);
        $status_class = $exists ? 'success' : 'warning';
        $status_text = $exists ? 'Exists in DB' : 'Missing in DB';
        
        echo "<tr>";
        echo "<td>{$city['id']}</td>";
        echo "<td>{$city['name']}</td>";
        echo "<td class='$status_class'>$status_text</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Provide solution options
    echo "<h2>Solution Options:</h2>";
    echo "<p>To fix the foreign key constraint error, you need to make sure the city IDs in your form match the city IDs in your database.</p>";
    
    echo "<p><strong>Option 1:</strong> Import cities from JSON to database (recommended if database is empty or has few cities)</p>";
    echo "<a href='import_cities.php' class='action-btn'>Import Cities to Database</a>";
    
    echo "<p><strong>Option 2:</strong> Update JSON file to match database (recommended if database already has many cities)</p>";
    echo "<a href='update_cities_json.php' class='action-btn'>Update Cities JSON</a>";
    
    echo "</body>\n</html>";
    
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
