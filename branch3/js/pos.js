// pos.js: Frontend logic for Mercury POS

document.addEventListener('DOMContentLoaded', () => {
    // State variables
    let order = [];
    let activeTarget = 'qty'; // 'qty' or 'payment'
    let selectedMedicine = null;

    // New state variables for discount and VAT
    let discountPercent = 0;
    let vatPercent = 0;

    const discountInput = document.getElementById('discount_input');
    const vatInput = document.getElementById('vat_input');

    const orderBody = document.getElementById('order-body');
    const totalEl = document.getElementById('total');
    const qtyInput = document.getElementById('qty_input');
    const paymentInput = document.getElementById('payment');
    const changeEl = document.getElementById('change');
    const checkoutButton = document.getElementById('checkout');
    const orderDataInput = document.getElementById('order_data');
    const totalAmountInput = document.getElementById('total_amount');
    const paymentAmountInput = document.getElementById('payment_amount');
    const changeAmountInput = document.getElementById('change_amount');
    const clearOrderBtn = document.getElementById('clear-order');
    const checkoutForm = document.getElementById('checkoutForm');

    // Filter medicines based on search input
    window.filterMeds = function() {
        const filter = document.getElementById('search').value.toLowerCase();
        const meds = document.querySelectorAll('#med-list .med');

        meds.forEach(med => {
            const name = med.getAttribute('data-name').toLowerCase();
            if (name.includes(filter)) {
                med.style.display = '';
            } else {
                med.style.display = 'none';
            }
        });
    };

// Select medicine (clicked on medicine card)
window.selectMedicine = function(element) {
    const id = element.getAttribute('data-id');
    const name = element.getAttribute('data-name');
    const price = parseFloat(element.getAttribute('data-price'));

    selectedMedicine = { id, name, price };

    // Clear qty input and focus when new medicine selected
    qtyInput.value = '';
    qtyInput.focus();
    activeTarget = 'qty';
    updateInputHighlight();

    // Highlight selected medicine visually
    highlightSelectedMedicine(id);
};

    // Highlight selected medicine visually in the med list
    function highlightSelectedMedicine(selectedId) {
        const meds = document.querySelectorAll('#med-list .med');
        meds.forEach(med => {
            if (med.getAttribute('data-id') === selectedId) {
                med.classList.add('border-blue-600', 'shadow-lg');
            } else {
                med.classList.remove('border-blue-600', 'shadow-lg');
            }
        });
    }

// Increment qty by 1 for selected medicine
window.incrementQty = function(button) {
    button.stopPropagation();
    const medElem = button.closest('.med');
    if (!medElem) return;
    const id = medElem.getAttribute('data-id');
    
    if (selectedMedicine && selectedMedicine.id === id) {
        // If already selected, increment from current value (treat empty as 0)
        let currentQty = parseInt(qtyInput.value) || 0;
        qtyInput.value = currentQty + 1;
    } else {
        // If selecting new medicine, select it and set quantity to 1
        selectMedicine(medElem);
        qtyInput.value = '1';
    }
    activeTarget = 'qty';
    updateInputHighlight();
};

    // Update input highlights based on active target
    window.updateInputHighlight = function() {
        if (activeTarget === 'qty') {
            qtyInput.classList.add('ring-2', 'ring-blue-500');
            paymentInput.classList.remove('ring-2', 'ring-blue-500');
        } else {
            paymentInput.classList.add('ring-2', 'ring-blue-500');
            qtyInput.classList.remove('ring-2', 'ring-blue-500');
        }
    };

    // Numpad input handler
    window.numpadInput = function(value) {
        if (activeTarget === 'qty') {
            handleQtyInput(value);
        } else {
            handlePaymentInput(value);
        }
    };

function handleQtyInput(value) {
    if (!selectedMedicine) {
        alert('Please select a medicine first.');
        return;
    }
    if (value === 'X') {
        qtyInput.value = '';
    } else if (value === '✓') {
        // Confirm qty and add medicine to order
        const qty = parseInt(qtyInput.value);
        if (isNaN(qty) || qty <= 0) {
            alert('Please enter a valid quantity.');
            return;
        }
        addOrUpdateOrderRow(selectedMedicine);
        qtyInput.value = '';
        selectedMedicine = null;
        highlightSelectedMedicine(null);
    } else {
        // Append to current value
        qtyInput.value += value;
    }
}

    function handlePaymentInput(value) {
        if (value === 'X') {
            paymentInput.value = '';
            updateChange();
        } else if (value === '✓') {
            // Confirm payment and try to checkout if order is not empty
            if (order.length === 0) {
                alert('No items in the order.');
                return;
            }
            const payment = parseFloat(paymentInput.value);
            const total = calculateFinalTotal().totalWithVat;
            if (isNaN(payment) || payment < total) {
                alert('Insufficient payment amount.');
                return;
            }
            submitOrder(payment);
        } else if (value === '.') {
            if (!paymentInput.value.includes('.')) {
                paymentInput.value += '.';
            }
        } else {
            paymentInput.value += value;
        }
        updateChange();
    }

    // Add or update an order row (keeps DOM and `order` array in sync)
    function addOrUpdateOrderRow(medicine) {
        const qty = parseInt(qtyInput.value || 1, 10) || 1;

        // Update or insert in `order` array (set qty to input value)
        const existingIdx = order.findIndex(i => i.id === medicine.id);
        if (existingIdx >= 0) {
            order[existingIdx].qty = qty;
            order[existingIdx].price = medicine.price;
            order[existingIdx].name = medicine.name;
        } else {
            order.push({ id: medicine.id, name: medicine.name, price: medicine.price, qty: qty });
        }

        renderOrder();
        updateTotals();
        updateChange();
    }

    // Render the current order in the table (also sets data- attributes)
    function renderOrder() {
        orderBody.innerHTML = '';
        order.forEach((item) => {
            const subtotal = item.price * item.qty;
            const tr = document.createElement('tr');
            tr.dataset.id = item.id;
            tr.dataset.qty = item.qty;
            tr.dataset.price = item.price;
            tr.innerHTML = `
                <td class="py-2 px-4 text-center">${item.qty}</td>
                <td class="py-2 px-4">${item.name}</td>
                <td class="py-2 px-4 text-right row-amt">₱${subtotal.toFixed(2)}</td>
                <td class="py-2 px-2 text-center">
                    <button class="text-red-500 hover:text-red-700" aria-label="Remove item" onclick="window.removeRow(this)">&times;</button>
                </td>
            `;
            orderBody.appendChild(tr);
        });
    }

    // Remove item from order by index (kept for backward compatibility)
    function removeOrderItem(index) {
        if (index >= 0 && index < order.length) {
            order.splice(index, 1);
            renderOrder();
            updateTotals();
            updateChange();
        }
    }

    // Remove row helper used by inline onclick in rendered rows
    window.removeRow = function(button) {
        const tr = button.closest('tr');
        if (!tr) return;
        const id = tr.dataset.id;
        const idx = order.findIndex(i => i.id === id);
        if (idx >= 0) {
            order.splice(idx, 1);
        }
        tr.remove();
        renderOrder();
        updateTotals();
        updateChange();
    };

    // Clear the order entirely
    clearOrderBtn.addEventListener('click', () => {
        order = [];
        renderOrder();
        updateTotals();
        qtyInput.value = '';
        paymentInput.value = '';
        changeEl.textContent = '₱0.00';
        selectedMedicine = null;
        highlightSelectedMedicine(null);
        activeTarget = 'qty';
        updateInputHighlight();
    });

    // Calculate raw total (sum of price * qty)
    function calculateRawTotal() {
        return order.reduce((acc, item) => acc + (parseFloat(item.price) || 0) * (parseFloat(item.qty) || 0), 0);
    }

    // Calculate final total after discount and VAT
    function calculateFinalTotal() {
        const raw = calculateRawTotal();
        const discountType = document.getElementById('discount_type') ? document.getElementById('discount_type').value : 'regular';
        const vatPercent = parseFloat(document.getElementById('vat_percent') ? document.getElementById('vat_percent').value : 0) || 0;
        const discount = raw * (discountRates[discountType] || 0);
        const taxable = raw - discount;
        const vat_amount = taxable * (vatPercent / 100);
        const totalWithVat = taxable + vat_amount;
        return { raw, discount, vat_amount, totalWithVat, discountType };
    }

    // Update totals displayed in UI and discount input
    function updateTotals() {
        const totals = calculateFinalTotal();
        const discountInputEl = document.getElementById('discount_amount');
        if (discountInputEl) discountInputEl.value = totals.discount.toFixed(2);

        if (totalEl) {
            totalEl.textContent = `₱${totals.totalWithVat.toFixed(2)}`;
            totalEl.dataset.rawTotal = totals.raw.toFixed(2);
        }
    }

    // Update change display based on payment input
    function updateChange() {
        const payment = parseFloat(paymentInput.value) || 0;
        const total = calculateFinalTotal().totalWithVat;
        const change = payment - total;
        if (change < 0) {
            changeEl.textContent = '₱0.00';
        } else {
            changeEl.textContent = `₱${change.toFixed(2)}`;
        }
    }



    // Payment input click to set active target
    paymentInput.addEventListener('click', () => {
        activeTarget = 'payment';
        updateInputHighlight();
    });

    // Qty input click to set active target
    qtyInput.addEventListener('click', () => {
        activeTarget = 'qty';
        updateInputHighlight();
    });

    // Submit order data to backend API
        async function submitOrder(paymentAmount) {
        // Disable checkout button to prevent multiple submits
        checkoutButton.disabled = true;
        checkoutButton.textContent = 'Processing...';

            const total = calculateFinalTotal().totalWithVat;
        const paymentTypeSelect = document.getElementById('payment_type');
        let paymentMethod = 'Cash';
        if (paymentTypeSelect && paymentTypeSelect.value) {
            paymentMethod = paymentTypeSelect.value;
        }

        const totals = calculateFinalTotal();
        const payload = {
            items: order.map(item => ({
                id: parseInt(item.id),
                name: item.name,
                qty: item.qty,
                price: item.price
            })),
            total_amount: totals.totalWithVat,
            raw_total: totals.raw,
            discount_amount: totals.discount,
            vat_amount: totals.vat_amount,
            discount_type: totals.discountType || (document.getElementById('discount_type') ? document.getElementById('discount_type').value : 'regular'),
            payment_method: paymentMethod, // Use selected payment method or default to Cash
            customer_name: null // Could extend UI for customer name if needed
        };

        try {
            const response = await fetch('api/pos_api.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

if (response.ok && data.success) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Transaction completed successfully!',
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    }).then(() => {
        // Reset order UI
        order = [];
        renderOrder();
        updateTotals();
        qtyInput.value = '';
        paymentInput.value = '';
        const paymentTypeSelect = document.getElementById('payment_type');
        if (paymentTypeSelect) {
            paymentTypeSelect.value = '';
        }
        changeEl.textContent = '₱0.00';

        // Set values in hidden inputs
        orderDataInput.value = JSON.stringify(payload.items);
        totalAmountInput.value = payload.total_amount.toFixed(2);
        paymentAmountInput.value = paymentAmount.toFixed(2);
        changeAmountInput.value = (paymentAmount - payload.total_amount).toFixed(2);

        // Show the invoice modal instead of submitting form
        window.showInvoiceModal({
            transaction_id: data.transaction_id,
            cashier: window.currentUserFull || 'N/A',
            branch: window.currentBranchName || 'N/A',
            items: payload.items,
            total_amount: payload.total_amount,
            raw_total: payload.raw_total,
            discount_amount: payload.discount_amount,
            vat_amount: payload.vat_amount,
            discount_type: payload.discount_type,
            payment_amount: paymentAmount,
            change_amount: paymentAmount - payload.total_amount,
            payment_method: paymentMethod
        });

        // Update stock display for each purchased medicine
        updateStockDisplay(payload.items);
    });
} else {
    alert(data.error || 'Failed to process transaction');
}
        } catch (error) {
            alert('Network error: ' + error.message);
        } finally {
            checkoutButton.disabled = false;
            checkoutButton.textContent = 'CHECKOUT';
        }
    }

    // Attach submit listener to checkout form to prevent submission and handle via JS
    checkoutForm.addEventListener('submit', e => {
        e.preventDefault();
        const payment = parseFloat(paymentInput.value);
        if (order.length === 0) {
            alert('No items in the order.');
            return;
        }
        if (isNaN(payment) || payment < calculateFinalTotal().totalWithVat) {
            alert('Insufficient payment amount.');
            return;
        }
        submitOrder(payment);
    });

    // Update stock display function
    function updateStockDisplay(purchasedItems) {
        purchasedItems.forEach(item => {
            const medElem = document.querySelector(`#med-list .med[data-id='${item.id}']`);
            if (medElem) {
                const stockElem = medElem.querySelector('.absolute.top-0.right-0');
                if (stockElem) {
                    let currentStockText = stockElem.textContent;
                    let currentStock = parseInt(currentStockText) || 0;
                    let newStock = currentStock - item.qty;
                    if (newStock < 0) newStock = 0;
                    stockElem.textContent = `${newStock} Left`;

                    // Disable selection if stock is 0
                    if (newStock === 0) {
                        medElem.classList.add('opacity-50', 'cursor-not-allowed');
                        medElem.removeAttribute('onclick'); // prevent selecting out of stock
                    }
                }
            }
        });
    }

    // Initialize UI state
    updateInputHighlight();
    updateTotals();

    // Live qty input: when qty changes and qty is active, update the selected medicine row
    /*qtyInput.addEventListener('input', () => {
        if (activeTarget === 'qty' && selectedMedicine) {
            addOrUpdateOrderRow(selectedMedicine);
        }
    });*/

    // Wire discount and VAT input changes to recalc totals
    const discEl = document.getElementById('discount_type');
    if (discEl) discEl.addEventListener('change', updateTotals);
    const vatEl = document.getElementById('vat_percent');
    if (vatEl) vatEl.addEventListener('input', updateTotals);
});

// Discount rates for different customer types
const discountRates = {
    regular: 0,
    senior: 0.20, // 20%
    pwd: 0.20     // 20%
};

// Called whenever quantity, discount type, or VAT changes
// NOTE: main updateTotals implementation lives inside the DOMContentLoaded scope

// Listeners wired inside DOMContentLoaded

