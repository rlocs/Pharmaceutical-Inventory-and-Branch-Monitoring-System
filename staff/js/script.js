document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const navLinks = document.querySelectorAll('#navbar .nav-link');
    const mobileLinks = document.querySelectorAll('#mobile-menu .mobile-link');
    const pageTitle = document.getElementById('page-title');

    // Toggle mobile menu
    mobileMenuButton.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });

    // Handle desktop navigation
    navLinks.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const href = link.getAttribute('href');
            if (href) {
                window.location.href = href;
            }
        });
    });

    // Handle mobile navigation
    mobileLinks.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const href = link.getAttribute('href');
            if (href) {
                window.location.href = href;
            }
        });
    });
});

// ------------------------------------------------------------------
// --- ALERT NOTIFICATION API INTEGRATION (SIDEBAR POLLING) ---

const lowStockList = document.getElementById('low-stock-list');
const expiringSoonList = document.getElementById('expiring-soon-list');
const expiredList = document.getElementById('expired-list');

function fetchSidebarAlerts() {
    console.log('fetchSidebarAlerts: elements', lowStockList, expiringSoonList, expiredList);
    if (!lowStockList) return;

    fetch('staff/api/get_alerts.php', { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            console.log('get_alerts response', data);
            if (!data.success) return console.error('API error', data.error);

            const alerts = data.alerts || {};

            const renderList = (ul, items, emptyMsg, renderItem) => {
                ul.innerHTML = '';
                if (items && items.length > 0) {
                    items.forEach(it => {
                        const li = document.createElement('li');
                        li.className = 'text-xs';
                        li.innerHTML = renderItem(it);
                        ul.appendChild(li);
                    });
                } else {
                    ul.innerHTML = `<li class="font-medium text-gray-500">${emptyMsg}</li>`;
                }
            };

            // Expired: use consistent dark font for name and red for the "Expired" tag
            renderList(expiredList, alerts.expired, 'No expired medicines! 🎉', (item) => {
                const nameHtml = `<span class="text-gray-800 font-medium">${item.name}</span>`;
                const expiredHtml = `<span class="text-red-600 font-semibold ml-2">- Expired</span>`;
                return `${nameHtml} ${expiredHtml}`;
            });

            // Expiring soon: show "name - X days" with consistent font/color
            renderList(expiringSoonList, alerts.expiring_soon, 'No medicines expiring soon! 👍', (item) => {
                const nameHtml = `<span class="text-gray-800 font-medium">${item.name}</span>`;
                const daysHtml = `<span class="text-gray-600 ml-2">- ${item.days_remaining} days</span>`;
                return `${nameHtml} ${daysHtml}`;
            });

            // Low stock list: normal items use dark name + muted qty; out-of-stock items use red
            renderList(lowStockList, alerts.low_stock, 'All stocks are healthy! 📈', (item) => {
                const isOut = (item.stock_quantity === 0);
                if (isOut) {
                    // Keep the medicine name dark; only the OUT OF STOCK tag is red
                    const nameHtml = `<span class="text-gray-800 font-medium">${item.name}</span>`;
                    const outHtml = `<span class="text-red-600 font-bold ml-2">(OUT OF STOCK)</span>`;
                    return `${nameHtml} ${outHtml}`;
                }
                const nameHtml = `<span class="text-gray-800 font-medium">${item.name}</span>`;
                const qtyHtml = `<span class="text-gray-600 ml-2">(${item.stock_quantity} left)</span>`;
                return `${nameHtml} ${qtyHtml}`;
            });

        })
        .catch(err => console.error('Error fetching sidebar alerts:', err));
}

fetchSidebarAlerts();
setInterval(fetchSidebarAlerts, 45000);
