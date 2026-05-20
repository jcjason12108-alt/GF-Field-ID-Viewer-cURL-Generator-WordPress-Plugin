# GF Field ID Viewer + cURL Generator (WordPress Plugin)

**Version:** 1.2.8  
**Author:** Jason Cox  
**License:** GPLv2 or later  

Adds an admin tool for **Gravity Forms** to:  
1. View **field IDs** (including sub-IDs for compound fields like Name, Address, etc.).  
2. Generate ready-to-run **cURL examples** (URL-encoded, JSON, and multipart for file uploads) for testing form submissions via the **Gravity Forms REST API**.  

---

## Features
- **Field ID Viewer**
  - Lists every field, type, label, and sub-ID for the selected Gravity Form.  
  - Shows the exact `input_{id}` POST keys used in API submissions.  
- **cURL Generator**
  - Builds example requests for the selected form:
    - **URL-encoded** (matches working n8n examples).  
    - **JSON payloads** (`application/json`).  
    - **Multipart** examples when file upload fields exist.  
  - Includes placeholder/mock values for fields (dates, names, emails, addresses, checkboxes, etc.).  
  - Copy-to-clipboard buttons for each cURL block.  
- Integrates under both:
  - **Tools → GF Field ID Viewer** (for admins).  
  - **Forms → Field ID Viewer** (inside Gravity Forms menu, if user has GF permissions).  

---

## Installation
1. Ensure **Gravity Forms** is installed and active.  
2. Download the release ZIP from GitHub Releases.  
3. In WordPress: **Plugins → Add New → Upload Plugin** and select the ZIP.  
4. Activate the plugin.  
5. Go to **Tools → GF Field ID Viewer** (or inside the GF menu) to start.  

---

## Usage
1. Select a form from the dropdown.  
2. The plugin displays:
   - **Field IDs**, labels, types, and sub-inputs.  
   - POST keys (`input_#`) for use in API requests.  
3. Scroll down to see the **cURL Generator** section with three ready-to-run examples:  
   - **URL-encoded** (`application/x-www-form-urlencoded`) — best for direct testing or n8n.  
   - **JSON** (`application/json`) — alternate format for GF REST.  
   - **Multipart** — auto-generated when file upload fields are present.  
4. Replace mock values with real data, update auth credentials, and run.  

---

## Example Output
### URL-encoded cURL
```bash
curl -X POST \
  'https://example.com/wp-json/gf/v2/forms/3/submissions' \
  -H 'Authorization: Basic BASE64_ENCODED_CREDENTIALS' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'input_1=Test' \
  --data-urlencode 'input_2=User' \
  --data-urlencode 'gform_submit=1'

JSON cURL

curl -X POST \
  'https://example.com/wp-json/gf/v2/forms/3/submissions' \
  -H 'Authorization: Basic BASE64_ENCODED_CREDENTIALS' \
  -H 'Content-Type: application/json' \
  -d '{"input_values":{"input_1":"Test","input_2":"User","gform_submit":"1"}}'


⸻

Requirements
	•	WordPress 6.0+
	•	Gravity Forms 2.5+
	•	PHP 7.4+

⸻

Changelog

1.2.8
	•	Updated WordPress compatibility metadata to 7.0 and hardened admin output/loading guards.

1.2.7
	•	Hardened admin request handling by unslashing the selected form ID before sanitizing it.

1.2.6
	•	Added novice-friendly cURL help panels that explain command parts, authentication, and when to use each request format.

1.2.5
	•	Improved the field list layout with search, type filtering, compact field rows, and POST key copy buttons.

1.2.4
	•	Added Plugin Update Checker for automatic updates from GitHub.
	•	Enabled branch-only update checks from the main branch.
	•	Replaced credential-like cURL auth examples with placeholders.
	•	Updated WordPress compatibility metadata to 6.9.4.

1.2.3
	•	Added copy-to-clipboard buttons for all cURL examples.
	•	Expanded mock value logic for grievance/incident-style fields.
	•	Improved admin UI and styling.

1.0.0
	•	Initial release with field ID viewer and cURL generator.

⸻

License

GPLv2 or later — see LICENSE for details.
