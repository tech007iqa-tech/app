<?php
require_once '../core/database.php';
require_once '../core/auth.php';

// Access Control: Only Admins can access the Audit page
if ($_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$user_display = htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']);
$user_role = htmlspecialchars($_SESSION['role']);

// Fetch pending action requests directly in PHP
try {
    $conn = Database::tech();
    $stmt_reqs = $conn->query("SELECT * FROM logs WHERE delete_requested = 1 OR status_change_requested != '' ORDER BY created_at DESC");
    $pending_reqs = $stmt_reqs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_reqs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Audit Logs | System</title>
    <!-- Include Orders Design System -->
    <link rel="stylesheet" href="../../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../assets/styles/dashboard.css">
    <link rel="stylesheet" href="../assets/styles/admin.css">
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
                <span class="step-num">👥</span> Admin Audit Logs
            </a>
        </nav>
        <div>
            <a href="settings.php" class="btn-settings">⚙️ Settings</a>
        </div>
    </div>

    <main class="container" style="max-width: 1200px; margin: 0 auto; width: 95%;">

        <div class="page-header">
            <h1><span>👥</span> Admin Audit Logs</h1>
            <p>Monitor technician output, filter by custom date ranges, and authorize change requests.</p>
        </div>

        <!-- Pending Action Requests (Only displayed if requests exist) -->
        <?php if (count($pending_reqs) > 0): ?>
            <div class="request-card">
                <h2 style="margin: 0 0 15px 0; font-size: 1.25rem; color: #b45309; display: flex; align-items: center; gap: 8px;">⚠️ Pending Action Requests</h2>
                <div class="log-table-container" style="background: white; border: 1px solid #fde68a; border-radius: 12px; overflow: hidden;">
                    <table>
                        <thead>
                            <tr style="background: #fffbef;">
                                <th>Tech ID</th>
                                <th>Requested Action</th>
                                <th>Make / Model / Specs</th>
                                <th>Date Submitted</th>
                                <th style="text-align: right;">Authorization</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_reqs as $req): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($req['tech_id']) ?></strong></td>
                                    <td>
                                        <?php if ((int)$req['delete_requested'] === 1): ?>
                                            <span style="color:#ef4444; font-weight:700;">🗑️ DELETE ENTRY</span>
                                        <?php else: ?>
                                            <span style="color:#f59e0b; font-weight:700;">🔄 CHANGE STATUS TO <?= htmlspecialchars($req['status_change_requested']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($req['make']) ?> <?= htmlspecialchars($req['model']) ?></strong>
                                        <br><small style="color: #64748b;"><?= htmlspecialchars($req['cpu'] ?: '-') ?> / <?= htmlspecialchars($req['ram'] ?: '-') ?> / <?= htmlspecialchars($req['storage'] ?: '-') ?><?= $req['os'] ? ' / ' . htmlspecialchars($req['os']) : '' ?></small>
                                    </td>
                                    <td><small><?= htmlspecialchars($req['created_at']) ?></small></td>
                                    <td style="text-align: right;">
                                        <?php if ((int)$req['delete_requested'] === 1): ?>
                                            <button onclick="handleRequest(<?= $req['id'] ?>, 'approve_delete')" class="btn-approve">Approve Delete</button>
                                            <button onclick="handleRequest(<?= $req['id'] ?>, 'reject_delete')" class="btn-reject">Reject</button>
                                        <?php else: ?>
                                            <button onclick="handleRequest(<?= $req['id'] ?>, 'approve_status_change')" class="btn-approve">Approve Status</button>
                                            <button onclick="handleRequest(<?= $req['id'] ?>, 'reject_status_change')" class="btn-reject">Reject</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Audit Filters -->
        <div class="filter-card">
            <div class="filter-group">
                <!-- Status Filter -->
                <div class="status-pill-toggle">
                    <button class="status-pill active" onclick="setStatusFilter('')" id="pill-all">All Logs</button>
                    <button class="status-pill" onclick="setStatusFilter('Good')" id="pill-good">Good Only</button>
                    <button class="status-pill" onclick="setStatusFilter('Bad')" id="pill-bad">Bad Only</button>
                </div>

                <!-- Custom Date Range -->
                <div class="filter-control">
                    <label>Start Date</label>
                    <input type="date" id="start_date" onchange="fetchAuditLogs()">
                </div>
                <div class="filter-control">
                    <label>End Date</label>
                    <input type="date" id="end_date" onchange="fetchAuditLogs()">
                </div>

                <button onclick="resetFilters()" style="padding: 8px 16px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; color: #475569; font-weight: 700; cursor: pointer;">Reset Date</button>
            </div>
        </div>

        <!-- Grouped Logs Output -->
        <div id="auditContainer">
            <div style="text-align: center; padding: 40px; color: #64748b;">Loading audit logs...</div>
        </div>

    </main>

    <!-- Edit Log Modal (Admin Mode) -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div style="background: white; border-radius: 16px; width: 90%; max-width: 700px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden; border: 1px solid #cbd5e1;">
            <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;">✏️ Edit Log Entry (Admin)</h2>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <form id="editForm" style="padding: 24px; margin-bottom: 0;">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="action" value="admin_edit">

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
        let currentStatus = '';

        function getLocalDateString() {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        // Initialize date inputs to today
        const todayStr = getLocalDateString();
        document.getElementById('start_date').value = todayStr;
        document.getElementById('end_date').value = todayStr;

        function setStatusFilter(status) {
            currentStatus = status;

            document.getElementById('pill-all').classList.remove('active');
            document.getElementById('pill-good').classList.remove('active');
            document.getElementById('pill-bad').classList.remove('active');

            if (status === '') {
                document.getElementById('pill-all').classList.add('active');
            } else if (status === 'Good') {
                document.getElementById('pill-good').classList.add('active');
            } else {
                document.getElementById('pill-bad').classList.add('active');
            }

            fetchAuditLogs();
        }

        function resetFilters() {
            const todayStr = getLocalDateString();
            document.getElementById('start_date').value = todayStr;
            document.getElementById('end_date').value = todayStr;
            fetchAuditLogs();
        }

        async function fetchAuditLogs() {
            const container = document.getElementById('auditContainer');
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
                        container.innerHTML = '<div style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px; text-align: center; color: #64748b; font-weight: 600;">No logs found for the selected range.</div>';
                        return;
                    }

                    // Group logs by tech_id
                    let grouped = {};
                    result.data.forEach(log => {
                        if (!grouped[log.tech_id]) {
                            grouped[log.tech_id] = [];
                        }
                        grouped[log.tech_id].push(log);
                    });

                    let html = '';
                    for (const tech in grouped) {
                        const logs = grouped[tech];
                        const goodCount = logs.filter(l => l.status === 'Good').reduce((sum, l) => sum + parseInt(l.qty), 0);
                        const badCount = logs.filter(l => l.status === 'Bad').reduce((sum, l) => sum + parseInt(l.qty), 0);

                        html += `
                            <div class="tech-section-card">
                                <div class="tech-section-header">
                                    <h2>👤 Technician: ${tech}</h2>
                                    <div style="font-size: 0.85rem; font-weight: 700; color: #64748b;">
                                        Total Good: <span style="color:#15803d">${goodCount}</span> |
                                        Total Bad: <span style="color:#b91c1c">${badCount}</span>
                                    </div>
                                </div>
                                <div class="log-table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th style="width: 100px;">Status</th>
                                                <th>Make / Model</th>
                                                <th>Specifications</th>
                                                <th>Date & Time Info</th>
                                                <th>Notes</th>
                                                <th style="text-align: right; width: 320px;">Admin Operations</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                        `;

                        logs.forEach(log => {
                            const badgeClass = log.status === 'Good' ? 'good' : 'bad';
                            const rowStyle = log.status === 'Good' ? 'background: #f0fdf4;' : 'background: #fef2f2;';
                            const dt = new Date(log.created_at);
                            const dateStr = dt.toLocaleDateString();
                            const timeStr = dt.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                            const editedBadge = parseInt(log.edited) === 1 ? `<span class="badge" style="background: #fef9c3; color: #854d0e; font-size: 0.7rem; padding: 2px 4px; margin-left: 5px;">Edited</span>` : '';
                            const rejectedBadge = parseInt(log.delete_requested) === 2 ? `<span class="badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; font-size: 0.7rem; padding: 2px 4px; margin-left: 5px;">Delete Rejected</span>` : '';

                            // Check request state to label row nicely if pending
                            let statusText = '';
                            if (parseInt(log.delete_requested) === 1) {
                                statusText = `<span style="color: #ef4444; font-size: 0.75rem; font-weight: bold; display: block; margin-bottom: 4px;">🗑️ Pending Delete Approval</span>`;
                            } else if (log.status_change_requested !== '') {
                                statusText = `<span style="color: #f59e0b; font-size: 0.75rem; font-weight: bold; display: block; margin-bottom: 4px;">⏳ Pending Status Change -> ${log.status_change_requested}</span>`;
                            }

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
                                        </small>
                                    </td>
                                    <td>
                                        <small style="color: #475569;">${log.notes || '-'}</small>
                                    </td>
                                    <td style="text-align: right; vertical-align: middle;">
                                        ${statusText}
                                        <div style="display: flex; gap: 4px; justify-content: flex-end; align-items: center;">
                                            <button onclick="adminToggleStatus(${log.id})" style="background: #cbd5e1; color: #1e293b; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">🔄 Status</button>
                                            <button onclick="openEditModal(${JSON.stringify(log).replace(/"/g, '&quot;')})" style="background: #e2e8f0; color: #475569; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">✏️ Edit</button>
                                            <button onclick="adminDelete(${log.id})" style="background: #fee2e2; color: #991b1b; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">🗑️ Delete</button>
                                            <button onclick="printLabel(${log.id})" style="background: #e0e7ff; color: #3730a3; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 700;">🖨️ Print</button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });

                        html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                    }
                    container.innerHTML = html;
                }
            } catch (err) {
                container.innerHTML = '<div style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px; text-align: center; color: #ef4444; font-weight: 600;">Failed to load audit logs.</div>';
            }
        }

        // Admin Request Resolvers
        async function handleRequest(id, action) {
            if (!confirm(`Are you sure you want to execute this request (${action})?`)) return;
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('action', action);

                const response = await fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    window.location.reload(); // Reload to refresh both pending requests PHP block and logs list
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        // Admin Operations (Immediate Actions)
        async function adminToggleStatus(id) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('action', 'admin_toggle_status');

                const response = await fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    fetchAuditLogs();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        async function adminDelete(id) {
            if (!confirm('Are you sure you want to permanently delete this log entry? This action is immediate and cannot be undone.')) return;
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('action', 'admin_delete');

                const response = await fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    fetchAuditLogs();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
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

        // Edit Modal Controls
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
                const response = await fetch('../api/admin_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    msg.textContent = '✅ Log updated successfully!';
                    msg.style.color = '#15803d';
                    setTimeout(() => {
                        closeEditModal();
                        fetchAuditLogs();
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
        fetchAuditLogs();
    </script>
</body>
</html>
