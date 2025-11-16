// Global state for ordered items
const orderItems = {}; // { id: { id, name, price, qty, amount } }

// Numpad state
let activeInputId = 'qty_input'; // Default focus is quantity
let selectedMed = null;

// DOM Elements
const qtyInput = document.getElementById("qty_input");
const paymentInput = document.getElementById("payment");
const confirmQtyButton = document.getElementById("confirm_qty");
const totalEl = document.getElementById("total");
const changeEl = document.getElementById("change");
const numpadEl = document.getElementById("numpad");
const orderTableBody = document.querySelector("#order-table tbody");
const checkoutForm = document.getElementById("checkoutForm");
const searchInput = document.getElementById("search");
const numpadTargetDisplay = document.getElementById("numpad-target-display");

// --- UTILITY FUNCTIONS ---

function updateChange() {
    const total = parseFloat(totalEl.textContent.replace('$', '')) || 0;
    const payment = parseFloat(paymentInput.value) || 0;
    const change = payment - total;

    changeEl.textContent = `$${change >= 0 ? change.toFixed(2) : "0.00"}`;
}

function calculateTotal() {
    let total = 0;
    for (const id in orderItems) {
        total += orderItems[id].amount;
    }
    totalEl.textContent = `$${total.toFixed(2)}`;
    updateChange();
    return total;
}

function renderOrderTable() {
    orderTableBody.innerHTML = '';
    for (const id in orderItems) {
        const item = orderItems[id];
        const row = document.createElement("tr");

        row.innerHTML = `
            <td>${item.qty}</td>
            <td>${item.name}</td>
            <td>$${item.amount.toFixed(2)}</td>
        `;
        orderTableBody.appendChild(row);
    }
    calculateTotal();
}

function clearQuantityInput() {
    qtyInput.value = '';
    selectedMed = null;
    document.querySelectorAll('.med').forEach(x=>x.classList.remove('selected'));
    // Optionally, switch focus back to payment after confirming QTY
    setActiveInput('payment');
}


// --- NUMPAD & FOCUS LOGIC ---

function setActiveInput(id) {
    activeInputId = id;
    numpadTargetDisplay.textContent = id === 'qty_input' ? 'Quantity' : 'Payment';
    
    // Visually focus the input
    document.getElementById('qty_input').classList.remove('active-target');
    document.getElementById('payment').classList.remove('active-target');
    
    const targetEl = document.getElementById(id);
    if(targetEl) {
        targetEl.classList.add('active-target');
        // If it's payment, run change calculation
        if (id === 'payment') updateChange();
    }
}


// --- EVENT LISTENERS ---

document.addEventListener('DOMContentLoaded', () => {
    
    // Set initial active input
    setActiveInput('qty_input');

    // 1. Medicine Click Handler (Selects item and focuses QTY input)
    document.querySelectorAll('.med').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('.med').forEach(x=>x.classList.remove('selected'));
            el.classList.add('selected');
            selectedMed = {
                id: el.dataset.id,
                name: el.dataset.name,
                price: parseFloat(el.dataset.price)
            };
            // Automatically focus the numpad on quantity when an item is selected
            setActiveInput('qty_input');
            qtyInput.value = ''; // Clear previous quantity
            qtyInput.focus();
        });
    });

    // 2. Input Focus Handlers (Allow manual mode switching)
    qtyInput.addEventListener('click', () => setActiveInput('qty_input'));
    paymentInput.addEventListener('click', () => setActiveInput('payment'));

    // 3. Payment Input Change Listener (Manual typing also updates change)
    paymentInput.addEventListener("input", updateChange);

    // 4. Quantity Confirmation
    confirmQtyButton.addEventListener('click', () => {
        const qty = parseInt(qtyInput.value, 10);

        if (!selectedMed) {
            alert('Please select a medicine first.');
            return;
        }
        if (isNaN(qty) || qty <= 0) {
            alert('Please enter a valid quantity.');
            return;
        }

        const id = selectedMed.id;
        const price = selectedMed.price;

        if (orderItems[id]) {
            orderItems[id].qty += qty;
            orderItems[id].amount = orderItems[id].qty * price;
        } else {
            orderItems[id] = {
                id: id,
                name: selectedMed.name,
                price: price,
                qty: qty,
                amount: qty * price
            };
        }
        
        renderOrderTable();
        clearQuantityInput(); // Clears QTY input, deselects item, and focuses payment.
    });

    // 5. Numpad Logic (Dual-purpose)
    numpadEl.addEventListener("click", (e) => {
        const button = e.target.closest("button");
        if (!button) return;

        const num = button.getAttribute("data-num");
        const targetEl = document.getElementById(activeInputId);

        if (!targetEl) return;

        let currentValue = targetEl.value;

        if (num === '✓') {
            // Confirm quantity if active input is qty_input
            if (activeInputId === 'qty_input') {
                confirmQtyButton.click();
            }
            return;
        } else if (num === 'X') {
            // Backspace
            currentValue = currentValue.slice(0, -1);
        } else if (num !== null) {
            // Append number or decimal point
            currentValue += num;
        }

        // Handle input differences
        if (activeInputId === 'payment') {
            // For payment, allow decimals
            const floatVal = parseFloat(currentValue);
            targetEl.value = isNaN(floatVal) ? '' : floatVal.toString();
            updateChange();
        } else if (activeInputId === 'qty_input') {
            // Quantity must be an integer > 0
            const intVal = parseInt(currentValue, 10);
            targetEl.value = isNaN(intVal) || intVal <= 0 ? '' : intVal.toString();
        }
    });

    // 6. Search Input Listener
    searchInput.addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('.med').forEach(el => {
            const name = el.dataset.name.toLowerCase();
            el.style.display = name.includes(q) ? 'flex' : 'none';
        });
    });

    // 7. Checkout Handler
    checkoutForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const total = calculateTotal();
        const payment = parseFloat(paymentInput.value) || 0;
        const change = payment - total;

        if (total <= 0) {
            alert("Please add items to the order.");
            return;
        }

        if (change < 0) {
            alert(`Payment is insufficient. Missing $${Math.abs(change).toFixed(2)}.`);
            // Set focus back to payment input
            setActiveInput('payment'); 
            return;
        }
        
        // Prepare data for POST
        const orderArray = Object.values(orderItems).map(item => ({
            qty: item.qty,
            name: item.name,
            amount: item.amount.toFixed(2)
        }));

        document.getElementById("order_data").value = JSON.stringify(orderArray);
        document.getElementById("total_amount").value = total.toFixed(2);
        document.getElementById("payment_amount").value = payment.toFixed(2);
        document.getElementById("change_amount").value = change.toFixed(2);

        // Submit the form
        checkoutForm.submit();
    });

});