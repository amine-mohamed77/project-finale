<?php
session_start();
require_once './db.php';
require_once './chat_notifications.php';

// Get unread message count if user is logged in
$unread_message_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $unread_message_count = getUnreadMessageCount($conn, $_SESSION['user_id'], $_SESSION['user_type']);
}

// Initialize variables for form
$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_demand'])) {
    // Check if user is logged in and is a student
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
        $error_message = 'You must be logged in as a student to post a demand';
    } else {
        // Get form data
        $student_id = $_SESSION['user_id'];
        $city_id = trim($_POST['city_id']);
        $location = trim($_POST['location']);
        $property_type = trim($_POST['property_type']);
        $budget = trim($_POST['budget']);
        $move_in_date = trim($_POST['move_in_date']);
        $duration = trim($_POST['duration']);
        $requirements = trim($_POST['requirements']);
        
        // Validate form data
        if (empty($city_id) || empty($location) || empty($property_type) || empty($budget) || empty($move_in_date) || empty($duration)) {
            $error_message = 'Please fill in all required fields';
        } else {
            try {
                // Insert demand into database
                $stmt = $conn->prepare("INSERT INTO student_demand 
                    (student_id, city_id, location, property_type, budget, move_in_date, duration, requirements, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $student_id,
                    $city_id,
                    $location,
                    $property_type,
                    $budget,
                    $move_in_date,
                    $duration,
                    $requirements
                ]);
                
                $success_message = 'Your housing demand has been posted successfully!';
                
                // Clear form data after successful submission
                $city_id = $location = $property_type = $budget = $move_in_date = $duration = $requirements = '';
                
            } catch (PDOException $e) {
                $error_message = 'Error posting demand: ' . $e->getMessage();
            }
        }
    }
}

// Get cities for dropdown
try {
    $stmt = $conn->prepare("SELECT city_id, city_name FROM city ORDER BY city_name");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Error fetching cities: ' . $e->getMessage();
}

