

// Global pagination state
let currentPage = 1;
let totalPages = 1;
let totalRecords = 0;
let currentLimit = 20;
let currentStartDate = null;
let currentEndDate = null;
let currentSummary = null;

// 1. Fetch Logic
function fetchReport(page = 1, startDate = null, endDate = null) {
    // Reset to page 1 if date filters changed
    if (startDate && endDate && (startDate !== currentStartDate || endDate !== currentEndDate)) {
        page = 1;
        currentStartDate = startDate;
        currentEndDate = endDate;
    }

    const tbody = document.getElementById('report-table-body');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Fetching records...</td></tr>';

    let url = `api/sales_history.php?page=${page}&limit=${currentLimit}&include_summary=1`;
    if (currentStartDate && currentEndDate) {
        url += `&start_date=${currentStartDate}&end_date=${currentEndDate}`;
    }

    fetch(url, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            currentPage = data.pagination.page;
            totalPages = data.pagination.total_pages;
            totalRecords = data.pagination.total_records;
            currentSummary = data.summary || null;

            // Map API response to expected format
            const mappedData = data.data.map(t => ({
                TransactionID: t.TransactionID,
                TransactionDateTime: t.TransactionDateTime,
                TotalAmount: t.TotalAmount,
                PaymentMethod: t.PaymentMethod,
                CashierName: t.CashierName,
                items: t.items.map(item => ({
                    MedicineName: item.name,
                    Quantity: item.qty,
                    PriceAtSale: item.price,
                    Subtotal: item.subtotal
                }))
            }));
            renderTable(mappedData);
            renderPagination();
            renderSummary();
        } else {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">${data.error || 'Failed to load data'}</td></tr>`;
            document.getElementById('pagination-container').innerHTML = '';
            document.getElementById('summary-container').innerHTML = '<div class="text-center text-red-500">Failed to load summary</div>';
        }
    })
    .catch(err => {
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Connection Error</td></tr>';
        document.getElementById('pagination-container').innerHTML = '';
        document.getElementById('summary-container').innerHTML = '<div class="text-center text-red-500">Connection Error</div>';
    });
}

// 2. Render Table (Aligned with DB SalesTransactions table)
let currentTransactions = [];

