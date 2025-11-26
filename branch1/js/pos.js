// pos.js: Frontend logic for Mercury POS

document.addEventListener('DOMContentLoaded', () => {
    // State variables
    let order = [];
    let activeTarget = 'qty'; // 'qty' or 'payment'
    let selectedMedicine = null;

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
    const confirmQtyBtn = document.getElementById('confirm_qty');
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

        // Set qty input to 1 by default when new medicine selected
        qtyInput.value = 1;
        activeTarget = 'qty';
        updateInputHighlight();

        // Highlight selected medicine visually (optional)
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
        button.stopPropagation;
        const medElem = button.closest('.med');
        if (!medElem) return;
        const id = medElem.getAttribute('data-id');
        if (selectedMedicine && selectedMedicine.id === id) {
            let currentQty = parseInt(qtyInput.value) || 0;
            qtyInput.value = currentQty + 1;
        } else {
            selectMedicine(medElem);
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
            addToOrder(selectedMedicine, qty);
            qtyInput.value = '';
            selectedMedicine = null;
            highlightSelectedMedicine(null);
        } else {
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
            const total = calculateTotal();
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

    // Add medicine and quantity to order
    function addToOrder(medicine, qty) {
        // Check if item already exists in order
        const existingItemIndex = order.findIndex(item => item.id === medicine.id);
        if (existingItemIndex >= 0) {
            // Update qty
            order[existingItemIndex].qty += qty;
        } else {
            order.push({
                id: medicine.id,
                name: medicine.name,
                price: medicine.price,
                qty: qty
            });
        }
        renderOrder();
        updateTotalsUI();
    }

    // Render the current order in the table
    function renderOrder() {
        orderBody.innerHTML = '';
        order.forEach((item, index) => {
            const subtotal = item.price * item.qty;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="py-2 px-4 text-center">${item.qty}</td>
                <td class="py-2 px-4">${item.name}</td>
                <td class="py-2 px-4 text-right">₱${subtotal.toFixed(2)}</td>
                <td class="py-2 px-2 text-center">
                    <button class="text-red-500 hover:text-red-700" aria-label="Remove item" data-index="${index}">&times;</button>
                </td>
            `;
            // Remove button event
            tr.querySelector('button').addEventListener('click', e => {
                const idx = parseInt(e.target.getAttribute('data-index'));
                removeOrderItem(idx);
            });
            orderBody.appendChild(tr);
        });
    }

    // Remove item from order
    function removeOrderItem(index) {
        if (index >= 0 && index < order.length) {
            order.splice(index, 1);
            renderOrder();
            updateTotalsUI();
            updateChange();
        }
    }

    // Clear the order entirely
    clearOrderBtn.addEventListener('click', () => {
        order = [];
        renderOrder();
        updateTotalsUI();
        qtyInput.value = '';
        paymentInput.value = '';
        changeEl.textContent = '₱0.00';
        selectedMedicine = null;
        highlightSelectedMedicine(null);
        activeTarget = 'qty';
        updateInputHighlight();
    });

    // Update totals displayed in UI
    function updateTotalsUI() {
        const total = calculateTotal();
        totalEl.textContent = `₱${total.toFixed(2)}`;
    }

    // Calculate total of the order
    function calculateTotal() {
        return order.reduce((acc, item) => acc + item.price * item.qty, 0);
    }

    // Update change display based on payment input
    function updateChange() {
        const payment = parseFloat(paymentInput.value) || 0;
        const total = calculateTotal();
        const change = payment - total;
        if (change < 0) {
            changeEl.textContent = '₱0.00';
        } else {
            changeEl.textContent = `₱${change.toFixed(2)}`;
        }
    }

    // Confirm qty button click (alternative to numpad enter)
    confirmQtyBtn.addEventListener('click', () => {
        if (!selectedMedicine) {
            alert('Please select a medicine first.');
            return;
        }
        const qty = parseInt(qtyInput.value);
        if (isNaN(qty) || qty <= 0) {
            alert('Please enter a valid quantity.');
            return;
        }
        addToOrder(selectedMedicine, qty);
        qtyInput.value = '';
        selectedMedicine = null;
        highlightSelectedMedicine(null);
    });

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

        const total = calculateTotal();
        const paymentTypeSelect = document.getElementById('payment_type');
        let paymentMethod = 'Cash';
        if (paymentTypeSelect && paymentTypeSelect.value) {
            paymentMethod = paymentTypeSelect.value;
        }

        const payload = {
            items: order.map(item => ({
                id: parseInt(item.id),
                name: item.name,
                qty: item.qty,
                price: item.price
            })),
            total_amount: total,
            payment_method: paymentMethod, // Use selected payment method or default to Cash
            customer_name: null // Could extend UI for customer name if needed
        };

        try {
            const response = await fetch('api/pos_api.php', {
                method: 'POST',
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
        updateTotalsUI();
        qtyInput.value = '';
        paymentInput.value = '';
        const paymentTypeSelect = document.getElementById('payment_type');
        if (paymentTypeSelect) {
            paymentTypeSelect.value = '';
        }
        changeEl.textContent = '₱0.00';

        // Set values in hidden inputs
        orderDataInput.value = JSON.stringify(payload.items);
        totalAmountInput.value = total.toFixed(2);
        paymentAmountInput.value = paymentAmount.toFixed(2);
        changeAmountInput.value = (paymentAmount - total).toFixed(2);

        // Show the invoice modal instead of submitting form
        window.showInvoiceModal({
            transaction_id: data.transaction_id,
            cashier: window.currentUserFull || 'N/A',
            branch: window.currentBranchName || 'N/A',
            items: payload.items,
            total_amount: total,
            payment_amount: paymentAmount,
            change_amount: paymentAmount - total,
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
        if (isNaN(payment) || payment < calculateTotal()) {
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
    updateTotalsUI();
});
