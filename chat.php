<?php
session_start();
require_once './db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['user_name'] ?? 'User';

// Get owner_id and student_id from URL parameters
$owner_id = isset($_GET['owner_id']) ? intval($_GET['owner_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Validate that the user is either the owner or the student
if (($user_type === 'owner' && $user_id != $owner_id) || 
    ($user_type === 'student' && $user_id != $student_id)) {
    // If user is owner but owner_id doesn't match, set owner_id to user_id
    if ($user_type === 'owner') {
        $owner_id = $user_id;
    }
    // If user is student but student_id doesn't match, set student_id to user_id
    else if ($user_type === 'student') {
        $student_id = $user_id;
    }
}

// Get conversation partner details
$conversation_partner = null;
try {
    if ($user_type === 'student' && $owner_id > 0) {
        // Get owner details
        $stmt = $conn->prepare("SELECT o.owner_id, o.owner_name, p.picture_url 
                              FROM owner o
                              LEFT JOIN picture p ON p.owner_id = o.owner_id
                              WHERE o.owner_id = ?");
        $stmt->execute([$owner_id]);
        $conversation_partner = $stmt->fetch(PDO::FETCH_ASSOC);
    } else if ($user_type === 'owner' && $student_id > 0) {
        // Get student details
        $stmt = $conn->prepare("SELECT s.student_id, s.student_name, p.picture_url 
                              FROM student s
                              LEFT JOIN picture p ON s.picture_id = p.picture_id
                              WHERE s.student_id = ?");
        $stmt->execute([$student_id]);
        $conversation_partner = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching conversation partner: " . $e->getMessage());
}

// Get all conversations for the sidebar
$conversations = [];
try {
    if ($user_type === 'student') {
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - UniHousing</title>
    <link rel="stylesheet" href="stylen.css">
     <!-- <link rel="stylesheet" href="chat-interface-responsive.css">  -->
    <!-- <link rel="stylesheet" href="chat-image-upload.css">  -->
     <!-- <link rel="stylesheet" href="chat-message-form.css"> -->
    <!-- <link rel="stylesheet" href="mobile-sidebar-toggle.css">  -->
 <link rel="stylesheet" href="./chat-interface.css">
  <link rel="stylesheet" href="">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/fixed-chat.js"></script>
    <script src="./main.js" defer></script>
</head>
<body>

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
                <li><a href="about.html">About</a></li>
                <li><a href="contact.html">Contact</a></li>
            </ul>
        </nav>
        <div class="mobile-menu-auth">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="Profile.php" class="btn btn-primary" style="position: relative;">
                    My Profile
                    <?php if (isset($unread_message_count) && $unread_message_count > 0): ?>
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

    <section class="chat-page">
        <div class="container">
            <div class="chat-container">
                <div class="chat-sidebar">
                    <div class="sidebar-header">
                        <h2>Conversations</h2>
                        <a href="Profile.php" class="back-to-profile"><i class="fas fa-arrow-left"></i> Back to Profile</a>
                        <button id="toggleSidebar" class="toggle-sidebar d-md-none"><i class="fas fa-bars"></i></button>
                    </div>
                    <div class="conversation-list">
                        <?php if (!empty($conversations)): ?>
                            <?php foreach ($conversations as $conversation): ?>
                                <?php 
                                $conversation_id = $user_type == 'student' ? $conversation['owner_id'] : $conversation['student_id'];
                                $name = $user_type == 'student' ? $conversation['owner_name'] : $conversation['student_name'];
                                $profile_pic = !empty($conversation['picture_url']) ? $conversation['picture_url'] : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';
                                $last_message = !empty($conversation['last_message']) ? htmlspecialchars(substr($conversation['last_message'], 0, 40)) . (strlen($conversation['last_message']) > 40 ? '...' : '') : 'No messages yet';
                                $unread_count = $conversation['unread_count'] ?? 0;
                                $is_active = ($user_type == 'student' && $conversation_id == $owner_id) || ($user_type == 'owner' && $conversation_id == $student_id);
                                ?>
                                <div class="message-item <?php echo $is_active ? 'active' : ''; ?>" 
                                     data-owner-id="<?php echo $user_type == 'student' ? $conversation_id : $user_id; ?>" 
                                     data-student-id="<?php echo $user_type == 'owner' ? $conversation_id : $user_id; ?>">
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
                        <?php else: ?>
                            <div class="no-conversations">
                                <div class="empty-state small">
                                    <i class="fas fa-comments"></i>
                                    <h3>No Conversations Yet</h3>
                                    <p>Your conversations will appear here.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="chat-main">
                    <?php if ($owner_id > 0 && $student_id > 0): ?>
                        <!-- Mobile sidebar toggle button in main area -->
                        <button class="mobile-toggle-sidebar" id="mobileToggleSidebar">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        <div class="chat-header">
                            <?php if ($conversation_partner): ?>
                                <div class="avatar">
                                    <img src="<?php echo !empty($conversation_partner['picture_url']) ? htmlspecialchars($conversation_partner['picture_url']) : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80'; ?>" alt="<?php echo htmlspecialchars($user_type == 'student' ? $conversation_partner['owner_name'] : $conversation_partner['student_name']); ?>">
                                </div>
                                <h3><?php echo htmlspecialchars($user_type == 'student' ? $conversation_partner['owner_name'] : $conversation_partner['student_name']); ?></h3>
                            <?php else: ?>
                                <h3>Chat</h3>
                            <?php endif; ?>
                        </div>
                        
                        <div class="messages-container" data-owner-id="<?php echo $owner_id; ?>" data-student-id="<?php echo $student_id; ?>" data-user-type="<?php echo $user_type; ?>">
                            <div class="message-thread">
                                <!-- Messages will be loaded here via JavaScript -->
                                <div class="loading-messages">
                                    <div class="spinner"></div>
                                    <p>Loading messages...</p>
                                </div>
                            </div>
                            
                            <form id="message-form" class="message-form">
                                <label for="image-upload" class="image-upload-label">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="image-upload" accept="image/*" style="display: none;">
                                <div id="image-preview-container" style="display: none;">
                                    <img id="image-preview" src="#" alt="Preview">
                                    <button type="button" id="remove-image" class="btn btn-danger">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <input type="text" id="message-input" placeholder="Type your message...">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
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
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize chat if messages container exists
            if (document.querySelector('.messages-container')) {
                const messagesContainer = document.querySelector('.messages-container');
                const ownerId = messagesContainer.dataset.ownerId;
                const studentId = messagesContainer.dataset.studentId;
                const userType = messagesContainer.dataset.userType;
                
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
                
                // Mobile sidebar toggle functionality
                const toggleSidebarBtn = document.querySelector('.toggle-sidebar');
                const chatSidebar = document.querySelector('.chat-sidebar');
                
                if (toggleSidebarBtn && chatSidebar) {
                    toggleSidebarBtn.addEventListener('click', function() {
                        chatSidebar.classList.toggle('sidebar-visible');
                    });
                }
                
                if (ownerId && studentId) {
                    initChat({
                        ownerId: ownerId,
                        studentId: studentId,
                        userType: userType,
                        messageThreadSelector: '.message-thread',
                        messageFormSelector: '#message-form',
                        messageInputSelector: '#message-input'
                    });
                    
                    // Manually add form submission handler to ensure it works
                    const messageForm = document.getElementById('message-form');
                    const messageInput = document.getElementById('message-input');
                    
                    if (messageForm && messageInput) {
                        messageForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const messageText = messageInput.value.trim();
                            
                            if (messageText) {
                                // Send message using the sendMessage function from fixed-chat.js
                                sendMessage(messageText);
                                messageInput.value = '';
                            }
                        });
                    }
                    
                    // Mark messages as read when conversation is opened
                    fetch(`mark_messages_read.php?owner_id=${ownerId}&student_id=${studentId}`, {
                        method: 'GET'
                    }).then(response => {
                        console.log('Messages marked as read');
                    }).catch(error => {
                        console.error('Error marking messages as read:', error);
                    });
                }
            }
            
            // Handle conversation switching
            const messageItems = document.querySelectorAll('.message-item');
            messageItems.forEach(item => {
                item.addEventListener('click', function() {
                    const newOwnerId = this.dataset.ownerId;
                    const newStudentId = this.dataset.studentId;
                    
                    if (newOwnerId && newStudentId) {
                        window.location.href = `chat.php?owner_id=${newOwnerId}&student_id=${newStudentId}`;
                    }
                });
            });
        });
    </script>
</body>
</html>