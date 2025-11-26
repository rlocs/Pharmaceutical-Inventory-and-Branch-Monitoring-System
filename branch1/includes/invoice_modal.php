<!-- invoice_modal.php: Fixed Thermal Receipt Modal with Email Functionality -->

<!-- 1. The Overlay (Background) -->
<div id="invoiceModal" class="fixed inset-0 bg-black bg-opacity-60 hidden z-50 flex justify-center items-center transition-opacity duration-300 backdrop-blur-sm">
    
    <!-- 2. The Modal Content (Receipt) -->
    <!-- Added pb-0 to remove bottom padding so the footer sits flush -->
    <div class="bg-white w-80 mx-auto font-mono text-sm shadow-2xl relative animate-in fade-in zoom-in duration-200 rounded-lg overflow-hidden flex flex-col max-h-[90vh]">
        
        <!-- Scrollable Receipt Content -->
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <!-- Receipt Header -->
            <div class="text-center mb-4 border-b-2 border-dashed border-gray-800 pb-4">
                <h2 class="text-xl font-black uppercase tracking-widest">MERCURY DRUG</h2>
                
                <div class="flex justify-between text-xs mt-2 text-gray-600">
                    <span>Branch:</span>
                    <span id="invoiceBranch" class="font-bold text-black"></span>
                </div>
                
                <div class="flex justify-between text-xs text-gray-600">
                    <span>Date:</span>
                    <span id="invoiceDate"></span>
                </div>

                <div class="flex justify-between text-xs text-gray-600">
                    <span>Cashier:</span>
                    <span id="invoiceCashier"></span>
                </div>

                <div class="flex justify-between text-xs text-gray-600">
                    <span>Trans ID:</span>
                    <span id="invoiceTransactionId"></span>
                </div>
            </div>

            <!-- Receipt Items -->
            <table class="w-full mb-4">
                <thead class="border-b border-dashed border-gray-400">
                    <tr>
                        <th class="text-left py-1 w-1/2">Item</th>
                        <th class="text-center py-1 w-1/4">Qty</th>
                        <th class="text-right py-1 w-1/4">Amt</th>
                    </tr>
                </thead>
                <tbody id="invoiceItems" class="text-xs font-medium">
                    <!-- JS fills this -->
                </tbody>
            </table>

            <!-- Receipt Totals -->
            <div class="border-t-2 border-dashed border-gray-800 pt-2 space-y-1">
                <div class="flex justify-between font-bold text-lg items-end">
                    <span>TOTAL</span>
                    <span id="invoiceTotalAmount" class="text-xl"></span>
                </div>
                <div class="flex justify-between text-xs text-gray-600">
                    <span>CASH</span>
                    <span id="invoicePaymentAmount"></span>
                </div>
                <div class="flex justify-between text-xs text-gray-600">
                    <span>CHANGE</span>
                    <span id="invoiceChangeAmount"></span>
                </div>
                <div class="flex justify-between text-xs text-gray-600">
                    <span>PAYMENT METHOD</span>
                    <span id="invoicePaymentMethod">Cash</span>
                </div>
            </div>

            <!-- Standard Print/Close Buttons -->
            <div class="mt-6 text-center space-y-3 print:hidden">
                <p class="text-xs font-semibold">*** THANK YOU ***</p>
                <div class="grid grid-cols-2 gap-2 mt-4">
                    <button onclick="window.print()" class="col-span-1 bg-gray-800 text-white py-2 rounded hover:bg-black transition text-xs font-bold uppercase flex items-center justify-center gap-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print
                    </button>
                    <button id="closeInvoiceModal" class="col-span-1 border border-red-500 text-red-600 py-2 rounded hover:bg-red-50 transition text-xs font-bold uppercase">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. New Email Footer Section -->
        <footer class="bg-gray-50 border-t border-gray-200 p-4 print:hidden">
            <label class="block text-[10px] font-bold text-gray-400 mb-2 uppercase tracking-wider">Send Receipt via Email</label>
            <div class="flex gap-2">
                <input 
                    type="email" 
                    id="customerEmail" 
                    placeholder="customer@gmail.com" 
                    class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-shadow"
                >
                <button 
                    id="btnSendEmail"
                    class="bg-indigo-600 text-white px-3 py-2 rounded-md text-xs font-bold hover:bg-indigo-700 disabled:bg-gray-400 transition-colors flex items-center gap-1 shadow-sm"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span>Send</span>
                </button>
            </div>
            <!-- Status Message Area -->
            <div id="emailStatusMessage" class="mt-2 text-[10px] font-bold hidden text-center"></div>
        </footer>
    </div>
</div>

