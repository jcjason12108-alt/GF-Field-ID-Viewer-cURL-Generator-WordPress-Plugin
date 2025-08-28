<?php
/**
 * Plugin Name: GF Field ID Viewer + cURL Generator
 * Description: View Gravity Forms field IDs (including sub-IDs) and generate ready-to-run cURL examples (URL-encoded + multipart).
 * Version:     1.2.3
 * Author:      Jason Cox
 */

if ( ! defined('ABSPATH') ) exit;

/** ----- Capability helpers ----- */
function gf_fis_cap_gf() : string { return 'gravityforms_view_forms'; }
function gf_fis_cap_tools() : string { return 'manage_options'; }
function gf_fis_user_has_gf_cap() : bool { return current_user_can( gf_fis_cap_gf() ); }

/** ----- Menu registration ----- */
add_action('admin_menu', function () {
	// 1) Always add under Tools for admins.
	add_submenu_page(
		'tools.php',
		'GF Field ID Viewer',
		'GF Field ID Viewer',
		gf_fis_cap_tools(),
		'gf-field-id-viewer',
		'gf_field_id_viewer_admin_page'
	);

	// 2) Also add under Forms (GF) if user has GF capability.
	if ( class_exists('GFForms') && gf_fis_user_has_gf_cap() ) {
		add_submenu_page(
			'gf_edit_forms',
			'GF Field ID Viewer',
			'Field ID Viewer',
			gf_fis_cap_gf(),
			'gf-field-id-viewer',
			'gf_field_id_viewer_admin_page'
		);
	}
}, 100);

/** ----- Styling ----- */
add_action('admin_head', function () {
	if ( ! isset($_GET['page']) || $_GET['page'] !== 'gf-field-id-viewer' ) return; ?>
	<style>
		.gf-fis-wrap .notice{margin:1em 0;}
		.gf-fis-table th, .gf-fis-table td{vertical-align: top;}
		.gf-fis-subids{font-family:monospace;font-size:12px}
		.gf-fis-table .col-narrow{width:110px}
		.gf-fis-table .col-wide{width:60%}
		.gf-fis-form-select{min-width:260px}
		textarea.large-text.code{font-family:Menlo,Consolas,monospace}
		.gf-fis-block h3{margin-top:20px}
	</style>
<?php });

/** ----- Helpers ----- */
/**
 * Return true if the field is required.
 */
function gf_fis_field_is_required($field) : bool {
	// Works for both array and object style fields.
	if (is_array($field)) {
		return !empty($field['isRequired']);
	}
	if (is_object($field)) {
		return !empty($field->isRequired);
	}
	return false;
}

function gf_fis_has_fileupload($form) : bool {
	if (empty($form['fields'])) return false;
	foreach ($form['fields'] as $f) {
		$type = $f['type'] ?? (isset($f->type) ? $f->type : '');
		if ($type === 'fileupload') return true;
	}
	return false;
}

/**
 * Collect ALL non-file input_* pairs for a form with sensible mock values.
 * Includes optional fields. Skips honeypot/captcha/page/section and fileupload.
 */
