// ============================================================
// SwiftHaul Logistics — Global JS
// Navbar scroll state, mobile nav, Intersection Observer reveals,
// animated stat counters, and small modal helpers.
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    // ---------- Sticky / glassmorphism navbar + hide on scroll-down, reveal on scroll-up ----------
    const navbar = document.getElementById('navbar');
    let lastScrollY = window.scrollY;

    const onScroll = () => {
        if (!navbar) return;
        const currentY = window.scrollY;

        if (currentY > 40) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');

        // Don't hide the navbar while the mobile menu is open, or near the very top.
        const menuOpen = document.getElementById('navLinks')?.classList.contains('open');
        if (!menuOpen) {
            if (currentY > lastScrollY && currentY > 160) {
                navbar.classList.add('nav-hidden');   // scrolling down -> hide
            } else if (currentY < lastScrollY) {
                navbar.classList.remove('nav-hidden'); // scrolling up -> reveal
            }
        }
        lastScrollY = currentY;
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    // ---------- Mobile nav toggle ----------
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
            navbar?.classList.remove('nav-hidden');
            const icon = navToggle.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-xmark');
        });
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => navLinks.classList.remove('open'));
        });
    }

    // ---------- Nav "More" dropdown: tap-to-expand on mobile ----------
    // (Desktop uses pure CSS :hover/:focus-within — no JS needed there.)
    const navDropdown = document.querySelector('.nav-dropdown');
    const navDropdownTrigger = document.querySelector('.nav-dropdown-trigger');
    if (navDropdown && navDropdownTrigger) {
        navDropdownTrigger.addEventListener('click', (e) => {
            if (window.innerWidth <= 900) {
                e.preventDefault();
                const isOpen = navDropdown.classList.toggle('mobile-open');
                navDropdownTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        });
    }

    // ---------- Scroll-triggered reveal animations ----------
    const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .stagger');
    if ('IntersectionObserver' in window && revealEls.length) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                } else {
                    // allow re-animation when scrolling back up past the section
                    entry.target.classList.remove('visible');
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });
        revealEls.forEach(el => io.observe(el));
    } else {
        revealEls.forEach(el => el.classList.add('visible'));
    }

    // ---------- Animated stat counters ----------
    const counters = document.querySelectorAll('[data-counter]');
    if ('IntersectionObserver' in window && counters.length) {
        const counterIO = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.dataset.counted) {
                    entry.target.dataset.counted = 'true';
                    animateCounter(entry.target);
                }
            });
        }, { threshold: 0.5 });
        counters.forEach(el => counterIO.observe(el));
    }

    function animateCounter(el) {
        const target = parseInt(el.getAttribute('data-counter'), 10) || 0;
        const suffix = el.getAttribute('data-suffix') || '';
        const duration = 1400;
        const start = performance.now();
        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(eased * target).toLocaleString() + suffix;
            if (progress < 1) requestAnimationFrame(tick);
            else el.textContent = target.toLocaleString() + suffix;
        }
        requestAnimationFrame(tick);
    }

    // ---------- Generic modal open/close (data-modal-target / data-modal-close) ----------
    document.querySelectorAll('[data-modal-target]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.querySelector(btn.getAttribute('data-modal-target'));
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay')?.classList.remove('open');
        });
    });
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // ---------- Hero background slider (auto-play + dot navigation) ----------
    const heroSlides = document.querySelectorAll('#heroSlider .hero-slide');
    const heroDots = document.querySelectorAll('#heroDots button');
    if (heroSlides.length) {
        let currentSlide = 0;
        let sliderTimer;

        const goToSlide = (index) => {
            heroSlides[currentSlide]?.classList.remove('active');
            heroDots[currentSlide]?.classList.remove('active');
            currentSlide = index % heroSlides.length;
            heroSlides[currentSlide].classList.add('active');
            heroDots[currentSlide]?.classList.add('active');
        };

        const startAutoplay = () => {
            clearInterval(sliderTimer);
            sliderTimer = setInterval(() => goToSlide(currentSlide + 1), 6000);
        };

        heroDots.forEach((dot, i) => {
            dot.addEventListener('click', () => { goToSlide(i); startAutoplay(); });
        });

        startAutoplay();
    }

    // ---------- Flash message auto-dismiss ----------
    document.querySelectorAll('.alert[data-autohide]').forEach(alert => {
        setTimeout(() => { alert.style.transition = 'opacity 400ms ease'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 400); }, 4500);
    });
});
