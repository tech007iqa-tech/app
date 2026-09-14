/**
 * Standalone IQA Live Notification Engine
 * Standardized system-wide toast notification alerts.
 */

const IQA_Notify = {
    show(message, type = 'success') {
        let container = document.getElementById('iqa-notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'iqa-notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 350px;
                width: calc(100% - 40px);
            `;
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `iqa-toast iqa-toast-${type}`;

        const colors = {
            success: { bg: 'rgba(33, 136, 56, 0.95)', border: '#218838', icon: '✅' },
            error: { bg: 'rgba(239, 68, 68, 0.95)', border: '#ef4444', icon: '❌' },
            warning: { bg: 'rgba(245, 158, 11, 0.95)', border: '#f59e0b', icon: '⚠️' }
        };

        const style = colors[type] || colors.success;

        toast.style.cssText = `
            background: ${style.bg};
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: white;
            padding: 14px 20px;
            border-radius: 12px;
            border: 1px solid ${style.border};
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        `;

        toast.innerHTML = `
            <span style="font-size: 1.1rem; flex-shrink: 0;">${style.icon}</span>
            <span style="flex-grow: 1; word-break: break-word; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">${message}</span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; font-size: 1rem; font-weight: 800; padding: 0; opacity: 0.7; transition: opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">✕</button>
        `;

        container.appendChild(toast);

        // Force reflow to trigger CSS transition
        toast.offsetHeight;

        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';

        // Automatically remove after timeout
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                toast.remove();
            }, 400);
        }, 4000);
    },

    success(message) {
        this.show(message, 'success');
    },

    error(message) {
        this.show(message, 'error');
    },

    warning(message) {
        this.show(message, 'warning');
    }
};
