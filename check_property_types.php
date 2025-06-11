<?php
require_once './db.php';

try {
    // Check existing property types
    $stmt = $conn->prepare("SELECT * FROM proprety_type");
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Property Types:</h2>";
    echo "<pre>";
    print_r($types);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
