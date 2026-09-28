/**
 * IQA Metal Store - Core Client Utilities
 * Handles theme toggling, image previews, and UI helpers.
 */

// Instant theme initialization
(function() {
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
})();

/**
 * Toggle between light and dark themes
 */
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
}

/**
 * Image preview handler for product cards
 */
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const card = input.closest('.card');
            if (card) {
                const img = card.querySelector('.card-img');
                if (img) img.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Image preview handler for the "Add Item" form
 */
function previewAddPhoto(input) {
    const preview = document.getElementById('add-photo-preview');
    if (!preview) return;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px; padding: 4px 8px; background: rgba(0, 102, 255, 0.08); border: 1px dashed var(--primary-color); border-radius: 4px;">
                    <img src="${e.target.result}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 4px;" alt="Preview">
                    <span style="font-size: 0.72rem; color: var(--text-color); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${file.name}</span>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
}

/**
 * Product Details Modal Controller
 */
function openProductModal(productId) {
    const scriptTag = document.getElementById('product-json-' + productId);
    if (!scriptTag) return;

    let p = null;
    try {
        p = JSON.parse(scriptTag.textContent);
    } catch (e) {
        console.error('Failed to parse product JSON', e);
        return;
    }

    const modal = document.getElementById('productDetailsModal');
    if (!modal) return;

    // Header badges
    const catBadge = document.getElementById('pdmCategoryBadge');
    if (catBadge) catBadge.textContent = p.category || 'Hardware';

    const locBadge = document.getElementById('pdmLocationBadge');
    if (locBadge) {
        if (p.location_code && p.is_warehouse) {
            locBadge.textContent = '📍 Shelf: ' + p.location_code;
            locBadge.style.display = 'inline-flex';
        } else {
            locBadge.style.display = 'none';
        }
    }

    // Hero info
    const img = document.getElementById('pdmImage');
    if (img) img.src = p.image || 'images/placeholder.svg';

    const title = document.getElementById('pdmTitle');
    if (title) title.textContent = p.title || 'Product Details';

    const price = document.getElementById('pdmPrice');
    if (price) price.textContent = '$' + parseFloat(p.price || 0).toFixed(2);

    const stock = document.getElementById('pdmStockBadge');
    if (stock) {
        stock.textContent = (p.quantity > 0) ? `Stock: ${p.quantity}` : 'In Stock';
    }

    const summary = document.getElementById('pdmSummary');
    const details = p.details || {};
    if (summary) summary.textContent = details.summary || p.description || '';

    const prodIdInput = document.getElementById('pdmProductId');
    if (prodIdInput) prodIdInput.value = p.id;

    const footerIdInput = document.getElementById('pdmFooterProductId');
    if (footerIdInput) footerIdInput.value = p.id;

    // Chips
    const chipsContainer = document.getElementById('pdmChips');
    if (chipsContainer) {
        chipsContainer.innerHTML = '';
        if (details.chips && details.chips.length > 0) {
            details.chips.forEach(c => {
                const span = document.createElement('span');
                span.className = 'chip-pill';
                span.innerHTML = `<span class="chip-icon">${c.icon}</span> <span class="chip-text">${c.text}</span>`;
                chipsContainer.appendChild(span);
            });
        }
    }

    // Specs Table
    const specsTable = document.getElementById('pdmSpecsTable');
    const specsSection = document.getElementById('pdmSpecsSection');
    if (specsTable && specsSection) {
        const tbody = specsTable.querySelector('tbody') || specsTable;
        tbody.innerHTML = '';
        const specs = details.specs || {};
        const entries = Object.entries(specs);

        if (entries.length > 0) {
            specsSection.style.display = 'block';
            entries.forEach(([k, v]) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<th>${k}</th><td>${v}</td>`;
                tbody.appendChild(tr);
            });
        } else {
            specsSection.style.display = 'none';
        }
    }

    // Condition & Diagnostics
    const condSection = document.getElementById('pdmConditionSection');
    const condBox = document.getElementById('pdmConditionBox');
    if (condSection && condBox) {
        const cond = details.condition || {};
        const condEntries = Object.entries(cond);
        if (condEntries.length > 0) {
            condSection.style.display = 'block';
            condBox.innerHTML = condEntries.map(([k, v]) => `
                <div class="condition-item" style="margin-bottom: 6px; font-size: 0.85rem; line-height: 1.45;">
                    <strong style="color: var(--accent-amber);">${k}:</strong> <span>${v}</span>
                </div>
            `).join('');
        } else {
            condSection.style.display = 'none';
        }
    }

    // Accessories
    const accSection = document.getElementById('pdmAccessoriesSection');
    const accBox = document.getElementById('pdmAccessoriesBox');
    if (accSection && accBox) {
        if (details.accessories && details.accessories.trim()) {
            accSection.style.display = 'block';
            accBox.textContent = details.accessories;
        } else {
            accSection.style.display = 'none';
        }
    }

    // Terms
    const termsBox = document.getElementById('pdmTermsBox');
    if (termsBox && details.terms) {
        termsBox.innerHTML = details.terms;
    }

    // Show modal & lock scroll
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeProductModal() {
    const modal = document.getElementById('productDetailsModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProductModal();
    }
});

