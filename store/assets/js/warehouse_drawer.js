/**
 * IQA Metal Store - Warehouse Drawer Module
 * Handles searching, filtering, and publishing physical warehouse inventory to the storefront.
 */

let currentWhSector = 'all';
let whSearchTimer = null;

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openWarehouseModal() {
    const modal = document.getElementById('warehouseModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

let whSessionPostedCount = 0;

function closeWarehouseModal(forceReload = false) {
    const modal = document.getElementById('warehouseModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    if (forceReload || whSessionPostedCount > 0) {
        window.location.reload();
    }
}

function setWhSector(sector, btn) {
    currentWhSector = sector.toLowerCase();
    const pills = document.querySelectorAll('#whSectorPills .wh-pill');
    pills.forEach(p => p.classList.remove('active'));
    if (btn) btn.classList.add('active');
    triggerWhFetch();
}

function debounceWhSearch() {
    clearTimeout(whSearchTimer);
    whSearchTimer = setTimeout(triggerWhFetch, 300);
}

async function triggerWhFetch() {
    const searchInput = document.getElementById('whSearchInput');
    const searchVal = (searchInput ? searchInput.value : '').trim();
    const container = document.getElementById('whGrid');
    const emptyMsg = document.getElementById('whEmptyMsg');

    if (!container) return;

    container.innerHTML = '<div style="text-align:center; padding: 2rem; color: var(--light-text); font-size: 0.9rem;">⏳ Loading warehouse inventory...</div>';
    if (emptyMsg) emptyMsg.style.display = 'none';

    try {
        const url = `admin_action.php?action=get_warehouse_items&sector=${encodeURIComponent(currentWhSector)}&search=${encodeURIComponent(searchVal)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (data.success && data.items && data.items.length > 0) {
            container.innerHTML = '';
            data.items.slice(0, 50).forEach(item => {
                container.innerHTML += renderWarehouseCard(item);
            });
            if (emptyMsg) emptyMsg.style.display = 'none';
        } else {
            container.innerHTML = '';
            if (emptyMsg) emptyMsg.style.display = 'block';
        }
    } catch (e) {
        container.innerHTML = '<div style="text-align:center; padding: 2rem; color: #ff3b30;">Failed to load items.</div>';
    }
}

function renderWarehouseCard(item) {
    const id = item.id;
    const title = escapeHtml(item.title);
    const brand = escapeHtml(item.brand || '');
    const model = escapeHtml(item.model || '');
    const category = escapeHtml(item.category || '');
    const shelf = escapeHtml(item.location_code || 'SHELF');
    const qty = parseInt(item.quantity || 0, 10);
    const price = parseFloat(item.price || 0).toFixed(2);
    const thumb = escapeHtml(item.thumb || 'images/placeholder.svg');
    const specs = escapeHtml(item.description || '');
    const rawSpecs = escapeHtml(item.raw_specs || item.description || '');

    return `
    <div class="wh-card" id="wh-card-${id}">
        <div class="wh-card-top">
            <img src="${thumb}" class="wh-card-img" onerror="this.onerror=null; this.src='images/placeholder.svg';" alt="${title}">
            <div class="wh-card-meta">
                <div style="display: flex; gap: 0.35rem; align-items: center; margin-bottom: 4px; flex-wrap: wrap;">
                    <span class="wh-loc-badge" title="Warehouse Physical Shelf">📍 ${shelf}</span>
                    <span class="wh-sec-badge">${category}</span>
                    <span class="wh-qty-badge">Stock: ${qty}</span>
                </div>
                <h4 class="wh-card-title">${title}</h4>
                <p class="wh-card-specs">${specs}</p>
            </div>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: 4px;">
            <button type="button" class="wh-customize-toggle-btn" onclick="toggleWhCustomize(${id})">
                ⚙️ Customize Details
            </button>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 0.8rem; color: var(--light-text); font-weight: 600;">$</span>
                <input type="number" id="quick-price-${id}" step="0.01" value="${price}" title="Store Selling Price" style="width: 80px; padding: 0.3rem; border: 1px solid rgba(0,0,0,0.15); border-radius: 4px; font-weight: bold; font-family: inherit; color: var(--primary-color);">
                <button type="button" class="wh-post-btn" id="btn-quick-post-${id}" onclick="submitQuickPost(${id})" style="padding: 0.4rem 0.85rem; font-size: 0.8rem;">
                    🚀 Quick Post
                </button>
            </div>
        </div>

        <form method="POST" action="admin_action.php" enctype="multipart/form-data" id="wh-config-form-${id}" class="wh-config-form" style="display: none;" onsubmit="handleWarehouseFullPost(event, ${id})">
            <input type="hidden" name="action" value="post_warehouse">
            <input type="hidden" name="id" value="${id}">

            <div class="wh-config-inner">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--primary-color); margin-bottom: 0.5rem;">
                    Decide Storefront Attributes
                </div>
                
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="flex: 1;">
                        <label class="wh-field-label">Brand</label>
                        <input type="text" name="brand" value="${brand}" class="wh-field-input" required>
                    </div>
                    <div style="flex: 1;">
                        <label class="wh-field-label">Model</label>
                        <input type="text" name="model" value="${model}" class="wh-field-input" required>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="flex: 1;">
                        <label class="wh-field-label">Store Retail Price ($)</label>
                        <input type="number" name="price" step="0.01" value="${price}" class="wh-field-input" style="color: var(--primary-color); font-weight: bold;" required>
                    </div>
                    <div style="flex: 1;">
                        <label class="wh-field-label">Units to Post (Max ${qty})</label>
                        <input type="number" name="quantity" min="1" max="${qty}" value="${qty}" class="wh-field-input" required>
                    </div>
                    <div style="flex: 1;">
                        <label class="wh-field-label">Category</label>
                        <select name="sector" class="wh-field-input">
                            <option value="Laptops" ${category.toLowerCase().includes('laptop') ? 'selected' : ''}>Laptops</option>
                            <option value="Desktops" ${category.toLowerCase().includes('desktop') ? 'selected' : ''}>Desktops</option>
                            <option value="Gaming" ${category.toLowerCase().includes('gaming') ? 'selected' : ''}>Gaming</option>
                            <option value="Servers" ${category.toLowerCase().includes('server') ? 'selected' : ''}>Servers</option>
                            <option value="Parts" ${category.toLowerCase().includes('part') ? 'selected' : ''}>Parts</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 0.5rem;">
                    <label class="wh-field-label">Public Description & Tested Specs</label>
                    <textarea name="specs_json" rows="3" class="wh-field-input" style="resize: vertical;">${rawSpecs}</textarea>
                </div>

                <div style="margin-bottom: 0.75rem;">
                    <label class="wh-field-label">Upload Custom Store Photo (Optional)</label>
                    <input type="file" name="photo" accept="image/*" style="font-size: 0.75rem;" onchange="previewModalCustomPhoto(this, '${id}')">
                    <div id="wh-photo-preview-${id}"></div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" class="wh-btn-secondary" onclick="toggleWhCustomize(${id})">Cancel</button>
                    <button type="submit" class="wh-post-btn" id="btn-custom-post-${id}">
                        ✓ Publish Custom Listing
                    </button>
                </div>
            </div>
        </form>
    </div>`;
}

function toggleWhCustomize(id) {
    const form = document.getElementById(`wh-config-form-${id}`);
    if (form) {
        form.style.display = (form.style.display === 'none') ? 'block' : 'none';
    }
}

async function submitQuickPost(id) {
    const priceInput = document.getElementById(`quick-price-${id}`);
    const btn = document.getElementById(`btn-quick-post-${id}`);
    const price = priceInput ? priceInput.value : '';

    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Posting...';
    }

    const formData = new FormData();
    formData.append('action', 'post_warehouse');
    formData.append('id', id);
    formData.append('price', price);

    try {
        const res = await fetch('admin_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            handlePostSuccess(id);
        } else {
            alert(data.error || 'Failed to post item.');
            if (btn) {
                btn.disabled = false;
                btn.innerText = '🚀 Quick Post';
            }
        }
    } catch (e) {
        alert(e.message || 'An error occurred while posting.');
        if (btn) {
            btn.disabled = false;
            btn.innerText = '🚀 Quick Post';
        }
    }
}

async function handleWarehouseFullPost(e, id) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById(`btn-custom-post-${id}`);

    if (btn) {
        btn.disabled = true;
        btn.innerText = 'Publishing...';
    }

    const formData = new FormData(form);

    try {
        const res = await fetch('admin_action.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            handlePostSuccess(id);
        } else {
            alert(data.error || 'Failed to post item.');
            if (btn) {
                btn.disabled = false;
                btn.innerText = '✓ Publish Custom Listing';
            }
        }
    } catch (err) {
        alert(err.message || 'An error occurred while publishing.');
        if (btn) {
            btn.disabled = false;
            btn.innerText = '✓ Publish Custom Listing';
        }
    }
}

function handlePostSuccess(id) {
    whSessionPostedCount++;
    const card = document.getElementById(`wh-card-${id}`);
    if (card) {
        card.style.transition = 'all 0.4s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        setTimeout(() => {
            card.remove();
            updateWhCounters();
        }, 400);
    }
}

function updateWhCounters() {
    const modalBadge = document.getElementById('whModalBadge');
    const navBadge = document.getElementById('whNavBadge');
    let current = parseInt(navBadge ? navBadge.innerText : '0', 10);
    if (current > 0) {
        current -= 1;
        if (navBadge) navBadge.innerText = current;
        if (modalBadge) modalBadge.innerText = `${current} Units Available`;
    }
}

function previewModalCustomPhoto(input, id) {
    const preview = document.getElementById(`wh-photo-preview-${id}`);
    if (!preview) return;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px; padding: 4px 8px; background: rgba(0, 102, 255, 0.08); border-radius: 4px;">
                    <img src="${e.target.result}" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px;" alt="Custom Photo">
                    <span style="font-size: 0.72rem; color: var(--primary-color);">${escapeHtml(file.name)}</span>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
}
