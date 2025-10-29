document.addEventListener('DOMContentLoaded', () => {
  // Simple modal handling for Inventory "View Details" buttons
  const modal = document.getElementById('modal');

  function closeModal() {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.innerHTML = '';
  }

  function openModal(contentHtml) {
    if (!modal) return;
    modal.innerHTML = `
      <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 overflow-hidden">
        <div class="p-4 flex justify-between items-start">
          <div class="flex items-start gap-4">
            ${contentHtml}
          </div>
          <button id="modal-close" class="text-gray-500 hover:text-gray-700">&times;</button>
        </div>
      </div>
    `;
    modal.classList.remove('hidden');

    // close button
    const closeBtn = document.getElementById('modal-close');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    // click outside to close
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) closeModal();
    });
  }

  // Delegate clicks for any button with class 'view-details'
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.view-details');
    if (!btn) return;
    const card = btn.closest('.medicine-card');
    if (!card) return;

    const name = card.querySelector('h4') ? card.querySelector('h4').textContent.trim() : (card.dataset.name || 'Medicine');
    const image = card.querySelector('img') ? card.querySelector('img').src : '';
    const price = card.dataset.price || card.querySelector('.text-xl') ? card.querySelector('.text-xl').textContent.trim() : '';
    const stock = card.querySelector('p strong') ? card.querySelector('p strong').textContent.trim() : card.dataset.stock || '';
    const description = card.dataset.description || 'No description available.';

    const contentHtml = `
      <div class="flex gap-6 w-full">
        <div class="w-40 h-40 flex-shrink-0">
          <img src="${image}" alt="${name}" class="w-full h-full object-cover rounded">
        </div>
        <div class="flex-1">
          <h3 class="text-lg font-bold text-gray-900">${name}</h3>
          <p class="text-sm text-gray-600 mt-1">Price: ₱${price}</p>
          <p class="text-sm text-gray-600 mt-1">Stock: <strong class="text-gray-800">${stock}</strong></p>
          <div class="mt-3 text-sm text-gray-700">${description}</div>
        </div>
      </div>
    `;

    openModal(contentHtml);
  });
});
