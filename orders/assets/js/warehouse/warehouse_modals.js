/**
 * Warehouse Modals & UI Interactions Module
 * Handles Working Zone and Shelf renaming dialogs, status creation, sticky scroll sync, and photo hover previews.
 */

function openRenameWorkingZoneModal(wzData) {
    const name = wzData.name;
    const oldInput = document.getElementById('rename-old-zone-name');
    const deleteInput = document.getElementById('delete-working-zone-name');
    const newInput = document.getElementById('rename-new-zone-name');
    const modal = document.getElementById('rename-working-zone-modal');

    if (oldInput) oldInput.value = name;
    if (deleteInput) deleteInput.value = name;
    if (newInput) newInput.value = name;

    if (modal) modal.style.display = 'flex';
    if (newInput) newInput.focus();
}

function closeRenameWorkingZoneModal() {
    const modal = document.getElementById('rename-working-zone-modal');
    if (modal) modal.style.display = 'none';
}

async function submitRenameWorkingZoneAjax(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const oldLoc = formData.get('old_zone_name');
    const newLoc = formData.get('new_zone_name');
    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Updating...';
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok || response.redirected) {
            const url = new URL(window.location.href);
            if (url.searchParams.get('zone') === oldLoc) {
                url.searchParams.set('zone', newLoc);
                window.location.href = url.toString();
            } else {
                window.location.reload();
            }
        } else {
            alert("Failed to update.");
        }
    } catch (err) {
        console.error(err);
        alert("An error occurred.");
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Update Zone';
        }
    }
}

function openRenameModal(locData) {
    const loc = locData.location_code;
    const status = locData.status;

    const oldLocInput = document.getElementById('rename-old-loc');
    const deleteLocInput = document.getElementById('delete-zone-loc');
    const newLocInput = document.getElementById('rename-new-loc');
    const statusSelect = document.getElementById('rename-status');
    const modal = document.getElementById('rename-modal');

    if (oldLocInput) oldLocInput.value = loc;
    if (deleteLocInput) deleteLocInput.value = loc;
    if (newLocInput) newLocInput.value = loc;
    if (statusSelect) {
        const tempOpt = statusSelect.querySelector('option[data-temp-custom="true"]');
        if (tempOpt) tempOpt.remove();

        statusSelect.value = status;
        if (status && statusSelect.value !== status) {
            const opt = document.createElement('option');
            opt.value = status;
            opt.textContent = `${status} (Current Custom)`;
            opt.setAttribute('data-temp-custom', 'true');
            statusSelect.appendChild(opt);
            statusSelect.value = status;
        }
    }

    if (modal) modal.style.display = 'flex';
    if (newLocInput) newLocInput.focus();
}

function closeRenameModal() {
    const modal = document.getElementById('rename-modal');
    const statusBlock = document.getElementById('manage-statuses-block');
    if (modal) modal.style.display = 'none';
    if (statusBlock) statusBlock.style.display = 'none';
}

async function submitRenameZoneAjax(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const oldLoc = formData.get('old_loc');
    const newLoc = formData.get('new_loc');
    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Updating...';
    }

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        if (response.ok || response.redirected) {
            const url = new URL(window.location.href);
            if (url.searchParams.get('loc') === oldLoc) {
                url.searchParams.set('loc', newLoc);
                window.location.href = url.toString();
            } else {
                window.location.reload();
            }
        } else {
            alert("Failed to update.");
        }
    } catch (err) {
        console.error(err);
        alert("An error occurred.");
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Update Zone';
        }
    }
}

function toggleManageStatuses() {
    const block = document.getElementById('manage-statuses-block');
    if (block) {
        block.style.display = block.style.display === 'none' ? 'block' : 'none';
    }
}

async function addNewStatusType() {
    const nameInput = document.getElementById('new-status-name');
    const colorInput = document.getElementById('new-status-color');
    if (!nameInput) return;

    const name = nameInput.value.trim();
    const color = colorInput ? colorInput.value : '#64748b';
    if (!name) return;

    const oldLoc = document.getElementById('rename-old-loc')?.value || '';
    const formData = new FormData();
    formData.append('action', 'add_location_status');
    formData.append('status_name', name);
    formData.append('status_color', color);
    if (oldLoc) {
        formData.append('location_code', oldLoc);
    }
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    formData.append('csrf_token', csrfToken);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        if (response.ok) {
            const select = document.getElementById('rename-status');
            if (select) {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = `${name} (Custom)`;
                opt.setAttribute('data-temp-custom', 'true');
                select.appendChild(opt);
                select.value = name;
            }
            const block = document.getElementById('manage-statuses-block');
            if (block) block.style.display = 'none';
            nameInput.value = '';
        }
    } catch (err) {
        console.error("Failed to add status", err);
    }
}

