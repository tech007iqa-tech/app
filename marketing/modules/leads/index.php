<?php
/**
 * Leads Module - Main View & Logic
 * Multi-database synchronization, zero-trust input sanitization, and atomic operations.
 */

// Handle Actions
$action = $_GET['action'] ?? null;
$error = null;

// 1. Add Lead (POST, CSRF Protected)
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
        header("Location: ?page=leads");
        exit;
    }

    $contact_person = trim($_POST['name'] ?? '');
    $company_name = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $lead_source = trim($_POST['source'] ?? 'Manual');

    // CRM requires company_name, so we default it if empty
    if (empty($company_name)) {
        $company_name = "Lead: " . $contact_person;
    }

    if (!empty($contact_person) && !empty($email)) {
        try {
            // Generate Master CRM ID (Matching Order Manager format)
            $customer_id = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            // Atomic dual-write using db_transaction
            db_transaction($crmDb, function($cDb) use ($customer_id, $contact_person, $company_name, $email, $phone, $lead_source) {
                $stmtCrm = $cDb->prepare("INSERT INTO customers (customer_id, contact_person, company_name, email, phone, lead_source, account_status) VALUES (?, ?, ?, ?, ?, ?, 'Lead')");
                $stmtCrm->execute([$customer_id, $contact_person, $company_name, $email, $phone, $lead_source]);
            });

            db_transaction($marketingDb, function($mDb) use ($customer_id, $contact_person, $company_name, $email, $phone, $lead_source) {
                $stmtLocal = $mDb->prepare("INSERT INTO leads (customer_id, name, company, email, phone, source, status) VALUES (?, ?, ?, ?, ?, ?, 'Lead')");
                $stmtLocal->execute([$customer_id, $contact_person, $company_name, $email, $phone, $lead_source]);
            });

            log_marketing_audit($marketingDb, 'Lead', $customer_id, 'SYNCED', "Lead created and synced to CRM: $contact_person ($company_name)");

            $_SESSION['notify'] = ['message' => "Lead captured and synced to Master CRM successfully!", 'type' => 'success'];
            header("Location: ?page=leads");
            exit;
        } catch (Throwable $e) {
            $error = "Failed to add lead: " . $e->getMessage();
        }
    } else {
        $error = "Contact Name and Email are required.";
    }
}

// 2. Handle Sync Action (Pull from CRM to Local, POST, CSRF Protected)
if ($action === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
        header("Location: ?page=leads");
        exit;
    }

    try {
        $crmContacts = $crmDb->query("SELECT * FROM customers")->fetchAll() ?: [];
        $importedCount = 0;

        db_transaction($marketingDb, function($mDb) use ($crmContacts, &$importedCount) {
            $check = $mDb->prepare("SELECT COUNT(*) FROM leads WHERE customer_id = ? OR email = ?");
            $stmtImport = $mDb->prepare("INSERT INTO leads (customer_id, name, company, email, phone, source, status) VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($crmContacts as $crmC) {
                $check->execute([$crmC['customer_id'] ?? '', $crmC['email'] ?? '']);
                if ($check->fetchColumn() == 0) {
                    $localStatus = $crmC['account_status'] ?? 'Lead';
                    $stmtImport->execute([
                        $crmC['customer_id'] ?? null,
                        $crmC['contact_person'] ?? 'Unknown',
                        $crmC['company_name'] ?? 'Unspecified',
                        $crmC['email'] ?? '',
                        $crmC['phone'] ?? '',
                        $crmC['lead_source'] ?? 'Master CRM',
                        $localStatus
                    ]);
                    $importedCount++;
                }
            }
        });

        log_marketing_audit($marketingDb, 'CRM_SYNC', 'BATCH', 'SYNCED', "Imported $importedCount contacts from Master CRM");
        $_SESSION['notify'] = ['message' => "Sync complete! Imported $importedCount new contacts from Master CRM.", 'type' => 'success'];
        header("Location: ?page=leads");
        exit;
    } catch (Throwable $e) {
        $error = "CRM Synchronization failed: " . $e->getMessage();
    }
}

