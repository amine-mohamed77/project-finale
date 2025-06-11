<?php
session_start();
require_once './db.php';
require_once './includes/image_helper.php';
require_once './chat_notifications.php';

// Get unread message count if user is logged in
$unread_message_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $unread_message_count = getUnreadMessageCount($conn, $_SESSION['user_id'], $_SESSION['user_type']);
}

// Check if search was submitted from home page or offers page
$search_submitted = isset($_GET['search_submitted']) || isset($_GET['location']) || isset($_GET['property_type']);
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$property_type = isset($_GET['property_type']) ? $_GET['property_type'] : '';
$price_range = isset($_GET['price_range']) ? $_GET['price_range'] : '';

// Get all properties with optional filtering
try {
    // Base query
    $base_query = "
        SELECT h.*, c.city_name, pt.proprety_type_name, 
               (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
                JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
                WHERE hpp.house_id = h.house_id LIMIT 1) as image_url
        FROM house h
        JOIN city c ON h.city_id = c.city_id
        JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
    ";
    
    // Only apply filters if search was submitted
    if ($search_submitted) {
        $where_clauses = [];
        $params = [];
        
        // Location filter
        if (!empty($location)) {
            $where_clauses[] = "(c.city_name LIKE ? OR h.house_title LIKE ? OR h.house_description LIKE ?)";
            $params[] = "%$location%";
            $params[] = "%$location%";
            $params[] = "%$location%";
        }
        
        // Property type filter
        if (!empty($property_type)) {
            // Check if property_type is a number (ID) or a string (name)
            if (is_numeric($property_type)) {
                $where_clauses[] = "h.proprety_type_id = ?";
                $params[] = $property_type;
            } else {
                $where_clauses[] = "pt.proprety_type_name = ?";
                $params[] = $property_type;
            }
        }
        
        // Price range filter
        if (!empty($price_range)) {
            if ($price_range == '0-500') {
                $where_clauses[] = "h.house_price BETWEEN ? AND ?";
                $params[] = 0;
                $params[] = 500;
            } elseif ($price_range == '501-1000') {
                $where_clauses[] = "h.house_price BETWEEN ? AND ?";
                $params[] = 501;
                $params[] = 1000;
            } elseif ($price_range == '1001-1500') {
                $where_clauses[] = "h.house_price BETWEEN ? AND ?";
                $params[] = 1001;
                $params[] = 1500;
            } elseif ($price_range == '1501+') {
                $where_clauses[] = "h.house_price >= ?";
                $params[] = 1501;
            }
        }
        
        // Add WHERE clause if we have conditions
        if (!empty($where_clauses)) {
            $base_query .= " WHERE " . implode(" AND ", $where_clauses);
        }
    }
    
    // Add ordering
    $base_query .= " ORDER BY h.house_id DESC";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($base_query);
    if ($search_submitted && !empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's favorites if logged in as student
    $favorites = [];
    if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'student') {
        $stmt = $conn->prepare("SELECT house_id FROM student_house WHERE student_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Debug favorites
        error_log("User ID: " . $_SESSION['user_id'] . ", Found " . count($favorites) . " favorites");
        if (!empty($favorites)) {
            error_log("Favorite house IDs: " . implode(',', $favorites));
        }
    }
} catch (PDOException $e) {
    // Handle error
    $error_message = "Error fetching properties: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offers - UniHousing</title>
     <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="./logo-animation.css">
    <link rel="stylesheet" href="./notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
                        <!-- Notifications Icon and Panel -->
                        <div class="notification-icon-container">
                            <div class="notification-icon" id="notificationIcon">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                            </div>
                            
                            <!-- Notifications Panel -->
                            <div class="notifications-panel" id="notificationsPanel">
                                <div class="notifications-header">
                                    <h3>Notifications</h3>
                                    <button class="mark-all-read" id="markAllReadBtn">Mark all as read</button>
                                </div>
                                <div class="notifications-list" id="notificationsList">
                                    <!-- Notifications will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <a href="Profile.php" class="btn btn-primary" style="position: relative;">
                            My Profile
                            <?php if ($unread_message_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_message_count; ?></span>
                            <?php endif; ?>
                        </a>
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
                            <li><a href="./home.php">Home</a></li>
                            <li><a href="./offers.php" class="active">Offers</a></li>
                            <li><a href="./demands.php">Demands</a></li>
                            <li><a href="about.html">About</a></li>
                            <li><a href="contact.html">Contact</a></li>
                        </ul>
                    </nav>
                    <div class="mobile-menu-auth">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="Profile.php" class="btn btn-primary" style="position: relative;">
                                My Profile
                                <?php if ($unread_message_count > 0): ?>
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
            </div>
        </div>
    </header>

    <!-- Offers Hero Section -->
    <section class="offers-hero">
        <div class="offers-hero-background"></div>
        <div class="container">
            <div class="offers-hero-content">
                <h1>Available Properties</h1>
                <p>Find your perfect student accommodation from our extensive listings</p>
                <div class="hero-stats">
                    <div class="stat">
                        <span class="number">500+</span>
                        <span class="label">Active Listings</span>
                    </div>
                    <div class="stat">
                        <span class="number">50+</span>
                        <span class="label">Universities</span>
                    </div>
                    <div class="stat">
                        <span class="number">1000+</span>
                        <span class="label">Happy Students</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="filters-section">
        <div class="container">
            <div class="filters-header">
                <h2>Find Your Perfect Home</h2>
                <p>Use our advanced filters to find exactly what you're looking for</p>
            </div>
            <form action="offers.php" method="GET" class="filters-container">
                <input type="hidden" name="search_submitted" value="1">
                <div class="filter-group">
                    <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" id="location" name="location" placeholder="University or city" value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <label for="property_type"><i class="fas fa-home"></i> Property Type</label>
                    <select id="property_type" name="property_type">
                        <option value="" <?php echo $property_type === '' ? 'selected' : ''; ?>>All Types</option>
                        <option value="Apartment" <?php echo $property_type === 'Apartment' ? 'selected' : ''; ?>>Apartment</option>
                        <option value="House" <?php echo $property_type === 'House' ? 'selected' : ''; ?>>House</option>
                        <option value="Room" <?php echo $property_type === 'Room' ? 'selected' : ''; ?>>Room</option>
                        <option value="Dormitory" <?php echo $property_type === 'Dormitory' ? 'selected' : ''; ?>>Dormitory</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="price_range"><i class="fas fa-dollar-sign"></i> Price Range</label>
                    <select id="price_range" name="price_range">
                        <option value="" <?php echo $price_range === '' ? 'selected' : ''; ?>>Any Price</option>
                        <option value="0-500" <?php echo $price_range === '0-500' ? 'selected' : ''; ?>>$0 - $500</option>
                        <option value="501-1000" <?php echo $price_range === '501-1000' ? 'selected' : ''; ?>>$501 - $1000</option>
                        <option value="1001-1500" <?php echo $price_range === '1001-1500' ? 'selected' : ''; ?>>$1001 - $1500</option>
                        <option value="1501+" <?php echo $price_range === '1501+' ? 'selected' : ''; ?>>$1501+</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="bedrooms"><i class="fas fa-bed"></i> Bedrooms</label>
                    <select id="bedrooms" name="bedrooms">
                        <option value="">Any</option>
                        <option value="1">1 Bedroom</option>
                        <option value="2">2 Bedrooms</option>
                        <option value="3">3+ Bedrooms</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary search-btn">
                    <i class="fas fa-search"></i> Search Properties
                </button>
            </form>
            <div class="quick-filters">
                <span class="quick-filter-label">Popular:</span>
                <a href="#" class="quick-filter">Near Campus</a>
                <a href="#" class="quick-filter">Furnished</a>
                <a href="#" class="quick-filter">Pet Friendly</a>
                <a href="#" class="quick-filter">All Inclusive</a>
            </div>
        </div>
    </section>

    <!-- Properties Grid Section -->
    <section class="property-section">
        <div class="container">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info">
                    <?php echo htmlspecialchars($_SESSION['info']); ?>
                </div>
                <?php unset($_SESSION['info']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'owner'): ?>
            <div class="section-header">
                <h2>Property Listings</h2>
                <a href="create_offer.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Housing Offer
                </a>
            </div>
            <?php else: ?>
            <h2 class="section-title">Property Listings</h2>
            <?php endif; ?>
            
            <div class="property-grid">
                <?php if (!empty($properties)): ?>
                    <?php foreach ($properties as $property): ?>
                        <div class="property-card">
                            <div class="property-image">
                                <?php 
                                // Use the helper function to get a valid image path
                                $image_src = get_valid_image_path($property['image_url']);
                                ?>
                                <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                <div class="property-overlay">
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'student'): ?>
                                        <?php if (in_array($property['house_id'], $favorites)): ?>
                                            <a href="javascript:void(0)" data-property-id="<?php echo $property['house_id']; ?>" class="favorite-btn active" title="Remove from favorites">
                                                <i class="fas fa-heart"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="javascript:void(0)" data-property-id="<?php echo $property['house_id']; ?>" class="favorite-btn" title="Add to favorites">
                                                <i class="far fa-heart"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="login.php" class="favorite-btn" data-property-id="0" title="Login to add to favorites">
                                            <i class="far fa-heart"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php
                                    // Check if house_status exists before using it
                                    $status = isset($property['house_status']) ? $property['house_status'] : '';
                                    
                                    if ($status == 'New'): ?>
                                        <span class="property-tag">New</span>
                                    <?php elseif ($status == 'Popular'): ?>
                                        <span class="property-tag">Popular</span>
                                    <?php elseif ($status == 'Featured'): ?>
                                        <span class="property-tag">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="property-content">
                                <div class="property-header">
                                    <h3><?php echo htmlspecialchars($property['house_title']); ?></h3>
                                    <p class="price">$<?php echo htmlspecialchars($property['house_price']); ?>/mo</p>
                                </div>
                                <div class="property-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <p><?php echo htmlspecialchars($property['house_location']); ?>, <?php echo htmlspecialchars($property['city_name']); ?></p>
                                </div>
                                <div class="property-features">
                                    <div class="feature">
                                        <i class="fas fa-bed"></i>
                                        <span><?php echo htmlspecialchars($property['house_badroom']); ?> Bed<?php echo $property['house_badroom'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                    <div class="feature">
                                        <i class="fas fa-bath"></i>
                                        <span><?php echo htmlspecialchars($property['house_bathroom']); ?> Bath<?php echo $property['house_bathroom'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                    <div class="feature">
                                        <i class="fas fa-vector-square"></i>
                                        <span><?php echo isset($property['house_surface']) ? htmlspecialchars($property['house_surface']) : 'N/A'; ?> m²</span>
                                    </div>
                                </div>
                                <a href="view_property.php?id=<?php echo $property['house_id']; ?>" class="btn btn-primary btn-full">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-home"></i>
                        <h3>No Properties Available</h3>
                        <p>There are currently no properties listed. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Pagination -->
            <div class="pagination">
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <a href="#" class="next">Next <i class="fas fa-chevron-right"></i></a>
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
  <script src="./logo-animation.js"></script>
    <script src="./main.js"></script>
    <script src="js/notifications.js"></script>
    <script src="js/favorites.js"></script>
</body>
</html> 