function initPhotoHoverPreviews() {
    // Delegated hover previews on document to seamlessly support real-time AJAX DOM sync
    document.addEventListener('mouseover', (e) => {
        const container = e.target.closest('.img-preview-container, .img-preview-container-zone');
        if (container) {
            const preview = container.querySelector('.hover-preview, .hover-preview-zone');
            if (preview) {
                preview.style.display = 'block';
            }
        }
    });

    document.addEventListener('mouseout', (e) => {
        const container = e.target.closest('.img-preview-container, .img-preview-container-zone');
        if (container) {
            const related = e.relatedTarget;
            if (!container.contains(related)) {
                const preview = container.querySelector('.hover-preview, .hover-preview-zone');
                if (preview) {
                    preview.style.display = 'none';
                }
            }
        }
    });

    document.addEventListener('mousemove', (e) => {
        const container = e.target.closest('.img-preview-container, .img-preview-container-zone');
        if (container) {
            const preview = container.querySelector('.hover-preview, .hover-preview-zone');
            if (preview && preview.style.display === 'block') {
                preview.style.left = (e.clientX + 20) + 'px';
                preview.style.top = (e.clientY - 150) + 'px';
            }
        }
    });
}

function initStickyTableHeaders() {
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                document.querySelectorAll('.spreadsheet-table-wrapper, .inventory-table-container').forEach(wrapper => {
                    const table = wrapper.querySelector('table');
                    if (!table) return;
                    const thead = table.querySelector('thead');
                    if (!thead) return;
                    const ths = thead.querySelectorAll('th');
                    const rect = wrapper.getBoundingClientRect();

                    if (rect.top < 0) {
                        const headerHeight = thead.offsetHeight;
                        const maxTranslate = rect.height - headerHeight - 60;
                        const translateVal = Math.min(-rect.top, maxTranslate);

                        if (translateVal > 0) {
                            ths.forEach(th => {
                                th.style.transform = `translateY(${translateVal - 1}px)`;
                                th.style.zIndex = '10';
                            });
                            ticking = false;
                            return;
                        }
                    }

                    ths.forEach(th => {
                        th.style.transform = '';
                    });
                });
                ticking = false;
            });
            ticking = true;
        }
    });
}