function renderTable(transactions) {
    currentTransactions = transactions;
    const tbody = document.getElementById('report-table-body');
    tbody.innerHTML = '';

    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No receipts found.</td></tr>';
        return;
    }

    transactions.forEach(t => {
        // Calculate item count from TransactionItems array
        const itemCount = t.items.reduce((sum, item) => sum + parseInt(item.Quantity), 0);

        // Styling based on PaymentMethod ENUM('Cash', 'Card', 'Gcash')
        let badgeClass = 'pill-cash';
        let paymentLabel = t.PaymentMethod || 'Cash';

        if (paymentLabel.toLowerCase() === 'card') badgeClass = 'pill-card';
        if (paymentLabel.toLowerCase() === 'gcash') badgeClass = 'pill-gcash';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="px-6 py-4" style="font-family:monospace; font-weight:bold;">#${t.TransactionID}</td>
            <td class="px-6 py-4">${t.TransactionDateTime}</td>
            <td class="px-6 py-4">${itemCount} items</td>
            <td class="px-6 py-4"><span class="status-pill ${badgeClass}">${paymentLabel}</span></td>
            <td class="px-6 py-4 text-right font-bold">₱${parseFloat(t.TotalAmount).toFixed(2)}</td>
            <td class="px-6 py-4 text-center">
                <button class="action-btn" onclick='viewReceipt(${JSON.stringify(t)})'>
                    <i data-lucide="receipt" size="14"></i> View Receipt
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    lucide.createIcons();
}

// Render Summary
function renderSummary() {
    const container = document.getElementById('summary-container');
    if (!container) return;

    if (!currentSummary) {
        container.innerHTML = '<div class="text-center text-gray-500">No summary data available</div>';
        return;
    }

    const netSales = currentSummary.total_sales - (currentSummary.total_discount || 0);

    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900">${currentSummary.total_transactions}</p>
                    </div>
                    <div class="p-2 bg-blue-100 rounded-full">
                        <i data-lucide="shopping-cart" class="h-6 w-6 text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Sales</p>
                        <p class="text-2xl font-bold text-green-600">₱${parseFloat(currentSummary.total_sales).toFixed(2)}</p>
                    </div>
                    <div class="p-2 bg-green-100 rounded-full">
                        <i data-lucide="dollar-sign" class="h-6 w-6 text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Tax</p>
                        <p class="text-2xl font-bold text-orange-600">₱${parseFloat(currentSummary.total_tax || 0).toFixed(2)}</p>
                    </div>
                    <div class="p-2 bg-orange-100 rounded-full">
                        <i data-lucide="percent" class="h-6 w-6 text-orange-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Discounts</p>
                        <p class="text-2xl font-bold text-red-600">₱${parseFloat(currentSummary.total_discount || 0).toFixed(2)}</p>
                    </div>
                    <div class="p-2 bg-red-100 rounded-full">
                        <i data-lucide="tag" class="h-6 w-6 text-red-600"></i>
                    </div>
                </div>
            </div>
        </div>
    `;
    lucide.createIcons();
}

// Render Pagination Controls
function renderPagination() {
    const container = document.getElementById('pagination-container');
    if (!container || totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    const startItem = (currentPage - 1) * currentLimit + 1;
    const endItem = Math.min(currentPage * currentLimit, totalRecords);
    const totalItem = totalRecords;

    let pageButtons = '';
    const maxVisiblePages = 5;
    const halfVisible = Math.floor(maxVisiblePages / 2);

    let startPage = Math.max(1, currentPage - halfVisible);
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentPage ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
        pageButtons += `<button onclick="fetchReport(${i})" type="button" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ${activeClass}" aria-current="${i === currentPage ? 'page' : 'false'}">${i}</button>`;
    }

    const html = `
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    <span class="font-medium">Showing</span>
                    <span class="ml-1">${startItem}</span> to
                    <span class="ml-1">${endItem}</span> of
                    <span class="ml-1 font-medium">${totalItem}</span> results
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <button onclick="fetchReport(${Math.max(1, currentPage - 1)})" ${currentPage === 1 ? 'disabled' : ''} class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 focus:z-20 focus:outline-offset-0" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
                    ${pageButtons}
                    <button onclick="fetchReport(${Math.min(totalPages, currentPage + 1)})" ${currentPage === totalPages ? 'disabled' : ''} class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 focus:z-20 focus:outline-offset-0" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
                </nav>
            </div>
        </div>
    `;
    container.innerHTML = html;
}

