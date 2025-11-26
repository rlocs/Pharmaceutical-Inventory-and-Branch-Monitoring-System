// medicine.js - Frontend logic for medicine inventory management

document.addEventListener('DOMContentLoaded', function () {
    if (window.console) console.debug('medicine.js loaded');

    // Initialize medicine management
    loadMedicines();
    loadAlerts();
    setupSearch();

    // Category filter
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            loadMedicines(1, this.value);
        });
    }

    // Pagination
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-action="prev"], [data-action="next"]')) {
            e.preventDefault();
            const action = e.target.getAttribute('data-action');
            const currentPage = parseInt(document.querySelector('.showing-entries').getAttribute('data-current-page') || 1);
            let newPage = currentPage;

            if (action === 'prev' && currentPage > 1) {
                newPage = currentPage - 1;
            } else if (action === 'next') {
                newPage = currentPage + 1;
            }

            loadMedicines(newPage, categoryFilter ? categoryFilter.value : '');
        }
    });

    // Add medicine form
    const addForm = document.getElementById('addMedicineForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            addMedicine();
        });
    }

    // Edit medicine form
    const editForm = document.getElementById('editMedicineForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            updateMedicine();
        });
    }

    // Handle category changes for "Others"
    document.addEventListener('change', function(e) {
        if (e.target.id === 'addCategorySelect') {
            toggleOtherCategory('add');
        } else if (e.target.id === 'editCategorySelect') {
            toggleOtherCategory('edit');
        }
    });
});

function apiRequest(action, params = {}, method = 'GET') {
    let url = 'api/medicine_api.php?action=' + encodeURIComponent(action);
    const opts = { method: method, credentials: 'same-origin' };

    if (method === 'GET') {
        const query = new URLSearchParams({ action, ...params }).toString();
        url = 'api/medicine_api.php?' + query;
    } else {
        if (method === 'POST' && !(params instanceof FormData)) {
            opts.headers = { 'Content-Type': 'application/json' };
            opts.body = JSON.stringify(params);
        } else {
            opts.body = params;
        }
    }

    return fetch(url, opts).then(r => r.json());
}

function loadMedicines(page = 1, category = '') {
    const tbody = document.querySelector('#dynamic-content table tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">Loading...</td></tr>';

    const params = { page };
    if (category) params.category = category;

    apiRequest('get_medicines', params, 'GET').then(res => {
        if (!res.success) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-red-500">${escapeHtml(res.error || 'Failed to load medicines')}</td></tr>`;
            return;
        }

        const medicines = res.medicines || [];
        tbody.innerHTML = '';

        if (medicines.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4">No medicines found.</td></tr>';
        } else {
            medicines.forEach(med => {
                const row = createMedicineRow(med);
                tbody.appendChild(row);
            });
        }

        // Re-render Lucide icons for the new content
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }

        // Update pagination
        updatePagination(res);
    }).catch(err => {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-red-500">${escapeHtml(err.message || 'Network error')}</td></tr>`;
    });
}

function createMedicineRow(med) {
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-gray-50';

    const statusClass = getStatusClass(med.Status);
    const statusText = med.Status;

    tr.innerHTML = `
        <td class="py-3 px-4 border-b border-gray-200">${escapeHtml(med.MedicineName)}</td>
        <td class="py-3 px-4 border-b border-gray-200">${escapeHtml(med.Form)}</td>
        <td class="py-3 px-4 border-b border-gray-200">${escapeHtml(med.Unit)}</td>
        <td class="py-3 px-4 border-b border-gray-200">₱${parseFloat(med.Price).toFixed(2)}</td>
        <td class="py-3 px-4 border-b border-gray-200">${escapeHtml(med.Category || 'Uncategorized')}</td>
        <td class="py-3 px-4 border-b border-gray-200 text-center">
            <span class="px-2 py-1 rounded-full text-xs font-medium ${getStockClass(med.Stocks)}">
                ${med.Stocks}
            </span>
        </td>
        <td class="py-3 px-4 border-b border-gray-200">${formatDate(med.ExpiryDate)}</td>
        <td class="py-3 px-4 border-b border-gray-200">
            <span class="px-2 py-1 rounded-full text-xs font-medium ${statusClass}">
                ${statusText}
            </span>
        </td>
        <td class="py-3 px-4 border-b border-gray-200 text-center">
            <button onclick="editMedicine(${med.BranchInventoryID})" class="text-blue-600 hover:text-blue-800 mr-2">
                <i data-lucide="edit" class="h-4 w-4"></i>
            </button>
            <button onclick="deleteMedicine(${med.BranchInventoryID})" class="text-red-600 hover:text-red-800">
                <i data-lucide="trash-2" class="h-4 w-4"></i>
            </button>
        </td>
    `;

    return tr;
}

