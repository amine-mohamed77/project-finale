<?php
session_start();
require_once './db.php';
require_once './includes/image_helper.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to view your profile";
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_data = [];
$profile_picture = null;

try {
    if ($user_type == 'student') {
        $stmt = $conn->prepare("
            SELECT s.*, p.picture_url 
            FROM student s
            LEFT JOIN picture p ON s.picture_id = p.picture_id
            WHERE s.student_id = ?
        ");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get wishlist/favorites
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
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug favorites
        if (empty($favorites)) {
            error_log("No favorites found for student ID: " . $user_id);
        } else {
            error_log("Found " . count($favorites) . " favorites for student ID: " . $user_id);
        }
        
        // Get student demands
        try {
            $stmt = $conn->prepare("
                SELECT sd.*, c.city_name
                FROM student_demand sd
                JOIN city c ON sd.city_id = c.city_id
                WHERE sd.student_id = ?
                ORDER BY sd.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $student_demands = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching student demands: " . $e->getMessage());
            $student_demands = [];
        }
        
    } else { // owner
        $stmt = $conn->prepare("
            SELECT o.*, p.picture_url 
            FROM owner o
            LEFT JOIN picture p ON p.owner_id = o.owner_id
            WHERE o.owner_id = ?
        ");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get owner's properties
        $stmt = $conn->prepare("
            SELECT h.*, c.city_name, pt.proprety_type_name,
                   (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
                    JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
                    WHERE hpp.house_id = h.house_id LIMIT 1) as image_url
            FROM house h
            JOIN city c ON h.city_id = c.city_id
            JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
            WHERE h.owner_id = ?
        ");
        $stmt->execute([$user_id]);
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get all cities for dropdown
    $stmt = $conn->prepare("SELECT city_id, city_name FROM city");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all property types for dropdown
    $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type");
    $stmt->execute();
    $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching profile data: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email)) {
        $_SESSION['error'] = "Name and email are required";
    } else {
        try {
            // Verify current password if changing password
            if (!empty($new_password)) {
                if ($_SESSION['user_type'] == 'student') {
                    $stmt = $conn->prepare("SELECT student_password FROM student WHERE student_id = ?");
                } else {
                    $stmt = $conn->prepare("SELECT owner_password FROM owner WHERE owner_id = ?");
                }
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stored_password = $result[$_SESSION['user_type'].'_password'];
                
                if (!password_verify($current_password, $stored_password)) {
                    $_SESSION['error'] = "Current password is incorrect";
                    header("Location: Profile.php");
                    exit();
                }
                
                if ($new_password !== $confirm_password) {
                    $_SESSION['error'] = "New passwords do not match";
                    header("Location: Profile.php");
                    exit();
                }
                
                if (strlen($new_password) < 6) {
                    $_SESSION['error'] = "New password must be at least 6 characters";
                    header("Location: Profile.php");
                    exit();
                }
                
                $password_to_update = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            // Handle profile picture upload
            $filepath = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array(strtolower($file_extension), $allowed_extensions)) {
                    $filename = uniqid() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath);
                    
                    // Update picture in database
                    if ($_SESSION['user_type'] == 'student') {
                        $stmt = $conn->prepare("SELECT picture_id FROM student WHERE student_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $picture_id = $stmt->fetch(PDO::FETCH_ASSOC)['picture_id'];
                        
                        if ($picture_id) {
                            $stmt = $conn->prepare("UPDATE picture SET picture_url = ? WHERE picture_id = ?");
                            $stmt->execute([$filepath, $picture_id]);
                        } else {
                            // Create new picture record
                            $stmt = $conn->prepare("SELECT MAX(picture_id) as max_id FROM picture");
                            $stmt->execute();
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            $picture_id = ($result['max_id'] ?? 0) + 1;
                            
                            $stmt = $conn->prepare("INSERT INTO picture (picture_id, picture_url) VALUES (?, ?)");
                            $stmt->execute([$picture_id, $filepath]);
                            
                            // Update student with new picture_id
                            $stmt = $conn->prepare("UPDATE student SET picture_id = ? WHERE student_id = ?");
                            $stmt->execute([$picture_id, $_SESSION['user_id']]);
                        }
                    } else {
                        // Check if owner already has a picture
                        $stmt = $conn->prepare("SELECT picture_id FROM picture WHERE owner_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $picture = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($picture) {
                            $stmt = $conn->prepare("UPDATE picture SET picture_url = ? WHERE picture_id = ?");
                            $stmt->execute([$filepath, $picture['picture_id']]);
                        } else {
                            // Get next picture_id
                            $stmt = $conn->prepare("SELECT MAX(picture_id) as max_id FROM picture");
                            $stmt->execute();
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            $picture_id = ($result['max_id'] ?? 0) + 1;
                            
                            $stmt = $conn->prepare("INSERT INTO picture (picture_id, owner_id, picture_url) VALUES (?, ?, ?)");
                            $stmt->execute([$picture_id, $_SESSION['user_id'], $filepath]);
                        }
                    }
                }
            }
            
            // Update user data
            if ($_SESSION['user_type'] == 'student') {
                $sql = "UPDATE student SET student_name = ?, student_email = ?";
                $params = [$name, $email];
                
                if (!empty($new_password)) {
                    $sql .= ", student_password = ?";
                    $params[] = $password_to_update;
                }
                
                $sql .= " WHERE student_id = ?";
                $params[] = $_SESSION['user_id'];
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "UPDATE owner SET owner_name = ?, owner_email = ?";
                $params = [$name, $email];
                
                if (!empty($new_password)) {
                    $sql .= ", owner_password = ?";
                    $params[] = $password_to_update;
                }
                
                $sql .= " WHERE owner_id = ?";
                $params[] = $_SESSION['user_id'];
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }
            
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['success'] = "Profile updated successfully";
            header("Location: Profile.php");
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle property submission (for owners)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_property']) && $user_type == 'owner') {
    $title = trim($_POST['title']);
    $price = trim($_POST['price']);
    $location = trim($_POST['location']);
    $city_id = $_POST['city'];
    $property_type = $_POST['property_type'];
    $bedrooms = $_POST['bedrooms'];
    $bathrooms = $_POST['bathrooms'];
    $description = trim($_POST['description']);
    
    if (empty($title) || empty($price) || empty($location) || empty($city_id) || empty($property_type)) {
        $_SESSION['error'] = "Please fill in all required fields";
    } else {
        try {
            // Get next house_id
            $stmt = $conn->prepare("SELECT MAX(house_id) as max_id FROM house");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $house_id = ($result['max_id'] ?? 0) + 1;
            
            // Insert new property
            $stmt = $conn->prepare("
                INSERT INTO house 
                (house_id, city_id, proprety_type_id, owner_id, house_title, house_price, house_location, house_badroom, house_bathroom, house_description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $house_id, 
                $city_id, 
                $property_type, 
                $_SESSION['user_id'], 
                $title, 
                $price, 
                $location, 
                $bedrooms, 
                $bathrooms, 
                $description
            ]);
            
            // Handle property images
            if (isset($_FILES['property_images']) && !empty($_FILES['property_images']['name'][0])) {
                $upload_dir = 'uploads/properties/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Get next proprety_pictures_id
                $stmt = $conn->prepare("SELECT MAX(proprety_pictures_id) as max_id FROM proprety_pictures");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $picture_id = ($result['max_id'] ?? 0) + 1;
                
                foreach ($_FILES['property_images']['name'] as $key => $name) {
                    if ($_FILES['property_images']['error'][$key] == 0) {
                        $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                        
                        if (in_array(strtolower($file_extension), $allowed_extensions)) {
                            $filename = uniqid() . '.' . $file_extension;
                            $filepath = $upload_dir . $filename;
                            move_uploaded_file($_FILES['property_images']['tmp_name'][$key], $filepath);
                            
                            // Insert property picture
                            $stmt = $conn->prepare("INSERT INTO proprety_pictures (proprety_pictures_id, proprety_pictures_name) VALUES (?, ?)");
                            $stmt->execute([$picture_id, $filepath]);
                            
                            // Link property to picture
                            $stmt = $conn->prepare("INSERT INTO house_property_pictures (house_id, proprety_pictures_id, house_property_pictures_date) VALUES (?, ?, NOW())");
                            $stmt->execute([$house_id, $picture_id]);
                            
                            $picture_id++;
                        }
                    }
                }
            }
            
            $_SESSION['success'] = "Property added successfully";
            header("Location: Profile.php");
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding property: " . $e->getMessage();
        }
    }
}

// Get user name and profile picture for display
$user_name = $user_data[$user_type . '_name'] ?? $_SESSION['user_name'] ?? 'User';
$user_email = $user_data[$user_type . '_email'] ?? $_SESSION['user_email'] ?? '';
$profile_picture = $user_data['picture_url'] ?? 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Manage your UniHousing profile, listings, and account settings">
    <meta name="theme-color" content="#2563eb">
    <title>My Profile - UniHousing</title>
    <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="./profile-responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="demands-table.css">
    <!-- <link rel="stylesheet" href="chat-interface.css"> -->
    <link rel="stylesheet" href="./notifications.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./logo-animation.css">
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/fixed-chat.js"></script>
    <!-- Mobile optimization -->
    <link rel="apple-touch-icon" href="./images/logopro.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <style>
        .fa-star:before {
            content: "\f005";
            position: static;
            display: inline-block;
            position: static !important;
            color: #FFD700 !important;
            font-size: 1.75rem !important;
        }
    </style>
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
                        <li><a href="home.php">Home</a></li>
                        <li><a href="offers.php">Offers</a></li>
                        <li><a href="demands.php">Demands</a></li>
                    </ul>
                </nav>
                <div class="auth-buttons">
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
                    <a href="Profile.php" class="btn btn-primary">My Profile</a>
                    <a href="logout.php" class="btn btn-outline">Logout</a>
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
                            <li><a href="home.php">Home</a></li>
                            <li><a href="offers.php">Offers</a></li>
                            <li><a href="demands.php">Demands</a></li>
                            <li><a href="about.php">About</a></li>
                            <li><a href="contact.php">Contact</a></li>
                        </ul>
                    </nav>
                    <div class="mobile-menu-auth">
                        <a href="Profile.php" class="btn btn-primary active">My Profile</a>
                        <a href="logout.php" class="btn btn-outline">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Profile Section -->
    <section class="profile-section">
        <div class="container">
            <?php
            // Display error or success messages
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
                unset($_SESSION['success']);
            }
            ?>
            <div class="profile-grid">
                <!-- Sidebar Navigation -->
                <div class="profile-sidebar">
                    <div class="user-info">
                        <div class="user-avatar">
                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Avatar">
                            <button class="edit-avatar" onclick="document.getElementById('avatar-upload').click()">
                                <i class="fas fa-camera"></i>
                            </button>
                            <form id="avatar-form" action="Profile.php" method="POST" enctype="multipart/form-data" style="display:none;">
                                <input type="file" id="avatar-upload" name="avatar" onchange="document.getElementById('avatar-form').submit()">
                                <input type="hidden" name="update_profile" value="1">
                                <input type="hidden" name="name" value="<?php echo htmlspecialchars($user_name); ?>">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_email); ?>">
                            </form>
                        </div>
                        <h2><?php echo htmlspecialchars($user_name); ?></h2>
                        <p class="user-type"><?php echo ucfirst(htmlspecialchars($user_type)); ?></p>
                        <div class="user-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <span>4.5 (24 reviews)</span>
                        </div>
                    </div>
                    <nav class="profile-nav">
                        <ul>
                            <li class="active">
                                <a href="#dashboard-stats" data-section="dashboard">
                                    <i class="fas fa-home"></i>
                                    Dashboard
                                </a>
                            </li>
                            <?php if ($user_type == 'owner'): ?>
                            <li>
                                <a href="#my-listings" data-section="my-listings">
                                    <i class="fas fa-list"></i>
                                    My Listings
                                </a>
                            </li>
                            <?php else: ?>
                            <li>
                                <a href="#favorites" data-section="favorites">
                                    <i class="fas fa-heart"></i>
                                    Favorites
                                </a>
                            </li>
                            <li>
                                <a href="#my-demands" data-section="my-demands">
                                    <i class="fas fa-bullhorn"></i>
                                    My Demands
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a href="#messages" data-section="messages">
                                    <i class="fas fa-envelope"></i>
                                    Messages
                                </a>
                            </li>
                            <li>
                                <a href="#settings" data-section="settings">
                                    <i class="fas fa-cog"></i>
                                    Settings
                                </a>
                            </li>
                        
                           
                        </ul>
                    </nav>
                </div>

                <!-- Main Content -->
                <div class="profile-content">
                    <!-- Dashboard Section -->
                    <div class="profile-section active" id="dashboard">
                        <h1>Dashboard</h1>
                        <div class="dashboard-stats">
                            <!-- Active Listings Card -->
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>Active Listings</h3>
                                    <?php
                                    // Get the number of active listings
                                    if ($user_type == 'owner') {
                                        $active_listings = isset($listings) ? count($listings) : '0';
                                    } else {
                                        $active_listings = isset($favorites) ? count($favorites) : '0';
                                    }
                                    ?>
                                    <p class="stat-number"><?php echo $active_listings; ?></p>
                                </div>
                            </div>
                            
                            <?php if ($user_type == 'student'): ?>
                            <!-- Active Demands Card -->
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-bullhorn"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>Active Demands</h3>
                                    <p class="stat-number"><?php echo isset($student_demands) ? count($student_demands) : '0'; ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Total Views Card -->
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>Total Views</h3>
                                    <p class="stat-number">1,234</p>
                                </div>
                            </div>
                            
                            <!-- New Messages Card -->
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>New Messages</h3>
                                    <p class="stat-number">3</p>
                                </div>
                            </div>
                            
                            <!-- Average Rating Card -->
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>Average Rating</h3>
                                    <p class="stat-number">4.5</p>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-row">
                            <!-- Recent Activity Section -->
                            <div class="recent-activity">
                                <h2>Recent Activity</h2>
                                <div class="activity-list">
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-eye"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p>Your listing "Modern Studio Apartment" received 25 views today</p>
                                            <span class="activity-time">2 hours ago</span>
                                        </div>
                                    </div>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p>New message from Sarah Johnson regarding "Cozy Studio Near Campus"</p>
                                            <span class="activity-time">5 hours ago</span>
                                        </div>
                                    </div>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p>You received a new 5-star review for "Modern Studio Apartment"</p>
                                            <span class="activity-time">1 day ago</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Recent Messages Section -->
                            <div class="recent-messages">
                                <h2>Recent Messages</h2>
                                <?php
                                // Get recent messages for the current user (limit to 3)
                                try {
                                    if ($user_type == 'student') {
                                        // For students, get conversations with owners
                                        $stmt = $conn->prepare("SELECT DISTINCT o.owner_id, o.owner_name, p.picture_url,
                                                              (SELECT MAX(m.message_date) FROM messages m 
                                                               WHERE m.owner_id = o.owner_id AND m.student_id = ?) as last_message_date,
                                                              (SELECT m.message_text FROM messages m 
                                                               WHERE m.owner_id = o.owner_id AND m.student_id = ? 
                                                               ORDER BY m.message_date DESC LIMIT 1) as last_message,
                                                              (SELECT COUNT(*) FROM messages m 
                                                               WHERE m.owner_id = o.owner_id AND m.student_id = ? 
                                                               AND m.sender_type = 'owner' AND m.is_read = FALSE) as unread_count
                                                              FROM messages m
                                                              JOIN owner o ON m.owner_id = o.owner_id
                                                              LEFT JOIN picture p ON p.owner_id = o.owner_id
                                                              WHERE m.student_id = ?
                                                              GROUP BY o.owner_id
                                                              ORDER BY last_message_date DESC
                                                              LIMIT 3");
                                        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
                                    } else {
                                        // For owners, get conversations with students
                                        $stmt = $conn->prepare("SELECT DISTINCT s.student_id, s.student_name, p.picture_url,
                                                              (SELECT MAX(m.message_date) FROM messages m 
                                                               WHERE m.student_id = s.student_id AND m.owner_id = ?) as last_message_date,
                                                              (SELECT m.message_text FROM messages m 
                                                               WHERE m.student_id = s.student_id AND m.owner_id = ? 
                                                               ORDER BY m.message_date DESC LIMIT 1) as last_message,
                                                              (SELECT COUNT(*) FROM messages m 
                                                               WHERE m.student_id = s.student_id AND m.owner_id = ? 
                                                               AND m.sender_type = 'student' AND m.is_read = FALSE) as unread_count
                                                              FROM messages m
                                                              JOIN student s ON m.student_id = s.student_id
                                                              LEFT JOIN picture p ON s.picture_id = p.picture_id
                                                              WHERE m.owner_id = ?
                                                              GROUP BY s.student_id
                                                              ORDER BY last_message_date DESC
                                                              LIMIT 3");
                                        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
                                    }
                                    $recent_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) {
                                    error_log("Error fetching recent conversations: " . $e->getMessage());
                                    $recent_conversations = [];
                                }
                                ?>
                                
                                <div class="message-preview-list">
                                    <?php if (!empty($recent_conversations)): ?>
                                        <?php foreach ($recent_conversations as $conversation): ?>
                                            <?php 
                                            $conversation_id = $user_type == 'student' ? $conversation['owner_id'] : $conversation['student_id'];
                                            $name = $user_type == 'student' ? $conversation['owner_name'] : $conversation['student_name'];
                                            $profile_pic = !empty($conversation['picture_url']) ? $conversation['picture_url'] : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';
                                            $last_message = !empty($conversation['last_message']) ? htmlspecialchars(substr($conversation['last_message'], 0, 40)) . (strlen($conversation['last_message']) > 40 ? '...' : '') : 'No messages yet';
                                            $unread_count = $conversation['unread_count'] ?? 0;
                                            ?>
                                            <div class="message-preview-item" data-owner-id="<?php echo $user_type == 'student' ? $conversation_id : $user_id; ?>" data-student-id="<?php echo $user_type == 'owner' ? $conversation_id : $user_id; ?>">
                                                <div class="avatar">
                                                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                                                    <?php if ($unread_count > 0): ?>
                                                        <span class="unread-badge"><?php echo $unread_count; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="message-preview-content">
                                                    <div class="message-preview-header">
                                                        <h4><?php echo htmlspecialchars($name); ?></h4>
                                                        <span class="message-time"><?php echo date('g:i A', strtotime($conversation['last_message_date'])); ?></span>
                                                    </div>
                                                    <p class="message-text"><?php echo $last_message; ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php 
                                        $first_conversation = $recent_conversations[0];
                                        $chat_owner_id = $user_type == 'student' ? $first_conversation['owner_id'] : $user_id;
                                        $chat_student_id = $user_type == 'owner' ? $first_conversation['student_id'] : $user_id;
                                        ?>
                                        <a href="chat.php?owner_id=<?php echo $chat_owner_id; ?>&student_id=<?php echo $chat_student_id; ?>" class="btn btn-outline btn-sm">View All Messages</a>
                                    <?php else: ?>
                                        <div class="no-messages">
                                            <div class="empty-state small">
                                                <i class="fas fa-comments"></i>
                                                <h3>No Messages Yet</h3>
                                                <p>Your conversations will appear here.</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- My Listings Section -->
                    <?php if ($user_type == 'owner'): ?>
                    <div class="profile-section" id="my-listings">
                        <div class="section-header">
                            <h1>My Listings</h1>
                            <div class="button-group">
                                <a href="create_offer.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i>
                                    Create Offer
                                </a>
                                <a href="offers.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Add New Listing
                                </a>
                            </div>
                        </div>
                        <div class="listings-grid">
                            <?php if (isset($listings) && !empty($listings)): ?>
                                <?php foreach ($listings as $property): ?>
                                <!-- Property Card -->
                                <div class="property-card">
                                    <div class="property-image">
                                        <?php if (!empty($property['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($property['image_url']); ?>" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                        <?php else: ?>
                                        <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="property-details">
                                        <h3><?php echo htmlspecialchars($property['house_title']); ?></h3>
                                        <p class="property-price">$<?php echo htmlspecialchars($property['house_price']); ?>/month</p>
                                        <p class="property-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['house_location']); ?>, <?php echo htmlspecialchars($property['city_name']); ?></p>
                                        <div class="property-features">
                                            <span><i class="fas fa-bed"></i> <?php echo htmlspecialchars($property['house_badroom']); ?> Bed<?php echo $property['house_badroom'] > 1 ? 's' : ''; ?></span>
                                            <span><i class="fas fa-bath"></i> <?php echo htmlspecialchars($property['house_bathroom']); ?> Bath<?php echo $property['house_bathroom'] > 1 ? 's' : ''; ?></span>
                                            <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($property['proprety_type_name']); ?></span>
                                        </div>
                                        <div class="property-actions">
                                            <a href="edit_property.php?id=<?php echo $property['house_id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                                            <a href="delete_property.php?id=<?php echo $property['house_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this property?');">Delete</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-home"></i>
                                    <h3>No Properties Listed</h3>
                                    <p>You haven't listed any properties yet. Add your first property to start attracting student tenants.</p>
                                    <a href="#add-property" class="btn btn-primary" onclick="document.getElementById('add-property').style.display = 'block'; return false;">Add Your First Property</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Favorites Section -->
                    <?php if ($user_type == 'student'): ?>
                    <div class="profile-section" id="favorites">
                        <h1>My Favorites</h1>
                        <div class="favorites-grid">
                            <?php if (isset($favorites) && !empty($favorites)): ?>
                                <?php foreach ($favorites as $property): ?>
                                <!-- Favorite Property Card -->
                                <div class="property-card">
                                    <div class="property-image">
                                        <?php if (!empty($property['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($property['image_url']); ?>" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                        <?php else: ?>
                                        <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                        <?php endif; ?>
                                        <div class="property-badge">
                                            <span><i class="fas fa-heart"></i> Favorite</span>
                                        </div>
                                    </div>
                                    <div class="property-details">
                                        <h3><?php echo htmlspecialchars($property['house_title']); ?></h3>
                                        <p class="property-price">$<?php echo htmlspecialchars($property['house_price']); ?>/month</p>
                                        <p class="property-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['house_location']); ?>, <?php echo htmlspecialchars($property['city_name']); ?></p>
                                        <div class="property-features">
                                            <span><i class="fas fa-bed"></i> <?php echo htmlspecialchars($property['house_badroom']); ?> Bed<?php echo $property['house_badroom'] > 1 ? 's' : ''; ?></span>
                                            <span><i class="fas fa-bath"></i> <?php echo htmlspecialchars($property['house_bathroom']); ?> Bath<?php echo $property['house_bathroom'] > 1 ? 's' : ''; ?></span>
                                            <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($property['proprety_type_name']); ?></span>
                                        </div>
                                        <div class="property-actions">
                                            <form method="post" action="view_property.php" style="display:inline;">
    <input type="hidden" name="property_id" value="<?php echo htmlspecialchars($property['house_id']); ?>">
    <button type="submit" class="btn btn-sm btn-outline">View Details</button>
</form>
                                            <a href="remove_favorite.php?id=<?php echo $property['house_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this property from favorites?');">Remove</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-heart"></i>
                                    <h3>No Favorites Yet</h3>
                                    <p>You haven't saved any properties to your favorites yet.</p>
                                    <a href="offers.php" class="btn btn-primary">Browse Properties</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Add Property Form (for owners) -->
                    <?php if ($user_type == 'owner'): ?>
                    <div class="profile-section" id="add-property" style="display: none;">
                        <h1>Add New Property</h1>
                        <form action="Profile.php" method="POST" enctype="multipart/form-data" class="property-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="title">Property Title</label>
                                    <input type="text" id="title" name="title" required placeholder="e.g. Modern Studio Apartment Near Campus">
                                </div>
                                <div class="form-group">
                                    <label for="price">Monthly Rent ($)</label>
                                    <input type="number" id="price" name="price" required placeholder="e.g. 800">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="location">Address</label>
                                    <input type="text" id="location" name="location" required placeholder="e.g. 123 University Ave">
                                </div>
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <select id="city" name="city" required>
                                        <option value="">Select City</option>
                                        <?php
                                        if (isset($cities) && !empty($cities)) {
                                            foreach ($cities as $city) {
                                                echo '<option value="' . $city['city_id'] . '">' . htmlspecialchars($city['city_name']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="property_type">Property Type</label>
                                    <select id="property_type" name="property_type" required>
                                        <option value="">Select Property Type</option>
                                        <?php
                                        if (isset($property_types) && !empty($property_types)) {
                                            foreach ($property_types as $type) {
                                                echo '<option value="' . $type['proprety_type_id'] . '">' . htmlspecialchars($type['proprety_type_name']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="bedrooms">Bedrooms</label>
                                    <select id="bedrooms" name="bedrooms" required>
                                        <option value="0">Studio</option>
                                        <option value="1">1 Bedroom</option>
                                        <option value="2">2 Bedrooms</option>
                                        <option value="3">3 Bedrooms</option>
                                        <option value="4">4 Bedrooms</option>
                                        <option value="5">5+ Bedrooms</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="bathrooms">Bathrooms</label>
                                    <select id="bathrooms" name="bathrooms" required>
                                        <option value="1">1 Bathroom</option>
                                        <option value="2">2 Bathrooms</option>
                                        <option value="3">3 Bathrooms</option>
                                        <option value="4">4+ Bathrooms</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group" style="flex: 100%;">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="5" required placeholder="Describe your property, including amenities, distance to campus, etc."></textarea>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group" style="flex: 100%;">
                                    <label for="property_images">Property Images</label>
                                    <input type="file" id="property_images" name="property_images[]" multiple accept="image/*">
                                    <p class="form-help">Upload up to 5 images. Recommended size: 1200x800 pixels.</p>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <input type="hidden" name="submit_property" value="1">
                                <button type="button" class="btn btn-outline" onclick="hideAddPropertyForm()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit Property</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Student Demands Section -->
                    <?php if ($user_type == 'student'): ?>
                    <div class="profile-section" id="my-demands">
                        <div class="section-header">
                            <h1>My Housing Demands</h1>
                            <div class="button-group">
                                <a href="demands.php#post-demand" class="btn btn-primary">
                                    <i class="fas fa-plus-circle"></i>
                                    Post New Demand
                                </a>
                            </div>
                        </div>
                        
                        <?php if (isset($_SESSION['demand_success'])): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($_SESSION['demand_success']); ?>
                            </div>
                            <?php unset($_SESSION['demand_success']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['demand_error'])): ?>
                            <div class="alert alert-error">
                                <?php echo htmlspecialchars($_SESSION['demand_error']); ?>
                            </div>
                            <?php unset($_SESSION['demand_error']); ?>
                        <?php endif; ?>
                        
                        <div class="demands-list">
                            <?php if (isset($student_demands) && !empty($student_demands)): ?>
                                <table class="demands-table">
                                    <thead>
                                        <tr>
                                            <th>City</th>
                                            <th>Location</th>
                                            <th>Property Type</th>
                                            <th>Budget</th>
                                            <th>Move-in Date</th>
                                            <th>Posted On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_demands as $demand): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($demand['city_name']); ?></td>
                                            <td><?php echo htmlspecialchars($demand['location']); ?></td>
                                            <td><?php echo htmlspecialchars($demand['property_type']); ?></td>
                                            <td>$<?php echo htmlspecialchars($demand['budget']); ?>/mo</td>
                                            <td><?php echo date('M j, Y', strtotime($demand['move_in_date'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($demand['created_at'])); ?></td>
                                            <td class="action-buttons">
                                                <a href="edit_demand.php?id=<?php echo $demand['demand_id']; ?>" class="btn btn-sm btn-outline">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="delete_demand.php?id=<?php echo $demand['demand_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this demand?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-bullhorn"></i>
                                    <h3>No Housing Demands Posted</h3>
                                    <p>You haven't posted any housing requirements yet. Post your first demand to find your perfect accommodation.</p>
                                    <a href="demands.php#post-demand" class="btn btn-primary">Post Your First Demand</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Messages Section -->
                    <div class="profile-section" id="messages">
                        <h1>Messages</h1>
                        <?php
                        // Get active conversations for the current user
                        try {
                            if ($user_type == 'student') {
                                // For students, get conversations with owners
                                $stmt = $conn->prepare("SELECT DISTINCT o.owner_id, o.owner_name, p.picture_url,
                                                      (SELECT MAX(m.message_date) FROM messages m 
                                                       WHERE m.owner_id = o.owner_id AND m.student_id = ?) as last_message_date,
                                                      (SELECT m.message_text FROM messages m 
                                                       WHERE m.owner_id = o.owner_id AND m.student_id = ? 
                                                       ORDER BY m.message_date DESC LIMIT 1) as last_message,
                                                      (SELECT COUNT(*) FROM messages m 
                                                       WHERE m.owner_id = o.owner_id AND m.student_id = ? 
                                                       AND m.sender_type = 'owner' AND m.is_read = FALSE) as unread_count
                                                      FROM messages m
                                                      JOIN owner o ON m.owner_id = o.owner_id
                                                      LEFT JOIN picture p ON p.owner_id = o.owner_id
                                                      WHERE m.student_id = ?
                                                      GROUP BY o.owner_id
                                                      ORDER BY last_message_date DESC");
                                $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
                            } else {
                                // For owners, get conversations with students
                                $stmt = $conn->prepare("SELECT DISTINCT s.student_id, s.student_name, p.picture_url,
                                                      (SELECT MAX(m.message_date) FROM messages m 
                                                       WHERE m.student_id = s.student_id AND m.owner_id = ?) as last_message_date,
                                                      (SELECT m.message_text FROM messages m 
                                                       WHERE m.student_id = s.student_id AND m.owner_id = ? 
                                                       ORDER BY m.message_date DESC LIMIT 1) as last_message,
                                                      (SELECT COUNT(*) FROM messages m 
                                                       WHERE m.student_id = s.student_id AND m.owner_id = ? 
                                                       AND m.sender_type = 'student' AND m.is_read = FALSE) as unread_count
                                                      FROM messages m
                                                      JOIN student s ON m.student_id = s.student_id
                                                      LEFT JOIN picture p ON s.picture_id = p.picture_id
                                                      WHERE m.owner_id = ?
                                                      GROUP BY s.student_id
                                                      ORDER BY last_message_date DESC");
                                $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
                            }
                            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            error_log("Error fetching conversations: " . $e->getMessage());
                            $conversations = [];
                        }
                        
                        // Get messages for the first conversation if any exist
                        $active_conversation = null;
                        $messages = [];
                        
                        if (!empty($conversations)) {
                            $active_conversation = $conversations[0];
                            
                            try {
                                if ($user_type == 'student') {
                                    $owner_id = $active_conversation['owner_id'];
                                    $student_id = $user_id;
                                    
                                    // Get owner details
                                    $stmt = $conn->prepare("SELECT o.*, p.picture_url 
                                                          FROM owner o 
                                                          LEFT JOIN picture p ON p.owner_id = o.owner_id 
                                                          WHERE o.owner_id = ?");
                                    $stmt->execute([$owner_id]);
                                    $conversation_partner = $stmt->fetch(PDO::FETCH_ASSOC);
                                } else {
                                    $owner_id = $user_id;
                                    $student_id = $active_conversation['student_id'];
                                    
                                    // Get student details
                                    $stmt = $conn->prepare("SELECT s.*, p.picture_url 
                                                          FROM student s 
                                                          LEFT JOIN picture p ON s.picture_id = p.picture_id 
                                                          WHERE s.student_id = ?");
                                    $stmt->execute([$student_id]);
                                    $conversation_partner = $stmt->fetch(PDO::FETCH_ASSOC);
                                }
                                
                                // Get messages for this conversation
                                $stmt = $conn->prepare("SELECT m.*, 
                                                      DATE_FORMAT(m.message_date, '%Y-%m-%d %H:%i:%s') as formatted_date,
                                                      CASE 
                                                          WHEN m.sender_type = 'student' THEN 
                                                              (SELECT p.picture_url FROM student s LEFT JOIN picture p ON s.picture_id = p.picture_id WHERE s.student_id = ?)
                                                          ELSE 
                                                              (SELECT p.picture_url FROM picture p WHERE p.owner_id = ?)
                                                      END as profile_pic
                                                      FROM messages m 
                                                      WHERE m.owner_id = ? AND m.student_id = ? 
                                                      ORDER BY m.message_date ASC");
                                $stmt->execute([$student_id, $owner_id, $owner_id, $student_id]);
                                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Mark messages as read
                                if ($user_type == 'student') {
                                    $update_stmt = $conn->prepare("UPDATE messages 
                                                                SET is_read = TRUE 
                                                                WHERE owner_id = ? AND student_id = ? 
                                                                AND sender_type = 'owner' AND is_read = FALSE");
                                    $update_stmt->execute([$owner_id, $student_id]);
                                } else {
                                    $update_stmt = $conn->prepare("UPDATE messages 
                                                                SET is_read = TRUE 
                                                                WHERE owner_id = ? AND student_id = ? 
                                                                AND sender_type = 'student' AND is_read = FALSE");
                                    $update_stmt->execute([$owner_id, $student_id]);
                                }
                            } catch (PDOException $e) {
                                error_log("Error fetching messages: " . $e->getMessage());
                            }
                        }
                        ?>
                        
                        <div class="messages-container" data-owner-id="<?php echo $user_type == 'student' && !empty($active_conversation) ? $active_conversation['owner_id'] : $user_id; ?>" data-student-id="<?php echo $user_type == 'owner' && !empty($active_conversation) ? $active_conversation['student_id'] : $user_id; ?>" data-user-type="<?php echo $user_type; ?>">
                            <div class="messages-sidebar">
                                <div class="search-messages">
                                    <input type="text" placeholder="Search messages..." id="search-messages-input">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="message-list">
                                    <?php if (!empty($conversations)): ?>
                                        <?php foreach ($conversations as $index => $conversation): ?>
                                            <?php 
                                            $conversation_id = $user_type == 'student' ? $conversation['owner_id'] : $conversation['student_id'];
                                            $name = $user_type == 'student' ? $conversation['owner_name'] : $conversation['student_name'];
                                            $profile_pic = !empty($conversation['picture_url']) ? $conversation['picture_url'] : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';
                                            $last_message = !empty($conversation['last_message']) ? htmlspecialchars(substr($conversation['last_message'], 0, 40)) . (strlen($conversation['last_message']) > 40 ? '...' : '') : 'No messages yet';
                                            $unread_count = $conversation['unread_count'] ?? 0;
                                            ?>
                                            <div class="message-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                 data-conversation-id="<?php echo $conversation_id; ?>"
                                                 data-owner-id="<?php echo $user_type == 'student' ? $conversation_id : $user_id; ?>"
                                                 data-student-id="<?php echo $user_type == 'owner' ? $conversation_id : $user_id; ?>">
                                                <div class="avatar">
                                                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                                                </div>
                                                <div class="message-info">
                                                    <div class="message-header">
                                                        <h4><?php echo htmlspecialchars($name); ?></h4>
                                                        <span class="message-time"><?php echo date('g:i A', strtotime($conversation['last_message_date'])); ?></span>
                                                    </div>
                                                    <p class="message-preview"><?php echo $last_message; ?></p>
                                                    <?php if ($unread_count > 0): ?>
                                                        <span class="unread-badge"><?php echo $unread_count; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-messages">
                                            <p>No conversations yet.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="message-content">
                                <?php if (!empty($active_conversation) && !empty($conversation_partner)): ?>
                                    <div class="message-header">
                                        <div class="user-info">
                                            <div class="avatar">
                                                <?php 
                                                $partner_pic = !empty($conversation_partner['picture_url']) ? $conversation_partner['picture_url'] : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';
                                                $partner_name = $user_type == 'student' ? $conversation_partner['owner_name'] : $conversation_partner['student_name'];
                                                ?>
                                                <img src="<?php echo htmlspecialchars($partner_pic); ?>" alt="<?php echo htmlspecialchars($partner_name); ?>">
                                            </div>
                                            <div>
                                                <h3><?php echo htmlspecialchars($partner_name); ?></h3>
                                                <p><?php echo $user_type == 'student' ? 'Property Owner' : 'Student'; ?></p>
                                            </div>
                                        </div>
                                        <div class="message-actions">
                                            <button class="btn btn-outline" title="Contact Info">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="message-thread">
                                        <?php if (!empty($messages)): ?>
                                            <?php foreach ($messages as $message): ?>
                                                <?php 
                                                $is_sent = $message['sender_type'] === $user_type;
                                                $profile_pic = !empty($message['profile_pic']) ? $message['profile_pic'] : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';
                                                ?>
                                                <div class="message-bubble <?php echo $is_sent ? 'sent' : 'received'; ?>" data-message-id="<?php echo $message['message_id']; ?>">
                                                    <div class="avatar">
                                                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="<?php echo $message['sender_type']; ?>">
                                                    </div>
                                                    <div class="message-content-wrapper">
                                                        <p><?php echo nl2br(htmlspecialchars($message['message_text'])); ?></p>
                                                        <div class="message-time"><?php echo date('g:i A', strtotime($message['formatted_date'])); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="no-messages-yet">
                                                <div class="empty-state">
                                                    <i class="fas fa-comments"></i>
                                                    <h3>No messages yet</h3>
                                                    <p>Start the conversation by sending a message below.</p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form id="message-form" class="message-input">
                                        <input type="text" id="message-input" placeholder="Type your message...">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="no-chat-selected">
                                        <div class="empty-state">
                                            <i class="fas fa-comments"></i>
                                            <h3>No conversation selected</h3>
                                            <p>Select a conversation from the sidebar or start a new one.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Section -->
                    <div class="profile-section" id="settings">
                        <h1>Account Settings</h1>
                        <div class="settings-grid">
                            <div class="settings-section">
                                <h2>Profile Information</h2>
                                <form class="settings-form" action="Profile.php" method="POST" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="name">Full Name</label>
                                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="avatar">Profile Picture</label>
                                        <input type="file" id="avatar" name="avatar" accept="image/*">
                                        <p class="form-help">Upload a square image for best results. Max size: 2MB.</p>
                                    </div>
                                    <input type="hidden" name="update_profile" value="1">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                            
                            <div class="settings-section">
                                <h2>Change Password</h2>
                                <form class="settings-form" action="Profile.php" method="POST">
                                    <div class="form-group">
                                        <label for="current-password">Current Password</label>
                                        <input type="password" id="current-password" name="current_password" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="new-password">New Password</label>
                                        <input type="password" id="new-password" name="new_password" required>
                                        <p class="form-help">Password must be at least 6 characters long.</p>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm-password">Confirm New Password</label>
                                        <input type="password" id="confirm-password" name="confirm_password" required>
                                    </div>
                                    <input type="hidden" name="update_profile" value="1">
                                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($user_name); ?>">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_email); ?>">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </form>
                            </div>
                            

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript for Message Preview Items -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Make message preview items clickable
            const messagePreviewItems = document.querySelectorAll('.message-preview-item');
            messagePreviewItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Navigate to messages section
                    const messagesNavItem = document.querySelector('.nav-item[data-section="messages"]');
                    if (messagesNavItem) {
                        messagesNavItem.click();
                        
                        // Get owner_id and student_id
                        const ownerId = this.dataset.ownerId;
                        const studentId = this.dataset.studentId;
                        
                        // Find and activate the corresponding conversation in the messages section
                        setTimeout(() => {
                            const messageItems = document.querySelectorAll('.message-item');
                            messageItems.forEach(msgItem => {
                                if (msgItem.dataset.ownerId === ownerId && msgItem.dataset.studentId === studentId) {
                                    // Simulate click on this conversation
                                    msgItem.click();
                                }
                            });
                        }, 100); // Small delay to ensure messages section is loaded
                    }
                });
            });
            
            // Initialize chat in messages section if it exists
            if (document.querySelector('.messages-container')) {
                const messagesContainer = document.querySelector('.messages-container');
                const ownerId = messagesContainer.dataset.ownerId;
                const studentId = messagesContainer.dataset.studentId;
                const userType = messagesContainer.dataset.userType;
                
                if (ownerId && studentId) {
                    initChat({
                        ownerId: ownerId,
                        studentId: studentId,
                        userType: userType,
                        messageThreadSelector: '.message-thread',
                        messageFormSelector: '#message-form',
                        messageInputSelector: '#message-input'
                    });
                    
                    // Handle conversation switching
                    const messageItems = document.querySelectorAll('.message-item');
                    messageItems.forEach(item => {
                        item.addEventListener('click', function() {
                            // Remove active class from all items
                            messageItems.forEach(i => i.classList.remove('active'));
                            // Add active class to clicked item
                            this.classList.add('active');
                            
                            // Update chat parameters
                            const newOwnerId = this.dataset.ownerId;
                            const newStudentId = this.dataset.studentId;
                            
                            // Update messages container data attributes
                            messagesContainer.dataset.ownerId = newOwnerId;
                            messagesContainer.dataset.studentId = newStudentId;
                            
                            // Reinitialize chat with new parameters
                            initChat({
                                ownerId: newOwnerId,
                                studentId: newStudentId,
                                userType: userType,
                                messageThreadSelector: '.message-thread',
                                messageFormSelector: '#message-form',
                                messageInputSelector: '#message-input'
                            });
                            
                            // Load messages for this conversation
                            // This will be handled by the initChat function
                        });
                    });
                }
            }
        });
    </script>
    
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
    <script src="./profile-fix.js"></script>
    <script src="./js/profile-navigation.js"></script>
    <!-- Chat Scripts -->
    <script src="js/real-time-chat.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize chat functionality if we're on the messages tab
            if ($('#messages').length) {
                // TIMESTAMP FIX: Override the createMessageElement function to fix NaN issue
                window.fixTimestamps = function() {
                    // Find all message time elements
                    const timeElements = document.querySelectorAll('.message-time');
                    
                    // Replace any NaN timestamps with current time
                    timeElements.forEach(el => {
                        if (el.textContent.includes('NaN')) {
                            const now = new Date();
                            el.textContent = now.toLocaleTimeString('en-US', { 
                                hour: '2-digit', 
                                minute: '2-digit', 
                                hour12: true 
                            });
                        }
                    });
                };
                
                // Run the timestamp fix immediately and periodically
                setInterval(window.fixTimestamps, 500);
                
                // Handle conversation selection
                $('.message-item').on('click', function() {
                    const conversationId = $(this).data('conversation-id');
                    const ownerId = $(this).data('owner-id');
                    const studentId = $(this).data('student-id');
                    const userType = $('.messages-container').data('user-type');
                    
                    // Update active conversation
                    $('.message-item').removeClass('active');
                    $(this).addClass('active');
                    
                    // Load conversation
                    window.location.href = `chat.php?owner_id=${ownerId}&student_id=${studentId}`;
                });
                
                // Initialize message form if it exists
                if ($('#message-form').length) {
                    const ownerId = $('.messages-container').data('owner-id');
                    const studentId = $('.messages-container').data('student-id');
                    const userType = $('.messages-container').data('user-type');
                    
                    // Initialize real-time chat
                    if (ownerId && studentId) {
                        initChat({
                            ownerId: ownerId,
                            studentId: studentId,
                            userType: userType,
                            messageThreadSelector: '.message-thread',
                            messageFormSelector: '#message-form',
                            messageInputSelector: '#message-input'
                        });
                    }
                }
            }
        });
    </script>
    <script src="js/notifications.js"></script>
</body>
</html>