// Render newly added photo dynamically into the gallery DOM without page reload
function renderLocationPhotoCard(photo) {
    if (!photo) return;

    // Helper escape
    const esc = (str) => {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    };

    // 1. Horizontal Gallery (Spreadsheet View)
    const galleryGrid = document.querySelector('.photo-grid-horizontal');
    if (galleryGrid) {
        // Remove empty state placeholder if present
        const emptyMsg = galleryGrid.querySelector('div[style*="color: var(--text-dim)"]');
        if (emptyMsg) emptyMsg.remove();

        const addBtn = galleryGrid.querySelector('button');

        const card = document.createElement('div');
        card.className = 'photo-card-mini';
        card.style.cssText = 'flex: 0 0 110px; text-align: center; border: 1px solid var(--border-color); border-radius: 8px; padding: 4px; background: var(--bg-body); position: relative; animation: modalIn 0.3s ease;';
        card.innerHTML = `
            <div class="img-preview-container" style="position: relative; width: 100%; height: 75px; overflow: hidden; border-radius: 6px;">
                <img src="${esc(photo.thumbnail_path)}" alt="${esc(photo.original_filename || '')}" style="width: 100%; height: 100%; object-fit: cover;">
                <div class="hover-preview" style="display: none; position: fixed; z-index: 2100; width: 450px; height: 350px; background: rgba(0,0,0,0.95); border: 2px solid var(--accent-color); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); overflow: hidden; pointer-events: none;">
                    <img src="${esc(photo.optimized_path)}" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
            </div>
            <div style="font-size: 0.7rem; font-weight: 700; margin-top: 4px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="${esc(photo.location_code)} - ${esc(photo.category)}">
                ${esc(photo.location_code)} (${esc(photo.category)})
            </div>
            <div style="display: flex; justify-content: center; gap: 8px; margin-top: 4px;">
                <a href="download_archive.php?id=${photo.id}" class="btn-icon-tiny" title="Download Raw Original" style="font-size: 0.75rem; text-decoration: none;">📥</a>
                <button type="button" onclick="deleteLocationPhotoAjax(${photo.id}, this)" style="background: none; border: none; padding: 0; cursor: pointer; font-size: 0.75rem;" title="Delete Photo">🗑️</button>
            </div>
        `;

        if (addBtn) {
            galleryGrid.insertBefore(card, addBtn);
        } else {
            galleryGrid.appendChild(card);
        }

        // Update photo count badge
        const totalCards = galleryGrid.querySelectorAll('.photo-card-mini').length;
        const countBadge = document.querySelector('.location-photo-widget-container .photo-count, details .photo-count');
        if (countBadge) {
            countBadge.textContent = `${totalCards} Photos`;
            countBadge.style.backgroundColor = '#10b981';
        }

        // Open details accordion so user sees their new photo
        const detailsEl = document.querySelector('.location-photo-widget-container details, details');
        if (detailsEl) {
            detailsEl.open = true;
        }
    }

    // 2. Zone Photos Modal (if present)
    const zoneGrid = document.querySelector('#zone-photos-modal div[style*="grid-template-columns"]');
    if (zoneGrid) {
        const emptyZoneMsg = document.querySelector('#zone-photos-modal div[style*="text-align: center"]');
        if (emptyZoneMsg) emptyZoneMsg.remove();

        const zoneCard = document.createElement('div');
        zoneCard.className = 'photo-card-mini-zone';
        zoneCard.style.cssText = 'border: 1px solid var(--border-color); border-radius: 8px; padding: 6px; background: var(--bg-body); text-align: center; animation: modalIn 0.3s ease;';
        zoneCard.innerHTML = `
            <div class="img-preview-container-zone" style="position: relative; width: 100%; height: 130px; overflow: hidden; border-radius: 6px; cursor: pointer;">
                <img src="${esc(photo.thumbnail_path)}" alt="${esc(photo.original_filename || '')}" style="width: 100%; height: 100%; object-fit: cover;">
                <div class="hover-preview-zone" style="display: none; position: fixed; z-index: 2100; width: 450px; height: 350px; background: rgba(0,0,0,0.95); border: 2px solid var(--accent-primary); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); overflow: hidden; pointer-events: none;">
                    <img src="${esc(photo.optimized_path)}" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
            </div>
            <div style="font-size: 0.8rem; font-weight: 700; margin-top: 6px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                Location: ${esc(photo.location_code)}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 2px;">
                Sector: ${esc(photo.sector)}
            </div>
            <div style="font-size: 0.75rem; font-weight: 600; color: var(--accent-color); margin-top: 2px;">
                ${esc(photo.category)}
            </div>
            <div style="display: flex; justify-content: center; gap: 12px; margin-top: 8px;">
                <a href="download_archive.php?id=${photo.id}" class="btn-icon-tiny" title="Download Raw Original" style="font-size: 0.85rem; text-decoration: none;">📥</a>
                <button type="button" onclick="deleteLocationPhotoAjax(${photo.id}, this)" style="background: none; border: none; padding: 0; cursor: pointer; font-size: 0.85rem;" title="Delete Photo">🗑️</button>
            </div>
        `;
        zoneGrid.prepend(zoneCard);
    }
}
window.renderLocationPhotoCard = renderLocationPhotoCard;

async function deleteLocationPhotoAjax(photoId, btnEl) {
    if (!confirm('Delete this photo?')) return;

    try {
        const result = await AppSync.post('api/media_delete.php', { photo_id: photoId });

        if (result.success) {
            const card = btnEl.closest('.photo-card-mini, .photo-card-mini-zone');
            const galleryGrid = card ? card.closest('.photo-grid-horizontal') : null;
            if (card) {
                card.style.transition = 'all 0.25s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.85)';
                setTimeout(() => {
                    card.remove();

                    if (galleryGrid) {
                        const remaining = galleryGrid.querySelectorAll('.photo-card-mini').length;
                        const countBadge = document.querySelector('.location-photo-widget-container .photo-count, details .photo-count');
                        if (countBadge) {
                            countBadge.textContent = `${remaining} Photos`;
                            if (remaining === 0) {
                                countBadge.style.backgroundColor = '#94a3b8';
                                const addBtn = galleryGrid.querySelector('button');
                                const emptyMsg = document.createElement('div');
                                emptyMsg.style.cssText = 'color: var(--text-dim); font-size: 0.85rem; padding: 0.5rem 0;';
                                emptyMsg.textContent = 'No photographs uploaded for this location yet. Click Add / Snap Photo to capture or upload.';
                                if (addBtn) {
                                    galleryGrid.insertBefore(emptyMsg, addBtn);
                                }
                            }
                        }
                    }
                }, 250);
            }

            const notifyEngine = window.Notifications || window.IQA_Notify;
            if (notifyEngine && typeof notifyEngine.success === 'function') {
                notifyEngine.success('Photo removed.');
            }
        } else {
            alert(result.message || result.error || 'Failed to delete photo.');
        }
    } catch (err) {
        console.error('Delete error:', err);
        alert('An error occurred while deleting photo.');
    }
}
window.deleteLocationPhotoAjax = deleteLocationPhotoAjax;

