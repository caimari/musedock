document.addEventListener('DOMContentLoaded', function() {

    const consentPopup = document.getElementById('cookie-consent-popup');
    const preferencesModal = document.getElementById('cookie-preferences-modal');
    const acceptAllBtnPopup = document.getElementById('cookie-accept-all');
    const rejectAllBtnPopup = document.getElementById('cookie-reject-all');
    const managePrefsBtnPopup = document.getElementById('cookie-manage-prefs');

    const closeModalBtn = document.getElementById('cookie-modal-close');
    const savePrefsBtnModal = document.getElementById('cookie-modal-save');
    const acceptAllBtnModal = document.getElementById('cookie-modal-accept-all');
    const rejectAllBtnModal = document.getElementById('cookie-modal-reject-all');

    const targetingToggle = document.getElementById('cookie-pref-targeting');

    const COOKIE_CONSENT_KEY = 'cookie_consent_preferences';
    const CONSENT_GIVEN_KEY = 'cookie_consent_given';

    // --- Funciones Principales ---

    function getConsentPreferences() {
        const savedPrefs = localStorage.getItem(COOKIE_CONSENT_KEY);
        if (savedPrefs) {
            try {
                const parsed = JSON.parse(savedPrefs);
                if (typeof parsed === 'object' && parsed !== null) {
                    return {
                        necessary: true,
                        targeting: !!parsed.targeting
                    };
                }
            } catch (e) {
                console.error("Error parsing saved cookie preferences:", e);
            }
        }
        return { necessary: true, targeting: false };
    }

    function saveConsentPreferences(prefs) {
        const prefsToSave = {
            necessary: true,
            targeting: !!prefs.targeting
        };
        localStorage.setItem(COOKIE_CONSENT_KEY, JSON.stringify(prefsToSave));
        localStorage.setItem(CONSENT_GIVEN_KEY, 'true');
        applyConsentPreferences(prefsToSave);
    }

    function applyConsentPreferences(prefs) {
        // Targeting cookies (marketing, redes sociales, etc.)
        if (prefs.targeting) {
            // Aqui se pueden cargar scripts de marketing si se configuran
        } else {
            // Eliminar cookies de targeting si existen
            deleteCookie('_fbp');
        }
    }

    function showConsentPopup() {
        if (consentPopup) consentPopup.style.display = 'block';
    }

    function hideConsentPopup() {
        if (consentPopup) consentPopup.style.display = 'none';
    }

    function showPreferencesModal() {
        const currentPrefs = getConsentPreferences();
        if (targetingToggle) targetingToggle.checked = currentPrefs.targeting;
        if (preferencesModal) preferencesModal.style.display = 'flex';
    }

    function hidePreferencesModal() {
        if (preferencesModal) preferencesModal.style.display = 'none';
    }

    // --- Logica de Inicializacion ---
    const consentGiven = localStorage.getItem(CONSENT_GIVEN_KEY) === 'true';

    if (!consentGiven) {
        showConsentPopup();
    } else {
        const prefs = getConsentPreferences();
        applyConsentPreferences(prefs);
    }

    // --- Event Listeners ---

    if (acceptAllBtnPopup) {
        acceptAllBtnPopup.addEventListener('click', () => {
            const prefs = { necessary: true, targeting: true };
            saveConsentPreferences(prefs);
            hideConsentPopup();
        });
    }

    if (rejectAllBtnPopup) {
        rejectAllBtnPopup.addEventListener('click', () => {
            const prefs = { necessary: true, targeting: false };
            saveConsentPreferences(prefs);
            hideConsentPopup();
        });
    }

    if (managePrefsBtnPopup) {
        managePrefsBtnPopup.addEventListener('click', () => {
            hideConsentPopup();
            showPreferencesModal();
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            hidePreferencesModal();
            if (!localStorage.getItem(CONSENT_GIVEN_KEY)) {
                showConsentPopup();
            }
        });
    }

    if (preferencesModal) {
        preferencesModal.addEventListener('click', (event) => {
            if (event.target === preferencesModal) {
                hidePreferencesModal();
                if (!localStorage.getItem(CONSENT_GIVEN_KEY)) {
                    showConsentPopup();
                }
            }
        });
    }

    if (savePrefsBtnModal) {
        savePrefsBtnModal.addEventListener('click', () => {
            const prefs = {
                necessary: true,
                targeting: targetingToggle ? targetingToggle.checked : false
            };
            saveConsentPreferences(prefs);
            hidePreferencesModal();
        });
    }

    if (acceptAllBtnModal) {
        acceptAllBtnModal.addEventListener('click', () => {
            const prefs = { necessary: true, targeting: true };
            saveConsentPreferences(prefs);
            hidePreferencesModal();
        });
    }

    if (rejectAllBtnModal) {
        rejectAllBtnModal.addEventListener('click', () => {
            const prefs = { necessary: true, targeting: false };
            saveConsentPreferences(prefs);
            hidePreferencesModal();
        });
    }

    // --- Enlace del footer para abrir configuracion de cookies (RGPD) ---
    const openCookieSettingsLink = document.getElementById('open-cookie-settings');
    if (openCookieSettingsLink) {
        openCookieSettingsLink.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            // Evitar scroll cuando hay navbar fijo
            if (window.scrollY > 0) {
                window.scrollTo({ top: window.scrollY, behavior: 'instant' });
            }
            showPreferencesModal();
        });
    }

    // --- Funciones Auxiliares ---

    function deleteCookie(name) {
        const path = '; Path=/';
        const expires = '; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
        let domain = '';
        if (window.location.hostname !== 'localhost') {
            domain = '; Domain=' + window.location.hostname.split('.').slice(-2).join('.');
        }
        document.cookie = name + '=' + path + domain + expires;
        document.cookie = name + '=' + path + '; Domain=' + window.location.hostname + expires;
        document.cookie = name + '=' + path + expires;
    }

});
