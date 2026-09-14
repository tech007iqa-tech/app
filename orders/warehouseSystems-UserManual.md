# 📦 IQA Metal Warehouse Systems 9/5/2026 10:52 PM
*Last Updated: 9/5/2026 10:52 PM*

Welcome to the **IQA Metal Warehouse Systems** user manual. This guide provides comprehensive instructions on how to navigate and utilize the ecosystem for warehouse management, sales logistics, and customer relations.

---

## 📖 Table of Contents
1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Warehouse Control Center](#warehouse-control-center)
4. [CRM & Relationship Hub](#4-crm--relationship-hub)
5. [Order Builder & Checkout](#5-order-builder--checkout)
6. [Scheduling & Analytics](#6-scheduling--analytics)
7. [System Administration](#7-system-administration)

---

## 1. Introduction
**IQA Metal Warehouse Systems** is a high-performance management ecosystem designed for physical warehouse environments. It optimizes hardware intake, label logistics, and customer lifecycles through a unified, responsive dashboard.

### Key Concepts
- **Monolith System**: All modules are integrated into a single platform for seamless navigation.
- **Sector-Based Tracking**: Hardware is categorized into specific sectors (Laptops, Gaming, Desktops, Electronics) with tailored data fields.
- **Real-Time AJAX**: Most operations occur instantly without page reloads, ensuring high speed during busy operations.

---

## 2. Getting Started

### Accessing the System
1. Open your web browser and navigate to the application URL provided by your administrator.
2. Log in using your assigned credentials.
3. **Note**: Non-admin users are restricted to the Warehouse Portal, while admins have full access to all modules.

### Paper Password User System
PPP a less secure Version of PPP by GRC is implemented, so that way users are able able to remember a mumber row on a list rather than the actual password.

### The Dashboard
Upon logging in, you will see the main dashboard (`index.php`). The sidebar or navigation menu allows you to switch between the Core Modules: **Warehouse**, **Leads**, **Orders**, **Calendar**, **Trends**, and **Settings**.

---

## Warehouse Control Center
The Warehouse module (`/prod/pages/warehouse.php`) is where you manage physical stock and storage zones. And is where most the Users will live.

### Stock Intake
1. **Select Sector**: Choose the appropriate category (e.g., Laptops) to see relevant fields like CPU, RAM, and Battery status.
2. **Add Inventory**: Fill in the brand, model, and specifications.
3. **Efficiency Tools**:
   - **Clone Last Entry**: Use this to pre-fill the form with details from the previous item—perfect for processing identical units.
   - **Bulk Clipboard Import**: Copy rows from an Excel or CSV file and paste them directly into the import tool for massive batch processing.

### Zone & Location Management
- **Working Zones Grid**: The high-level view groups storage spaces into parent Working Zones (e.g., `Zone A`, `Zone B`, `Inbound`, `General`).
- **Drill-down Navigation**: Clicking a parent zone filters the dashboard to only show its specific sub-locations or shelves. Use "Back to Zones" to return.
- **Dynamic Addition**: Administrators can create new top-level Working Zones. When inside a Working Zone, new locations/shelves can be added with automatic code prefixing (e.g. `A-` for Zone A).
- **Zone Renaming**: Use the inline rename pencil icon on a shelf/location or parent zone to instantly rename it in the system.
- **Relocation**: Use the **Bulk Action Bar** to select multiple items and move them to a different zone in one click.
- **Location & Shelf Photos**: Upload photos of physical storage shelves/locations. Photos can be tagged with a category/layer (Layer 1 Bottom to Layer 5 Top, or Row/Overall View) and sector.
- **Zone Photo Gallery**: Click **View Zone Photos** when inside a parent zone to view an aggregated grid of all photographs uploaded for shelves in that zone. You can also upload new photos directly from this gallery modal.
- **Hover Zoom Preview**: Hovering over any photo thumbnail instantly displays a larger high-resolution optimized preview window.


### Label Printing
- **Thermal Labels**: Generate 2"×1" Flat XML labels directly from the inventory list. These are optimized for thermal printers and can be opened in LibreOffice.

---

## 4. CRM & Relationship Hub
Manage your sales pipeline and customer interactions in the **Leads** module (`/prod/pages/leads.php`).

### Managing Leads
- **Executive Bar**: View real-time KPIs, including active lead counts and total pipeline value.
- **Priority Call Queue**: The system automatically highlights leads that require a follow-up today based on the `Callback Date`.
- **Activity Timeline**: Log every interaction (Call, Email, Chat) to maintain a complete history of the relationship.

### Converting Leads to Customers
When a lead is ready to purchase, use the **One-Tap Conversion** button. This promotes them to a "Customer" status and redirects you to the Order Builder to start their first transaction.

---

## 5. Order Builder & Checkout
The ordering workflow is split into two phases: building the batch and finalizing the manifest.

### Phase 1: Batch Builder (`/prod/pages/new_order.php`)
- **AJAX Intake**: Add items to an order batch in real-time. The sidebar tracks total units and order ID as you work. The main spreadsheet supports inline editing, including a dedicated **Notes** column for specifications.
- **Interactive Chips**: Use pre-defined keyword chips (e.g., "Tested", "Working") to quickly fill in item descriptions.
- **Repeat Last**: Quickly add the same item configuration again with a single click.
- **Work Order AI Import**: Click "Import" and utilize the full-screen AI Batch Import Center. Upload a handwritten or printed manifest, and the AI will extract all details (including specs like RAM and Storage into the Notes column) and automatically estimate CPU values!
- **Clipboard Bulk Import**: Easily map bulk text or spreadsheet pastes. If your spreadsheet contains a column named "note" or "notes", the importer will automatically route that data into the batch item notes.

### Phase 2: Checkout Manifest (`/prod/checkout.php`)
- **Verification**: Review all items in the batch. You can edit unit prices, quantities, and descriptions inline.
- **Backdating**: If necessary, you can adjust the order date to reflect when the transaction actually occurred.
- **Transfer Order**: Move an entire order batch from one customer account to another if a mistake was made during intake.

---

## 6. Scheduling & Analytics

### Admin Calendar (`/prod/pages/calendar.php`)
- **Dual Views**: Switch between a Monthly Grid for long-term planning and a Weekly Timeline for daily tasks.
- **Auto-Sync**: Lead callback dates automatically appear as suggested tasks on your calendar.
- **Conversion Tracking**: Events are tagged as **Converted ✅** if they resulted in a sale, helping you measure outreach effectiveness.

### Historical Trends (`/prod/pages/trends.php`)
- **BI Analytics**: Visualize sales velocity, pricing curves, and hardware dominance (e.g., which CPU generations are selling fastest).
- **CPU Pricing Insights**: Click any row under the CPU Family Dominance card to open a pricing breakdown. Inspect minimum, maximum, and average prices alongside recent transaction records.
- **Order Preview**: Click a transaction code (Order ID) in the pricing dialog to instantly overlay a detailed manifest checklist and valuation.
- **Custom Queries**: Run historical reports to identify buying trends and optimize your inventory procurement.

---

## 7. System Administration
The **Settings** module (`/prod/pages/settings.php`) is reserved for system maintenance and security.

### Maintenance Tools
- **Schema Repair**: If the system behaves unexpectedly, run the "Integrity Schema Repair" to fix database structures and clear cached sessions.
- **Backup Manager**: Generate a secure ZIP archive containing all SQLite databases for off-site storage.
- **Photo Backup & Restore**: Create and download `.tar` backups of location/zone photography files and metadata, or restore them using the import utility.
- **Archive Directory Picker**: Configure the target spinning disk path for raw photo archives with an interactive folder explorer.
- **Audit Logs**: Review the system audit log to track user actions and maintain security compliance.

---
*For technical support or feature requests, please contact your system administrator.*