/* ==========================================================================
   Item-Level Inventory Migration, Renaming & Location Management Controller
   ========================================================================== */

let activeMigrationItem = null;
let activeMigrationMode = 'single'; // 'single' | 'bulk'
let pendingDepletedShelf = null;

function getWarehouseLocations() {
    try {
        const el = document.getElementById('warehouse-locations-data');
        return el ? JSON.parse(el.textContent) : [];
    } catch (e) {
        return [];
    }
}

function getWarehouseZones() {
    try {
        const el = document.getElementById('warehouse-zones-data');
        return el ? JSON.parse(el.textContent) : ['General'];
    } catch (e) {
        return ['General'];
    }
}

function populateTargetShelvesDropdown(zoneName, currentShelfToExclude = null) {
    const shelfSelect = document.getElementById('migrate-target-shelf');
    if (!shelfSelect) return;

    shelfSelect.innerHTML = '';
    const locs = getWarehouseLocations();

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- Choose Shelf --';
    shelfSelect.appendChild(placeholder);

    // Filter locations by zone
    const matching = locs.filter(l => {
        const lZone = l.working_zone_name || 'General';
        return (!zoneName || lZone === zoneName) && (!currentShelfToExclude || l.location_code !== currentShelfToExclude);
    });

    matching.forEach(l => {
        const opt = document.createElement('option');
        opt.value = l.location_code;
        opt.textContent = `${l.location_code} (${l.status || 'Working'})`;
        shelfSelect.appendChild(opt);
    });

    // Add option to create a new shelf
    const newOpt = document.createElement('option');
    newOpt.value = '__NEW__';
    newOpt.textContent = '+ Create New Shelf...';
    newOpt.style.color = '#2563eb';
    newOpt.style.fontWeight = '800';
    shelfSelect.appendChild(newOpt);

    // Auto-select first matching shelf if available, otherwise __NEW__
    if (matching.length > 0) {
        shelfSelect.value = matching[0].location_code;
        handleMigrateShelfChange(matching[0].location_code);
    } else {
        shelfSelect.value = '__NEW__';
        handleMigrateShelfChange('__NEW__');
    }
}

