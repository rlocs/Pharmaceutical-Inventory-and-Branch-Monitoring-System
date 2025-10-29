document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('modal');
  if (!modal) return;

  // Escape HTML to prevent XSS
  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Preserve line breaks: convert newlines to <br>
  function formatDescription(text) {
    const escaped = escapeHtml(text || '');
    return escaped.replace(/\r\n|\r|\n/g, '<br>');
  }

  function onKeyDown(e) {
    if (e.key === 'Escape') closeModal();
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.innerHTML = '';
    document.removeEventListener('keydown', onKeyDown);
  }

  function openModal(contentHtml) {
    modal.innerHTML = `
      <div class="max-w-3xl w-full mx-4">
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
          <div class="flex justify-between items-start p-4 border-b">
            <div class="flex-1 pr-4">
              <!-- content goes here -->
              ${contentHtml}
            </div>
            <div class="flex-shrink-0 pl-2">
              <button id="modal-close" class="text-gray-500 hover:text-gray-800 text-2xl leading-none">&times;</button>
            </div>
          </div>
        </div>
      </div>
    `;

    modal.classList.remove('hidden');

    const closeBtn = document.getElementById('modal-close');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    // close on backdrop click
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) closeModal();
    });

    // ESC to close
    document.addEventListener('keydown', onKeyDown);
  }

  // Delegate clicks for any 'View Details' button
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.view-details');
    if (!btn) return;

    const card = btn.closest('.medicine-card');
    if (!card) return;

    const nameEl = card.querySelector('h4');
    const name = nameEl ? nameEl.textContent.trim() : (card.dataset.name || 'Medicine');
    const imageEl = card.querySelector('img');
    const image = imageEl ? imageEl.src : '';
    // price was stored formatted in data-price
    const price = card.dataset.price || (card.querySelector('.text-xl') ? card.querySelector('.text-xl').textContent.trim() : '');
    const stockStrong = card.querySelector('p strong');
    const stock = stockStrong ? stockStrong.textContent.trim() : (card.dataset.stock || '0');
    const rawDescription = card.dataset.description || '';
    const descriptionHtml = formatDescription(rawDescription);

    const contentHtml = `
      <div class="flex gap-6 w-full">
        <div class="w-36 h-36 flex-shrink-0 bg-gray-100 rounded overflow-hidden">
          <img src="${escapeHtml(image)}" alt="${escapeHtml(name)}" class="w-full h-full object-cover">
        </div>
        <div class="flex-1">
          <h3 class="text-xl font-bold text-gray-900">${escapeHtml(name)}</h3>
          <p class="text-sm text-gray-600 mt-2">Price: <span class="font-semibold text-gray-800">₱${escapeHtml(price)}</span></p>
          <p class="text-sm text-gray-600 mt-1">Stock: <strong class="text-gray-800">${escapeHtml(stock)}</strong></p>
          <div class="mt-3 text-sm text-gray-700 leading-relaxed max-h-48 overflow-auto">${descriptionHtml}</div>
        </div>
      </div>
    `;

    openModal(contentHtml);
  });
});