function getStatusClass(status) {
    // Handle multiple status values
    const primaryStatus = status.split(',')[0].trim();
    switch (primaryStatus) {
        case 'Active': return 'bg-green-100 text-green-800';
        case 'Low Stock': return 'bg-yellow-100 text-yellow-800';
        case 'Out of Stock': return 'bg-red-100 text-red-800';
        case 'Expiring Soon': return 'bg-orange-100 text-orange-800';
        case 'Expired': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStockClass(stocks) {
    const numStocks = parseInt(stocks);
    if (numStocks === 0) return 'bg-red-100 text-red-800';
    if (numStocks <= 10) return 'bg-yellow-100 text-yellow-800';
    return 'bg-green-100 text-green-800';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString();
}

function updatePagination(data) {
    const showingEl = document.querySelector('.showing-entries');
    if (showingEl) {
        const start = (data.page - 1) * data.limit + 1;
        const end = Math.min(data.page * data.limit, data.total);
        showingEl.textContent = `Showing ${start} to ${end} of ${data.total} medicines`;
        showingEl.setAttribute('data-current-page', data.page);
    }

    // Update pagination buttons
    const prevBtn = document.querySelector('[data-action="prev"]');
    const nextBtn = document.querySelector('[data-action="next"]');

    if (prevBtn) prevBtn.classList.toggle('disabled', data.page <= 1);
    if (nextBtn) nextBtn.classList.toggle('disabled', data.page >= data.total_pages);
}

function loadAlerts() {
    apiRequest('get_alerts', {}, 'GET').then(res => {
        if (!res.success) {
            console.error('Failed to load alerts:', res);
            return;
        }

        const alerts = res.alerts || {};
        const counts = res.counts || {};

        // Debug: Log the structure to help diagnose
        console.log('Alerts response:', JSON.stringify(res, null, 2));
        console.log('Expiring soon items:', alerts.expiringSoon);
        console.log('Low stock items:', alerts.lowStock);
        console.log('Expired items:', alerts.expired);

        // Update counts
        updateAlertCount('expiringSoonCount', counts.expiringSoon || 0);
        // Combine lowStock and outOfStock for the "Items Need Attention" count
        const totalLowStock = (counts.lowStock || 0) + (counts.outOfStock || 0);
        updateAlertCount('lowStockCount', totalLowStock);
        updateAlertCount('expiredCount', counts.expired || 0);

        // Update lists - ensure we have arrays and log what we're passing
        const expiringSoon = Array.isArray(alerts.expiringSoon) ? alerts.expiringSoon : [];
        const lowStock = Array.isArray(alerts.lowStock) ? alerts.lowStock : [];
        const outOfStock = Array.isArray(alerts.outOfStock) ? alerts.outOfStock : [];
        const expired = Array.isArray(alerts.expired) ? alerts.expired : [];
        
        console.log('Updating expiringSoonList with', expiringSoon.length, 'items');
        console.log('Updating lowStockList with', (lowStock.length + outOfStock.length), 'items');
        console.log('Updating expiredList with', expired.length, 'items');
        
        updateAlertList('expiringSoonList', expiringSoon);
        const combinedLowStock = [...lowStock, ...outOfStock];
        updateAlertList('lowStockList', combinedLowStock);
        updateAlertList('expiredList', expired);
    }).catch(error => {
        console.error('Error loading alerts:', error);
    });
}

function updateAlertCount(elementId, count) {
    const el = document.getElementById(elementId);
    if (el) el.textContent = count;
}

function updateAlertList(elementId, items) {
    const el = document.getElementById(elementId);
    if (!el) {
        console.error('updateAlertList: Element not found:', elementId);
        return;
    }

    // Ensure items is an array
    if (!Array.isArray(items)) {
        console.error('updateAlertList: items is not an array for', elementId, items);
        el.innerHTML = '<li class="text-gray-500">Error loading alerts</li>';
        return;
    }

    if (items.length === 0) {
        el.innerHTML = '<li class="text-gray-500">None</li>';
        return;
    }

    // Build HTML for each item
    const htmlItems = [];
    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        
        // Handle null/undefined
        if (!item || typeof item !== 'object') {
            console.warn('updateAlertList: Invalid item at index', i, item);
            htmlItems.push('<li class="text-gray-500">Invalid item</li>');
            continue;
        }

        // Extract properties - handle both camelCase and PascalCase
        const name = item.name || item.MedicineName || 'Unknown Medicine';
        let stocks = item.stocks;
        if (stocks === undefined) {
            stocks = item.Stocks !== undefined ? parseInt(item.Stocks, 10) : undefined;
        }
        const expiry = item.expiry || item.ExpiryDate || '';
        const status = item.status || item.Status || '';
        
        // Build display text - start with medicine name
        let displayText = escapeHtml(String(name));
        
        // Add stock information
        if (stocks !== undefined && stocks !== null && !isNaN(stocks)) {
            const stockNum = parseInt(stocks, 10);
            if (stockNum === 0) {
                displayText += ' <span class="font-medium text-red-600">(Out of stock)</span>';
            } else if (stockNum <= 10) {
                displayText += ' <span class="font-medium text-orange-600">(' + stockNum + ' left)</span>';
            }
        }
        
        // Add expiry information
        if (expiry) {
            try {
                const expiryDate = new Date(expiry);
                if (!isNaN(expiryDate.getTime())) {
                    const expiryFmt = expiryDate.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
                    displayText += ' <span class="font-medium text-gray-600">(' + escapeHtml(expiryFmt) + ')</span>';
                }
            } catch (e) {
                console.warn('Date parsing error for', expiry, e);
            }
        }
        
        // Add status if available
        if (status) {
            displayText += ' <span class="text-xs text-gray-500">- ' + escapeHtml(String(status)) + '</span>';
        }
        
        htmlItems.push('<li>' + displayText + '</li>');
    }
    
    el.innerHTML = htmlItems.join('');
}

function showAddMedicineModal() {
    const modal = document.getElementById('addMedicineModal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('addMedicineForm').reset();
        toggleOtherCategory('add');
    }
}

function closeAddModal() {
    const modal = document.getElementById('addMedicineModal');
    if (modal) modal.style.display = 'none';
}

function addMedicine() {
    const form = document.getElementById('addMedicineForm');
    if (!form) return;

    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    // Handle "Others" category - if "Others" is selected, use the custom category name
    // The API will handle finding or creating the category
    if (data.category === 'Others') {
        if (!data.otherCategory || data.otherCategory.trim() === '') {
            alert('Please enter a category name for "Others"');
            return;
        }
        data.category = data.otherCategory.trim(); // Send category name, API will find/create it
    }
    // If a regular category name is selected, it's already in data.category
    delete data.otherCategory;

    // Convert stocks and price to numbers
    data.stocks = parseInt(data.stocks, 10);
    data.price = parseFloat(data.price);

    // Disable submit button during request
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Adding...';

    apiRequest('add_medicine', data, 'POST').then(res => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        
        if (res.success) {
            closeAddModal();
            form.reset();
            loadMedicines();
            loadAlerts();
            showNotification('Medicine added successfully!', 'success');
        } else {
            showNotification('Error: ' + (res.error || 'Failed to add medicine'), 'error');
        }
    }).catch(error => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        console.error('Error adding medicine:', error);
        showNotification('Error adding medicine. Please try again.', 'error');
    });
}

