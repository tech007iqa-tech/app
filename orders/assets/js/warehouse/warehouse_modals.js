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
    if (statusSelect) statusSelect.value = status;

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

    const formData = new FormData();
    formData.append('action', 'add_location_status');
    formData.append('status_name', name);
    formData.append('status_color', color);
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
                opt.textContent = name;
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
    document.querySelectorAll('.img-preview-container').forEach(container => {
        const preview = container.querySelector('.hover-preview');
        if (!preview) return;
        container.addEventListener('mouseenter', () => {
            preview.style.display = 'block';
        });
        container.addEventListener('mouseleave', () => {
            preview.style.display = 'none';
        });
        container.addEventListener('mousemove', (e) => {
            preview.style.left = (e.clientX + 20) + 'px';
            preview.style.top = (e.clientY - 150) + 'px';
        });
    });

    document.querySelectorAll('.img-preview-container-zone').forEach(container => {
        const preview = container.querySelector('.hover-preview-zone');
        if (!preview) return;
        container.addEventListener('mouseenter', () => {
            preview.style.display = 'block';
        });
        container.addEventListener('mouseleave', () => {
            preview.style.display = 'none';
        });
        container.addEventListener('mousemove', (e) => {
            preview.style.left = (e.clientX + 20) + 'px';
            preview.style.top = (e.clientY - 150) + 'px';
        });
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