// 3. Handle Update Action (POST, CSRF Protected)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['notify'] = ['message' => 'Security Error: Invalid CSRF token.', 'type' => 'error'];
        header("Location: ?page=leads");
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $customer_id = trim($_POST['customer_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = trim($_POST['status'] ?? 'Lead');

    try {
        if ($id > 0) {
            // 1. Update Local Marketing DB
            $stmtLocal = $marketingDb->prepare("UPDATE leads SET name = ?, company = ?, email = ?, phone = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtLocal->execute([$name, $company, $email, $phone, $status, $id]);

            // 2. Update Master CRM (if customer_id exists)
            if (!empty($customer_id) && $crmDb) {
                $stmtCrm = $crmDb->prepare("UPDATE customers SET contact_person = ?, company_name = ?, email = ?, phone = ?, account_status = ? WHERE customer_id = ?");
                $stmtCrm->execute([$name, $company, $email, $phone, $status, $customer_id]);
            }

            log_marketing_audit($marketingDb, 'Lead', $customer_id ?: $id, 'UPDATED', "Lead updated and synced: $name ($company)");
            $_SESSION['notify'] = ['message' => "Lead details updated successfully!", 'type' => 'success'];
            header("Location: ?page=leads");
            exit;
        }
    } catch (Throwable $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch Lead for Editing
$editLead = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $marketingDb->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editLead = $stmt->fetch();
}
?>

<?php if ($editLead): ?>
    <!-- EDIT LEAD VIEW -->
    <header class="page-header">
        <h1>Edit Contact: <?= h($editLead['name']); ?></h1>
        <p>Updates will be synchronized across both Marketing Hub and Master CRM.</p>
    </header>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <section class="card lead-details-form" style="grid-column: span 12;">
            <h2>Lead Details</h2>
            <form action="?page=leads&action=update" method="POST" class="standard-form">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$editLead['id']; ?>">
                <input type="hidden" name="customer_id" value="<?= h($editLead['customer_id'] ?? ''); ?>">

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="name">Contact Name</label>
                        <input type="text" name="name" id="name" value="<?= h($editLead['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="company">Company</label>
                        <input type="text" name="company" id="company" value="<?= h($editLead['company'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" value="<?= h($editLead['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" name="phone" id="phone" value="<?= h($editLead['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Marketing Status</label>
                        <select name="status" id="status">
                            <option value="Lead" <?= ($editLead['status'] ?? '') === 'Lead' ? 'selected' : ''; ?>>Lead</option>
                            <option value="Customer" <?= ($editLead['status'] ?? '') === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                            <option value="Lost" <?= ($editLead['status'] ?? '') === 'Lost' ? 'selected' : ''; ?>>Lost / Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn-action">💾 Sync & Save Changes</button>
                    <a href="?page=leads" class="btn-small" style="line-height: 48px; padding: 0 20px;">Cancel</a>
                </div>
            </form>
        </section>
    </div>
<?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error); ?></div>
    <?php endif; ?>

    <header class="page-header">
        <h1>Lead Management</h1>
        <p>Track and manage your B2B prospects synced with the Master CRM.</p>
    </header>

    <div class="dashboard-grid">
        <!-- NEW LEAD FORM -->
        <section class="card">
            <h2>Capture New Lead</h2>
            <form action="?page=leads&action=add" method="POST" class="standard-form">
                <?= UI::csrf_field() ?>
                <div class="form-group">
                    <label for="name">Contact Name</label>
                    <input type="text" name="name" id="name" required placeholder="e.g. John Doe">
                </div>
                <div class="form-group">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" placeholder="e.g. Acme Corp">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" required placeholder="john@example.com">
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" name="phone" id="phone" placeholder="+1 (555) 000-0000">
                </div>
                <div class="form-group">
                    <label for="source">Lead Source</label>
                    <select name="source" id="source">
                        <option value="Marketing Hub">Marketing Hub</option>
                        <option value="LinkedIn">LinkedIn</option>
                        <option value="Email Outreach">Email Outreach</option>
                        <option value="Referral">Referral</option>
                    </select>
                </div>
                <button type="submit" class="btn-action">Add to CRM</button>
            </form>
        </section>

        <!-- LEADS TABLE -->
        <section class="card lead-pool-table">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h2 style="margin: 0;">Lead & Customer Pool</h2>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="text" id="leadSearch" placeholder="Search name, company..." style="width: 240px; min-height: 38px; font-size: 0.9rem; padding: 0 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-main);">
                    
                    <form action="?page=leads&action=sync" method="POST" style="margin: 0;">
                        <?= UI::csrf_field() ?>
                        <button type="submit" class="btn-small" style="background: var(--accent-tertiary); color: var(--accent-primary); cursor: pointer; border: none; padding: 8px 14px; font-weight: 600;">🔄 Sync CRM</button>
                    </form>
                </div>
            </div>

            <!-- Filtering Tabs -->
            <div class="tabs-container" style="margin-bottom: 1.5rem;">
                <button type="button" class="tab-btn active" onclick="filterLeads('all')">All Contacts</button>
                <button type="button" class="tab-btn" onclick="filterLeads('lead')">Leads Only</button>
                <button type="button" class="tab-btn" onclick="filterLeads('customer')">Customers Only</button>
            </div>

            <div class="table-responsive">
                <table class="data-table" id="leadsTable">
                    <thead>
                        <tr>
                            <th>Name & ID</th>
                            <th>Company</th>
                            <th>Contact Info</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $leads = [];
                        try {
                            $leads = $marketingDb->query("SELECT * FROM leads ORDER BY created_at DESC LIMIT 60")->fetchAll();
                        } catch (Throwable $e) {
                            error_log("Failed to fetch leads: " . $e->getMessage());
                        }

                        if (empty($leads)):
                        ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-dim);">No leads found. Click "🔄 Sync CRM" above to import existing contacts.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leads as $lead):
                                $rawStatus = strtolower($lead['status'] ?? '');
                                $isCustomer = (strpos($rawStatus, 'customer') !== false);
                                $badgeClass = $isCustomer ? 'badge-customer' : 'badge-lead';
                                $displayStatus = $isCustomer ? 'CUSTOMER' : strtoupper($lead['status'] ?? 'LEAD');
                            ?>
                            <tr class="lead-row" data-status="<?= $isCustomer ? 'customer' : 'lead'; ?>">
                                <td data-label="Name & ID">
                                    <div style="font-size: 0.7rem; color: var(--text-secondary);"><?= h($lead['customer_id'] ?? 'LOCAL'); ?></div>
                                    <strong class="searchable-name"><?= h($lead['name']); ?></strong>
                                </td>
                                <td data-label="Company" class="searchable-company"><?= h($lead['company'] ?? '—'); ?></td>
                                <td data-label="Contact Info">
                                    <a href="mailto:<?= h($lead['email']); ?>" style="display:block; font-size: 0.85rem; color: var(--accent-primary); text-decoration:none;">✉️ <?= h($lead['email']); ?></a>
                                    <?php if (!empty($lead['phone'])): ?>
                                        <a href="tel:<?= h($lead['phone']); ?>" style="display:block; font-size: 0.85rem; color: var(--text-dim); text-decoration:none; margin-top: 4px;">📞 <?= h($lead['phone']); ?></a>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status"><span class="badge <?= $badgeClass; ?>"><?= h($displayStatus); ?></span></td>
                                <td data-label="Added" style="font-size: 0.8rem; color: var(--text-secondary);"><?= date('M j, y', strtotime($lead['created_at'] ?? 'now')); ?></td>
                                <td style="text-align: right;">
                                    <a href="?page=leads&action=edit&id=<?= (int)$lead['id']; ?>" class="btn-small">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php endif; ?>

<script>
function filterLeads(filter) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if(btn.innerText.toLowerCase().includes(filter)) btn.classList.add('active');
        if(filter === 'all' && btn.innerText.includes('All')) btn.classList.add('active');
    });

    document.querySelectorAll('.lead-row').forEach(row => {
        if (filter === 'all' || row.dataset.status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Client search logic
const leadSearchInput = document.getElementById('leadSearch');
if (leadSearchInput) {
    leadSearchInput.addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.lead-row').forEach(row => {
            const nameEl = row.querySelector('.searchable-name');
            const compEl = row.querySelector('.searchable-company');
            const name = nameEl ? nameEl.innerText.toLowerCase() : '';
            const company = compEl ? compEl.innerText.toLowerCase() : '';
            if (name.includes(term) || company.includes(term)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}
</script>

<style>
.tabs-container {
    display: flex;
    gap: 0.5rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
}
.tab-btn {
    background: none;
    border: none;
    padding: 0.5rem 1rem;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-dim);
    border-radius: 6px;
    transition: all 0.2s;
}
.tab-btn:hover {
    background: rgba(0, 0, 0, 0.05);
}
.tab-btn.active {
    background: var(--accent-primary);
    color: white;
}
</style>
