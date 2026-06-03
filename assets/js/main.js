document.addEventListener('DOMContentLoaded', () => {
    // Stars
    const starsContainer = document.getElementById('stars');
    if (starsContainer) {
        for (let i = 0; i < 80; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            star.style.left = Math.random() * 100 + '%';
            star.style.top = Math.random() * 100 + '%';
            star.style.setProperty('--duration', (2 + Math.random() * 4) + 's');
            star.style.setProperty('--delay', Math.random() * 3 + 's');
            star.style.width = (1 + Math.random() * 2) + 'px';
            star.style.height = star.style.width;
            starsContainer.appendChild(star);
        }
    }

    // Hero particles
    const particlesContainer = document.getElementById('heroParticles');
    if (particlesContainer) {
        for (let i = 0; i < 30; i++) {
            const p = document.createElement('div');
            p.className = 'hero-particle';
            p.style.left = Math.random() * 100 + '%';
            p.style.top = (40 + Math.random() * 60) + '%';
            p.style.animationDelay = Math.random() * 5 + 's';
            p.style.animationDuration = (3 + Math.random() * 4) + 's';
            particlesContainer.appendChild(p);
        }
    }

    // Mobile nav
    const toggle = document.getElementById('mobileToggle');
    const nav = document.getElementById('mobileNav');
    const close = document.getElementById('mobileClose');

    if (toggle && nav && close) {
        toggle.addEventListener('click', e => { e.stopPropagation(); nav.classList.add('active'); });
        close.addEventListener('click', e => { e.stopPropagation(); nav.classList.remove('active'); });
        nav.querySelectorAll('.mobile-link').forEach(link => {
            link.addEventListener('click', () => nav.classList.remove('active'));
        });
    }

    // Hero carousel
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.carousel-dot');
    const prevBtn = document.querySelector('.carousel-btn.prev');
    const nextBtn = document.querySelector('.carousel-btn.next');
    let current = 0;
    let interval;

    function goToSlide(index) {
        slides[current]?.classList.remove('active');
        dots[current]?.classList.remove('active');
        current = (index + slides.length) % slides.length;
        slides[current]?.classList.add('active');
        dots[current]?.classList.add('active');
    }

    function nextSlide() { goToSlide(current + 1); }
    function prevSlide() { goToSlide(current - 1); }

    if (slides.length > 1) {
        nextBtn?.addEventListener('click', () => { nextSlide(); resetInterval(); });
        prevBtn?.addEventListener('click', () => { prevSlide(); resetInterval(); });
        dots.forEach((dot, i) => dot.addEventListener('click', () => { goToSlide(i); resetInterval(); }));
        interval = setInterval(nextSlide, 6000);
    }

    function resetInterval() { clearInterval(interval); interval = setInterval(nextSlide, 6000); }

    // Games ring carousel
    const gameCards = document.querySelectorAll('.games-ring .game-card');
    const gamesPrevBtn = document.querySelector('.games-nav-prev');
    const gamesNextBtn = document.querySelector('.games-nav-next');
    let ringIndex = 0;
    let ringRange = 2; // ±2 = 5 items (desktop/tablet)

    function getRingRange() {
        const total = gameCards.length;
        let maxRange;
        if (window.innerWidth <= 390) maxRange = 0;      // 1 card
        else if (window.innerWidth <= 600) maxRange = 1;  // 3 cards
        else maxRange = 2;                                 // 5 cards

        // Manter sempre número ímpar de cards visíveis
        if (maxRange > 0 && total <= maxRange * 2 + 1) {
            maxRange = Math.floor((total - 1) / 2);
        }
        return Math.max(0, maxRange);
    }

    function updateRing() {
        const total = gameCards.length;
        if (total === 0) return;

        ringRange = getRingRange();

        gameCards.forEach((card, i) => {
            card.className = card.className.replace(/ring-pos-\S+|ring-hidden/g, '').trim();

            const offset = ((i - ringIndex + total) % total);
            const normalizedOffset = offset <= total / 2 ? offset : offset - total;

            if (normalizedOffset === 0) {
                card.classList.add('ring-pos-0');
            } else if (Math.abs(normalizedOffset) <= ringRange) {
                const cls = 'ring-pos-' + normalizedOffset;
                card.classList.add(cls);
            } else {
                card.classList.add('ring-hidden');
            }
        });
    }

    if (gameCards.length > 0) {
        updateRing();
        window.addEventListener('resize', updateRing);

        gamesPrevBtn?.addEventListener('click', () => {
            ringIndex = (ringIndex - 1 + gameCards.length) % gameCards.length;
            updateRing();
        });

        gamesNextBtn?.addEventListener('click', () => {
            ringIndex = (ringIndex + 1) % gameCards.length;
            updateRing();
        });
    }

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', e => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});
