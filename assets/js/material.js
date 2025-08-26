// Material Design JavaScript Components

class MaterialDropdown {
    constructor(element) {
        this.element = element;
        this.toggle = element.querySelector('.dropdown-toggle');
        this.menu = element.querySelector('.dropdown-menu');
        this.isOpen = false;
        
        this.init();
    }
    
    init() {
        this.toggle.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleDropdown();
        });
        
        document.addEventListener('click', (e) => {
            if (!this.element.contains(e.target)) {
                this.closeDropdown();
            }
        });
    }
    
    toggleDropdown() {
        if (this.isOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }
    
    openDropdown() {
        this.menu.style.display = 'block';
        this.isOpen = true;
        this.element.classList.add('show');
    }
    
    closeDropdown() {
        this.menu.style.display = 'none';
        this.isOpen = false;
        this.element.classList.remove('show');
    }
}

class MaterialModal {
    constructor(element) {
        this.element = element;
        this.init();
    }
    
    init() {
        const closeButtons = this.element.querySelectorAll('[data-dismiss="modal"]');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => this.hide());
        });
        
        this.element.addEventListener('click', (e) => {
            if (e.target === this.element) {
                this.hide();
            }
        });
    }
    
    show() {
        this.element.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    hide() {
        this.element.classList.remove('show');
        document.body.style.overflow = '';
    }
}

class MaterialAlert {
    constructor(element) {
        this.element = element;
        this.init();
    }
    
    init() {
        const closeButton = this.element.querySelector('[data-dismiss="alert"]');
        if (closeButton) {
            closeButton.addEventListener('click', () => this.hide());
        }
    }
    
    hide() {
        this.element.style.opacity = '0';
        setTimeout(() => {
            this.element.remove();
        }, 300);
    }
}

class MaterialTabs {
    constructor(element) {
        this.element = element;
        this.tabs = element.querySelectorAll('.tab-link');
        this.contents = element.querySelectorAll('.tab-content');
        this.init();
    }
    
    init() {
        this.tabs.forEach((tab, index) => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.showTab(index);
            });
        });
    }
    
    showTab(index) {
        // Remove active class from all tabs and contents
        this.tabs.forEach(tab => tab.classList.remove('active'));
        this.contents.forEach(content => content.classList.remove('active'));
        
        // Add active class to selected tab and content
        this.tabs[index].classList.add('active');
        this.contents[index].classList.add('active');
    }
}

// Form validation
class FormValidator {
    constructor(form) {
        this.form = form;
        this.init();
    }
    
    init() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
            }
        });
        
        // Real-time validation
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearError(input));
        });
    }
    
    validate() {
        let isValid = true;
        const inputs = this.form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        // Required validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            message = 'This field is required';
        }
        
        // Email validation
        if (field.type === 'email' && value && !this.isValidEmail(value)) {
            isValid = false;
            message = 'Please enter a valid email address';
        }
        
        // Phone validation
        if (field.type === 'tel' && value && !this.isValidPhone(value)) {
            isValid = false;
            message = 'Please enter a valid phone number';
        }
        
        // Password validation
        if (field.type === 'password' && value && value.length < 8) {
            isValid = false;
            message = 'Password must be at least 8 characters long';
        }
        
        this.setFieldStatus(field, isValid, message);
        return isValid;
    }
    
    setFieldStatus(field, isValid, message) {
        const formGroup = field.closest('.form-group');
        const feedback = formGroup.querySelector('.invalid-feedback');
        
        if (isValid) {
            field.classList.remove('is-invalid');
            if (feedback) feedback.remove();
        } else {
            field.classList.add('is-invalid');
            if (!feedback) {
                const feedbackEl = document.createElement('div');
                feedbackEl.className = 'invalid-feedback';
                feedbackEl.textContent = message;
                formGroup.appendChild(feedbackEl);
            } else {
                feedback.textContent = message;
            }
        }
    }
    
    clearError(field) {
        field.classList.remove('is-invalid');
        const feedback = field.closest('.form-group').querySelector('.invalid-feedback');
        if (feedback) feedback.remove();
    }
    
    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    isValidPhone(phone) {
        const re = /^[\+]?[1-9][\d]{0,15}$/;
        return re.test(phone.replace(/[\s\-\(\)]/g, ''));
    }
}

// Data table functionality
class DataTable {
    constructor(table) {
        this.table = table;
        this.init();
    }
    
    init() {
        this.addSearch();
        this.addSorting();
    }
    
    addSearch() {
        const searchInput = document.querySelector('.table-search input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.search(e.target.value);
            });
        }
    }
    
    search(term) {
        const rows = this.table.querySelectorAll('tbody tr');
        const searchTerm = term.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    addSorting() {
        const headers = this.table.querySelectorAll('thead th[data-sortable]');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => this.sort(index));
        });
    }
    
    sort(columnIndex) {
        const rows = Array.from(this.table.querySelectorAll('tbody tr'));
        const isAscending = this.table.classList.contains('asc');
        
        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();
            
            if (isAscending) {
                return aVal.localeCompare(bVal);
            } else {
                return bVal.localeCompare(aVal);
            }
        });
        
        const tbody = this.table.querySelector('tbody');
        rows.forEach(row => tbody.appendChild(row));
        
        this.table.classList.toggle('asc');
    }
}

// Initialize components when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dropdowns
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        new MaterialDropdown(dropdown);
    });
    
    // Initialize modals
    document.querySelectorAll('.modal').forEach(modal => {
        new MaterialModal(modal);
    });
    
    // Initialize alerts
    document.querySelectorAll('.alert').forEach(alert => {
        new MaterialAlert(alert);
    });
    
    // Initialize tabs
    document.querySelectorAll('.tabs').forEach(tabs => {
        new MaterialTabs(tabs);
    });
    
    // Initialize forms
    document.querySelectorAll('form').forEach(form => {
        new FormValidator(form);
    });
    
    // Initialize data tables
    document.querySelectorAll('.data-table').forEach(table => {
        new DataTable(table);
    });
});

// Utility functions
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = new MaterialModal(modal);
        modalInstance.show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = new MaterialModal(modal);
        modalInstance.hide();
    }
}

function showAlert(message, type = 'info', container = 'body') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible">
            ${message}
            <button type="button" class="btn-close" data-dismiss="alert">&times;</button>
        </div>
    `;
    
    const containerEl = typeof container === 'string' ? document.querySelector(container) : container;
    containerEl.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        const alert = containerEl.querySelector('.alert');
        if (alert) {
            new MaterialAlert(alert).hide();
        }
    }, 5000);
}

function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// AJAX helper
function makeRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const config = Object.assign({}, defaults, options);
    
    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        });
}