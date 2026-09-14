# Local Environment Setup Guide (XAMPP)

## 1. Overview
The IQA Metal Inventory & Label system is designed to run on a local network server. This guide explains how to set up the environment on a dedicated Windows machine using **XAMPP**.

By following these steps, any device on your local warehouse network (phones, tablets, other laptops) will be able to access the app via a web browser.

---

## 2. XAMPP Installation & Configuration
XAMPP provides the Apache web server and PHP environment needed for this app.

1. **Download & Install:** Download XAMPP for Windows (PHP 8.0+ recommended).
2. **Install Location:** Leave it at the default directory (`C:\xampp`).
3. **Start Services:** Open the XAMPP Control Panel and start the **Apache** service. (You do not need to start MySQL, as we use SQLite).

---

## 3. Configuring PHP for SQLite (`php.ini`)
SQLite does not require a background service, but PHP needs permission to talk to the `.sqlite` files.

1. In the XAMPP Control Panel, click the **Config** button next to Apache and select `PHP (php.ini)`.
2. A text editor will open. Search for the following lines:
   ```ini
   ;extension=pdo_sqlite
   ;extension=sqlite3
   ```
3. **Remove the preceding semicolon (`;`)** to uncomment and enable them:
   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```
4. Save the file and **Restart Apache** from the XAMPP Control Panel.

---

## 4. Enabling PowerShell Script Execution
Because this app generates `.odt` label files natively using PowerShell, the Windows server machine must allow scripts to run.

1. On the Windows Server machine, click Start and search for **PowerShell**.
2. Right-click it and select **Run as Administrator**.
3. Run the following command to check your current policy:
   ```powershell
   Get-ExecutionPolicy
   ```
   *If it says `Restricted`, PowerShell will block our template files from generating.*
4. Change the policy by running:
   ```powershell
   Set-ExecutionPolicy RemoteSigned
   ```
   *(Press `Y` to confirm when prompted).*

---

## 5. Deploying the Application Code
1. Open the XAMPP web root directory: `C:\xampp\htdocs\`
2. Create a new folder for the app: `C:\xampp\htdocs\app\`
3. Copy all the files from this repository into that folder.
4. Ensure the following directories have **read/write permissions** for PHP:
   - `/db/` — PHP must be able to create and write to the `.sqlite` files.
   - `/exports/labels/` — PHP writes generated `.odt` label files here.
   - `/templates/` — PowerShell reads master template files from here.
5. **CRITICAL — First Run:** The system is **Self-Healing**. Simply visit the app in your browser (`http://localhost/app/`), and it will automatically create any missing databases and tables.

---

## 6. System Health & Backups
The system includes a built-in **Self-Diagnosis Engine**.
1. **Health Check**: The landing page verifies database integrity on load.
2. **Settings Hub**: Navigate to **⚙️ System Settings** to create manual backups (stored in `/db/backups/`) or run deep database repairs.
3. **Recovery**: If a database file is accidentally deleted, simply refresh the app to rebuild the empty structure instantly.

---

## 7. Accessing the App on the Local Network
**On the Host Server Machine:**
Open a web browser and go to: `http://localhost/app/`

**From Other Devices on the Warehouse Network:**
1. On the host server machine, open Command Prompt (`cmd`) and type `ipconfig`.
2. Look for the **IPv4 Address** (e.g., `192.168.1.50`).
3. On any phone or laptop connected to the same WiFi/Network, open the browser and go to: `http://192.168.1.50/app/`