function gf_fis_collect_all_inputs(array $form) : array {
	$kv = [];

	// helpers
	$get_first_choice_value = function($field){
		$choices = is_array($field) ? ($field['choices'] ?? []) : ($field->choices ?? []);
		if (!empty($choices) && is_array($choices)) {
			$first = $choices[0];
			if (is_array($first)) return $first['value'] ?? ($first['text'] ?? 'Example');
			return $first->value ?? ($first->text ?? 'Example');
		}
		return 'Example';
	};

	$ts   = current_time('timestamp');
	$mon  = date('n', $ts);
	$day  = date('j', $ts);
	$year = date('Y', $ts);
	$mdy  = date('m/d/Y', $ts);

	if (empty($form['fields']) || !is_array($form['fields'])) {
		return $kv;
	}

	foreach ($form['fields'] as $f) {
		$type   = $f['type'] ?? (isset($f->type) ? $f->type : '');
		$id     = $f['id'] ?? (isset($f->id) ? $f->id : '');
		$label  = strtolower($f['label'] ?? (isset($f->label) ? $f->label : ''));
		$inputs = $f['inputs'] ?? (isset($f->inputs) ? $f->inputs : null);

		// skip non-postable or file types here (file handled in multipart)
		if (in_array($type, ['honeypot','captcha','page','section'], true)) continue;
		if ($type === 'fileupload') continue;

		switch ($type) {
			case 'name':
				if (!empty($inputs)) {
					foreach ($inputs as $inp) {
						$iid  = is_array($inp) ? ($inp['id'] ?? '') : ($inp->id ?? '');
						$ilbl = strtolower( is_array($inp) ? ($inp['label'] ?? '') : ($inp->label ?? '') );
						if (!$iid) continue;
						if (strpos($ilbl,'first') !== false)   { $kv["input_{$iid}"] = 'Test'; continue; }
						if (strpos($ilbl,'last')  !== false)   { $kv["input_{$iid}"] = 'User'; continue; }
						if (strpos($ilbl,'prefix')!== false)   { $kv["input_{$iid}"] = 'Mr.'; continue; }
						if (strpos($ilbl,'middle')!== false)   { $kv["input_{$iid}"] = 'Q'; continue; }
						if (strpos($ilbl,'suffix')!== false)   { $kv["input_{$iid}"] = ''; continue; }
						$kv["input_{$iid}"] = 'example';
					}
				} elseif ($id !== '') {
					$kv["input_{$id}"] = 'Test User';
				}
				break;

			case 'email':
				if (!empty($inputs)) {
					foreach ($inputs as $inp) {
						$iid = is_array($inp) ? ($inp['id'] ?? '') : ($inp->id ?? '');
						if ($iid) $kv["input_{$iid}"] = 'test@example.com';
					}
				} elseif ($id !== '') {
					$kv["input_{$id}"] = 'test@example.com';
				}
				break;

			case 'phone':
				if ($id !== '') $kv["input_{$id}"] = '555-123-4567';
				break;

			case 'address':
				if (!empty($inputs)) {
					foreach ($inputs as $inp) {
						$iid  = is_array($inp) ? ($inp['id'] ?? '') : ($inp->id ?? '');
						$ilbl = strtolower( is_array($inp) ? ($inp['label'] ?? '') : ($inp->label ?? '') );
						if (!$iid) continue;
						$val = 'example';
						if (strpos($ilbl,'street') !== false)             $val = '123 Main St';
						elseif (strpos($ilbl,'address line 2') !== false) $val = 'Apt 4B';
						elseif (strpos($ilbl,'city') !== false)           $val = 'Anytown';
						elseif (strpos($ilbl,'state') !== false || strpos($ilbl,'province') !== false) $val = 'California';
						elseif (strpos($ilbl,'zip') !== false || strpos($ilbl,'postal') !== false)     $val = '90210';
						elseif (strpos($ilbl,'country') !== false)        $val = 'United States';
						$kv["input_{$iid}"] = $val;
					}
				} elseif ($id !== '') {
					$kv["input_{$id}"] = '123 Main St, Anytown, CA 90210';
				}
				break;

			case 'date':
				if (!empty($inputs)) {
					foreach ($inputs as $inp) {
						$iid  = is_array($inp) ? ($inp['id'] ?? '') : ($inp->id ?? '');
						$ilbl = strtolower( is_array($inp) ? ($inp['label'] ?? '') : ($inp->label ?? '') );
						if (!$iid) continue;
						if (strpos($ilbl,'month') !== false) { $kv["input_{$iid}"] = (string)$mon; continue; }
						if (strpos($ilbl,'day')   !== false) { $kv["input_{$iid}"] = (string)$day; continue; }
						if (strpos($ilbl,'year')  !== false) { $kv["input_{$iid}"] = (string)$year; continue; }
						$kv["input_{$iid}"] = $mdy;
					}
				} elseif ($id !== '') {
					$kv["input_{$id}"] = $mdy;
				}
				break;

			case 'time':
				if ($id !== '') $kv["input_{$id}"] = '09:00 AM';
				break;

			case 'select':
			case 'radio':
				if ($id !== '') $kv["input_{$id}"] = $get_first_choice_value($f);
				break;

			case 'checkbox':
				$choices = is_array($f) ? ($f['choices'] ?? []) : ($f->choices ?? []);
				$first_input = !empty($f['inputs']) ? $f['inputs'][0] : (!empty($f->inputs) ? $f->inputs[0] : null);
				if ($first_input && !empty($choices)) {
					$iid = is_array($first_input) ? ($first_input['id'] ?? '') : ($first_input->id ?? '');
					$val = is_array($choices[0]) ? ($choices[0]['value'] ?? $choices[0]['text'] ?? 'Example')
													: ($choices[0]->value ?? $choices[0]->text ?? 'Example');
					if ($iid) $kv["input_{$iid}"] = $val;
				}
				break;

			case 'consent':
				$first_input = !empty($inputs) ? $inputs[0] : null;
				$iid = $first_input ? (is_array($first_input) ? ($first_input['id'] ?? '') : ($first_input->id ?? '')) : '';
				if ($iid)       $kv["input_{$iid}"] = '1';
				elseif ($id)    $kv["input_{$id}"]  = '1';
				break;

			default:
				// Generic single-value fields (text, textarea, number, website, hidden, etc.)
				if (!empty($inputs) && is_array($inputs)) {
					foreach ($inputs as $inp) {
						$iid = is_array($inp) ? ($inp['id'] ?? '') : ($inp->id ?? '');
						if ($iid) $kv["input_{$iid}"] = 'example';
					}
				} elseif ($id !== '') {
					// Friendlier mocks for known fields in your grievance form
					if ($id == 7)       { $kv['input_7']  = 'BK-12345'; }
					elseif ($id == 9)   { $kv['input_9']  = 'Article 5, Section 2'; }
					elseif ($id == 10)  { $kv['input_10'] = 'Detailed description of the incident including dates, times, and witnesses.'; }
					elseif ($id == 15)  { $kv['input_15'] = 'Discussed with supervisor on 08/10; awaiting HR response.'; }
					elseif ($id == 16)  { $kv['input_16'] = 'Request reinstatement and back pay.'; }
					elseif ($id == 21 || strpos($label,'date of incident') !== false || strpos($label,'date') !== false) {
						$kv['input_' . $id] = $mdy; // for text-based date field
					} else {
						$kv['input_' . $id] = 'example';
					}
				}
		}
	}

	return $kv;
}

