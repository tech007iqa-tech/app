/**
 * Settings Password Security & Strength Meter Module
 * Handles password complexity evaluation, real-time strength meter updates, and PPP bypass toggles.
 */

function toggleBypassPPP(isChecked) {
    const warning = document.getElementById('ppp-bypass-warning');
    const meter = document.getElementById('strength-meter-container');
    const newPasswordInput = document.getElementById('new_password');
    const state = typeof getSettingsState === 'function' ? getSettingsState() : {};
    const isForced = state.is_forced || false;

    if (warning) {
        warning.style.display = isChecked ? 'block' : 'none';
    }
    if (meter) {
        meter.style.display = isChecked ? 'flex' : 'none';
    }

    if (newPasswordInput) {
        if (isChecked) {
            newPasswordInput.removeAttribute('readonly');
            newPasswordInput.placeholder = "Min 12 chars, complex (A-Z, a-z, 0-9, symbol)";
            updatePasswordStrength(newPasswordInput.value);
        } else {
            if (isForced) {
                newPasswordInput.setAttribute('readonly', 'readonly');
            }
            newPasswordInput.placeholder = "Min 24 chars, complex (A-Z, a-z, 0-9, symbol)";
            if (meter) {
                meter.style.display = 'none';
            }
        }
    }
}

function updatePasswordStrength(password) {
    const bar = document.getElementById('strength-meter-bar');
    const text = document.getElementById('strength-meter-text');
    if (!bar || !text) return;

    if (!password) {
        bar.style.width = '0%';
        bar.style.backgroundColor = '#e2e8f0';
        text.innerText = '';
        return;
    }

    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (password.length >= 16) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    let percentage = (score / 7) * 100;
    let color = '#ef4444'; // Red
    let label = 'Weak';

    if (score >= 6) {
        color = '#10b981'; // Green
        label = 'Strong';
    } else if (score >= 4) {
        color = '#f59e0b'; // Yellow
        label = 'Medium';
    }

    bar.style.width = `${percentage}%`;
    bar.style.backgroundColor = color;
    text.innerText = `Strength: ${label}`;
    text.style.color = color;
}
