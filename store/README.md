# 🛍️ IQA Metal Hardware Storefront (`/store`)

The **IQA Metal Storefront** is a modular, high-performance e-commerce catalog for selling refurbished and as-is computer hardware directly from the warehouse inventory database.

It is split cleanly into two distinct actors:
1. **The Tender**: Authenticated staff/warehouse operator with inventory publishing, pricing, photo editing, and curation privileges.
2. **The User**: Public customer browsing hardware, adding items to cart, and checking out.

---

## 👥 The Two System Roles

### 🏪 1. The Tender (Staff / Privileged Operator)
- **Authentication**: Checked via `Tender::isLoggedIn()`. Sessions originate from warehouse staff authentication (`serverWarehouse/orders/core/login.php`).
- **Tender Top Bar (`views/tender/tender_bar.php`)**: A dedicated staff banner above the storefront showing tender identity, live warehouse stock count, preview toggle, and sign out.
- **Physical Stock Publishing (`views/tender/warehouse_drawer.php`)**: Slide-over drawer to search warehouse inventory and publish directly to the storefront with custom pricing.
- **Inline Editing (`views/tender/product_card_tender.php`)**: Edit title, category, description, and price inline on cards without navigating to a separate dashboard. Non-overlapping unpost and delete buttons.
- **Manual Intake (`views/tender/add_item_tender.php`)**: Card for adding custom non-warehouse items with photo upload.
- **Customer Preview (`?preview=1`)**: Allows the Tender to preview the store exactly as a public shopper sees it (hides edit cards & warehouse drawer) with a 1-click toggle back.
- **Controller (`tender_action.php`)**: Centralized endpoint for posting, unposting, adding, updating, and deleting items.

### 🛒 2. The User (Public Shopper / Customer)
- **Clean Customer Header**: The store navigation contains only the store logo, category links, cart counter, and theme toggle.
- **Public Product Cards (`views/product_card.php`)**: Polished hardware cards displaying title, specs, price, and the "Acquire" (Add to Cart) action.
- **Cart & Checkout (`cart.php`)**: Session-based cart with real-time stock deduction upon acquisition.

---

## 🧭 Directory Structure

```
store/
├── index.php                 # Storefront catalog (controller + view assembly)
├── category.php              # Category-filtered view (?cat=laptops, desktops, servers, parts)
├── cart.php                  # Session-backed shopping cart with checkout stock deduction
├── tender_action.php         # Central REST/AJAX controller for Tender actions
├── admin_action.php          # Backwards-compatible forwarder to tender_action.php
├── terms.php                 # Legal terms of sale & warranty disclaimers
│
├── core/                     # Modular Business Logic
│   ├── Tender.php            # Tender role & session authentication service
│   ├── db.php                # Database connection (centralized Database::warehouse() or fallback)
│   ├── Inventory.php         # Product querying, warehouse posting, stock deduction, self-healing
│   ├── Cart.php              # Session-based shopping cart model
│   ├── StoreImageProcessor.php # Dual-tier WebP photo optimization & thumbnail generator
│   └── UI.php                # Template helpers (escaping, currency formatting, badges)
│
├── views/                    # Clean, Focused View Partials (<80 lines each)
│   ├── header.php            # Global customer navbar & theme switch (includes tender_bar if auth)
│   ├── footer.php            # Global footer with legal links
│   ├── product_card.php      # Customer product card (delegates to tender card if Tender active)
│   ├── add_item.php          # Wrapper delegating to views/tender/add_item_tender.php
│   ├── warehouse_modal.php   # Wrapper delegating to views/tender/warehouse_drawer.php
│   └── tender/               # Dedicated Privileged Tender Views
│       ├── tender_bar.php    # Sticky staff toolbar (identity, preview toggle, warehouse drawer trigger)
│       ├── product_card_tender.php # Inline card editor with non-overlapping action toolbar
│       ├── add_item_tender.php     # Manual custom item intake card
│       └── warehouse_drawer.php   # Slide-over warehouse stock publishing drawer
│
├── assets/                   # Static Presentation Assets
│   ├── css/
│   │   ├── theme.css         # Design tokens, modern color palettes, light & dark mode variables
│   │   ├── store.css         # Grid layouts, hero, header & footer styles
│   │   └── components.css    # Cards, tender top bar, drawer modal, badges, buttons
│   └── js/
│       ├── store.js          # Core client scripts: theme toggle, image previews
│       └── warehouse_drawer.js # Warehouse drawer search, filtering, card rendering & posting
│
└── images/
    ├── placeholder.svg       # Zero-dependency SVG fallback image
    └── store/                # Vault for uploaded WebP product photography
```

---

## ⚡ The 5 Golden Rules for Future Agents

1. **Role Separation (Tender vs User)**:
   - Check `Tender::isTenderMode()` when rendering staff controls or inline forms.
   - Use `Tender::isLoggedIn()` and `Tender::isCustomerPreview()` to distinguish staff viewing public mode.
2. **Store Photos Belong in `store/images/store/`**:
   - Always upload photos through `StoreImageProcessor::processUpload($file)` to produce `opt_...webp` and `thumb_...webp`.
3. **Never Overwrite Shared Warehouse Shelf Photos**:
   - When posting warehouse stock with a custom photo, use an isolated location code (`STORE-WH-$id`) so other units on that shelf bin keep their photo intact.
4. **Never `DELETE` Physical Warehouse Records**:
   - When unposting warehouse items from the storefront, update `is_posted = 0`. Only custom store items (`user_owner = 'STORE'`) may be deleted.
5. **Keep Views and Logic Decoupled**:
   - Keep `.php` view templates concise and focused on markup. Put business logic in `core/` and browser interactions in `assets/js/`.

---

## 🛠️ How to Make Common Changes (Cheat Sheet)

### How to Add a New Category:
1. In `views/header.php`, add a link: `<a href="category.php?cat=monitors">Monitors</a>`.
2. In `views/tender/add_item_tender.php`, `views/tender/product_card_tender.php`, and `assets/js/warehouse_drawer.js`, add `<option value="Monitors">Monitors</option>`.

### How to Add a New Tender Feature (e.g. Bulk Price Discounting):
1. In `core/Tender.php`: check permissions with `Tender::isAdmin()`.
2. In `tender_action.php`: add the action case `bulk_discount`.
3. In `views/tender/tender_bar.php`: add the button trigger to the tender bar.

### How to Test Changes:
Run the PHP CLI syntax checks:
```powershell
php -l store/index.php
php -l store/category.php
php -l store/cart.php
php -l store/tender_action.php
php -l store/admin_action.php
php -l store/core/Tender.php
php -l store/core/Inventory.php
```
Run `php -f store/index.php` to verify error-free execution.

