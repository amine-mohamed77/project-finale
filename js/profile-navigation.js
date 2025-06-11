document.addEventListener('DOMContentLoaded', function() {
    // Get all menu items and content sections
    const menuItems = document.querySelectorAll('.profile-nav li a');
    const contentSections = document.querySelectorAll('.profile-content > .profile-section');

    // Function to show a specific section and hide others
    function showSection(sectionId) {
        // Hide all sections first
        contentSections.forEach(section => {
            section.style.display = 'none';
            section.classList.remove('active');
        });

        // Show the selected section
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.style.display = 'block';
            targetSection.classList.add('active');
        }

        // Update active state in menu
        menuItems.forEach(item => {
            item.parentElement.classList.remove('active');
            if (item.getAttribute('data-section') === sectionId) {
                item.parentElement.classList.add('active');
            }
        });
    }

    // Add click event listeners to menu items
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const sectionId = this.getAttribute('data-section');
            showSection(sectionId);

            // For mobile: if there's a mobile menu, close it
            const mobileMenu = document.querySelector('.mobile-menu');
            if (mobileMenu && mobileMenu.classList.contains('active')) {
                mobileMenu.classList.remove('active');
            }
        });
    });

    // Show dashboard section by default
    showSection('dashboard');
});
