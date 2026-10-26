# 🗺️ Technician Control Center (`/tech`) — Implementation Roadmap
*IQA Metal Warehouse Systems | Planned Implementation: Next Session*

---

## 🎯 Primary Goal & Problem Statement
When a technician clicks any of the submission options in the Hardware Intake Workbench (`pages/logs.php`):
- `💾 Save Hardware Log` (`.btn-save-log`)
- `🖨️ Print Thermal Label` (`.btn-thermal`)
- `📄 Save & Windows ODT` (`.btn-odt`)

There is currently **no visual loading indicator** or **mutex lock**, which can lead to:
1. **Uncertainty**: The technician does not know if their click registered, prompting multiple rapid clicks.
2. **Duplicate Records**: Parallel `POST` requests to `api/add_log.php` cause multiple entries in `tech.db` (`logs` table) and inflate quantities in `labels.db` (`items` table).
3. **Scanner Double-Triggers**: Handheld barcode scanners sending trailing `Enter` keys can trigger double submissions if fired in rapid succession.

This roadmap details the exact step-by-step implementation plan to eliminate duplicate submissions and deliver immediate, high-contrast visual feedback.

---

## 📅 Implementation Phases Overview

| Phase | Milestone | Target Files | Key Deliverables | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Phase 1** | Visual Feedback & Styles | `assets/styles/logs.css` | Button morphing, `:disabled` opacity, `.spinner-inline` keyframe animation, active glow. | ✅ Completed |
| **Phase 2** | Client Mutex Lock | `pages/logs.php` | Atomic `isSubmittingIntake` lock, button disabling, dynamic status banner, error fallback. | ✅ Completed |
| **Phase 3** | Scanner Keydown Debounce | `pages/logs.php` | Barcode scanner `Enter` event throttle to prevent hardware double-firing. | ✅ Completed |
| **Phase 4** | Server-Side Idempotency | `api/add_log.php` | 5-second serial duplicate guard; reuses existing record instead of creating duplicate rows. | ✅ Completed |
| **Phase 5** | Verification & Stress Test | Browser & Terminal | Simulated 5x rapid click stress test, multi-serial lot test, CLI health test regression. | ✅ Completed |
| **Phase 6** | Modular Bench QoL Subsystem | `assets/js/modules/`, `qol_workbench.css` | Audio cues synthesizer, hotkeys, scanner guard, shelf chips, 1-click clipboard, CSV export, parts auto-deduct. | ✅ Completed |

---

## 🛠️ Step-by-Step Technical Blueprint

### Phase 1: CSS Loading States & Button Transformations (`logs.css`)

