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
    <title>Label Generation | Technician Dashboard</title>
    <!-- Include Orders Design System -->
    <link rel="stylesheet" href="../../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../assets/styles/dashboard.css">
    <link rel="stylesheet" href="../assets/styles/labels.css">
    <link rel="icon" type="image/png" href="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>
<body class="modern-theme">

    <div class="breadcrumb-container" role="banner" style="max-width: 1200px; margin: 20px auto; width: 95%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <a href="../index.php" class="crumb">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
            <span class="separator">/</span>
            <a href="#" class="crumb active">
                <span class="step-num">🏷️</span> Label Generation
            </a>
        </nav>
        <div>
            <a href="settings.php" class="btn-settings">⚙️ Settings</a>
        </div>
    </div>

    <main class="container" style="max-width: 1200px; margin: 0 auto; width: 95%;">

        <div class="page-header">
            <h1><span>🏷️</span> Hardware Labels</h1>
            <p>Search tested hardware and print professional .odt thermal labels.</p>
        </div>

        <div class="form-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                <input type="text" id="labelSearch" class="search-bar" placeholder="🔍 Search logs by Make, Model, or Series...">
            </div>
            <div class="log-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Make / Model</th>
                            <th>Specs</th>
                            <th>Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <tr><td colspan="5" style="text-align: center; padding: 20px;">Loading logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        let allLogs = [];

        async function fetchAllLogs() {
            try {
                // We reuse the get_logs endpoint, but we need it to return all logs, not just today's.
                // Let's call search_logs with an empty query, or we can just fetch all from a new endpoint.
                // Actually, let's just make search_logs.php return recent 100 if query is empty.
                const response = await fetch('../api/search_logs.php?q=');
                const result = await response.json();

                if (result.success) {
                    allLogs = result.data;
                    renderLogs(allLogs);
                }
            } catch (err) {
                console.error(err);
            }
        }

        function renderLogs(logsToRender) {
            const tbody = document.getElementById('logsTableBody');
            if (logsToRender.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #64748b;">No logs found.</td></tr>';
                return;
            }

            let html = '';
            logsToRender.forEach(log => {
                html += `
                    <tr>
                        <td><strong>#${log.id}</strong></td>
                        <td><strong>${log.make} ${log.model}</strong><br><small style="color: #64748b;">Series: ${log.series || 'N/A'}</small></td>
                        <td><small>${log.cpu || '-'} / ${log.ram || '-'} / ${log.storage || '-'}</small></td>
                        <td><small>${new Date(log.created_at).toLocaleDateString()}</small></td>
                        <td style="text-align: right;">
                            <button onclick="printLabel(${log.id})" class="btn-print">
                                🖨️ Print .ODT
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        document.getElementById('labelSearch').addEventListener('input', async (e) => {
            const q = e.target.value.trim();
            if (q.length < 2) {
                renderLogs(allLogs);
                return;
            }
            try {
                const response = await fetch('../api/search_logs.php?q=' + encodeURIComponent(q));
                const result = await response.json();
                if (result.success) {
                    renderLogs(result.data);
                }
            } catch (err) {}
        });

        async function printLabel(id) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                // Default options
                formData.append('qty', 1);
                formData.append('print_a', 1);
                formData.append('print_b', 1);

                const response = await fetch('../api/print_label.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Trigger download/open
                    window.location.href = result.file_path;
                } else {
                    alert('Failed to generate label: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        // Initial fetch
        fetchAllLogs();
    </script>
</body>
</html>
