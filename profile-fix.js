// Fix for View Details buttons in the Profile page
document.addEventListener('DOMContentLoaded', function() {
    // Find all View Details buttons in the Profile page
    const viewDetailsButtons = document.querySelectorAll('.property-actions button, .property-actions .btn-outline');
    
    // Add click event listeners to each button
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Get the property ID from the hidden input or form
            const form = this.closest('form');
            if (form && form.action.includes('property.php')) {
                const propertyId = form.querySelector('input[name="id"]').value;
                // Navigate directly to the property page
                window.location.href = 'property.php?id=' + propertyId;
            }
        });
    });
    
    console.log('Profile page View Details fix loaded');
});
