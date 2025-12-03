// --- Real Data Store for Dashboard ---
let dashboardData = {
    'all': {
        name: 'All Branches (Consolidated)',
        salesToday: '--',
        transactions: '--',
        revenueMonth: '--',
        alerts: '--',
        weeklySales: '--',
        inventoryValue: '--',
        paymentStats: {
            cash: { amt: '--', count: 0 },
            card: { amt: '--', count: 0 },
            credit: { amt: '--', count: 0 }
        },
        inventory: { active: 0, low: 0, out: 0, expiring: 0, expired: 0 },
        topMedicines: [],
        weeklyTrend: [],
        categorySales: []
    }
};

// --- Function to Fetch Real Data from API ---
async function fetchDashboardData() {
    try {
        const response = await fetch('api/dashboard_api.php');
        const result = await response.json();

        if (result.success && result.data) {
            // Update the dashboardData with real data
            const data = result.data;
            dashboardData['all'] = {
                name: 'All Branches (Consolidated)',
                salesToday: data.salesToday,
                transactions: data.transactions,
                revenueMonth: data.revenueMonth,
                alerts: data.alerts,
                weeklySales: data.weeklySales,
                inventoryValue: data.inventoryValue,
                paymentStats: data.paymentStats,
                inventory: data.inventory,
                topMedicines: data.topMedicines,
                weeklyTrend: data.weeklyTrend,
                categorySales: data.categorySales
            };
        } else {
            console.error('Failed to fetch dashboard data:', result.error);
        }
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    }
}

// Track current branch for export functionality
let currentBranch = 'all';

// --- Helper Functions ---
function parseCurrency(str) {
    if (!str) return 0;
    return parseFloat(str.replace(/[₱,]/g, ''));
}

