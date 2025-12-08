/**
 * Image Gallery - Lightbox Script
 *
 * Visor de imágenes en pantalla completa para galerías
 */

(function() {
    'use strict';

    // Evitar carga múltiple
    if (window.MusedockLightbox) return;

    class MusedockLightbox {
        constructor() {
            this.currentIndex = 0;
            this.images = [];
            this.galleryId = null;
            this.isOpen = false;
            this.isZoomed = false;

            this.init();
        }

        init() {
            this.createLightbox();
            this.bindEvents();
        }

        createLightbox() {
            const html = `
                <div class="gallery-lightbox" id="musedockLightbox">
                    <button class="lightbox-close" aria-label="Cerrar">&times;</button>
                    <button class="lightbox-nav lightbox-prev" aria-label="Anterior">&#10094;</button>
                    <button class="lightbox-nav lightbox-next" aria-label="Siguiente">&#10095;</button>
                    <div class="lightbox-counter"><span class="current">1</span> / <span class="total">1</span></div>
                    <div class="lightbox-image-container">
                        <div class="lightbox-loading">
                            <div class="lightbox-spinner"></div>
                        </div>
                        <img class="lightbox-image" src="" alt="">
                    </div>
                    <div class="lightbox-caption">
                        <div class="lightbox-title"></div>
                        <p class="lightbox-description"></p>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', html);

            // Referencias a elementos
            this.lightbox = document.getElementById('musedockLightbox');
            this.image = this.lightbox.querySelector('.lightbox-image');
            this.loading = this.lightbox.querySelector('.lightbox-loading');
            this.title = this.lightbox.querySelector('.lightbox-title');
            this.description = this.lightbox.querySelector('.lightbox-description');
            this.counter = this.lightbox.querySelector('.lightbox-counter');
            this.prevBtn = this.lightbox.querySelector('.lightbox-prev');
            this.nextBtn = this.lightbox.querySelector('.lightbox-next');
            this.closeBtn = this.lightbox.querySelector('.lightbox-close');
        }

        bindEvents() {
            // Click en imágenes de galería
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[data-lightbox]');
                if (link) {
                    e.preventDefault();
                    this.open(link);
                }
            });

            // Cerrar
            this.closeBtn.addEventListener('click', () => this.close());
            this.lightbox.addEventListener('click', (e) => {
                if (e.target === this.lightbox) this.close();
            });

            // Navegación
            this.prevBtn.addEventListener('click', () => this.prev());
            this.nextBtn.addEventListener('click', () => this.next());

            // Teclado
            document.addEventListener('keydown', (e) => {
                if (!this.isOpen) return;

                switch (e.key) {
                    case 'Escape':
                        this.close();
                        break;
                    case 'ArrowLeft':
                        this.prev();
                        break;
                    case 'ArrowRight':
                        this.next();
                        break;
                }
            });

            // Zoom
            this.image.addEventListener('click', () => this.toggleZoom());

            // Swipe en móvil
            let touchStartX = 0;
            let touchEndX = 0;

            this.lightbox.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            this.lightbox.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                this.handleSwipe(touchStartX, touchEndX);
            }, { passive: true });
        }

        open(link) {
            const galleryId = link.dataset.lightbox;

            // Recopilar todas las imágenes de la galería
            this.images = Array.from(document.querySelectorAll(`a[data-lightbox="${galleryId}"]`));
            this.galleryId = galleryId;
            this.currentIndex = this.images.indexOf(link);

            this.isOpen = true;
            this.lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';

            this.updateCounter();
            this.showImage();
        }

        close() {
            this.isOpen = false;
            this.isZoomed = false;
            this.lightbox.classList.remove('active');
            this.image.classList.remove('zoomed');
            document.body.style.overflow = '';
        }

        prev() {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.showImage();
                this.updateCounter();
            }
        }

        next() {
            if (this.currentIndex < this.images.length - 1) {
                this.currentIndex++;
                this.showImage();
                this.updateCounter();
            }
        }

        showImage() {
            const link = this.images[this.currentIndex];
            const src = link.href;
            const titleText = link.dataset.title || '';
            const descText = link.dataset.description || '';

            // Mostrar loading
            this.loading.style.display = 'flex';
            this.image.style.opacity = '0';

            // Cargar imagen
            const img = new Image();
            img.onload = () => {
                this.image.src = src;
                this.image.style.opacity = '1';
                this.loading.style.display = 'none';
            };
            img.onerror = () => {
                this.loading.style.display = 'none';
                this.image.src = '';
                this.image.alt = 'Error al cargar la imagen';
            };
            img.src = src;

            // Actualizar caption
            this.title.textContent = titleText;
            this.description.textContent = descText;

            // Mostrar/ocultar caption
            const caption = this.lightbox.querySelector('.lightbox-caption');
            if (titleText || descText) {
                caption.style.display = 'block';
            } else {
                caption.style.display = 'none';
            }

            // Actualizar estado de botones
            this.prevBtn.disabled = this.currentIndex === 0;
            this.nextBtn.disabled = this.currentIndex === this.images.length - 1;

            // Reset zoom
            if (this.isZoomed) {
                this.isZoomed = false;
                this.image.classList.remove('zoomed');
            }
        }

        updateCounter() {
            this.counter.querySelector('.current').textContent = this.currentIndex + 1;
            this.counter.querySelector('.total').textContent = this.images.length;

            // Ocultar counter si solo hay una imagen
            this.counter.style.display = this.images.length > 1 ? 'block' : 'none';
            this.prevBtn.style.display = this.images.length > 1 ? 'flex' : 'none';
            this.nextBtn.style.display = this.images.length > 1 ? 'flex' : 'none';
        }

        toggleZoom() {
            this.isZoomed = !this.isZoomed;
            this.image.classList.toggle('zoomed', this.isZoomed);
        }

        handleSwipe(startX, endX) {
            const threshold = 50;
            const diff = startX - endX;

            if (Math.abs(diff) > threshold) {
                if (diff > 0) {
                    this.next();
                } else {
                    this.prev();
                }
            }
        }
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.MusedockLightbox = new MusedockLightbox();
        });
    } else {
        window.MusedockLightbox = new MusedockLightbox();
    }

})();
