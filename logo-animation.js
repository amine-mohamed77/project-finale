// Logo Animation and Preloader Script

document.addEventListener('DOMContentLoaded', function() {
    // Add 'home-page' class to body if we're on the homepage
    if (window.location.pathname.endsWith('home.php') || 
        window.location.pathname.endsWith('/') || 
        window.location.pathname.endsWith('index.php')) {
        document.body.classList.add('home-page');
    }
    
    // Handle preloader
    const preloader = document.querySelector('.preloader');
    if (preloader) {
        // Fade out preloader after content is loaded
        setTimeout(function() {
            preloader.classList.add('fade-out');
            
            // Remove preloader from DOM after animation completes
            setTimeout(function() {
                preloader.remove();
            }, 500);
        }, 2000); // 2 seconds delay to show animation
    }
});