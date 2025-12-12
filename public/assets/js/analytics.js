/**
 * MuseDock Analytics - Cliente JavaScript
 * Sistema de tracking ligero y respetuoso con la privacidad
 * Compatible con GDPR
 */

(function() {
    'use strict';

    const MuseDockAnalytics = {
        // Configuración
        config: {
            cookieName: 'musedock_visitor',
            cookieConsent: 'musedock_cookies_accepted',
            sessionName: 'musedock_session',
            cookieDays: 365,
            endpoint: '/api/analytics/track'
        },

        // Estado
        sessionStart: null,
        pageViewStart: null,
        visitorId: null,
        sessionId: null,

        /**
         * Inicializar analytics
         */
        init: function() {
            // Verificar consentimiento de cookies
            if (!this.hasConsent()) {
                console.log('Analytics: User has not accepted cookies');
                return;
            }

            // Generar o recuperar IDs
            this.visitorId = this.getOrCreateVisitorId();
            this.sessionId = this.getOrCreateSessionId();

            // Registrar inicio de página
            this.pageViewStart = Date.now();
            this.sessionStart = this.getSessionStart();

            // Rastrear vista de página
            this.trackPageView();

            // Rastrear eventos de salida
            this.setupExitTracking();

            // Rastrear eventos de interacción
            this.setupInteractionTracking();
        },

        /**
         * Verificar si el usuario ha dado consentimiento
         */
        hasConsent: function() {
            return this.getCookie(this.config.cookieConsent) === 'true';
        },

        /**
         * Obtener o crear ID de visitante
         */
        getOrCreateVisitorId: function() {
            let visitorId = this.getCookie(this.config.cookieName);

            if (!visitorId) {
                visitorId = this.generateId();
                this.setCookie(this.config.cookieName, visitorId, this.config.cookieDays);
            }

            return visitorId;
        },

        /**
         * Obtener o crear ID de sesión
         */
        getOrCreateSessionId: function() {
            let sessionId = sessionStorage.getItem(this.config.sessionName);

            if (!sessionId) {
                sessionId = this.generateId();
                sessionStorage.setItem(this.config.sessionName, sessionId);
                sessionStorage.setItem(this.config.sessionName + '_start', Date.now().toString());
            }

            return sessionId;
        },

        /**
         * Obtener timestamp de inicio de sesión
         */
        getSessionStart: function() {
            const start = sessionStorage.getItem(this.config.sessionName + '_start');
            return start ? parseInt(start) : Date.now();
        },

        /**
         * Rastrear vista de página
         */
        trackPageView: function() {
            const data = {
                visitor_id: this.visitorId,
                session_id: this.sessionId,
                page_url: window.location.pathname + window.location.search,
                page_title: document.title,
                referrer: document.referrer,
                screen_resolution: screen.width + 'x' + screen.height,
                language: navigator.language || navigator.userLanguage,
                is_returning: this.getCookie(this.config.cookieName + '_returning') === 'true'
            };

            // Marcar como visitante recurrente para futuras visitas
            if (!data.is_returning) {
                this.setCookie(this.config.cookieName + '_returning', 'true', this.config.cookieDays);
            }

            this.sendData(data);
        },

        /**
         * Rastrear tiempo en página antes de salir
         */
        setupExitTracking: function() {
            const self = this;

            // Calcular duración cuando el usuario sale
            const trackDuration = function() {
                const duration = Math.round((Date.now() - self.pageViewStart) / 1000);
                const sessionDuration = Math.round((Date.now() - self.sessionStart) / 1000);

                // Enviar actualización con duración
                navigator.sendBeacon(self.config.endpoint, JSON.stringify({
                    visitor_id: self.visitorId,
                    session_id: self.sessionId,
                    session_duration: sessionDuration,
                    page_duration: duration
                }));
            };

            // Eventos de salida
            window.addEventListener('beforeunload', trackDuration);
            window.addEventListener('pagehide', trackDuration);

            // Rastrear cuando la pestaña pierde visibilidad
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    trackDuration();
                }
            });
        },

        /**
         * Rastrear interacciones del usuario
         */
        setupInteractionTracking: function() {
            const self = this;
            let hasInteracted = false;

            const markInteraction = function() {
                if (!hasInteracted) {
                    hasInteracted = true;
                    // La página no es un rebote si hay interacción
                    sessionStorage.setItem('musedock_bounce', 'false');
                }
            };

            // Eventos de interacción
            ['click', 'scroll', 'keydown', 'mousemove'].forEach(function(event) {
                document.addEventListener(event, markInteraction, { once: true, passive: true });
            });
        },

        /**
         * Enviar datos al servidor
         */
        sendData: function(data) {
            // Usar sendBeacon si está disponible (más confiable)
            if (navigator.sendBeacon) {
                navigator.sendBeacon(this.config.endpoint, JSON.stringify(data));
            } else {
                // Fallback a fetch
                fetch(this.config.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data),
                    keepalive: true
                }).catch(function(error) {
                    console.error('Analytics error:', error);
                });
            }
        },

        /**
         * Generar ID único
         */
        generateId: function() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
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
            MuseDockAnalytics.init();
        });
    } else {
        MuseDockAnalytics.init();
    }

    // Exportar para uso manual si es necesario
    window.MuseDockAnalytics = MuseDockAnalytics;
})();
