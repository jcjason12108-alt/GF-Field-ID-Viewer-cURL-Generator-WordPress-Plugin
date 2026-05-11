=== GF Field ID Viewer + cURL Generator ===
Contributors: Jason Cox
Tags: gravity forms, field ids, curl, rest api
Requires at least: 6.0
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 1.2.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

View Gravity Forms field IDs, including sub-IDs, and generate ready-to-run cURL examples for Gravity Forms REST submissions.

== Description ==

GF Field ID Viewer + cURL Generator adds an admin screen for viewing Gravity Forms field IDs and generating URL-encoded, JSON, and multipart cURL examples for form submissions.

== Installation ==

1. Upload the `gf-field-id-viewer` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Open Tools > GF Field ID Viewer, or Forms > Field ID Viewer when Gravity Forms is active.

== Changelog ==

= 1.2.5 =
* Improved the field list layout with search, type filtering, compact field rows, and POST key copy buttons.

= 1.2.4 =
* Added Plugin Update Checker for automatic updates from GitHub.
* Enabled branch-only update checks from the `main` branch.
* Added optional GitHub token authentication via `PLUGIN_UPDATE_GITHUB_TOKEN`.
* Updated WordPress compatibility metadata to 6.9.4.
* Replaced credential-like cURL auth examples with a placeholder.
* Added capability validation in the admin page callback.

= 1.2.3 =
* Initial packaged version.
