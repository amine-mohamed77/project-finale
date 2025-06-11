<?php
session_start();
require_once './db.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    $_SESSION['error'] = "You are not authorized to edit demands";
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$demand_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get all cities for dropdown
try {
    $stmt = $conn->prepare("SELECT city_id, city_name FROM city");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['demand_error'] = "Error fetching cities: " . $e->getMessage();
    header("Location: Profile.php#my-demands");
    exit();
}

// Get demand data if editing
$demand = null;
if ($demand_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT sd.*, c.city_name 
            FROM student_demand sd
            JOIN city c ON sd.city_id = c.city_id
            WHERE sd.demand_id = ? AND sd.student_id = ?
        ");
        $stmt->execute([$demand_id, $student_id]);
        $demand = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$demand) {
            $_SESSION['demand_error'] = "Demand not found or you don't have permission to edit it";
            header("Location: Profile.php#my-demands");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['demand_error'] = "Error fetching demand: " . $e->getMessage();
        header("Location: Profile.php#my-demands");
        exit();
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_demand'])) {
    // Validate and sanitize input
    $city_id = isset($_POST['city_id']) ? intval($_POST['city_id']) : 0;
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $property_type = isset($_POST['property_type']) ? trim($_POST['property_type']) : '';
    $budget = isset($_POST['budget']) ? floatval($_POST['budget']) : 0;
    $move_in_date = isset($_POST['move_in_date']) ? trim($_POST['move_in_date']) : '';
    $duration = isset($_POST['duration']) ? trim($_POST['duration']) : '';
    $requirements = isset($_POST['requirements']) ? trim($_POST['requirements']) : '';
    
    // Validation
    $errors = [];
    if ($city_id <= 0) {
        $errors[] = "Please select a valid city";
    }
    if (empty($location)) {
        $errors[] = "Location is required";
    }
    if (empty($property_type)) {
        $errors[] = "Property type is required";
    }
    if ($budget <= 0) {
        $errors[] = "Please enter a valid budget";
    }
    if (empty($move_in_date)) {
        $errors[] = "Move-in date is required";
    }
    if (empty($duration)) {
        $errors[] = "Duration is required";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE student_demand 
                SET city_id = ?, location = ?, property_type = ?, budget = ?, 
                    move_in_date = ?, duration = ?, requirements = ?
                WHERE demand_id = ? AND student_id = ?
            ");
            $stmt->execute([
                $city_id, $location, $property_type, $budget, 
                $move_in_date, $duration, $requirements, 
                $demand_id, $student_id
            ]);
            
            $_SESSION['demand_success'] = "Your housing demand has been updated successfully";
            header("Location: Profile.php#my-demands");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error updating demand: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Housing Demand - UniHousing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./stylen.css">
      <link rel="stylesheet" href="./logo-animation.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
     <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <style>
        :root {
            --primary-color: #4a6ee0;
            --primary-light: #6989ff;
            --primary-dark: #2c4bbd;
            --secondary-color: #ff6b6b;
            --text-dark: #333;
            --text-medium: #555;
            --text-light: #777;
            --background-white: #fff;
            --background-light: #f8f9fa;
            --background-gray: #f0f2f5;
            --border-color: #e1e4e8;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var();
            color: var(--text-dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .form-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 2rem;
            background-color: var(--background-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 1.75rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 110, 224, 0.1);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: 0 4px 10px rgba(74, 110, 224, 0.2);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--primary-color));
        }
        
        .btn-outline:hover {
           background-color: #4a6ee0;
           color: white;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 500;
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .alert ul {
            margin: 0.5rem 0 0.5rem 1.5rem;
            padding: 0;
        }
        
        .alert li {
            margin-bottom: 0.25rem;
        }
        
        /* Form sections styling */
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        /* Form animations */
        .form-control {
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            transform: translateY(-2px);
        }
        
        /* Custom styling for date input */
        input[type="date"] {
            position: relative;
            padding-right: 2rem;
        }
        
        input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }
        
        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .form-container {
                padding: 1.5rem;
                margin: 20px auto;
            }
            
            .btn-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
            
            .form-row {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

    <!-- Preloader -->
<div class="preloader">
        <dotlottie-player src="https://lottie.host/11f7d3b7-bf99-4f72-be1e-e72f766c5d3d/KJ6V4IJRpi.lottie" background="transparent" speed="1" style="width: 300px; height: 300px" loop autoplay></dotlottie-player>
    </div>
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Edit Housing Demand</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form action="edit_demand.php?id=<?php echo $demand_id; ?>" method="POST" class="property-form">
                <!-- Location Information -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> Location Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city_id">City</label>
                            <select name="city_id" id="city_id" class="form-control" required>
                                <option value="">Select City</option>
                                <?php foreach ($cities as $city): ?>
                                    <option value="<?php echo $city['city_id']; ?>" <?php echo ($demand && $demand['city_id'] == $city['city_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city['city_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Preferred Location</label>
                            <input type="text" name="location" id="location" class="form-control" placeholder="Neighborhood, area, or street" value="<?php echo $demand ? htmlspecialchars($demand['location']) : ''; ?>" required>
                        </div>
                    </div>
                </div>
                
                <!-- Property Details -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-home"></i> Property Details</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type</label>
                            <select name="property_type" id="property_type" class="form-control" required>
                                <option value="">Select Property Type</option>
                                <option value="Apartment" <?php echo ($demand && $demand['property_type'] == 'Apartment') ? 'selected' : ''; ?>>Apartment</option>
                                <option value="House" <?php echo ($demand && $demand['property_type'] == 'House') ? 'selected' : ''; ?>>House</option>
                                <option value="Studio" <?php echo ($demand && $demand['property_type'] == 'Studio') ? 'selected' : ''; ?>>Studio</option>
                                <option value="Shared Room" <?php echo ($demand && $demand['property_type'] == 'Shared Room') ? 'selected' : ''; ?>>Shared Room</option>
                                <option value="Dormitory" <?php echo ($demand && $demand['property_type'] == 'Dormitory') ? 'selected' : ''; ?>>Dormitory</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="budget">Monthly Budget ($)</label>
                            <input type="number" name="budget" id="budget" class="form-control" placeholder="Your maximum budget" value="<?php echo $demand ? htmlspecialchars($demand['budget']) : ''; ?>" min="1" required>
                        </div>
                    </div>
                </div>
                
                <!-- Timing Details -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Timing Details</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="move_in_date">Preferred Move-in Date</label>
                            <input type="date" name="move_in_date" id="move_in_date" class="form-control" value="<?php echo $demand ? htmlspecialchars($demand['move_in_date']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration of Stay</label>
                            <select name="duration" id="duration" class="form-control" required>
                                <option value="">Select Duration</option>
                                <option value="Less than 3 months" <?php echo ($demand && $demand['duration'] == 'Less than 3 months') ? 'selected' : ''; ?>>Less than 3 months</option>
                                <option value="3-6 months" <?php echo ($demand && $demand['duration'] == '3-6 months') ? 'selected' : ''; ?>>3-6 months</option>
                                <option value="6-12 months" <?php echo ($demand && $demand['duration'] == '6-12 months') ? 'selected' : ''; ?>>6-12 months</option>
                                <option value="1+ year" <?php echo ($demand && $demand['duration'] == '1+ year') ? 'selected' : ''; ?>>1+ year</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Requirements -->
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-list-ul"></i> Additional Requirements</h3>
                    
                    <div class="form-group">
                        <label for="requirements">Specific Requirements or Preferences</label>
                        <textarea name="requirements" id="requirements" class="form-control" placeholder="Describe any specific requirements or preferences you have for your accommodation..."><?php echo $demand ? htmlspecialchars($demand['requirements']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="btn-container">
                    <a href="Profile.php#my-demands" class="btn btn-outline">Cancel</a>
                    <button type="submit" name="update_demand" class="btn btn-primary">Update Demand</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Add any JavaScript validation if needed
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                // Basic validation can be added here if needed
            });
        });
    </script>
    <script src="./logo-animation.js"></script>
</body>
</html>
