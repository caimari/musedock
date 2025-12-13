// Sustainable NextJS Theme - JavaScript

document.addEventListener('DOMContentLoaded', function() {

    // Language Selector Dropdown
    const langBtn = document.querySelector('.lang-btn');
    const langDropdown = document.querySelector('.lang-dropdown');

    if (langBtn && langDropdown) {
        langBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = langDropdown.style.display === 'block';
            langDropdown.style.display = isVisible ? 'none' : 'block';
        });

        langDropdown.addEventListener('click', function() {
            langDropdown.style.display = 'none';
        });

        document.addEventListener('click', function(e) {
            if (!langBtn.contains(e.target) && !langDropdown.contains(e.target)) {
                langDropdown.style.display = 'none';
            }
        });
    }

    // Sticky Header
    const header = document.getElementById('main-header');
    if (header && header.classList.contains('enable-sticky')) {
        let lastScroll = 0;
        const headerHeight = header.offsetHeight;

        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

            if (currentScroll > headerHeight) {
                header.classList.add('sticky');
                document.body.style.paddingTop = headerHeight + 'px';
            } else {
                header.classList.remove('sticky');
                document.body.style.paddingTop = '0';
            }

            lastScroll = currentScroll;
        });
    }

    // Smooth Scroll for Anchor Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#!') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Add animation on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.feature-box').forEach(box => {
        observer.observe(box);
    });

});
