/**
 * Chat notifications system for UniHousing platform
 * Handles real-time checking for new messages and updating notification badges
 */

// Check if notification badges exist in the DOM
const hasNotificationBadges = document.querySelectorAll('.notification-badge').length > 0;

// Initialize notification checking if user is logged in
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if the user is logged in (check if logout button exists)
    const logoutButton = document.querySelector('a[href="logout.php"]');
    
    if (logoutButton) {
        // Start checking for new messages
        initNotificationChecking();
    }
});

/**
 * Initialize periodic checking for new messages
 */
function initNotificationChecking() {
    // Check immediately on page load
    checkForNewMessages();
    
    // Then check periodically (every 30 seconds)
    setInterval(checkForNewMessages, 30000);
}

/**
 * Check for new unread messages via AJAX
 */
function checkForNewMessages() {
    fetch('get_unread_count.php', {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            console.log('Error checking messages:', data.error);
            return;
        }
        
        // Update all notification badges
        updateNotificationBadges(data.count);
    })
    .catch(error => {
        console.error('Error checking for new messages:', error);
    });
}

/**
 * Update all notification badges with the current count
 * @param {number} count - Number of unread messages
 */
function updateNotificationBadges(count) {
    // Get all notification badge containers (profile links)
    const profileLinks = document.querySelectorAll('a[href="Profile.php"]');
    
    profileLinks.forEach(link => {
        // Find existing badge or create a new one
        let badge = link.querySelector('.notification-badge');
        
        if (count > 0) {
            // If there are unread messages
            if (badge) {
                // Update existing badge
                badge.textContent = count;
                badge.style.display = 'inline-flex';
            } else {
                // Create new badge
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                badge.textContent = count;
                link.appendChild(badge);
            }
            
            // Add animation to draw attention if the count increased
            if (parseInt(badge.getAttribute('data-previous-count') || 0) < count) {
                badge.classList.add('notification-pulse');
                setTimeout(() => {
                    badge.classList.remove('notification-pulse');
                }, 1000);
            }
            
            // Store current count for comparison next time
            badge.setAttribute('data-previous-count', count);
        } else if (badge) {
            // Hide badge if no unread messages
            badge.style.display = 'none';
        }
    });
}
