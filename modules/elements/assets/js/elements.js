/**
 * Elements Module - Frontend JavaScript
 * Funcionalidad interactiva para elementos
 */

(function() {
    'use strict';

    /**
     * FAQ Accordion functionality
     */
    function initFAQAccordion() {
        const faqItems = document.querySelectorAll('.faq-accordion .faq-item');

        faqItems.forEach(function(item) {
            const question = item.querySelector('.faq-question');

            if (!question) return;

            question.addEventListener('click', function() {
                // Cerrar otros items abiertos
                faqItems.forEach(function(otherItem) {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                    }
                });

                // Toggle item actual
                item.classList.toggle('active');
            });
        });
    }

    /**
     * Inicializar cuando el DOM esté listo
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initFAQAccordion();
        });
    } else {
        initFAQAccordion();
    }
})();
