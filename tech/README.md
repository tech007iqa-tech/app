# 📦 IQA Warehouse Systems 9/5/2026 10:52 PM

## 🛠️ Technician Control Center (`/tech`)

The Technician Control Center is the central hub for hardware testing, log management, and warehouse component inventory.

### Key Features
- **Dashboard Summary**: Real-time overview of daily test yields, calculating "Tested Today" with Good vs Bad metrics per technician.
- **Hardware Logs**: Comprehensive logging system for tested devices. Features a global search widget via `api/search_logs.php` for instantly locating historical tests by serial, make, or model.
- **Parts Inventory**: Live tracking of warehouse components (e.g. RAM, Storage, Batteries, Tools). Includes automated Low Stock Alerts prominently displayed on the dashboard for actionable reordering.
- **Admin Audit Trail**: A dedicated, searchable interface restricted to Administrator roles for tracking and auditing individual technician performance and throughput.
- **Label Generation Link**: Integrated access to the standalone label generator (`/labels`).

### Architecture
- Operates primarily on procedural PHP using the shared `Database` class for database connections (`core/database.php`).
- Relies on role-based access control (`core/auth.php`) to segment Operator and Admin interfaces.
- Employs the `Orders` module's CSS Design System (`assets/styles/components.css`) for consistent styling, ensuring uniformity across the application.