**Target File**: [`c:\xampp\htdocs\app\tech\assets\styles\logs.css`](file:///c:/xampp/htdocs/app/tech/assets/styles/logs.css)

1. **Disabled Button State**:
   - Prevent any pointer events, cursor interactions, or hover translations while a submission is in flight.
   ```css
   .intake-action-bar button:disabled {
       opacity: 0.55 !important;
       cursor: not-allowed !important;
       pointer-events: none !important;
       transform: none !important;
       box-shadow: none !important;
   }
   ```
2. **Inline Loading Spinner**:
   - Lightweight, zero-dependency CSS keyframe spinner matching button text colors.
   ```css
   .spinner-inline {
       display: inline-block;
       width: 16px;
       height: 16px;
       border: 2px solid rgba(255, 255, 255, 0.35);
       border-radius: 50%;
       border-top-color: #ffffff;
       animation: spin 0.6s linear infinite;
       vertical-align: middle;
       margin-right: 6px;
   }
   @keyframes spin {
       to { transform: rotate(360deg); }
   }
   ```
3. **Active Pulse / In-Flight Highlight**:
   - Subtle outer glow on the active button executing the submission.

---

### Phase 2: Client Mutex Lock & Lifecycle (`pages/logs.php`)

**Target File**: [`c:\xampp\htdocs\app\tech\pages\logs.php`](file:///c:/xampp/htdocs/app/tech/pages/logs.php)  
**Location**: `submitIntake(action)` (~Line 1745)

1. **Atomic Guard Lock**:
   - Declare global flag: `let isSubmittingIntake = false;`.
   - Abort immediately if `isSubmittingIntake === true`.
2. **Pre-Submission UI Transition**:
   - Lock mutex: `isSubmittingIntake = true;`.
   - Disable all buttons in `.intake-action-bar`:
     ```javascript
     const actionButtons = document.querySelectorAll('.intake-action-bar button');
     actionButtons.forEach(btn => btn.disabled = true);
     ```
   - Identify clicked button and morph text & icon:
     - `save`: `<span class="spinner-inline"></span> <span>Saving Hardware Log...</span>`
     - `thermal`: `<span class="spinner-inline"></span> <span>Saving & Printing...</span>`
     - `odt`: `<span class="spinner-inline"></span> <span>Generating ODT...</span>`
   - Set status badge in `#intakeFormMessage`:
     `⏳ Recording hardware test & updating warehouse stock...` (color: `#2563eb`).
3. **Post-Submission & `finally` Cleanup**:
   - On success: Flash green message `✅ Hardware log saved successfully!`.
   - On error: Flash red message `❌ Error: {error_details}`.
   - In `finally` block:
     - Release mutex: `isSubmittingIntake = false;`.
     - Re-enable all action buttons: `actionButtons.forEach(btn => btn.disabled = false);`.
     - Restore original button labels and SVG/emojis.

---

### Phase 3: Hardware Barcode Scanner Debounce (`pages/logs.php`)

**Target File**: [`c:\xampp\htdocs\app\tech\pages\logs.php`](file:///c:/xampp/htdocs/app/tech/pages/logs.php)  
**Location**: Serial number keydown listener (~Line 1537)

1. **Scanner Enter Key Throttle**:
   - When a handheld 2D/1D scanner scans a barcode, it fires `Enter`.
   - Add a 1000ms timestamp debounce:
     ```javascript
     let lastScannerSubmitTime = 0;
     document.getElementById('intake_serial_number').addEventListener('keydown', (e) => {
         if (e.key === 'Enter') {
             e.preventDefault();
             const now = Date.now();
             if (now - lastScannerSubmitTime < 1000 || isSubmittingIntake) {
                 return; // Ignore scanner jitter or accidental double-scan
             }
             lastScannerSubmitTime = now;
             ...
             submitIntake('thermal');
         }
     });
     ```

---

### Phase 4: Server-Side Deduplication & Idempotency Guard (`add_log.php`)

**Target File**: [`c:\xampp\htdocs\app\tech\api\add_log.php`](file:///c:/xampp/htdocs/app/tech/api/add_log.php)  
**Location**: Inside the `$batch_serials` loop (~Line 238)

1. **5-Second Duplicate Detection**:
   - Before executing `$stmt_log->execute(...)`, check if the same serial number was inserted by the same technician within the last 5 seconds:
     ```php
     if (!empty($curr_sn)) {
         $stmt_dup = $conn_tech->prepare("
             SELECT id, label_id FROM logs 
             WHERE serial_number = ? 
               AND tech_id = ? 
               AND created_at >= datetime('now', '-5 seconds')
             ORDER BY id DESC LIMIT 1
         ");
         $stmt_dup->execute([$curr_sn, $tech_id]);
         $dup = $stmt_dup->fetch(PDO::FETCH_ASSOC);

         if ($dup) {
             // Reuse existing record to prevent duplicate rows and stock inflation
             $inserted_log_ids[] = (int)$dup['id'];
             if (!empty($dup['label_id'])) {
                 $inserted_label_ids[] = (int)$dup['label_id'];
             }
             continue;
         }
     }
     ```
2. **Preventing `items.qty` Over-Incrementing**:
   - Skipping duplicate rows ensures `labels.db` does not increment quantities twice for a single physical machine.

---

### Phase 5: Verification & Quality Assurance Checklist

After implementing Phases 1–4 tomorrow, execute these specific verification tests:

- [x] **Rapid Click Stress Test**:
  - Open `http://localhost/app/tech/pages/logs.php`.
  - Enter test specs (e.g. Dell Latitude 5400).
  - Rapidly click `💾 Save Hardware Log` 5 times in under 1 second.
  - Verify that only **1** log entry appears in the logs table.
  - Verify that the button immediately disables, shows the spinner, and re-enables upon completion.
- [x] **Thermal Print Option Test**:
  - Click `🖨️ Print Thermal Label`.
  - Verify spinner displays `⏳ Saving & Printing...` and only 1 print frame/dialog is opened.
- [x] **ODT Label Option Test**:
  - Click `📄 Save & Windows ODT`.
  - Verify spinner displays `⏳ Generating ODT...` and only 1 file is written to `tech/exports/labels/`.
- [x] **Barcode Scanner Enter Test**:
  - Type a serial number and rapidly tap `Enter` twice.
  - Verify only 1 log is created.
- [x] **Automated Regression Suite**:
  - Run `C:\xampp\php\php.exe tests/run.php` to verify all 42 tests pass with 0 regressions.

---

## 🔒 Architectural Invariants Maintained
- **Vanilla Zero-Dependency**: Pure CSS keyframe spinner (no external font icons or Lottie animations).
- **Touch-First Target**: Buttons remain >= 48px height.
- **Pacific Time Boundary**: Logging timestamps continue to evaluate under `America/Los_Angeles`.
- **Database Integrity**: PDO prepared statements used across all duplicate detection queries.
