(() => {
    'use strict';

    const body = document.body;
    const header = document.querySelector('[data-dl-header]');
    const menuButton = document.querySelector('[data-dl-menu-button]');
    const navigation = document.querySelector('[data-dl-navigation]');
    const stepButtons = Array.from(document.querySelectorAll('[data-dl-step]'));
    const demoTitle = document.querySelector('[data-dl-demo-title]');
    const demoCopy = document.querySelector('[data-dl-demo-copy]');
    const demoLabel = document.querySelector('[data-dl-demo-label]');
    const demoPercent = document.querySelector('[data-dl-demo-percent]');
    const demoBar = document.querySelector('[data-dl-demo-bar]');
    const emailForm = document.querySelector('[data-dl-email-form]');
    const submitButton = document.querySelector('[data-dl-submit]');
    const submitText = document.querySelector('[data-dl-submit-text]');

    const closeMenu = () => {
        if (!menuButton || !navigation) {
            return;
        }

        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'Abrir menú');
        navigation.classList.remove('is-open');
        body.classList.remove('dl-menu-open');
    };

    if (menuButton && navigation) {
        menuButton.addEventListener('click', () => {
            const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
            menuButton.setAttribute('aria-expanded', String(!isOpen));
            menuButton.setAttribute('aria-label', isOpen ? 'Abrir menú' : 'Cerrar menú');
            navigation.classList.toggle('is-open', !isOpen);
            body.classList.toggle('dl-menu-open', !isOpen);
        });

        navigation.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', closeMenu);
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1050) {
                closeMenu();
            }
        });
    }

    const updateHeader = () => {
        header?.classList.toggle('is-scrolled', window.scrollY > 20);
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    stepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const progress = Math.max(0, Math.min(100, Number(button.dataset.progress) || 0));

            stepButtons.forEach((item) => {
                const isCurrent = item === button;
                item.classList.toggle('is-active', isCurrent);
                item.setAttribute('aria-pressed', String(isCurrent));
            });

            if (demoTitle) demoTitle.textContent = button.dataset.title || '';
            if (demoCopy) demoCopy.textContent = button.dataset.copy || '';
            if (demoLabel) demoLabel.textContent = button.dataset.label || '';
            if (demoPercent) demoPercent.textContent = `${progress}%`;
            if (demoBar) demoBar.style.width = `${progress}%`;
        });
    });

    const revealItems = Array.from(document.querySelectorAll('.dl-reveal'));

    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        body.classList.add('dl-ready');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                entry.target.classList.toggle('is-visible', entry.isIntersecting);
            });
        }, {
            threshold: 0.08,
            rootMargin: '-6% 0px -8%',
        });

        revealItems.forEach((item) => observer.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    }

    const spotlight = document.querySelector('[data-dl-spotlight]');

    if (spotlight && window.matchMedia('(pointer: fine)').matches) {
        spotlight.addEventListener('pointermove', (event) => {
            const bounds = spotlight.getBoundingClientRect();
            const x = ((event.clientX - bounds.left) / bounds.width) * 100;
            const y = ((event.clientY - bounds.top) / bounds.height) * 100;
            spotlight.style.setProperty('--dl-pointer-x', `${x}%`);
            spotlight.style.setProperty('--dl-pointer-y', `${y}%`);
        });
    }

    if (emailForm && submitButton && submitText) {
        emailForm.addEventListener('submit', () => {
            submitButton.disabled = true;
            submitText.textContent = 'Guardando tu lugar…';
        });
    }
})();
