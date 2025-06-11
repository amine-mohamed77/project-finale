<?php
require_once './db.php';

// Function to display messages in a styled format
function display_message($type, $message) {
    $color = ($type == 'error') ? 'red' : (($type == 'success') ? 'green' : 'blue');
    echo "<div style='color: {$color}; margin: 10px 0; padding: 10px; border: 1px solid {$color}; border-radius: 5px;'>{$message}</div>";
}

// Start HTML output
echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Database Repair Tool</title>\n<style>\nbody { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }\nh1, h2 { color: #2563eb; }\ntable { border-collapse: collapse; width: 100%; margin: 20px 0; }\nth, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\nth { background-color: #f2f2f2; }\ntr:nth-child(even) { background-color: #f9f9f9; }\n.btn { display: inline-block; padding: 10px 15px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }\n.btn-danger { background-color: #dc2626; }\n.btn-success { background-color: #10b981; }\n.code { font-family: monospace; background-color: #f1f1f1; padding: 2px 5px; border-radius: 3px; }\n</style>\n</head>\n<body>\n";

echo "<h1>Database Repair Tool</h1>";

// Check if action is specified
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        if ($action === 'reset_cities') {
            // Clear existing cities
            $stmt = $conn->prepare("DELETE FROM city");
            $stmt->execute();
            
            display_message('success', "Cleared all cities from the database.");
            
            // Import cities from JSON
            if (file_exists('cities.json')) {
                $cities_json = file_get_contents('cities.json');
                $cities = json_decode($cities_json, true);
                
                if ($cities === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Error parsing cities.json: " . json_last_error_msg());
                }
                
                $inserted = 0;
                foreach ($cities as $city) {
                    $stmt = $conn->prepare("INSERT INTO city (city_id, city_name) VALUES (?, ?)");
                    $stmt->execute([$city['id'], $city['name']]);
                    $inserted++;
                }
                
                display_message('success', "Imported {$inserted} cities from cities.json.");
            } else {
                display_message('error', "cities.json file not found.");
            }
        } elseif ($action === 'reset_property_types') {
            // Clear existing property types
            $stmt = $conn->prepare("DELETE FROM proprety_type");
            $stmt->execute();
            
            display_message('success', "Cleared all property types from the database.");
            
            // Import property types from JSON
            if (file_exists('property_types.json')) {
                $types_json = file_get_contents('property_types.json');
                $types = json_decode($types_json, true);
                
                if ($types === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Error parsing property_types.json: " . json_last_error_msg());
                }
                
                $inserted = 0;
                foreach ($types as $type) {
                    $stmt = $conn->prepare("INSERT INTO proprety_type (proprety_type_id, proprety_type_name) VALUES (?, ?)");
                    $stmt->execute([$type['id'], $type['name']]);
                    $inserted++;
                }
                
                display_message('success', "Imported {$inserted} property types from property_types.json.");
            } else {
                display_message('error', "property_types.json file not found.");
            }
        } elseif ($action === 'test_insert') {
            // Simulate form submission to debug
            display_message('info', "Simulating form submission with test data...");
            
            // Get a valid city ID from the database
            $stmt = $conn->prepare("SELECT city_id FROM city LIMIT 1");
            $stmt->execute();
            $city_id = $stmt->fetchColumn();
            
            if (!$city_id) {
                throw new Exception("No cities found in the database. Please reset cities first.");
            }
            
            // Get a valid property type ID from the database
            $stmt = $conn->prepare("SELECT proprety_type_id FROM proprety_type LIMIT 1");
            $stmt->execute();
            $property_type_id = $stmt->fetchColumn();
            
            if (!$property_type_id) {
                throw new Exception("No property types found in the database. Please reset property types first.");
            }
            
            // Get a valid owner ID
            $stmt = $conn->prepare("SELECT owner_id FROM owner LIMIT 1");
            $stmt->execute();
            $owner_id = $stmt->fetchColumn();
            
            if (!$owner_id) {
                throw new Exception("No owners found in the database. Please create an owner account first.");
            }
            
            // Get the next house_id
            $stmt = $conn->prepare("SELECT MAX(house_id) as max_id FROM house");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $house_id = ($result['max_id'] ?? 0) + 1;
            
            // Test data
            $test_data = [
                'house_id' => $house_id,
                'city_id' => $city_id,
                'proprety_type_id' => $property_type_id,
                'owner_id' => $owner_id,
                'house_title' => 'Test Property',
                'house_price' => 500,
                'house_location' => 'Test Location',
                'house_badroom' => 2,
                'house_bathroom' => 1,
                'house_description' => 'This is a test property description.'
            ];
            
            // Insert test data
            $stmt = $conn->prepare("
                INSERT INTO house (
                    house_id, city_id, proprety_type_id, owner_id, 
                    house_title, house_price, house_location, 
                    house_badroom, house_bathroom, house_description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $test_data['house_id'],
                $test_data['city_id'],
                $test_data['proprety_type_id'],
                $test_data['owner_id'],
                $test_data['house_title'],
                $test_data['house_price'],
                $test_data['house_location'],
                $test_data['house_badroom'],
                $test_data['house_bathroom'],
                $test_data['house_description']
            ]);
            
            display_message('success', "Test property successfully inserted with ID: {$house_id}");
            display_message('info', "Used city_id: {$city_id}, property_type_id: {$property_type_id}, owner_id: {$owner_id}");
        }
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        display_message('error', "Error: " . $e->getMessage());
    }
}

