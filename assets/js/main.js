// ===================================================================
// HAU ATHLETICS PORTAL - MAIN JAVASCRIPT
// ===================================================================

document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
    initializeUserMenu();
    initializeSearch();
    initializeModals();
    initializeDatePickers();
    initializeFavorites();
    checkOverdueItems();
    autoHideAlerts();
    initializeFormValidation();
});

// ===================================================================
// SIDEBAR TOGGLE (Mobile)
// ===================================================================
function initializeSidebar() {
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }
}

// ===================================================================
// USER MENU DROPDOWN
// ===================================================================
function initializeUserMenu() {
    const userMenu = document.querySelector('.user-menu');
    const userMenuToggle = document.querySelector('.user-menu-toggle');
    
    if (userMenu && userMenuToggle) {
        userMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('active');
        });
        
        // Close when clicking outside
        document.addEventListener('click', function() {
            userMenu.classList.remove('active');
        });
    }
}

// ===================================================================
// LIVE SEARCH
// ===================================================================
function initializeSearch() {
    const searchInput = document.getElementById('equipment-search');
    
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                return;
            }
            
            searchTimeout = setTimeout(function() {
                performSearch(query);
            }, 300);
        });
    }
}

function performSearch(query) {
    fetch(`../actions/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.results);
            }
        })
        .catch(error => console.error('Search error:', error));
}

function displaySearchResults(results) {
    const container = document.getElementById('equipment-grid');
    
    if (!container) return;
    
    if (results.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="grid-column: 1/-1;">
                <div class="empty-state-icon">🔍</div>
                <div class="empty-state-message">No equipment found</div>
                <div class="empty-state-description">Try adjusting your search terms</div>
            </div>
        `;
        return;
    }
    
    container.innerHTML = results.map(item => createEquipmentCard(item)).join('');
}

function createEquipmentCard(equipment) {
    const available = equipment.quantity_available > 0;
    const statusClass = available ? 'available' : 'unavailable';
    const statusText = available ? `Available: ${equipment.quantity_available}/${equipment.quantity_total}` : 'Not Available';
    
    return `
        <div class="equipment-card" onclick="window.location.href='details.php?id=${equipment.equipment_id}'">
            <img src="../assets/images/equipment/${equipment.image}" alt="${equipment.name}" class="equipment-card-image" onerror="this.src='../assets/images/default.png'">
            <div class="card-body">
                <h3>${equipment.name}</h3>
                <p class="category">${equipment.category_name}</p>
                <p class="location">📍 ${equipment.location}</p>
                <span class="status-badge ${statusClass}">${statusText}</span>
                <div style="margin-top: 10px;">
                    <a href="details.php?id=${equipment.equipment_id}" class="btn btn-primary btn-block">View Details</a>
                </div>
            </div>
        </div>
    `;
}

// ===================================================================
// MODAL MANAGEMENT
// ===================================================================
function initializeModals() {
    const modals = document.querySelectorAll('.modal');
    const closeBtns = document.querySelectorAll('.modal-close');
    
    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').classList.remove('show');
        });
    });
    
    // Close on outside click
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });
    
    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modals.forEach(modal => modal.classList.remove('show'));
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

// ===================================================================
// DATE PICKER INITIALIZATION
// ===================================================================
function initializeDatePickers() {
    const pickupDateInput = document.getElementById('pickup-date');
    const returnDateInput = document.getElementById('return-date');
    
    if (pickupDateInput) {
        // Set minimum date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        pickupDateInput.min = tomorrow.toISOString().split('T')[0];
        
        // Auto-calculate return date
        pickupDateInput.addEventListener('change', function() {
            if (returnDateInput) {
                const maxDays = parseInt(this.dataset.maxDays) || 7;
                const returnDate = new Date(this.value);
                returnDate.setDate(returnDate.getDate() + maxDays);
                returnDateInput.value = returnDate.toISOString().split('T')[0];
                returnDateInput.min = this.value;
            }
        });
    }
}

// ===================================================================
// FAVORITES FUNCTIONALITY
// ===================================================================
function initializeFavorites() {
    const favoriteButtons = document.querySelectorAll('.favorite-btn');
    
    favoriteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleFavorite(this);
        });
    });
}