function openItemMigrationModal(itemData) {
    activeMigrationMode = 'single';
    activeMigrationItem = itemData;

    const modal = document.getElementById('item-migration-modal');
    if (!modal) return;

    // Reset error banner
    const errBanner = document.getElementById('migrate-error-banner');
    if (errBanner) {
        errBanner.style.display = 'none';
        errBanner.textContent = '';
    }

    // Single item context visibility
    document.getElementById('migrate-single-context').style.display = 'block';
    document.getElementById('migrate-bulk-context').style.display = 'none';
    document.getElementById('migrate-qty-group').style.display = 'block';

    // Modal title & subtitle
    document.getElementById('migrate-modal-title').textContent = 'Relocate Item Stock';
    document.getElementById('migrate-modal-subtitle').textContent = `Transfer stock quantity from ${itemData.location_code || 'current shelf'} to another location.`;

    // Populate item specs
    let specs = {};
    if (typeof itemData.specs_json === 'string') {
        try { specs = JSON.parse(itemData.specs_json); } catch(e) {}
    } else if (itemData.specs_json && typeof itemData.specs_json === 'object') {
        specs = itemData.specs_json;
    }

    const titleEl = document.getElementById('migrate-item-title');
    if (titleEl) titleEl.textContent = `${itemData.brand || ''} ${itemData.model || ''}`.trim() || 'Inventory Item';

    const specsParts = [];
    if (specs.cpu) specsParts.push(`CPU: ${specs.cpu}`);
    if (specs.ram) specsParts.push(`RAM: ${specs.ram}`);
    if (specs.storage) specsParts.push(`Storage: ${specs.storage}`);
    if (specs.series) specsParts.push(`Series: ${specs.series}`);
    if (specs.condition) specsParts.push(`Condition: ${specs.condition}`);
    const specsEl = document.getElementById('migrate-item-specs');
    if (specsEl) specsEl.textContent = specsParts.length > 0 ? specsParts.join(' • ') : 'Standard specs';

    const sectorBadge = document.getElementById('migrate-item-sector-badge');
    if (sectorBadge) sectorBadge.textContent = itemData.sector || 'General';

    const srcLoc = itemData.location_code || '-';
    document.getElementById('migrate-item-source-loc').textContent = srcLoc;

    // Find parent zone of source shelf
    const locs = getWarehouseLocations();
    const foundLoc = locs.find(l => l.location_code === srcLoc);
    const srcZone = foundLoc?.working_zone_name || 'General';
    document.getElementById('migrate-item-source-zone').textContent = srcZone;

    const qty = parseInt(itemData.quantity) || 1;
    document.getElementById('migrate-item-available-qty').textContent = qty;
    document.getElementById('migrate-qty-max-label').textContent = qty;

    const qtyInput = document.getElementById('migrate-qty-input');
    if (qtyInput) {
        qtyInput.value = qty;
        qtyInput.max = qty;
    }

    // Highlight 'All' preset
    updatePresetPillActive(qty, qty);

    // Setup Zones dropdown
    const zoneSelect = document.getElementById('migrate-target-zone');
    if (zoneSelect) {
        zoneSelect.value = srcZone;
        if (!zoneSelect.value && zoneSelect.options.length > 1) {
            zoneSelect.selectedIndex = 1;
        }
        handleMigrateZoneChange(zoneSelect.value, srcLoc);
    }

    modal.style.display = 'flex';
}
window.openItemMigrationModal = openItemMigrationModal;

function openBulkMigrationModal() {
    if (typeof selectedIds === 'undefined' || selectedIds.size === 0) {
        if (window.IQA_Notify) {
            window.IQA_Notify.error("Please select at least one item from the table to migrate.");
        } else {
            alert("Please select at least one item from the table to migrate.");
        }
        return;
    }

    activeMigrationMode = 'bulk';
    activeMigrationItem = null;

    const modal = document.getElementById('item-migration-modal');
    if (!modal) return;

    // Reset error banner
    const errBanner = document.getElementById('migrate-error-banner');
    if (errBanner) {
        errBanner.style.display = 'none';
        errBanner.textContent = '';
    }

    // Toggle single vs bulk context
    document.getElementById('migrate-single-context').style.display = 'none';
    document.getElementById('migrate-bulk-context').style.display = 'block';
    document.getElementById('migrate-qty-group').style.display = 'none';

    document.getElementById('migrate-modal-title').textContent = 'Batch Relocate Inventory';
    document.getElementById('migrate-modal-subtitle').textContent = `Transfer all units of ${selectedIds.size} selected item(s) to a target shelf.`;
    document.getElementById('migrate-bulk-selected-count').textContent = selectedIds.size;

    // Setup Zones dropdown
    const zoneSelect = document.getElementById('migrate-target-zone');
    if (zoneSelect && zoneSelect.options.length > 1) {
        if (!zoneSelect.value) zoneSelect.selectedIndex = 1;
        handleMigrateZoneChange(zoneSelect.value);
    }

    modal.style.display = 'flex';
}
window.openBulkMigrationModal = openBulkMigrationModal;

function closeItemMigrationModal() {
    const modal = document.getElementById('item-migration-modal');
    if (modal) modal.style.display = 'none';
    activeMigrationItem = null;
}
window.closeItemMigrationModal = closeItemMigrationModal;