// Display database status
try {
    // Check cities
    $stmt = $conn->prepare("SELECT COUNT(*) FROM city");
    $stmt->execute();
    $city_count = $stmt->fetchColumn();
    
    // Check property types
    $stmt = $conn->prepare("SELECT COUNT(*) FROM proprety_type");
    $stmt->execute();
    $property_type_count = $stmt->fetchColumn();
    
    // Check owners
    $stmt = $conn->prepare("SELECT COUNT(*) FROM owner");
    $stmt->execute();
    $owner_count = $stmt->fetchColumn();
    
    // Check houses
    $stmt = $conn->prepare("SELECT COUNT(*) FROM house");
    $stmt->execute();
    $house_count = $stmt->fetchColumn();
    
    echo "<h2>Database Status</h2>";
    echo "<table>";
    echo "<tr><th>Table</th><th>Count</th><th>Status</th></tr>";
    
    $city_status = $city_count > 0 ? "<span style='color: green;'>OK</span>" : "<span style='color: red;'>Empty</span>";
    $property_type_status = $property_type_count > 0 ? "<span style='color: green;'>OK</span>" : "<span style='color: red;'>Empty</span>";
    $owner_status = $owner_count > 0 ? "<span style='color: green;'>OK</span>" : "<span style='color: red;'>Empty</span>";
    $house_status = $house_count > 0 ? "<span style='color: green;'>OK</span>" : "<span style='color: orange;'>Empty</span>";
    
    echo "<tr><td>Cities</td><td>{$city_count}</td><td>{$city_status}</td></tr>";
    echo "<tr><td>Property Types</td><td>{$property_type_count}</td><td>{$property_type_status}</td></tr>";
    echo "<tr><td>Owners</td><td>{$owner_count}</td><td>{$owner_status}</td></tr>";
    echo "<tr><td>Houses</td><td>{$house_count}</td><td>{$house_status}</td></tr>";
    echo "</table>";
    
    // Display sample data
    if ($city_count > 0) {
        $stmt = $conn->prepare("SELECT city_id, city_name FROM city ORDER BY city_id LIMIT 5");
        $stmt->execute();
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample Cities</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th></tr>";
        foreach ($cities as $city) {
            echo "<tr><td>{$city['city_id']}</td><td>{$city['city_name']}</td></tr>";
        }
        echo "</table>";
    }
    
    if ($property_type_count > 0) {
        $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type ORDER BY proprety_type_id LIMIT 5");
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample Property Types</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th></tr>";
        foreach ($types as $type) {
            echo "<tr><td>{$type['proprety_type_id']}</td><td>{$type['proprety_type_name']}</td></tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    display_message('error', "Error checking database status: " . $e->getMessage());
}

// Display actions
echo "<h2>Available Actions</h2>";
echo "<p>Use these actions to fix your database constraints:</p>";
echo "<a href='?action=reset_cities' class='btn btn-danger' onclick='return confirm(\"This will delete all existing cities and import from cities.json. Continue?\");'>Reset & Import Cities</a> ";
echo "<a href='?action=reset_property_types' class='btn btn-danger' onclick='return confirm(\"This will delete all existing property types and import from property_types.json. Continue?\");'>Reset & Import Property Types</a> ";
echo "<a href='?action=test_insert' class='btn'>Test House Insertion</a> ";
echo "<a href='create_offer.php' class='btn btn-success'>Go to Create Offer Form</a>";

// Display troubleshooting information
echo "<h2>Troubleshooting</h2>";
echo "<p>If you're still experiencing the foreign key constraint error, try these steps:</p>";
echo "<ol>";
echo "<li>Click 'Reset & Import Cities' to ensure your cities table has the correct data.</li>";
echo "<li>Click 'Reset & Import Property Types' to ensure your property types table has the correct data.</li>";
echo "<li>Click 'Test House Insertion' to verify if a test property can be inserted.</li>";
echo "<li>If the test is successful, try using the Create Offer form again.</li>";
echo "<li>Make sure you have at least one owner account in your database.</li>";
echo "</ol>";

echo "<h3>Common Issues</h3>";
echo "<ul>";
echo "<li><strong>Foreign key constraint fails</strong>: This means the city_id or property_type_id you're trying to use doesn't exist in the respective tables.</li>";
echo "<li><strong>No cities in database</strong>: Use the 'Reset & Import Cities' button to populate your cities table.</li>";
echo "<li><strong>No property types in database</strong>: Use the 'Reset & Import Property Types' button to populate your property types table.</li>";
echo "<li><strong>No owner accounts</strong>: You need at least one owner account to create property listings.</li>";
echo "</ul>";

echo "</body>\n</html>";
?>
