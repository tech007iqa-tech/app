# Design System & UI Guidelines

## 1. Overview
The IQA Metal Label APP avoids heavy CSS frameworks like Tailwind or Bootstrap. Everything is styled through a global `style.css` file. The goal of this UI is to look incredibly clean, professional, and accessible.

To maintain consistency throughout the app, future developers and agents must adhere to the design decisions and CSS variables outlined below.

---

## 2. CSS Variables (The Foundation)
You can see these initialized in `index.php`. All new CSS written must use these global `--var` tags instead of hardcoding colors. This makes it trivial to change themes later if needed.

```css
:root {
    /* Architecture */
    --sidebar-width: 260px;
    --header-height: 64px;
    --btn-height: 48px;          /* iPhone Human Interface Standard */

    /* Colors - Robust Light Theme */
    --bg-page: #fdfdfd;          /* Reduced glare white */
    --bg-panel: #ffffff;
    --text-main: #0f172a;        /* Deep slate for legibility */
    --text-secondary: #475569;
    --accent-color: #8cc63f;     /* Safety Green */
    --border-color: #e2e8f0;     /* Sharper borders */

    /* Layout & Spacing */
    --spacing: 16px;
    --border-radius-lg: 12px;
    --border-radius-md: 8px;
}
```

---

## 3. Typography & Basics
* **Font Family:** `--font-main` (Arial/Sans-Serif) for high legibility.
* **Headers (`h1`, `h2`, `h3`):** Use `--text-main`. Keep them crisp with a bottom margin of `0.5em` or `1em`.
* **Paragraphs & Labels (`p`, `label`):** Use `--text-secondary`.
* **Links (`a`):** Interactive color (`--link-color`), no underline by default. Underline only on `:hover`.

---

## 4. UI Components

### Panels & Cards
All major content (forms, tables, dashboard stats) should be contained within a "card" or "panel" to separate it from the page background.
```css
.panel {
    background-color: var(--bg-panel);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: var(--spacing);
    margin-bottom: var(--spacing);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Soft, premium shadow */
}
```

### Forms & Inputs
Forms need to be highly readable since this system involves precise hardware specifications.
* Inputs, Selects, and Textareas should span `100%` of their container width by default.
* Provide a solid `padding` (e.g., `10px`) so they are easy to click.
* On `:focus`, wrap the input in a ring matching the `--link-color` to clearly show active state.

```css
.form-group {
    margin-bottom: 15px;
}
input[type="text"], select {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-family: var(--font-main);
}
input[type="text"]:focus, select:focus {
    outline: none;
    border-color: var(--link-color);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}
```

### Buttons & Touch Targets
Buttons should feel tactile and adhere to the **iPhone 48px Rule**:
- **Min-Height:** All actionable elements must be at least `48px` tall.
- **Primary Actions**: Use `--text-main` background (Slate).
- **Positive Actions**: Use `--accent-color` (Safety Green).
- **Destructive Actions**: Use `#ef4444` (Red).

All buttons should have a `scale(0.98)` active state for tactile feedback and a smooth transition.

### iPhone Safe Areas
Always use `env(safe-area-inset-top)` and `env(safe-area-inset-bottom)` for fixed headers or bottom bars to ensure they aren't obscured by the physical notch or home indicator.

---

## 5. Layout (Flex & Grid)
* Instead of messy floats or inline-blocks, rely heavily on `display: flex;` and `gap` for aligning small elements (like buttons in a row or navbar links).
* Rely on `display: grid;` for laying out the dashboard numbers or structurally splitting two halves of a form.

---

## 6. Javascript Interactivity Guidelines
When an action updates data, try to avoid traditional browser POST redirect loops (`header("Location: index.php")`).
Instead:
1. Prevent default form submission.
2. Send data using Javascript `fetch()`.
3. Show a lightweight CSS spinner/loader on the button.
4. Update the DOM explicitly (e.g., change target to "Sold") or pop up a subtle notification toast upon success.
This aligns with the goal of creating a fast, snappy local Web-App.
