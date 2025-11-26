<?php
// reports.php

// ------------------------------------------------------------------
// ACCESS CONTROL CHECK
// ------------------------------------------------------------------

session_start();

// 1. Check if the user is not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit;
}

// 2. Check Role: Only Staff or Admin can view this page.
if ($_SESSION["user_role"] !== 'Staff' && $_SESSION["user_role"] !== 'Admin') {
    die("ERROR: You do not have permission to view this page.");
}

// 3. Check Branch (Crucial for Staff access).
$required_branch_id = 1; // OR dynamically set based on session for Admin
if ($_SESSION["user_role"] === 'Staff' && $_SESSION["branch_id"] != $required_branch_id) {
    header("Location: ../login.php?error=branch_mismatch");
    exit;
}

// Fallback name if API doesn't return the specific cashier for a transaction
$current_staff_name = $_SESSION["username"] ?? "Staff";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Ledger & Receipts - Branch <?php echo $required_branch_id; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Lucide Icons for nice UI elements -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* --- GENERAL STYLING --- */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .report-container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 1100px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .header-section h1 {
            font-size: 22px;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* --- CONTROLS --- */
        .controls-wrapper {
            display: flex;
            gap: 10px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .input-group {
            position: relative;
        }

        .input-control {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .input-control:focus { border-color: #4a90e2; }

        .search-box { width: 250px; }
        .date-box { width: 220px; }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .btn-primary { background-color: #4a90e2; color: white; }
        .btn-refresh { background-color: #2ecc71; color: white; }

        /* --- TABLE --- */
        .table-wrapper {
            overflow-x: auto;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background-color: #f8f9fa;
            color: #5f6368;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 2px solid #e1e4e8;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            color: #333;
        }

        tr:hover { background-color: #fcfcfc; }

        /* Payment Method Pills (Aligned with DB ENUM) */
        .status-pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .pill-cash { background-color: #e6fffa; color: #047857; border: 1px solid #b7eb8f; } /* Green */
        .pill-card { background-color: #fff7e6; color: #d46b08; border: 1px solid #ffd591; } /* Orange */
        .pill-gcash { background-color: #e6f7ff; color: #096dd9; border: 1px solid #91d5ff; } /* Blue */

        .action-btn {
            background-color: #edf2f7;
            color: #2d3748;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .action-btn:hover { background-color: #e2e8f0; }

        /* --- RECEIPT MODAL (THE "STORED RECEIPT") --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .receipt-modal {
            background: white;
            padding: 0;
            border-radius: 8px;
            width: 380px; /* Thermal printer width simulation */
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .receipt-header-bar {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .receipt-body {
            padding: 20px;
            overflow-y: auto;
            background: #fff;
            font-family: 'Courier New', Courier, monospace; /* Receipt Font */
        }

        /* Thermal Printer CSS Styling */
        .receipt-paper {
            width: 100%;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
        }
        .rp-header { text-align: center; margin-bottom: 15px; }
        .rp-title { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .rp-info { font-size: 11px; color: #333; }
        .rp-dashed { border-top: 1px dashed #000; margin: 10px 0; }
        
        .rp-table { width: 100%; }
        .rp-table td { padding: 2px 0; border: none; }
        .rp-right { text-align: right; }
        
        .rp-total-section { margin-top: 10px; font-weight: bold; }
        .rp-footer { text-align: center; margin-top: 20px; font-size: 10px; }

        .receipt-actions {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            background: #f9f9f9;
            border-radius: 0 0 8px 8px;
        }

        /* Print Specifics */
        @media print {
            body * { visibility: hidden; }
            #printable-receipt-area, #printable-receipt-area * { visibility: visible; }
            #printable-receipt-area { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 100%; 
                padding: 0;
                margin: 0;
            }
            .modal-overlay { position: static; display: block; background: white; }
            .receipt-actions, .receipt-header-bar { display: none; }
        }
    </style>
</head>
<body>

    <div class="report-container">
        <!-- HEADER -->
        <div class="header-section">
            <h1><i data-lucide="scroll-text"></i> Sales Ledger & Receipts</h1>
            <span style="color: #666; font-size: 14px;">Branch <?php echo $required_branch_id; ?> Repository</span>
        </div>

        <!-- FILTERS -->
        <div class="controls-wrapper">
            <input type="text" id="date-picker" class="input-control date-box" placeholder="Filter by Date">
            
            <input type="text" id="search-input" class="input-control search-box" placeholder="Search Order ID (e.g., 1024)">
            
            <button id="refresh-btn" class="btn btn-refresh">
                <i data-lucide="refresh-cw" size="16"></i> Refresh
            </button>
            
            <button id="export-btn" class="btn btn-primary" style="background-color: #6c757d;">
                <i data-lucide="download" size="16"></i> Export CSV
            </button>
        </div>

        <!-- MAIN TABLE (Clean View) -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th width="15%">Order ID</th>
                        <th width="20%">Date & Time</th>
                        <th width="15%">Items Count</th>
                        <th width="15%">Payment</th>
                        <th width="15%" style="text-align: right;">Total Amount</th>
                        <th width="20%" style="text-align: center;">Receipt Action</th>
                    </tr>
                </thead>
                <tbody id="report-table-body">
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">Loading records...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- DIGITAL RECEIPT MODAL -->
    <div id="receipt-modal" class="modal-overlay">
        <div class="receipt-modal">
            <div class="receipt-header-bar">
                <strong>Digital Receipt View</strong>
                <button onclick="closeModal()" style="border:none; background:none; cursor:pointer; font-size:18px;">&times;</button>
            </div>
            
            <!-- Actual Receipt Content (Printable) -->
            <div class="receipt-body" id="printable-receipt-area">
                <div class="receipt-paper">
                    <div class="rp-header">
                        <div class="rp-title">PHARMACY BRANCH <?php echo $required_branch_id; ?></div>
                        <div class="rp-info">123 Main Street, City Center</div>
                        <div class="rp-info">Tel: (043) 123-4567</div>
                    </div>
                    
                    <div class="rp-dashed"></div>
                    
                    <div style="display:flex; justify-content:space-between; font-size:11px;">
                        <span>OR#: <strong id="rec-id">000</strong></span>
                        <span id="rec-date">00/00/0000</span>
                    </div>
                    <div style="font-size:11px;">Cashier: <span id="rec-cashier">Unknown</span></div>
                    
                    <div class="rp-dashed"></div>
                    
                    <table class="rp-table" id="rec-items-list">
                        <!-- Items injected here via JS -->
                    </table>
                    
                    <div class="rp-dashed"></div>
                    
                    <div class="rp-total-section">
                        <div style="display:flex; justify-content:space-between;">
                            <span>TOTAL:</span>
                            <span id="rec-total" style="font-size: 14px;">0.00</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:11px; margin-top:5px; font-weight:normal;">
                            <span>Payment Method:</span>
                            <span id="rec-payment-method">CASH</span>
                        </div>
                    </div>
                    
                    <div class="rp-footer">
                        Thank you for your purchase!<br>
                        This serves as your official receipt.<br>
                        Returns valid within 7 days.
                    </div>
                </div>
            </div>

            <div class="receipt-actions">
                <button onclick="closeModal()" class="btn" style="background:#eee; color:#333;">Close</button>
                <button onclick="printReceipt()" class="btn btn-primary">
                    <i data-lucide="printer" size="16"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        const BRANCH_ID = <?php echo $required_branch_id; ?>;
        const DEFAULT_CASHIER = "<?php echo $current_staff_name; ?>";
        lucide.createIcons();

        // 1. Fetch Logic
        function fetchReport(startDate, endDate) {
            const tbody = document.getElementById('report-table-body');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Fetching records...</td></tr>';

            fetch('api/reports_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_report',
                    start_date: startDate,
                    end_date: endDate,
                    branch_id: BRANCH_ID
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderTable(data.transactions);
                } else {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: red;">${data.message}</td></tr>`;
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Connection Error</td></tr>';
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
                    <td style="font-family:monospace; font-weight:bold;">#${t.TransactionID}</td>
                    <td>${t.TransactionDateTime}</td>
                    <td>${itemCount} items</td>
                    <td><span class="status-pill ${badgeClass}">${paymentLabel}</span></td>
                    <td style="text-align: right; font-weight:bold;">₱${parseFloat(t.TotalAmount).toFixed(2)}</td>
                    <td style="display:flex; justify-content:center;">
                        <button class="action-btn" onclick='viewReceipt(${JSON.stringify(t)})'>
                            <i data-lucide="receipt" size="14"></i> View Receipt
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            lucide.createIcons();
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
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No matching Order ID</td></tr>';
                return;
            }

            filtered.forEach(t => {
                const itemCount = t.items.reduce((sum, item) => sum + parseInt(item.Quantity), 0);
                let badgeClass = 'pill-cash';
                let paymentLabel = t.PaymentMethod || 'Cash';
                if (paymentLabel.toLowerCase() === 'card') badgeClass = 'pill-card';
                if (paymentLabel.toLowerCase() === 'gcash') badgeClass = 'pill-gcash';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-family:monospace; font-weight:bold;">#${t.TransactionID}</td>
                    <td>${t.TransactionDateTime}</td>
                    <td>${itemCount} items</td>
                    <td><span class="status-pill ${badgeClass}">${paymentLabel}</span></td>
                    <td style="text-align: right; font-weight:bold;">₱${parseFloat(t.TotalAmount).toFixed(2)}</td>
                    <td style="display:flex; justify-content:center;">
                        <button class="action-btn" onclick='viewReceipt(${JSON.stringify(t)})'>
                            <i data-lucide="receipt" size="14"></i> View Receipt
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            lucide.createIcons();
        });

        // 4. Receipt Modal Logic (Aligned with TransactionItems)
        function viewReceipt(transaction) {
            const modal = document.getElementById('receipt-modal');
            const list = document.getElementById('rec-items-list');
            
            // Header Info
            document.getElementById('rec-id').innerText = transaction.TransactionID;
            document.getElementById('rec-date').innerText = transaction.TransactionDateTime.split(' ')[0];
            document.getElementById('rec-total').innerText = '₱' + parseFloat(transaction.TotalAmount).toFixed(2);
            document.getElementById('rec-payment-method').innerText = (transaction.PaymentMethod || 'CASH').toUpperCase();
            
            // Cashier Name: Use the one from DB (via JOIN Accounts) or fallback
            // Ensure your API returns 'CashierName' (FirstName + LastName)
            document.getElementById('rec-cashier').innerText = transaction.CashierName || DEFAULT_CASHIER;

            // List Items (Using PriceAtSale and Subtotal from DB)
            list.innerHTML = '';
            transaction.items.forEach(item => {
                // Use PriceAtSale as defined in TransactionItems table
                const price = parseFloat(item.PriceAtSale); 
                const subtotal = parseFloat(item.Subtotal); 

                list.innerHTML += `
                    <tr>
                        <td colspan="2">${item.MedicineName}</td>
                    </tr>
                    <tr style="font-size:11px; color:#555;">
                        <td>${item.Quantity} x ${price.toFixed(2)}</td>
                        <td class="rp-right">₱${subtotal.toFixed(2)}</td>
                    </tr>
                `;
            });

            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('receipt-modal').style.display = 'none';
        }

        function printReceipt() {
            window.print();
        }

        window.onclick = function(event) {
            const modal = document.getElementById('receipt-modal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // 5. Initialize
        document.addEventListener('DOMContentLoaded', () => {
            const fp = flatpickr("#date-picker", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: [new Date(Date.now() - 30 * 24 * 60 * 60 * 1000), new Date()],
                onChange: (selectedDates) => {
                    if (selectedDates.length === 2) {
                        const start = selectedDates[0].toISOString().split('T')[0];
                        const end = selectedDates[1].toISOString().split('T')[0];
                        fetchReport(start, end);
                    }
                }
            });

            const today = new Date();
            const lastMonth = new Date(today);
            lastMonth.setDate(today.getDate() - 30);
            fetchReport(lastMonth.toISOString().split('T')[0], today.toISOString().split('T')[0]);

            document.getElementById('refresh-btn').addEventListener('click', () => {
                 const dates = fp.selectedDates;
                 if(dates.length === 2) {
                    const start = dates[0].toISOString().split('T')[0];
                    const end = dates[1].toISOString().split('T')[0];
                    fetchReport(start, end);
                 }
            });
        });
    </script>
</body>
</html>