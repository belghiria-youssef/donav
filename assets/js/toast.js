/**
 * Toast Notification System
 * Based on Figma Design - Professional Toast Component
 * 
 * Usage:
 *   showToast('Your message here', 'success');
 *   showToast('Error occurred', 'error');
 *   showToast('Warning!', 'warning');
 *   showToast('Information', 'info');
 */

// Initialize toast container on page load
document.addEventListener('DOMContentLoaded', function() {
    if (!document.querySelector('.toast-container')) {
        const container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
});

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - Toast type: 'success', 'error', 'warning', 'info'
 * @param {number} duration - Duration in milliseconds (default: 4000)
 */
function showToast(message, type = 'info', duration = 4000) {
    // Ensure container exists
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Get icon SVG based on type
    const icon = getToastIcon(type);
    
    // Build toast HTML
    toast.innerHTML = `
        <div class="toast-icon">
            ${icon}
        </div>
        <div class="toast-message">${message}</div>
        <button class="toast-close" onclick="removeToast(this.parentElement)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    `;
    
    // Add to container
    container.appendChild(toast);
    
    // Auto-remove after duration
    if (duration > 0) {
        setTimeout(() => {
            removeToast(toast);
        }, duration);
    }
    
    return toast;
}

/**
 * Remove a toast notification
 * @param {HTMLElement} toast - The toast element to remove
 */
function removeToast(toast) {
    if (!toast || !toast.classList.contains('toast')) return;
    
    toast.classList.add('removing');
    setTimeout(() => {
        if (toast.parentElement) {
            toast.parentElement.removeChild(toast);
        }
    }, 300);
}

/**
 * Get icon SVG for toast type
 * @param {string} type - Toast type
 * @returns {string} SVG icon HTML
 */
function getToastIcon(type) {
    const icons = {
        success: `
            <svg viewBox="0 0 32 32" fill="none">
                <circle cx="16" cy="16" r="13" fill="rgba(255,255,255,0.2)"/>
                <path d="M9 16.5L13.5 21L23 11.5" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `,
        error: `
            <svg viewBox="0 0 32 32" fill="none">
                <circle cx="16" cy="16" r="13" fill="rgba(255,255,255,0.2)"/>
                <path d="M16 10V17" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="16" cy="21.5" r="1.5" fill="white"/>
            </svg>
        `,
        warning: `
            <svg viewBox="0 0 32 32" fill="none">
                <path d="M16 3L29.5 27H2.5L16 3Z" fill="rgba(255,255,255,0.2)"/>
                <path d="M16 12V18" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="16" cy="22" r="1.5" fill="white"/>
            </svg>
        `,
        info: `
            <svg viewBox="0 0 32 32" fill="none">
                <circle cx="16" cy="16" r="13" fill="rgba(255,255,255,0.2)"/>
                <path d="M16 15V22" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                <circle cx="16" cy="11" r="1.5" fill="white"/>
            </svg>
        `
    };
    
    return icons[type] || icons.info;
}

/**
 * Clear all toasts
 */
function clearAllToasts() {
    const container = document.querySelector('.toast-container');
    if (container) {
        const toasts = container.querySelectorAll('.toast');
        toasts.forEach(toast => removeToast(toast));
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { showToast, removeToast, clearAllToasts };
}
