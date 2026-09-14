/**
 * Settings Master Coordinator Script
 * Central controller managing state hydration, sub-module loading, PPP initialization, and password security.
 */

let __settingsState = null;
function getSettingsState() {
    if (__settingsState) return __settingsState;
    const el = document.getElementById('settings-state');
    __settingsState = el ? JSON.parse(el.textContent) : {};
    return __settingsState;
}

// 1. Synchronously load all settings sub-modules in order
(function loadSettingsModules() {
    const modules = [
        'assets/js/settings/settings_ppp.js',
        'assets/js/settings/settings_security.js',
        'assets/js/settings/settings_dir_picker.js'
    ];

    modules.forEach(src => {
        if (!document.querySelector(`script[src*="${src}"]`)) {
            const script = document.createElement('script');
            script.src = src;
            script.async = false;
            document.head.appendChild(script);
        }
    });
})();

// 2. Initialize settings page functionality
function initSettingsApp() {
    const state = getSettingsState();

    if (typeof initPPPSettingsState === 'function') {
        initPPPSettingsState();
    }

    if (state.seq_key && typeof fetchGridPreview === 'function') {
        fetchGridPreview(state.seq_key);
    }

    const bypassCheckbox = document.getElementById('bypass_ppp');
    if (bypassCheckbox && typeof toggleBypassPPP === 'function') {
        toggleBypassPPP(bypassCheckbox.checked);
    }

    const newPasswordInput = document.getElementById('new_password');
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', () => {
            const checkbox = document.getElementById('bypass_ppp');
            if (checkbox && checkbox.checked && typeof updatePasswordStrength === 'function') {
                updatePasswordStrength(newPasswordInput.value);
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSettingsApp);
} else {
    initSettingsApp();
}
