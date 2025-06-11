<?php
// Script to revert the notification-icon-container from mobile-menu-toggle
session_start();
require_once './db.php';

// List of main pages to update
$pages = [
    'home.php',
    'offers.php',
    'demands.php',
    'Profile.php',
    'property.php',
    'chat.php',
    'contact.php'
];

$updated_files = [];
$errors = [];

foreach ($pages as $page) {
    $file_path = __DIR__ . '/' . $page;
    
    if (!file_exists($file_path)) {
        $errors[] = "File not found: $page";
        continue;
    }
    
    // Read the file content
    $content = file_get_contents($file_path);
    $original_content = $content;
    
    // Find and replace the modified mobile-menu-toggle div with notification icon
    $pattern = '/<div class="mobile-menu-toggle" id="mobileMenuToggle">\s*<\?php if \(isset\(\$_SESSION\[\'user_id\'\]\)\): \?>\s*<!-- Mobile Notification Icon -->\s*<div class="notification-icon-container mobile-notification">\s*<div class="notification-icon" id="mobileNotificationIcon">\s*<i class="fas fa-bell"><\/i>\s*<span class="notification-badge" id="mobileNotificationBadge" style="display: none;">0<\/span>\s*<\/div>\s*<\/div>\s*<\?php endif; \?>\s*<i class="fas fa-bars"><\/i>\s*<\/div>/s';
    
    // Simple replacement with original version
    $replacement = '<div class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </div>';
    
    // Replace the content
    $new_content = preg_replace($pattern, $replacement, $content);
    
    // If the content was changed, save the file
    if ($new_content !== $original_content) {
        file_put_contents($file_path, $new_content);
        $updated_files[] = $page;
    } else {
        // Try an alternative pattern
        $alt_pattern = '/<div class="mobile-menu-toggle" id="mobileMenuToggle">.*?<i class="fas fa-bars"><\/i>\s*<\/div>/s';
        if (preg_match($alt_pattern, $content)) {
            $new_content = preg_replace($alt_pattern, $replacement, $content);
            if ($new_content !== $original_content) {
                file_put_contents($file_path, $new_content);
                $updated_files[] = $page;
            } else {
                $errors[] = "Could not update $page - pattern not found";
            }
        } else {
            $errors[] = "Could not update $page - mobile-menu-toggle not found";
        }
    }
}

// Redirect to home page
header("Location: home.php");
exit();
?>
