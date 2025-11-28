// --- Mock Data Store for Branches ---
const dashboardData = {
    'all': {
        name: 'All Branches (Consolidated)',
        salesToday: '₱128,450.00',
        transactions: '1,204 transactions today',
        revenueMonth: '₱4,250,890.00',
        alerts: '45',
        weeklySales: '₱933,450.00',
        inventoryValue: '₱542,100.00',
        paymentStats: {
            cash: { amt: '₱521.58', count: 19 },
            card: { amt: '₱161.87', count: 7 },
            credit: { amt: '₱41.50', count: 2 }
        },
        inventory: { active: 38, low: 8, out: 5, expiring: 5, expired: 10 },
        topMedicines: [
            { name: 'Paracetamol 500mg', qty: 1200 },
            { name: 'Amoxicillin 500mg', qty: 950 },
            { name: 'Vitamin C 500mg', qty: 800 },
            { name: 'Losartan 50mg', qty: 750 },
            { name: 'Amlodipine 10mg', qty: 600 },
            { name: 'Simvastatin 20mg', qty: 550 },
            { name: 'Metformin 500mg', qty: 500 },
            { name: 'Omeprazole 20mg', qty: 450 },
            { name: 'Ibuprofen 400mg', qty: 400 },
            { name: 'Cetirizine 10mg', qty: 350 }
        ],
        weeklyTrend: [
            { day: 'Mon', sales: 125000 },
            { day: 'Tue', sales: 110000 },
            { day: 'Wed', sales: 140000 },
            { day: 'Thu', sales: 128450 },
            { day: 'Fri', sales: 160000 },
            { day: 'Sat', sales: 180000 },
            { day: 'Sun', sales: 90000 }
        ],
        categorySales: [
            { label: 'Antibiotics', value: 135000, color: 'bg-blue-500' },
            { label: 'Pain Relief', value: 128000, color: 'bg-green-500' },
            { label: 'Vitamins', value: 115000, color: 'bg-yellow-500' },
            { label: 'Chronic Care', value: 98000, color: 'bg-purple-500' },
            { label: 'First Aid', value: 45000, color: 'bg-red-500' }
        ]
    },
    'branch1': {
        id: 'branch1',
        name: 'Branch 1',
        salesToday: '₱65,200.00',
        transactions: '540 transactions today',
        revenueMonth: '₱2,100,500.00',
        alerts: '12',
        weeklySales: '₱450.00',
        inventoryValue: '₱280,000.00',
        paymentStats: { cash: { amt: '₱300.00', count: 10 }, card: { amt: '₱100.00', count: 4 }, credit: { amt: '₱50.00', count: 2 } },
        inventory: { active: 90, low: 5, out: 2, expiring: 10, expired: 2 },
        topMedicines: [ { name: 'Paracetamol 500mg', qty: 600 }, { name: 'Amoxicillin 500mg', qty: 400 }, { name: 'Vitamin C 500mg', qty: 350 }, { name: 'Losartan 50mg', qty: 300 }, { name: 'Amlodipine 10mg', qty: 250 }, { name: 'Simvastatin 20mg', qty: 200 }, { name: 'Metformin 500mg', qty: 180 }, { name: 'Omeprazole 20mg', qty: 150 }, { name: 'Ibuprofen 400mg', qty: 120 }, { name: 'Cetirizine 10mg', qty: 100 } ],
        weeklyTrend: [
            { day: 'Mon', sales: 60000 }, { day: 'Tue', sales: 55000 }, { day: 'Wed', sales: 70000 }, { day: 'Thu', sales: 65200 }, { day: 'Fri', sales: 80000 }, { day: 'Sat', sales: 85000 }, { day: 'Sun', sales: 40000 }
        ],
        categorySales: [
            { label: 'Antibiotics', value: 65000, color: 'bg-blue-500' },
            { label: 'Pain Relief', value: 60000, color: 'bg-green-500' },
            { label: 'Vitamins', value: 55000, color: 'bg-yellow-500' },
            { label: 'Chronic Care', value: 45000, color: 'bg-purple-500' },
            { label: 'First Aid', value: 20000, color: 'bg-red-500' }
        ]
    },
    'branch2': {
        id: 'branch2',
        name: 'Branch 2',
        salesToday: '₱42,150.00',
        transactions: '380 transactions today',
        revenueMonth: '₱1,450,200.00',
        alerts: '25',
        weeklySales: '₱174.95',
        inventoryValue: '₱150,500.00',
        paymentStats: { cash: { amt: '₱121.58', count: 5 }, card: { amt: '₱41.87', count: 2 }, credit: { amt: '₱11.50', count: 1 } },
        inventory: { active: 40, low: 8, out: 8, expiring: 20, expired: 5 },
        topMedicines: [ { name: 'Paracetamol 500mg', qty: 300 }, { name: 'Amoxicillin 500mg', qty: 250 }, { name: 'Vitamin C 500mg', qty: 200 }, { name: 'Losartan 50mg', qty: 180 }, { name: 'Amlodipine 10mg', qty: 150 }, { name: 'Simvastatin 20mg', qty: 130 }, { name: 'Metformin 500mg', qty: 120 }, { name: 'Omeprazole 20mg', qty: 100 }, { name: 'Ibuprofen 400mg', qty: 90 }, { name: 'Cetirizine 10mg', qty: 80 } ],
        weeklyTrend: [
            { day: 'Mon', sales: 40000 }, { day: 'Tue', sales: 38000 }, { day: 'Wed', sales: 45000 }, { day: 'Thu', sales: 42150 }, { day: 'Fri', sales: 50000 }, { day: 'Sat', sales: 55000 }, { day: 'Sun', sales: 30000 }
        ],
        categorySales: [
            { label: 'Antibiotics', value: 40000, color: 'bg-blue-500' },
            { label: 'Pain Relief', value: 38000, color: 'bg-green-500' },
            { label: 'Vitamins', value: 35000, color: 'bg-yellow-500' },
            { label: 'Chronic Care', value: 30000, color: 'bg-purple-500' },
            { label: 'First Aid', value: 15000, color: 'bg-red-500' }
        ]
    },
    'branch3': {
        id: 'branch3',
        name: 'Branch 3',
        salesToday: '₱21,100.00',
        transactions: '284 transactions today',
        revenueMonth: '₱700,190.00',
        alerts: '8',
        weeklySales: '₱100.00',
        inventoryValue: '₱111,600.00',
        paymentStats: { cash: { amt: '₱100.00', count: 4 }, card: { amt: '₱20.00', count: 1 }, credit: { amt: '₱0.00', count: 0 } },
        inventory: { active: 20, low: 5, out: 2, expiring: 5, expired: 3 },
        topMedicines: [ { name: 'Paracetamol 500mg', qty: 300 }, { name: 'Amoxicillin 500mg', qty: 300 }, { name: 'Vitamin C 500mg', qty: 250 }, { name: 'Losartan 50mg', qty: 270 }, { name: 'Amlodipine 10mg', qty: 200 }, { name: 'Simvastatin 20mg', qty: 220 }, { name: 'Metformin 500mg', qty: 200 }, { name: 'Omeprazole 20mg', qty: 200 }, { name: 'Ibuprofen 400mg', qty: 190 }, { name: 'Cetirizine 10mg', qty: 170 } ],
        weeklyTrend: [
            { day: 'Mon', sales: 25000 }, { day: 'Tue', sales: 17000 }, { day: 'Wed', sales: 25000 }, { day: 'Thu', sales: 21100 }, { day: 'Fri', sales: 30000 }, { day: 'Sat', sales: 40000 }, { day: 'Sun', sales: 20000 }
        ],
        categorySales: [
            { label: 'Antibiotics', value: 30000, color: 'bg-blue-500' },
            { label: 'Pain Relief', value: 30000, color: 'bg-green-500' },
            { label: 'Vitamins', value: 25000, color: 'bg-yellow-500' },
            { label: 'Chronic Care', value: 23000, color: 'bg-purple-500' },
            { label: 'First Aid', value: 10000, color: 'bg-red-500' }
        ]
    }
};

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
window.onload = function() {
    switchBranch('all');
    updateClockStatic();
};