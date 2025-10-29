document.addEventListener('DOMContentLoaded', () => {
  const addBtn = document.getElementById('add-medicine-btn');
  const modal = document.getElementById('modal');
  if (!addBtn || !modal) return;

  function closeModal() {
    modal.classList.add('hidden');
    modal.innerHTML = '';
    document.removeEventListener('keydown', onKeyDown);
  }

  function onKeyDown(e) {
    if (e.key === 'Escape') closeModal();
  }

  function openModal(html) {
    modal.innerHTML = `
      <div class="max-w-3xl w-full mx-4">
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
          <div class="p-4">
            ${html}
          </div>
        </div>
      </div>
    `;
    modal.classList.remove('hidden');
    document.addEventListener('keydown', onKeyDown);

    // close on backdrop
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) closeModal();
    });
  }

  addBtn.addEventListener('click', () => {
    const formHtml = `
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold">Add Medicine</h3>
        <button id="close-add-modal" class="text-gray-500">&times;</button>
      </div>
      <form id="add-medicine-form" class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <input name="name" placeholder="Name" required class="p-2 border rounded" />
          <input name="category" placeholder="Category" class="p-2 border rounded" />
          <input id="price-input" name="price" placeholder="Price" type="number" step="0.01" class="p-2 border rounded" />
          <input id="stock-input" name="stock_quantity" placeholder="Stock Quantity" type="number" class="p-2 border rounded" />
          <input id="minstock-input" name="min_stock_threshold" placeholder="Min Stock Threshold" type="number" class="p-2 border rounded" />
          <input name="expiry_date" placeholder="Expiry Date (YYYY-MM-DD)" type="date" class="p-2 border rounded" />
          <input id="image-url-input" name="image_url" placeholder="Image URL (optional)" class="p-2 border rounded col-span-2" />
          <input id="image-file-input" name="image_file" type="file" accept="image/*" class="p-2 border rounded col-span-2" />
          <div id="image-preview" class="col-span-2"></div>
        </div>
        <textarea name="description" placeholder="Description" class="w-full p-2 border rounded" rows="4"></textarea>
        <div class="flex justify-end gap-2">
          <button type="button" id="cancel-add" class="px-4 py-2 rounded bg-gray-200">Cancel</button>
          <button type="submit" id="submit-add" class="px-4 py-2 rounded bg-green-600 text-white">Add Medicine</button>
        </div>
      </form>
    `;

    openModal(formHtml);

    // wire up close and form handling
    document.getElementById('close-add-modal').addEventListener('click', closeModal);
    document.getElementById('cancel-add').addEventListener('click', closeModal);

    const form = document.getElementById('add-medicine-form');

    // image preview
    const imageFileInput = document.getElementById('image-file-input');
    const imagePreview = document.getElementById('image-preview');
    if (imageFileInput) {
      imageFileInput.addEventListener('change', () => {
        const file = imageFileInput.files[0];
        imagePreview.innerHTML = '';
        if (!file) return;
        const img = document.createElement('img');
        img.className = 'w-32 h-32 object-cover rounded mt-2';
        const reader = new FileReader();
        reader.onload = (e) => img.src = e.target.result;
        reader.readAsDataURL(file);
        imagePreview.appendChild(img);
      });
    }

    // client-side validation helpers
    const priceInput = document.getElementById('price-input');
    const stockInput = document.getElementById('stock-input');
    const minstockInput = document.getElementById('minstock-input');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      // basic validation
      const name = form.querySelector('input[name="name"]').value.trim();
      if (!name) return alert('Name is required');
      if (priceInput && priceInput.value && Number(priceInput.value) < 0) return alert('Price must be >= 0');
      if (stockInput && stockInput.value && Number(stockInput.value) < 0) return alert('Stock must be >= 0');

      const fd = new FormData(form);

      try {
        const resp = await fetch('api/add_medicine.php', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin' // ensure cookies (PHP session) are sent
        });
        const data = await resp.json();
        if (!data.success) {
          // show error inside modal
          const errBox = document.createElement('div');
          errBox.className = 'bg-red-100 text-red-700 p-2 rounded mb-2';
          errBox.textContent = data.error || 'Unknown error';
          form.prepend(errBox);
          return;
        }

  // show success message inside modal and offer to add another or close
        const successHtml = `
          <div class="p-4 text-center">
            <h3 class="text-lg font-bold text-green-700">Medicine added successfully</h3>
            <p class="text-sm text-gray-600 mt-2">${data.medicine.name}</p>
            <div class="flex justify-center gap-2 mt-4">
              <button id="close-after-add" class="px-4 py-2 rounded bg-gray-200">Close</button>
              <button id="add-another" class="px-4 py-2 rounded bg-green-600 text-white">Add another</button>
            </div>
          </div>
        `;
        openModal(successHtml);

        // If server returned alert flags, trigger immediate sidebar refresh
        try {
          if (data.alerts && (data.alerts.low_stock || data.alerts.expiring_soon || data.alerts.expired)) {
            if (typeof fetchSidebarAlerts === 'function') fetchSidebarAlerts();
          }
        } catch (err) {
          console.warn('Error checking alerts flags', err);
        }

        // Insert the new medicine card into the DOM (try to find matching category section)
        try {
          const med = data.medicine;
          // build card element
          const card = document.createElement('div');
          card.setAttribute('data-name', med.name ? med.name.toLowerCase() : '');
          card.setAttribute('data-category', med.category || '');
          card.setAttribute('data-description', med.description || '');
          card.setAttribute('data-price', med.price || '0.00');

          // compute badges and expiry visuals similar to server-side logic
          const baseClasses = 'medicine-card flex-shrink-0 w-60 h-72 bg-white shadow-lg rounded-lg overflow-hidden border-2 relative';
          let statusClass = 'border-gray-300';

          const stockNum = (med.stock_quantity !== undefined && med.stock_quantity !== null && med.stock_quantity !== '') ? Number(med.stock_quantity) : 0;
          const minThreshold = (med.min_stock_threshold !== undefined && med.min_stock_threshold !== null && med.min_stock_threshold !== '') ? Number(med.min_stock_threshold) : null;

          let topRightBadge = '';
          if (stockNum === 0) {
            topRightBadge = '<span class="absolute top-0 right-0 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-bl-lg">OUT OF STOCK</span>';
            statusClass = 'border-red-500 ring-2 ring-red-300';
          } else if (minThreshold !== null && stockNum <= minThreshold) {
            topRightBadge = '<span class="absolute top-0 right-0 bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-bl-lg">LOW STOCK</span>';
            statusClass = 'border-yellow-500 ring-2 ring-yellow-300';
          }

          // expiry badge bottom-left
          let bottomLeftBadge = '';
          if (med.expiry_date) {
            try {
              const today = new Date();
              today.setHours(0,0,0,0);
              const expiry = new Date(med.expiry_date);
              expiry.setHours(0,0,0,0);
              if (expiry < today) {
                bottomLeftBadge = '<span class="absolute bottom-0 left-0 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-tr-lg">EXPIRED</span>';
                statusClass = 'border-red-600 ring-2 ring-red-300';
              } else {
                const msPerDay = 1000 * 60 * 60 * 24;
                const daysRemaining = Math.floor((expiry - today) / msPerDay);
                const thresholdDays = 90;
                if (daysRemaining <= thresholdDays) {
                  const badgeText = (daysRemaining <= 30) ? 'EXPIRING SOON' : 'EXPIRING';
                  bottomLeftBadge = `<span class="absolute bottom-0 left-0 bg-orange-600 text-white text-xs font-bold px-2 py-1 rounded-tr-lg">${badgeText}</span>`;
                }
              }
            } catch (err) {
              console.warn('Failed to parse expiry date for dynamic card', err);
            }
          }

          card.className = baseClasses + ' ' + statusClass;

          card.innerHTML = `
            ${topRightBadge}
            ${bottomLeftBadge}
            <img src="${med.image_url ? med.image_url : 'https://placehold.co/240x96?text=No+Image'}" alt="${med.name}" class="w-full h-24 object-cover bg-gray-200">
            <div class="p-4 flex flex-col justify-between h-48">
              <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-1">${med.name}</h4>
                <p class="text-sm text-gray-500">Category: ${med.category}</p>
              </div>
              <div class="mt-2">
                <p class="text-xl font-bold text-green-600">₱${med.price}</p>
                <p class="text-sm text-gray-700">Stock: <strong>${stockNum}</strong></p>
                <button data-id="${med.medicine_id}" class="view-details mt-2 w-full bg-blue-500 text-white text-sm py-1 rounded hover:bg-blue-600">View Details</button>
              </div>
            </div>
          `;

          // find category section
          const sections = document.querySelectorAll('.category-section');
          let targetList = null;
          sections.forEach(sec => {
            if (sec.dataset.category === med.category) {
              const container = sec.querySelector('.flex.overflow-x-auto');
              if (container) targetList = container;
            }
          });
          if (!targetList) {
            // create new category section at top
            const displayArea = document.getElementById('inventory-display-area');
            const section = document.createElement('section');
            section.className = 'mb-8 category-section';
            section.dataset.category = med.category;
            section.innerHTML = `<h3 class="text-2xl font-semibold text-primary-accent mb-4 border-b pb-2">${med.category}</h3><div class="flex overflow-x-auto space-x-4 pb-4"></div>`;
            displayArea.prepend(section);
            targetList = section.querySelector('.flex.overflow-x-auto');
          }
          // append card
          targetList.prepend(card);
          // Update sidebar alerts immediately if the global fetchSidebarAlerts function is available
          try {
            if (typeof fetchSidebarAlerts === 'function') {
              fetchSidebarAlerts();
            }
          } catch (err) {
            console.warn('fetchSidebarAlerts not available or failed', err);
          }
        } catch (err) {
          console.warn('Failed to insert card dynamically', err);
        }

        // wire up success modal buttons
        document.getElementById('close-after-add').addEventListener('click', () => { closeModal(); });
        document.getElementById('add-another').addEventListener('click', () => { closeModal(); addBtn.click(); });

      } catch (err) {
        console.error(err);
        alert('Failed to add medicine. See console for details.');
      }
    });
  });
});
