// Alerts logic for sidebar and dashboard

document.addEventListener('DOMContentLoaded', function () {
    loadSidebarAlerts();

    if (typeof updateDashboardAlerts === 'function') {
        try {
            updateDashboardAlerts({
                expiringSoon: { count: 0, medicines: [] },
                lowStock: { count: 0, medicines: [] },
                outOfStock: { count: 0, medicines: [] },
                expired: { count: 0, medicines: [] }
            });
        } catch (e) {
            console.debug('alerts.js fallback failed', e);
        }
    }
    if (window.console) console.debug('alerts.js loaded');
});

function loadSidebarAlerts() {
    // Use consolidated medicine_api get_alerts which returns combined statuses and grouped data
    fetch('api/medicine_api.php?action=get_alerts', { credentials: 'same-origin' })
        .then(response => response.json())
        .then(payload => {
            if (!payload || payload.success !== true) throw new Error('Failed to load alerts');
            updateSidebarAlerts(payload);
        })
        .catch(error => {
            console.error('Error loading alerts:', error);
            // Fallback
            updateSidebarAlerts({
                alerts: { lowStock: [], outOfStock: [], expiringSoon: [], expired: [] },
                counts: { lowStock: 0, outOfStock: 0, expiringSoon: 0, expired: 0 }
            });
        });
}

function updateSidebarAlerts(data) {
    const groups = data.alerts || { lowStock: [], outOfStock: [], expiringSoon: [], expired: [] };

    const lowStockList = document.getElementById('low-stock-list');
    const expiringSoonList = document.getElementById('expiring-soon-list');
    const expiredList = document.getElementById('expired-list');

    // Clear existing content
    lowStockList.innerHTML = '';
    expiringSoonList.innerHTML = '';
    expiredList.innerHTML = '';

    // Populate Low Stock and Out of Stock together
    const combinedLowStock = [...(groups.lowStock || []), ...(groups.outOfStock || [])];
    
    if (combinedLowStock.length > 0) {
        combinedLowStock.forEach(item => {
            const li = document.createElement('li');
            const stockText = item.stocks === 0 ? 'Out of stock' : `${item.stocks} left`;
            li.innerHTML = `${escapeHtml(item.name)}: <span class="font-medium text-red-600">${escapeHtml(stockText)}</span>` +
                (item.status ? ` <span class="text-xs text-gray-600">(${escapeHtml(item.status)})</span>` : '');
            lowStockList.appendChild(li);
        });
    } else {
        lowStockList.innerHTML = '<li class="text-gray-500">No alerts</li>';
    }

    // Populate Expiring Soon
    if (groups.expiringSoon.length > 0) {
        groups.expiringSoon.forEach(item => {
            const li = document.createElement('li');
            const expiryFmt = formatMonthYear(item.expiry);
            li.innerHTML = `${escapeHtml(item.name)} <span class="font-medium text-yellow-700">(${escapeHtml(expiryFmt)})</span>` +
                (item.status ? ` <span class="text-xs text-gray-600">(${escapeHtml(item.status)})</span>` : '');
            expiringSoonList.appendChild(li);
        });
    } else {
        expiringSoonList.innerHTML = '<li class="text-gray-500">No alerts</li>';
    }

    // Populate Expired
    if (groups.expired.length > 0) {
        groups.expired.forEach(item => {
            const li = document.createElement('li');
            const expiryFmt = formatMonthYear(item.expiry);
            li.innerHTML = `${escapeHtml(item.name)} <span class="font-medium text-gray-700">(${escapeHtml(expiryFmt)})</span>` +
                (item.status ? ` <span class="text-xs text-gray-600">(${escapeHtml(item.status)})</span>` : '');
            expiredList.appendChild(li);
        });
    } else {
        expiredList.innerHTML = '<li class="text-gray-500">No alerts</li>';
    }
}

function formatMonthYear(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (Number.isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
}

function escapeHtml(text) {
    if (text === undefined || text === null) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}
