/**
 * IQA Global Notification System
 * A lightweight, glassmorphic toast notification engine.
 */

class Notifications {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        if (document.getElementById('toast-container')) return;

        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        let icon = 'ℹ️';
        if (type === 'success') icon = '✨';
        if (type === 'error') icon = '⚠️';
        if (type === 'warning') icon = '🔔';

        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
        `;

        this.container.appendChild(toast);

        // Auto-remove
        setTimeout(() => this.hide(toast), duration);

        // Manual remove on click
        toast.onclick = () => this.hide(toast);
    }

    hide(toast) {
        if (toast.classList.contains('hiding')) return;

        toast.classList.add('hiding');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }

// Shortcut methods
    success(msg, dur) { this.show(msg, 'success', dur); }
    error(msg, dur) { this.show(msg, 'error', dur); }
    warn(msg, dur) { this.show(msg, 'warning', dur); }
    info(msg, dur) { this.show(msg, 'info', dur); }
}

// Global instance with backward-compatible aliases
window.LatinosPC_Notify = new Notifications();
window.IQA_Notify = window.LatinosPC_Notify;
window.Notify = window.LatinosPC_Notify;

// Check for session-based notifications (PHP flash messages)
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');

        const messages = {
            'added': 'New entry successfully added ✨',
            'updated': 'Changes saved successfully 💾',
            'deleted': 'Item removed from database 🗑️',
            'customer_deleted': 'Customer profile permanently deleted',
            'order_deleted': 'Order removed from registry',
            'order_finalized': 'Order manifest finalized and archived ✅',
            'item_removed': 'Item removed from batch',
            'zone_deleted': 'Warehouse zone cleared 🧹',
            'zone_merged': 'Locations successfully merged 🔄',
            'working_zone_merged': 'Working zones successfully merged 🔄',
            'working_zone_updated': 'Working zone renamed successfully 💾',
            'error': 'An unexpected error occurred ⚠️',
            'unauthorized': 'Security access denied 🚫'
        };

        if (messages[msg]) {
            IQA_Notify.show(messages[msg], msg.includes('error') || msg === 'unauthorized' ? 'error' : 'success');
        }

        // Clean URL without refresh
        const url = new URL(window.location);
        url.searchParams.delete('msg');
        window.history.replaceState({}, '', url.pathname + url.search);
    }
});