<!-- 4. The Logic -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Variables ---
    const invoiceModal = document.getElementById('invoiceModal');
    const closeBtn = document.getElementById('closeInvoiceModal');
    const sendEmailBtn = document.getElementById('btnSendEmail');
    const emailInput = document.getElementById('customerEmail');
    const emailStatus = document.getElementById('emailStatusMessage');
    
    // Store transaction data here so the email function can access it
    let currentInvoiceData = null;

    // --- Helper: Safely set text content ---
    const safeSetText = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    };

    // --- Close Logic ---
    const closeModal = () => {
        if(invoiceModal) invoiceModal.classList.add('hidden');
        // Reset email form when closing
        if(emailInput) emailInput.value = '';
        if(emailStatus) emailStatus.classList.add('hidden');
    };

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (invoiceModal) {
        invoiceModal.addEventListener('click', (e) => {
            if (e.target === invoiceModal) closeModal();
        });
    }

    // --- Main Display Function (Called by POS.js) ---
    window.showInvoiceModal = function(data) {
        currentInvoiceData = data; // Save data for email function
        console.log("Invoice Modal Data Loaded:", data);

        // Reset Email UI
        if(emailInput) emailInput.value = '';
        if(emailStatus) {
            emailStatus.textContent = '';
            emailStatus.classList.add('hidden');
        }
        if(sendEmailBtn) sendEmailBtn.disabled = false;

        // Populate Fields
        safeSetText('invoiceDate', new Date().toLocaleString());
        safeSetText('invoiceTransactionId', '#' + (data.transaction_id || '0000'));
        safeSetText('invoiceBranch', data.branch || 'Main');
        safeSetText('invoiceCashier', data.cashier || 'Staff');

        // Populate Items
        const itemsTbody = document.getElementById('invoiceItems');
        if (itemsTbody) {
            itemsTbody.innerHTML = '';
            if (data.items && Array.isArray(data.items)) {
                data.items.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="text-left py-1 truncate max-w-[120px]">${item.name}</td>
                        <td class="text-center py-1">${item.qty}</td>
                        <td class="text-right py-1">₱${parseFloat(item.price * item.qty).toFixed(2)}</td>
                    `;
                    itemsTbody.appendChild(tr);
                });
            }
        }

        // Populate Money
        const fmt = (num) => '₱' + parseFloat(num || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        safeSetText('invoiceTotalAmount', fmt(data.total_amount));
        safeSetText('invoicePaymentAmount', fmt(data.payment_amount));
        safeSetText('invoiceChangeAmount', fmt(data.change_amount));
        safeSetText('invoicePaymentMethod', data.payment_method || 'Cash');

        // Show Modal
        if (invoiceModal) invoiceModal.classList.remove('hidden');
    };

    // --- Send Email Logic ---
    if(sendEmailBtn) {
        sendEmailBtn.addEventListener('click', function() {
            const email = emailInput.value.trim();
            
            // 1. Validation
            if (!email || !email.includes('@')) {
                emailStatus.textContent = "Please enter a valid email.";
                emailStatus.className = "mt-2 text-[10px] font-bold text-red-600 block text-center";
                return;
            }

            if (!currentInvoiceData) {
                emailStatus.textContent = "Error: No transaction data found.";
                emailStatus.className = "mt-2 text-[10px] font-bold text-red-600 block text-center";
                return;
            }

            // 2. Loading State
            sendEmailBtn.disabled = true;
            const originalBtnText = sendEmailBtn.innerHTML;
            sendEmailBtn.innerHTML = `<svg class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;

            // 3. Prepare Payload
            const payload = {
                email: email,
                transaction_id: currentInvoiceData.transaction_id,
                items: currentInvoiceData.items,
                total: currentInvoiceData.total_amount,
                date: new Date().toLocaleString()
            };

            // 4. Send Request
            // NOTE: Ensure 'handlers/send_invoice_email.php' exists relative to the page calling this script
            fetch('api/send_invoice_email.php', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    emailStatus.textContent = "Receipt sent successfully!";
                    emailStatus.className = "mt-2 text-[10px] font-bold text-green-600 block text-center";
                    emailInput.value = ''; // Clear input on success
                } else {
                    throw new Error(data.message || 'Failed to send');
                }
            })
            .catch(error => {
                console.error('Email Error:', error);
                emailStatus.textContent = "Failed: " + (error.message || "Server error");
                emailStatus.className = "mt-2 text-[10px] font-bold text-red-600 block text-center";
            })
            .finally(() => {
                // Restore Button
                sendEmailBtn.disabled = false;
                sendEmailBtn.innerHTML = originalBtnText;
            });
        });
    }
});
</script>