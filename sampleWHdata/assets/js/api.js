const API = {
    async getConfig() {
        try {
            const response = await fetch('process.php?action=get_config');
            const result = await response.json();
            return result.success ? (result.config || {}) : {};
        } catch (e) {
            console.warn('API error fetching config:', e);
            return null;
        }
    },

    async saveConfig(config) {
        const response = await fetch('process.php?action=save_config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(config)
        });
        return await response.json();
    },

    async normalizeRows(rows) {
        try {
            const response = await fetch('process.php?action=normalize', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
        const formData = new FormData();
        formData.append('images[]', file);
        const response = await fetch('process.php?action=extract', {
            method: 'POST',
            body: formData
        });
        return await response.json();
    },

    async saveRows(payload) {
        const response = await fetch('process.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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
            const response = await fetch('process.php?action=clear_committed', {
                method: 'POST'
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
