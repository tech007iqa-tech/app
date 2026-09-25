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
