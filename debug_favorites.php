<?php
session_start();
require_once './db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>Not logged in. Please <a href='login.php'>login</a> first.</p>";
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

echo "<h1>Favorites Debug Information</h1>";
echo "<p>User ID: $user_id</p>";
echo "<p>User Type: $user_type</p>";

if ($user_type != 'student') {
    echo "<p>Only students can have favorites. You are logged in as an owner.</p>";
    exit();
}

try {
    // Check student_house table
    $stmt = $conn->prepare("SELECT * FROM student_house WHERE student_id = ?");
    $stmt->execute([$user_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Student House Records</h2>";
    if (empty($favorites)) {
        echo "<p>No favorites found in student_house table for student ID: $user_id</p>";
    } else {
        echo "<p>Found " . count($favorites) . " favorites in student_house table:</p>";
        echo "<ul>";
        foreach ($favorites as $fav) {
            echo "<li>House ID: {$fav['house_id']}, Date: {$fav['student_house_date']}</li>";
        }
        echo "</ul>";
    }
    
    // Check house table for these IDs
    if (!empty($favorites)) {
        $house_ids = array_column($favorites, 'house_id');
        $placeholders = str_repeat('?,', count($house_ids) - 1) . '?';
        
        $stmt = $conn->prepare("SELECT * FROM house WHERE house_id IN ($placeholders)");
        $stmt->execute($house_ids);
        $houses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>House Records</h2>";
        if (empty($houses)) {
            echo "<p>No matching houses found in house table for the favorite house IDs</p>";
        } else {
            echo "<p>Found " . count($houses) . " houses:</p>";
            echo "<ul>";
            foreach ($houses as $house) {
                echo "<li>House ID: {$house['house_id']}, Title: {$house['house_title']}</li>";
            }
            echo "</ul>";
        }
    }
    
    // Check the query used in Profile.php
    echo "<h2>Testing Profile.php Query</h2>";
    $stmt = $conn->prepare("
        SELECT h.*, c.city_name, pt.proprety_type_name, 
               (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
                JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
                WHERE hpp.house_id = h.house_id LIMIT 1) as image_url,
               sh.student_house_date
        FROM student_house sh
        JOIN house h ON sh.house_id = h.house_id
        JOIN city c ON h.city_id = c.city_id
        JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
        WHERE sh.student_id = ?
        ORDER BY sh.student_house_date DESC
    ");
    $stmt->execute([$user_id]);
    $profile_favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($profile_favorites)) {
        echo "<p>No favorites found using Profile.php query</p>";
    } else {
        echo "<p>Found " . count($profile_favorites) . " favorites using Profile.php query:</p>";
        echo "<ul>";
        foreach ($profile_favorites as $fav) {
            echo "<li>House ID: {$fav['house_id']}, Title: {$fav['house_title']}</li>";
        }
        echo "</ul>";
    }
    
    // Check if the property images are correctly linked
    if (!empty($profile_favorites)) {
        echo "<h2>Property Images Check</h2>";
        foreach ($profile_favorites as $fav) {
            echo "<p>House ID: {$fav['house_id']}, Image URL: " . ($fav['image_url'] ?? 'No image') . "</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='Profile.php'>Return to Profile</a></p>";