function editMedicine(medicineId) {
    const modal = document.getElementById('editMedicineModal');
    if (!modal) return;

    // Show loading state
    modal.style.display = 'flex';
    const form = document.getElementById('editMedicineForm');
    form.reset();
    
    // Fetch medicine data from API
    apiRequest('get_medicine', { medicineId: medicineId }, 'GET').then(res => {
        if (!res.success) {
            alert('Error: ' + (res.error || 'Failed to load medicine data'));
            closeEditModal();
            return;
        }

        const med = res.medicine;
        
        // Populate form fields
        form.querySelector('input[name="medicineId"]').value = med.BranchInventoryID;
        form.querySelector('input[name="medicineName"]').value = med.MedicineName || '';
        form.querySelector('input[name="unit"]').value = med.Unit || '';
        form.querySelector('input[name="stocks"]').value = med.Stocks || 0;
        form.querySelector('input[name="price"]').value = med.Price || 0;
        
        // Format expiry date for input[type="date"]
        if (med.ExpiryDate) {
            const expiryDate = new Date(med.ExpiryDate);
            if (!isNaN(expiryDate.getTime())) {
                const formattedDate = expiryDate.toISOString().split('T')[0];
                form.querySelector('input[name="expiryDate"]').value = formattedDate;
            }
        }
        
        // Set category - use CategoryName since dropdown now uses names as values
        const categorySelect = form.querySelector('#editCategorySelect');
        if (med.CategoryName) {
            // Check if category exists in dropdown
            const categoryOption = Array.from(categorySelect.options).find(opt => opt.value === med.CategoryName);
            if (categoryOption) {
                categorySelect.value = med.CategoryName;
            } else {
                // Category not in list, use "Others"
                categorySelect.value = 'Others';
                const otherInput = document.getElementById('editOtherCategory');
                if (otherInput) {
                    otherInput.value = med.CategoryName;
                    otherInput.classList.remove('hidden');
                    otherInput.required = true;
                }
            }
        }
        
        // Set form
        const formSelect = form.querySelector('select[name="form"]');
        if (med.Form && formSelect) {
            formSelect.value = med.Form;
        }
        
        // Update "Others" category visibility
        toggleOtherCategory('edit');
    }).catch(error => {
        console.error('Error loading medicine:', error);
        alert('Error loading medicine data. Please try again.');
        closeEditModal();
    });
}

