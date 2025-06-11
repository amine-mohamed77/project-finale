<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Details - UniHousing</title>
    <link rel="stylesheet" href="./style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.html">Uni<span>Housing</span></a>
                </div>
                <nav class="nav-menu">
                    <ul>
                        <li><a href="index.html">Home</a></li>
                        <li><a href="offers.html" class="active">Offers</a></li>
                        <li><a href="demands.html">Demands</a></li>
                        <li><a href="about.html">About</a></li>
                        <li><a href="contact.html">Contact</a></li>
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <a href="login.html" class="btn btn-outline">Login</a>
                    <a href="register.html" class="btn btn-primary">Sign Up</a>
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
            <div class="property-gallery">
                <div class="main-image">
                    <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Main Property Image">
                    <button class="favorite-btn">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
                <div class="thumbnail-grid">
                    <div class="thumbnail active">
                        <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Thumbnail 1">
                    </div>
                    <div class="thumbnail">
                        <img src="https://images.unsplash.com/photo-1560185127-6ed189bf02f4?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Thumbnail 2">
                    </div>
                    <div class="thumbnail">
                        <img src="https://images.unsplash.com/photo-1560185893-a55cbc8c57e8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Thumbnail 3">
                    </div>
                    <div class="thumbnail">
                        <img src="https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Thumbnail 4">
                    </div>
                </div>
            </div>

            <div class="property-info">
                <div class="property-header">
                    <div class="title-section">
                        <h1>Modern Studio Apartment</h1>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>Near Stanford University, Palo Alto</p>
                        </div>
                    </div>
                    <div class="price-section">
                        <p class="price">$650/mo</p>
                        <button class="btn btn-primary">Contact Owner</button>
                    </div>
                </div>

                <div class="property-features">
                    <div class="feature">
                        <i class="fas fa-bed"></i>
                        <span>1 Bed</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-bath"></i>
                        <span>1 Bath</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-vector-square"></i>
                        <span>35 m²</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-building"></i>
                        <span>Apartment</span>
                    </div>
                </div>

                <div class="property-description">
                    <h2>Description</h2>
                    <p>Beautiful modern studio apartment located just minutes away from Stanford University. This fully furnished apartment offers a perfect blend of comfort and convenience for students. Features include high-speed internet, modern appliances, and a dedicated study area.</p>
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
                                <h3>Stanford University</h3>
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
                            <h3>Michael Chen</h3>
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
                        <button class="btn btn-primary">Contact Owner</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Similar Properties Section -->
    <section class="similar-properties">
        <div class="container">
            <h2>Similar Properties</h2>
            <div class="property-grid">
                <!-- Similar Property Cards -->
                <div class="property-card">
                    <div class="property-image">
                        <img src="https://images.unsplash.com/photo-1560185127-6ed189bf02f4?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Similar Property 1">
                        <button class="favorite-btn">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="property-content">
                        <div class="property-header">
                            <h3>Cozy Studio Near Campus</h3>
                            <p class="price">$600/mo</p>
                        </div>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>Stanford Area, Palo Alto</p>
                        </div>
                        <div class="property-features">
                            <div class="feature">
                                <i class="fas fa-bed"></i>
                                <span>1 Bed</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-bath"></i>
                                <span>1 Bath</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-vector-square"></i>
                                <span>30 m²</span>
                            </div>
                        </div>
                        <a href="offer-details.html" class="btn btn-primary btn-full">View Details</a>
                    </div>
                </div>
                <!-- Add more similar property cards as needed -->
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
</body>
</html> 