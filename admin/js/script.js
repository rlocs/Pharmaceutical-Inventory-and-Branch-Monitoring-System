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
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            pageTitle.textContent = `${link.textContent.trim()} Module`;
        });
    });

    // Handle mobile navigation
    mobileLinks.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            mobileLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            mobileMenu.classList.add('hidden');
            pageTitle.textContent = `${link.textContent.trim()} Module`;
        });
    });
});