function updateMedicine() {
    const form = document.getElementById('editMedicineForm');
    if (!form) return;

    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    // Handle "Others" category - if "Others" is selected, use the custom category name
    // The API will handle finding or creating the category
    if (data.category === 'Others') {
        if (!data.otherCategory || data.otherCategory.trim() === '') {
            alert('Please enter a category name for "Others"');
            return;
        }
        data.category = data.otherCategory.trim(); // Send category name, API will find/create it
    }
    // If a regular category name is selected, it's already in data.category
    delete data.otherCategory;

    // Convert stocks and price to numbers
    data.stocks = parseInt(data.stocks, 10);
    data.price = parseFloat(data.price);

    // Disable submit button during request
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';

    apiRequest('update_medicine', data, 'POST').then(res => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        
        if (res.success) {
            closeEditModal();
            loadMedicines();
            loadAlerts();
            showNotification('Medicine updated successfully!', 'success');
        } else {
            showNotification('Error: ' + (res.error || 'Failed to update medicine'), 'error');
        }
    }).catch(error => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        console.error('Error updating medicine:', error);
        showNotification('Error updating medicine. Please try again.', 'error');
    });
}

function closeEditModal() {
    const modal = document.getElementById('editMedicineModal');
    if (modal) modal.style.display = 'none';
}

function deleteMedicine(medicineId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            apiRequest('delete_medicine', { medicine_id: medicineId }, 'POST').then(res => {
                if (res.success) {
                    loadMedicines();
                    loadAlerts();
                    showNotification('Medicine deleted successfully!', 'success');
                } else {
                    showNotification('Error: ' + (res.error || 'Failed to delete medicine'), 'error');
                }
            }).catch(error => {
                console.error('Error deleting medicine:', error);
                showNotification('Error deleting medicine. Please try again.', 'error');
            });
        }
    });
}

function toggleAlertDetails(elementId) {
    const el = document.getElementById(elementId);
    if (el) el.classList.toggle('hidden');
}

function toggleOtherCategory(prefix) {
    const select = document.getElementById(`${prefix}CategorySelect`);
    const otherInput = document.getElementById(`${prefix}OtherCategory`);

    if (select && otherInput) {
        // Show input field only when "Others" is selected
        if (select.value === 'Others') {
            otherInput.classList.remove('hidden');
            otherInput.required = true;
        } else {
            otherInput.classList.add('hidden');
            otherInput.required = false;
            otherInput.value = '';
        }
    }
}

function escapeHtml(text) {
    if (!text && text !== 0) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Notification system
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existing = document.querySelectorAll('.notification-toast');
    existing.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
    const icon = type === 'success' ? 'check-circle' : 'alert-circle';
    
    notification.className = `notification-toast fixed top-4 right-4 ${bgColor} text-white px-6 py-4 rounded-lg shadow-lg z-50 flex items-center gap-3 animate-slide-in`;
    notification.innerHTML = `
        <i data-lucide="${icon}" class="h-5 w-5"></i>
        <span>${escapeHtml(message)}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slide-out 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Search functionality
function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = this.value.trim().toLowerCase();
        
        searchTimeout = setTimeout(() => {
            filterTableBySearch(searchTerm);
        }, 300);
    });
}

function filterTableBySearch(searchTerm) {
    const tbody = document.querySelector('#dynamic-content table tbody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show "no results" message if needed
    const noResults = tbody.querySelector('.no-search-results');
    if (visibleCount === 0 && searchTerm) {
        if (!noResults) {
            const tr = document.createElement('tr');
            tr.className = 'no-search-results';
            tr.innerHTML = `<td colspan="9" class="text-center py-4 text-gray-500">No medicines found matching "${escapeHtml(searchTerm)}"</td>`;
            tbody.appendChild(tr);
        }
    } else if (noResults) {
        noResults.remove();
    }
}
