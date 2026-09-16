<?php
/**
 * Universal Camera & Photo Uploader Modal Component
 * Reusable modal for live camera snapshot capture & file upload.
 */
?>
<!-- Universal Camera & Photo Uploader Modal -->
<div id="camera-uploader-modal" class="modal-overlay no-print"
    style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.7); backdrop-filter:blur(6px); z-index:2500; align-items:center; justify-content:center; padding:15px;">
    
    <div class="card" 
        style="width:100%; max-width:520px; background:#ffffff; color:#0f172a; border-radius:24px; border:1px solid rgba(226,232,240,0.8); box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); display:flex; flex-direction:column; overflow:hidden; animation:modalIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px 14px 24px; border-bottom:1px solid #f1f5f9;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius:10px; background:#e0f2fe; color:#0284c7; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                    📸
                </div>
                <div>
                    <h3 id="cam-modal-title" style="margin:0; font-size:1.15rem; font-weight:900; color:#0f172a;">Warehouse Camera & Upload</h3>
                    <p style="margin:0; font-size:0.75rem; color:#64748b; font-weight:600;">Capture live camera inspection or upload files</p>
                </div>
            </div>
            <button type="button" onclick="CameraUploader.close()" 
                style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8; line-height:1; padding:4px 8px; border-radius:8px;"
                onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#94a3b8'">×</button>
        </div>

        <!-- Mode Tabs -->
        <div style="display:flex; background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:4px 24px 0 24px; gap:8px;">
            <button type="button" id="cam-tab-btn-camera" onclick="CameraUploader.switchTab('camera')"
                style="padding:10px 18px; font-size:0.85rem; font-weight:800; border:none; background:transparent; border-bottom:3px solid #3b82f6; color:#0f172a; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.15s;">
                <span>📷</span> Live Camera
            </button>
            <button type="button" id="cam-tab-btn-file" onclick="CameraUploader.switchTab('file')"
                style="padding:10px 18px; font-size:0.85rem; font-weight:600; border:none; background:transparent; border-bottom:3px solid transparent; color:#64748b; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.15s;">
                <span>📁</span> Upload File
            </button>
        </div>

        <!-- Body Content -->
        <div style="padding:20px 24px; display:flex; flex-direction:column; gap:16px; max-height:75vh; overflow-y:auto;">
            
            <!-- TAB 1: LIVE CAMERA VIEW -->
            <div id="cam-pane-camera">
                <div style="position:relative; width:100%; height:260px; background:#0b0f19; border-radius:16px; overflow:hidden; display:flex; align-items:center; justify-content:center; box-shadow:inset 0 2px 8px rgba(0,0,0,0.5);">
                    
                    <!-- Flash Effect Overlay -->
                    <div id="cam-flash-overlay" style="position:absolute; inset:0; background:white; opacity:0; pointer-events:none; transition:opacity 0.15s; z-index:20;"></div>

                    <!-- Live Video Element -->
                    <video id="cam-video-preview" autoplay playsinline muted 
                        style="width:100%; height:100%; object-fit:cover; display:none;"></video>

                    <!-- Snapshot Freeze Canvas Preview -->
                    <div id="cam-snapshot-container" style="display:none; width:100%; height:100%; position:relative;">
                        <canvas id="cam-canvas-snapshot" style="width:100%; height:100%; object-fit:cover; display:block;"></canvas>
                        <div style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.7); color:#22c55e; font-size:0.75rem; font-weight:800; padding:4px 10px; border-radius:20px; backdrop-filter:blur(4px); display:flex; align-items:center; gap:5px;">
                            <span>✓</span> Photo Captured
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="cam-loading" style="display:flex; flex-direction:column; align-items:center; gap:8px; color:#94a3b8; font-size:0.85rem;">
                        <div style="font-size:1.8rem; animation:spin 1s linear infinite;">⏳</div>
                        <span>Connecting to camera...</span>
                    </div>

                    <!-- Error State -->
                    <div id="cam-error" style="display:none; padding:16px; text-align:center; color:#f87171; font-size:0.85rem; font-weight:700;"></div>
                </div>

                <!-- Live Camera Controls -->
                <div id="cam-live-controls" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                    <button type="button" id="cam-switch-btn" onclick="CameraUploader.switchCamera()"
                        style="display:none; padding:8px 14px; border-radius:10px; border:1px solid #cbd5e1; background:#f8fafc; color:#475569; font-weight:800; font-size:0.75rem; cursor:pointer; align-items:center; gap:6px;">
                        <span>🔄</span> Switch Lens
                    </button>
                    <div></div>
                    <button type="button" id="cam-shutter-btn" onclick="CameraUploader.takeSnapshot()" disabled
                        style="padding:10px 24px; border-radius:30px; border:none; background:#3b82f6; color:white; font-weight:900; font-size:0.9rem; cursor:pointer; display:flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(59,130,246,0.35); transition:all 0.15s;">
                        <span style="display:inline-block; width:12px; height:12px; background:white; border-radius:50%;"></span> Snap Photo
                    </button>
                </div>

                <!-- Snapshot Retake Controls -->
                <div id="cam-snapshot-controls" style="display:none; justify-content:center; gap:12px; margin-top:12px;">
                    <button type="button" onclick="CameraUploader.retakeSnapshot()"
                        style="padding:8px 18px; border-radius:10px; border:1px solid #cbd5e1; background:#f8fafc; color:#475569; font-weight:800; font-size:0.8rem; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <span>🔄</span> Retake Photo
                    </button>
                </div>
            </div>

            <!-- TAB 2: FILE UPLOAD DROPZONE -->
            <div id="cam-pane-file" style="display:none;">
                <input type="file" id="cam-file-input" accept="image/*" style="display:none;">
                
                <div id="cam-dropzone"
                    style="border:2px dashed #cbd5e1; border-radius:16px; padding:24px 16px; text-align:center; cursor:pointer; transition:all 0.2s; background:#f8fafc; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:180px;">
                    
                    <div id="cam-dropzone-prompt">
                        <div style="font-size:2.5rem; margin-bottom:8px; opacity:0.8;">📁</div>
                        <div style="font-weight:800; color:#0f172a; font-size:0.95rem; margin-bottom:4px;">Drag & drop image here</div>
                        <div style="font-size:0.75rem; color:#64748b;">or click to browse from your computer</div>
                        <div style="font-size:0.65rem; color:#94a3b8; margin-top:8px;">Supports JPG, PNG, WEBP, GIF</div>
                    </div>

                    <img id="cam-file-preview-img" src="" alt="Selected Preview" 
                        style="display:none; max-width:100%; max-height:160px; object-fit:contain; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <div id="cam-file-info" style="display:none; font-size:0.75rem; color:#15803d; font-weight:800; margin-top:8px;"></div>
                </div>
            </div>

            <!-- Context Metadata Inputs -->
            <input type="hidden" id="cam-static-loc" value="">

            <div id="cam-loc-select-group" style="display:none;">
                <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#64748b;">
                    Shelf / Location Code
                </label>
                <select id="cam-loc-select"
                    style="width:100%; height:42px; border-radius:10px; border:1px solid #cbd5e1; padding:0 12px; font-weight:800; font-size:0.9rem; background:#ffffff; color:#0f172a;">
                </select>
            </div>

            <div>
                <label style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#64748b;">
                    Layer / Category Tag
                </label>
                <select id="cam-category-select"
                    style="width:100%; height:42px; border-radius:10px; border:1px solid #cbd5e1; padding:0 12px; font-weight:700; font-size:0.85rem; background:#ffffff; color:#0f172a;">
                    <option value="Layer 1 (Bottom)">Layer 1 (Bottom)</option>
                    <option value="Layer 2">Layer 2</option>
                    <option value="Layer 3">Layer 3</option>
                    <option value="Layer 4">Layer 4</option>
                    <option value="Layer 5 (Top)">Layer 5 (Top)</option>
                    <option value="Row View">Row / Overall View</option>
                    <option value="Hardware Detail">Hardware Detail</option>
                    <option value="General">General</option>
                </select>
            </div>

            <!-- Status Alert Box -->
            <div id="cam-status-box" style="display:none; padding:10px 14px; border-radius:10px; font-size:0.8rem; font-weight:700;"></div>

            <!-- Submit & Cancel Actions -->
            <div style="display:flex; gap:12px; margin-top:6px;">
                <button type="button" onclick="CameraUploader.close()"
                    style="flex:1; height:46px; border-radius:12px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:800; cursor:pointer; color:#64748b;">
                    Cancel
                </button>
                <button type="button" id="cam-upload-submit-btn" onclick="CameraUploader.submitUpload()"
                    style="flex:2; height:46px; border-radius:12px; border:none; background:var(--accent-color, #3b82f6); color:white; font-weight:900; font-size:0.9rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 10px rgba(59,130,246,0.25);">
                    <span>⚡</span> Upload Photo
                </button>
            </div>

        </div>
    </div>
</div>
