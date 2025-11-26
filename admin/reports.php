<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Reports & Analytics</title>
    
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom styles and Tailwind configuration -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root {
            font-family: 'Inter', sans-serif;
        }
        
        .reports-input {
            @apply px-4 py-2 border border-gray-300 rounded-lg focus:ring-pharmacy-primary focus:border-pharmacy-primary text-sm shadow-sm transition-colors;
        }
        .reports-select {
            @apply px-4 py-2 border border-gray-300 rounded-lg focus:ring-pharmacy-primary focus:border-pharmacy-primary text-sm shadow-sm transition-colors bg-white;
        }
        .reports-btn {
            @apply px-6 py-2 rounded-lg font-semibold text-white transition-all duration-200 shadow-md;
        }
    </style>
    <script>
        // Tailwind Configuration (copied from dashboard)
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'pharmacy-primary': '#0D9488',
                        'pharmacy-secondary': '#14B8A6',
                        'pharmacy-bg': '#F3F4F6',
                        'card-bg': '#FFFFFF',
                        'success-green': '#10B981',
                        'danger-red': '#EF4444',
                        'warning-yellow': '#FBBF24',
                        'text-dark': '#1F2937',
                        'text-medium': '#4B5563',
                        'text-light': '#6B7280',
                        'text-alert': '#F59E0B',
                        'text-danger': '#EF4444',
                    }
                }
            }
        }

        // --- Mock Data ---
        const mockTransactions = [
            { id: 1001, date: '2025-11-25 10:30', branch: 'Main Branch', staff: 'John Doe', amount: 5120.00, discount: 0.00, type: 'Regular' },
            { id: 1002, date: '2025-11-25 11:45', branch: 'Second St. Clinic', staff: 'Jane Smith', amount: 1200.00, discount: 240.00, type: 'Senior Citizen' },
            { id: 1003, date: '2025-11-25 12:15', branch: 'Main Branch', staff: 'John Doe', amount: 3500.00, discount: 0.00, type: 'Regular' },
            { id: 1004, date: '2025-11-25 14:00', branch: 'Second St. Clinic', staff: 'Mark Lee', amount: 890.00, discount: 0.00, type: 'Regular' },
            { id: 1005, date: '2025-11-25 15:30', branch: 'HQ Pharmacy', staff: 'Sarah Connor', amount: 750.00, discount: 150.00, type: 'PWD' },
            { id: 1006, date: '2025-11-25 16:10', branch: 'Main Branch', staff: 'Jane Smith', amount: 2400.00, discount: 0.00, type: 'Regular' },
            { id: 1007, date: '2025-11-25 17:50', branch: 'HQ Pharmacy', staff: 'Sarah Connor', amount: 480.00, discount: 0.00, type: 'Regular' },
        ];

        // --- Utility Functions ---

        /**
         * Formats a number as Philippine Peso (₱).
         * @param {number} value 
         */
        function formatCurrency(value) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(value);
        }

        /**
         * Renders the mock sales transaction data into the table.
         */
        function renderTransactionTable(data) {
            const tbody = document.getElementById('report-table-body');
            if (!tbody) return;

            tbody.innerHTML = ''; // Clear existing rows

            data.forEach(item => {
                const row = document.createElement('tr');
                row.className = 'border-b hover:bg-gray-50 transition-colors';
                
                row.innerHTML = `
                    <td class="px-6 py-3 font-mono text-xs">${item.id}</td>
                    <td class="px-6 py-3 text-sm text-text-medium">${item.date}</td>
                    <td class="px-6 py-3 text-sm">${item.branch}</td>
                    <td class="px-6 py-3 text-sm">${item.staff}</td>
                    <td class="px-6 py-3 text-right font-bold text-pharmacy-primary">${formatCurrency(item.amount)}</td>
                    <td class="px-6 py-3 text-right text-text-alert">${formatCurrency(item.discount)}</td>
                    <td class="px-6 py-3">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full ${item.type === 'Senior Citizen' ? 'bg-indigo-100 text-indigo-700' : (item.type === 'PWD' ? 'bg-pink-100 text-pink-700' : 'bg-gray-100 text-gray-700')}">
                            ${item.type}
                        </span>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        /**
         * Calculates and updates the KPI cards based on the mock data.
         */
        function updateKPIs(data) {
            const totalRevenue = data.reduce((sum, item) => sum + item.amount - item.discount, 0);
            const totalTransactions = data.length;
            const avgTransactionValue = totalTransactions > 0 ? totalRevenue / totalTransactions : 0;
            const totalDiscounts = data.reduce((sum, item) => sum + item.discount, 0);

            document.getElementById('kpi-revenue').textContent = formatCurrency(totalRevenue);
            document.getElementById('kpi-transactions').textContent = totalTransactions.toLocaleString();
            document.getElementById('kpi-atv').textContent = formatCurrency(avgTransactionValue);
            document.getElementById('kpi-discounts').textContent = formatCurrency(totalDiscounts);
        }

        /**
         * Placeholder function for rendering the chart area.
         */
        function renderChartPlaceholder() {
            const chartArea = document.getElementById('report-chart-area');
            if (!chartArea) return;
            
            // Simple placeholder bar chart using DIVs for visualization
            chartArea.innerHTML = `
                <div class="h-full flex items-end justify-between px-4 py-2">
                    <div class="w-1/6 h-3/4 bg-pharmacy-secondary rounded-t-lg shadow-md hover:bg-pharmacy-primary transition-colors duration-200 flex items-end justify-center text-xs text-white font-bold" title="Revenue: ₱15,000">Q1</div>
                    <div class="w-1/6 h-full bg-pharmacy-secondary rounded-t-lg shadow-md hover:bg-pharmacy-primary transition-colors duration-200 flex items-end justify-center text-xs text-white font-bold" title="Revenue: ₱20,500">Q2</div>
                    <div class="w-1/6 h-1/2 bg-pharmacy-secondary rounded-t-lg shadow-md hover:bg-pharmacy-primary transition-colors duration-200 flex items-end justify-center text-xs text-white font-bold" title="Revenue: ₱10,200">Q3</div>
                    <div class="w-1/6 h-5/6 bg-pharmacy-secondary rounded-t-lg shadow-md hover:bg-pharmacy-primary transition-colors duration-200 flex items-end justify-center text-xs text-white font-bold" title="Revenue: ₱17,800">Q4</div>
                </div>
            `;
        }

        // --- Initialization ---
        window.onload = function() {
            renderTransactionTable(mockTransactions);
            updateKPIs(mockTransactions);
            renderChartPlaceholder();
            
            // Log for debugging/monitoring
            console.log("Reports module UI loaded and mock data rendered.");
        };

    </script>
</head>
<body class="bg-pharmacy-bg min-h-screen text-text-dark">

    <div class="max-w-8xl mx-auto p-4 md:p-8">
        
        <!-- Header Section -->
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-pharmacy-primary">Analytics & Reports Hub</h1>
                <p class="text-sm text-text-light">Generate, filter, and export detailed operational reports.</p>
            </div>
            <!-- Main Actions (Refined Export Buttons) -->
            <div class="space-x-4 flex items-center">
                <button onclick="console.log('Generating PDF...')" class="reports-btn bg-red-600 hover:bg-red-700" title="Download current report view as a PDF document">
                    <div class="flex items-center space-x-2">
                        <!-- File Icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                        <span>Export Report (PDF)</span>
                    </div>
                </button>
                <button onclick="console.log('Generating CSV...')" class="reports-btn bg-gray-500 hover:bg-gray-600" title="Download detailed table data as a CSV file">
                    <div class="flex items-center space-x-2">
                        <!-- Download Icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>Export Data (CSV)</span>
                    </div>
                </button>
            </div>
        </header>

        <!-- Report Filtering & Selector Bar -->
        <div class="bg-card-bg p-6 rounded-xl shadow-lg mb-8">
            <h3 class="text-lg font-semibold text-text-dark mb-4 border-b pb-2">Filter and Select Report Type</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                
                <!-- Report Type Selector -->
                <div class="md:col-span-2">
                    <label for="report-type" class="block text-xs font-medium text-text-light mb-1">Report Type</label>
                    <select id="report-type" class="reports-select w-full">
                        <option value="sales-summary" selected>Sales Transaction Summary</option>
                        <option value="inventory-status">Branch Inventory Status</option>
                        <option value="top-sellers">Top 10 Selling Medicines</option>
                        <option value="staff-performance">Staff Sales Performance</option>
                    </select>
                </div>

                <!-- Branch Selector -->
                <div>
                    <label for="branch-select" class="block text-xs font-medium text-text-light mb-1">Branch</label>
                    <select id="branch-select" class="reports-select w-full">
                        <option value="all" selected>All Branches</option>
                        <option value="1">Main Branch</option>
                        <option value="2">Second St. Clinic</option>
                        <option value="3">HQ Pharmacy</option>
                    </select>
                </div>
                
                <!-- Date Range Input -->
                <div>
                    <label for="date-start" class="block text-xs font-medium text-text-light mb-1">Date Start</label>
                    <input type="date" id="date-start" value="2025-11-01" class="reports-input w-full">
                </div>
                
                <!-- Date Range Input -->
                <div>
                    <label for="date-end" class="block text-xs font-medium text-text-light mb-1">Date End</label>
                    <input type="date" id="date-end" value="2025-11-25" class="reports-input w-full">
                </div>

            </div>

            <!-- Apply Filters Button (Refined) -->
            <div class="mt-6 flex justify-end">
                <button onclick="console.log('Applying filters...')" class="reports-btn bg-pharmacy-primary hover:bg-pharmacy-secondary">
                    <div class="flex items-center space-x-2">
                        <!-- Filter/Search Icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <span>View Report</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- KPI Row (Summary of Report) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- 1. Total Revenue -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg border-b-4 border-success-green">
                <p class="text-sm mb-2 text-text-light">Total Net Revenue</p>
                <p id="kpi-revenue" class="text-3xl font-extrabold text-success-green">₱0.00</p>
                <p class="text-xs text-text-medium mt-1">Total income after discounts.</p>
            </div>

            <!-- 2. Total Transactions -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg border-b-4 border-pharmacy-primary">
                <p class="text-sm mb-2 text-text-light">Total Transactions</p>
                <p id="kpi-transactions" class="text-3xl font-extrabold text-pharmacy-primary">0</p>
                <p class="text-xs text-text-medium mt-1">Number of sales in the period.</p>
            </div>

            <!-- 3. Average Transaction Value (ATV) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg border-b-4 border-warning-yellow">
                <p class="text-sm mb-2 text-text-light">Avg. Transaction Value (ATV)</p>
                <p id="kpi-atv" class="text-3xl font-extrabold text-text-alert">₱0.00</p>
                <p class="text-xs text-text-medium mt-1">Average amount spent per customer.</p>
            </div>

            <!-- 4. Total Discounts Applied -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg border-b-4 border-danger-red">
                <p class="text-sm mb-2 text-text-light">Total Discounts Applied</p>
                <p id="kpi-discounts" class="text-3xl font-extrabold text-danger-red">₱0.00</p>
                <p class="text-xs text-text-medium mt-1">Total monetary value of all discounts.</p>
            </div>
        </div>

        <!-- Chart Visualization and Detailed Table -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- 1. Visualization Area (1/3 width) -->
            <div class="lg:col-span-1 bg-card-bg p-6 rounded-xl shadow-lg flex flex-col">
                <h2 class="text-xl font-semibold text-text-dark mb-4">Revenue Breakdown (Quarterly Mock)</h2>
                <div id="report-chart-area" class="flex-grow h-64 border border-gray-100 rounded-lg p-2">
                    <!-- Chart content will be injected by JavaScript -->
                    <p class="text-center text-text-light mt-12">Loading Chart...</p>
                </div>
                <p class="text-xs text-text-light mt-4">Visualization for: Sales Transaction Summary</p>
            </div>

            <!-- 2. Detailed Data Table (2/3 width) -->
            <div class="lg:col-span-2 bg-card-bg p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-text-dark mb-4">Detailed Transaction Log</h2>
                
                <!-- Table Controls (Search & Pagination) -->
                <div class="flex justify-between items-center mb-4">
                    <input type="text" placeholder="Search by Transaction ID or Staff Name..." class="reports-input w-full max-w-xs" oninput="console.log('Searching...')">
                    <div class="flex items-center space-x-2 text-sm text-text-medium">
                        <span class="font-medium">Page 1 of 5</span>
                        <button class="px-3 py-1 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Prev</button>
                        <button class="px-3 py-1 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Next</button>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-light uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-light uppercase tracking-wider">Date/Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-light uppercase tracking-wider">Branch</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-light uppercase tracking-wider">Staff</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-text-light uppercase tracking-wider">Total (Net)</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-text-light uppercase tracking-wider">Discount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-light uppercase tracking-wider">Customer Type</th>
                            </tr>
                        </thead>
                        <tbody id="report-table-body" class="bg-white divide-y divide-gray-100">
                            <!-- Rows injected by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</body>
</html>