=== Insert Database Records ===
Contributors: yourwordpressorgusername
Tags: csv import, database import, mysql, admin tools, custom tables
Requires at least: 6.5
Tested up to: 6.9.4
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Admin-only CSV import tool for validated inserts into custom MySQL tables, with batch logging and delete-last-import support.

== Description ==

This is a test!!!

Insert Database Records adds an admin-only screen under **Tools** that lets site administrators upload a CSV file, choose an allowed **custom** database table, validate the file against the selected table, insert one row per CSV row, and delete the most recent successful import batch when needed.

The plugin is designed for cases where you need a controlled import workflow for plugin-owned or custom application tables without exposing WordPress core tables to import actions.

Key features include:

* Adds **Tools → Insert Database Records** for a focused admin workflow.
* Restricts access to users with the `manage_options` capability.
* Shows only allowed **custom** tables. WordPress core tables are excluded.
* Requires a CSV header row and validates the file before any insert runs.
* Compares CSV columns against the destination table schema.
* Rejects invalid files before import and returns a clear admin message.
* Inserts one database row per CSV row.
* Logs each import as a batch for traceability.
* Adds a **Delete Last Inserted Records** action with a confirmation modal.
* Deletes only the most recent successful import batch for the selected table.
* Supports primary-key-based delete and a row-snapshot fallback strategy.

This plugin is best suited for administrators, developers, and site owners who manage custom tables created by plugins, integrations, or bespoke application logic.

= What problem this plugin solves =

Many import tools are designed for posts, users, WooCommerce content, or broad database operations. This plugin is intentionally narrower:

* It targets **custom MySQL tables** only.
* It blocks importing into default WordPress tables.
* It validates the CSV against the chosen table before insert.
* It records import batches so the last successful batch can be reversed.

That makes it useful for internal tools, custom dashboards, integration tables, reporting tables, and other plugin-managed datasets where a safer import process is more important than a generic bulk uploader.

= How it works =

1. An administrator opens **Tools → Insert Database Records**.
2. The plugin loads a server-generated allowlist of custom tables.
3. The administrator uploads a CSV file.
4. The plugin stores the uploaded file temporarily and returns a short-lived token.
5. The administrator validates the CSV against the selected table.
6. If validation passes, the administrator confirms the import.
7. The plugin inserts one record per CSV row.
8. The plugin logs the batch and row references for later deletion.
9. If needed, the administrator can delete the most recent successful batch for that selected table.

= Validation behavior =

Before import, the plugin validates:

* user capability
* uploaded file availability
* selected table allowlist membership
* CSV header presence
* destination schema availability
* unknown columns
* missing required columns
* generated column restrictions
* row column-count consistency
* basic type compatibility for common numeric and date fields
* duplicate risks for unique indexes when detectable

If validation fails, the import is not run.

= Important notes =

* This plugin is intended for **custom tables**, not WordPress core tables.
* Import safety depends on the quality of the target table schema, including primary keys and unique indexes.
* For delete-last-import behavior, tables with a reliable primary key provide the most deterministic rollback path.
* Large imports should be tested in a staging environment before production use.
* Always back up your database before running bulk imports.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or upload the ZIP file through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Make sure your custom destination tables already exist in the database.
4. Sign in as an Administrator.
5. Go to **Tools → Insert Database Records**.
6. Choose an allowed custom table.
7. Upload a CSV file with a header row.
8. Click **Validate CSV**.
9. If validation passes, click **Insert Records** and confirm.

== Frequently Asked Questions ==

= Who can use this plugin? =

Only users with the `manage_options` capability can access the screen or perform upload, validation, import, and delete actions.

= Can this plugin import into WordPress core tables such as `wp_posts` or `wp_users`? =

No. The plugin is designed to work with custom tables only and excludes core WordPress tables from the selectable list.

= Does the CSV need a header row? =

Yes. The first row should contain column names that match the destination table columns.

= What happens if the CSV is invalid for the selected table? =

The plugin stops before insert and returns a validation error message. No rows are inserted when validation fails.

= What does “Delete Last Inserted Records” remove? =

It removes only the most recent successful import batch for the selected table, not every row in that table.

= Does the plugin support rollback if the table has no primary key? =

The plugin stores row snapshots and can use a row-match fallback strategy, but a stable primary key is strongly recommended for the most reliable delete behavior.

= Can I use this for very large CSV files? =

Possibly, but large imports should be tested carefully. Server timeouts, memory limits, and database constraints can affect performance. Use a staging site first.

= Does the plugin create the destination custom tables for me? =

No. The destination tables should already exist before import. The plugin creates only its own internal logging tables.

= Does this plugin support multisite? =

The plugin excludes common multisite core tables from selection, but you should test carefully in your own multisite environment before production use.

= Is this a replacement for phpMyAdmin? =

No. It is a more focused WordPress-admin workflow inspired by the idea of choosing a destination table and importing one CSV into one table, but it is not a full database administration tool.

== Screenshots ==

1. Tools → Insert Database Records screen.
2. Table selection and CSV upload form.
3. Validation success message before import.
4. Import confirmation modal.
5. Successful import notice with batch result.
6. Delete Last Inserted Records confirmation modal.

== Changelog ==

= 0.2.0 =
* Added a real upload flow using a temporary upload token.
* Added schema-aware CSV validation before import.
* Added import batch logging and row-level tracking.
* Added delete-last-import support for the most recent successful batch.
* Added custom-table filtering and blocked WordPress core tables.
* Added admin-page workflow under the Tools menu.

= 0.1.0 =
* Initial scaffold release.
* Added plugin bootstrap, admin page, REST routes, validator, import service, and delete service foundation.

== Upgrade Notice ==

= 0.2.0 =
This update introduces the real upload flow, stronger validation, batch logging, and delete-last-import support. Test in staging before using on production data.

