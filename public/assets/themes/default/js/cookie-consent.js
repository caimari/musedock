       document.addEventListener('DOMContentLoaded', function() {

            const consentPopup = document.getElementById('cookie-consent-popup');
            const preferencesModal = document.getElementById('cookie-preferences-modal');
            // Corrección: Asegúrate que los IDs coinciden entre HTML y JS
            const acceptAllBtnPopup = document.getElementById('cookie-accept-all'); // ID del botón Aceptar en el Popup
            const rejectAllBtnPopup = document.getElementById('cookie-reject-all'); // ID del botón Rechazar en el Popup
            const managePrefsBtnPopup = document.getElementById('cookie-manage-prefs'); // ID del botón Gestionar en el Popup

            const closeModalBtn = document.getElementById('cookie-modal-close');
            const savePrefsBtnModal = document.getElementById('cookie-modal-save');
             // Corrección: Asegúrate que los IDs coinciden entre HTML y JS
            const acceptAllBtnModal = document.getElementById('cookie-modal-accept-all'); // ID del botón Aceptar en el Modal
            const rejectAllBtnModal = document.getElementById('cookie-modal-reject-all'); // ID del botón Rechazar en el Modal


            const analyticsToggle = document.getElementById('cookie-pref-analytics');
            const targetingToggle = document.getElementById('cookie-pref-targeting');

            const COOKIE_CONSENT_KEY = 'cookie_consent_preferences';
            const CONSENT_GIVEN_KEY = 'cookie_consent_given';

            // Variables para tracking de scripts cargados (declarar al inicio)
            let analyticsLoaded = false;
            let targetingLoaded = false;

            // --- Funciones Principales ---

            function getConsentPreferences() {
                const savedPrefs = localStorage.getItem(COOKIE_CONSENT_KEY);
                if (savedPrefs) {
                    try {
                        // Añadido chequeo por si el valor guardado no es un objeto válido
                        const parsed = JSON.parse(savedPrefs);
                        if (typeof parsed === 'object' && parsed !== null) {
                           // Asegurar que las propiedades booleanas existen
                           return {
                                necessary: true, // Siempre true
                                analytics: !!parsed.analytics,
                                targeting: !!parsed.targeting
                            };
                        }
                    } catch (e) {
                        console.error("Error parsing saved cookie preferences:", e);
                    }
                }
                // Estado inicial por defecto si no hay nada guardado o hay error
                return { necessary: true, analytics: false, targeting: false };
            }

            function saveConsentPreferences(prefs) {
                // Asegurarse de guardar booleanos
                const prefsToSave = {
                     necessary: true,
                     analytics: !!prefs.analytics,
                     targeting: !!prefs.targeting
                };
                localStorage.setItem(COOKIE_CONSENT_KEY, JSON.stringify(prefsToSave));
                localStorage.setItem(CONSENT_GIVEN_KEY, 'true');
                console.log("Preferences saved:", prefsToSave);
                applyConsentPreferences(prefsToSave);
            }

            function applyConsentPreferences(prefs) {
                console.log("Applying preferences:", prefs);

                // Analíticas
                if (prefs.analytics) {
                    console.log("Enabling Analytics Cookies");
                    if (typeof gtag === 'function') {
                        gtag('consent', 'update', {'analytics_storage': 'granted'});
                    }
                    const gaTrackingId = findGaTrackingId();
                    if (gaTrackingId) { window[`ga-disable-${gaTrackingId}`] = false; }
                    loadAnalyticsScriptIfNeeded();
                } else {
                    console.log("Disabling Analytics Cookies");
                    if (typeof gtag === 'function') {
                        gtag('consent', 'update', {'analytics_storage': 'denied'});
                    }
                     const gaTrackingId = findGaTrackingId();
                     if (gaTrackingId) { window[`ga-disable-${gaTrackingId}`] = true; }
                    deleteCookie('_ga');
                    deleteCookie('_gid');
                    deleteCookie('_gat'); // y variantes _gat_gtag_...
                    // Busca y elimina cookies _gat_gtag_UA_XXXX o _gat_gtag_G_XXXX
                    document.cookie.split(';').forEach(function(c) {
                        const name = c.trim().split('=')[0];
                        if (name.startsWith('_gat_gtag_')) {
                            deleteCookie(name);
                        }
                     });
                }

                // Targeting
                if (prefs.targeting) {
                    console.log("Enabling Targeting Cookies");
                    // if (typeof fbq === 'function') { fbq('consent', 'grant'); }
                    loadTargetingScriptIfNeeded();
                } else {
                    console.log("Disabling Targeting Cookies");
                    // if (typeof fbq === 'function') { fbq('consent', 'revoke'); }
                    deleteCookie('_fbp');
                    // eliminar otras cookies de targeting...
                }
            }

            function showConsentPopup() {
                // Comprueba si el elemento existe antes de intentar modificarlo
                if (consentPopup) consentPopup.style.display = 'block';
                else console.error("Element with ID 'cookie-consent-popup' not found.");
            }

            function hideConsentPopup() {
                if (consentPopup) consentPopup.style.display = 'none';
            }

            function showPreferencesModal() {
                const currentPrefs = getConsentPreferences();
                if (analyticsToggle) analyticsToggle.checked = currentPrefs.analytics;
                if (targetingToggle) targetingToggle.checked = currentPrefs.targeting;

                // Comprueba si el elemento existe
                if (preferencesModal) preferencesModal.style.display = 'flex';
                 else console.error("Element with ID 'cookie-preferences-modal' not found.");
            }

            function hidePreferencesModal() {
                if (preferencesModal) preferencesModal.style.display = 'none';
            }

            // --- Lógica de Inicialización ---
            const consentGiven = localStorage.getItem(CONSENT_GIVEN_KEY) === 'true';

            if (!consentGiven) {
                showConsentPopup();
            } else {
                const prefs = getConsentPreferences();
                applyConsentPreferences(prefs);
            }

            // --- Event Listeners (con comprobaciones de existencia de elementos) ---

            if (acceptAllBtnPopup) {
                acceptAllBtnPopup.addEventListener('click', () => {
                    const prefs = { necessary: true, analytics: true, targeting: true };
                    saveConsentPreferences(prefs);
                    hideConsentPopup();
                });
            } else { console.warn("Button 'cookie-accept-all' (Popup) not found"); }

            if (rejectAllBtnPopup) {
                rejectAllBtnPopup.addEventListener('click', () => {
                    const prefs = { necessary: true, analytics: false, targeting: false };
                    saveConsentPreferences(prefs);
                    hideConsentPopup();
                });
             } else { console.warn("Button 'cookie-reject-all' (Popup) not found"); }

            if (managePrefsBtnPopup) {
                managePrefsBtnPopup.addEventListener('click', () => {
                    hideConsentPopup();
                    showPreferencesModal();
                });
            } else { console.warn("Button 'cookie-manage-prefs' (Popup) not found"); }

            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', () => {
                    hidePreferencesModal();
                    if (!localStorage.getItem(CONSENT_GIVEN_KEY)) {
                         showConsentPopup();
                    }
                });
            } else { console.warn("Button 'cookie-modal-close' not found"); }

            if (preferencesModal) {
                preferencesModal.addEventListener('click', (event) => {
                    if (event.target === preferencesModal) {
                        hidePreferencesModal();
                        if (!localStorage.getItem(CONSENT_GIVEN_KEY)) {
                            showConsentPopup();
                        }
                    }
                });
            } // No warning needed here, handled above

            if (savePrefsBtnModal) {
                savePrefsBtnModal.addEventListener('click', () => {
                    const prefs = {
                        necessary: true,
                        analytics: analyticsToggle ? analyticsToggle.checked : false,
                        targeting: targetingToggle ? targetingToggle.checked : false
                    };
                    saveConsentPreferences(prefs);
                    hidePreferencesModal();
                });
            } else { console.warn("Button 'cookie-modal-save' not found"); }

            if (acceptAllBtnModal) {
                acceptAllBtnModal.addEventListener('click', () => {
                    const prefs = { necessary: true, analytics: true, targeting: true };
                    saveConsentPreferences(prefs);
                    hidePreferencesModal();
                });
            } else { console.warn("Button 'cookie-modal-accept-all' (Modal) not found"); }

            if (rejectAllBtnModal) {
                rejectAllBtnModal.addEventListener('click', () => {
                    const prefs = { necessary: true, analytics: false, targeting: false };
                    saveConsentPreferences(prefs);
                    hidePreferencesModal();
                });
            } else { console.warn("Button 'cookie-modal-reject-all' (Modal) not found"); }


            // --- Funciones Auxiliares ---
            function findGaTrackingId() {
                // Primero intenta con gtag si existe
                if (typeof gtag === 'function' && window.dataLayer) {
                    for (const item of window.dataLayer) {
                         if (item[0] === 'config' && item[1] && (item[1].startsWith('UA-') || item[1].startsWith('G-'))) {
                             return item[1];
                         }
                     }
                }
                 // Luego intenta con ga si existe
                 if (typeof ga === 'function' && ga.getAll) {
                    const trackers = ga.getAll();
                    if (trackers.length > 0) { return trackers[0].get('trackingId'); }
                }
                 // Busca en scripts como último recurso (menos fiable)
                 const scripts = document.getElementsByTagName('script');
                 for (let script of scripts) {
                     const matchGtag = script.innerHTML.match(/gtag\('config', '([^']+)'/);
                     if (matchGtag && matchGtag[1]) return matchGtag[1];
                     const matchGa = script.innerHTML.match(/ga\('create', '([^']+)'/);
                     if (matchGa && matchGa[1]) return matchGa[1];
                 }
                 console.warn("Google Analytics Tracking ID not found automatically.");
                 return null;
            }

            function deleteCookie(name) {
                const path = '; Path=/';
                const expires = '; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                let domain = '';
                // Intenta con el dominio base y luego sin especificar dominio explícitamente
                if (window.location.hostname !== 'localhost') {
                    domain = '; Domain=' + window.location.hostname.split('.').slice(-2).join('.');
                }
                document.cookie = name + '=' + path + domain + expires; // Con dominio base
                 document.cookie = name + '=' + path + '; Domain=' + window.location.hostname + expires; // Con dominio completo
                document.cookie = name + '=' + path + expires; // Sin dominio explícito
                console.log(`Attempted to delete cookie: ${name}`);
            }

            function loadAnalyticsScriptIfNeeded() {
                if (typeof gtag === 'function' || typeof ga === 'function' || analyticsLoaded) {
                    //console.log("Analytics already loaded or initialized.");
                    return;
                }
                if (getConsentPreferences().analytics) {
                    console.log("Dynamically loading Analytics script (Placeholder - Implement actual loading if needed)");
                    // AQUÍ iría la lógica para crear el <script> de GA/GTM si no lo cargas por defecto en el HTML
                    // Ejemplo comentado:
                    /*
                    const gaId = findGaTrackingId() || 'TU_ID_POR_DEFECTO'; // Necesitas tu ID
                    if (gaId.startsWith('G-') || gaId.startsWith('AW-')) { // GA4 o GTAG
                        const script = document.createElement('script');
                        script.src = `https://www.googletagmanager.com/gtag/js?id=${gaId}`;
                        script.async = true;
                        document.head.appendChild(script);
                        script.onload = () => {
                            window.dataLayer = window.dataLayer || [];
                            function gtag(){dataLayer.push(arguments);}
                            gtag('js', new Date());
                            gtag('config', gaId);
                            gtag('consent', 'update', {'analytics_storage': 'granted'}); // Asegurar estado
                            analyticsLoaded = true;
                            console.log("Gtag loaded dynamically");
                        };
                     } else if (gaId.startsWith('UA-')) { // Universal Analytics (viejo)
                        // ... código para cargar analytics.js ...
                        analyticsLoaded = true;
                     }
                     */
                }
            }

            function loadTargetingScriptIfNeeded() {
                if (typeof fbq === 'function' || targetingLoaded) { // Ejemplo FB Pixel
                    //console.log("Targeting script already loaded or initialized.");
                    return;
                }
                if (getConsentPreferences().targeting) {
                    console.log("Dynamically loading Targeting script (Placeholder - Implement actual loading)");
                    // AQUÍ iría la lógica para crear el <script> de FB Pixel, etc.
                    targetingLoaded = true;
                }
            }

       });
 