// 3. Search Filter Logic
document.getElementById('search-input').addEventListener('keyup', (e) => {
    const term = e.target.value.toLowerCase();
    const filtered = currentTransactions.filter(t => 
        t.TransactionID.toString().includes(term)
    );
    
    const tbody = document.getElementById('report-table-body');
    tbody.innerHTML = '';
    
    if(filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No matching Order ID</td></tr>';
        return;
    }

    filtered.forEach(t => {
        const itemCount = t.items.reduce((sum, item) => sum + parseInt(item.Quantity), 0);
        let badgeClass = 'pill-cash';
        let paymentLabel = t.PaymentMethod || 'Cash';
        if (paymentLabel.toLowerCase() === 'card') badgeClass = 'pill-card';
        if (paymentLabel.toLowerCase() === 'gcash') badgeClass = 'pill-gcash';

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50';
        tr.innerHTML = `
            <td class="py-3 px-4 border-b border-gray-200">${t.TransactionID}</td>
            <td class="py-3 px-4 border-b border-gray-200">${t.TransactionDateTime}</td>
            <td class="py-3 px-4 border-b border-gray-200">${itemCount} items</td>
            <td class="py-3 px-4 border-b border-gray-200">
                <span class="px-2 py-1 rounded-full text-xs font-medium ${badgeClass === 'pill-cash' ? 'bg-green-100 text-green-800' : badgeClass === 'pill-card' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}">
                    ${paymentLabel}
                </span>
            </td>
            <td class="py-3 px-4 border-b border-gray-200 text-right font-bold">₱${parseFloat(t.TotalAmount).toFixed(2)}</td>
            <td class="py-3 px-4 border-b border-gray-200 text-center">
                <button class="text-blue-600 hover:text-blue-800 mr-2" onclick='viewReceipt(${JSON.stringify(t)})'>
                    <i data-lucide="receipt" class="h-4 w-4"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    lucide.createIcons();
});

// 4. Receipt Modal Logic (Using Invoice Modal)
function viewReceipt(transaction) {
    // Map transaction data to invoice modal format
    const invoiceData = {
        transaction_id: transaction.TransactionID,
        items: transaction.items.map(item => ({
            name: item.MedicineName || item.name,
            qty: item.Quantity || item.qty,
            price: item.PriceAtSale || item.price
        })),
        total_amount: transaction.TotalAmount,
        raw_total: transaction.TotalAmount + (transaction.TotalDiscountAmount || 0) - (transaction.TotalTaxAmount || 0), // Calculate raw total
        discount_amount: transaction.TotalDiscountAmount || 0,
        vat_amount: transaction.TotalTaxAmount || 0,
        discount_type: 'regular', // Default, as not stored in history
        payment_amount: transaction.TotalAmount, // Assume payment equals total for historical data
        change_amount: 0, // Assume no change for historical data
        payment_method: transaction.PaymentMethod || 'Cash',
        branch: transaction.BranchName || 'Main',
        cashier: transaction.CashierName || DEFAULT_CASHIER
    };

    // Call the invoice modal function
    if (window.showInvoiceModal) {
        window.showInvoiceModal(invoiceData);
    } else {
        console.error('Invoice modal function not available');
    }
}

// Modal functions removed - invoice modal handles its own closing and printing

// 5. Export CSV Logic
function exportToCSV() {
    // Fetch all data (ignoring pagination) with current filters
    let url = `api/sales_history.php?limit=10000`; // High limit to get all records
    if (currentStartDate && currentEndDate) {
        url += `&start_date=${currentStartDate}&end_date=${currentEndDate}`;
    }

    fetch(url, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.data.length > 0) {
            // Generate CSV content
            const csvHeaders = ['Transaction ID', 'Date & Time', 'Items Count', 'Payment Method', 'Total Amount', 'Cashier Name'];
            let csvContent = csvHeaders.join(',') + '\n';

            data.data.forEach(transaction => {
                const itemCount = transaction.items.reduce((sum, item) => sum + parseInt(item.Quantity || item.qty), 0);
                const row = [
                    transaction.TransactionID,
                    `"${transaction.TransactionDateTime}"`,
                    itemCount,
                    `"${transaction.PaymentMethod || 'Cash'}"`,
                    transaction.TotalAmount,
                    `"${transaction.CashierName || DEFAULT_CASHIER}"`
                ];
                csvContent += row.join(',') + '\n';
            });

            // Create and trigger download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `sales_report_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            alert('No data available for export.');
        }
    })
    .catch(err => {
        console.error('Export error:', err);
        alert('Failed to export data. Please try again.');
    });
}

// 6. Initialize
document.addEventListener('DOMContentLoaded', () => {
    const fp = flatpickr("#date-picker", {
        mode: "range",
        dateFormat: "Y-m-d",
        onChange: (selectedDates) => {
            if (selectedDates.length === 2) {
                const start = selectedDates[0].toISOString().split('T')[0];
                const end = selectedDates[1].toISOString().split('T')[0];
                fetchReport(1, start, end); // Reset to page 1 when applying date filter
            } else if (selectedDates.length === 0) {
                // Clear date filters and show all data
                currentStartDate = null;
                currentEndDate = null;
                fetchReport(1); // Reset to page 1 and show all data
            }
        }
    });

    // Load all data initially (no date filter)
    fetchReport(1);

    document.getElementById('refresh-btn').addEventListener('click', () => {
         const dates = fp.selectedDates;
         if(dates.length === 2) {
            const start = dates[0].toISOString().split('T')[0];
            const end = dates[1].toISOString().split('T')[0];
            fetchReport(1, start, end); // Reset to page 1 when refreshing with date filter
         } else {
            // Refresh all data
            fetchReport(1);
         }
    });

    // Add export button listener
    document.getElementById('export-btn').addEventListener('click', exportToCSV);

    // Add receipt display event listeners
    const closeReceiptBtn = document.getElementById('close-receipt-btn');
    if (closeReceiptBtn) {
        closeReceiptBtn.addEventListener('click', closeReceiptDisplay);
    }

    const emailReceiptBtn = document.getElementById('email-receipt-btn');
    if (emailReceiptBtn) {
        emailReceiptBtn.addEventListener('click', function() {
            // For now, show a placeholder message. In a real implementation,
            // this would open an email modal or send the receipt via email
            alert('Email functionality would be implemented here. Receipt data is ready for sending.');
        });
    }
});
