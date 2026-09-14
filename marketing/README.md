# Marketing Hub - IQA Warehouse Systems  https://threebestrated.com/
*Last Updated: September 5, 2026 10:52 PM*

The **Marketing Hub** is a modular enterprise web application engineered to bridge physical warehouse inventory with outbound marketing, B2B sales outreach, hardware photography, and CRM pipelines.

---

## ⚡ Key Modules

- **Dashboard** (`modules/dashboard`): Strategic overview of inventory turnover, conversion rates, and smart sales opportunities.
- **Leads & CRM** (`modules/leads`): Prospective customer tracking with bi-directional Master CRM (`customers.db`) synchronization.
- **Model Templates** (`modules/model_templates`): Standardized hardware specs, hero descriptions, photo asset tracking, and live interactive warehouse stock spreadsheet (`db/warehouse.db`, 1,211 items) with multi-header sorting and flexible search.
- **Ad Generator** (`modules/ad_generator`): Instant multi-tone copy creation (Manifest, Urgency, Social) cross-referenced with live warehouse stock levels in `labels.sqlite`.
- **Photo Bucket** (`modules/photo_bucket`): Centralized hardware photography vault with automated WebP dual-tier image processing (150x150 thumbnails, 1920px display).
- **Campaigns** (`modules/campaigns`): Multi-channel marketing initiative tracking and goal auditing.
- **Reports** (`modules/reports`): Funnel analytics, lead velocity, and inventory marketing coverage auditing.
- **Documentation Hub** (`modules/docs`): Built-in knowledge base for technical guidelines, SOPs, and system specifications.

---

## 📋 System Prerequisites

- **PHP 8.0+** with `pdo_sqlite` extension enabled.
- **PHP GD Library** (`extension=gd` in `php.ini`): Required for automated WebP image optimization and thumbnail generation.
- **Web Server**: Apache via XAMPP on Windows or Linux.

---

## 📚 Documentation & Specifications

Complete technical documentation is located in the [`docs/`](file:///c:/Users/Laptop/Desktop/WarehouseSystems-main/marketing/docs/) directory:

- [ARCHITECTURE.md](docs/ARCHITECTURE.md): Multi-database topology, directory structure, security, and design principles.
- [DEVELOPMENT_GUIDELINES.md](docs/DEVELOPMENT_GUIDELINES.md): Module standards, CSRF, RBAC, PDO prepared queries, and audit logging.
- [FEATURE_SPEC_AD_GENERATOR.md](docs/FEATURE_SPEC_AD_GENERATOR.md): Ad generator logic, tone specs, and stock filtering.
- [FEATURE_SPEC_MODEL_TEMPLATES.md](docs/FEATURE_SPEC_MODEL_TEMPLATES.md): Model template schema and photo coverage badges.
- [PROCESS_PHOTOGRAPHY.md](docs/PROCESS_PHOTOGRAPHY.md): Recommended hero shots, WebP compression specs, and GD troubleshooting.
- [PROJECT_ROADMAP.md](docs/PROJECT_ROADMAP.md): Implementation progress and future backlog.
