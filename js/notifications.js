/**
 * Notifications System for UniHousing Platform
 * Handles fetching and displaying notifications in the header
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize notifications
    initNotifications();
    
    // Set up notification polling (every 15 seconds)
    setInterval(fetchNotifications, 15000);
});

/**
 * Initialize notifications system
 */
function initNotifications() {
    // Add click handler for notification icon
    const notificationIcon = document.getElementById('notificationIcon');
    if (notificationIcon) {
        notificationIcon.addEventListener('click', toggleNotificationsPanel);
    }
    
    // Add click handler for "Mark all as read" button
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', markAllNotificationsAsRead);
    }
    
    // Initial fetch of notifications
    fetchNotifications();
}

/**
 * Fetch notifications from the server
 */
function fetchNotifications() {
    fetch('notifications_api.php?action=get')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.unread_count);
                updateNotificationsPanel(data.notifications);
            } else {
                console.error('Error fetching notifications:', data.error);
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

/**
 * Update the notification badge with the unread count
 */
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
            
            // Add animation class if not already present
            if (!badge.classList.contains('pulse')) {
                badge.classList.add('pulse');
            }
        } else {
            badge.style.display = 'none';
            badge.classList.remove('pulse');
        }
    }
}

/**
 * Update the notifications panel with the latest notifications
 */
function updateNotificationsPanel(notifications) {
    const notificationsList = document.getElementById('notificationsList');
    if (!notificationsList) return;
    
    // Clear existing notifications
    notificationsList.innerHTML = '';
    
    if (notifications.length === 0) {
        // Show empty state
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-notifications';
        emptyState.innerHTML = `
            <i class="fas fa-bell-slash"></i>
            <p>No notifications yet</p>
        `;
        notificationsList.appendChild(emptyState);
        return;
    }
    
    // Add each notification to the panel
    notifications.forEach(notification => {
        const notificationItem = document.createElement('div');
        notificationItem.className = 'notification-item';
        if (!notification.is_read) {
            notificationItem.classList.add('unread');
        }
        
        // Determine icon based on notification type
        let icon = 'fa-bell';
        if (notification.notification_type === 'new_message') {
            icon = 'fa-envelope';
        } else if (notification.notification_type === 'property_update') {
            icon = 'fa-home';
        }
        
        notificationItem.innerHTML = `
            <div class="notification-icon">
                <i class="fas ${icon}"></i>
            </div>
            <div class="notification-content">
                <p>${notification.message}</p>
                <span class="notification-time">${notification.time_ago}</span>
            </div>
            ${!notification.is_read ? '<span class="unread-indicator"></span>' : ''}
        `;
        
        // Add click handler to mark as read and navigate if needed
        notificationItem.addEventListener('click', () => {
            console.log('Notification clicked:', notification);
            markNotificationAsRead(notification.notification_id);
            
            // Navigate to related content if applicable
            if (notification.notification_type === 'new_message') {
                if (notification.related_id) {
                    console.log('Fetching message details for ID:', notification.related_id);
                    
                    // Get the message details to extract owner_id and student_id
                    fetch(`get_message_details.php?message_id=${notification.related_id}`)
                        .then(response => {
                            console.log('Response status:', response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log('Message details response:', data);
                            if (data.success) {
                                // Redirect to chat.php with the appropriate parameters
                                const chatUrl = `chat.php?owner_id=${data.owner_id}&student_id=${data.student_id}`;
                                console.log('Redirecting to:', chatUrl);
                                window.location.href = chatUrl;
                            } else {
                                // Fallback to profile messages if there's an error
                                console.error('Error fetching message details:', data.error);
                                window.location.href = 'Profile.php?section=messages';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching message details:', error);
                            window.location.href = 'Profile.php?section=messages';
                        });
                }
            }
        });
        
        notificationsList.appendChild(notificationItem);
    });
}

/**
 * Toggle the notifications panel visibility
 */
function toggleNotificationsPanel() {
    const panel = document.getElementById('notificationsPanel');
    if (panel) {
        panel.classList.toggle('show');
        
        // If opening the panel, fetch fresh notifications
        if (panel.classList.contains('show')) {
            fetchNotifications();
        }
    }
}

/**
 * Mark a notification as read
 */
function markNotificationAsRead(notificationId) {
    fetch(`notifications_api.php?action=mark_read&notification_id=${notificationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh notifications
                fetchNotifications();
            } else {
                console.error('Error marking notification as read:', data.error);
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead() {
    fetch('notifications_api.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh notifications
                fetchNotifications();
            } else {
                console.error('Error marking all notifications as read:', data.error);
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
}

// Handle mobile notifications
const mobileNotificationIcon = document.getElementById("mobileNotificationIcon");
const mobileNotificationBadge = document.getElementById("mobileNotificationBadge");

// Update both desktop and mobile notification badges
function updateNotificationBadges(count) {
    // Update desktop badge
    if (notificationBadge) {
        if (count > 0) {
            notificationBadge.textContent = count;
            notificationBadge.style.display = "flex";
        } else {
            notificationBadge.style.display = "none";
        }
    }
    
    // Update mobile badge
    if (mobileNotificationBadge) {
        if (count > 0) {
            mobileNotificationBadge.textContent = count;
            mobileNotificationBadge.style.display = "flex";
        } else {
            mobileNotificationBadge.style.display = "none";
        }
    }
}

// Add click handler for mobile notification icon
if (mobileNotificationIcon) {
    mobileNotificationIcon.addEventListener("click", function(e) {
        e.stopPropagation();
        
        // Toggle the notifications panel
        if (notificationsPanel) {
            notificationsPanel.classList.toggle("show");
            
            // Load notifications if panel is shown
            if (notificationsPanel.classList.contains("show")) {
                loadNotifications();
            }
        }
    });
}
