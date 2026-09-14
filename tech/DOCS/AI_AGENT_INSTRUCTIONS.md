# 📦 IQA Warehouse Systems 9/5/2026 10:52 PM

## AI Agent Technical Instructions (Tech Module)

Welcome to the Technician Control Center (`/tech`) module! This document is designed to help you quickly understand the module's structure, styling, and data layer so you can immediately begin developing.

### 🗄️ Database Context
The `tech` module connects primarily to the master databases shared across the system via `core/database.php`.
- **Hardware Logs (`logs`)**: Typically stores entries when a technician tests a unit. Important metrics include Good vs. Bad testing status, timestamp, and technician ID.
- **Parts Inventory (`parts_inventory`)**: Manages the warehouse stock of components (RAM, Storage, Batteries, Tools). Contains a critical `low_stock_threshold` column that drives the UI's alert box.

### 🎨 Design System
- **Global UI Toolkit**: The module does *not* redefine core CSS components. It imports `../orders/assets/styles/components.css` and `../orders/assets/styles/style.css`.
- **Dashboard specifics**: Small custom dashboard elements (like the Daily Impact Summary widget and search bar) are handled in `assets/styles/dashboard.css`.
- Always stick to the established structural guidelines (rounded corners, soft shadows, vibrant badges) defined in the Orders Design System to ensure brand consistency.

### 🔐 Authentication & Roles
- Access is strictly governed by `core/auth.php`.
- **Role-Based Views**: The `index.php` dashboard dynamically renders different cards and summaries based on `$_SESSION['role']`.
  - *Operators/Technicians* see their personal testing statistics and can access the `logs.php` entry form.
  - *Admins* see global statistics (all testing combined) and have access to `admin_audit.php` instead of the standard hardware logs module.

### 🔍 Global Search API
- The dashboard search bar uses vanilla JavaScript `fetch()` to call `api/search_logs.php`.
- Ensure any modifications to the backend search endpoints maintain a strict JSON response signature (`{"success": true, "data": [...]}`) since the frontend relies on it for virtual UI rendering.

### 💡 Development Tips
- **Avoid Duplication**: Rely on the existing `orders` CSS.
- **Data Integrity**: When adding new fields to logs or inventory, ensure you check the underlying `Schema Guard` (if active for the DB) so migrations handle the updates gracefully.
