<?php
require_once './db.php';

try {
    // Get cities from database
    $stmt = $conn->prepare("SELECT city_id, city_name FROM city");
    $stmt->execute();
    $db_cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($db_cities)) {
        throw new Exception("No cities found in the database. Please add cities to the database first.");
    }
    
    // Convert database cities to JSON format
    $json_cities = [];
    foreach ($db_cities as $city) {
        $json_cities[] = [
            'id' => $city['city_id'],
            'name' => $city['city_name']
        ];
    }
    
    // Sort by name for better usability
    usort($json_cities, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    // Create backup of existing JSON file
    if (file_exists('cities.json')) {
        copy('cities.json', 'cities_backup_' . date('Y-m-d_H-i-s') . '.json');
    }
    
    // Write new JSON file
    $json_content = json_encode($json_cities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents('cities.json', $json_content);
    
    // Display results
    echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Update Cities JSON</title>\n<style>\nbody { font-family: Arial, sans-serif; margin: 20px; }\nh1, h2 { color: #2563eb; }\ntable { border-collapse: collapse; width: 100%; margin-top: 20px; }\nth, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\nth { background-color: #f2f2f2; }\ntr:nth-child(even) { background-color: #f9f9f9; }\n.success { color: green; }\n.back-link { margin-top: 20px; display: block; }\n.action-btn { display: inline-block; margin-top: 20px; padding: 10px 15px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; }\n</style>\n</head>\n<body>\n";
    
    echo "<h1>Update Cities JSON</h1>";
    
    echo "<p class='success'>The cities.json file has been updated with " . count($json_cities) . " cities from your database.</p>";
    
    echo "<p>A backup of your original cities.json file has been created.</p>";
    
    echo "<h2>Cities in Updated JSON File:</h2>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th></tr>";
    
    foreach ($json_cities as $city) {
        echo "<tr><td>{$city['id']}</td><td>{$city['name']}</td></tr>";
    }
    
    echo "</table>";
    
    echo "<div>";
    echo "<a href='check_cities.php' class='action-btn'>Back to City Comparison</a> ";
    echo "<a href='create_offer.php' class='action-btn'>Create Property Listing</a>";
    echo "</div>";
    
    echo "</body>\n</html>";
    
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<a href='check_cities.php'>Back to City Comparison</a>";
}
?>
