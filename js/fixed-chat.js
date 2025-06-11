/**
 * UniHousing Chat System
 * A real-time chat system for the UniHousing platform
 */

// Global variables
let ownerId;
let studentId;
let userType;
let messageThreadSelector;
let messageFormSelector;
let messageInputSelector;
let imageUploadSelector;
let imagePreviewContainerSelector;
let imagePreviewSelector;
let removeImageSelector;
let selectedImage = null;
let lastMessageId = 0;
let isPolling = false;
let pollingInterval;
let retryCount = 0;
let maxRetries = 3;

/**
 * Initialize chat functionality with configuration options
 * @param {Object} config - Configuration object
 */
function initChat(config) {
    // Set configuration values
    ownerId = config.ownerId;
    studentId = config.studentId;
    userType = config.userType;
    messageThreadSelector = config.messageThreadSelector || '.message-thread';
    messageFormSelector = config.messageFormSelector || '#message-form';
    messageInputSelector = config.messageInputSelector || '#message-input';
    imageUploadSelector = config.imageUploadSelector || '#image-upload';
    imagePreviewContainerSelector = config.imagePreviewContainerSelector || '#image-preview-container';
    imagePreviewSelector = config.imagePreviewSelector || '#image-preview';
    removeImageSelector = config.removeImageSelector || '#remove-image';
    
    console.log('Chat initialized with:', { ownerId, studentId, userType });
    
    // Initialize sidebar toggle for mobile devices
    initSidebarToggle();
    
    // Initialize message form submission
    const messageForm = document.querySelector(messageFormSelector);
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
        
        // Initialize image upload
        const imageUpload = document.querySelector(imageUploadSelector);
        const imagePreviewContainer = document.querySelector(imagePreviewContainerSelector);
        const imagePreview = document.querySelector(imagePreviewSelector);
        const removeImage = document.querySelector(removeImageSelector);
        
        if (imageUpload && imagePreviewContainer && imagePreview && removeImage) {
            // Handle image selection
            imageUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreviewContainer.style.display = 'flex';
                        selectedImage = file;
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Handle image removal
            removeImage.addEventListener('click', function() {
                imagePreviewContainer.style.display = 'none';
                imagePreview.src = '#';
                imageUpload.value = '';
                selectedImage = null;
            });
        } else {
            console.error('Image upload elements not found');
        }
    } else {
        console.error('Message form not found:', messageFormSelector);
    }
    
    // Load initial messages and hide loading indicator when done
    loadInitialMessages();
    
    // Start polling for new messages
    startMessagePolling();
    
    // Initialize conversation switching (if in sidebar)
    initConversationSwitching();
}

/**
 * Initialize conversation switching in the sidebar
 */
function initConversationSwitching() {
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
}

/**
 * Send a message
 */
