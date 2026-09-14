<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $conn = Database::customers();

    // Full schema for customers
    $conn->exec("CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id TEXT NOT NULL UNIQUE,
        company_name TEXT NOT NULL,
        website TEXT,
        contact_person TEXT,
        address TEXT,
        email TEXT,
        phone TEXT,
        shipping_address TEXT,
        internal_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // CRM Migration
    $cols = $conn->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC);
    $has_cb = false; $has_msg = false;
    foreach($cols as $c) {
        if ($c['name'] === 'callback_date') $has_cb = true;
        if ($c['name'] === 'message_date') $has_msg = true;
    }
    if (!$has_cb) $conn->exec("ALTER TABLE customers ADD COLUMN callback_date TEXT DEFAULT ''");
    if (!$has_msg) $conn->exec("ALTER TABLE customers ADD COLUMN message_date TEXT DEFAULT ''");

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'register') {
        // 1. Simple Security Check
        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            $_SESSION['message'] = "<div class='alert error'>Security Error: Invalid form submission. Please try again.</div>";
            header("Location: index.php?view=register");
            exit();
        }

        // 2. Generate internal customer ID
        $internal_id = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $data = [
            ':cid' => $internal_id,
            ':company' => $_POST['company_name'],
            ':web' => $_POST['website'] ?? '',
            ':contact' => $_POST['contact_person'] ?? '',
            ':addr' => $_POST['address'] ?? '',
            ':email' => $_POST['email'] ?? '',
            ':phone' => $_POST['phone'] ?? '',
            ':ship' => $_POST['shipping_address'] ?? '',
            ':notes' => $_POST['internal_notes'] ?? '',
            ':cb_date' => $_POST['callback_date'] ?? '',
            ':msg_date' => $_POST['message_date'] ?? ''
        ];

        $sql = "INSERT INTO customers (customer_id, company_name, website, contact_person, address, email, phone, shipping_address, internal_notes, callback_date, message_date)
                VALUES (:cid, :company, :web, :contact, :addr, :email, :phone, :ship, :notes, :cb_date, :msg_date)";

        $stmt = $conn->prepare($sql);
        try {
            if ($stmt->execute($data)) {
                // AUTOMATIC FRESH BATCH CREATION
                $conn_o = Database::orders();
                $new_order_id = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

                $stmt_o = $conn_o->prepare("INSERT INTO orders (order_id, customer_id, status) VALUES (?, ?, 'active')");
                $stmt_o->execute([$new_order_id, $internal_id]);

                // Direct redirect to order entry for the new customer
                header("Location: index.php?customer_id=" . urlencode($internal_id) . "&order_id=" . $new_order_id);
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "<div class='alert error'>Update failed: " . $e->getMessage() . "</div>";
        }
        header("Location: index.php?view=register");
        exit();
    }
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

$message = $_SESSION['message'] ?? "";
unset($_SESSION['message']);
?>

<div class="form-side" style="grid-column: span 2;">

    <?php echo $message; ?>

    <form action="" method="POST" class="grid-form">
        <input type="hidden" name="action" value="register">
        <?= UI::csrf_field() ?>

        <div class="form-row">
            <div class="form-group" style="grid-column: span 2;">
                <label for="company_name">Company Name*</label>
                <input type="text" id="company_name" name="company_name" placeholder="Full legal name" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="contact_person">Contact Person</label>
                <input type="text" id="contact_person" name="contact_person" placeholder="Primary contact">
            </div>
            <div class="form-group">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" placeholder="https://...">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="contact@company.com">
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" placeholder="+1 (555) 000-0000">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="callback_date">Next Callback Date</label>
                <input type="date" id="callback_date" name="callback_date">
            </div>
            <div class="form-group">
                <label for="message_date">Last Message Date</label>
                <input type="date" id="message_date" name="message_date">
            </div>
        </div>

        <div class="form-group">
            <label for="address">Business Address</label>
            <input type="text" id="address" name="address" placeholder="Street, City, Zip">
        </div>

        <div class="form-group">
            <label for="shipping_address">Shipping Address (If different)</label>
            <input type="text" id="shipping_address" name="shipping_address" placeholder="Drop-off location">
        </div>

        <div class="form-group">
            <label for="internal_notes">Internal Notes</label>
            <textarea id="internal_notes" name="internal_notes" placeholder="Any special instructions..."></textarea>
        </div>

        <input type="submit" value="Register & Save Customer">
        <a href="index.php" style="margin-top: 10px; display: block; text-align:center; color: var(--text-secondary); text-decoration:none; font-size: 0.9rem;">Back to Active Customer List</a>
    </form>
</div>

<style>
.grid-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
textarea {
    min-height: 80px;
    padding: 12px;
    border-radius: var(--border-radius-md);
    border: 1px solid var(--border-color);
    font-family: inherit;
    resize: vertical;
}
</style>