// --- Export Function ---
function exportDashboardData() {
    // Get current view data
    const data = dashboardData[currentBranch];
    const rows = [];

    // 1. Header Information
    rows.push(['Exported Dashboard Report']);
    rows.push(['Date Generated', new Date().toLocaleString()]);
    rows.push(['Scope', data.name]);
    rows.push([]); // Empty row for spacing

    // 2. Key Performance Indicators (KPIs)
    rows.push(['KPI METRICS']);
    rows.push(['Sales Today', data.salesToday.replace(/,/g, '')]); // Remove commas for CSV safety
    rows.push(['Transactions Today', data.transactions]);
    rows.push(['Monthly Revenue', data.revenueMonth.replace(/,/g, '')]);
    rows.push(['Active Alerts', data.alerts]);
    rows.push(['Weekly Sales Total', data.weeklySales.replace(/,/g, '')]);
    rows.push(['Total Inventory Value', data.inventoryValue.replace(/,/g, '')]);
    rows.push([]);

    // 3. Weekly Sales Trend
    rows.push(['WEEKLY SALES TREND']);
    rows.push(['Day', 'Sales Amount']);
    data.weeklyTrend.forEach(day => {
        rows.push([day.day, day.sales]);
    });
    rows.push([]);

    // 4. Top Medicines
    rows.push(['TOP 10 MEDICINES']);
    rows.push(['Medicine Name', 'Quantity Sold']);
    data.topMedicines.forEach(med => {
        rows.push([med.name, med.qty]);
    });
    rows.push([]);

    // 5. Category Sales
    rows.push(['SALES BY CATEGORY']);
    rows.push(['Category', 'Sales Amount']);
    data.categorySales.forEach(cat => {
        rows.push([cat.label, cat.value]);
    });

    // Convert to CSV string
    const csvContent = "data:text/csv;charset=utf-8," 
        + rows.map(e => e.join(",")).join("\n");

    // Create download link and trigger click
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Pharmacy_Report_${currentBranch}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function switchBranch(branchKey) {
    document.getElementById('single-branch-view').classList.remove('hidden');
    document.getElementById('comparison-view').classList.add('hidden');

    const data = dashboardData[branchKey];
    currentBranch = branchKey; // Update current branch for export
    resetButtons();
    
    const activeBtn = document.querySelector(`.branch-btn[data-target="${branchKey}"]`);
    if(activeBtn) {
        activeBtn.classList.add('active', 'bg-pharmacy-primary', 'text-white', 'border-transparent');
        activeBtn.classList.remove('bg-white', 'text-text-medium', 'border-gray-200');
    }

    document.getElementById('view-title').textContent = `Viewing: ${data.name}`;
    document.getElementById('view-title').classList.remove('text-pharmacy-secondary');
    document.getElementById('view-title').classList.add('text-pharmacy-primary');

    animateValue('val-sales', data.salesToday);
    document.getElementById('sub-sales').textContent = data.transactions;
    animateValue('val-revenue', data.revenueMonth);
    document.getElementById('val-alerts').textContent = data.alerts;
    document.getElementById('val-weekly-sales').textContent = data.weeklySales;
    
    document.getElementById('pay-cash-amt').textContent = data.paymentStats.cash.amt;
    document.getElementById('pay-cash-count').textContent = `(${data.paymentStats.cash.count} txns)`;
    document.getElementById('pay-card-amt').textContent = data.paymentStats.card.amt;
    document.getElementById('pay-card-count').textContent = `(${data.paymentStats.card.count} txns)`;
    document.getElementById('pay-credit-amt').textContent = data.paymentStats.credit.amt;
    document.getElementById('pay-credit-count').textContent = `(${data.paymentStats.credit.count} txns)`;

    document.getElementById('val-inv-value').textContent = data.inventoryValue;
    
    // Updated inventory update logic
    document.getElementById('inv-active').textContent = data.inventory.active;
    document.getElementById('inv-low').textContent = data.inventory.low;
    document.getElementById('inv-out').textContent = data.inventory.out;
    document.getElementById('inv-exp').textContent = data.inventory.expiring;
    
    renderTopMedicinesChart(data.topMedicines);
    renderWeeklySalesChart(data.weeklyTrend);
    renderCategorySalesChart(data.categorySales); // New Function
}

function compareBranches(metric) {
    document.getElementById('single-branch-view').classList.add('hidden');
    document.getElementById('comparison-view').classList.remove('hidden');

    resetButtons();
    const activeBtn = document.querySelector(`.comp-btn[data-target="${metric}"]`);
    if(activeBtn) {
        activeBtn.classList.add('active', 'bg-pharmacy-primary', 'text-white', 'border-transparent');
        activeBtn.classList.remove('bg-white', 'text-text-medium', 'border-gray-200');
    }

    const container = document.getElementById('comparison-results');
    container.innerHTML = '';
    
    document.getElementById('view-title').textContent = `Mode: Comparing ${metric.toUpperCase()}`;
    document.getElementById('view-title').classList.remove('text-pharmacy-primary');
    document.getElementById('view-title').classList.add('text-pharmacy-secondary');

    const metricsConfig = {
        'sales': {
            cards: [
                { label: 'Today', key: 'salesToday', format: 'currency', color: 'bg-pharmacy-primary' },
                { label: 'This Week', key: 'weeklySales', format: 'currency', color: 'bg-pharmacy-secondary' },
                { label: 'This Month', key: 'revenueMonth', format: 'currency', color: 'bg-success-green' }
            ]
        },
        'stock': {
            cards: [
                { label: 'Low Stock', key: 'inventory.low', format: 'number', color: 'bg-warning-yellow' },
                { label: 'Out of Stock', key: 'inventory.out', format: 'number', color: 'bg-danger-red' }
            ]
        },
        'alerts': {
            cards: [
                { label: 'Will Expire Soon', key: 'inventory.expiring', format: 'number', color: 'bg-text-alert' },
                { label: 'Expired', key: 'inventory.expired', format: 'number', color: 'bg-text-danger' }
            ]
        }
    };

    const config = metricsConfig[metric];
    const branches = ['branch1', 'branch2', 'branch3'];

    branches.forEach(bKey => {
        const branch = dashboardData[bKey];
        const branchRow = document.createElement('div');
        branchRow.className = 'mb-6 pb-6 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0';
        
        const titleHtml = `<h4 class="text-md font-bold text-text-dark mb-3 flex items-center">
            <span class="w-2 h-6 bg-pharmacy-primary rounded-r mr-2"></span>
            ${branch.name}
        </h4>`;
        
        const gridClass = metric === 'sales' ? 'grid-cols-1 md:grid-cols-3' : 'grid-cols-1 md:grid-cols-2';
        let cardsHtml = `<div class="grid ${gridClass} gap-4">`;

        config.cards.forEach(cardConfig => {
            const valRaw = cardConfig.key.split('.').reduce((o, i) => o[i], branch);
            let displayVal = valRaw;
            let percentage = 0;
            const mockMax = metric === 'sales' ? 3000000 : 50; 
            
            let numVal = cardConfig.format === 'currency' ? parseCurrency(valRaw) : parseInt(valRaw);
            percentage = Math.min((numVal / mockMax) * 100, 100); 

            cardsHtml += `
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-xs text-text-light mb-1 uppercase font-semibold">${cardConfig.label}</p>
                    <p class="text-xl font-bold text-text-dark mb-2">${displayVal}</p>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="${cardConfig.color} h-1.5 rounded-full" style="width: ${percentage > 0 ? percentage : 5}%"></div>
                    </div>
                </div>
            `;
        });

        cardsHtml += `</div>`;
        branchRow.innerHTML = titleHtml + cardsHtml;
        container.appendChild(branchRow);
    });
}

function resetButtons() {
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active', 'bg-pharmacy-primary', 'text-white', 'border-transparent');
        btn.classList.add('bg-white', 'text-text-medium', 'border-gray-200');
    });
}