function toggleFavorite(button) {
    const equipmentId = button.dataset.equipmentId;
    
    fetch('../actions/toggle-favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `equipment_id=${equipmentId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.toggle('favorited');
            button.innerHTML = data.is_favorited ? '❤️ Favorited' : '🤍 Favorite';
            showAlert(data.message, 'success');
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Favorite error:', error);
        showAlert('Action failed. Please try again.', 'error');
    });
}

// ===================================================================
// CHECK OVERDUE ITEMS
// ===================================================================
function checkOverdueItems() {
    const loanRows = document.querySelectorAll('.loan-row');
    
    loanRows.forEach(row => {
        const dueDateStr = row.dataset.dueDate;
        if (!dueDateStr) return;
        
        const dueDate = new Date(dueDateStr);
        const now = new Date();
        const daysRemaining = Math.ceil((dueDate - now) / (1000 * 60 * 60 * 24));
        
        const statusCell = row.querySelector('.days-remaining');
        if (!statusCell) return;
        
        if (daysRemaining < 0) {
            statusCell.innerHTML = `<span class="badge-danger">Overdue ${Math.abs(daysRemaining)} days</span>`;
            row.classList.add('overdue');
        } else if (daysRemaining === 0) {
            statusCell.innerHTML = `<span class="badge-danger">Due Today</span>`;
            row.classList.add('due-soon');
        } else if (daysRemaining <= 2) {
            statusCell.innerHTML = `<span class="badge-warning">${daysRemaining} days left</span>`;
            row.classList.add('due-soon');
        } else {
            statusCell.innerHTML = `<span class="badge-success">${daysRemaining} days left</span>`;
        }
    });
}

// ===================================================================
// FORM VALIDATION
// ===================================================================
function initializeFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        } else {
            clearFieldError(field);
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    field.classList.add('error');
    const error = document.createElement('div');
    error.className = 'form-error';
    error.textContent = message;
    field.parentNode.appendChild(error);
}

function clearFieldError(field) {
    field.classList.remove('error');
    const error = field.parentNode.querySelector('.form-error');
    if (error) {
        error.remove();
    }
}

// ===================================================================
// ALERT SYSTEM
// ===================================================================
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.animation = 'slideInDown 0.3s ease';
    
    const container = document.querySelector('.main-content');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            setTimeout(() => alertDiv.remove(), 300);
        }, 5000);
    }
}

function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

// ===================================================================
// CONFIRM ACTIONS
// ===================================================================
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

function confirmDelete(url, itemName) {
    if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
        window.location.href = url;
    }
}

// ===================================================================
// FILTER EQUIPMENT
// ===================================================================
function filterEquipment() {
    const category = document.getElementById('filter-category')?.value || '';
    const location = document.getElementById('filter-location')?.value || '';
    const availability = document.getElementById('filter-availability')?.value || '';
    
    const params = new URLSearchParams();
    if (category) params.append('category', category);
    if (location) params.append('location', location);
    if (availability) params.append('availability', availability);
    
    window.location.href = `browse.php?${params.toString()}`;
}

function clearFilters() {
    window.location.href = 'browse.php';
}

// ===================================================================
// EXPORT TO CSV
// ===================================================================
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = Array.from(table.querySelectorAll('tr'));
    
    const csv = rows.map(row => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        return cells.map(cell => `"${cell.textContent.trim()}"`).join(',');
    }).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

// ===================================================================
// UTILITY FUNCTIONS
// ===================================================================
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function formatDateTime(dateTimeString) {
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateTimeString).toLocaleDateString('en-US', options);
}

function getPointsStatusClass(points) {
    if (points >= 70) return 'status-good';
    if (points >= 40) return 'status-warning';
    return 'status-restricted';
}

// ===================================================================
// PRINT FUNCTION
// ===================================================================
function printPage() {
    window.print();
}

// ===================================================================
// LOADING INDICATOR
// ===================================================================
function showLoading() {
    const loading = document.createElement('div');
    loading.id = 'loading-overlay';
    loading.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
            <div style="background: white; padding: 30px; border-radius: 8px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 10px;">⏳</div>
                <div>Loading...</div>
            </div>
        </div>
    `;
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('loading-overlay');
    if (loading) {
        loading.remove();
    }
}
