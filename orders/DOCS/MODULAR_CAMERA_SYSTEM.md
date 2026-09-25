# 📸 Modular Camera & Photography System Guide

## Overview
The Photography system has been completely overhauled into a centralized, decoupled architecture. All image processing, WebP optimization, EXIF rotation correction, thumbnail generation, date-partitioned storage (`YYYY/MM/`), and live webcam/device streaming are handled through a unified service and reusable UI component.

---

## 1. Backend Architecture: `core/MediaManager.php`

All photo operations are consolidated in [`orders/core/MediaManager.php`](file:///c:/xampp/htdocs/app/orders/core/MediaManager.php).

### Key Features:
- **Date Partitioning (`YYYY/MM/`)**: Organizes storage into monthly folders (e.g. `assets/location_photos/2026/09/`) to maintain instant filesystem lookups and avoid single-directory inode slowdowns.
- **Dual WebP Deliverables**:
  1. **Optimized Web View**: WebP (max 1920px width, 82% quality, ~200-300 KB).
  2. **Square Micro Thumbnail**: WebP (160x160px square crop, 75% quality, ~25 KB).
- **EXIF Auto-Orientation Fix**: Corrects sideways photos taken in portrait orientation on smartphones and tablets.
- **Raw Archive Preservation**: Automatically preserves original raw uploads in the configured archive storage driver for compliance or high-resolution re-export.
- **Cascading File Cleanup**: When deleting a photo record (`MediaManager::deletePhoto($id)`), all corresponding optimized WebP images, thumbnails, and raw archive files are safely removed from disk.

### Usage in PHP:
```php
require_once __DIR__ . '/core/MediaManager.php';

$mediaManager = new MediaManager();

// Process a file upload or base64 dataURI
$photo = $mediaManager->processUpload(
    $tmpPathOrBase64,       // File path or data:image/jpeg;base64,...
    'shelf_photo.jpg',       // Original filename
    'C-1',                   // Location / Shelf Code
    'Laptops',               // Sector
    'Layer 1 (Bottom)',      // Category
    'Erwin'                  // Username
);

// Delete photo and physical disk assets
$mediaManager->deletePhoto($photoId);
```

---

## 2. API Endpoints

### `POST orders/api/media_upload.php`
Accepts either `multipart/form-data` with `photo` file OR `application/x-www-form-urlencoded` / `POST` with `photo_base64`.

**Parameters**:
- `location_code` *(required)*: e.g. `C-1`
- `sector` *(optional)*: e.g. `Laptops` (default)
- `category` *(optional)*: e.g. `Layer 1 (Bottom)`
- `photo` *(file)* OR `photo_base64` *(data URI string)*
- `csrf_token` *(required)*

**Response JSON**:
```json
{
  "success": true,
  "message": "Photo saved successfully ✨",
  "photo": {
    "id": 142,
    "location_code": "C-1",
    "original_filename": "snap_C-1_1726500000.jpg",
    "optimized_path": "assets/location_photos/2026/09/C-1_snap_1726500000_opt.webp",
    "thumbnail_path": "assets/location_photos/2026/09/C-1_snap_1726500000_thumb.webp",
    "category": "Layer 1 (Bottom)",
    "sector": "Laptops",
    "uploaded_by": "Erwin",
    "created_at": "2026-09-16 10:50:00"
  }
}
```

### `POST orders/api/media_delete.php`
**Parameters**:
- `photo_id` *(int)*
- `csrf_token` *(string)*

---

## 3. Frontend Component: `CameraUploader`

Any existing or new view can trigger the Camera and File Uploader with a single JavaScript call.

### How to Trigger the Camera Modal:

#### Example 1: Specific Shelf Upload
```javascript
CameraUploader.open({
    locationCode: 'C-1',
    sector: 'Laptops',
    category: 'Layer 1 (Bottom)',
    defaultTab: 'camera', // 'camera' or 'file'
    onSuccess: function(photo) {
        console.log('Uploaded new photo:', photo);
        if (window.AppSync) {
            AppSync.sync('inventory-list', true);
        }
    }
});
```

#### Example 2: Zone / Multi-Location Picker
```javascript
CameraUploader.open({
    sector: 'Laptops',
    availableLocations: ['C-1', 'C-2', 'C-3', 'C-4'],
    onSuccess: function(photo) {
        if (window.AppSync) {
            AppSync.sync('inventory-list', true);
        }
    }
});
```

---

## 4. UI Capabilities

1. **Live HTML5 Camera Viewfinder (`getUserMedia`)**:
   - Streams live video from webcam, smartphone, or USB inspection camera.
   - Includes **"Switch Lens"** button to flip between front/rear cameras or USB devices.
   - **Snap Photo** shutter button with instant visual flash and freeze-frame confirmation.
   - **Retake Photo** action to discard and snap again.
2. **Drag & Drop File Upload Tab**:
   - Clean drag & drop dropzone with instant client preview.
3. **Category Chips & Dropdowns**:
   - Presets for shelf layers: `Layer 1 (Bottom)`, `Layer 2`, `Layer 3`, `Layer 4`, `Layer 5 (Top)`, `Row View / Overall View`, and `Hardware Detail`.
