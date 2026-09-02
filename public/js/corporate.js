document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.nav-toggle');
    const header = document.querySelector('.header-inner');

    if (!toggle || !header) {
        return;
    }

    toggle.addEventListener('click', () => {
        header.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', header.classList.contains('is-open') ? 'true' : 'false');
    });
});
