const API = {
    csrfToken: '',

    getCsrfToken() {
        if (this.csrfToken) return this.csrfToken;
        try {
            const meta = document.querySelector('meta[name="csrf-token"]') || 
                         (window.parent && window.parent !== window && window.parent.document && window.parent.document.querySelector('meta[name="csrf-token"]'));
            if (meta) return meta.getAttribute('content') || '';
        } catch (e) {}
        return '';
    },

    async getConfig() {
        try {
            const response = await fetch('process.php?action=get_config');
            const result = await response.json();
            if (result && result.csrf_token) {
                this.csrfToken = result.csrf_token;
            }
            return result.success ? (result.config || {}) : {};
        } catch (e) {
            console.warn('API error fetching config:', e);
            return null;
        }
    },

    async saveConfig(config) {
        const csrf = this.getCsrfToken();
        const response = await fetch('process.php?action=save_config', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify({ ...config, csrf_token: csrf })
        });
        return await response.json();
    },

    async normalizeRows(rows) {
        try {
            const csrf = this.getCsrfToken();
            const response = await fetch('process.php?action=normalize', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrf
                },
                body: JSON.stringify(rows)
            });
            const result = await response.json();
            return result.success ? result.data : rows;
        } catch (e) {
            console.warn('Normalization failed, using raw rows:', e);
            return rows;
        }
    },

    async extractOCR(file) {
        const csrf = this.getCsrfToken();
        const formData = new FormData();
        formData.append('images[]', file);
        if (csrf) formData.append('csrf_token', csrf);
        const response = await fetch('process.php?action=extract', {
            method: 'POST',
            headers: csrf ? { 'X-CSRF-Token': csrf } : {},
            body: formData
        });
        return await response.json();
    },

    async saveRows(payload) {
        const csrf = this.getCsrfToken();
        const response = await fetch('process.php?action=save', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(payload)
        });
        return await response.json();
    },

    async getCommitted() {
        try {
            const response = await fetch('process.php?action=get_committed');
            const result = await response.json();
            return result.success ? (result.data || []) : [];
        } catch (e) {
            console.warn('API error fetching committed records:', e);
            return [];
        }
    },

    async clearCommitted() {
        try {
            const csrf = this.getCsrfToken();
            const response = await fetch('process.php?action=clear_committed', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrf
                },
                body: JSON.stringify({ csrf_token: csrf })
            });
            const result = await response.json();
            return result.success;
        } catch (e) {
            console.warn('API error clearing committed records:', e);
            return false;
        }
    }
};
window.API = API;
