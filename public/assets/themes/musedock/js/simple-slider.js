/**
 * Simple Slider - Lightweight alternative to Revolution Slider
 * No dependencies required
 */
class SimpleSlider {
    constructor(container, options = {}) {
        this.container = document.querySelector(container);
        if (!this.container) return;

        this.slides = this.container.querySelectorAll('.simple-slide');
        this.currentSlide = 0;
        this.isTransitioning = false;

        // Options
        this.options = {
            autoplay: options.autoplay !== undefined ? options.autoplay : true,
            interval: options.interval || 5000,
            transition: options.transition || 600,
            ...options
        };

        this.init();
    }

    init() {
        if (this.slides.length === 0) return;

        // Show first slide
        this.slides[0].classList.add('active');

        // Create navigation arrows
        this.createArrows();

        // Start autoplay
        if (this.options.autoplay) {
            this.startAutoplay();
        }

        // Pause on hover
        this.container.addEventListener('mouseenter', () => this.stopAutoplay());
        this.container.addEventListener('mouseleave', () => this.startAutoplay());
    }

    createArrows() {
        const prevArrow = document.createElement('button');
        prevArrow.className = 'simple-slider-arrow simple-slider-prev';
        prevArrow.innerHTML = '<i class="fa fa-angle-left"></i>';
        prevArrow.addEventListener('click', () => this.prev());

        const nextArrow = document.createElement('button');
        nextArrow.className = 'simple-slider-arrow simple-slider-next';
        nextArrow.innerHTML = '<i class="fa fa-angle-right"></i>';
        nextArrow.addEventListener('click', () => this.next());

        this.container.appendChild(prevArrow);
        this.container.appendChild(nextArrow);
    }

    goToSlide(index) {
        if (this.isTransitioning) return;
        if (index === this.currentSlide) return;

        this.isTransitioning = true;

        // Remove active class from current slide
        this.slides[this.currentSlide].classList.remove('active');

        // Add active class to new slide
        this.slides[index].classList.add('active');

        this.currentSlide = index;

        setTimeout(() => {
            this.isTransitioning = false;
        }, this.options.transition);
    }

    next() {
        const nextIndex = (this.currentSlide + 1) % this.slides.length;
        this.goToSlide(nextIndex);
    }

    prev() {
        const prevIndex = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
        this.goToSlide(prevIndex);
    }

    startAutoplay() {
        if (!this.options.autoplay) return;
        this.stopAutoplay();
        this.autoplayInterval = setInterval(() => this.next(), this.options.interval);
    }

    stopAutoplay() {
        if (this.autoplayInterval) {
            clearInterval(this.autoplayInterval);
            this.autoplayInterval = null;
        }
    }
}

// Initialize slider when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    new SimpleSlider('#simple-slider', {
        autoplay: true,
        interval: 5000,
        transition: 600
    });

    // FAQ Accordion Fix
    // 1. Prevent default anchor jump
    // 2. Allow multiple items to be open (already handled by removing data-parent in HTML)
    const faqButtons = document.querySelectorAll('.ziph-faq_btn');

    faqButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            // Prevent default anchor jump behavior
            e.preventDefault();

            // Get target ID from href
            const targetId = this.getAttribute('href');
            if (targetId && targetId.startsWith('#')) {
                const target = document.querySelector(targetId);
                if (target) {
                    // Check if Bootstrap is handling it
                    // If the element has 'collapse' class, Bootstrap should handle it.
                    // But if it's not working, we can toggle it manually.
                    // We'll toggle the 'show' class which Bootstrap 4 uses, or 'in' for Bootstrap 3.

                    // Simple manual toggle as fallback/enhancement
                    if (typeof jQuery === 'undefined' || !jQuery(this).data('bs.collapse')) {
                        // Toggle visibility classes
                        if (target.classList.contains('show') || target.classList.contains('in')) {
                            target.classList.remove('show', 'in');
                            this.setAttribute('aria-expanded', 'false');
                            // Also update the parent panel state if needed for styling
                            const panel = this.closest('.panel');
                            if (panel) panel.setAttribute('data-show', 'false');
                        } else {
                            target.classList.add('show', 'in');
                            this.setAttribute('aria-expanded', 'true');
                            const panel = this.closest('.panel');
                            if (panel) panel.setAttribute('data-show', 'true');
                        }
                    }
                }
            }
        });
    });
});
