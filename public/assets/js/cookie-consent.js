/**
 * MuseDock Cookie Consent Banner
 * Sistema de consentimiento de cookies compatible con GDPR/RGPD
 */

(function() {
    'use strict';

    const CookieConsent = {
        // Configuración
        config: {
            cookieName: 'musedock_cookies_accepted',
            cookieLifetime: 365, // días
            bannerDelay: 1000, // ms antes de mostrar el banner
            position: 'bottom', // bottom, top
            theme: 'light' // light, dark
        },

        /**
         * Inicializar el banner de cookies
         */
        init: function() {
            // Si ya aceptó o rechazó las cookies, no mostrar banner
            if (this.hasConsent() !== null) {
                return;
            }

            // Mostrar banner después de un pequeño delay
            setTimeout(() => {
                this.showBanner();
            }, this.config.bannerDelay);
        },

        /**
         * Verificar si el usuario ya dio consentimiento
         * @returns {boolean|null} true = aceptó, false = rechazó, null = no ha decidido
         */
        hasConsent: function() {
            const consent = this.getCookie(this.config.cookieName);

            if (consent === 'true') return true;
            if (consent === 'false') return false;
            return null;
        },

        /**
         * Mostrar banner de cookies
         */
        showBanner: function() {
            const banner = this.createBanner();
            document.body.appendChild(banner);

            // Animación de entrada
            setTimeout(() => {
                banner.classList.add('show');
            }, 10);
        },

        /**
         * Crear HTML del banner
         */
        createBanner: function() {
            const banner = document.createElement('div');
            banner.id = 'musedock-cookie-banner';
            banner.className = 'musedock-cookie-banner ' + this.config.position + ' ' + this.config.theme;

            banner.innerHTML = `
                <div class="cookie-banner-content">
                    <div class="cookie-banner-text">
                        <h4 class="cookie-banner-title">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M6 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm4.5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm-.5 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                                <path d="M8 0a7.963 7.963 0 0 0-4.075 1.114c-.162.089-.33.175-.5.256A8 8 0 1 0 8 0zm0 1a7 7 0 1 1 0 14A7 7 0 0 1 8 1z"/>
                            </svg>
                            Uso de Cookies
                        </h4>
                        <p class="cookie-banner-description">
                            Utilizamos cookies para mejorar tu experiencia de navegación, analizar el tráfico del sitio y personalizar el contenido.
                            Al hacer clic en "Aceptar", consientes el uso de todas las cookies.
                        </p>
                        <div class="cookie-banner-links">
                            <a href="/privacy" target="_blank">Política de Privacidad</a>
                            <a href="/cookies" target="_blank">Política de Cookies</a>
                        </div>
                    </div>
                    <div class="cookie-banner-actions">
                        <button type="button" class="cookie-btn cookie-btn-settings" id="cookie-settings-btn">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/>
                            </svg>
                            Configurar
                        </button>
                        <button type="button" class="cookie-btn cookie-btn-reject" id="cookie-reject-btn">
                            Rechazar
                        </button>
                        <button type="button" class="cookie-btn cookie-btn-accept" id="cookie-accept-btn">
                            Aceptar
                        </button>
                    </div>
                </div>
            `;

            // Event listeners
            banner.querySelector('#cookie-accept-btn').addEventListener('click', () => {
                this.acceptCookies();
            });

            banner.querySelector('#cookie-reject-btn').addEventListener('click', () => {
                this.rejectCookies();
            });

            banner.querySelector('#cookie-settings-btn').addEventListener('click', () => {
                this.showSettings(banner);
            });

            return banner;
        },

        /**
         * Mostrar configuración avanzada
         */
        showSettings: function(banner) {
            const content = banner.querySelector('.cookie-banner-content');

            content.innerHTML = `
                <div class="cookie-banner-text">
                    <h4 class="cookie-banner-title">Configuración de Cookies</h4>
                    <p class="cookie-banner-description">
                        Puedes elegir qué tipos de cookies deseas permitir. Ten en cuenta que deshabilitar algunas cookies puede afectar tu experiencia en el sitio.
                    </p>

                    <div class="cookie-preferences">
                        <div class="cookie-preference-item">
                            <div class="cookie-preference-header">
                                <label>
                                    <input type="checkbox" checked disabled>
                                    <strong>Cookies Necesarias</strong>
                                </label>
                                <span class="cookie-badge required">Requeridas</span>
                            </div>
                            <p class="cookie-preference-description">
                                Estas cookies son esenciales para el funcionamiento del sitio web y no se pueden desactivar.
                            </p>
                        </div>

                        <div class="cookie-preference-item">
                            <div class="cookie-preference-header">
                                <label>
                                    <input type="checkbox" id="cookie-analytics" checked>
                                    <strong>Cookies de Análisis</strong>
                                </label>
                            </div>
                            <p class="cookie-preference-description">
                                Nos ayudan a entender cómo los visitantes interactúan con el sitio web recopilando y reportando información de forma anónima.
                            </p>
                        </div>

                        <div class="cookie-preference-item">
                            <div class="cookie-preference-header">
                                <label>
                                    <input type="checkbox" id="cookie-functional" checked>
                                    <strong>Cookies Funcionales</strong>
                                </label>
                            </div>
                            <p class="cookie-preference-description">
                                Permiten funcionalidades mejoradas y personalizadas, como videos y chat en vivo.
                            </p>
                        </div>

                        <div class="cookie-preference-item">
                            <div class="cookie-preference-header">
                                <label>
                                    <input type="checkbox" id="cookie-marketing">
                                    <strong>Cookies de Marketing</strong>
                                </label>
                            </div>
                            <p class="cookie-preference-description">
                                Se utilizan para rastrear a los visitantes en los sitios web con fines publicitarios.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="cookie-banner-actions">
                    <button type="button" class="cookie-btn cookie-btn-settings" id="cookie-back-btn">
                        Volver
                    </button>
                    <button type="button" class="cookie-btn cookie-btn-accept" id="cookie-save-btn">
                        Guardar Preferencias
                    </button>
                </div>
            `;

            // Event listeners
            content.querySelector('#cookie-back-btn').addEventListener('click', () => {
                this.hideBanner();
                this.showBanner();
            });

            content.querySelector('#cookie-save-btn').addEventListener('click', () => {
                const preferences = {
                    necessary: true,
                    analytics: content.querySelector('#cookie-analytics').checked,
                    functional: content.querySelector('#cookie-functional').checked,
                    marketing: content.querySelector('#cookie-marketing').checked
                };

                this.savePreferences(preferences);
            });
        },

        /**
         * Aceptar todas las cookies
         */
        acceptCookies: function() {
            this.setCookie(this.config.cookieName, 'true', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_analytics', 'true', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_functional', 'true', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_marketing', 'true', this.config.cookieLifetime);

            this.hideBanner();
            this.reloadIfNeeded();
        },

        /**
         * Rechazar cookies no esenciales
         */
        rejectCookies: function() {
            this.setCookie(this.config.cookieName, 'false', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_analytics', 'false', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_functional', 'false', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_marketing', 'false', this.config.cookieLifetime);

            this.hideBanner();
        },

        /**
         * Guardar preferencias personalizadas
         */
        savePreferences: function(preferences) {
            this.setCookie(this.config.cookieName, 'true', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_analytics', preferences.analytics ? 'true' : 'false', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_functional', preferences.functional ? 'true' : 'false', this.config.cookieLifetime);
            this.setCookie('musedock_cookie_marketing', preferences.marketing ? 'true' : 'false', this.config.cookieLifetime);

            this.hideBanner();
            this.reloadIfNeeded();
        },

        /**
         * Ocultar banner
         */
        hideBanner: function() {
            const banner = document.getElementById('musedock-cookie-banner');

            if (banner) {
                banner.classList.remove('show');

                setTimeout(() => {
                    banner.remove();
                }, 300);
            }
        },

        /**
         * Recargar si es necesario (para activar analytics)
         */
        reloadIfNeeded: function() {
            // Si se aceptaron las cookies de analytics, recargar para iniciar tracking
            if (this.getCookie('musedock_cookie_analytics') === 'true') {
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        },

        /**
         * Obtener cookie
         */
        getCookie: function(name) {
            const value = '; ' + document.cookie;
            const parts = value.split('; ' + name + '=');

            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }

            return null;
        },

        /**
         * Establecer cookie
         */
        setCookie: function(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));

            const cookieValue = name + '=' + value +
                ';expires=' + expires.toUTCString() +
                ';path=/' +
                ';SameSite=Lax' +
                (location.protocol === 'https:' ? ';Secure' : '');

            document.cookie = cookieValue;
        }
    };

    // Auto-inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            CookieConsent.init();
        });
    } else {
        CookieConsent.init();
    }

    // Exportar para uso manual
    window.CookieConsent = CookieConsent;
})();
