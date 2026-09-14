/**
 * AppSync — Scalable Livewire-style UI Synchronization Utility
 * Uses ultra-fast non-blocking sync checks (<1ms) to eliminate server worker blocking.
 */
window.AppSync = window.AppSync || {
    registrations: {},
    pollTimer: null,
    lastMtime: 0,
    isPolling: false,

    /**
     * Register a container for event-driven synchronization.
     */
    register({ elementId, url, rowSelector = 'tr', rowIdAttribute = 'data-id', onUpdate = null }) {
        this.registrations[elementId] = {
            elementId,
            url,
            rowSelector,
            rowIdAttribute,
            onUpdate,
            controller: null
        };

        this.initPolling();
    },

    /**
     * Initialize non-blocking periodic polling for database changes.
     */
    initPolling() {
        if (this.pollTimer) return;

        // Clean up immediately on page unload so navigation is instant
        window.addEventListener('beforeunload', () => this.stopAll());
        window.addEventListener('pagehide', () => this.stopAll());

        // Baseline timestamp fetch
        this.checkSync();

        // Periodic check every 3.5 seconds (only when page is visible)
        this.pollTimer = setInterval(() => {
            if (!document.hidden) {
                this.checkSync();
            }
        }, 3500);
    },

    /**
     * Non-blocking database check endpoint request (<1ms execution on server).
     */
    async checkSync() {
        if (this.isPolling) return;
        if (Object.keys(this.registrations).length === 0) return;

        this.isPolling = true;
        try {
            const res = await fetch(`api/sync_check.php?since=${this.lastMtime}`, {
                cache: 'no-store'
            });
            if (res.ok) {
                const data = await res.json();
                if (this.lastMtime > 0 && data.changed) {
                    // Instantly sync all registered components when database change detected
                    Object.keys(this.registrations).forEach(elementId => {
                        this.sync(elementId);
                    });
                }
                if (data.mtime) {
                    this.lastMtime = data.mtime;
                }
            }
        } catch (e) {
            // Silently ignore network interruptions
        } finally {
            this.isPolling = false;
        }
    },

    /**
     * Stop all synchronization and abort pending requests on unload.
     */
    stopAll() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        Object.keys(this.registrations).forEach(elementId => {
            const reg = this.registrations[elementId];
            if (reg && reg.controller) {
                try { reg.controller.abort(); } catch (e) {}
            }
        });
    },

    /**
     * Stop synchronization for a specific element.
     */
    stop(elementId) {
        const reg = this.registrations[elementId];
        if (reg) {
            if (reg.controller) reg.controller.abort();
            delete this.registrations[elementId];
        }

        if (Object.keys(this.registrations).length === 0) {
            this.stopAll();
        }
    },

    /**
     * Perform the AJAX synchronization fetch and smart diff.
     */
    async sync(elementId) {
        const reg = this.registrations[elementId];
        if (!reg) return;

        const container = document.getElementById(elementId);
        if (!container) return;

        // Skip syncing if the user is actively typing inside this specific container
        if (document.activeElement && container.contains(document.activeElement)) {
            const tagName = document.activeElement.tagName;
            if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
                return;
            }
        }

        if (reg.controller) {
            reg.controller.abort();
        }

        reg.controller = new AbortController();
        const signal = reg.controller.signal;

        try {
            const response = await fetch(reg.url, { signal });
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);

            const responseText = await response.text();
            let data = {};
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                data = { [elementId]: responseText };
            }

            for (const [targetId, newHTML] of Object.entries(data)) {
                const targetContainer = document.getElementById(targetId);
                if (targetContainer) {
                    this.applyDiff(targetContainer, newHTML, reg, targetId);
                }
            }

        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn(`[AppSync] Sync failed for #${elementId}:`, err);
            }
        } finally {
            reg.controller = null;
        }
    },

    /**
     * Diff and patch the DOM container.
     */
    applyDiff(container, newHTML, reg, targetId) {
        let hasChanges = false;

        // If it's the main registered element, use smart row-by-row diffing
        if (targetId === reg.elementId) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(`<table><tbody id="temp-root">${newHTML}</tbody></table>`, 'text/html');
            const tempRoot = doc.getElementById('temp-root');
            if (!tempRoot) return;

            const newRows = Array.from(tempRoot.querySelectorAll(reg.rowSelector));
            const oldRows = Array.from(container.querySelectorAll(reg.rowSelector));

            const oldRowMap = new Map();
            oldRows.forEach(row => {
                const id = row.getAttribute(reg.rowIdAttribute);
                if (id) oldRowMap.set(id, row);
            });

            newRows.forEach((newRow, index) => {
                const rowId = newRow.getAttribute(reg.rowIdAttribute);
                if (!rowId) return;

                const existingRow = oldRowMap.get(rowId);

                if (existingRow) {
                    const isChanged = (existingRow.innerHTML !== newRow.innerHTML) ||
                                      (existingRow.className !== newRow.className);

                    if (isChanged) {
                        // Preserve states of all checkboxes by index
                        const oldCheckboxes = existingRow.querySelectorAll('input[type="checkbox"]');
                        const checkboxCheckedStates = Array.from(oldCheckboxes).map(cb => cb.checked);
                        const hadSelectedClass = existingRow.classList.contains('selected-row');

                        existingRow.innerHTML = newRow.innerHTML;
                        Array.from(newRow.attributes).forEach(attr => {
                            existingRow.setAttribute(attr.name, attr.value);
                        });

                        // Restore checkbox states by index
                        const newCheckboxes = existingRow.querySelectorAll('input[type="checkbox"]');
                        newCheckboxes.forEach((cb, idx) => {
                            if (idx < checkboxCheckedStates.length) {
                                cb.checked = checkboxCheckedStates[idx];
                            }
                        });
                        if (hadSelectedClass) {
                            existingRow.classList.add('selected-row');
                        }

                        existingRow.classList.remove('row-pulse-highlight');
                        void existingRow.offsetWidth;
                        existingRow.classList.add('row-pulse-highlight');

                        hasChanges = true;
                    }
                    if (container.children[index] !== existingRow) {
                        container.insertBefore(existingRow, container.children[index] || null);
                    }
                } else {
                    newRow.classList.add('row-pulse-highlight');

                    if (index >= container.children.length) {
                        container.appendChild(newRow);
                    } else {
                        container.insertBefore(newRow, container.children[index] || null);
                    }
                    hasChanges = true;
                }
            });

            const newRowIds = new Set(newRows.map(r => r.getAttribute(reg.rowIdAttribute)).filter(Boolean));
            oldRows.forEach(oldRow => {
                const rowId = oldRow.getAttribute(reg.rowIdAttribute);
                if (rowId && !newRowIds.has(rowId)) {
                    oldRow.remove();
                    hasChanges = true;
                }
            });

        } else {
            // Simple DOM swap for other sections
            if (container.innerHTML !== newHTML) {
                container.innerHTML = newHTML;
                hasChanges = true;
            }
        }

        if (hasChanges && typeof reg.onUpdate === 'function') {
            reg.onUpdate();
        }
    }
};
