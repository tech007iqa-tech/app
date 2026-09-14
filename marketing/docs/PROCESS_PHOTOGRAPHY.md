# Standard Operating Procedure: Hardware Photography & Photo Bucket
*Last Updated: September 5, 2026 10:52 PM*

## 🎯 Objective
Accelerate hardware sales listings and multi-channel marketing campaigns by maintaining a centralized, high-performance "Photo Bucket" of reusable hardware photography across all warehouse inventory models.

---

## 📷 Recommended "Hero Shots" for Every Model

To ensure maximum buyer confidence and rapid listing turnaround, warehouse technicians and photographers should capture three standardized photo angles per hardware model:

1. **The Volume Shot** (Category: `Bulk Stock`):
   - **Visual**: A full pallet, orderly stack, or rack of multiple units.
   - **Commercial Purpose**: Proves immediate wholesale capacity and batch availability to B2B procurement teams.
2. **The Detail Shot** (Category: `Laptop` / `Workstation` / `Mini PC`):
   - **Visual**: A clean, close-up photograph of a single refurbished unit showing front/rear I/O ports and case condition.
   - **Commercial Purpose**: Demonstrates refurbishment standards, physical cleanliness, and cosmetic grade.
3. **The Spec Proof Shot** (Category: `Other`):
   - **Visual**: A clear photo of the monitor displaying the BIOS summary, UEFI system information, or OS specs.
   - **Commercial Purpose**: Eliminates buyer disputes by providing undeniable photographic proof of CPU generation, installed RAM, and storage.

---

## ⚙️ Automated Triple-Tier Processing Pipeline

All uploaded images are processed automatically through [`marketing/includes/photo_processor.php`](file:///c:/Users/Laptop/Desktop/WarehouseSystems-main/marketing/includes/photo_processor.php):

| Tier | Format | Specifications | Primary Use Case |
| :--- | :--- | :--- | :--- |
| **1. Raw Original** | Original (`.jpg`/`.png`) | Unaltered original resolution | Master archive & print reproduction |
| **2. Optimized Full** | WebP (`opt_*.webp`) | Proportional downscale (Max 1920px), 85% quality | High-DPI listing views & external ad links |
| **3. Gallery Thumbnail** | WebP (`thumb_*.webp`) | Exact 150x150px center-square crop, 75% quality | High-speed gallery grids & selection modals |

---

## 🛠️ Storage & Database Architecture

- **Physical Assets**: Saved to `marketing/assets/photo_bucket/`.
- **Database Tracking**: Recorded in the `photos` table in `marketing.db`:
  - `id`: Auto-incrementing primary key.
  - `filename`: Sanitized unique storage filename.
  - `file_path`: Path to raw file.
  - `thumbnail_path`: Path to 150x150 WebP thumbnail.
  - `optimized_path`: Path to 1920px WebP display version.
  - `model_name`: Associated hardware model (linked to `model_templates`).
  - `category`: Photographic angle type.
  - `status`: Processing state (`Ready`, `Processing`, `Error`).

---

## 🔧 Troubleshooting: GD Extension & WebP Processing

If the Photo Bucket displays an advisory that the **GD Library is missing**, image thumbnailing falls back to raw paths.

### How to Enable the PHP GD Extension in XAMPP:
1. Open the **XAMPP Control Panel**.
2. Click **Config** next to Apache and select **PHP (php.ini)**.
3. Search for `;extension=gd` (around line 930).
4. Remove the leading semicolon `;` so it reads:
   ```ini
   extension=gd
   ```
5. Save the file.
6. Click **Stop** on Apache in XAMPP, then click **Start** to reload PHP with GD support.
