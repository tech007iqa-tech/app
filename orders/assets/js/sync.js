/**
 * Forwarding bridge to universal AppSync engine.
 */
if (typeof window.AppSync !== 'undefined' && typeof window.AppSync.initPolling === 'function') {
    window.AppSync.initPolling();
}
