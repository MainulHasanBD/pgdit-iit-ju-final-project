// Application-specific JavaScript

class CoachingCenterApp {
    constructor() {
        this.init();
    }
    
    init() {
        this.initSidebar();
        this.initDatePickers();
        this.initFileUploads();
        this.initCharts();
        this.setupAjaxForms();
    }
    
    initSidebar() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('show');
            });
            
            // Auto-hide sidebar on mobile when clicking outside
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(e.target) && 
                    !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });
        }
        
        // Show/hide toggle button based on screen size
        const updateSidebarToggle = () => {
            if (sidebarToggle) {
                if (window.innerWidth <= 768) {
                    sidebarToggle.classList.remove('d-none');
                } else {
                    sidebarToggle.classList.add('d-none');
                    sidebar.classList.remove('show');
                }
            }
        };
        
        window.addEventListener('resize', updateSidebarToggle);
        updateSidebarToggle();
    }
    
    initDatePickers() {
        // Simple date picker implementation
        const dateInputs = document.querySelectorAll('input[type="date"]');
        dateInputs.forEach(input => {
            if (!input.value && input.hasAttribute('data-default-today')) {
                input.value = new Date().toISOString().split('T')[0];
            }
        });
    }
    
    initFileUploads() {
        const fileAreas = document.querySelectorAll('.file-upload-area');
        fileAreas.forEach(area => {
            const input = area.parentElement.querySelector('input[type="file"]');
            if (!input) return;
            
            // Drag and drop
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.classList.add('dragover');
            });
            
            area.addEventListener('dragleave', () => {
                area.classList.remove('dragover');
            });
            
            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    this.handleFileSelection(input, files[0]);
                }
            });
            
            // Click to upload
            area.addEventListener('click', () => {
                input.click();
            });
            
            input.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.handleFileSelection(input, e.target.files[0]);
                }
            });
        });
    }
    
    handleFileSelection(input, file) {
        const area = input.parentElement.querySelector('.file-upload-area');
        const textEl = area.querySelector('.file-upload-text');
        
        if (textEl) {
            textEl.innerHTML = `
                <i class="fas fa-file"></i>
                Selected: ${file.name}
                <br>
                <small class="text-muted">${this.formatFileSize(file.size)}</small>
            `;
        }
        
        // Validate file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            showAlert('File size must be less than 5MB', 'danger');
            input.value = '';
            return;
        }
        
        // Show preview for images
        if (file.type.startsWith('image/')) {
            this.showImagePreview(file, area);
        }
    }
    
    showImagePreview(file, container) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const existingPreview = container.querySelector('.image-preview');
            if (existingPreview) {
                existingPreview.remove();
            }
            
            const preview = document.createElement('div');
            preview.className = 'image-preview mt-2';
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
            `;
            container.appendChild(preview);
        };
        reader.readAsDataURL(file);
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    initCharts() {
        // Initialize charts if Chart.js is loaded
        if (typeof Chart !== 'undefined') {
            this.initDashboardCharts();
        }
    }
    
    initDashboardCharts() {
        // Attendance chart
        const attendanceChart = document.getElementById('attendanceChart');
        if (attendanceChart) {
            new Chart(attendanceChart, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                    datasets: [{
                        label: 'Attendance Rate',
                        data: [95, 89, 92, 87, 94, 88],
                        borderColor: 'rgb(25, 118, 210)',
                        backgroundColor: 'rgba(25, 118, 210, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
        
        // Salary distribution chart
        const salaryChart = document.getElementById('salaryChart');
        if (salaryChart) {
            new Chart(salaryChart, {
                type: 'doughnut',
                data: {
                    labels: ['Basic Salary', 'Allowances', 'Deductions'],
                    datasets: [{
                        data: [70, 20, 10],
                        backgroundColor: [
                            'rgb(25, 118, 210)',
                            'rgb(76, 175, 80)',
                            'rgb(244, 67, 54)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }
    
    setupAjaxForms() {
        const ajaxForms = document.querySelectorAll('.ajax-form');
        ajaxForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleAjaxForm(form);
            });
        });
    }
    
    handleAjaxForm(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<span class="loading"></span> Processing...';
        submitBtn.disabled = true;
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message || 'Operation completed successfully', 'success');
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else if (data.reload) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                showAlert(data.message || 'An error occurred', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('An error occurred while processing your request', 'danger');
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }
    
    // Utility methods
    confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
    
    showLoading() {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = '<div class="loading"></div>';
        document.body.appendChild(loadingOverlay);
        return loadingOverlay;
    }
    
    hideLoading(overlay) {
        if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
    }
    
    formatCurrency(amount, currency = 'BDT') {
        return new Intl.NumberFormat('en-BD', {
            style: 'currency',
            currency: currency === 'BDT' ? 'BDT' : 'USD',
            minimumFractionDigits: 2
        }).format(amount);
    }
    
    formatDate(date, format = 'short') {
        const options = {
            short: { year: 'numeric', month: 'short', day: 'numeric' },
            long: { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' },
            time: { hour: '2-digit', minute: '2-digit' }
        };
        
        return new Intl.DateTimeFormat('en-US', options[format]).format(new Date(date));
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.app = new CoachingCenterApp();
    
    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('Global error:', e.error);
        showAlert('An unexpected error occurred', 'danger');
    });
});

// Export for use in other scripts
window.CoachingCenterApp = CoachingCenterApp;



// Enhanced Sidebar Functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
    
    // Prevent body scroll when sidebar is open on mobile
    if (window.innerWidth <= 768) {
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }
}

// Auto-hide sidebar on mobile when clicking a link
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    toggleSidebar();
                }, 150);
            }
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    // Smooth scrolling for sidebar
    const sidebarNav = document.querySelector('.sidebar-nav');
    if (sidebarNav) {
        sidebarNav.style.scrollBehavior = 'smooth';
    }
});

// Add ripple effect to nav links
function addRippleEffect() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

// Initialize ripple effect
document.addEventListener('DOMContentLoaded', addRippleEffect);
