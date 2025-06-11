/**
 * Chat Interface JavaScript for UniHousing Platform
 * Handles chat functionality including auto-scrolling and form submission
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to bottom of message thread
    const messageThread = document.getElementById('messageThread');
    if (messageThread) {
        messageThread.scrollTop = messageThread.scrollHeight;
    }

    // Handle message form submission
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            const messageInput = messageForm.querySelector('input[name="message"]');
            if (!messageInput.value.trim()) {
                e.preventDefault();
                return false;
            }
        });
    }

    // Handle message search functionality
    const searchInput = document.getElementById('searchMessages');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const messageItems = document.querySelectorAll('.message-item');
            
            messageItems.forEach(item => {
                const name = item.querySelector('h4').textContent.toLowerCase();
                const preview = item.querySelector('.message-preview').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || preview.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Mark messages as read when viewed
    function updateUnreadBadges() {
        // This will be triggered when a conversation is opened
        // The PHP code already handles marking messages as read in the database
        // This just updates the UI to reflect that
        
        // Get the current unread count from the server
        fetch('get_unread_count.php')
            .then(response => response.json())
            .then(data => {
                // Update all notification badges with the new count
                const badges = document.querySelectorAll('.notification-badge');
                badges.forEach(badge => {
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'inline-flex';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            })
            .catch(error => console.error('Error updating unread count:', error));
    }

    // Update unread badges when page loads
    updateUnreadBadges();

    // Set up polling for new messages (every 30 seconds)
    setInterval(updateUnreadBadges, 30000);
});