function handleMigrateZoneChange(zoneName, currentShelfToExclude = null) {
    const newZoneDrawer = document.getElementById('migrate-new-zone-drawer');
    const newShelfDrawer = document.getElementById('migrate-new-shelf-drawer');
    const shelfSelect = document.getElementById('migrate-target-shelf');

    if (zoneName === '__NEW__') {
        if (newZoneDrawer) newZoneDrawer.style.display = 'block';
        if (newShelfDrawer) newShelfDrawer.style.display = 'block';
        if (shelfSelect) {
            shelfSelect.innerHTML = '<option value="__NEW__" selected>+ Create New Shelf...</option>';
        }
        const nzInput = document.getElementById('migrate-new-zone-name');
        if (nzInput) nzInput.focus();
    } else {
        if (newZoneDrawer) newZoneDrawer.style.display = 'none';
        const exclude = currentShelfToExclude || (activeMigrationItem ? activeMigrationItem.location_code : null);
        populateTargetShelvesDropdown(zoneName, exclude);
    }
}
window.handleMigrateZoneChange = handleMigrateZoneChange;

function handleMigrateShelfChange(shelfCode) {
    const newShelfDrawer = document.getElementById('migrate-new-shelf-drawer');
    if (shelfCode === '__NEW__') {
        if (newShelfDrawer) newShelfDrawer.style.display = 'block';
        const nsCode = document.getElementById('migrate-new-shelf-code');
        if (nsCode) nsCode.focus();
    } else {
        if (newShelfDrawer) newShelfDrawer.style.display = 'none';
    }
}
window.handleMigrateShelfChange = handleMigrateShelfChange;

function adjustMigrateQty(delta) {
    const input = document.getElementById('migrate-qty-input');
    if (!input || !activeMigrationItem) return;
    const max = parseInt(activeMigrationItem.quantity) || 1;
    let current = parseInt(input.value) || 1;
    current = Math.max(1, Math.min(max, current + delta));
    input.value = current;
    updatePresetPillActive(current, max);
}
window.adjustMigrateQty = adjustMigrateQty;

function setMigrateQtyPreset(val) {
    const input = document.getElementById('migrate-qty-input');
    if (!input || !activeMigrationItem) return;
    const max = parseInt(activeMigrationItem.quantity) || 1;
    let target = (val === 'all') ? max : Math.min(parseInt(val) || 1, max);
    input.value = target;
    updatePresetPillActive(target, max);
}
window.setMigrateQtyPreset = setMigrateQtyPreset;

function validateMigrateQtyInput() {
    const input = document.getElementById('migrate-qty-input');
    if (!input || !activeMigrationItem) return;
    const max = parseInt(activeMigrationItem.quantity) || 1;
    let current = parseInt(input.value) || 1;
    if (current > max) current = max;
    if (current < 1) current = 1;
    input.value = current;
    updatePresetPillActive(current, max);
}
window.validateMigrateQtyInput = validateMigrateQtyInput;

