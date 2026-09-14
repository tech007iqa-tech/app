# 📦 IQA Warehouse Systems 9/5/2026 10:52 PM

[![Version](https://img.shields.io/badge/version-2.2.0-green.svg)](https://github.com/)
[![Tech](https://img.shields.io/badge/Stack-Vanilla_PHP_|_SQLite_|_JS-blue.svg)](https://github.com/)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/))

A premium, high-performance warehouse management and sales logistics ecosystem designed for speed, reliability, and precision. Optimized for physical warehouse environments where quick hardware intake, accurate label logistics, and customer relation lifecycles are mission-critical.

---

## 🚀 The Core Modules

All modules are unified under a single responsive dashboard in `index.php`. Access controls restrict non-admin users to the Warehouse Portal.

### 🏬 Warehouse Control Center (`orders/pages/warehouse.php`)
*Physical Stock Logistics & Density Tracking*
- **Sector Specifications**: Tailored data entry attributes based on hardware categories (Laptops, Gaming, Desktops, Electronics).
- **Nested Working Zones & Locations**: High-level grid grouping shelves/areas into parent Working Zones (e.g., `Zone A`, `Zone B`, `Inbound`). Supports drill-down navigation to view and manage sub-locations/shelves.
- **Dynamic Add & Rename**: Add new working zones or sub-locations and rename existing ones via inline inputs.
- **Zone Status Logic**: Operational states like `Working`, `Audit`, `Shipping`, `In-Review`, `Warehoused`, or `Idle`.
- **Intake Optimizations**: Clone Last Entry, Bulk Clipboard Import.
- **Labeling Integration**: Generation of 2"×1" Flat XML labels for physical thermal printing.

### 📥 Inbound Intake Terminal (`orders/pages/inbound.php`)
*AI-Powered Handwritten Sheet Digitization*
- Embeds the [Audit & Normalization Terminal](../sampleWHdata/README.md) via a seamless full-screen iframe.
- Provides access to AI OCR extraction, Manual Grid Overlay, image pan/zoom/rotate, and the full intake workflow without leaving the main system.
- The terminal is also fully usable offline/standalone at `sampleWHdata/audit.html`.

### 📊 Order Builder & Checkout Manifest (`orders/pages/new_order.php` & `checkout.php`)
*B2B Batch Intake & Order Verification*
- **Batch Builder UI**: Real-time AJAX-powered intake of customer order items with inline editing, batch counts, and instant totals.
- **Final Checkout**: Verification view showing searchable item details, editable unit prices, editable order dates, and total balances.
- **Interactive Modals**: Instant AJAX metadata editing of manifest rows and order ownership transfer between client accounts.

### 🎯 CRM & Relationship Hub (`orders/pages/leads.php`)
*Outreach Pipeline & Lead Status Tracking*
- **Executive Bar**: High-level real-time KPI overview showing Active Leads count and overall Pipeline Gross Value.
- **Priority Call Queue**: Highlights critical accounts needing callbacks today.
- **Activity Timeline**: Vertical stream mapping interactions (📞 Call, 📧 Email, 💬 Chat).
- **Real-Time SSE Sync**: Server-Sent Events database change stream synchronizing changes across all workstations under 500ms.
- **One-Tap Conversion**: Promotes high-priority leads to customers and automatically redirects to a fresh order batch intake sheet.

### 📅 Admin Calendar (`orders/pages/calendar.php`)
*Scheduler & Lead Management Calendar*
- **Dual Layouts**: Monthly Grid and Weekly Timeline (Mon-Fri) views.
- **Auto Sync**: Integrates leads' callback dates as "Suggested Tasks."
- **Conversion Badging**: Tags calendar events as **Converted ✅** or **Window Shopping 👀** based on sales correlation.

### 📈 Historical Trends Engine (`orders/pages/trends.php`)
*Business Intelligence Analytics*
- **BI Charts**: Uncapped historical queries parsing sales velocities, pricing curves, GPU/CPU generation dominance, and customer buying trends.
- **CPU Pricing Details**: Interactive modal detailing price ranges, averages, and transaction history for CPU lines.
- **Order Preview Manifest**: Interactive inline modal allowing users to preview full order manifests from transaction records.

### ⚙️ System Settings & Tools (`orders/pages/settings.php`)
*Administrative Control Panel*
- **Integrity Schema Repair**: Clears cached session structures and runs diagnostic checkups/repairs on all DB structures.
- **Backup Tool**: Stream-based archiving packages all SQLite databases into a secure ZIP file.
- **Audit Logs**: Secure viewer exposing recent records from the centralized system audit log.

---

## 🛠️ Technology Stack

| Layer | Tech | Description |
| :--- | :--- | :--- |
| **Backend** | PHP 8.1+ | Clean, procedural-routing engine, object-oriented database layers. |
| **Database** | SQLite 3 | Zero-configuration database storage with WAL journaling, optimistic locking, and busy-timeouts. |
| **Frontend** | Vanilla JS / CSS3 | Modern interface built on HSL variables, glassmorphic styles, and layout animations. |
| **AI / OCR** | Gemini Vision API | Handwritten intake sheet image extraction and field normalization. |
| **Documents** | Flat XML (FODT) | XML-based OpenDocument tags for LibreOffice-compatible label printing. |

---

## ⚙️ Getting Started & Setup

### 1. Pre-requisites
- **PHP 8.1+** with the `sqlite3` and `pdo_sqlite` extensions enabled in `php.ini`.
- **Apache/Nginx** with `.htaccess` support enabled (AllowOverride All).
- **LibreOffice** (optional) for viewing/printing Flat XML `.odt` labels.
- **Gemini API Key** (optional, for AI-powered OCR intake in the Inbound Terminal).

### 2. Project Installation
1. Move the project directory to your webserver document root (e.g., `C:/xampp/htdocs/WarehouseSystems-main`).
2. Set **Write Permissions** on the `db/` directory.
3. Open the application in your browser: `http://localhost/WarehouseSystems-main/orders/index.php`
4. Databases and tables will initialize automatically on first load via the **Schema Guard** self-healing logic.

---

## 🔍 Internal Guidelines

If you are an AI assistant or human programmer modifying this project, consult the following documents inside `DOCS/` before editing:
- [🗺️ Global Sitemap](DOCS/GLOBAL_SITEMAP.md): Complete directory map of all components.
- [🤖 AI Agent Instructions](DOCS/AI_AGENT_INSTRUCTIONS.md): Coding guidelines, routing patterns, and optimization guidelines.
- [🧠 Technical Deep Dive](DOCS/AI_TECHNICAL_DEEP_DIVE.md): Detailed information on database tables, schema guard migrations, and label template XML.
- [🔍 Reviewer Checklist](DOCS/CODE_REVIEW_CHECKLIST.md): Quality control gates, security checks, and code hygiene rules.