function sendMessage() {
    const messageInput = document.querySelector(messageInputSelector);
    const messageText = messageInput.value.trim();
    const imagePreviewContainer = document.querySelector(imagePreviewContainerSelector);
    
    // Check if there's either text or an image to send
    if (!messageText && !selectedImage) return;
    
    // Clear input field immediately
    messageInput.value = '';
    
    // Create temporary message element
    const tempId = 'temp-' + Date.now();
    const now = new Date();
    const formattedTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    
    const tempMessage = createMessageElement({
        message_id: tempId,
        message_text: messageText,
        image_url: selectedImage ? URL.createObjectURL(selectedImage) : null,
        sender_type: userType,
        is_temp: true,
        formatted_time: formattedTime
    });
    
    // Add to message thread
    const messageThread = document.querySelector(messageThreadSelector);
    messageThread.appendChild(tempMessage);
    messageThread.scrollTop = messageThread.scrollHeight;
    
    // Remove any "no messages yet" placeholder
    const noMessagesYet = messageThread.querySelector('.no-messages-yet');
    if (noMessagesYet) {
        noMessagesYet.remove();
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('owner_id', ownerId);
    formData.append('student_id', studentId);
    
    // If there's a message, add it
    if (messageText) {
        formData.append('message', messageText);
    }
    
    // If there's an image, add it and use the image upload endpoint
    let endpoint = 'send_message.php';
    if (selectedImage) {
        formData.append('image', selectedImage);
        endpoint = 'upload_chat_image.php';
        
        // Reset the image preview
        imagePreviewContainer.style.display = 'none';
        document.querySelector(imagePreviewSelector).src = '#';
        document.querySelector(imageUploadSelector).value = '';
        selectedImage = null;
    }
    
    // Send message to server
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Message sent successfully:', data);
        
        if (data.success && data.message) {
            // Replace temporary message with confirmed message
            const tempMessageElement = document.getElementById(tempId);
            if (tempMessageElement) {
                tempMessageElement.id = 'message-' + data.message.message_id;
                tempMessageElement.classList.remove('temp');
                const statusElement = tempMessageElement.querySelector('.message-status');
                if (statusElement) {
                    statusElement.innerHTML = '<i class="fas fa-check"></i>';
                    // Fade out status after 2 seconds
                    setTimeout(() => {
                        statusElement.style.opacity = '0';
                        setTimeout(() => {
                            statusElement.remove();
                        }, 300);
                    }, 2000);
                }
                
                // Update avatar if provided
                if (data.message.profile_pic) {
                    const avatarImg = tempMessageElement.querySelector('.avatar img');
                    if (avatarImg) {
                        avatarImg.src = data.message.profile_pic;
                    }
                }
            }
            
            // Update last message ID
            if (data.message.message_id && parseInt(data.message.message_id) > lastMessageId) {
                lastMessageId = parseInt(data.message.message_id);
            }
        } else {
            console.error('Error in server response:', data);
            // Mark message as failed
            const tempMessageElement = document.getElementById(tempId);
            if (tempMessageElement) {
                tempMessageElement.classList.add('failed');
                const statusElement = tempMessageElement.querySelector('.message-status');
                if (statusElement) {
                    statusElement.innerHTML = '<i class="fas fa-exclamation-circle"></i> Failed';
                }
            }
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        
        // Mark message as failed
        const tempMessageElement = document.getElementById(tempId);
        if (tempMessageElement) {
            tempMessageElement.classList.add('failed');
            const statusElement = tempMessageElement.querySelector('.message-status');
            if (statusElement) {
                statusElement.innerHTML = '<i class="fas fa-exclamation-circle"></i> Failed to send';
            }
        }
    });
}

/**
 * Create a message element
 * @param {Object} message - Message data
 * @returns {HTMLElement} - Message element
 */
function createMessageElement(message) {
    const isSent = message.sender_type === userType;
    const messageElement = document.createElement('div');
    messageElement.id = message.is_temp ? message.message_id : 'message-' + message.message_id;
    messageElement.className = `message-bubble ${isSent ? 'outgoing' : 'incoming'}`;
    if (message.is_temp) {
        messageElement.classList.add('temp');
    }
    
    // Default profile picture
    const defaultProfilePic = 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';
    
    // FIXED: Use local time without timezone specification
    // This ensures the time matches the user's local time
    const now = new Date();
    const timeDisplay = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        hour12: true
        // No timeZone specified = use local time
    });
    
    // Create message HTML
    let messageContent = '';
    
    // Add message text if present
    if (message.message_text && message.message_text.trim() !== '') {
        messageContent += `<p class="message-text">${message.message_text.replace(/\n/g, '<br>')}</p>`;
    }
    
    // Add image if present
    if (message.image_url) {
        messageContent += `
            <div class="message-image-container">
                <img src="${message.image_url}" alt="Chat image" class="message-image" 
                     onclick="window.open(this.src, '_blank')" 
                     onerror="this.onerror=null; this.src='images/image-placeholder.png'; console.error('Failed to load image: ' + this.src);">
            </div>
        `;
    }
    
    messageElement.innerHTML = `
        <div class="avatar">
            <img src="${message.profile_pic || defaultProfilePic}" alt="${message.sender_type}">
        </div>
        <div class="bubble-content">
            ${messageContent}
            <div class="message-meta">
                <span class="message-time">${timeDisplay}</span>
                ${message.is_temp ? '<span class="message-status"><i class="fas fa-clock"></i> Sending...</span>' : ''}
            </div>
        </div>
    `;
    
    return messageElement;
}

