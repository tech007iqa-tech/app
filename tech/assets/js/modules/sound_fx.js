/**
 * ═══════════════════════════════════════════════════════════════════════════
 * MODULE: sound_fx.js
 * Hardware Bench Sound Cues & Web Audio Synthesizer
 * IQA Metal Warehouse Systems | Strict Zero-Dependency Vanilla ES6
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Future Agent Guide:
 * - Uses native window.AudioContext / webkitAudioContext.
 * - ZERO external sound assets, zero network latency, 100% offline capable.
 * - Call `TechWorkbench.Audio.playSuccess()`, `playMatch()`, `playError()`.
 * - State is persisted in localStorage: 'tech_sound_muted'.
 */

'use strict';

window.TechWorkbench = window.TechWorkbench || {};

(function(exports) {
    let audioCtx = null;
    let isMuted = localStorage.getItem('tech_sound_muted') === 'true';

    function getAudioContext() {
        if (!audioCtx) {
            const AudioClass = window.AudioContext || window.webkitAudioContext;
            if (AudioClass) {
                audioCtx = new AudioClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    const SoundFX = {
        /**
         * Initialize audio context on first user interaction to comply with browser autoplay policies.
         */
        init() {
            getAudioContext();
        },

        /**
         * Returns true if sound is currently muted by technician.
         */
        isMuted() {
            return isMuted;
        },

        /**
         * Toggles mute state and saves to localStorage.
         * @returns {boolean} New mute state
         */
        toggleMute() {
            isMuted = !isMuted;
            localStorage.setItem('tech_sound_muted', isMuted ? 'true' : 'false');
            this.updateToggleButtons();
            if (!isMuted) {
                this.playClick();
            }
            return isMuted;
        },

        /**
         * Positive two-tone chime (D5 -> A5) played on successful intake save or thermal print.
         */
        playSuccess() {
            if (isMuted) return;
            try {
                const ctx = getAudioContext();
                if (!ctx) return;

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';

                // Pitch envelope: 587.33Hz (D5) -> 880Hz (A5)
                osc.frequency.setValueAtTime(587.33, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.10);

                // Amplitude envelope: soft attack & smooth fade
                gain.gain.setValueAtTime(0.18, ctx.currentTime);
                gain.gain.linearRampToValueAtTime(0.22, ctx.currentTime + 0.08);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.32);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.32);
            } catch (e) {
                // AudioContext safely suppressed if device audio is unavailable
            }
        },

        /**
         * High chime (C6: 1046Hz) played when barcode scanner matches an existing catalog label.
         */
        playMatch() {
            if (isMuted) return;
            try {
                const ctx = getAudioContext();
                if (!ctx) return;

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';

                osc.frequency.setValueAtTime(1046.50, ctx.currentTime);

                gain.gain.setValueAtTime(0.20, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.22);
            } catch (e) {}
        },

        /**
         * Low double buzz (220Hz -> 180Hz) played on validation error, duplicate scan, or network failure.
         */
        playError() {
            if (isMuted) return;
            try {
                const ctx = getAudioContext();
                if (!ctx) return;

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';

                osc.frequency.setValueAtTime(220, ctx.currentTime);
                osc.frequency.setValueAtTime(180, ctx.currentTime + 0.12);

                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.setValueAtTime(0.01, ctx.currentTime + 0.10);
                gain.gain.setValueAtTime(0.15, ctx.currentTime + 0.12);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.28);
            } catch (e) {}
        },

        /**
         * Subtle tactile click (1200Hz, 15ms) played on button or pill tap.
         */
        playClick() {
            if (isMuted) return;
            try {
                const ctx = getAudioContext();
                if (!ctx) return;

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1200, ctx.currentTime);

                gain.gain.setValueAtTime(0.08, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.04);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.04);
            } catch (e) {}
        },

        /**
         * Syncs visual state of all sound toggle buttons across the UI.
         */
        updateToggleButtons() {
            document.querySelectorAll('.btn-sound-toggle').forEach(btn => {
                if (isMuted) {
                    btn.classList.add('muted');
                    btn.innerHTML = '<span>🔇</span> <span>Sound: OFF</span>';
                    btn.setAttribute('title', 'Click to enable audio feedback (Alt+M)');
                } else {
                    btn.classList.remove('muted');
                    btn.innerHTML = '<span>🔊</span> <span>Sound: ON</span>';
                    btn.setAttribute('title', 'Click to mute audio feedback (Alt+M)');
                }
            });
        }
    };

    // Auto-init on first click/keypress on the document
    const initAudioOnce = () => {
        SoundFX.init();
        document.removeEventListener('click', initAudioOnce);
        document.removeEventListener('keydown', initAudioOnce);
    };
    document.addEventListener('click', initAudioOnce);
    document.addEventListener('keydown', initAudioOnce);

    exports.Audio = SoundFX;
})(window.TechWorkbench);
