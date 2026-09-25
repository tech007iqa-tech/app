/**
 * Universal System AJAX / API & Real-Time Sync Client (AppSync)
 * 
 * Provides rock-solid, resilient AJAX communication & Livewire-style DOM sync:
 * 1. HTTP API Client:
 *    - Automatic CSRF token discovery & header injection
 *    - Built-in configurable timeout with AbortController (default 15s)
 *    - Safe response parser (handles HTML error pages, PHP warnings, JSON parse errors gracefully)
 *    - Centralized Auth (401) and CSRF (403) handling
 *    - Clean async/await methods (get, post, put, delete, bindForm)
 *    - Abortable request controllers for debounced / live-sync inputs
 * 
 * 2. Real-Time UI Synchronization & Smart Diffing:
 *    - Event-driven & periodic lightweight sync checks (<1ms checkSync)
 *    - Row-by-row smart DOM diffing (preserves inputs, checkboxes, cursor focus, animates changes)
 *    - Instant component sync: await AppSync.sync('inventory-list')
 */

(function(window) {
    'use strict';

    const AppSync = {
        /**
         * Global Configuration
         */
        config: {
            defaultTimeoutMs: 15000,
            csrfToken: null,
            pollIntervalMs: 2500,
            onAuthExpired: () => {
                if (window.Notifications && typeof window.Notifications.error === 'function') {
                    window.Notifications.error('Your session has expired. Please refresh and log in.');
                } else if (window.IQA_Notify && typeof window.IQA_Notify.error === 'function') {
                    window.IQA_Notify.error('Your session has expired. Please refresh and log in.');
                } else {
                    alert('Your session has expired. Please refresh and log in.');
                }
            }
        },

        // Sync State
        registrations: {},
        pollTimer: null,
        lastMtime: 0,
        lastToken: null,
        isPolling: false,

        /**
         * Discovers CSRF token from DOM or meta tags
         */
        getCsrfToken() {
            if (this.config.csrfToken) {
                return this.config.csrfToken;
            }

            // 1. Meta tag
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag && metaTag.getAttribute('content')) {
                return metaTag.getAttribute('content');
            }

            // 2. Hidden input fields in current page
            const inputField = document.querySelector('input[name="csrf_token"]');
            if (inputField && inputField.value) {
                return inputField.value;
            }

            // 3. Dataset metadata
            const metaEl = document.getElementById('warehouse-metadata');
            if (metaEl && metaEl.dataset.csrf) {
                return metaEl.dataset.csrf;
            }

            return '';
        },

        /**
         * Sets the CSRF token programmatically
         */
        setCsrfToken(token) {
            this.config.csrfToken = token;
        },

        /**
         * Core Request Method
         * @param {string} url 
         * @param {object} options 
         * @returns {Promise<{success: boolean, data: any, message: string, error?: string, status: number}>}
         */
        async request(url, options = {}) {
            const {
                method = 'GET',
                headers = {},
                body = null,
                params = null,
                timeout = this.config.defaultTimeoutMs,
                signal = null,
                autoCsrf = true
            } = options;

            // Build full URL if params provided
            let finalUrl = url;
            if (params && Object.keys(params).length > 0) {
                const searchParams = new URLSearchParams();
                for (const [key, val] of Object.entries(params)) {
                    if (val !== undefined && val !== null) {
                        searchParams.append(key, val);
                    }
                }
                const queryString = searchParams.toString();
                if (queryString) {
                    finalUrl += (finalUrl.includes('?') ? '&' : '?') + queryString;
                }
            }

            // Standardize headers
            const requestHeaders = new Headers(headers);
            if (!requestHeaders.has('X-Requested-With')) {
                requestHeaders.set('X-Requested-With', 'XMLHttpRequest');
            }

            const csrf = autoCsrf ? this.getCsrfToken() : '';
            if (csrf && !requestHeaders.has('X-CSRF-Token')) {
                requestHeaders.set('X-CSRF-Token', csrf);
            }

            // Process body formatting
            let finalBody = body;
            if (body && typeof body === 'object' && !(body instanceof FormData) && !(body instanceof URLSearchParams) && !(body instanceof Blob)) {
                if (!requestHeaders.has('Content-Type')) {
                    requestHeaders.set('Content-Type', 'application/json; charset=utf-8');
                }
                // Inject csrf_token into JSON if not already present
                if (csrf && !body.csrf_token) {
                    body.csrf_token = csrf;
                }
                finalBody = JSON.stringify(body);
            } else if (body instanceof FormData && csrf && !body.has('csrf_token')) {
                body.append('csrf_token', csrf);
            }

            // Setup Timeout Controller with optional external signal combining
            const timeoutController = new AbortController();
            let timeoutId = null;
            if (timeout > 0) {
                timeoutId = setTimeout(() => {
                    timeoutController.abort(new Error(`Request timed out after ${timeout}ms`));
                }, timeout);
            }

            // Combine signals if external signal passed
            let activeSignal = timeoutController.signal;
            if (signal) {
                if (signal.aborted) {
                    timeoutController.abort(signal.reason);
                } else {
                    signal.addEventListener('abort', () => timeoutController.abort(signal.reason), { once: true });
                }
            }

            try {
                const response = await fetch(finalUrl, {
                    method: method.toUpperCase(),
                    headers: requestHeaders,
                    body: finalBody,
                    signal: activeSignal,
                    credentials: 'same-origin'
                });

                if (timeoutId) clearTimeout(timeoutId);

                // Handle HTTP 401 Unauthorized
                if (response.status === 401) {
                    this.config.onAuthExpired();
                    return {
                        success: false,
                        status: 401,
                        message: 'Session expired. Please log in again.',
                        error: 'Unauthorized'
                    };
                }

                // Handle HTTP 403 Forbidden
                if (response.status === 403) {
                    return {
                        success: false,
                        status: 403,
                        message: 'Action forbidden or invalid security token (CSRF).',
                        error: 'Forbidden'
                    };
                }

                // Parse response safely (handle JSON or HTML error leak)
                const rawText = await response.text();
                let parsedJson = null;

                if (rawText && rawText.trim().length > 0) {
                    try {
                        parsedJson = JSON.parse(rawText);
                    } catch (e) {
                        const cleanedText = rawText.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                        return {
                            success: false,
                            status: response.status,
                            raw: rawText,
                            message: cleanedText.substring(0, 300) || `Server error (${response.status})`,
                            error: 'Non-JSON response received'
                        };
                    }
                }

                if (!response.ok) {
                    const errMsg = (parsedJson && (parsedJson.error || parsedJson.message)) || `Server returned error status ${response.status}`;
                    return {
                        success: false,
                        status: response.status,
                        data: parsedJson && parsedJson.data ? parsedJson.data : parsedJson,
                        message: errMsg,
                        error: errMsg
                    };
                }

                if (parsedJson && typeof parsedJson === 'object') {
                    if (parsedJson.success === false) {
                        return {
                            success: false,
                            status: response.status,
                            data: parsedJson.data || null,
                            message: parsedJson.message || parsedJson.error || 'Operation failed',
                            error: parsedJson.error || parsedJson.message || 'Operation failed'
                        };
                    }
                    return {
                        success: true,
                        status: response.status,
                        data: parsedJson.data !== undefined ? parsedJson.data : parsedJson,
                        message: parsedJson.message || 'Success',
                        rawJson: parsedJson
                    };
                }

                return {
                    success: true,
                    status: response.status,
                    data: rawText,
                    message: 'Success'
                };

            } catch (err) {
                if (timeoutId) clearTimeout(timeoutId);

                if (err.name === 'AbortError') {
                    return {
                        success: false,
                        status: 0,
                        isAborted: true,
                        message: 'Request was cancelled or timed out.',
                        error: err.message || 'Aborted'
                    };
                }

                console.error('[AppSync Network Error]:', err);
                return {
                    success: false,
                    status: 0,
                    message: 'Network connection failed or server unreachable.',
                    error: err.message
                };
            }
        },

        get(url, params = {}, options = {}) {
            return this.request(url, { ...options, method: 'GET', params });
        },

        post(url, body = {}, options = {}) {
            return this.request(url, { ...options, method: 'POST', body });
        },

        put(url, body = {}, options = {}) {
            return this.request(url, { ...options, method: 'PUT', body });
        },

        delete(url, params = {}, options = {}) {
            return this.request(url, { ...options, method: 'DELETE', params });
        },

        createAbortable() {
            let activeController = null;

            return {
                async post(url, body = {}, options = {}) {
                    if (activeController) {
                        activeController.abort('Newer request dispatched');
                    }
                    activeController = new AbortController();
                    return await AppSync.post(url, body, {
                        ...options,
                        signal: activeController.signal
                    });
                },
                async get(url, params = {}, options = {}) {
                    if (activeController) {
                        activeController.abort('Newer request dispatched');
                    }
                    activeController = new AbortController();
                    return await AppSync.get(url, params, {
                        ...options,
                        signal: activeController.signal
                    });
                },
                abort(reason = 'Cancelled by user') {
                    if (activeController) {
                        activeController.abort(reason);
                        activeController = null;
                    }
                }
            };
        },

        bindForm(formSelector, options = {}) {
            const form = typeof formSelector === 'string' ? document.querySelector(formSelector) : formSelector;
            if (!form) {
                console.warn('[AppSync] bindForm: target form not found for', formSelector);
                return;
            }

            const {
                url = form.getAttribute('action') || window.location.href,
                onSuccess = null,
                onError = null,
                loadingText = 'Saving...',
                autoNotify = true,
                resetOnSuccess = false
            } = options;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                let originalBtnContent = '';

                if (submitBtn) {
                    originalBtnContent = submitBtn.innerHTML || submitBtn.value;
                    submitBtn.disabled = true;
                    if (submitBtn.tagName === 'INPUT') {
                        submitBtn.value = loadingText;
                    } else {
                        submitBtn.innerHTML = `<span>⏳</span> ${loadingText}`;
                    }
                }

                try {
                    const formData = new FormData(form);
                    const response = await this.post(url, formData);

                    if (response.success) {
                        const notifyEngine = window.Notifications || window.IQA_Notify;
                        if (autoNotify && notifyEngine && typeof notifyEngine.success === 'function') {
                            notifyEngine.success(response.message || 'Operation completed successfully.');
                        }
                        if (resetOnSuccess) {
                            form.reset();
                        }
                        if (typeof onSuccess === 'function') {
                            onSuccess(response, form);
                        }
                    } else {
                        const errMsg = response.message || response.error || 'Failed to submit form.';
                        const notifyEngine = window.Notifications || window.IQA_Notify;
                        if (autoNotify && notifyEngine && typeof notifyEngine.error === 'function') {
                            notifyEngine.error(errMsg);
                        }
                        if (typeof onError === 'function') {
                            onError(response, form);
                        }
                    }
                } catch (err) {
                    console.error('[AppSync Form Error]:', err);
                    const notifyEngine = window.Notifications || window.IQA_Notify;
                    if (autoNotify && notifyEngine && typeof notifyEngine.error === 'function') {
                        notifyEngine.error('An unexpected error occurred while submitting.');
                    }
                    if (typeof onError === 'function') {
                        onError({ success: false, message: err.message, error: err }, form);
                    }
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (submitBtn.tagName === 'INPUT') {
                            submitBtn.value = originalBtnContent;
                        } else {
                            submitBtn.innerHTML = originalBtnContent;
                        }
                    }
                }
            });
        },

        // =========================================================================
        // 🔄 Real-Time UI Synchronization & Live Smart Diffing Engine
        // =========================================================================

        /**
         * Register a DOM container for real-time synchronization.
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
            this.checkSync();
        },

        /**
         * Initialize non-blocking periodic polling for database changes.
         */
        initPolling() {
            if (this.pollTimer) return;

            window.addEventListener('beforeunload', () => this.stopAll());
            window.addEventListener('pagehide', () => this.stopAll());
            window.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    this.checkSync();
                }
            });
            window.addEventListener('focus', () => {
                this.checkSync();
            });

            this.checkSync();

            this.pollTimer = setInterval(() => {
                if (!document.hidden) {
                    this.checkSync();
                }
            }, this.config.pollIntervalMs);
        },

        /**
         * Non-blocking database check endpoint (<3ms on server).
         */
        async checkSync() {
            if (this.isPolling) return;
            if (Object.keys(this.registrations).length === 0) return;

            this.isPolling = true;
            try {
                const tokenParam = (this.lastToken !== null) ? `token=${encodeURIComponent(this.lastToken)}` : `token=`;
                const res = await fetch(`api/sync_check.php?${tokenParam}&_t=${Date.now()}`, {
                    cache: 'no-store'
                });
                if (res.ok) {
                    const data = await res.json();
                    if (this.lastToken !== null && data.changed) {
                        Object.keys(this.registrations).forEach(elementId => {
                            this.sync(elementId);
                        });
                    }
                    if (data.token) {
                        this.lastToken = data.token;
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
         * Stop all synchronization and timers.
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
         * Perform immediate AJAX synchronization fetch and smart diff on target element.
         * @param {string} elementId 
         * @param {boolean} forceSync Even if user is focused inside, force sync
         * @returns {Promise<boolean>} Whether changes were applied
         */
        async sync(elementId, forceSync = false) {
            const reg = this.registrations[elementId];
            if (!reg) {
                // Fallback default registration if called directly
                const fallbackContainer = document.getElementById(elementId);
                if (fallbackContainer) {
                    this.register({
                        elementId: elementId,
                        url: window.location.pathname + window.location.search + (window.location.search ? '&ajax=1' : '?ajax=1')
                    });
                } else {
                    return false;
                }
            }

            const activeReg = this.registrations[elementId];
            const container = document.getElementById(elementId);
            if (!container) return false;

            // Skip syncing if user is actively typing inside an input in this container (unless forced)
            if (!forceSync && document.activeElement && container.contains(document.activeElement)) {
                const tagName = document.activeElement.tagName;
                if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
                    return false;
                }
            }

            if (activeReg.controller) {
                activeReg.controller.abort();
            }

            activeReg.controller = new AbortController();
            const signal = activeReg.controller.signal;

            try {
                const response = await fetch(activeReg.url, { 
                    signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                
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
                        this.applyDiff(targetContainer, newHTML, activeReg, targetId);
                    }
                }
                return true;

            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.warn(`[AppSync] Sync failed for #${elementId}:`, err);
                }
                return false;
            } finally {
                activeReg.controller = null;
            }
        },

        /**
         * Diff and patch the DOM container smoothly without flickering or losing focus.
         */
        applyDiff(container, newHTML, reg, targetId) {
            let hasChanges = false;

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
                        // Check if row is currently focused
                        const isFocusedInside = document.activeElement && existingRow.contains(document.activeElement);

                        if (!isFocusedInside) {
                            const isChanged = (existingRow.innerHTML !== newRow.innerHTML) ||
                                              (existingRow.className !== newRow.className);

                            if (isChanged) {
                                const oldCheckboxes = existingRow.querySelectorAll('input[type="checkbox"]');
                                const checkboxCheckedStates = Array.from(oldCheckboxes).map(cb => cb.checked);
                                const hadSelectedClass = existingRow.classList.contains('selected-row');

                                existingRow.innerHTML = newRow.innerHTML;
                                Array.from(newRow.attributes).forEach(attr => {
                                    existingRow.setAttribute(attr.name, attr.value);
                                });

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
                if (container.innerHTML !== newHTML) {
                    container.innerHTML = newHTML;
                    if (targetId === 'photo-count-badge') {
                        const count = parseInt(newHTML) || 0;
                        container.style.backgroundColor = count > 0 ? '#10b981' : '#94a3b8';
                        const details = document.getElementById('location-photos-details') || container.closest('details');
                        if (details && count > 0) {
                            details.open = true;
                        }
                    }
                    if (targetId === 'location-photos-gallery') {
                        const cards = container.querySelectorAll('.photo-card-mini, .photo-card-mini-zone');
                        const details = document.getElementById('location-photos-details') || container.closest('details');
                        if (details && cards.length > 0) {
                            details.open = true;
                        }
                    }
                    hasChanges = true;
                }
            }

            if (hasChanges && typeof reg.onUpdate === 'function') {
                reg.onUpdate();
            }
        }
    };

    // Expose AppSync globally and attach to window
    window.AppSync = AppSync;

})(window);