/**
 * Format time from ISO string to readable format
 * @param {string} dateString - Date string
 * @returns {string} - Formatted time
 */
function formatTime(dateString) {
    try {
        // Make sure we have a valid date string
        if (!dateString) {
            const now = new Date();
            return formatTimeFromDate(now);
        }
        
        const date = new Date(dateString);
        
        // Check if date is valid
        if (isNaN(date.getTime())) {
            const now = new Date();
            return formatTimeFromDate(now);
        }
        
        return formatTimeFromDate(date);
    } catch (e) {
        console.error('Error formatting time:', e);
        const now = new Date();
        return formatTimeFromDate(now);
    }
}

/**
 * Helper function to format time from a Date object
 * @param {Date} date - Date object
 * @returns {string} - Formatted time
 */
function formatTimeFromDate(date) {
    try {
        // Check if date is valid before using it
        if (!date || isNaN(date.getTime())) {
            // If date is invalid, use current time
            date = new Date();
        }
        
        // Use toLocaleTimeString for more reliable formatting
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    } catch (e) {
        console.error('Error in formatTimeFromDate:', e);
        // Fallback to a safe default format
        const now = new Date();
        return now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
}

/**
 * Start polling for new messages
 */
function startMessagePolling() {
    if (isPolling) return;
    
    isPolling = true;
    pollingInterval = setInterval(fetchNewMessages, 1000); // Poll every second
    
    console.log('Started message polling');
}

/**
 * Stop polling for new messages
 */
function stopMessagePolling() {
    if (!isPolling) return;
    
    clearInterval(pollingInterval);
    isPolling = false;
    
    console.log('Stopped message polling');
}

/**
 * Load initial messages and hide the loading indicator
 */
function loadInitialMessages() {
    if (!ownerId || !studentId) {
        console.error('Owner ID or Student ID not set');
        return;
    }
    
    const url = `get_messages.php?owner_id=${ownerId}&student_id=${studentId}&last_id=0`;
    const messageThread = document.querySelector(messageThreadSelector);
    const loadingElement = messageThread.querySelector('.loading-messages');
    
    fetch(url)
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        // Hide loading indicator
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }
        
        if (data.messages && data.messages.length > 0) {
            console.log('Loaded initial messages:', data.messages.length);
            
            // Process each message
            data.messages.forEach(message => {
                // Create message element
                const messageElement = createMessageElement({
                    message_id: message.message_id,
                    message_text: message.message_text,
                    image_url: message.image_url || null,
                    sender_type: message.sender_type,
                    formatted_time: formatTime(message.message_date),
                    profile_pic: message.profile_pic
                });
                
                // Add message to thread
                messageThread.appendChild(messageElement);
                
                // Update last message ID
                if (parseInt(message.message_id) > lastMessageId) {
                    lastMessageId = parseInt(message.message_id);
                }
            });
            
            // Scroll to bottom
            messageThread.scrollTop = messageThread.scrollHeight;
        } else {
            // Show no messages yet placeholder
            const noMessagesElement = document.createElement('div');
            noMessagesElement.className = 'no-messages-yet';
            noMessagesElement.innerHTML = '<p>No messages yet. Start the conversation!</p>';
            messageThread.appendChild(noMessagesElement);
        }
    })
    .catch(error => {
        console.error('Error loading initial messages:', error);
        
        // Hide loading indicator and show error
        if (loadingElement) {
            loadingElement.innerHTML = '<p>Error loading messages. Please refresh the page.</p>';
        }
    });
}

