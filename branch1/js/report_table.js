// js/report_table.js

document.addEventListener('DOMContentLoaded', () => {
    const repositoryTableBody = document.getElementById('repositoryTableBody');
    const datePicker = document.getElementById('date-picker');
    const searchOrderIdInput = document.getElementById('searchOrderId');
    const refreshBtn = document.getElementById('refreshBtn');
    const exportCsvBtn = document.getElementById('exportCsvBtn');

    let transactionsData = [];

    // Fetch transaction data from the API
    async function fetchTransactions() {
        try {
            const response = await fetch('api/sales_history.php', {
                method: 'GET',
                credentials: 'same-origin',
            });

            const result = await response.json();
            if (result.success) {
                transactionsData = result.data || [];
                renderTable(transactionsData);
            } else {
                alert('Failed to fetch transactions: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            alert('Error fetching data: ' + error.message);
        }
    }

    // Render the transactions into the table body
    function renderTable(data) {
        repositoryTableBody.innerHTML = '';

        data.forEach((trans) => {
            const tr = document.createElement('tr');
            tr.classList.add('border-b', 'border-gray-200');

            // Order ID
            const orderIdTd = document.createElement('td');
            orderIdTd.className = 'px-4 py-3 border-b border-gray-300';
            orderIdTd.textContent = trans.transaction_id;
            tr.appendChild(orderIdTd);

            // Date & Time - formatted
            const dateTimeTd = document.createElement('td');
            dateTimeTd.className = 'px-4 py-3 border-b border-gray-300';
            const dateObj = new Date(trans.date);
            dateTimeTd.textContent = dateObj.toLocaleString();
            tr.appendChild(dateTimeTd);

            // Items Count (defensive)
            const itemsCountTd = document.createElement('td');
            itemsCountTd.className = 'px-4 py-3 border-b border-gray-300';
            const itemsArr = Array.isArray(trans.items) ? trans.items : (Array.isArray(trans.items_list) ? trans.items_list : []);
            itemsCountTd.textContent = itemsArr.length;
            tr.appendChild(itemsCountTd);

            // Payment Method - infer from items, else placeholder
            const paymentTd = document.createElement('td');
            paymentTd.className = 'px-4 py-3 border-b border-gray-300';
            // The API does not return payment method directly, use total_amount and payment_amount to guess
            paymentTd.textContent = 'Payment'; // For now placeholder, can improve if API changes
            tr.appendChild(paymentTd);

            // Total Amount (defensive)
            const totalAmountTd = document.createElement('td');
            totalAmountTd.className = 'px-4 py-3 border-b border-gray-300';
            const totalVal = Number(
                (trans.total_amount !== undefined && trans.total_amount !== null) ? trans.total_amount : 
                (trans.TotalAmount !== undefined && trans.TotalAmount !== null) ? trans.TotalAmount : 0
            );
            totalAmountTd.textContent = '₱' + totalVal.toFixed(2);
            tr.appendChild(totalAmountTd);

            // Receipt Action - placeholder button
            const actionTd = document.createElement('td');
            actionTd.className = 'px-4 py-3 border-b border-gray-300';

            const receiptBtn = document.createElement('button');
            receiptBtn.textContent = 'View Receipt';
            receiptBtn.className = 'bg-blue-600 hover:bg-blue-700 text-white rounded px-3 py-1 text-sm shadow transition duration-150';

            receiptBtn.addEventListener('click', () => {
                alert(`Receipt viewing for Order ID ${trans.transaction_id} not implemented yet.`);
            });

            actionTd.appendChild(receiptBtn);
            tr.appendChild(actionTd);

            repositoryTableBody.appendChild(tr);
        });

        if(data.length === 0){
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 6;
            td.className = 'text-center p-4 text-gray-500';
            td.textContent = 'No transactions found.';
            tr.appendChild(td);
            repositoryTableBody.appendChild(tr);
        }
    }

    // Apply filters based on date and order ID search
    function applyFilters() {
        let filtered = transactionsData;

        // Filter by date if selected
        if (datePicker.value) {
            const selectedDate = new Date(datePicker.value);
            filtered = filtered.filter(trans => {
                const transDate = new Date(trans.date);
                return transDate.toDateString() === selectedDate.toDateString();
            });
        }

        // Filter by Order ID if typed
        const orderIdSearch = searchOrderIdInput.value.trim();
        if (orderIdSearch !== '') {
            filtered = filtered.filter(trans => 
                trans.transaction_id.toString().includes(orderIdSearch)
            );
        }

        renderTable(filtered);
    }

    // Export currently visible table data to CSV
    function exportToCSV() {
        let rows = [['Order ID','Date & Time','Items Count','Payment','Total Amount']];

        const trs = repositoryTableBody.querySelectorAll('tr');
        trs.forEach(tr => {
            const cells = tr.querySelectorAll('td');
            if (cells.length === 6) {
                const row = [];
                for(let i=0; i<5; i++){
                    row.push(cells[i].textContent);
                }
                rows.push(row);
            }
        });

        if (rows.length === 1) {
            alert('No data to export.');
            return;
        }

        const csvContent = rows.map(r => 
            r.map(field => `"${field.replace(/"/g, '""')}"`).join(',')
        ).join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const now = new Date();
        const filename = `sales_report_${now.toISOString().slice(0,10)}.csv`;
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Event Listeners
    datePicker.addEventListener('change', applyFilters);
    searchOrderIdInput.addEventListener('input', applyFilters);
    refreshBtn.addEventListener('click', () => {
        fetchTransactions();
        datePicker.value = '';
        searchOrderIdInput.value = '';
    });
    exportCsvBtn.addEventListener('click', exportToCSV);

    // Initial fetch
    fetchTransactions();
});
