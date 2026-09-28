# 🎨 Storefront Components & UI Design Guide (`/store`)

This guide covers the frontend design tokens, theme implementation, reusable view components, and client-side interactions across the **IQA Metal Storefront (`iqametal.com`)**.

---

## 1. Design Tokens & Theming System

The storefront uses a modern, high-contrast design system with full support for both **Light** and **Dark** themes defined in [`store/assets/css/store.css`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/assets/css/store.css).

### CSS Variables Dictionary

| Token Name | Light Mode Value | Dark Mode Value | Usage |
|---|---|---|---|
| `--primary-color` | `#0066ff` | `#3385ff` | Primary interactive accents, primary buttons, borders |
| `--primary-dark` | `#0052cc` | `#1a75ff` | Hover states, card titles |
| `--secondary-color` | `#00f2fe` | `#00f2fe` | Cyan gradient highlights, accent badges |
| `--bg-color` | `#f8faff` | `#0b0f19` | Main page background |
| `--card-bg` | `#ffffff` | `#111827` | Product cards, modal containers |
| `--text-color` | `#1a1a1a` | `#f3f4f6` | Headings, primary body text |
| `--light-text` | `#666666` | `#9ca3af` | Secondary labels, specs descriptions, subtitles |
| `--border-color` | `rgba(0,0,0,0.08)`| `rgba(255,255,255,0.1)` | Card borders, dividers, form inputs |
| `--accent-gradient`| `linear-gradient(135deg, #0066ff, #00f2fe)` | `linear-gradient(135deg, #3385ff, #00f2fe)` | Buy buttons, logo accents |

### Zero-Flash Theme Execution
To prevent flash-of-unstyled-content (FOUC) when loading in dark mode, an inline script in [`views/header.php`](file:///c:/Users/Laptop/Documents/wh.latinospc/store/views/header.php) sets the `data-theme` attribute **immediately** before the DOM renders:

```html
<script>
(function() {
    var currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
})();
</script>
```

---

## 2. Reusable View Components

### 2.1 Global Header (`views/header.php`)
- **Logo**: Branded `IQA Metal` header with blue accent styling and "Warehouse Hardware & Electronics" tagline.
- **Category Navigation**: Quick filters for All, Laptops, Desktops, Servers, Parts.
- **Theme Switcher**: Floating button that toggles `data-theme="light|dark"` and persists choice to `localStorage`.
- **Cart Badge**: Real-time item counter connected to `Cart::getTotalQuantity()`.
- **Warehouse Stock Shortcut (Admin Only)**: Displays live count badge of available unposted warehouse units (`📦 Warehouse Stock [1,211]`) and triggers `openWarehouseModal()`.

### 2.2 Modular Product Card (`views/product_card.php`)
Renders in two distinct modes based on `$_SESSION['authenticated']`:

#### Mode A: Guest Retail View
- High-res product photography loaded via `Inventory::resolveImagePath()`.
- Protected against broken links: `onerror="this.onerror=null; this.src='images/placeholder.svg';"`.
- Verified condition badges and category sector pills.
- Tested specifications line (`Intel Core i5 | 16GB RAM | 512GB SSD`).
- Stock availability counter (`X units left in stock`).
- One-click "Add to Cart" form.

#### Mode B: Inline Admin Editable View
- Transforms into an interactive editing surface.
- **Update Photo Button**: Overlay on the product image that triggers a hidden `<input type="file">` and runs instant `previewImage(this)` on change.
- **Inline Inputs**: Editable inputs for Brand, Model, Price, Stock Quantity, and Sector.
- **Live Specs Editor**: Textarea pre-filled with raw specs/JSON.
- **Origin & Location Badges**: Visual indicator (`📦 Shelf: G2-L4` for warehouse stock vs. `🏪 Store Custom`).
- **Unpost Action**: An "Unpost" button that calls `admin_action.php?action=unpost` to retract the listing without deleting warehouse stock.

### 2.3 Add Item Component (`views/add_item.php`)
- Displayed as the very first card in admin mode.
- Top action: `⚡ Post from Warehouse Stock` button to open the warehouse modal drawer.
- Divider: `OR MANUAL CUSTOM ITEM`.
- Form inputs for Brand, Model, Specs, Price, Quantity, and Sector.
- **Instant Photo Preview**: Uses `previewAddPhoto(this)` to immediately display a thumbnail of the chosen photo before submitting.

### 2.4 Warehouse Stock Modal Drawer (`views/warehouse_modal.php`)
A slide-over drawer modal that allows browsing, searching, and publishing from real warehouse stock:
- **Header**: Live count counter, search bar, close button.
- **Sector Filter Pills**: All, Laptops, Desktops, Gaming, Servers, Parts.
- **Live Debounced Search**: 300ms debounce calling `admin_action.php?action=get_warehouse_items&search=...`.
- **Card Actions**:
  - **🚀 Quick Post**: Instant one-click posting using default attributes and a specified retail price.
  - **⚙️ Customize Details**: Expandable drawer revealing fields to adjust retail price, allocated quantity, refined title, public specs description, category sector, and **Upload Custom Store Photo (Optional)**.
- **Photo Preview in Modal**: The file input includes `previewModalCustomPhoto(this, id)`, showing a thumbnail, filename, and size before publishing.

### 2.5 Shopping Cart View (`cart.php`)
- Hydrates items from session storage (`$_SESSION['cart']`).
- Item thumbnail resolution via `Inventory::resolveImagePath()`.
- Quantity adjustment controls (`+`, `-`, and remove).
- Subtotal and total calculations.
- Order submission / Checkout simulation that calls `Inventory::reduceQuantity($id, $qty)`.

---

## 3. Client-Side JavaScript Handlers

### Instant Image Previews via `FileReader`

```javascript
// Instant preview in product card inline editor
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var card = input.closest('.card');
            if (card) {
                var img = card.querySelector('.card-img');
                if (img) img.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
```

```javascript
// Instant preview inside the warehouse customization drawer
function previewModalCustomPhoto(input, id) {
    const preview = document.getElementById('wh-photo-preview-' + id);
    if (!preview) return;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px; padding: 6px 10px; background: rgba(16, 185, 129, 0.08); border: 1px dashed #10b981; border-radius: 6px;">
                    <img src="${e.target.result}" style="width: 44px; height: 44px; object-fit: cover; border-radius: 4px;" alt="Preview">
                    <div style="font-size: 0.75rem;">
                        <strong style="color: #10b981;">✓ Custom Photo Ready</strong><br>
                        <span style="opacity: 0.8; font-size: 0.7rem;">${file.name} (${Math.round(file.size/1024)} KB)</span>
                    </div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
}
```

### Warehouse Search Debounce

```javascript
let whSearchTimer = null;
function debounceWhSearch() {
    clearTimeout(whSearchTimer);
    whSearchTimer = setTimeout(triggerWhFetch, 300);
}
```
