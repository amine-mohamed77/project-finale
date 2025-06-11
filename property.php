<?php
session_start();
require_once './db.php';
require_once './chat_notifications.php';

// Get unread message count if user is logged in
$unread_message_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $unread_message_count = getUnreadMessageCount($conn, $_SESSION['user_id'], $_SESSION['user_type']);
}

// Debug - log the referrer
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'unknown';
error_log("Property page accessed with ID: {$_GET['id']} from referrer: $referrer");

// Check if property ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No property specified";
    header("Location: offers.php");
    exit();
}

$house_id = $_GET['id'];

try {
    // Log the house ID we're trying to fetch
    error_log("Attempting to fetch property with ID: $house_id");
    
    // Get property details with error handling
    $stmt = $conn->prepare("
        SELECT h.*, c.city_name, pt.proprety_type_name, o.owner_name, o.owner_email, o.owner_phone
        FROM house h
        JOIN city c ON h.city_id = c.city_id
        JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
        JOIN owner o ON h.owner_id = o.owner_id
        WHERE h.house_id = ?
    ");
    $stmt->execute([$house_id]);
    $property = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log the result
    error_log("Property fetch result: " . ($property ? "Found" : "Not found"));
    
    // If property not found, show error but don't redirect
    if (!$property) {
        // Instead of redirecting, we'll display an error message on this page
        $error_message = "Property not found. The property with ID $house_id does not exist or has been removed.";
    }
    
    // Get property images
    $stmt = $conn->prepare("
        SELECT pp.proprety_pictures_id, pp.proprety_pictures_name
        FROM proprety_pictures pp
        JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id
        WHERE hpp.house_id = ?
    ");
    $stmt->execute([$house_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no images found, use a default image
    if (empty($images)) {
        $images = [
            ['proprety_pictures_id' => 0, 'proprety_pictures_name' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80']
        ];
    }
    
    // Check if the property is in the user's favorites
    $is_favorite = false;
    if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'student') {
        $stmt = $conn->prepare("SELECT * FROM student_house WHERE student_id = ? AND house_id = ?");
        $stmt->execute([$_SESSION['user_id'], $house_id]);
        $is_favorite = $stmt->rowCount() > 0;
    }
    
} catch (PDOException $e) {
    // Log the error but don't redirect
    error_log("Database error in property.php: " . $e->getMessage());
    $error_message = "Error retrieving property details. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($property['house_title']) ? htmlspecialchars($property['house_title']) : 'Property Details'; ?> - UniHousing</title>
    <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="./logo-animation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <style>
        /* Property Gallery Styles */
        .property-gallery {
            margin-bottom: 2rem;
        }
        
        .main-image {
            position: relative;
            width: 100%;
            height: 500px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .favorite-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .favorite-btn i {
            font-size: 20px;
            color: #ccc;
        }
        
        .favorite-btn.active i {
            color: #e74c3c;
        }
        
        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        
        .thumbnail {
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.3s ease;
        }
        
        .thumbnail.active {
            border: 3px solid #4CAF50;
        }
        
        .thumbnail:not(.active) img {
            opacity: 0.5;
        }
        
        /* Property Info Styles */
        .property-info {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .property-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .title-section h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .property-location {
            display: flex;
            align-items: center;
            color: #666;
        }
        
        .property-location i {
            margin-right: 0.5rem;
            color:rgb(12, 72, 237);
        }
        
        .price-section {
            text-align: right;
        }
        
        .price {
            font-size: 1.5rem;
            font-weight: 700;
           color:rgb(12, 72, 237);
            margin-bottom: 1rem;
        }
        
        .property-features {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .feature {
            display: flex;
            align-items: center;
        }
        
        .feature i {
            margin-right: 0.5rem;
            color:rgb(12, 72, 237);
        }
        
        .property-description, 
        .property-amenities,
        .property-location-details,
        .owner-info {
            margin-bottom: 2rem;
        }
        
        .property-description h2,
        .property-amenities h2,
        .property-location-details h2,
        .owner-info h2 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }
        
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        
        .amenity {
            display: flex;
            align-items: center;
            background: #f9f9f9;
            padding: 0.8rem;
            border-radius: 8px;
        }
        
        .amenity i {
            margin-right: 0.8rem;
           color:rgb(12, 72, 237);
        }
        
        .location-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .location-item {
            display: flex;
            align-items: center;
        }
        
        .location-item i {
            font-size: 1.5rem;
            margin-right: 1rem;
            color:rgb(12, 72, 237);
        }
        
        .location-info h3 {
            font-size: 1rem;
            margin-bottom: 0.3rem;
        }
        
        .location-info p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .owner-card {
            display: flex;
            align-items: center;
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 10px;
        }
        
        .owner-card img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1.5rem;
        }
        
        .owner-details {
            flex: 1;
        }
        
        .owner-details h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .owner-rating {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .owner-rating i {
            color: #FFD700;
            margin-right: 0.2rem;
        }
        
        .owner-rating span {
            margin-left: 0.5rem;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .property-header {
                flex-direction: column;
            }
            
            .price-section {
                text-align: left;
                margin-top: 1rem;
            }
            
            .property-features {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .amenities-grid, .location-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .owner-card {
                flex-direction: column;
                text-align: center;
            }
            
            .owner-card img {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .owner-rating {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .thumbnail-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .amenities-grid, .location-grid {
                grid-template-columns: 1fr;
            }
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
                        <li><a href="./offers.php" class="active">Offers</a></li>
                        <li><a href="./demands.php">Demands</a></li>
                        <li><a href="about.html">About</a></li>
                        <li><a href="contact.html">Contact</a></li>
                    </ul>
                </nav>
                <div class="auth-buttons">
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
                <div class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- Property Details Section -->
    <section class="property-details">
        <div class="container">
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
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                    <p class="mt-3"><a href="offers.php" class="btn btn-primary">Browse Properties</a></p>
                </div>
            <?php else: ?>
            
            <div class="property-gallery">
                <div class="main-image">
                    <img id="mainImage" src="<?php echo htmlspecialchars($images[0]['proprety_pictures_name']); ?>" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'student'): ?>
                        <?php if ($is_favorite): ?>
                            <a href="remove_favorite.php?id=<?php echo $house_id; ?>" class="favorite-btn active" title="Remove from favorites">
                                <i class="fas fa-heart"></i>
                            </a>
                        <?php else: ?>
                            <a href="add_favorite.php?id=<?php echo $house_id; ?>" class="favorite-btn" title="Add to favorites">
                                <i class="far fa-heart"></i>
                            </a>
                        <?php endif; ?>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="favorite-btn" title="Login to add to favorites">
                            <i class="far fa-heart"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="thumbnail-grid">
                    <?php foreach ($images as $index => $image): ?>
                        <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
                            <img src="<?php echo htmlspecialchars($image['proprety_pictures_name']); ?>" alt="Thumbnail <?php echo $index + 1; ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="property-info">
                <div class="property-header">
                    <div class="title-section">
                        <h1><?php echo htmlspecialchars($property['house_title']); ?></h1>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p><?php echo htmlspecialchars($property['house_location']); ?>, <?php echo htmlspecialchars($property['city_name']); ?></p>
                        </div>
                    </div>
                    <div class="price-section">
                        <p class="price">$<?php echo htmlspecialchars($property['house_price']); ?>/mo</p>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'student'): ?>
                            <a href="chat.php?id=<?php echo htmlspecialchars($property['owner_id']); ?>" class="btn btn-primary">Contact Owner</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">Login to Contact Owner</a>
                        <?php endif; ?>
                    </div>
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
                        <span><?php echo htmlspecialchars($property['house_surface']); ?> m²</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-building"></i>
                        <span><?php echo htmlspecialchars($property['proprety_type_name']); ?></span>
                    </div>
                </div>

                <div class="property-description">
                    <h2>Description</h2>
                    <p><?php echo nl2br(htmlspecialchars($property['house_description'])); ?></p>
                </div>

                <div class="property-amenities">
                    <h2>Amenities</h2>
                    <div class="amenities-grid">
                        <div class="amenity">
                            <i class="fas fa-wifi"></i>
                            <span>High-Speed Internet</span>
                        </div>
                        <div class="amenity">
                            <i class="fas fa-snowflake"></i>
                            <span>Air Conditioning</span>
                        </div>
                        <div class="amenity">
                            <i class="fas fa-tshirt"></i>
                            <span>Washer/Dryer</span>
                        </div>
                        <div class="amenity">
                            <i class="fas fa-parking"></i>
                            <span>Parking Available</span>
                        </div>
                        <div class="amenity">
                            <i class="fas fa-dumbbell"></i>
                            <span>Fitness Center</span>
                        </div>
                        <div class="amenity">
                            <i class="fas fa-swimming-pool"></i>
                            <span>Swimming Pool</span>
                        </div>
                    </div>
                </div>

                <div class="property-location-details">
                    <h2>Location & Nearby</h2>
                    <div class="location-grid">
                        <div class="location-item">
                            <i class="fas fa-university"></i>
                            <div class="location-info">
                                <h3>University</h3>
                                <p>5 minutes walk</p>
                            </div>
                        </div>
                        <div class="location-item">
                            <i class="fas fa-utensils"></i>
                            <div class="location-info">
                                <h3>Restaurants</h3>
                                <p>Multiple options within 10 minutes</p>
                            </div>
                        </div>
                        <div class="location-item">
                            <i class="fas fa-shopping-bag"></i>
                            <div class="location-info">
                                <h3>Shopping Center</h3>
                                <p>15 minutes walk</p>
                            </div>
                        </div>
                        <div class="location-item">
                            <i class="fas fa-bus"></i>
                            <div class="location-info">
                                <h3>Public Transport</h3>
                                <p>Bus stop 2 minutes away</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="owner-info">
                    <h2>Property Owner</h2>
                    <div class="owner-card">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" alt="Owner Photo">
                        <div class="owner-details">
                            <h3><?php echo htmlspecialchars($property['owner_name']); ?></h3>
                            <div class="owner-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                                <span>4.5 (24 reviews)</span>
                            </div>
                            <p>Verified Property Owner</p>
                        </div>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'student'): ?>
                            <a href="chat.php?id=<?php echo htmlspecialchars($property['owner_id']); ?>" class="btn btn-primary">Contact Owner</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">Login to Contact Owner</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <a href="home.php">Uni<span>Housing</span></a>
                    <p>Connecting students with their perfect housing solutions.</p>
                </div>
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="home.php">Home</a></li>
                        <li><a href="offers.php">Offers</a></li>
                        <li><a href="demands.php">Demands</a></li>
                        <li><a href="about.html">About Us</a></li>
                        <li><a href="contact.html">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h3>For Students</h3>
                    <ul>
                        <li><a href="offers.php">Find Housing</a></li>
                        <li><a href="#">Roommate Finder</a></li>
                        <li><a href="#">University Guides</a></li>
                        <li><a href="#">Student Resources</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h3>For Property Owners</h3>
                    <ul>
                        <li><a href="create_offer.php">List Your Property</a></li>
                        <li><a href="#">Landlord Resources</a></li>
                        <li><a href="#">Property Management</a></li>
                        <li><a href="#">Advertising Options</a></li>
                    </ul>
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

    <script>
        // Wait for DOM to be fully loaded before attaching event handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Preloader
            const preloader = document.querySelector('.preloader');
            if (preloader) {
                preloader.style.display = 'none';
            }

            // Mobile menu toggle
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const navMenu = document.querySelector('.nav-menu');
            
            if (mobileMenuToggle && navMenu) {
                mobileMenuToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                    this.classList.toggle('active');
                });
            }

            // Image gallery functionality
            const mainImage = document.getElementById('mainImage');
            const thumbnails = document.querySelectorAll('.thumbnail');
            
            if (mainImage && thumbnails.length > 0) {
                thumbnails.forEach(thumbnail => {
                    thumbnail.addEventListener('click', function() {
                        // Update main image
                        const imgSrc = this.querySelector('img').src;
                        mainImage.src = imgSrc;
                        
                        // Update active state
                        thumbnails.forEach(thumb => thumb.classList.remove('active'));
                        this.classList.add('active');
                    });
                });
            }
            
            // Favorite button functionality if not linked to a page
            const favoriteBtn = document.querySelector('.favorite-btn');
            if (favoriteBtn && !favoriteBtn.getAttribute('href')) {
                favoriteBtn.addEventListener('click', function(e) {
                    // Prevent default action to stop page reload
                    e.preventDefault();
                    
                    this.classList.toggle('active');
                    const icon = this.querySelector('i');
                    if (this.classList.contains('active')) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                    } else {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                    }
                });
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
