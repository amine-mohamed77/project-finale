<?php
session_start();
require_once './db.php';

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    $_SESSION['error'] = "You must be logged in as an owner to create property listings";
    header("Location: offers.php");
    exit();
}

try {
    // First check if the database has property types and cities
    $stmt = $conn->prepare("SELECT COUNT(*) FROM proprety_type");
    $stmt->execute();
    $property_type_count = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM city");
    $stmt->execute();
    $city_count = $stmt->fetchColumn();
    
    // If database has property types, use them
    if ($property_type_count > 0) {
        $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type");
        $stmt->execute();
        $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Otherwise try to use JSON file
        $property_types_json = file_get_contents('property_types.json');
        $property_types_data = json_decode($property_types_json, true);
        
        if ($property_types_data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error parsing property_types.json: " . json_last_error_msg());
        }
        
        // Import property types to database
        $conn->beginTransaction();
        foreach ($property_types_data as $type) {
            $stmt = $conn->prepare("INSERT INTO proprety_type (proprety_type_id, proprety_type_name) VALUES (?, ?)");
            $stmt->execute([$type['id'], $type['name']]);
        }
        $conn->commit();
        
        // Get property types from database after import
        $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type");
        $stmt->execute();
        $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If database has cities, use them
    if ($city_count > 0) {
        $stmt = $conn->prepare("SELECT city_id, city_name FROM city");
        $stmt->execute();
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Otherwise try to use JSON file
        $cities_json = file_get_contents('cities.json');
        $cities_data = json_decode($cities_json, true);
        
        if ($cities_data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error parsing cities.json: " . json_last_error_msg());
        }
        
        // Import cities to database
        $conn->beginTransaction();
        foreach ($cities_data as $city) {
            $stmt = $conn->prepare("INSERT INTO city (city_id, city_name) VALUES (?, ?)");
            $stmt->execute([$city['id'], $city['name']]);
        }
        $conn->commit();
        
        // Get cities from database after import
        $stmt = $conn->prepare("SELECT city_id, city_name FROM city");
        $stmt->execute();
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading form data: " . $e->getMessage();
    header("Location: offers.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Housing Offer - UniHousing</title>
    <link rel="stylesheet" href="./offer-creat.css">
    <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="./logo-animation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <script src="./main.js" defer></script>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader">
        <dotlottie-player src="https://lottie.host/11f7d3b7-bf99-4f72-be1e-e72f766c5d3d/KJ6V4IJRpi.lottie" background="transparent" speed="1" style="width: 300px; height: 300px" loop autoplay></dotlottie-player>
    </div>
    
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="home.php">
                        <img src="./images/logopro.png" alt="UniHousing Logo">
                    </a>
                </div>
                <nav class="nav-menu">
                    <ul>
                        <li><a href="./home.php">Home</a></li>
                        <li><a href="./offers.php" class="active">Offers</a></li>
                        <li><a href="./demands.php">Demands</a></li>
                       
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="Profile.php" class="btn btn-primary">My Profile</a>
                        <a href="logout.php" class="btn btn-outline">Logout</a>
                    <?php else: ?>
                        <a href="./login.php" class="btn btn-outline">Login</a>
                        <a href="./signUp.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
                <div class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <div class="logo">
                <a href="home.php">
                    <img src="./images/logopro.png" alt="UniHousing Logo">
                </a>
            </div>
            <button class="mobile-menu-close" id="mobileMenuClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav class="mobile-menu-nav">
            <ul>
                <li><a href="home.php">Home</a></li>
                <li><a href="offers.php" class="active">Offers</a></li>
                <li><a href="demands.php">Demands</a></li>
                <li><a href="about.html">About</a></li>
                <li><a href="contact.html">Contact</a></li>
            </ul>
        </nav>
        <div class="mobile-menu-auth">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="Profile.php" class="btn btn-primary" style="position: relative;">
                    My Profile
                    <?php if (isset($unread_message_count) && $unread_message_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_message_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            <?php else: ?>
                <a href="./login.php" class="btn btn-outline">Login</a>
                <a href="./signUp.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Offer Form Section -->
    <section class="create-offer-section">
        <div class="container">
            <div class="create-offer-card">
                <h1>Create Housing Offer</h1>
                <p class="form-intro">List your property for students to find and rent</p>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <form action="process_offer.php" method="POST" enctype="multipart/form-data" class="property-form">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required placeholder="e.g. Modern Student Flat for University Criteria">
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Monthly Rent ($)</label>
                        <input type="number" id="price" name="price" required min="1" placeholder="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" required placeholder="123 University Ave, Suburb">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type</label>
                            <select id="property_type" name="property_type_id" required>
                                <option value="">Select type</option>
                                <?php foreach ($property_types as $type): ?>
                                    <option value="<?php echo isset($type['proprety_type_id']) ? $type['proprety_type_id'] : $type['id']; ?>"><?php echo htmlspecialchars(isset($type['proprety_type_name']) ? $type['proprety_type_name'] : $type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bedrooms">Bedrooms</label>
                            <input type="number" id="bedrooms" name="bedrooms" required min="0" value="1">
                        </div>
                        
                        <div class="form-group">
                            <label for="bathrooms">Bathrooms</label>
                            <input type="number" id="bathrooms" name="bathrooms" required min="0" value="1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4" required placeholder="Describe your property, including amenities and nearby facilities..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="city">City</label>
                        <select id="city" name="city_id" required>
                            <option value="">Select city</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo isset($city['city_id']) ? $city['city_id'] : $city['id']; ?>"><?php echo htmlspecialchars(isset($city['city_name']) ? $city['city_name'] : $city['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="property_images">Image URL</label>
                        <input type="file" id="property_images" name="property_images[]" multiple accept="image/*" required>
                        <p class="form-help">Upload up to 5 images. Recommended size: 1200x800 pixels.</p>
                    </div>
                    
                    <div class="form-actions">
                        <a href="offers.php" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Offer</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <a href="index.html">Uni<span>Housing</span></a>
                    <p>Connecting students with their perfect housing solutions.</p>
                </div>
                <div class="footer-links">
                    <div class="footer-column">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="index.html">Home</a></li>
                            <li><a href="offers.html">Offers</a></li>
                            <li><a href="demands.html">Demands</a></li>
                            <li><a href="about.html">About Us</a></li>
                            <li><a href="contact.html">Contact</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>For Students</h3>
                        <ul>
                            <li><a href="offers.html">Find Housing</a></li>
                            <li><a href="demands.html">Post Requirements</a></li>
                            <li><a href="#">Roommate Finder</a></li>
                            <li><a href="#">University Guides</a></li>
                            <li><a href="#">Student Resources</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>For Owners</h3>
                        <ul>
                            <li><a href="offers.html?create=true">List Property</a></li>
                            <li><a href="demands.html">View Student Demands</a></li>
                            <li><a href="#">Landlord Resources</a></li>
                            <li><a href="#">Verification Process</a></li>
                            <li><a href="#">Success Stories</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2023 UniHousing. All rights reserved.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="./main.js"></script>
    <script src="./logo-animation.js"></script>
</body>
</html>