// Get active demands with optional filtering
try {
    // Base query
    $base_query = "SELECT sd.*, s.student_name, s.student_email, p.picture_url, c.city_name 
                   FROM student_demand sd 
                   JOIN student s ON sd.student_id = s.student_id 
                   JOIN city c ON sd.city_id = c.city_id
                   LEFT JOIN picture p ON s.picture_id = p.picture_id";
    
    $where_clauses = [];
    $params = [];
    
    // Check if filter was submitted
    $filter_submitted = isset($_GET['filter_submitted']);
    
    // Apply filters if submitted
    if ($filter_submitted) {
        // City filter
        if (!empty($_GET['city_id'])) {
            $where_clauses[] = "sd.city_id = ?";
            $params[] = $_GET['city_id'];
        }
        
        // Property type filter
        if (!empty($_GET['property_type'])) {
            $where_clauses[] = "sd.property_type = ?";
            $params[] = $_GET['property_type'];
        }
    }
    
    // Add WHERE clause if we have conditions
    if (!empty($where_clauses)) {
        $base_query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Add ordering
    if ($filter_submitted && !empty($_GET['sort_by'])) {
        switch ($_GET['sort_by']) {
            case 'newest':
                $base_query .= " ORDER BY sd.created_at DESC";
                break;
            case 'oldest':
                $base_query .= " ORDER BY sd.created_at ASC";
                break;
            case 'budget-high':
                $base_query .= " ORDER BY sd.budget DESC";
                break;
            case 'budget-low':
                $base_query .= " ORDER BY sd.budget ASC";
                break;
            default:
                $base_query .= " ORDER BY sd.created_at DESC";
        }
    } else {
        $base_query .= " ORDER BY sd.created_at DESC";
    }
    
    // Add limit
    $base_query .= " LIMIT 50";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($base_query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    
    $demands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Error fetching demands: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Demands - UniHousing</title>
   <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="./logo-animation.css">
    <link rel="stylesheet" href="./demands-card.css">
    <link rel="stylesheet" href="./notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
     <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
     
    <style>
        /* Fix the hero section icons */
        .demands-hero .hero-stats {
            display: flex;
            justify-content: center;
            gap: 80px;
            margin: 40px 0;
        }
        
        .demands-hero .hero-stats i {
            font-size: 32px;
            background-color: rgba(255, 255, 255, 0.2);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .demands-hero .hero-stats i:before {
            position: static !important;
        }
        
        .demands-hero .hero-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
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
                        <li><a href="./home.php">Home</a></li>
                        <li><a href="./offers.php">Offers </a></li>
                        <li><a href="./demands.php" class="active">Demands</a></li>
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
                            <li><a href="./offers.php">Offers</a></li>
                            <li><a href="./demands.php" class="active">Demands</a></li>
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

    <!-- Demands Hero Section -->
    <section class="demands-hero">
        <div class="demands-hero-background"></div>
        <div class="container">
            <div class="demands-hero-content">
                <h1>Find Your Perfect Student Housing</h1>
                <p>Connect with property owners and find the ideal accommodation for your academic journey</p>
                <div class="hero-stats">
                    <div class="stat">
                        <i class="fas fa-home stat-icon"></i>
                        <span class="number">200+</span>
                        <span class="label">Active Demands</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-map-marker-alt stat-icon"></i>
                        <span class="number">30+</span>
                        <span class="label">Cities</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-check-circle stat-icon"></i>
                        <span class="number">95%</span>
                        <span class="label">Success Rate</span>
                    </div>
                </div>
                <div class="hero-cta">
                    <a href="#post-demand" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus"></i> Post Your Demand
                    </a>
                    <a href="#active-demands" class="btn btn-outline btn-lg">
                        <i class="fas fa-search"></i> Browse Demands
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Post Demand Section -->
    <section id="post-demand" class="post-demand-section">
        <div class="container">
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="post-demand-container">
                <div class="post-demand-header">
                    <div class="header-icon">
                        <i class="fas fa-pen-to-square"></i>
                    </div>
                    <h2>Post Your Housing Requirements</h2>
                    <p>Let property owners know what you're looking for and find your perfect match</p>
                </div>
                
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="login-prompt">
                        <i class="fas fa-lock"></i>
                        <h3>Login Required</h3>
                        <p>You need to be logged in as a student to post your housing requirements.</p>
                        <div class="prompt-actions">
                            <a href="login.php" class="btn btn-primary">Login Now</a>
                            <a href="signUp.php" class="btn btn-outline">Sign Up</a>
                        </div>
                    </div>
                <?php elseif ($_SESSION['user_type'] !== 'student'): ?>
                    <div class="login-prompt">
                        <i class="fas fa-user-times"></i>
                        <h3>Student Access Only</h3>
                        <p>Only student accounts can post housing requirements.</p>
                    </div>
                <?php else: ?>
                    <form class="demand-form" method="POST" action="demands.php#post-demand">
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-city"></i> City</label>
                                <div class="select-with-icon">
                                    <i class="fas fa-city"></i>
                                    <select name="city_id" required>
                                        <option value="">Select city</option>
                                        <?php foreach ($cities as $city): ?>
                                            <option value="<?php echo $city['city_id']; ?>"><?php echo htmlspecialchars($city['city_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Location</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <input type="text" name="location" placeholder="Preferred area" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-home"></i> Property Type</label>
                                <div class="select-with-icon">
                                    <i class="fas fa-home"></i>
                                    <select name="property_type" required>
                                        <option value="">Select type</option>
                                        <option value="apartment">Apartment</option>
                                        <option value="house">House</option>
                                        <option value="room">Room</option>
                                        <option value="dormitory">Dormitory</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-dollar-sign"></i> Budget</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                    <input type="number" name="budget" placeholder="Maximum monthly rent" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar"></i> Move-in Date</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-calendar"></i>
                                    <input type="date" name="move_in_date" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Duration</label>
                                <div class="select-with-icon">
                                    <i class="fas fa-clock"></i>
                                    <select name="duration" required>
                                        <option value="">Select duration</option>
                                        <option value="semester">One Semester</option>
                                        <option value="year">One Year</option>
                                        <option value="longer">Longer</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label><i class="fas fa-comment"></i> Additional Requirements</label>
                            <div class="textarea-with-icon">
                                <i class="fas fa-comment"></i>
                                <textarea name="requirements" placeholder="Describe your requirements, preferences, and any other details..."></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="submit_demand" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Post Demand
                            </button>
                            <button type="reset" class="btn btn-outline">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Active Demands Section -->
    <section id="active-demands" class="demands-section">
        <div class="container">
            <div class="section-header" style="display: flex; flex-wrap: wrap; gap: 1rem;">
                <div class="header-left">
                    <h2>Active Demands</h2>
                    <p>Browse through current student housing requirements</p>
                </div>
                <form id="demand-filter-form" method="GET" action="demands.php#active-demands" class="demand-filters" style="display: flex;">
                    <div class="filter-group">
                        <i class="fas fa-city"></i>
                        <select name="city_id" id="city-filter">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo $city['city_id']; ?>" <?php echo (isset($_GET['city_id']) && $_GET['city_id'] == $city['city_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city['city_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <i class="fas fa-home"></i>
                        <select name="property_type" id="type-filter">
                            <option value="">All Types</option>
                            <?php 
                            // Fetch property types from database
                            try {
                                $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type");
                                $stmt->execute();
                                $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($property_types as $type) {
                                    $selected = (isset($_GET['property_type']) && $_GET['property_type'] == $type['proprety_type_id']) ? 'selected' : '';
                                    echo '<option value="' . $type['proprety_type_id'] . '" ' . $selected . '>' . $type['proprety_type_name'] . '</option>';
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
                    <div class="filter-group">
                        <i class="fas fa-sort"></i>
                        <select name="sort_by" id="sort-filter">
                            <option value="">Sort By</option>
                            <option value="newest" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="budget-high" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'budget-high') ? 'selected' : ''; ?>>Highest Budget</option>
                            <option value="budget-low" <?php echo (isset($_GET['sort_by']) && $_GET['sort_by'] == 'budget-low') ? 'selected' : ''; ?>>Lowest Budget</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <input type="hidden" name="filter_submitted" value="1">
                </form>
            </div>

            <div class="demands-grid">
                <?php if (!empty($demands)): ?>
                    <?php foreach ($demands as $demand): ?>
                        <div class="demand-card">
                            <div class="demand-header">
                                <div class="student-info">
                                    <?php if (!empty($demand['picture_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($demand['picture_url']); ?>" alt="<?php echo htmlspecialchars($demand['student_name']); ?>">
                                    <?php else: ?>
                                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" alt="<?php echo htmlspecialchars($demand['student_name']); ?>">
                                    <?php endif; ?>
                                    <div>
                                        <h3><?php echo htmlspecialchars($demand['student_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($demand['city_name']); ?></p>
                                        <div class="student-rating">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star-half-alt"></i>
                                            <span>(4.5)</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="demand-tags">
                                    <?php if (strtotime($demand['created_at']) > strtotime('-3 days')): ?>
                                        <span class="demand-tag new">New</span>
                                    <?php endif; ?>
                                    <span class="demand-tag verified">Verified</span>
                                </div>
                            </div>
                            <div class="demand-content">
                                <div class="demand-details">
                                    <div class="detail">
                                        <i class="fas fa-city"></i>
                                        <span><?php echo htmlspecialchars($demand['city_name']); ?></span>
                                    </div>
                                    <div class="detail">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($demand['location']); ?></span>
                                    </div>
                                    <div class="detail">
                                        <i class="fas fa-home"></i>
                                        <span><?php echo htmlspecialchars($demand['property_type']); ?></span>
                                    </div>
                                    <div class="detail">
                                        <i class="fas fa-dollar-sign"></i>
                                        <span>Budget: $<?php echo htmlspecialchars($demand['budget']); ?>/mo</span>
                                    </div>
                                </div>
                                <p class="demand-description"><?php echo nl2br(htmlspecialchars($demand['requirements'])); ?></p>
                                <div class="demand-features">
                                    <?php if (strpos(strtolower($demand['requirements']), 'internet') !== false || strpos(strtolower($demand['requirements']), 'wifi') !== false): ?>
                                        <span class="feature-tag"><i class="fas fa-wifi"></i> High-Speed Internet</span>
                                    <?php endif; ?>
                                    <?php if (strpos(strtolower($demand['requirements']), 'furnish') !== false): ?>
                                        <span class="feature-tag"><i class="fas fa-couch"></i> Furnished</span>
                                    <?php endif; ?>
                                    <?php if (strpos(strtolower($demand['requirements']), 'parking') !== false): ?>
                                        <span class="feature-tag"><i class="fas fa-parking"></i> Parking</span>
                                    <?php endif; ?>
                                    <?php if (strpos(strtolower($demand['requirements']), 'pet') !== false): ?>
                                        <span class="feature-tag"><i class="fas fa-paw"></i> Pet Friendly</span>
                                    <?php endif; ?>
                                    <?php if (strpos(strtolower($demand['requirements']), 'study') !== false || strpos(strtolower($demand['requirements']), 'quiet') !== false): ?>
                                        <span class="feature-tag"><i class="fas fa-book"></i> Study Room</span>
                                    <?php endif; ?>
                                </div>
                                <div class="demand-footer">
                                    <div class="demand-meta">
                                        <span class="date"><i class="far fa-clock"></i> Posted <?php echo date('M j, Y', strtotime($demand['created_at'])); ?></span>
                                        <span class="views"><i class="far fa-eye"></i> <?php echo rand(50, 300); ?> views</span>
                                    </div>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'owner'): ?>
                                        <a href="chat.php?owner_id=<?php echo htmlspecialchars($_SESSION['user_id']); ?>&student_id=<?php echo htmlspecialchars($demand['student_id']); ?>" class="btn btn-primary">
                                            <i class="fas fa-comments"></i> Contact Student
                                        </a>
                                    <?php else: ?>
                                        <a href="login.php" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt"></i> Login to Contact Student
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Demands Found</h3>
                        <p>There are currently no active housing demands. Be the first to post your requirements!</p>
                    </div>
                <?php endif; ?>

            <!-- Pagination -->
            <div class="pagination">
                <a href="#" class="prev"><i class="fas fa-chevron-left"></i> Previous</a>
                <div class="page-numbers">
                    <a href="#" class="active">1</a>
                    <a href="#">2</a>
                    <a href="#">3</a>
                    <span>...</span>
                    <a href="#">10</a>
                </div>
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
</body>
</html> 