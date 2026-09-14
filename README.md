# 📦 IQA Metal Warehouse Systems

[![Version](https://img.shields.io/badge/version-2.2.0-green.svg)](https://github.com/)
[![Tech](https://img.shields.io/badge/Stack-Vanilla_PHP_|_SQLite_|_JS-blue.svg)](https://github.com/)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/)

A premium, high-performance warehouse management ecosystem designed for speed, reliability, and precision. Built for physical warehouse environments where quick hardware intake, accurate label logistics, and customer relation lifecycles are mission-critical.

---

## 🚀 The Modules

### 📢 Marketing Hub (`/marketing`)
*Lead Generation, Model Templates & Multi-Channel Campaigns*
- **Model Templates & Stock Spreadsheet**: Live interactive spreadsheet of warehouse stock (`db/warehouse.db`) with multi-header bidirectional sorting, flexible multi-term search, dynamic All-rows display, and one-click template prefill.
- **Ad Generator**: Multi-channel marketing copy generator (OfferUp, Facebook Marketplace, Craigslist) with customizable presets.
- **Lead Capture**: Seamless CRM integration capturing incoming inquiries directly into Master CRM (`customers.db`).
- **Photo Bucket & Assets**: Centralized asset library organized by categories and model tags.
- **Documentation Hub (`/marketing/?page=docs`)**: Integrated native Markdown knowledge base reader with responsive dual-pane layout.

### 🏷️ Inventory Labels (`/labels`)
*Rapid Hardware Intake & ODT Generation*
- **Speed Intake**: Optimized forms for rapid technical specs entry.
- **Thermal Printing**: Generates high-fidelity `.odt` labels via a dependency-free Flat XML engine.
- **Hardware Specs**: Detailed tracking of CPUs, RAM, Storage, Battery Health, and BIOS status.
- **Self-Healing**: Native `Schema Guard` ensures database integrity and automatic recovery.

### 📊 Order Manager (`/orders`)
*B2B Relationship & Batch Fulfillment*
- **CRM Hub**: Advanced lead tracking with interaction timelines and status priority. Real-time, timer-free Server-Sent Events (SSE) synchronization across all workstations.
- **Batch Logistics**: Manage complex hardware orders with real-time stock allocation.
- **Warehouse Working Zones & Gates**: Nested zone mapping (e.g. Zone A, Zone B, General) with drill-down to specific locations/shelves.
- **Inventory Consolidation**: Automated deduplication and quantity merging for identical warehouse items within the same location.
- **CPU Pricing Insights**: Interactive pricing details modal with CPU model averages, ranges, and linked transaction logs.
- **Global Registry**: Searchable customer database with session-persistent filters.

### 🛠️ Technician Control Center (`/tech`)
*Hardware Testing & Technician Audits*
- **Test Logs**: Track daily throughput with detailed good/bad unit tracking per technician.
- **Parts Inventory**: Live management of warehouse components (RAM, Storage) with low-stock alerts.
- **Admin Audit Trail**: Searchable, centralized logs for evaluating individual technician performance and throughput.

### 📥 Inbound Intake Terminal (`/sampleWHdata`)
*AI-Powered Handwritten Sheet Digitization — Offline-capable & System-integrated*
- **AI OCR Extraction**: Sends handwritten intake sheet images to the **Gemini Vision API** and extracts structured tabular data (Date, QTY, Item, Serial, Location, Notes).
- **Manual Grid Overlay**: Transparent grid overlay mode for manually keying in data directly over the image.
- **Configurable AI Prompt**: Dictionary Settings panel for managing Gemini API Key, AI persona, brand abbreviation mappings, and handwriting normalization rules.
- **Committed History View**: Hierarchical location breadcrumbs (group by shelf letter → drill down to bin), full-text search, sortable columns, and CSV export.
- **Database Management**: Admin module in settings to permanently clear committed intake records.
- **Dual Access**: Accessible standalone at `sampleWHdata/audit.html` (offline) or embedded within the main system at `orders/index.php?view=inbound` with seamless parent-window navigation escaping.

---

## 🛠️ Technology Stack

| Layer | Tech | Description |
| :--- | :--- | :--- |
| **Backend** | PHP 8.1+ | Lean, procedural-focused logic with modular routing. |
| **Database** | SQLite 3 | Zero-config, portable database files with optimistic locking. |
| **Frontend** | Vanilla JS / CSS3 | Modern "App-like" experience using Glassmorphism & HSL variables. |
| **AI / OCR** | Gemini Vision API | Handwritten intake sheet image extraction and field normalization. |
| **Documents** | Flat XML (FODT) | Dependency-free OpenDocument generation for LibreOffice compatibility. |
| **Automation** | PowerShell | Native Windows integration for direct file launching. |

---

## 📂 Project Structure

```text
├── labels/                # Module: Inventory & Rapid Label Printing
├── orders/                # Module: CRM, Batching & Fulfillment
│   └── pages/
│       └── inbound.php    # Embeds sampleWHdata/audit.html via seamless iframe
├── sampleWHdata/          # Module: Offline-capable AI Intake Terminal
│   ├── audit.html         # Main intake terminal UI
│   ├── history.html       # Committed intake log with location breadcrumbs
│   ├── settings.html      # Gemini API config, prompt settings & DB management
│   ├── process.php        # Backend API router (OCR, save, config, clear)
│   └── src/               # PHP classes (OcrEngine, Normalizer, DbHandler, Config)
├── DOCS/                  # System-wide Documentation
│   ├── AI_AGENT_INSTRUCTIONS.md   # Guidelines for AI coding assistants
│   ├── CODE_REVIEW_CHECKLIST.md   # Quality control standards
│   └── GLOBAL_SITEMAP.md          # Full project directory map
├── index.php              # Premium Portal / Landing Page
└── README.md              # This document
```

---

## ⚙️ Getting Started

### 1. Requirements
- **PHP 8.1+** (XAMPP / WAMP recommended for Windows environments).
- **SQLite3 Extension** enabled in `php.ini`.
- **LibreOffice** (optional, for viewing/printing generated `.odt` labels).
- **Gemini API Key** (optional, required for AI OCR in the Inbound Terminal).

### 2. Installation
1. Clone the repository into your web root (e.g., `htdocs/WarehouseSystems-main`).
2. Ensure the `/db` directory has **Write Permissions**.
3. Access the system via `http://localhost/WarehouseSystems-main/orders/index.php`.
4. For the standalone intake terminal: `http://localhost/WarehouseSystems-main/sampleWHdata/audit.html`.

### 3. Usage
- Start in the **Portal** to navigate between label generation or order management.
- Databases are automatically initialized on the first run via the **Schema Guard** system.
- For AI-powered intake, navigate to **Inbound** from the sidebar, or open `audit.html` directly for offline use.

---

## 🔍 Documentation for Reviewers
If you are an AI assistant or a human code reviewer, please consult the following:
- [🤖 AI Agent Instructions](DOCS/AI_AGENT_INSTRUCTIONS.md)
- [🔍 Reviewer Checklist](DOCS/CODE_REVIEW_CHECKLIST.md)
- [🗺️ Global Sitemap](DOCS/GLOBAL_SITEMAP.md)

---

> [!TIP]
> Built for durability. Every interaction is audited, every database is self-healing, and every UI element is touch-optimized for warehouse hardware.

© 2026 IQA Metal Warehouse Systems
