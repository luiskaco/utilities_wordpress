=== Modern WP Migrator ===
Contributors: modern-dev
Tags: backup, clone, migrate, move-wordpress, export-import, php8
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 7.113.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A modern, fast, and unrestricted site migration and backup tool for WordPress. Optimized for PHP 8.x and modern web environments.

== Description ==

**Modern WP Migrator** is an independent, streamlined fork engineered for developers and site owners who need reliable, unrestricted backup and migration capabilities without artificial paywalls.

### Key Features:

* **Unrestricted Uploads**: Import archives of any size (up to 500 GB) without artificial file limits.
* **Direct 1-Click Restore**: Restore backups directly from your backup list in the admin panel.
* **Modern PHP 8.x Compatibility**: Tested and optimized for modern PHP runtimes (PHP 8.0 a 8.5).
* **Chunked Streaming**: Efficient 512KB chunk processing ensures stability even on memory-constrained servers.
* **Database & File Integrity**: Preserves serialized data, multisite structures, and media libraries cleanly.
* **Zero Upsell Nagging**: Clean, distraction-free administration dashboard.

== Installation ==

1. Upload the `modern-wp-migrator` folder to the `/wp-content/plugins/` directory, or upload the ZIP via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access **Site Migrator** in the WordPress admin sidebar to Export, Import, or manage Backups.

== Changelog ==

= 7.113.0 =
* Initial Modern WP Migrator release based on engine 7.113.
* Removed artificial upload size restrictions (unlocked to 500 GB).
* Enabled direct backup restoration from dashboard.
* Cleaned out marketing links and upsell prompts.
* Optimized for PHP 8.3 in Laragon and modern server environments.
