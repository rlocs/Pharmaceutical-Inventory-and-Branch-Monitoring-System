document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('inventory-search');
    const categoryFilter = document.getElementById('category-filter');
    const displayArea = document.getElementById('inventory-display-area');

    function filterMedicines() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value;
        const allSections = displayArea.querySelectorAll('.category-section');

        allSections.forEach(section => {
            let sectionVisibleCount = 0;
            const cards = section.querySelectorAll('.medicine-card');
            const sectionCategory = section.dataset.category;

            cards.forEach(card => {
                const medName = card.dataset.name;
                let isVisible = true;

                // 1. Filter by Search Term
                if (searchTerm && medName.indexOf(searchTerm) === -1) {
                    isVisible = false;
                }

                // 2. Filter by Category
                if (selectedCategory && sectionCategory !== selectedCategory) {
                    isVisible = false;
                }
                
                // Toggle visibility
                if (isVisible) {
                    card.style.display = 'flex';
                    sectionVisibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Hide the entire category section if no cards are visible
            if (sectionVisibleCount === 0 && (selectedCategory === '' || sectionCategory === selectedCategory)) {
                // Only hide if the search term resulted in zero, or if the user is filtering by a category
                section.style.display = 'none';
            } else {
                section.style.display = 'block';
            }
            
            // Special case: If filtering by category, hide all OTHER categories
            if (selectedCategory && sectionCategory !== selectedCategory) {
                 section.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterMedicines);
    categoryFilter.addEventListener('change', filterMedicines);

    // Initial load, ensuring the filter works if a category is pre-selected (though we default to 'All')
    filterMedicines(); 
});