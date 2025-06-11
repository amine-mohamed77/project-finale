<?php
session_start();
require_once './db.php';
require_once './includes/image_helper.php';
require_once './chat_notifications.php';

// Check if search was submitted
$search_submitted = isset($_GET['location']) || isset($_GET['property_type']);
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$property_type = isset($_GET['property_type']) ? $_GET['property_type'] : '';

// Fetch property types for dropdown and display
try {
    $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type");
    $stmt->execute();
    $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $property_types = [];
    error_log("Error fetching property types: " . $e->getMessage());
}

// Fetch search results if search was submitted
$search_results = [];
$search_debug = []; // For debugging

if ($search_submitted) {
    try {
        // Base query for search results
        $search_query = "SELECT h.*, c.city_name, pt.proprety_type_name, o.owner_name, o.owner_email, o.owner_id,
                        (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
                         JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
                         WHERE hpp.house_id = h.house_id LIMIT 1) as image_url
                 FROM house h
                 JOIN city c ON h.city_id = c.city_id
                 JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
                 JOIN owner o ON h.owner_id = o.owner_id";
        
        $where_clauses = [];
        $params = [];
        
        // Location filter - more flexible matching
        if (!empty($location)) {
            $where_clauses[] = "(c.city_name LIKE ? OR h.house_title LIKE ? OR h.house_description LIKE ?)";
            $params[] = "%$location%";
            $params[] = "%$location%";
            $params[] = "%$location%";
        }
        
        // Property type filter - more flexible matching
        if (!empty($property_type)) {
            // Check if property_type is a number (ID) or a string (name)
            if (is_numeric($property_type)) {
                $where_clauses[] = "h.proprety_type_id = ?";
                $params[] = $property_type;
            } else {
                $where_clauses[] = "pt.proprety_type_name LIKE ?";
                $params[] = "%$property_type%";
            }
        }
        
        // Add WHERE clause if we have conditions
        if (!empty($where_clauses)) {
            $search_query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        // Add ordering and limit
        $search_query .= " ORDER BY h.house_id DESC LIMIT 20";
        
        // Save query for debugging
        $search_debug['query'] = $search_query;
        $search_debug['params'] = $params;
        
        // Prepare and execute the query
        $stmt = $conn->prepare($search_query);
        if (!empty($params)) {
            $stmt->execute($params);
        } else {
            $stmt->execute();
        }
        
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $search_debug['count'] = count($search_results);
        
        // If no results with strict filters, try a more relaxed search
        if (empty($search_results) && (!empty($location) || !empty($property_type))) {
            // Prepare a more relaxed query
            $relaxed_query = "SELECT h.*, c.city_name, pt.proprety_type_name, o.owner_name, o.owner_email, o.owner_id,
                            (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
                             JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
                             WHERE hpp.house_id = h.house_id LIMIT 1) as image_url
                     FROM house h
                     JOIN city c ON h.city_id = c.city_id
                     JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
                     JOIN owner o ON h.owner_id = o.owner_id";
            
            $relaxed_where = [];
            $relaxed_params = [];
            
            if (!empty($location)) {
                $relaxed_where[] = "(c.city_name LIKE ? OR h.house_title LIKE ? OR h.house_description LIKE ?)";
                $relaxed_params[] = "%$location%";
                $relaxed_params[] = "%$location%";
                $relaxed_params[] = "%$location%";
            }
            
            if (!empty($relaxed_where)) {
                $relaxed_query .= " WHERE " . implode(" OR ", $relaxed_where);
            }
            
            $relaxed_query .= " ORDER BY h.house_id DESC LIMIT 20";
            
            $search_debug['relaxed_query'] = $relaxed_query;
            $search_debug['relaxed_params'] = $relaxed_params;
            
            $stmt = $conn->prepare($relaxed_query);
            if (!empty($relaxed_params)) {
                $stmt->execute($relaxed_params);
            } else {
                $stmt->execute();
            }
            
            $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $search_debug['relaxed_count'] = count($search_results);
        }
    } catch (PDOException $e) {
        // Handle error
        $search_debug['error'] = $e->getMessage();
        error_log("Search error: " . $e->getMessage());
    }
}

// Fetch recommended properties with a basic recommendation algorithm
try {
    // Base query for recommendations
    $query = "
        SELECT h.*, c.city_name, pt.proprety_type_name, o.owner_name, o.owner_email, o.owner_id
        FROM house h
        JOIN city c ON h.city_id = c.city_id
        JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
        JOIN owner o ON h.owner_id = o.owner_id
    ";
    
    // For student users, always show random properties regardless of preferences
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'student') {
        // Show 10 random properties for students
        $query .= " ORDER BY RAND() LIMIT 10";
    } else {
        // For owners or non-logged in users, use the existing logic
        // Get user preferences if logged in as owner
        $user_preferences = [];
        $preference_condition = "";
        $preference_params = [];
        
        // For owner users, we could implement different recommendation logic here if needed
        // For now, just show random properties
        $query .= " ORDER BY RAND() LIMIT 10";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $recommended_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get property images
    foreach ($recommended_properties as $key => $property) {
        $stmt = $conn->prepare("
            SELECT pp.proprety_pictures_name as image_url
            FROM proprety_pictures pp
            JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id
            WHERE hpp.house_id = ?
            LIMIT 1
        ");
        $stmt->execute([$property['house_id']]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        $recommended_properties[$key]['image_url'] = $image ? $image['image_url'] : 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80';
    }
} catch (PDOException $e) {
    $recommended_properties = [];
    error_log("Error fetching recommended properties: " . $e->getMessage());
}

// Fetch nearby properties (random selection for demo purposes)
try {
    $stmt = $conn->prepare("
        SELECT h.*, c.city_name, pt.proprety_type_name, o.owner_name, o.owner_email, o.owner_id
        FROM house h
        JOIN city c ON h.city_id = c.city_id
        JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
        JOIN owner o ON h.owner_id = o.owner_id
        ORDER BY RAND()
        LIMIT 3
    ");
    $stmt->execute();
    $nearby_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get property images
    foreach ($nearby_properties as $key => $property) {
        $stmt = $conn->prepare("
            SELECT pp.proprety_pictures_name as image_url
            FROM proprety_pictures pp
            JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id
            WHERE hpp.house_id = ?
            LIMIT 1
        ");
        $stmt->execute([$property['house_id']]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        $nearby_properties[$key]['image_url'] = $image ? $image['image_url'] : 'https://images.unsplash.com/photo-1560185007-5f0bb1866cab?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80';
    }
} catch (PDOException $e) {
    $nearby_properties = [];
    error_log("Error fetching nearby properties: " . $e->getMessage());
}

// Get unread message count if user is logged in
$unread_message_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $unread_message_count = getUnreadMessageCount($conn, $_SESSION['user_id'], $_SESSION['user_type']);
}

// Check for remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = base64_decode($_COOKIE['remember_token']);
    $parts = explode(':', $token);
    
    if (count($parts) == 2) {
        $user_id = $parts[0];
        $user_type = $parts[1];
        
        try {
            if ($user_type == 'student') {
                $stmt = $conn->prepare("SELECT student_id as user_id, student_name as name, student_email as email, 'student' as user_type FROM student WHERE student_id = ?");
            } else {
                $stmt = $conn->prepare("SELECT owner_id as user_id, owner_name as name, owner_email as email, 'owner' as user_type FROM owner WHERE owner_id = ?");
            }
            
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
            }
        } catch (PDOException $e) {
            // Invalid token, delete cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
}

$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $currentUser = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'type' => $_SESSION['user_type']
    ];
}

// Clear any success messages
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniHousing - Student Housing Platform</title>
    <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="./logo-animation.css">
    <link rel="stylesheet" href="notifications.css">
    <link rel="stylesheet" href="./search-results.css">
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="./js/chat-notifications.js" defer></script>
    <script src="./js/notifications.js" defer></script>
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
                        <li><a href="home.php" class="active">Home</a></li>
                        <li><a href="./offers.php">Offers</a></li>
                        <li><a href="./demands.php">Demands</a></li>
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <?php if ($currentUser): ?>
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
                        
                        <a href="Profile.php" class="btn btn-primary">
                            My Profile
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
            <div class="mobile-menu-close" id="mobileMenuClose">
                <i class="fas fa-times"></i>
            </div>
        </div>
        <nav class="mobile-menu-nav">
            <ul>
                <li><a href="home.php" class="active">Home</a></li>
                <li><a href="offers.php">Offers</a></li>
                <li><a href="demands.php">Demands</a></li>
                <li><a href="about.html">About</a></li>
                <li><a href="contact.html">Contact</a></li>
            </ul>
        </nav>
        <div class="mobile-menu-auth">
            <?php if ($currentUser): ?>
                <a href="Profile.php" class="btn btn-primary" style="position: relative;">
                    My Profile
                    <?php if ($unread_message_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_message_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Login</a>
                <a href="signUp.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-background"></div>
        <div class="container">
            <div class="hero-content">
                <h1>Find Your Perfect Student Housing</h1>
                <p>Connect with apartment owners and find the ideal place near your university.</p>
                
                <form action="home.php#search-results" method="GET" class="search-box">
                    <div class="search-input">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="location" placeholder="University or location">
                    </div>
                    <div class="search-input">
                        <i class="fas fa-home"></i>
                        <select name="property_type">
                            <option value="">Property type</option>
                            <?php
                            // Fetch property types from database
                            try {
                                $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type");
                                $stmt->execute();
                                $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($property_types as $type) {
                                    echo '<option value="' . $type['proprety_type_id'] . '">' . $type['proprety_type_name'] . '</option>';
                                }
                            } catch (PDOException $e) {
                                // If error, show default options
                                echo '<option value="1">Apartment</option>';
                                echo '<option value="2">House</option>';
                                echo '<option value="3">Room</option>';
                                echo '<option value="4">Dormitory</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="statistics">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-content">
                        <h3>10,000+</h3>
                        <p>Properties Listed</p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>50,000+</h3>
                        <p>Happy Students</p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="stat-content">
                        <h3>500+</h3>
                        <p>Universities Covered</p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <h3>4.8/5</h3>
                        <p>User Rating</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Results Section -->
    <?php if ($search_submitted): ?>
    <section id="search-results" class="search-results-section">
        <div class="container">
            <div class="section-header">
                <h2>Search Results</h2>
                <p>
                    <?php if (!empty($location) && !empty($property_type)): ?>
                        Showing results for <strong>"<?php echo htmlspecialchars($location); ?>"</strong> in property type <strong>"<?php 
                            // Display property type name instead of ID
                            if (is_numeric($property_type)) {
                                foreach ($property_types as $type) {
                                    if ($type['proprety_type_id'] == $property_type) {
                                        echo htmlspecialchars($type['proprety_type_name']);
                                        break;
                                    }
                                }
                            } else {
                                echo htmlspecialchars($property_type);
                            }
                        ?>"</strong>
                    <?php elseif (!empty($location)): ?>
                        Showing results for <strong>"<?php echo htmlspecialchars($location); ?>"</strong>
                    <?php elseif (!empty($property_type)): ?>
                        Showing results for property type <strong>"<?php 
                            // Display property type name instead of ID
                            if (is_numeric($property_type)) {
                                foreach ($property_types as $type) {
                                    if ($type['proprety_type_id'] == $property_type) {
                                        echo htmlspecialchars($type['proprety_type_name']);
                                        break;
                                    }
                                }
                            } else {
                                echo htmlspecialchars($property_type);
                            }
                        ?>"</strong>
                    <?php else: ?>
                        Showing all available properties
                    <?php endif; ?>
                </p>
                <div class="search-result-count">
                    <?php echo count($search_results); ?> properties found
                </div>
            </div>

            <?php if (empty($search_results)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No properties found</h3>
                <p>Try adjusting your search criteria or browse our recommended properties below.</p>
                
                <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
                <div class="debug-info" style="text-align: left; margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px;">
                    <h4>Debug Information</h4>
                    <p>Search Parameters:</p>
                    <ul>
                        <li>Location: "<?php echo htmlspecialchars($location); ?>"</li>
                        <li>Property Type: "<?php echo htmlspecialchars($property_type); ?>"</li>
                    </ul>
                    <?php if (!empty($search_debug)): ?>
                        <p>Query Information:</p>
                        <pre style="background: #eee; padding: 10px; overflow: auto; max-height: 200px;"><?php echo htmlspecialchars(print_r($search_debug, true)); ?></pre>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="property-grid">
                <?php foreach ($search_results as $property): ?>
                <div class="property-card">
                    <div class="property-image">
                        <?php 
                        // Use the helper function to get a valid image path
                        $image_src = get_valid_image_path($property['image_url']);
                        ?>
                        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo isset($property['house_title']) ? htmlspecialchars($property['house_title']) : 'Property'; ?>">
                        <div class="property-price">
                            <span>$<?php echo isset($property['house_price']) ? number_format($property['house_price']) : '0'; ?>/month</span>
                        </div>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'student'): ?>
                        <div class="property-favorite" data-property-id="<?php echo isset($property['house_id']) ? $property['house_id'] : '0'; ?>">
                            <i class="<?php echo isset($property['house_id']) && in_array($property['house_id'], $favorites ?? []) ? 'fas' : 'far'; ?> fa-heart"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="property-details">
                        <h3 class="property-title"><?php echo isset($property['house_title']) ? htmlspecialchars($property['house_title']) : 'Property Title'; ?></h3>
                        <p class="property-location"><i class="fas fa-map-marker-alt"></i> <?php echo isset($property['city_name']) ? htmlspecialchars($property['city_name']) : 'Location'; ?></p>
                        <div class="property-features search-property-features">
                            <span><i class="fas fa-home"></i> <?php echo isset($property['proprety_type_name']) ? htmlspecialchars($property['proprety_type_name']) : 'Property Type'; ?></span>
                            <span><i class="fas fa-bed"></i> <?php echo isset($property['house_badroom']) ? $property['house_badroom'] : '0'; ?> Bed<?php echo isset($property['house_badroom']) && $property['house_badroom'] > 1 ? 's' : ''; ?></span>
                            <span><i class="fas fa-bath"></i> <?php echo isset($property['house_bathroom']) ? $property['house_bathroom'] : '0'; ?> Bath<?php echo isset($property['house_bathroom']) && $property['house_bathroom'] > 1 ? 's' : ''; ?></span>
                        </div>
                        <p class="property-description"><?php echo isset($property['house_description']) ? substr(htmlspecialchars($property['house_description']), 0, 100) . '...' : 'No description available.'; ?></p>
                        <div class="property-actions search-property-actions">
                            <a href="view_property.php?id=<?php echo isset($property['house_id']) ? $property['house_id'] : '0'; ?>" class="btn btn-primary search-btn-details"><i class="fas fa-info-circle"></i> View Details</a>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'student'): ?>
                            <a href="chat.php?owner_id=<?php echo isset($property['owner_id']) ? $property['owner_id'] : '0'; ?>" class="btn btn-outline search-btn-contact"><i class="fas fa-comments"></i> Contact Owner</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Navigation Cards -->
    <section class="nav-cards">
        <div class="container">
            <div class="cards-grid">
                <a href="offers.html" class="nav-card">
                    <div class="icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h2>Browse Offers</h2>
                    <p>Explore available apartments and houses posted by owners.</p>
                </a>
                <a href="demands.html" class="nav-card">
                    <div class="icon green">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h2>Student Demands</h2>
                    <p>View what students are looking for and post your own requirements.</p>
                </a>
                <a href="profile.html" class="nav-card">
                    <div class="icon purple">
                        <i class="fas fa-star"></i>
                    </div>
                    <h2>Your Profile</h2>
                    <p>Manage your profile, view saved properties, and track applications.</p>
                </a>
            </div>
        </div>
    </section>

    <!-- Recommended Section -->
    <section class="property-section">
        <div class="container">
            <div class="section-header">
                <h2>Recommended For You</h2>
                <a href="offers.php" class="view-all">View all</a>
            </div>
            
            <div class="property-grid">
                <?php if (!empty($recommended_properties)): ?>
                    <?php foreach ($recommended_properties as $property): ?>
                        <div class="property-card">
                            <div class="property-image">
                                <?php $image_src = get_valid_image_path($property['image_url']); ?>
                                <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'student'): ?>
                                    <button class="favorite-btn" data-house-id="<?php echo $property['house_id']; ?>">
                                        <i class="far fa-heart"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="favorite-btn" title="Login to add to favorites">
                                        <i class="far fa-heart"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="property-content">
                                <div class="property-header">
                                    <h3><?php echo htmlspecialchars($property['house_title']); ?></h3>
                                    <p class="price"><?php echo htmlspecialchars($property['house_price']); ?>/mo</p>
                                </div>
                                <div class="property-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <p><?php echo htmlspecialchars($property['city_name']); ?></p>
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
                        <p>Check back soon for new listings!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Nearby University Section -->
    <section class="property-section">
        <div class="container">
            <div class="section-header">
                <h2>Nearby Your University</h2>
                <a href="offers.php" class="view-all">View all</a>
            </div>
            
            <div class="property-grid">
                <?php if (!empty($nearby_properties)): ?>
                    <?php foreach ($nearby_properties as $property): ?>
                        <div class="property-card">
                            <div class="property-image">
                                <?php $image_src = get_valid_image_path($property['image_url']); ?>
                                <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'student'): ?>
                                    <button class="favorite-btn" data-house-id="<?php echo $property['house_id']; ?>">
                                        <i class="far fa-heart"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="favorite-btn" title="Login to add to favorites">
                                        <i class="far fa-heart"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="property-content">
                                <div class="property-header">
                                    <h3><?php echo htmlspecialchars($property['house_title']); ?></h3>
                                    <p class="price"><?php echo htmlspecialchars($property['house_price']); ?>/mo</p>
                                </div>
                                <div class="property-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <p><?php echo htmlspecialchars($property['city_name']); ?></p>
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
                        <p>Check back soon for new listings!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <div class="steps-container">
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Create an Account</h3>
                    <p>Sign up as a student or property owner to get started.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Find or List Properties</h3>
                    <p>Search for housing or list your property for students.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Connect & Communicate</h3>
                    <p>Message directly with students or property owners.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h3>Secure Your Housing</h3>
                    <p>Finalize details and move into your new home.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">What Our Users Say</h2>
            <div class="testimonial-slider">
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"I found my perfect apartment near campus in just two days. The platform made it so easy to connect with property owners!"</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" alt="Sarah Johnson">
                        <div class="author-info">
                            <h4>Sarah Johnson</h4>
                            <p>Stanford University</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"As a property owner, I've been able to find reliable student tenants quickly. The verification process gives me peace of mind."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" alt="Michael Chen">
                        <div class="author-info">
                            <h4>Michael Chen</h4>
                            <p>Property Owner</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"The demand posting feature helped me find exactly what I was looking for. Property owners reached out with perfect matches!"</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1976&q=80" alt="James Wilson">
                        <div class="author-info">
                            <h4>James Wilson</h4>
                            <p>UCLA Graduate Student</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="slider-dots">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Find Your Perfect Student Housing?</h2>
                <p>Join thousands of students and property owners on our platform.</p>
                <div class="cta-buttons">
                    <?php if (!$currentUser): ?>
                        <a href="signUp.php" class="btn btn-primary">Sign Up Now</a>
                        <a href="about.html" class="btn btn-outline">Learn More</a>
                    <?php else: ?>
                        <a href="offers.html" class="btn btn-primary">Browse Properties</a>
                        <a href="demands.html" class="btn btn-outline">Post Requirements</a>
                    <?php endif; ?>
                </div>
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
    
    <!-- Favorites functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if user is logged in as student
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'student'): ?>
            // Get all favorite buttons with house IDs (both types)
            const favoriteButtons = document.querySelectorAll('.favorite-btn[data-house-id]');
            const propertyFavorites = document.querySelectorAll('.property-favorite[data-property-id]');
            
            // Collect house IDs from both button types
            const houseIds = [
                ...Array.from(favoriteButtons).map(btn => btn.dataset.houseId),
                ...Array.from(propertyFavorites).map(btn => btn.dataset.propertyId)
            ];
            
            // Check which properties are already in favorites
            if (houseIds.length > 0) {
                houseIds.forEach(houseId => {
                    checkFavoriteStatus(houseId);
                });
            }
            
            // Add click event listeners to favorite buttons
            favoriteButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const houseId = this.dataset.houseId;
                    const isFavorite = this.classList.contains('active');
                    
                    if (isFavorite) {
                        removeFavorite(houseId, this);
                    } else {
                        addFavorite(houseId, this);
                    }
                });
            });
            
            // Add click event listeners to property-favorite buttons in search results
            propertyFavorites.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const houseId = this.dataset.propertyId;
                    const heartIcon = this.querySelector('i');
                    const isFavorite = heartIcon.classList.contains('fas');
                    
                    if (isFavorite) {
                        removeFavoriteSearch(houseId, this);
                    } else {
                        addFavoriteSearch(houseId, this);
                    }
                });
            });
        <?php endif; ?>
        
        // Function to check if a property is in favorites
        function checkFavoriteStatus(houseId) {
            fetch('ajax_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'house_id=' + houseId + '&action=check'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.is_favorite) {
                    // Update regular favorite buttons
                    const btn = document.querySelector(`.favorite-btn[data-house-id="${houseId}"]`);
                    if (btn) {
                        btn.classList.add('active');
                        btn.querySelector('i').classList.remove('far');
                        btn.querySelector('i').classList.add('fas');
                    }
                    
                    // Update search results favorite buttons
                    const searchBtn = document.querySelector(`.property-favorite[data-property-id="${houseId}"]`);
                    if (searchBtn) {
                        const heartIcon = searchBtn.querySelector('i');
                        heartIcon.classList.remove('far');
                        heartIcon.classList.add('fas');
                    }
                }
            })
            .catch(error => console.error('Error checking favorite status:', error));
        }
        
        // Function to add a property to favorites
        function addFavorite(houseId, button) {
            fetch('ajax_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'house_id=' + houseId + '&action=add'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button appearance
                    button.classList.add('active');
                    button.querySelector('i').classList.remove('far');
                    button.querySelector('i').classList.add('fas');
                    
                    // Also update any matching search result favorites
                    const searchBtn = document.querySelector(`.property-favorite[data-property-id="${houseId}"]`);
                    if (searchBtn) {
                        const heartIcon = searchBtn.querySelector('i');
                        heartIcon.classList.remove('far');
                        heartIcon.classList.add('fas');
                    }
                    
                    // Optional: Show a toast or notification
                    showNotification(data.message);
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error adding favorite:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }
        
        // Function to add a property to favorites from search results
        function addFavoriteSearch(houseId, button) {
            fetch('ajax_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'house_id=' + houseId + '&action=add'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update search button appearance
                    const heartIcon = button.querySelector('i');
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                    
                    // Add animation effect
                    button.classList.add('favorited');
                    setTimeout(() => {
                        button.classList.remove('favorited');
                    }, 700);
                    
                    // Also update any matching regular favorites
                    const regularBtn = document.querySelector(`.favorite-btn[data-house-id="${houseId}"]`);
                    if (regularBtn) {
                        regularBtn.classList.add('active');
                        regularBtn.querySelector('i').classList.remove('far');
                        regularBtn.querySelector('i').classList.add('fas');
                    }
                    
                    // Optional: Show a toast or notification
                    showNotification(data.message);
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error adding favorite:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }
        
        // Function to remove a property from favorites
        function removeFavorite(houseId, button) {
            fetch('ajax_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'house_id=' + houseId + '&action=remove'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button appearance
                    button.classList.remove('active');
                    button.querySelector('i').classList.remove('fas');
                    button.querySelector('i').classList.add('far');
                    
                    // Also update any matching search result favorites
                    const searchBtn = document.querySelector(`.property-favorite[data-property-id="${houseId}"]`);
                    if (searchBtn) {
                        const heartIcon = searchBtn.querySelector('i');
                        heartIcon.classList.remove('fas');
                        heartIcon.classList.add('far');
                    }
                    
                    // Optional: Show a toast or notification
                    showNotification(data.message);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error removing favorite:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }
        
        // Function to remove a property from favorites from search results
        function removeFavoriteSearch(houseId, button) {
            fetch('ajax_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'house_id=' + houseId + '&action=remove'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update search button appearance
                    const heartIcon = button.querySelector('i');
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                    
                    // Also update any matching regular favorites
                    const regularBtn = document.querySelector(`.favorite-btn[data-house-id="${houseId}"]`);
                    if (regularBtn) {
                        regularBtn.classList.remove('active');
                        regularBtn.querySelector('i').classList.remove('fas');
                        regularBtn.querySelector('i').classList.add('far');
                    }
                    
                    // Optional: Show a toast or notification
                    showNotification(data.message);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error removing favorite:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }
        
        // Simple notification function (you can replace with a toast library if available)
        function showNotification(message, type = 'success') {
            // Check if notification container exists, if not create it
            let notificationContainer = document.getElementById('notification-container');
            if (!notificationContainer) {
                notificationContainer = document.createElement('div');
                notificationContainer.id = 'notification-container';
                notificationContainer.style.position = 'fixed';
                notificationContainer.style.bottom = '20px';
                notificationContainer.style.right = '20px';
                notificationContainer.style.zIndex = '9999';
                document.body.appendChild(notificationContainer);
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            notification.style.backgroundColor = type === 'success' ? '#4CAF50' : '#F44336';
            notification.style.color = 'white';
            notification.style.padding = '12px 16px';
            notification.style.marginTop = '10px';
            notification.style.borderRadius = '4px';
            notification.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s ease';
            
            // Add to container
            notificationContainer.appendChild(notification);
            
            // Fade in
            setTimeout(() => {
                notification.style.opacity = '1';
            }, 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notificationContainer.removeChild(notification);
                }, 300);
            }, 3000);
        }
    });
    </script>
</body>
</html>
