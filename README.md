# 📦 IQA Metal Warehouse Systems

[![Version](https://img.shields.io/badge/version-2.5.0-green.svg)](https://github.com/)
[![Tech](https://img.shields.io/badge/Stack-Vanilla_PHP_|_SQLite_|_JS-blue.svg)](https://github.com/)
[![Tests](https://img.shields.io/badge/Tests-42_Passed_|_Zero_Dependencies-brightgreen.svg)](tests/run.php)
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
- **Real-Time AppSync**: Live DOM updates with CSRF auto-injection and zero page reloads.

### 📊 Order Manager (`/orders`)
*B2B Relationship & Batch Fulfillment*
- **CRM Hub**: Advanced lead tracking with interaction timelines and status priority. Real-time, timer-free Server-Sent Events (SSE) and Livewire-style smart DOM diffing (`AppSync`) across all workstations. Features 9-column bidirectional table sorting (`data-sort-val`), dynamic urgency badges (`🔴 Overdue`, `🟡 Due Today`, `🟢 Upcoming`), live keyword search highlighting, and 1-click UTF-8 BOM CSV exports.
- **Historical Trends & BI Analytics**: Modular tabbed workspace featuring Model Demand Velocity (Avg Price displayed before Details, buyer links, 1-click CSV export), accounting-grade ASP timeline & Monthly Valuation trend graphs (with MoM growth chips, executive KPI summary cards, reconciliation ledger footer, and 1-click CSV export), Dual-Axis Performance Model switcher (Split View vs. Dual-Axis Combo with Left/Right Y-axes), Customer Profile & Order History Intelligence Dialog with lifetime spend, units liquidated, completed orders, tenure, and recent manifest preview modals, live matrix save confirmation toasts & cell glow animations, and CPU pricing insight modals.
- **Pipeline KPI Tracker**: Accurate monthly, weekly, and yearly pipeline tracking with strict calendar boundaries and local time anchoring.
- **Batch Logistics**: Manage complex hardware orders with real-time stock allocation.
- **Warehouse Working Zones & Gates**: Nested zone mapping (e.g. Zone A, Zone B, General) with drill-down to specific locations/shelves.
- **Inventory Consolidation**: Automated deduplication and quantity merging for identical warehouse items within the same location.
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
- **Dual Access**: Standalone at `sampleWHdata/audit.html` or embedded within `orders/index.php?view=inbound`.

### ⚙️ System Setup Wizard (`/setup`)
*Modular Environment Inspector & Database Provisioner*
- **4-Step Setup Controller**: Decomposed modular architecture (`SetupController.php`, view partials, and isolated CSS/JS).
- **Environment Diagnostics**: Verifies PHP 8.1+, PDO SQLite, GD WebP, write permissions, and memory limits.
- **Preset Configurations**: 1-click configuration for Small Shop, Medium Warehouse, or Enterprise Logistics.
- **Self-Healing DB Provisioner**: Initializes and repairs all 10 databases with atomic WAL activation.

---

## 🛠️ Technology Stack

| Layer | Tech | Description |
| :--- | :--- | :--- |
| **Backend** | Vanilla PHP 8.1+ | Strictly zero-dependency procedural & service architecture. |
| **Database** | SQLite 3 (WAL mode) | 10 dedicated SQLite databases with centralized connection pooling. |
| **Real-Time Sync** | `AppSync` + `ApiResponse` | Smart DOM diffing, input focus preservation, and <3ms WAL checking. |
| **Security** | `.htaccess` + `Security.php` | Web server shield, CSRF auto-injection, and PPP passcodes. |
| **Testing** | Zero-Dependency CLI Harness | 42 automated tests executing in under 60ms (`tests/run.php`). |
| **Frontend** | Vanilla JS / CSS3 | Modern touch-first interface (min 48px targets) with HSL tokens. |
| **Documents** | Flat XML (FODT) | OpenDocument labels generated without `ZipArchive` dependency. |

---

## 📂 Project Structure

```text
├── core/                  # Unified Platform Services
│   ├── ApiResponse.php    # Clean terminating JSON responder with CSRF/auth guards
│   ├── Database.php       # Centralized connection pool for all 10 SQLite databases
│   ├── Schema.php         # Self-healing migration blueprints across 30 tables
│   ├── Security.php       # CSRF token lifecycle, PPP passcodes, and sanitizers
│   ├── Auth.php           # Role-based guards and CLI non-interactive detection
│   ├── UI.php             # Server-rendered UI components (stat cards, badges, dialogs)
│   └── Company.php        # Dynamic company branding and currency fallback
├── assets/                # Global Public Assets
│   └── js/
│       └── app_sync.js    # Universal real-time sync client & Livewire-style DOM diffing
├── setup/                 # Modular Setup & Environment Wizard
│   ├── index.php          # 22-line clean front controller
│   ├── src/SetupController.php # Backend orchestrator
│   ├── views/             # Modular step templates (1 to 4, challenge, success)
│   └── assets/            # Isolated setup styling and AJAX client
├── tests/                 # Automated Health & Regression Test Suite
│   ├── run.php            # Master CLI test runner (`php tests/run.php`)
│   ├── TestRunner.php     # Zero-dependency test harness and colorful reporter
│   ├── Assert.php         # Assertion engine (same, true, false, matches, contains)
│   ├── Unit/              # Security, CSRF, and ApiResponse unit tests
│   └── Integration/       # Database health, schema integrity, and domain invariants
├── labels/                # Module: Inventory & Rapid Thermal Label Printing
├── orders/                # Module: CRM, Batching, Warehousing & Fulfillment
├── marketing/             # Module: Marketing Campaigns, Templates & Ad Copy
├── tech/                  # Module: Technician Testing Bench & Parts Inventory
├── sampleWHdata/          # Module: AI-Powered Handwritten Intake Terminal
├── DOCS/                  # System-wide Architectural Specifications
├── GEMINI.md              # AI Agent Workspace Guidelines & Domain Rules
├── index.php              # Master Portal Landing Page
└── README.md              # This document
```

---

## ⚙️ Getting Started

### 1. Requirements
- **PHP 8.1+** (local XAMPP or production Linux/Apache).
- **SQLite3 & PDO Extensions** enabled in `php.ini`.
- **GD Extension** (for WebP photo processing).

### 2. Run Automated Verification Tests
Verify all databases, schemas, CSRF systems, and domain invariants with one command:
```powershell
php tests/run.php
# Or via XAMPP path:
& 'c:\xampp\php\php.exe' tests/run.php
```

### 3. Accessing the System
- **Main Portal**: `http://localhost/app/index.php`
- **Setup & Diagnostics**: `http://localhost/app/setup/index.php`
- **Inbound AI Terminal**: `http://localhost/app/sampleWHdata/audit.html`
- **Orders & CRM**: `http://localhost/app/orders/index.php`
- **Hardware Labels**: `http://localhost/app/labels/index.php`
- **Marketing Hub**: `http://localhost/app/marketing/index.php`
- **Technician Desk**: `http://localhost/app/tech/index.php`

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