function updatePresetPillActive(currentVal, maxVal) {
    document.querySelectorAll('.btn-preset-qty').forEach(btn => {
        const text = btn.textContent.trim().toLowerCase();
        if (text === 'all' && currentVal === maxVal) {
            btn.classList.add('active');
        } else if (text === String(currentVal) && currentVal !== maxVal) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

async function executeInventoryMigration() {
    const errBanner = document.getElementById('migrate-error-banner');
    if (errBanner) {
        errBanner.style.display = 'none';
        errBanner.textContent = '';
    }

    const zoneSelect = document.getElementById('migrate-target-zone');
    const shelfSelect = document.getElementById('migrate-target-shelf');
    let targetZone = zoneSelect?.value.trim() || '';
    let targetShelf = shelfSelect?.value.trim() || '';

    // If new zone
    if (targetZone === '__NEW__') {
        const nzInput = document.getElementById('migrate-new-zone-name');
        targetZone = nzInput?.value.trim() || '';
        if (!targetZone) {
            showMigrateError("Please enter a name for the new working zone.");
            return;
        }
    }

    // If new shelf
    if (targetShelf === '__NEW__') {
        const nsCode = document.getElementById('migrate-new-shelf-code');
        targetShelf = nsCode?.value.trim() || '';
        if (!targetShelf) {
            showMigrateError("Please enter a code for the new shelf location.");
            return;
        }
    }

    if (!targetShelf) {
        showMigrateError("Please select or create a destination shelf location.");
        return;
    }

    // Check same-location restriction for single item
    if (activeMigrationMode === 'single' && activeMigrationItem) {
        if (targetShelf === activeMigrationItem.location_code) {
            showMigrateError(`Destination cannot be the same as current shelf (${targetShelf}).`);
            return;
        }
    }

    const autoArchive = document.getElementById('migrate-auto-archive-check')?.checked ?? true;
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // Build payload
    const payload = {
        csrf_token: csrfToken,
        target_location: targetShelf,
        target_zone: targetZone,
        auto_archive_source: autoArchive
    };

    if (activeMigrationMode === 'single' && activeMigrationItem) {
        const qtyToMove = parseInt(document.getElementById('migrate-qty-input')?.value) || 1;
        payload.item_id = activeMigrationItem.id;
        payload.quantity = qtyToMove;
    } else if (activeMigrationMode === 'bulk') {
        payload.ids = Array.from(selectedIds);
    }

    const submitBtn = document.getElementById('btn-submit-migration');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⌛ Migrating Stock...';
    }

    try {
        const res = await fetch('api/migrate_inventory.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        if (json.success) {
            closeItemMigrationModal();

            // Clear selections
            if (typeof selectedIds !== 'undefined') {
                selectedIds.clear();
                const selectAll = document.getElementById('selectAll');
                if (selectAll) selectAll.checked = false;
                if (typeof updateBulkBar === 'function') updateBulkBar();
            }

            const notifyEngine = window.Notifications || window.IQA_Notify;
            const successMsg = `Successfully moved ${json.moved_items_count} item(s) (${json.total_units_moved} units) to ${json.target_location}!`;
            if (notifyEngine && typeof notifyEngine.success === 'function') {
                notifyEngine.success(successMsg);
            } else {
                alert(successMsg);
            }

            // Report auto-archived sources
            if (json.archived_sources && json.archived_sources.length > 0) {
                const archiveNote = `Depleted shelf ${json.archived_sources.join(', ')} was emptied and automatically archived.`;
                if (notifyEngine && typeof notifyEngine.info === 'function') {
                    notifyEngine.info(archiveNote);
                }
            }

            // Sync with other terminals / refresh table
            if (window.AppSync && typeof window.AppSync.sync === 'function') {
                await window.AppSync.sync('inventory-list', true);
            }

            // If depleted shelves exist and were NOT auto-archived, show prompt
            if (json.depleted_sources && json.depleted_sources.length > 0) {
                const unarchived = json.depleted_sources.filter(s => !json.archived_sources || !json.archived_sources.includes(s));
                if (unarchived.length > 0) {
                    openDepletedShelfModal(unarchived[0]);
                }
            }
        } else {
            showMigrateError(json.error || "Failed to execute inventory migration.");
        }
    } catch (err) {
        console.error(err);
        showMigrateError("A network error occurred while executing migration.");
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Confirm & Relocate Stock ➔</span>';
        }
    }
}
window.executeInventoryMigration = executeInventoryMigration;

function showMigrateError(msg) {
    const errBanner = document.getElementById('migrate-error-banner');
    if (errBanner) {
        errBanner.textContent = msg;
        errBanner.style.display = 'block';
    } else {
        alert(msg);
    }
}

function openDepletedShelfModal(shelfCode) {
    pendingDepletedShelf = shelfCode;
    const modal = document.getElementById('depleted-shelf-modal');
    const label = document.getElementById('depleted-shelf-code-display');
    if (label) label.textContent = shelfCode;
    if (modal) modal.style.display = 'flex';
}
window.openDepletedShelfModal = openDepletedShelfModal;

function closeDepletedShelfModal() {
    const modal = document.getElementById('depleted-shelf-modal');
    if (modal) modal.style.display = 'none';
    pendingDepletedShelf = null;
}
window.closeDepletedShelfModal = closeDepletedShelfModal;

async function confirmArchiveDepletedShelf() {
    if (!pendingDepletedShelf) return;
    const shelfCode = pendingDepletedShelf;

    try {
        const formData = new FormData();
        formData.append('action', 'archive_location');
        formData.append('location_code', shelfCode);
        formData.append('reason', 'Depleted via Inventory Migration');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });

        const json = await response.json();
        closeDepletedShelfModal();

        const notifyEngine = window.Notifications || window.IQA_Notify;
        if (json.success) {
            if (notifyEngine && typeof notifyEngine.success === 'function') {
                notifyEngine.success(`Shelf ${shelfCode} has been archived.`);
            }
            if (window.AppSync && typeof window.AppSync.sync === 'function') {
                await window.AppSync.sync('inventory-list', true);
            }
        }
    } catch (err) {
        console.error(err);
        closeDepletedShelfModal();
    }
}
window.confirmArchiveDepletedShelf = confirmArchiveDepletedShelf;