function animateValue(id, endValueString) {
    const el = document.getElementById(id);
    if(!el) return;
    el.classList.add('opacity-50');
    setTimeout(() => {
        el.textContent = endValueString;
        el.classList.remove('opacity-50');
    }, 150);
}

function renderTopMedicinesChart(medicines) {
    const chartContainer = document.getElementById('top-medicines-chart');
    if (!chartContainer) return;
    chartContainer.innerHTML = '';
    const maxQty = Math.max(...medicines.map(m => m.qty));

    medicines.forEach(item => {
        const widthPercent = (item.qty / maxQty) * 100;
        const row = document.createElement('div');
        row.className = 'flex items-center text-sm mb-3';
        row.innerHTML = `
            <div class="w-1/3 truncate pr-2 text-text-dark font-medium" title="${item.name}">${item.name}</div>
            <div class="w-2/3 flex items-center">
                <div class="h-3 rounded-full bg-pharmacy-secondary transition-all duration-500" style="width: ${widthPercent}%"></div>
                <span class="ml-2 text-xs text-text-light font-semibold">${item.qty}</span>
            </div>
        `;
        chartContainer.appendChild(row);
    });
}

function renderWeeklySalesChart(data) {
    const chartContainer = document.getElementById('weekly-sales-chart');
    if (!chartContainer) return;
    chartContainer.innerHTML = '';
    
    const MAX_SCALE = 200000;

    data.forEach(item => {
        const heightPercent = Math.min((item.sales / MAX_SCALE) * 100, 100);
        const group = document.createElement('div');
        group.className = 'bar-group flex w-1/7 h-full items-end justify-center px-1 relative';
        
        group.innerHTML = `
            <div class="relative w-6 bg-pharmacy-primary rounded-t-sm mx-1 bar-chart-bar group transition-all duration-300 hover:shadow-xl hover:bg-pharmacy-secondary" style="height: ${heightPercent}%;">
                <div class="bar-tooltip">₱${item.sales.toLocaleString()}</div>
            </div>
            <span class="absolute -bottom-6 text-xs text-text-light">${item.day}</span>
        `;
        chartContainer.appendChild(group);
    });
}

function renderCategorySalesChart(data) {
    const chartContainer = document.getElementById('category-sales-chart');
    if (!chartContainer) return;
    chartContainer.innerHTML = '';
    
    const maxValue = Math.max(...data.map(d => d.value));

    data.forEach(item => {
        const widthPercent = (item.value / maxValue) * 100;
        const row = document.createElement('div');
        row.className = 'flex flex-col mb-4';
        row.innerHTML = `
            <div class="flex justify-between text-sm mb-1">
                <span class="text-text-dark font-medium">${item.label}</span>
                <span class="text-text-light">₱${item.value.toLocaleString()}</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="${item.color} h-2.5 rounded-full transition-all duration-700" style="width: ${widthPercent}%"></div>
            </div>
        `;
        chartContainer.appendChild(row);
    });
}

function updateClockStatic() {
    document.getElementById('current-time').textContent = '01:42 PM';
    document.getElementById('current-date').textContent = 'Thursday, November 27, 2025';
}

// Init
window.onload = async function() {
    await fetchDashboardData();
    switchBranch('all');
    updateClockStatic();
};
