# Modern WP Migrator

[![WordPress Version](https://img.shields.io/badge/WordPress-5.6%2B%20%7C%206.x-blue?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP Versions](https://img.shields.io/badge/PHP-7.4%20%7C%208.0%20%7C%208.1%20%7C%208.2%20%7C%208.3%2B-777bb4?logo=php&logoColor=white)](https://php.net)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Release](https://img.shields.io/badge/Release-v7.113.1-blue.svg)](https://github.com/luiskaco/modern-wp-migrator/releases)
[![Upload Limit](https://img.shields.io/badge/Upload%20Limit-500%20GB%20(Unlimited)-brightgreen.svg)](#features)

**Modern WP Migrator** is an open-source, unrestricted, and modernized WordPress migration and backup utility engineered for developers, sysadmins, and modern hosting environments.

Forked from the battle-tested 7.113 migration engine under the **GNU General Public License (GPLv3)**, this version removes artificial paywalls, unlocks file upload limits up to 500 GB, enables native 1-click restore directly from the dashboard, eliminates all telemetry and third-party advertising, and comes fully tested for **PHP 8.3+** and **WordPress 6.x+**.

---

## 🌟 Why Modern WP Migrator?

Most traditional migration plugins impose artificial limits on free users (such as capping file uploads at 512 MB or disabling the 1-click "Restore" button to push commercial licenses). 

**Modern WP Migrator** solves this by providing a clean, developer-focused experience:

- 🔓 **500 GB File Upload Limit**: Upload archives of virtually any size directly from your browser without artificial blocking modals.
- ⚡ **Direct 1-Click Backup Restoration**: Restore `.wpress` backups directly from your backup list in the WordPress administration panel.
- 🐘 **Modern PHP 8.x Optimized**: 100% clean syntax and execution on PHP 8.0, 8.1, 8.2, and 8.3+.
- 🧼 **Zero Bloat & Ad-Free**: No upsell notices, no fake grayed-out buttons, no cross-promotions, and no external tracking.
- 💾 **Safe & Chunked Streaming**: Files are processed in 512 KB chunks to avoid memory exhaustion (`memory_limit`) or script timeouts on budget servers.

---

## 📊 Feature Comparison

| Capability | Standard Free Plugins | Modern WP Migrator |
| :--- | :---: | :---: |
| **Max Upload Size** | 512 MB or host limit | **500 GB (Unlimited)** |
| **Direct 1-Click Restore** | 🔒 Paid extension only | **✅ Enabled out-of-the-box** |
| **Export Entire Site (.wpress)** | ✅ | **✅** |
| **Drag & Drop Archive Import** | Restricted by size | **✅ Unrestricted** |
| **PHP 8.3 Compatibility** | Often raises deprecations | **✅ Fully verified (0 errors)** |
| **Clean Admin Interface** | Ads, upsells, upsell menus | **✅ Distraction-free** |
| **System Status Widget** | Telemetry / feedback forms | **✅ Local environment metrics** |

---

## 🛠️ Requirements

- **WordPress**: 5.6 or higher (fully tested on WordPress 6.x)
- **PHP**: 7.4 to 8.3+
- **PHP Extensions**:
  - `curl` (for download operations)
  - `json` (for manifest and chunk index handling)
  - `mbstring` (for character encoding operations)
- **Web Server**: Apache, Nginx, LiteSpeed, or Laragon / LocalWP / Docker environments.

---

## 🚀 Installation

### Option 1: Upload via WordPress Admin (Recommended)

1. Download the latest `modern-wp-migrator.zip` from [Releases](https://github.com/luiskaco/modern-wp-migrator/releases).
2. In your WordPress admin panel, navigate to **Plugins > Add New > Upload Plugin**.
3. Choose `modern-wp-migrator.zip` and click **Install Now**.
4. Click **Activate Plugin**.
5. You will see **Site Migrator** in the WordPress sidebar.

### Option 2: Clone or Copy Manually

Clone the repository directly into your WordPress `wp-content/plugins/` folder:

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/luiskaco/modern-wp-migrator.git modern-wp-migrator
```

Then activate the plugin from **WordPress Admin > Plugins**.

---

## 📖 How to Use

### 1. Exporting a Site
1. Go to **Site Migrator > Export**.
2. *(Optional)* Use the **Find & Replace** fields if you are changing domains or database table prefixes.
3. Click **Export Site To > FILE**.
4. Download the generated `.wpress` file once compilation is complete.

### 2. Importing a Site
1. On your destination WordPress installation, go to **Site Migrator > Import**.
2. Drag and drop your `.wpress` archive or click **Import From > FILE**.
3. The chunked uploader will stream the file in 512 KB blocks.
4. Follow the on-screen prompts to complete the database and file restoration.

### 3. Backups & 1-Click Direct Restore
1. Go to **Site Migrator > Backups**.
2. Click **Create Backup** to generate a local snapshot stored in `wp-content/ai1wm-backups/`.
3. Click the three dots `...` next to any backup in the list and select **Restore** to directly roll back or apply that backup in 1 click!

---

## 🔬 Technical Enhancements & Architecture

### 1. Chunked Upload Pipeline
Archives are split into 512 KB binary chunks sent via `admin-ajax.php`. This bypasses strict web server post limits (`upload_max_filesize` and `post_max_size`) and ensures that large backups (even tens of gigabytes) transfer stably.

### 2. Unlocked File Size
- In the backend controller, the file size filter is unlocked:
  ```php
  'max_file_size' => apply_filters( 'ai1wm_max_file_size', 536870912000 ) // 500 GB
  ```
- In the frontend uploader script (`import.min.js`), artificial size threshold validations that previously triggered an upsell modal have been bypassed.

### 3. Native Restore Pipeline
The modal interception in `backups.min.js` has been replaced with a direct invocation of the import pipeline:
```javascript
Ai1wm.Restore = function(storage, archive) {
    var importInstance = new Ai1wm.Import({
        storage: storage,
        archive: archive,
        ai1wm_manual_restore: 1
    });
    importInstance.start();
};
```

---

## 🔒 Security & Privacy

- **No Remote Telemetry**: Does not send usage analytics, site domains, or database metadata to external servers.
- **Directory Protection**: The backup directory (`wp-content/ai1wm-backups/`) includes `.htaccess` and `web.config` rules to prevent unauthorized direct HTTP access.
- **Nonce & Capability Verification**: All AJAX calls strictly verify WordPress nonces and check for `export_site` or `import_site` administrator capabilities.

---

## 📄 License & Attribution

This project is licensed under the **GNU General Public License v3.0 (GPLv3)**. See the [LICENSE](LICENSE) file for complete details.

* **Attribution**: Based on the open-source migration engine originally developed by ServMask Inc., published under GPLv3. Modern WP Migrator is an independent community fork not affiliated with or endorsed by ServMask Inc. All trademarks belong to their respective owners.
