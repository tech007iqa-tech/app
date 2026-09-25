/**
 * Universal Camera & Photo Uploader Component
 * Provides live webcam/phone camera streaming, lens switching, freeze-frame snapshot capture,
 * drag-and-drop file upload, and seamless AJAX integration.
 */

const CameraUploader = (function() {
    let currentStream = null;
    let videoDevices = [];
    let currentDeviceIndex = 0;
    let capturedDataUrl = null;
    let activeConfig = {
        locationCode: '',
        sector: 'Laptops',
        category: 'Layer 1 (Bottom)',
        availableLocations: [],
        onSuccess: null
    };

    function init() {
        // Bind modal backdrop click to close
        const modal = document.getElementById('camera-uploader-modal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    close();
                }
            });
        }

        // File dropzone handlers
        const dropzone = document.getElementById('cam-dropzone');
        const fileInput = document.getElementById('cam-file-input');
        if (dropzone && fileInput) {
            dropzone.addEventListener('click', () => fileInput.click());
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--accent-color, #3b82f6)';
                dropzone.style.background = 'rgba(59, 130, 246, 0.05)';
            });
            dropzone.addEventListener('dragleave', () => {
                dropzone.style.borderColor = '#cbd5e1';
                dropzone.style.background = 'transparent';
            });
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.style.borderColor = '#cbd5e1';
                dropzone.style.background = 'transparent';
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    handleFileSelected(fileInput.files[0]);
                }
            });
            fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files.length > 0) {
                    handleFileSelected(fileInput.files[0]);
                }
            });
        }
    }

    function open(options = {}) {
        activeConfig = {
            locationCode: options.locationCode || '',
            sector: options.sector || 'Laptops',
            category: options.category || 'Layer 1 (Bottom)',
            availableLocations: options.availableLocations || [],
            onSuccess: options.onSuccess || null
        };

        const modal = document.getElementById('camera-uploader-modal');
        if (!modal) return;

        // Set title and contextual details
        const titleEl = document.getElementById('cam-modal-title');
        if (titleEl) {
            titleEl.textContent = activeConfig.locationCode 
                ? `📸 Photo for ${activeConfig.locationCode}` 
                : '📸 Warehouse Camera & Upload';
        }

        // Location selection UI
        const locSelectGroup = document.getElementById('cam-loc-select-group');
        const locSelect = document.getElementById('cam-loc-select');
        const locStaticInput = document.getElementById('cam-static-loc');

        if (activeConfig.availableLocations && activeConfig.availableLocations.length > 0) {
            if (locSelectGroup) locSelectGroup.style.display = 'block';
            if (locSelect) {
                locSelect.innerHTML = '';
                activeConfig.availableLocations.forEach(loc => {
                    const opt = document.createElement('option');
                    opt.value = loc;
                    opt.textContent = loc;
                    if (loc === activeConfig.locationCode) opt.selected = true;
                    locSelect.appendChild(opt);
                });
            }
        } else {
            if (locSelectGroup) locSelectGroup.style.display = 'none';
            if (locStaticInput) locStaticInput.value = activeConfig.locationCode;
        }

        // Category dropdown default
        const catSelect = document.getElementById('cam-category-select');
        if (catSelect) catSelect.value = activeConfig.category;

        // Reset states
        resetCameraState();
        resetFileState();
        showStatus('', '');

        modal.style.display = 'flex';

        // Default tab: Camera if supported, otherwise File
        switchTab(options.defaultTab || 'camera');
    }

    function close() {
        stopCurrentStream();
        const modal = document.getElementById('camera-uploader-modal');
        if (modal) modal.style.display = 'none';
    }

    function switchTab(tab) {
        const camPane = document.getElementById('cam-pane-camera');
        const filePane = document.getElementById('cam-pane-file');
        const camTabBtn = document.getElementById('cam-tab-btn-camera');
        const fileTabBtn = document.getElementById('cam-tab-btn-file');

        if (tab === 'camera') {
            if (camPane) camPane.style.display = 'block';
            if (filePane) filePane.style.display = 'none';
            if (camTabBtn) {
                camTabBtn.style.borderBottomColor = 'var(--accent-color, #3b82f6)';
                camTabBtn.style.color = 'var(--text-main, #0f172a)';
                camTabBtn.style.fontWeight = '800';
            }
            if (fileTabBtn) {
                fileTabBtn.style.borderBottomColor = 'transparent';
                fileTabBtn.style.color = '#64748b';
                fileTabBtn.style.fontWeight = '600';
            }
            startCamera();
        } else {
            stopCurrentStream();
            if (camPane) camPane.style.display = 'none';
            if (filePane) filePane.style.display = 'block';
            if (fileTabBtn) {
                fileTabBtn.style.borderBottomColor = 'var(--accent-color, #3b82f6)';
                fileTabBtn.style.color = 'var(--text-main, #0f172a)';
                fileTabBtn.style.fontWeight = '800';
            }
            if (camTabBtn) {
                camTabBtn.style.borderBottomColor = 'transparent';
                camTabBtn.style.color = '#64748b';
                camTabBtn.style.fontWeight = '600';
            }
        }
    }

    async function startCamera() {
        const video = document.getElementById('cam-video-preview');
        const loadingBox = document.getElementById('cam-loading');
        const errorBox = document.getElementById('cam-error');
        const shutterBtn = document.getElementById('cam-shutter-btn');
        const switchBtn = document.getElementById('cam-switch-btn');

        if (loadingBox) loadingBox.style.display = 'flex';
        if (errorBox) errorBox.style.display = 'none';
        if (shutterBtn) shutterBtn.disabled = true;

        stopCurrentStream();

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error("Live camera is not supported in this browser or requires HTTPS.");
            }

            // Enumerate devices if not yet done
            if (videoDevices.length === 0) {
                const devices = await navigator.mediaDevices.enumerateDevices();
                videoDevices = devices.filter(d => d.kind === 'videoinput');
            }

            if (switchBtn) {
                switchBtn.style.display = videoDevices.length > 1 ? 'inline-flex' : 'none';
            }

            let constraints = {
                video: {
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                    facingMode: currentDeviceIndex === 0 ? { ideal: "environment" } : { ideal: "user" }
                },
                audio: false
            };

            if (videoDevices[currentDeviceIndex] && videoDevices[currentDeviceIndex].deviceId) {
                constraints.video.deviceId = { exact: videoDevices[currentDeviceIndex].deviceId };
            }

            currentStream = await navigator.mediaDevices.getUserMedia(constraints);

            if (video) {
                video.srcObject = currentStream;
                video.style.display = 'block';
                await video.play();
            }

            if (loadingBox) loadingBox.style.display = 'none';
            if (shutterBtn) shutterBtn.disabled = false;
        } catch (err) {
            console.error("Camera access error:", err);
            if (loadingBox) loadingBox.style.display = 'none';
            if (errorBox) {
                errorBox.textContent = `📷 ${err.message || 'Unable to access camera.'}`;
                errorBox.style.display = 'block';
            }
            if (video) video.style.display = 'none';
        }
    }

    function switchCamera() {
        if (videoDevices.length <= 1) return;
        currentDeviceIndex = (currentDeviceIndex + 1) % videoDevices.length;
        startCamera();
    }

    function stopCurrentStream() {
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }
    }

    function takeSnapshot() {
        const video = document.getElementById('cam-video-preview');
        const canvas = document.getElementById('cam-canvas-snapshot');
        const snapContainer = document.getElementById('cam-snapshot-container');
        const liveControls = document.getElementById('cam-live-controls');
        const snapControls = document.getElementById('cam-snapshot-controls');

        if (!video || !canvas) return;

        canvas.width = video.videoWidth || 1280;
        canvas.height = video.videoHeight || 720;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        capturedDataUrl = canvas.toDataURL('image/jpeg', 0.92);

        // Visual flash effect
        const flashOverlay = document.getElementById('cam-flash-overlay');
        if (flashOverlay) {
            flashOverlay.style.opacity = '0.8';
            setTimeout(() => flashOverlay.style.opacity = '0', 150);
        }

        // Show freeze-frame preview
        if (video) video.style.display = 'none';
        if (snapContainer) snapContainer.style.display = 'block';
        if (liveControls) liveControls.style.display = 'none';
        if (snapControls) snapControls.style.display = 'flex';
    }

    function retakeSnapshot() {
        capturedDataUrl = null;
        const video = document.getElementById('cam-video-preview');
        const snapContainer = document.getElementById('cam-snapshot-container');
        const liveControls = document.getElementById('cam-live-controls');
        const snapControls = document.getElementById('cam-snapshot-controls');

        if (snapContainer) snapContainer.style.display = 'none';
        if (video) video.style.display = 'block';
        if (liveControls) liveControls.style.display = 'flex';
        if (snapControls) snapControls.style.display = 'none';
    }

    function resetCameraState() {
        capturedDataUrl = null;
        const video = document.getElementById('cam-video-preview');
        const snapContainer = document.getElementById('cam-snapshot-container');
        const liveControls = document.getElementById('cam-live-controls');
        const snapControls = document.getElementById('cam-snapshot-controls');

        if (snapContainer) snapContainer.style.display = 'none';
        if (video) video.style.display = 'block';
        if (liveControls) liveControls.style.display = 'flex';
        if (snapControls) snapControls.style.display = 'none';
    }

    function handleFileSelected(file) {
        if (!file) return;
        const previewImg = document.getElementById('cam-file-preview-img');
        const dropzonePrompt = document.getElementById('cam-dropzone-prompt');
        const fileInfo = document.getElementById('cam-file-info');

        const reader = new FileReader();
        reader.onload = (e) => {
            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            }
            if (dropzonePrompt) dropzonePrompt.style.display = 'none';
            if (fileInfo) {
                fileInfo.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                fileInfo.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    }

    function resetFileState() {
        const fileInput = document.getElementById('cam-file-input');
        const previewImg = document.getElementById('cam-file-preview-img');
        const dropzonePrompt = document.getElementById('cam-dropzone-prompt');
        const fileInfo = document.getElementById('cam-file-info');

        if (fileInput) fileInput.value = '';
        if (previewImg) {
            previewImg.src = '';
            previewImg.style.display = 'none';
        }
        if (dropzonePrompt) dropzonePrompt.style.display = 'block';
        if (fileInfo) fileInfo.style.display = 'none';
    }

    async function submitUpload() {
        const locSelect = document.getElementById('cam-loc-select');
        const locStatic = document.getElementById('cam-static-loc');
        const catSelect = document.getElementById('cam-category-select');
        const submitBtn = document.getElementById('cam-upload-submit-btn');
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value 
            || document.getElementById('warehouse-metadata')?.dataset.csrf || '';

        const locationCode = (locSelect && locSelect.offsetParent !== null) 
            ? locSelect.value 
            : (locStatic ? locStatic.value : activeConfig.locationCode);

        const category = catSelect ? catSelect.value : activeConfig.category;
        const sector = activeConfig.sector || 'Laptops';

        if (!locationCode) {
            showStatus('Please select a shelf / location code.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('location_code', locationCode);
        formData.append('sector', sector);
        formData.append('category', category);
        formData.append('csrf_token', csrfToken);

        const isCamTab = document.getElementById('cam-pane-camera').style.display !== 'none';

        if (isCamTab) {
            if (!capturedDataUrl) {
                showStatus('Please snap a photo before uploading.', 'error');
                return;
            }
            formData.append('photo_base64', capturedDataUrl);
            formData.append('filename', `snap_${locationCode}_${Date.now()}.jpg`);
        } else {
            const fileInput = document.getElementById('cam-file-input');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showStatus('Please select an image file to upload.', 'error');
                return;
            }
            formData.append('photo', fileInput.files[0]);
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>⏳</span> Uploading & Optimizing...';
        }
        showStatus('Processing and converting to WebP...', 'info');

        try {
            const result = await AppSync.post('api/media_upload.php', formData);

            if (result.success) {
                showStatus('✨ Photo uploaded and optimized successfully!', 'success');
                const photoData = (result.data && result.data.photo) ? result.data.photo : (result.photo || result.data);

                setTimeout(() => {
                    close();
                    if (photoData && typeof window.renderLocationPhotoCard === 'function') {
                        window.renderLocationPhotoCard(photoData);
                    }
                    if (typeof activeConfig.onSuccess === 'function') {
                        activeConfig.onSuccess(photoData);
                    }
                    const notifyEngine = window.Notifications || window.IQA_Notify;
                    if (notifyEngine && typeof notifyEngine.success === 'function') {
                        notifyEngine.success(`📸 Photo attached to ${photoData && photoData.location_code ? photoData.location_code : 'shelf'} ✨`);
                    }
                }, 350);
            } else {
                throw new Error(result.message || result.error || 'Upload failed.');
            }
        } catch (err) {
            console.error("Upload error:", err);
            showStatus(`❌ ${err.message}`, 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>⚡</span> Upload Photo';
            }
        }
    }

    function showStatus(msg, type) {
        const statusBox = document.getElementById('cam-status-box');
        if (!statusBox) return;
        if (!msg) {
            statusBox.style.display = 'none';
            return;
        }
        statusBox.style.display = 'block';
        statusBox.textContent = msg;
        if (type === 'error') {
            statusBox.style.background = '#fee2e2';
            statusBox.style.color = '#b91c1c';
            statusBox.style.border = '1px solid #fecaca';
        } else if (type === 'success') {
            statusBox.style.background = '#dcfce7';
            statusBox.style.color = '#15803d';
            statusBox.style.border = '1px solid #86efac';
        } else {
            statusBox.style.background = '#e0f2fe';
            statusBox.style.color = '#0369a1';
            statusBox.style.border = '1px solid #bae6fd';
        }
    }

    // Auto-bind on DOM load
    document.addEventListener('DOMContentLoaded', init);

    return {
        open,
        close,
        switchTab,
        switchCamera,
        takeSnapshot,
        retakeSnapshot,
        submitUpload
    };
})();

window.CameraUploader = CameraUploader;