/**
 * Build urlencoded pairs (name=value) for the selected form.
 * Mirrors a working GF REST submission (POST x-www-form-urlencoded) and appends gform_submit=1.
 * IMPORTANT: Do NOT pre-encode values here; curl's --data-urlencode will encode them.
 */
function gf_fis_build_example_urlencoded_pairs($form) : array {
	$pairs = [];
	$kv = gf_fis_collect_all_inputs($form);
	foreach ($kv as $k => $v) {
		$pairs[] = $k . '=' . $v; // curl --data-urlencode will encode
	}
	$pairs[] = 'gform_submit=1';
	return $pairs;
}

/** Build a simple mock (non-file) payload for JSON / multipart demo */
function gf_fis_build_mock_payload($form) : array {
	$payload = gf_fis_collect_all_inputs($form);
	$payload['gform_submit'] = '1';
	return $payload;
}

/** ----- cURL renderer (dynamic per selected form) ----- */
function gf_fis_render_curl_block($form) {
	$site      = site_url();
	$form_id   = intval($form['id']);
	$endpoint  = $site . '/wp-json/gf/v2/forms/' . $form_id . '/submissions';
	$has_files = gf_fis_has_fileupload($form);

	echo '<div class="gf-fis-block">';
	echo '<h2>cURL Generator</h2>';
	echo '<p><strong>Endpoint:</strong> <code>' . esc_html($endpoint) . '</code></p>';
	echo '<p><strong>Auth:</strong> use a WordPress <em>Application Password</em>. Replace <code>BASE64_ENCODED_CREDENTIALS</code> with <code>base64_encode("user@example.com:APPLICATION_PASSWORD")</code> or use <code>-u "user@example.com:APPLICATION_PASSWORD"</code> instead of the header.</p>';

	// URL-ENCODED (WORKING STYLE)
	$url_pairs = gf_fis_build_example_urlencoded_pairs($form);
	$lines = [];
	foreach ($url_pairs as $p) {
		$lines[] = "  --data-urlencode '" . esc_attr($p) . "'";
	}
	$urlencoded_block = "curl -X POST \\\n"
		. "  '" . esc_url($endpoint) . "' \\\n"
		. "  -H 'Authorization: Basic BASE64_ENCODED_CREDENTIALS' \\\n"
		. "  -H 'Content-Type: application/x-www-form-urlencoded' \\\n"
		. implode(" \\\n", $lines);

	echo '<h3>URL-encoded cURL (matches n8n working example)</h3>';
	echo '<textarea id="gf-curl-urlencoded" class="large-text code" rows="12" readonly>'. esc_textarea($urlencoded_block) .'</textarea>';
	echo '<p><button type="button" class="button copy-curl" data-target="gf-curl-urlencoded">Copy URL-encoded cURL</button></p>';

	// JSON (optional demo)
	$mock_json = wp_json_encode(['input_values' => gf_fis_build_mock_payload($form)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	$json_block = "curl -X POST \\\n"
		. "  '" . esc_url($endpoint) . "' \\\n"
		. "  -H 'Authorization: Basic BASE64_ENCODED_CREDENTIALS' \\\n"
		. "  -H 'Content-Type: application/json' \\\n"
		. "  -d '" . $mock_json . "'";

	echo '<h3>JSON cURL (alternate GF REST format)</h3>';
	echo '<textarea id="gf-curl-json" class="large-text code" rows="12" readonly>'. esc_textarea($json_block) .'</textarea>';
  	echo '<p><button type="button" class="button copy-curl" data-target="gf-curl-json">Copy JSON cURL</button></p>';

	// MULTIPART (if files exist)
	if ($has_files) {
		$mp_lines = [];
		// Add non-file fields as -F key=value (all inputs)
		$payload = gf_fis_collect_all_inputs($form);
		foreach ($payload as $k => $v) {
			$mp_lines[] = "  -F '{$k}={$v}'";
		}
		$mp_lines[] = "  -F 'gform_submit=1'";
		// Add file fields as -F input_{id}=@/path/to/file.pdf
		foreach ($form['fields'] as $f) {
			$type = $f['type'] ?? (isset($f->type) ? $f->type : '');
			if ($type === 'fileupload') {
				$field_id = $f['id'] ?? (isset($f->id) ? $f->id : '');
				if ($field_id !== '') $mp_lines[] = "  -F 'input_{$field_id}=@/path/to/file.pdf'";
			}
		}
		$multipart_block = "curl -X POST \\\n"
			. "  '" . esc_url($endpoint) . "' \\\n"
			. "  -H 'Authorization: Basic BASE64_ENCODED_CREDENTIALS' \\\n"
			. implode(" \\\n", $mp_lines);

		echo '<h3>Multipart cURL (for file uploads)</h3>';
		echo '<textarea id="gf-curl-multipart" class="large-text code" rows="12" readonly>'. esc_textarea($multipart_block) .'</textarea>';
		echo '<p><button type="button" class="button copy-curl" data-target="gf-curl-multipart">Copy Multipart cURL</button></p>';
	}

	// Copy buttons handler
	?>
	<script>
	document.addEventListener('click', function(e){
	  var b = e.target.closest('.copy-curl');
	  if(!b) return;
	  var id = b.getAttribute('data-target');
	  var ta = document.getElementById(id);
	  if(!ta) return;
	  navigator.clipboard.writeText(ta.value).then(function(){
		var old = b.textContent;
		b.textContent = 'Copied!';
		setTimeout(function(){ b.textContent = old; }, 1200);
	  });
	});
	</script>
	<?php
	echo '</div>';
}

/** ----- Admin page ----- */
function gf_field_id_viewer_admin_page() {
	echo '<div class="wrap gf-fis-wrap"><h1>GF Field ID Viewer</h1>';

	if ( ! class_exists('GFAPI') ) {
		echo '<div class="notice notice-error"><p>Gravity Forms is required.</p></div></div>';
		return;
	}

	$forms = GFAPI::get_forms(true);
	$selected_id   = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
	$selected_form = $selected_id ? GFAPI::get_form($selected_id) : null;

	echo '<form method="get" action="">';
	echo '<input type="hidden" name="page" value="gf-field-id-viewer" />';
	echo '<label class="screen-reader-text" for="gf-fis-form">Select a form</label> ';
	echo '<select id="gf-fis-form" class="gf-fis-form-select" name="form_id">';
	echo '<option value="">— Select a form —</option>';
	foreach ($forms as $f) {
		printf(
			'<option value="%d"%s>%s (ID %d)</option>',
			intval($f['id']),
			selected($selected_id, $f['id'], false),
			esc_html($f['title']),
			intval($f['id'])
		);
	}
	echo '</select> ';
	submit_button('View', 'secondary', '', false);
	echo '</form>';

	if ( ! $selected_form ) { echo '</div>'; return; }

	echo '<h2 style="margin-top:20px;">Form: ' . esc_html($selected_form['title']) . ' (ID ' . intval($selected_form['id']) . ')</h2>';

	echo '<table class="widefat striped gf-fis-table" style="margin-top:12px">';
	echo '<thead><tr><th class="col-narrow">Field ID</th><th class="col-narrow">Type</th><th class="col-wide">Label</th><th>Sub-IDs / Inputs</th></tr></thead><tbody>';

	if (!empty($selected_form['fields'])) {
		foreach ($selected_form['fields'] as $f) {
			$id     = $f['id'] ?? (isset($f->id) ? $f->id : '');
			$type   = $f['type'] ?? (isset($f->type) ? $f->type : '');
			$label  = $f['label'] ?? (isset($f->label) ? $f->label : '');
			$inputs = $f['inputs'] ?? (isset($f->inputs) ? $f->inputs : null);

			echo '<tr>';
			echo '<td><code>' . esc_html($id) . '</code></td>';
			echo '<td>' . esc_html($type) . '</td>';
			echo '<td>' . esc_html($label) . '</td>';
			echo '<td class="gf-fis-subids">';
			if (!empty($inputs) && is_array($inputs)) {
				echo '<ul style="margin:0;padding-left:18px">';
				foreach ($inputs as $inp) {
					$iid    = is_array($inp) ? ($inp['id'] ?? '') : ($inp->id ?? '');
					$ilabel = is_array($inp) ? ($inp['label'] ?? '') : ($inp->label ?? '');
					if ($iid === '') continue;
					echo '<li><code>' . esc_html($iid) . '</code>'
						. ($ilabel ? ' — ' . esc_html($ilabel) : '')
						. ' (POST key: <code>input_' . esc_html($iid) . '</code>)'
						. '</li>';
				}
				echo '</ul>';
			} else {
				echo 'POST key: <code>input_' . esc_html($id) . '</code>';
			}
			echo '</td></tr>';
		}
	} else {
		echo '<tr><td colspan="4">No fields found.</td></tr>';
	}
	echo '</tbody></table>';

	gf_fis_render_curl_block($selected_form);

	echo '</div>';
}