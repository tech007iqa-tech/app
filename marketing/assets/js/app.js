/**
 * Marketing Hub - Main Application Script
 * Scoped in an IIFE to prevent global identifier redeclaration collisions.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Prevent double submit on forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.hasAttribute('data-submitting') && !e.defaultPrevented) {
                    submitBtn.setAttribute('data-submitting', 'true');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.style.opacity = '0.7';
                    submitBtn.style.pointerEvents = 'none';
                    setTimeout(() => {
                        submitBtn.removeAttribute('data-submitting');
                        submitBtn.style.opacity = '1';
                        submitBtn.style.pointerEvents = 'auto';
                    }, 4000);
                }
            });
        });

        // 2. Setup CSRF for all standard fetch requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            window.CSRF_TOKEN = csrfToken;
        }
    });

    /**
     * Utility: Copy text to clipboard with feedback
     */
    function copyToClipboard(text, description = 'Content') {
        if (!text) return;

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                notify(`${description} copied to clipboard!`, 'success', '📋 Copied');
            }).catch(err => {
                fallbackCopy(text, description);
            });
        } else {
            fallbackCopy(text, description);
        }
    }

    function fallbackCopy(text, description) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.opacity = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            notify(`${description} copied to clipboard!`, 'success', '📋 Copied');
        } catch (err) {
            notify('Failed to copy to clipboard.', 'error');
        }
        document.body.removeChild(textArea);
    }

    /**
     * Toast Notification System - DOM XSS Safe
     */
    function notify(message, type = 'success', title = '') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        const defaultTitles = {
            success: 'Success',
            error: 'Error',
            warning: 'Attention',
            info: 'Update'
        };

        const iconDiv = document.createElement('div');
        iconDiv.className = 'toast-icon';
        iconDiv.textContent = icons[type] || 'ℹ️';

        const contentDiv = document.createElement('div');
        contentDiv.className = 'toast-content';

        const titleSpan = document.createElement('span');
        titleSpan.className = 'toast-title';
        titleSpan.textContent = title || defaultTitles[type] || 'Notification';

        const msgSpan = document.createElement('span');
        msgSpan.className = 'toast-message';
        msgSpan.textContent = message;

        contentDiv.appendChild(titleSpan);
        contentDiv.appendChild(msgSpan);

        toast.appendChild(iconDiv);
        toast.appendChild(contentDiv);
        container.appendChild(toast);

        // Auto remove
        const timer = setTimeout(() => {
            dismissToast(toast);
        }, 4500);

        toast.onclick = () => {
            clearTimeout(timer);
            dismissToast(toast);
        };
    }

    function dismissToast(toast) {
        toast.classList.add('hide');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }

    /**
     * Helper to confirm destructive actions
     */
    function confirmAction(message, formOrCallback) {
        if (confirm(message)) {
            if (typeof formOrCallback === 'function') {
                formOrCallback();
            } else if (formOrCallback && formOrCallback.submit) {
                formOrCallback.submit();
            }
            return true;
        }
        return false;
    }

    // Expose global helpers safely
    window.notify = notify;
    window.copyToClipboard = copyToClipboard;
    window.confirmAction = confirmAction;

})();
