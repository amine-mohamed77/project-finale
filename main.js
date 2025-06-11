document.addEventListener("DOMContentLoaded", () => {
  // Get elements by ID to ensure we're targeting the right elements
  const mobileMenuToggle = document.getElementById("mobileMenuToggle")
  const mobileMenu = document.getElementById("mobileMenu")
  const mobileMenuClose = document.getElementById("mobileMenuClose")

  // Toggle mobile menu
  if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener("click", () => {
      console.log("Toggle button clicked")
      if (mobileMenu) {
        mobileMenu.classList.add("active")
        document.body.style.overflow = "hidden" // Prevent scrolling
      }
    })
  }

  // Close mobile menu
  if (mobileMenuClose) {
    mobileMenuClose.addEventListener("click", () => {
      console.log("Close button clicked")
      if (mobileMenu) {
        mobileMenu.classList.remove("active")
        document.body.style.overflow = "" // Enable scrolling
      }
    })
  }

  // Close mobile menu when clicking on links
  const mobileMenuLinks = document.querySelectorAll(".mobile-menu-nav a")
  mobileMenuLinks.forEach((link) => {
    link.addEventListener("click", () => {
      console.log("Menu link clicked")
      if (mobileMenu) {
        mobileMenu.classList.remove("active")
        document.body.style.overflow = "" // Enable scrolling
      }
    })
  })

  // Close mobile menu when clicking on auth buttons
  const mobileAuthButtons = document.querySelectorAll(".mobile-menu-auth a")
  mobileAuthButtons.forEach((button) => {
    button.addEventListener("click", () => {
      console.log("Auth button clicked")
      if (mobileMenu) {
        mobileMenu.classList.remove("active")
        document.body.style.overflow = "" // Enable scrolling
      }
    })
  })

  // Favorite Button Toggle
  const favoriteButtons = document.querySelectorAll(".favorite-btn")

  favoriteButtons.forEach((button) => {
    // Skip any buttons that are not actually favorite buttons (e.g., View Details buttons)
    if (!button.classList.contains('favorite-btn')) return;
    
    button.addEventListener("click", function (e) {
      e.preventDefault()
      
      // Get the property ID from the data attribute
      const propertyId = this.getAttribute('data-property-id');
      if (!propertyId) {
        console.error('No property ID found on favorite button');
        return;
      }
      
      // Toggle active class visually
      this.classList.toggle("active")
      
      // Determine if we're adding or removing from favorites
      if (this.classList.contains("active")) {
        // Update the icon immediately for better UX
        this.innerHTML = '<i class="fas fa-heart"></i>'
        
        // Send request to add to favorites
        fetch(`add_favorite.php?id=${propertyId}`)
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            console.log('Property added to favorites');
            return response.text();
          })
          .catch(error => {
            console.error('Error adding to favorites:', error);
            // Revert UI if there was an error
            this.classList.remove("active");
            this.innerHTML = '<i class="far fa-heart"></i>';
          });
      } else {
        // Update the icon immediately for better UX
        this.innerHTML = '<i class="far fa-heart"></i>'
        
        // Send request to remove from favorites
        fetch(`remove_favorite.php?id=${propertyId}`)
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            console.log('Property removed from favorites');
            return response.text();
          })
          .catch(error => {
            console.error('Error removing from favorites:', error);
            // Revert UI if there was an error
            this.classList.add("active");
            this.innerHTML = '<i class="fas fa-heart"></i>';
          });
      }
    })
  })

  // Testimonial Slider
  const testimonials = document.querySelectorAll(".testimonial")
  const dots = document.querySelectorAll(".dot")
  let currentSlide = 0

  function showSlide(index) {
    // Hide all testimonials
    testimonials.forEach((testimonial) => {
      testimonial.style.display = "none"
    })

    // Remove active class from all dots
    dots.forEach((dot) => {
      dot.classList.remove("active")
    })

    // Show the current testimonial and activate the corresponding dot
    if (testimonials[index]) {
      testimonials[index].style.display = "block"
      dots[index].classList.add("active")
    }
  }

  // Initialize slider
  if (testimonials.length > 0) {
    showSlide(currentSlide)

    // Add click event to dots
    dots.forEach((dot, index) => {
      dot.addEventListener("click", () => {
        currentSlide = index
        showSlide(currentSlide)
      })
    })

    // Auto slide
    setInterval(() => {
      currentSlide = (currentSlide + 1) % testimonials.length
      showSlide(currentSlide)
    }, 5000)
  }

  // Form Validation
  const forms = document.querySelectorAll("form")

  forms.forEach((form) => {
    form.addEventListener("submit", (e) => {
      const requiredFields = form.querySelectorAll("[required]")
      let isValid = true

      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          isValid = false
          field.classList.add("error")

          // Create error message if it doesn't exist
          let errorMsg = field.nextElementSibling
          if (!errorMsg || !errorMsg.classList.contains("error-message")) {
            errorMsg = document.createElement("p")
            errorMsg.classList.add("error-message")
            errorMsg.textContent = "This field is required"
            field.parentNode.insertBefore(errorMsg, field.nextSibling)
          }
        } else {
          field.classList.remove("error")

          // Remove error message if it exists
          const errorMsg = field.nextElementSibling
          if (errorMsg && errorMsg.classList.contains("error-message")) {
            errorMsg.remove()
          }
        }
      })

      if (!isValid) {
        e.preventDefault()
      }
    })
  })
})

// URL Parameter Handling
function getUrlParameter(name) {
  name = name.replace(/[[]/, "\\[").replace(/[\]]/, "\\]")
  const regex = new RegExp("[\\?&]" + name + "=([^&#]*)")
  const results = regex.exec(location.search)
  return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "))
}

// Handle property details page
const propertyId = getUrlParameter("id")
if (propertyId && document.querySelector(".property-details")) {
  // In a real application, you would fetch property details from the server
  console.log("Loading property with ID:", propertyId)

  // For demo purposes, we'll just show a loading message
  const propertyContent = document.querySelector(".property-details-content")
  if (propertyContent) {
    propertyContent.innerHTML = '<div class="loading">Loading property details...</div>'

    // Simulate loading data
    setTimeout(() => {
      // In a real app, this would be replaced with actual data from the server
      propertyContent.innerHTML = '<div class="success">Property details loaded successfully!</div>'
    }, 1500)
  }
}

// Handle filter parameters
const filterParam = getUrlParameter("filter")
if (filterParam) {
  console.log("Filtering by:", filterParam)
  // In a real app, you would apply the filter to the property listings
}

// Property Details Page Functionality
document.addEventListener("DOMContentLoaded", () => {
  // Thumbnail Gallery
  const mainImage = document.querySelector(".main-image img")
  const thumbnails = document.querySelectorAll(".thumbnail")

  if (thumbnails.length > 0) {
    thumbnails.forEach((thumbnail) => {
      thumbnail.addEventListener("click", function () {
        // Update main image
        const newSrc = this.querySelector("img").src
        mainImage.src = newSrc

        // Update active thumbnail
        thumbnails.forEach((t) => t.classList.remove("active"))
        this.classList.add("active")
      })
    })
  }

  // Favorite Button
  const favoriteBtn = document.querySelector(".favorite-btn")
  if (favoriteBtn) {
    favoriteBtn.addEventListener("click", function () {
      this.classList.toggle("active")
      const icon = this.querySelector("i")
      if (this.classList.contains("active")) {
        icon.classList.remove("far")
        icon.classList.add("fas")
      } else {
        icon.classList.remove("fas")
        icon.classList.add("far")
      }
    })
  }
})
