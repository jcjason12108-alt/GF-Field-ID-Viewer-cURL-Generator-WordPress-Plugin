<?php
/**
 * Plugin Name: GF Field ID Viewer + cURL Generator
 * Plugin URI:  https://github.com/jcjason12108-alt/GF-Field-ID-Viewer-cURL-Generator-WordPress-Plugin
 * Description: View Gravity Forms field IDs (including sub-IDs) and generate ready-to-run cURL examples (URL-encoded + multipart).
 * Version:     1.2.4
 * Requires at least: 6.0
 * Tested up to: 6.9.4
 * Requires PHP: 7.4
 * Author:      Jason Cox
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gf-field-id-viewer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

$gf_fis_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/jcjason12108-alt/GF-Field-ID-Viewer-cURL-Generator-WordPress-Plugin/',
	__FILE__,
	'gf-field-id-viewer'
);
$gf_fis_update_checker->setBranch( 'main' );

add_filter(
	$gf_fis_update_checker->getUniqueName( 'vcs_update_detection_strategies' ),
	static function ( array $strategies ): array {
		return isset( $strategies['branch'] ) ? array( 'branch' => $strategies['branch'] ) : $strategies;
	}
);

$gf_fis_github_token = defined( 'PLUGIN_UPDATE_GITHUB_TOKEN' )
	? PLUGIN_UPDATE_GITHUB_TOKEN
	: getenv( 'PLUGIN_UPDATE_GITHUB_TOKEN' );

if ( ! empty( $gf_fis_github_token ) ) {
	$gf_fis_update_checker->setAuthentication( $gf_fis_github_token );
}

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
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'gf-field-id-viewer' !== $page ) {
		return;
	}
	?>
	<style>
		.gf-fis-wrap .notice{margin:1em 0;}
		.gf-fis-wrap{max-width:1280px;}
		.gf-fis-form-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:14px 0 22px;}
		.gf-fis-form-select{min-width:min(560px,100%);}
		.gf-fis-panel{background:#fff;border:1px solid #c3c4c7;margin-top:14px;}
		.gf-fis-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 16px;border-bottom:1px solid #dcdcde;}
		.gf-fis-panel-title{margin:0;font-size:18px;line-height:1.35;}
		.gf-fis-stats{display:flex;gap:8px;flex-wrap:wrap;}
		.gf-fis-stat{background:#f0f0f1;border:1px solid #dcdcde;border-radius:3px;padding:4px 8px;font-size:12px;color:#50575e;}
		.gf-fis-controls{display:grid;grid-template-columns:minmax(220px,1fr) minmax(160px,220px);gap:10px;padding:12px 16px;border-bottom:1px solid #dcdcde;background:#f6f7f7;}
		.gf-fis-controls input,.gf-fis-controls select{width:100%;}
		.gf-fis-field-list{display:grid;gap:0;}
		.gf-fis-field-card{display:grid;grid-template-columns:minmax(240px,1fr) minmax(220px,340px);gap:18px;padding:14px 16px;border-bottom:1px solid #dcdcde;background:#fff;}
		.gf-fis-field-card:nth-child(even){background:#fbfbfc;}
		.gf-fis-field-card:last-child{border-bottom:0;}
		.gf-fis-field-main{display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:start;min-width:0;}
		.gf-fis-field-id{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:28px;padding:0 8px;border-radius:3px;background:#f0f0f1;border:1px solid #dcdcde;font:600 13px/1.2 Menlo,Consolas,monospace;color:#1d2327;}
		.gf-fis-field-label{font-weight:600;font-size:14px;line-height:1.35;color:#1d2327;overflow-wrap:anywhere;}
		.gf-fis-field-meta{display:flex;gap:6px;flex-wrap:wrap;margin-top:5px;color:#646970;font-size:12px;}
		.gf-fis-pill{display:inline-flex;align-items:center;border:1px solid #dcdcde;background:#f6f7f7;border-radius:3px;padding:2px 6px;line-height:1.3;}
		.gf-fis-post-key{display:flex;align-items:center;justify-content:flex-end;gap:8px;min-width:0;}
		.gf-fis-key-group{display:flex;align-items:center;gap:6px;min-width:0;}
		.gf-fis-key-label{font-size:12px;color:#646970;white-space:nowrap;}
		.gf-fis-key{display:inline-block;max-width:100%;padding:3px 6px;background:#f0f0f1;border-radius:3px;font:13px/1.4 Menlo,Consolas,monospace;color:#1d2327;overflow-wrap:anywhere;}
		.gf-fis-copy-key.button{min-height:28px;padding:0 8px;}
		.gf-fis-subinput-grid{grid-column:1 / -1;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px;margin-top:2px;padding-left:46px;}
		.gf-fis-subinput{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px;border:1px solid #dcdcde;background:#fff;border-radius:3px;min-width:0;}
		.gf-fis-subinput-info{min-width:0;}
		.gf-fis-subinput-title{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:4px;color:#1d2327;}
		.gf-fis-empty{padding:18px 16px;color:#646970;}
		@media (max-width: 782px){
			.gf-fis-controls,.gf-fis-field-card{grid-template-columns:1fr;}
			.gf-fis-post-key{justify-content:flex-start;padding-left:46px;}
			.gf-fis-subinput-grid{padding-left:0;}
		}
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

function gf_fis_shell_single_quote( $value ) : string {
	return "'" . str_replace( "'", "'\"'\"'", (string) $value ) . "'";
}

/** ----- cURL renderer (dynamic per selected form) ----- */
function gf_fis_render_curl_block($form) {
	$site      = site_url();
	$form_id   = intval($form['id']);
	$endpoint  = $site . '/wp-json/gf/v2/forms/' . $form_id . '/submissions';
	$has_files = gf_fis_has_fileupload($form);
	$auth_header = 'Authorization: Basic BASE64_ENCODED_CREDENTIALS';

	echo '<div class="gf-fis-block">';
	echo '<h2>cURL Generator</h2>';
	echo '<p><strong>Endpoint:</strong> <code>' . esc_html($endpoint) . '</code></p>';
	echo '<p><strong>Auth:</strong> use a WordPress <em>Application Password</em>. Replace <code>BASE64_ENCODED_CREDENTIALS</code> with <code>base64_encode("user@example.com:APPLICATION_PASSWORD")</code> or use <code>-u "user@example.com:APPLICATION_PASSWORD"</code> instead of the header.</p>';

	// URL-ENCODED (WORKING STYLE)
	$url_pairs = gf_fis_build_example_urlencoded_pairs($form);
	$lines = [];
	foreach ($url_pairs as $p) {
		$lines[] = '  --data-urlencode ' . gf_fis_shell_single_quote( $p );
	}
	$urlencoded_block = "curl -X POST \\\n"
		. '  ' . gf_fis_shell_single_quote( $endpoint ) . " \\\n"
		. '  -H ' . gf_fis_shell_single_quote( $auth_header ) . " \\\n"
		. "  -H 'Content-Type: application/x-www-form-urlencoded' \\\n"
		. implode(" \\\n", $lines);

	echo '<h3>URL-encoded cURL (matches n8n working example)</h3>';
	echo '<textarea id="gf-curl-urlencoded" class="large-text code" rows="12" readonly>'. esc_textarea($urlencoded_block) .'</textarea>';
	echo '<p><button type="button" class="button copy-curl" data-target="gf-curl-urlencoded">Copy URL-encoded cURL</button></p>';

	// JSON (optional demo)
	$mock_json = wp_json_encode(['input_values' => gf_fis_build_mock_payload($form)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	$json_block = "curl -X POST \\\n"
		. '  ' . gf_fis_shell_single_quote( $endpoint ) . " \\\n"
		. '  -H ' . gf_fis_shell_single_quote( $auth_header ) . " \\\n"
		. "  -H 'Content-Type: application/json' \\\n"
		. '  -d ' . gf_fis_shell_single_quote( $mock_json );

	echo '<h3>JSON cURL (alternate GF REST format)</h3>';
	echo '<textarea id="gf-curl-json" class="large-text code" rows="12" readonly>'. esc_textarea($json_block) .'</textarea>';
  	echo '<p><button type="button" class="button copy-curl" data-target="gf-curl-json">Copy JSON cURL</button></p>';

	// MULTIPART (if files exist)
	if ($has_files) {
		$mp_lines = [];
		// Add non-file fields as -F key=value (all inputs)
		$payload = gf_fis_collect_all_inputs($form);
		foreach ($payload as $k => $v) {
			$mp_lines[] = '  -F ' . gf_fis_shell_single_quote( $k . '=' . $v );
		}
		$mp_lines[] = "  -F 'gform_submit=1'";
		// Add file fields as -F input_{id}=@/path/to/file.pdf
		foreach ($form['fields'] as $f) {
			$type = $f['type'] ?? (isset($f->type) ? $f->type : '');
			if ($type === 'fileupload') {
				$field_id = $f['id'] ?? (isset($f->id) ? $f->id : '');
				if ($field_id !== '') $mp_lines[] = '  -F ' . gf_fis_shell_single_quote( 'input_' . $field_id . '=@/path/to/file.pdf' );
			}
		}
		$multipart_block = "curl -X POST \\\n"
			. '  ' . gf_fis_shell_single_quote( $endpoint ) . " \\\n"
			. '  -H ' . gf_fis_shell_single_quote( $auth_header ) . " \\\n"
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
	if ( ! current_user_can( gf_fis_cap_tools() ) && ! current_user_can( gf_fis_cap_gf() ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gf-field-id-viewer' ) );
	}

	echo '<div class="wrap gf-fis-wrap"><h1>GF Field ID Viewer</h1>';

	if ( ! class_exists('GFAPI') ) {
		echo '<div class="notice notice-error"><p>Gravity Forms is required.</p></div></div>';
		return;
	}

	$forms = GFAPI::get_forms(true);
	$selected_id   = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
	$selected_form = $selected_id ? GFAPI::get_form($selected_id) : null;

	echo '<form class="gf-fis-form-bar" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
	echo '<input type="hidden" name="page" value="gf-field-id-viewer" />';
	echo '<label for="gf-fis-form"><strong>Form</strong></label> ';
	echo '<select id="gf-fis-form" class="gf-fis-form-select" name="form_id">';
	echo '<option value="">Select a form</option>';
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

	$field_count = ! empty( $selected_form['fields'] ) && is_array( $selected_form['fields'] ) ? count( $selected_form['fields'] ) : 0;
	$input_count = count( gf_fis_collect_all_inputs( $selected_form ) );
	$field_types = [];

	if ( ! empty( $selected_form['fields'] ) && is_array( $selected_form['fields'] ) ) {
		foreach ( $selected_form['fields'] as $field_for_type ) {
			$field_type = $field_for_type['type'] ?? ( isset( $field_for_type->type ) ? $field_for_type->type : '' );
			if ( '' !== $field_type ) {
				$field_types[ $field_type ] = true;
			}
		}
	}

	ksort( $field_types );

	echo '<section class="gf-fis-panel">';
	echo '<div class="gf-fis-panel-head">';
	echo '<h2 class="gf-fis-panel-title">' . esc_html($selected_form['title']) . ' <span class="gf-fis-pill">ID ' . intval($selected_form['id']) . '</span></h2>';
	echo '<div class="gf-fis-stats">';
	echo '<span class="gf-fis-stat">' . esc_html( number_format_i18n( $field_count ) ) . ' fields</span>';
	echo '<span class="gf-fis-stat">' . esc_html( number_format_i18n( $input_count ) ) . ' POST keys</span>';
	echo '</div></div>';
	echo '<div class="gf-fis-controls">';
	echo '<label class="screen-reader-text" for="gf-fis-field-search">Search fields</label>';
	echo '<input id="gf-fis-field-search" type="search" placeholder="Search label, field ID, type, or POST key" autocomplete="off" />';
	echo '<label class="screen-reader-text" for="gf-fis-type-filter">Filter by field type</label>';
	echo '<select id="gf-fis-type-filter"><option value="">All field types</option>';
	foreach ( array_keys( $field_types ) as $field_type ) {
		echo '<option value="' . esc_attr( strtolower( $field_type ) ) . '">' . esc_html( $field_type ) . '</option>';
	}
	echo '</select></div>';
	echo '<div class="gf-fis-field-list" id="gf-fis-field-list">';

	if (!empty($selected_form['fields'])) {
		foreach ($selected_form['fields'] as $f) {
			$id     = $f['id'] ?? (isset($f->id) ? $f->id : '');
			$type   = $f['type'] ?? (isset($f->type) ? $f->type : '');
			$label  = $f['label'] ?? (isset($f->label) ? $f->label : '');
			$inputs = $f['inputs'] ?? (isset($f->inputs) ? $f->inputs : null);
			$is_required = gf_fis_field_is_required( $f );
			$search_bits = [ $id, $type, $label, 'input_' . $id ];

			if (!empty($inputs) && is_array($inputs)) {
				foreach ($inputs as $inp) {
					$iid    = is_array($inp) ? ($inp['id'] ?? '') : ($inp->id ?? '');
					$ilabel = is_array($inp) ? ($inp['label'] ?? '') : ($inp->label ?? '');
					$search_bits[] = $iid;
					$search_bits[] = $ilabel;
					$search_bits[] = 'input_' . $iid;
				}
			}

			echo '<article class="gf-fis-field-card" data-type="' . esc_attr( strtolower( $type ) ) . '" data-search="' . esc_attr( strtolower( implode( ' ', array_filter( array_map( 'strval', $search_bits ) ) ) ) ) . '">';
			echo '<div class="gf-fis-field-main">';
			echo '<span class="gf-fis-field-id">' . esc_html($id) . '</span>';
			echo '<div><div class="gf-fis-field-label">' . esc_html($label ? $label : '(No label)') . '</div>';
			echo '<div class="gf-fis-field-meta"><span class="gf-fis-pill">' . esc_html($type ? $type : 'unknown') . '</span>';
			if ( $is_required ) {
				echo '<span class="gf-fis-pill">Required</span>';
			}
			if ( ! empty( $inputs ) && is_array( $inputs ) ) {
				echo '<span class="gf-fis-pill">' . esc_html( number_format_i18n( count( $inputs ) ) ) . ' sub-inputs</span>';
			}
			echo '</div></div></div>';

			echo '<div class="gf-fis-post-key">';
			if (!empty($inputs) && is_array($inputs)) {
				echo '<span class="gf-fis-pill">Compound field</span>';
			} else {
				$post_key = 'input_' . $id;
				echo '<div class="gf-fis-key-group"><span class="gf-fis-key-label">POST key</span><code class="gf-fis-key">' . esc_html( $post_key ) . '</code><button type="button" class="button gf-fis-copy-key" data-copy="' . esc_attr( $post_key ) . '">Copy</button></div>';
			}
			echo '</div>';

			if (!empty($inputs) && is_array($inputs)) {
				echo '<div class="gf-fis-subinput-grid">';
				foreach ($inputs as $inp) {
					$iid    = is_array($inp) ? ($inp['id'] ?? '') : ($inp->id ?? '');
					$ilabel = is_array($inp) ? ($inp['label'] ?? '') : ($inp->label ?? '');
					if ($iid === '') continue;
					$post_key = 'input_' . $iid;
					echo '<div class="gf-fis-subinput">';
					echo '<div class="gf-fis-subinput-info"><div class="gf-fis-subinput-title"><code class="gf-fis-key">' . esc_html($iid) . '</code>' . ( $ilabel ? '<span>' . esc_html($ilabel) . '</span>' : '' ) . '</div>';
					echo '<div class="gf-fis-key-group"><span class="gf-fis-key-label">POST key</span><code class="gf-fis-key">' . esc_html( $post_key ) . '</code></div></div>';
					echo '<button type="button" class="button gf-fis-copy-key" data-copy="' . esc_attr( $post_key ) . '">Copy</button>';
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</article>';
		}
	} else {
		echo '<div class="gf-fis-empty">No fields found.</div>';
	}
	echo '<div class="gf-fis-empty" id="gf-fis-no-results" hidden>No matching fields.</div>';
	echo '</div></section>';
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function(){
		var search = document.getElementById('gf-fis-field-search');
		var typeFilter = document.getElementById('gf-fis-type-filter');
		var cards = Array.prototype.slice.call(document.querySelectorAll('.gf-fis-field-card'));
		var noResults = document.getElementById('gf-fis-no-results');

		function filterCards(){
			var query = search ? search.value.trim().toLowerCase() : '';
			var type = typeFilter ? typeFilter.value : '';
			var visible = 0;

			cards.forEach(function(card){
				var matchesSearch = !query || card.getAttribute('data-search').indexOf(query) !== -1;
				var matchesType = !type || card.getAttribute('data-type') === type;
				var show = matchesSearch && matchesType;
				card.hidden = !show;
				if (show) visible++;
			});

			if (noResults) {
				noResults.hidden = visible !== 0;
			}
		}

		if (search) search.addEventListener('input', filterCards);
		if (typeFilter) typeFilter.addEventListener('change', filterCards);

		document.addEventListener('click', function(e){
			var button = e.target.closest('.gf-fis-copy-key');
			if (!button) return;
			navigator.clipboard.writeText(button.getAttribute('data-copy')).then(function(){
				var old = button.textContent;
				button.textContent = 'Copied';
				setTimeout(function(){ button.textContent = old; }, 1200);
			});
		});
	});
	</script>
	<?php

	gf_fis_render_curl_block($selected_form);

	echo '</div>';
}