/**
 * Fetch new messages from the server
 */
function fetchNewMessages() {
    if (!ownerId || !studentId) {
        console.error('Owner ID or Student ID not set');
        return;
    }
    
    const url = `get_messages.php?owner_id=${ownerId}&student_id=${studentId}&last_id=${lastMessageId}`;
    
    fetch(url)
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.messages && data.messages.length > 0) {
            console.log('Received new messages:', data.messages.length);
            
            // Add new messages to thread
            const messageThread = document.querySelector(messageThreadSelector);
            
            // Remove any "no messages yet" placeholder
            const noMessagesYet = messageThread.querySelector('.no-messages-yet');
            if (noMessagesYet) {
                noMessagesYet.remove();
            }
            
            // Process each new message
            data.messages.forEach(message => {
                // Create message element
                const messageElement = createMessageElement({
                    message_id: message.message_id,
                    message_text: message.message_text,
                    image_url: message.image_url || null,
                    sender_type: message.sender_type,
                    formatted_time: formatTime(message.message_date),
                    profile_pic: message.profile_pic
                });
                
                // Add message to thread
                messageThread.appendChild(messageElement);
                
                // Update last message ID
                if (parseInt(message.message_id) > lastMessageId) {
                    lastMessageId = parseInt(message.message_id);
                }
            });
            
            // Scroll to bottom
            messageThread.scrollTop = messageThread.scrollHeight;
            
            // Reset retry count on success
            retryCount = 0;
        }
    })
    .catch(error => {
        console.error('Error fetching messages:', error);
        
        // Implement retry logic
        retryCount++;
        if (retryCount >= maxRetries) {
            console.error('Max retries reached, stopping polling');
            stopMessagePolling();
            
            // Show error message
            const messageThread = document.querySelector(messageThreadSelector);
            const errorElement = document.createElement('div');
            errorElement.className = 'chat-error';
            errorElement.innerHTML = `
                <p>Connection lost. <a href="#" onclick="location.reload(); return false;">Refresh</a> to reconnect.</p>
            `;
            messageThread.appendChild(errorElement);
        }
    });
}

// Export the initChat function for global use
window.initChat = initChat;

/**
 * Initialize sidebar toggle functionality for mobile devices
 */
function initSidebarToggle() {
    const toggleSidebarBtn = document.getElementById('toggleSidebar');
    const mobileToggleSidebarBtn = document.getElementById('mobileToggleSidebar');
    const chatSidebar = document.querySelector('.chat-sidebar');
    
    if (chatSidebar) {
        console.log('Sidebar toggle initialized');
        
        // Handle main toggle button
        if (toggleSidebarBtn) {
            toggleSidebarBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                chatSidebar.classList.toggle('sidebar-visible');
                console.log('Sidebar toggled from header button');
            });
        }
        
        // Handle mobile toggle button in chat main area
        if (mobileToggleSidebarBtn) {
            mobileToggleSidebarBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                chatSidebar.classList.toggle('sidebar-visible');
                console.log('Sidebar toggled from mobile button');
            });
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isMobile = window.innerWidth <= 576;
            const isClickInsideSidebar = chatSidebar.contains(event.target);
            const isClickOnToggleButton = (toggleSidebarBtn && toggleSidebarBtn.contains(event.target)) || 
                                         (mobileToggleSidebarBtn && mobileToggleSidebarBtn.contains(event.target));
            
            if (isMobile && !isClickInsideSidebar && !isClickOnToggleButton && chatSidebar.classList.contains('sidebar-visible')) {
                chatSidebar.classList.remove('sidebar-visible');
            }
        });
        
        // Close sidebar when window is resized to desktop size
        window.addEventListener('resize', function() {
            if (window.innerWidth > 576 && chatSidebar.classList.contains('sidebar-visible')) {
                chatSidebar.classList.remove('sidebar-visible');
            }
        });
    }
}