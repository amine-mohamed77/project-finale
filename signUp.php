<?php
session_start();
require_once './db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $account_type = $_POST['type'];
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($account_type)) {
        $_SESSION['error'] = "Please fill in all fields";
        header("Location: signUp.php");
        exit();
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match";
        header("Location: signUp.php");
        exit();
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long";
        header("Location: signUp.php");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address";
        header("Location: signUp.php");
        exit();
    }
    
    if (!in_array($account_type, ['student', 'owner'])) {
        $_SESSION['error'] = "Please select a valid account type";
        header("Location: signUp.php");
        exit();
    }
    
    try {
        // Check if email already exists in both tables
        $stmt = $conn->prepare("SELECT student_email FROM student WHERE student_email = ? UNION SELECT owner_email FROM owner WHERE owner_email = ?");
        $stmt->execute([$email, $email]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Email address already exists";
            header("Location: signUp.php");
            exit();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
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
            }
        }
        
        if ($account_type == 'student') {
            // Get next student_id
            $stmt = $conn->prepare("SELECT MAX(student_id) as max_id FROM student");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $student_id = ($result['max_id'] ?? 0) + 1;
            
            // Create a dummy owner record first (or use existing one)
            $stmt = $conn->prepare("SELECT owner_id FROM owner WHERE owner_id = 1");
            $stmt->execute();
            $dummy_owner = $stmt->fetch();
            
            if (!$dummy_owner) {
                // Create a dummy owner for student pictures
                $stmt = $conn->prepare("INSERT INTO owner (owner_id, owner_name, owner_email, owner_password) VALUES (1, 'System', 'system@unihousing.com', ?)");
                $stmt->execute([password_hash('system123', PASSWORD_DEFAULT)]);
            }
            
            // Get next picture_id
            $stmt = $conn->prepare("SELECT MAX(picture_id) as max_id FROM picture");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $picture_id = ($result['max_id'] ?? 0) + 1;
            
            // Insert picture record with dummy owner_id = 1
            $stmt = $conn->prepare("INSERT INTO picture (picture_id, owner_id, picture_url) VALUES (?, ?, ?)");
            $stmt->execute([$picture_id, 1, $filepath]); // Use owner_id = 1 for student pictures
            
            // Now insert student with the picture_id that exists
            $stmt = $conn->prepare("INSERT INTO student (student_id, picture_id, student_name, student_email, student_password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $picture_id, $name, $email, $hashed_password]);
            
            $user_id = $student_id;
            
        } else { // owner
            // Get next owner_id
            $stmt = $conn->prepare("SELECT MAX(owner_id) as max_id FROM owner");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $owner_id = ($result['max_id'] ?? 0) + 1;
            
            // Insert owner first
            $stmt = $conn->prepare("INSERT INTO owner (owner_id, owner_name, owner_email, owner_password) VALUES (?, ?, ?, ?)");
            $stmt->execute([$owner_id, $name, $email, $hashed_password]);
            
            // If picture uploaded, create picture record
            if ($filepath) {
                $stmt = $conn->prepare("SELECT MAX(picture_id) as max_id FROM picture");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $picture_id = ($result['max_id'] ?? 0) + 1;
                
                $stmt = $conn->prepare("INSERT INTO picture (picture_id, owner_id, picture_url) VALUES (?, ?, ?)");
                $stmt->execute([$picture_id, $owner_id, $filepath]);
            }
            
            $user_id = $owner_id;
        }
        
        // Auto-login after successful registration
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_type'] = $account_type;
        
        $_SESSION['success'] = "Account created successfully! Welcome to UniHousing.";
        header("Location: Profile.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Registration failed. Please try again. Error: " . $e->getMessage();
        header("Location: signUp.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UniHousing</title>
    <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="./logo-animation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>


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
                        <li><a href="./offers.php">Offers</a></li>
                        <li><a href="demands.html">Demands</a></li>
                        <li><a href="about.html">About</a></li>
                        <li><a href="contact.html">Contact</a></li>
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <a href="./login.php" class="btn btn-outline">Login</a>
                    <a href="./signUp.php" class="btn btn-primary">Sign Up</a>
                </div>
                <div class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- Register Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-header">
                    <h1>Create Account</h1>
                    <p>Join our community of students and property owners</p>
                </div>
                
                <?php
                // Display error or success messages
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-error" style="background-color: #fee; color: #c33; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #fcc;">' . htmlspecialchars($_SESSION['error']) . '</div>';
                    unset($_SESSION['error']);
                }
                if (isset($_SESSION['success'])) {
                    echo '<div class="alert alert-success" style="background-color: #efe; color: #363; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #cfc;">' . htmlspecialchars($_SESSION['success']) . '</div>';
                    unset($_SESSION['success']);
                }
                ?>
                
                <form id="registerForm" class="auth-form" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user"></i>
                            <input type="text" id="name" name="name" required placeholder="Enter your full name">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" required placeholder="Enter your email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required placeholder="Create a password">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Confirm Password</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm-password" name="confirm-password" required placeholder="Confirm your password">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="type">Account Type</label>
                        <div class="select-with-icon">
                            <i class="fas fa-user-tag"></i>
                            <select id="type" name="type" required>
                                <option value="">Select account type</option>
                                <option value="student">Student</option>
                                <option value="owner">Property Owner</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="avatar">Profile Picture</label>
                        <div class="input-with-icon">
                            <i class="fas fa-camera"></i>
                            <input type="file" id="avatar" name="avatar" accept="image/*">
                        </div>
                    </div>
                    <div class="form-options">
                        <label class="terms">
                            <input type="checkbox" name="terms" required>
                            <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">Create Account</button>
                </form>
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Sign In</a></p>
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
</body>
</html>
