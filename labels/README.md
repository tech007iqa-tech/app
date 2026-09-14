# IQA Metal Inventory & Label System

## 1. Project Overview
This application is a local-network warehouse inventory tracker and hardware label printer. It was built using a "Vibe Coding" philosophy: clean, direct, and unbloated foundations that any developer or AI agent can immediately understand.

At its core, the app does two things:
1. **Inventory Tracking:** Tracks hardware units (Laptops, Gaming Consoles) in the warehouse with precise location tracking, condition grading, and full technical specs.
2. **Label Printing:** Generates high-fidelity `.odt` printable physical labels for individual hardware units via PowerShell template injection, optimized for thermal printers.

---

## 2. The Tech Stack (Strict Rules)
* **Frontend:** Vanilla HTML5, Vanilla CSS3 (Custom roots, flex/grid layouts), and Vanilla JavaScript (ES6+).
* **Backend:** PHP 8+
* **Database:** SQLite3 (Using PDO via `includes/db.php`).
* **Zero Bloat:** **NO** `node_modules`, **NO** Tailwind CSS or Bootstrap, **NO** PHP Frameworks (Laravel), and **NO** Composer packages.

### Why No Composer? (The PowerShell Injection Pattern)
This project uses **PowerShell Template Injection** for document generation. PHP generates raw `content.xml` strings and calls local PowerShell scripts to inject that XML inside a boilerplate `.odt` template. This keeps the repository lightweight and optimized for the host Windows environment.

---

## 3. Core Architecture
The system uses SQLite databases in the `/db/` directory:

1. `db/labels.sqlite`: The master inventory of every individual physical item in the warehouse.
2. `db/audit.sqlite`: System-wide audit trail tracking all hardware record changes.

*(See `ARCHITECTURE.md` for the exact schema structures).*

---

## 4. UI Philosophy
*(See `DESIGN_SYSTEM.md` for exact variables and rules).*
* The interface uses a robust light mode with high contrast for warehouse environments.
* Forms are dynamic — if a feature isn't selected, sub-options stay hidden.
* All operations use JavaScript `fetch()` to prevent full-page reloads.

---

## Agent Instructions (Read Before Coding)
If you are an AI Agent waking up in this repository:
1. **Read `ARCHITECTURE.md`** for database schemas and SQL patterns.
2. **Read `SITEMAP.md`** for the folder structure.
3. **Read `DESIGN_SYSTEM.md`** for CSS variables and UI rules.
4. **Do not install npm or composer packages.** Everything is vanilla.
5. **Use `HW_FIELDS` mapping** from `includes/hardware_mapping.php` — never hardcode field names.
