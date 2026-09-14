<?php
require_once '../core/database.php';
require_once '../core/auth.php';

$user_display = htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']);
$user_role = htmlspecialchars($_SESSION['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Logs | Technician Dashboard</title>
    <!-- Include Orders Design System -->
    <link rel="stylesheet" href="../../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../assets/styles/dashboard.css">
    <link rel="stylesheet" href="../assets/styles/logs.css">
    <link rel="icon" type="image/png" href="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>
<body class="modern-theme" style="background-color: #f8fafc;">

    <div class="breadcrumb-container" role="banner" style="max-width: 1200px; margin: 20px auto; width: 95%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <a href="../index.php" class="crumb">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
            <span class="separator">/</span>
            <a href="#" class="crumb active">
                <span class="step-num">📋</span> Hardware Logs
            </a>
        </nav>
        <div>
            <a href="settings.php" class="btn-settings">⚙️ Settings</a>
        </div>
    </div>

    <main class="container" style="max-width: 1200px; margin: 0 auto; width: 95%;">

        <div class="page-header">
            <h1><span>📋</span> Hardware Logs</h1>
            <p>Log your daily hardware tests. Select Good or Bad to classify the unit.</p>
        </div>

        <!-- New Log Entry Form (horizontal, full width, hidden/collapsed by default) -->
        <details class="form-card" style="padding: 0; overflow: hidden; margin-bottom: 30px; border: 1px solid #cbd5e1; border-radius: 16px;">
            <summary style="padding: 20px 24px; cursor: pointer; list-style: none; font-weight: 800; font-size: 1.25rem; color: #1e293b; display: flex; justify-content: space-between; align-items: center; user-select: none; background: #f8fafc;">
                <span>➕ Add New Log Entry</span>
                <span class="toggle-icon" style="font-size: 1rem; color: #64748b; transition: transform 0.2s;">▼</span>
            </summary>
            <div style="padding: 24px; border-top: 1px solid #cbd5e1;">
                <form id="logForm">
                    <div class="form-top-row">
                        <h2 style="margin: 0; font-size: 1.2rem; color: #1e293b;">Log Details</h2>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="status-toggle" style="margin-bottom: 0;">
                                <button type="button" class="status-btn active good" onclick="setStatus('Good')">Good Laptop</button>
                                <button type="button" class="status-btn bad" onclick="setStatus('Bad')">Bad Laptop</button>
                                <input type="hidden" id="status" name="status" value="Good">
                            </div>
                            <button type="submit" class="btn-submit">Save Log Entry</button>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>QTY</label>
                            <input type="number" class="form-control" name="qty" value="1" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Make</label>
                            <input type="text" class="form-control" name="make" placeholder="e.g. Dell" required>
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" class="form-control" name="model" placeholder="e.g. Latitude 7490" required>
                        </div>
                        <div class="form-group">
                            <label>Series</label>
                            <input type="text" class="form-control" name="series" placeholder="e.g. 7000">
                        </div>
                        <div class="form-group">
                            <label>CPU</label>
                            <input type="text" class="form-control" name="cpu" placeholder="e.g. i5-8350U">
                        </div>
                        <div class="form-group">
                            <label>GPU</label>
                            <input type="text" class="form-control" name="gpu" placeholder="e.g. Intel UHD">
                        </div>
                        <div class="form-group">
                            <label>RAM</label>
                            <input type="text" class="form-control" name="ram" placeholder="e.g. 16GB">
                        </div>
                        <div class="form-group">
                            <label>Storage</label>
                            <input type="text" class="form-control" name="storage" placeholder="e.g. 256GB SSD">
                        </div>
                        <div class="form-group">
                            <label>Battery</label>
                            <input type="text" class="form-control" name="battery" placeholder="e.g. Excellent">
                        </div>
                        <div class="form-group">
                            <label>BIOS State</label>
                            <input type="text" class="form-control" name="bios_state" placeholder="e.g. Unlocked">
                        </div>
                        <div class="form-group">
                            <label>OS</label>
                            <input type="text" class="form-control" name="os" placeholder="e.g. Windows 10 Pro">
                        </div>
                        <div class="form-group span-2">
                            <label>Notes</label>
                            <input type="text" class="form-control" name="notes" placeholder="Any additional findings...">
                        </div>
                    </div>
                    <div id="formMessage" style="font-weight: bold; text-align: center; font-size: 0.9rem;"></div>
                </form>
            </div>
        </details>

        <!-- Recent Logs Table (full width, below) -->
        <div class="form-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; gap: 10px;">
                    <button id="tab-good" onclick="setActiveTab('Good')" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: #22c55e; color: white; font-weight: 800; cursor: pointer; transition: all 0.2s;">✅ Good Laptops</button>
                    <button id="tab-bad" onclick="setActiveTab('Bad')" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: 800; cursor: pointer; transition: all 0.2s;">❌ Bad Laptops</button>
                </div>

                <!-- Date Filters -->
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.8rem; font-weight: 800; color: #475569;">START:</label>
                        <input type="date" id="start_date" onchange="onDateFilterChange()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 600; background: white;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.8rem; font-weight: 800; color: #475569;">END:</label>
                        <input type="date" id="end_date" onchange="onDateFilterChange()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 600; background: white;">
                    </div>
                    <button onclick="clearDateFilter()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; background: white; color: #475569; font-size: 0.85rem; font-weight: 700; cursor: pointer;">Reset</button>
                </div>
            </div>

            <div class="log-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Make / Model</th>
                            <th>Specifications</th>
                            <th>Date & Time Info</th>
                            <th style="text-align: right; width: 320px;">Control Panel</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <tr><td colspan="5" style="text-align: center; padding: 20px;">Loading logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Edit Log Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div style="background: white; border-radius: 16px; width: 90%; max-width: 700px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; border: 1px solid #cbd5e1;">
            <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;">✏️ Edit Log Entry</h2>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form id="editForm" style="padding: 24px; margin-bottom: 0;">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="action" value="edit">

                <div class="form-grid">
                    <div class="form-group">
                        <label>QTY</label>
                        <input type="number" class="form-control" name="qty" id="edit-qty" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Make</label>
                        <input type="text" class="form-control" name="make" id="edit-make" required>
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" class="form-control" name="model" id="edit-model" required>
                    </div>
                    <div class="form-group">
                        <label>Series</label>
                        <input type="text" class="form-control" name="series" id="edit-series">
                    </div>
                    <div class="form-group">
                        <label>CPU</label>
                        <input type="text" class="form-control" name="cpu" id="edit-cpu">
                    </div>
                    <div class="form-group">
                        <label>GPU</label>
                        <input type="text" class="form-control" name="gpu" id="edit-gpu">
                    </div>
                    <div class="form-group">
                        <label>RAM</label>
                        <input type="text" class="form-control" name="ram" id="edit-ram">
                    </div>
                    <div class="form-group">
                        <label>Storage</label>
                        <input type="text" class="form-control" name="storage" id="edit-storage">
                    </div>
                    <div class="form-group">
                        <label>Battery</label>
                        <input type="text" class="form-control" name="battery" id="edit-battery">
                    </div>
                    <div class="form-group">
                        <label>BIOS State</label>
                        <input type="text" class="form-control" name="bios_state" id="edit-bios_state">
                    </div>
                    <div class="form-group">
                        <label>OS</label>
                        <input type="text" class="form-control" name="os" id="edit-os">
                    </div>
                    <div class="form-group span-2">
                        <label>Notes</label>
                        <input type="text" class="form-control" name="notes" id="edit-notes">
                    </div>
                </div>

                <div id="editFormMessage" style="font-weight: bold; text-align: center; font-size: 0.9rem; margin-bottom: 15px;"></div>

                <div style="display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: 700; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-submit" style="padding: 10px 30px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentStatus = 'Good';

        function setStatus(status) {
            document.getElementById('status').value = status;
            const btns = document.querySelectorAll('.status-toggle .status-btn');
            btns.forEach(btn => btn.classList.remove('active'));

            if (status === 'Good') {
                btns[0].classList.add('active');
            } else {
                btns[1].classList.add('active');
            }
        }

        function getLocalDateString() {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        // Set default date range to today on page load
        const todayStr = getLocalDateString();
        document.getElementById('start_date').value = todayStr;
        document.getElementById('end_date').value = todayStr;

        function setActiveTab(status) {
            currentStatus = status;

            const btnGood = document.getElementById('tab-good');
            const btnBad = document.getElementById('tab-bad');

            if (status === 'Good') {
                btnGood.style.background = '#22c55e';
                btnGood.style.color = 'white';
                btnBad.style.background = 'white';
                btnBad.style.color = '#475569';
            } else {
                btnGood.style.background = 'white';
                btnGood.style.color = '#475569';
                btnBad.style.background = '#ef4444';
                btnBad.style.color = 'white';
            }

            fetchLogs();
        }

        function onDateFilterChange() {
            fetchLogs();
        }

        function clearDateFilter() {
            const todayStr = getLocalDateString();
            document.getElementById('start_date').value = todayStr;
            document.getElementById('end_date').value = todayStr;
            fetchLogs();
        }

        document.getElementById('logForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('formMessage');
            msg.textContent = 'Saving...';
            msg.style.color = '#475569';

            const formData = new FormData(e.target);

            try {
                const response = await fetch('../api/add_log.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    msg.textContent = '✅ Log saved successfully!';
                    msg.style.color = '#15803d';
                    e.target.reset();
                    setStatus(document.getElementById('status').value);
                    fetchLogs();

                    setTimeout(() => { msg.textContent = ''; }, 3000);
                } else {
                    msg.textContent = '❌ Error: ' + result.error;
                    msg.style.color = '#b91c1c';
                }
            } catch (err) {
                msg.textContent = '❌ Network Error';
                msg.style.color = '#b91c1c';
            }
        });

        async function fetchLogs() {
            const tbody = document.getElementById('logsTableBody');
            const start = document.getElementById('start_date').value;
            const end = document.getElementById('end_date').value;

            let url = `../api/get_logs.php?status=${currentStatus}`;
            if (start && end) {
                url += `&start_date=${start}&end_date=${end}`;
            }

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    if (result.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #64748b;">No ${currentStatus.toLowerCase()} laptop logs found for this date range.</td></tr>`;
                        return;
                    }

                    let html = '';
                    result.data.forEach(log => {
                        const badgeClass = log.status === 'Good' ? 'good' : 'bad';
                        const rowStyle = log.status === 'Good' ? 'background: #f0fdf4;' : 'background: #fef2f2;';

                        // Parse date and time
                        const dt = new Date(log.created_at);
                        const dateStr = dt.toLocaleDateString();
                        const timeStr = dt.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

                        // Control buttons
                        let controlHtml = '';
                        if (parseInt(log.delete_requested) === 1) {
                            controlHtml = `<span style="color: #ef4444; font-size: 0.75rem; font-weight: bold;">🗑️ Pending Delete</span>`;
                        } else {
                            // If status change is pending
                            let statusText = log.status_change_requested !== '' ?
                                `<span style="color: #f97316; font-size: 0.75rem; font-weight: bold; display: block; margin-bottom: 4px;">⏳ Pending Status -> ${log.status_change_requested}</span>` : '';

                            controlHtml = `
                                ${statusText}
                                <div style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                    <button onclick="toggleStatus(${log.id})" style="background: #cbd5e1; color: #1e293b; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">🔄 Status</button>
                                    <button onclick="openEditModal(${JSON.stringify(log).replace(/"/g, '&quot;')})" style="background: #e2e8f0; color: #475569; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">✏️ Edit</button>
                                    <button onclick="requestDelete(${log.id})" style="background: #fee2e2; color: #991b1b; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">🗑️ Delete</button>
                                    <button onclick="printLabel(${log.id})" style="background: #e0e7ff; color: #3730a3; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">🖨️ Print</button>
                                </div>
                            `;
                        }

                        // Edited indicator
                        const editedBadge = parseInt(log.edited) === 1 ? `<span class="badge" style="background: #fef9c3; color: #854d0e; font-size: 0.7rem; padding: 2px 4px; margin-left: 5px;">Edited</span>` : '';

                        // Delete Rejected indicator
                        const rejectedBadge = parseInt(log.delete_requested) === 2 ? `<span class="badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; font-size: 0.7rem; padding: 2px 4px; margin-left: 5px;">Delete Rejected</span>` : '';

                        html += `
                            <tr style="${rowStyle}">
                                <td><span class="badge ${badgeClass}">${log.status}</span></td>
                                <td>
                                    <strong>${log.make} ${log.model}</strong>${editedBadge}${rejectedBadge}
                                    ${log.series ? `<br><small style="color: #64748b;">Series: ${log.series}</small>` : ''}
                                    <br><small style="color: #64748b;">Qty: ${log.qty}</small>
                                </td>
                                <td>
                                    <small>
                                        <strong>CPU:</strong> ${log.cpu || '-'}<br>
                                        <strong>RAM:</strong> ${log.ram || '-'}<br>
                                        <strong>Storage:</strong> ${log.storage || '-'}
                                        ${log.gpu ? `<br><strong>GPU:</strong> ${log.gpu}` : ''}
                                        ${log.battery ? `<br><strong>Battery:</strong> ${log.battery}` : ''}
                                        ${log.bios_state ? `<br><strong>BIOS:</strong> ${log.bios_state}` : ''}
                                        ${log.os ? `<br><strong>OS:</strong> ${log.os}` : ''}
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <strong>Date:</strong> ${dateStr}<br>
                                        <strong>Time:</strong> ${timeStr}
                                        ${log.notes ? `<br><strong>Notes:</strong> ${log.notes}` : ''}
                                    </small>
                                </td>
                                <td style="text-align: right; vertical-align: middle;">
                                    ${controlHtml}
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #ef4444;">Failed to load logs.</td></tr>';
            }
        }

        // Action Handlers
        async function toggleStatus(id) {
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('id', id);

                const response = await fetch('../api/request_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    fetchLogs();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Network error trying to toggle status.');
            }
        }

        async function requestDelete(id) {
            if (!confirm('Are you sure you want to request deletion of this entry? This will require Admin approval.')) return;
            try {
                const formData = new FormData();
                formData.append('action', 'request_delete');
                formData.append('id', id);

                const response = await fetch('../api/request_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    fetchLogs();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Network error trying to request delete.');
            }
        }

        async function printLabel(id) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('qty', 1);
                formData.append('print_a', 1);
                formData.append('print_b', 1);

                const response = await fetch('../api/print_label.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    window.location.href = result.file_path;
                } else {
                    alert('Failed to generate label: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        // Modal Controls
        function openEditModal(log) {
            document.getElementById('edit-id').value = log.id;
            document.getElementById('edit-qty').value = log.qty;
            document.getElementById('edit-make').value = log.make;
            document.getElementById('edit-model').value = log.model;
            document.getElementById('edit-series').value = log.series || '';
            document.getElementById('edit-cpu').value = log.cpu || '';
            document.getElementById('edit-gpu').value = log.gpu || '';
            document.getElementById('edit-ram').value = log.ram || '';
            document.getElementById('edit-storage').value = log.storage || '';
            document.getElementById('edit-battery').value = log.battery || '';
            document.getElementById('edit-bios_state').value = log.bios_state || '';
            document.getElementById('edit-os').value = log.os || '';
            document.getElementById('edit-notes').value = log.notes || '';

            document.getElementById('editFormMessage').textContent = '';
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('editForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('editFormMessage');
            msg.textContent = 'Saving...';
            msg.style.color = '#475569';

            const formData = new FormData(e.target);
            try {
                const response = await fetch('../api/request_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    msg.textContent = '✅ Log updated successfully!';
                    msg.style.color = '#15803d';
                    setTimeout(() => {
                        closeEditModal();
                        fetchLogs();
                    }, 1000);
                } else {
                    msg.textContent = '❌ Error: ' + result.error;
                    msg.style.color = '#b91c1c';
                }
            } catch (err) {
                msg.textContent = '❌ Network Error';
                msg.style.color = '#b91c1c';
            }
        });

        // Initial fetch
        fetchLogs();
    </script>
</body>
</html>
