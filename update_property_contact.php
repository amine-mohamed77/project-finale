<?php
// This script updates the property.php file to replace the mailto link with a chat link
$file_path = __DIR__ . '/property.php';
$file_content = file_get_contents($file_path);

// Replace the mailto link with the chat link
$search = '<a href="mailto:<?php echo htmlspecialchars($property[\'owner_email\']); ?>" class="btn btn-primary">Contact Owner</a>';
$replace = '<?php if (isset($_SESSION[\'user_id\']) && $_SESSION[\'user_type\'] == \'student\'): ?>
                            <a href="chat.php?id=<?php echo htmlspecialchars($property[\'owner_id\']); ?>" class="btn btn-primary">Contact Owner</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">Login to Contact Owner</a>
                        <?php endif; ?>';

$new_content = str_replace($search, $replace, $file_content);

// Save the updated content back to the file
if ($new_content !== $file_content) {
    file_put_contents($file_path, $new_content);
    echo "Successfully updated property.php with chat link!";
} else {
    echo "No changes were made to property.php. The search string was not found.";
}
?>
