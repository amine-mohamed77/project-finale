<?php
require_once './db.php';

// Sample house data
$houses = [
    [
        'house_id' => 1,
        'city_id' => 33, // Agadir
        'proprety_type_id' => 2, // Apartment
        'owner_id' => 1,
        'house_title' => 'Modern Studio Apartment Near University',
        'house_price' => 500,
        'house_location' => 'Agadir, University District, 123 Student Ave',
        'house_badroom' => 1,
        'house_bathroom' => 1,
        'house_description' => 'A beautiful modern studio apartment perfect for students. Features include high-speed internet, fully furnished with modern appliances, and walking distance to campus.'
    ],
    [
        'house_id' => 2,
        'city_id' => 4, // Casablanca
        'proprety_type_id' => 3, // House
        'owner_id' => 1,
        'house_title' => 'Spacious Family House with Garden',
        'house_price' => 1200,
        'house_location' => 'Casablanca, Residential Area, 456 Family Street',
        'house_badroom' => 3,
        'house_bathroom' => 2,
        'house_description' => 'Large family house with a beautiful garden. Includes 3 bedrooms, 2 bathrooms, a fully equipped kitchen, and a spacious living room. Close to schools and shopping centers.'
    ],
    [
        'house_id' => 3,
        'city_id' => 11, // Meknès
        'proprety_type_id' => 4, // Room
        'owner_id' => 2,
        'house_title' => 'Cozy Room in Shared Apartment',
        'house_price' => 300,
        'house_location' => 'Meknès, City Center, 789 Roommate Road',
        'house_badroom' => 1,
        'house_bathroom' => 1,
        'house_description' => 'Comfortable private room in a shared apartment. Includes access to common areas, kitchen, and bathroom. Utilities included in the price. Perfect for students.'
    ],
    [
        'house_id' => 4,
        'city_id' => 33, // Agadir
        'proprety_type_id' => 5, // Dormitory
        'owner_id' => 2,
        'house_title' => 'University Dormitory - Single Room',
        'house_price' => 250,
        'house_location' => 'Agadir, Campus Area, University Residence Hall',
        'house_badroom' => 1,
        'house_bathroom' => 1,
        'house_description' => 'Single room in university dormitory. Shared bathroom and kitchen facilities. All utilities included. On-campus location with easy access to classes, library, and student center.'
    ],
    [
        'house_id' => 5,
        'city_id' => 4, // Casablanca
        'proprety_type_id' => 2, // Apartment
        'owner_id' => 3,
        'house_title' => 'Luxury Apartment with Ocean View',
        'house_price' => 800,
        'house_location' => 'Casablanca, Coastal Area, 101 Ocean Drive',
        'house_badroom' => 2,
        'house_bathroom' => 2,
        'house_description' => 'Stunning apartment with panoramic ocean views. Features 2 bedrooms, 2 bathrooms, modern kitchen, and a spacious balcony. Building includes gym and swimming pool.'
    ]
];

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Check if houses already exist
    $stmt = $conn->prepare("SELECT house_id FROM house");
    $stmt->execute();
    $existing_houses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $inserted_count = 0;
    $skipped_count = 0;
    $inserted_houses = [];
    
    // Insert houses
    foreach ($houses as $house) {
        // Skip if house_id already exists
        if (in_array($house['house_id'], $existing_houses)) {
            $skipped_count++;
            continue;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO house (
                house_id, city_id, proprety_type_id, owner_id, 
                house_title, house_price, house_location, 
                house_badroom, house_bathroom, house_description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $house['house_id'],
            $house['city_id'],
            $house['proprety_type_id'],
            $house['owner_id'],
            $house['house_title'],
            $house['house_price'],
            $house['house_location'],
            $house['house_badroom'],
            $house['house_bathroom'],
            $house['house_description']
        ]);
        
        $inserted_count++;
        $inserted_houses[] = $house;
    }
    
    // Commit transaction
    $conn->commit();
    
    // Display results
    echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Insert Sample Houses</title>\n<style>\nbody { font-family: Arial, sans-serif; margin: 20px; }\nh1, h2 { color: #2563eb; }\ntable { border-collapse: collapse; width: 100%; margin-top: 20px; }\nth, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\nth { background-color: #f2f2f2; }\ntr:nth-child(even) { background-color: #f9f9f9; }\n.success { color: green; }\n.info { color: blue; }\n.back-link { margin-top: 20px; display: block; }\n</style>\n</head>\n<body>\n";
    
    echo "<h1>Insert Sample Houses</h1>";
    
    echo "<p><span class='success'>$inserted_count houses inserted successfully.</span>";
    if ($skipped_count > 0) {
        echo " <span class='info'>$skipped_count houses skipped (already exist).</span>";
    }
    echo "</p>";
    
    if (!empty($inserted_houses)) {
        echo "<h2>Inserted Houses:</h2>";
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Title</th>";
        echo "<th>Type</th>";
        echo "<th>City</th>";
        echo "<th>Price</th>";
        echo "<th>Bedrooms</th>";
        echo "<th>Bathrooms</th>";
        echo "</tr>";
        
        foreach ($inserted_houses as $house) {
            // Get property type name
            $stmt = $conn->prepare("SELECT proprety_type_name FROM proprety_type WHERE proprety_type_id = ?");
            $stmt->execute([$house['proprety_type_id']]);
            $property_type = $stmt->fetchColumn() ?: 'Unknown';
            
            // Get city name
            $stmt = $conn->prepare("SELECT city_name FROM city WHERE city_id = ?");
            $stmt->execute([$house['city_id']]);
            $city_name = $stmt->fetchColumn() ?: 'Unknown';
            
            echo "<tr>";
            echo "<td>{$house['house_id']}</td>";
            echo "<td>{$house['house_title']}</td>";
            echo "<td>$property_type</td>";
            echo "<td>$city_name</td>";
            echo "<td>\${$house['house_price']}</td>";
            echo "<td>{$house['house_badroom']}</td>";
            echo "<td>{$house['house_bathroom']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    echo "<a href='offers.php' class='back-link'>Go to Offers Page</a>";
    echo "</body>\n</html>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<h1>Error</h1>";
    echo "<p>Error inserting houses: " . $e->getMessage() . "</p>";
    echo "<p>You may need to check if the owner_id, city_id, and proprety_type_id values exist in your database.</p>";
    echo "<a href='offers.php'>Go to Offers Page</a>";
}
?>
