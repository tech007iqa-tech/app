# 🚀 Future Upgrades & Potential Enhancements

**File:** `DOCS/FUTURE_UPGRADES.md`
**Purpose:** Brainstormed features and potential upgrades for the IQA Metal project.

---

## 1. 📦 Bulk Batching Tool
- **Multi-Select Checkboxes**: Add checkboxes to `labels.php` rows for batch selection.
- **Batch API Endpoint**: Create `api/batch_update.php` for bulk status/location changes.
- **Audit Integration**: Ensure batch operations log individual entries to `audit.sqlite`.

## 2. 📊 Inventory Analytics
- **Aging Tracker**: Highlight items sitting in warehouse > 30 days.
- **Brand Distribution**: Visualize inventory breakdown by manufacturer.
- **Condition Breakdown**: Chart of Untested vs. Refurbished vs. For Parts.

## 3. 🔍 Advanced Search & Filtering
- **Saved Filters**: Let users save frequently-used filter combinations.
- **Barcode Scanning**: Integrate camera-based barcode reading for mobile intake.

## 4. 📱 Progressive Web App (PWA)
- **Offline Mode**: Cache recent inventory data for warehouse use without connectivity.
- **Home Screen Install**: Enable "Add to Home Screen" for a native app feel.

---

### ✅ Completed / Integrated
- **Thermal Printer Native Optimization:** Unified high-speed 2x1 HTML/CSS printing implemented.
- **Offline / Zero-Storage Mode:** Browser-native printing bypasses file system storage for rapid warehouse use.
- **Visual Identification Hooks:** Hardware ID anchors added to physical labels.
