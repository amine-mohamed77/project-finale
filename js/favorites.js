/**
 * Favorites System for UniHousing Platform
 * Handles adding/removing favorites with visual feedback
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize favorites functionality
    initFavorites();
});

/**
 * Initialize favorites system
 */
function initFavorites() {
    // Add click handlers for all favorite buttons
    const favoriteButtons = document.querySelectorAll('.favorite-btn[data-property-id]');
    
    favoriteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Skip if this is a login link
            if (this.getAttribute('href') === 'login.php') return;
            
            const propertyId = this.dataset.propertyId;
            const isFavorite = this.classList.contains('active');
            
            if (isFavorite) {
                removeFavorite(propertyId, this);
            } else {
                addFavorite(propertyId, this);
            }
        });
    });
}

/**
 * Add a property to favorites
 */
function addFavorite(propertyId, button) {
    fetch('ajax_favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'house_id=' + propertyId + '&action=add'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button appearance
            button.classList.add('active');
            button.querySelector('i').classList.remove('far');
            button.querySelector('i').classList.add('fas');
            
            // Show notification
            showFavoriteNotification('Property added to favorites');
        } else if (data.redirect) {
            window.location.href = data.redirect;
        } else {
            showFavoriteNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error adding favorite:', error);
        showFavoriteNotification('An error occurred. Please try again.', 'error');
    });
}

/**
 * Remove a property from favorites
 */
function removeFavorite(propertyId, button) {
    fetch('ajax_favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'house_id=' + propertyId + '&action=remove'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button appearance
            button.classList.remove('active');
            button.querySelector('i').classList.remove('fas');
            button.querySelector('i').classList.add('far');
            
            // Show notification
            showFavoriteNotification('Property removed from favorites');
        } else {
            showFavoriteNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error removing favorite:', error);
        showFavoriteNotification('An error occurred. Please try again.', 'error');
    });
}

/**
 * Show a notification for favorite actions
 */
function showFavoriteNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'favorite-notification ' + type;
    notification.textContent = message;
    
    // Style the notification to match the screenshot
    notification.style.position = 'fixed';
    notification.style.right = '20px';
    notification.style.top = '80px';
    notification.style.backgroundColor = type === 'success' ? '#4CAF50' : '#F44336';
    notification.style.color = 'white';
    notification.style.padding = '12px 20px';
    notification.style.borderRadius = '4px';
    notification.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
    notification.style.zIndex = '9999';
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-20px)';
    notification.style.transition = 'all 0.3s ease';
    
    // Add to body
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateY(0)';
    }, 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}
