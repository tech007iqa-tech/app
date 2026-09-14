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
    <title>Parts Inventory | Technician Dashboard</title>
    <link rel="stylesheet" href="../../orders/assets/styles/components.css">
    <link rel="stylesheet" href="../../orders/assets/styles/style.css">
    <link rel="stylesheet" href="../assets/styles/dashboard.css">
    <link rel="stylesheet" href="../assets/styles/inventory.css">
    <link rel="icon" type="image/png" href="../../orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>
<body class="modern-theme" style="background-color: #f8fafc;">

    <div class="breadcrumb-container" role="banner" style="max-width: 1000px; margin: 20px auto; width: 95%; display: flex; justify-content: space-between; align-items: center;">
        <nav class="breadcrumbs">
            <a href="../index.php" class="crumb">
                <span class="step-num">🔧</span> Technician Dashboard
            </a>
            <span class="separator">/</span>
            <a href="#" class="crumb active">
                <span class="step-num">📦</span> Parts Inventory
            </a>
        </nav>
        <div>
            <a href="settings.php" class="btn-settings">⚙️ Settings</a>
        </div>
    </div>

    <main class="container" style="max-width: 1000px; margin: 0 auto; width: 95%;">

        <div class="page-header">
            <h1><span>📦</span> Parts Inventory</h1>
            <p>Manage and track stock levels for RAM, SSDs, batteries, and tools.</p>
        </div>

        <!-- Add New Part Form (collapsible details) -->
        <details class="form-card" style="padding: 0; overflow: hidden; margin-bottom: 30px; border: 1px solid #cbd5e1; border-radius: 16px;">
            <summary style="padding: 20px 24px; cursor: pointer; list-style: none; font-weight: 800; font-size: 1.25rem; color: #1e293b; display: flex; justify-content: space-between; align-items: center; user-select: none; background: #f8fafc;">
                <span>➕ Add New Part / Tool</span>
                <span class="toggle-icon" style="font-size: 1rem; color: #64748b; transition: transform 0.2s;">▼</span>
            </summary>
            <div style="padding: 24px; border-top: 1px solid #cbd5e1;">
                <form id="addPartForm" style="display: flex !important; flex-direction: row !important; align-items: flex-end !important; gap: 16px !important; flex-wrap: wrap !important; width: 100% !important;">
                    <div style="flex: 2; min-width: 200px; display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">Part / Tool Name</label>
                        <input type="text" name="part_name" placeholder="e.g. 1TB NVMe SSD" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1.5; min-width: 150px; display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">Category</label>
                        <input type="text" name="category" placeholder="e.g. Storage" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1; min-width: 90px; display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">Qty</label>
                        <input type="number" name="quantity" value="0" min="0" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1; min-width: 90px; display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">Low Alert</label>
                        <input type="number" name="low_stock_threshold" value="5" min="0" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 0 0 auto;">
                        <button type="submit" style="background: #4f46e5; color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 800; font-size: 0.95rem; cursor: pointer; white-space: nowrap; height: 42px; display: flex; align-items: center; justify-content: center;">+ Add Part</button>
                    </div>
                </form>
                <div id="addPartMessage" style="margin-top: 15px; font-weight: bold; text-align: center; font-size: 0.9rem;"></div>
            </div>
        </details>

        <!-- Stock Table -->
        <div class="form-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <h2 style="margin: 0; font-size: 1.2rem; color: #1e293b;">Available Stock</h2>
                <button onclick="fetchInventory()" style="background: none; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: #475569;">🔄 Refresh</button>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Part / Tool Name</th>
                            <th>Category</th>
                            <th>Quantity In Stock</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <tr><td colspan="4" style="text-align: center; padding: 20px;">Loading inventory...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Add Part Form
        document.getElementById('addPartForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('addPartMessage');
            msg.textContent = 'Adding...';
            msg.style.color = '#475569';

            const formData = new FormData(e.target);

            try {
                const response = await fetch('../api/add_part.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    msg.textContent = '✅ Part added successfully!';
                    msg.style.color = '#15803d';
                    e.target.reset();
                    fetchInventory();
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

        async function fetchInventory() {
            const tbody = document.getElementById('inventoryTableBody');
            try {
                const response = await fetch('../api/get_inventory.php');
                const result = await response.json();

                if (result.success) {
                    if (result.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #64748b;">No parts found.</td></tr>';
                        return;
                    }

                    let html = '';
                    result.data.forEach(part => {
                        const isLowStock = parseInt(part.quantity) <= parseInt(part.low_stock_threshold);
                        const lowStockBadge = isLowStock ? '<span class="low-stock">⚠️ LOW STOCK</span>' : '';

                        html += `
                            <tr>
                                <td>
                                    <strong style="color: #1e293b; font-size: 1.05rem;">${part.part_name}</strong>
                                    ${lowStockBadge}
                                </td>
                                <td><span style="color: #64748b; font-size: 0.85rem; text-transform: uppercase; font-weight: 700;">${part.category}</span></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <button class="btn-adjust minus" onclick="adjustPart(${part.id}, -1)">-</button>
                                        <span class="qty-display" style="${isLowStock ? 'color: #b91c1c;' : ''}">${part.quantity}</span>
                                        <button class="btn-adjust plus" onclick="adjustPart(${part.id}, 1)">+</button>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <button onclick="deletePart(${part.id}, '${part.part_name.replace(/'/g, "\\'")}')" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.2rem;" title="Remove Part">🗑️</button>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #ef4444;">Failed to load inventory.</td></tr>';
            }
        }

        async function adjustPart(id, adjustment) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('adjustment', adjustment);

                const response = await fetch('../api/update_part.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    fetchInventory();
                } else {
                    alert('Failed to update: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        async function deletePart(id, name) {
            if (!confirm('Are you sure you want to remove "' + name + '" from the inventory?')) return;

            try {
                const formData = new FormData();
                formData.append('id', id);

                const response = await fetch('../api/delete_part.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    fetchInventory();
                } else {
                    alert('Failed to delete: ' + result.error);
                }
            } catch (err) {
                alert('Network Error');
            }
        }

        // Initial fetch
        fetchInventory();
    </script>
</body>
</html>
