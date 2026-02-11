// Main JavaScript file for Habit Tracker

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Toast notifications
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            // Create toast container if it doesn't exist
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1050';
            document.body.appendChild(container);
        }
        
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
        toast.show();
        
        // Remove toast from DOM after hiding
        toastElement.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    }
    
    // Check for messages in session
    const sessionMessage = document.querySelector('[data-session-message]');
    if (sessionMessage) {
        const message = sessionMessage.dataset.sessionMessage;
        const messageType = sessionMessage.dataset.sessionType || 'success';
        showToast(message, messageType);
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Password strength indicator
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        if (input.id.includes('password') || input.name.includes('password')) {
            input.addEventListener('input', function() {
                const strengthBar = this.parentElement.querySelector('.password-strength');
                if (strengthBar) {
                    const password = this.value;
                    let strength = 0;
                    
                    if (password.length >= 8) strength++;
                    if (/[A-Z]/.test(password)) strength++;
                    if (/[0-9]/.test(password)) strength++;
                    if (/[^A-Za-z0-9]/.test(password)) strength++;
                    
                    const colors = ['#dc3545', '#ffc107', '#17a2b8', '#28a745'];
                    const width = strength * 25;
                    
                    strengthBar.style.width = width + '%';
                    strengthBar.style.height = '5px';
                    strengthBar.style.backgroundColor = colors[strength - 1] || '#dc3545';
                    strengthBar.style.borderRadius = '2px';
                    strengthBar.style.transition = 'all 0.3s';
                }
            });
        }
    });
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Dark mode toggle
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-bs-theme') === 'dark';
            const icon = this.querySelector('i');
            
            if (isDark) {
                html.removeAttribute('data-bs-theme');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
                localStorage.setItem('darkMode', 'false');
            } else {
                html.setAttribute('data-bs-theme', 'dark');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                localStorage.setItem('darkMode', 'true');
            }
            
            // Send AJAX request to save preference
            fetch('update-dark-mode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ dark_mode: !isDark })
            });
        });
        
        // Load dark mode preference
        const darkModePref = localStorage.getItem('darkMode');
        if (darkModePref === 'true') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            const icon = darkModeToggle.querySelector('i');
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
    }
    
    // Auto-save forms
    const autoSaveForms = document.querySelectorAll('[data-auto-save]');
    autoSaveForms.forEach(form => {
        let saveTimeout;
        
        form.querySelectorAll('input, textarea, select').forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    saveForm(form);
                }, 1000);
            });
        });
    });
    
    function saveForm(form) {
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Changes saved automatically', 'info');
            }
        })
        .catch(error => {
            console.error('Auto-save error:', error);
        });
    }
    
    // Confirm before leaving unsaved changes
    window.addEventListener('beforeunload', function(event) {
        const unsavedForms = document.querySelectorAll('.needs-validation.was-validated');
        if (unsavedForms.length > 0) {
            event.preventDefault();
            event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    // Progress animations
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width || bar.getAttribute('aria-valuenow') + '%';
        bar.style.width = '0%';
        
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
    
    // Calendar functionality
    const calendarDays = document.querySelectorAll('.calendar-day');
    calendarDays.forEach(day => {
        day.addEventListener('click', function() {
            const date = this.dataset.date;
            if (date) {
                // Load habits for selected date
                loadHabitsForDate(date);
            }
        });
    });
    
    function loadHabitsForDate(date) {
        // AJAX call to load habits for specific date
        fetch(`get-habits.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                // Update UI with habits for selected date
                updateHabitList(data);
            });
    }
    
    // Chart initialization helper
    window.initChart = function(canvasId, config) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        return new Chart(ctx, config);
    };
    
    // Notification bell animation
    const notificationBell = document.querySelector('.notification-bell');
    if (notificationBell) {
        let animationCount = 0;
        const maxAnimations = 3;
        
        notificationBell.addEventListener('click', function() {
            if (animationCount < maxAnimations) {
                this.classList.add('ringing');
                setTimeout(() => {
                    this.classList.remove('ringing');
                }, 1000);
                animationCount++;
            }
        });
    }
    
    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (sidebar && sidebar.classList.contains('show') && 
            !sidebar.contains(event.target) && 
            !mobileMenuToggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    });
    
    // Load more functionality
    const loadMoreButtons = document.querySelectorAll('.load-more');
    loadMoreButtons.forEach(button => {
        button.addEventListener('click', function() {
            const target = this.dataset.target;
            const container = document.getElementById(target);
            const currentItems = container.querySelectorAll('.load-item:not(.d-none)').length;
            const totalItems = container.querySelectorAll('.load-item').length;
            const loadCount = parseInt(this.dataset.count) || 5;
            
            // Show next set of items
            for (let i = currentItems; i < currentItems + loadCount && i < totalItems; i++) {
                container.querySelectorAll('.load-item')[i].classList.remove('d-none');
            }
            
            // Hide button if all items are shown
            if (currentItems + loadCount >= totalItems) {
                this.classList.add('d-none');
            }
        });
    });
    
    // Initialize
    console.log('Habit Tracker initialized successfully!');
});

// Utility functions
window.HabitTracker = {
    // Format date
    formatDate: function(date, format = 'short') {
        const d = new Date(date);
        const options = {
            short: { year: 'numeric', month: 'short', day: 'numeric' },
            long: { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' },
            time: { hour: '2-digit', minute: '2-digit' }
        };
        
        return d.toLocaleDateString('en-US', options[format] || options.short);
    },
    
    // Calculate streak
    calculateStreak: function(habitId) {
        return fetch(`calculate-streak.php?habit_id=${habitId}`)
            .then(response => response.json())
            .then(data => data.streak);
    },
    
    // Mark habit as complete
    markHabitComplete: function(habitId) {
        return fetch('complete-habit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ habit_id: habitId })
        })
        .then(response => response.json());
    },
    
    // Get calorie summary
    getCalorieSummary: function(date) {
        return fetch(`get-calorie-summary.php?date=${date}`)
            .then(response => response.json());
    },
    
    // Show loading spinner
    showLoading: function(element) {
        const spinner = document.createElement('div');
        spinner.className = 'spinner-container';
        spinner.innerHTML = '<div class="spinner"></div>';
        element.innerHTML = '';
        element.appendChild(spinner);
    },
    
    // Hide loading spinner
    hideLoading: function(element, content) {
        element.innerHTML = content;
    }
};