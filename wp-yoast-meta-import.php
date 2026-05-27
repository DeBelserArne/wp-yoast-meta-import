<?php

/**
 * Plugin Name: Yoast SEO Meta Importer
 * Plugin URI:  https://github.com/DeBelserArne/wp-yoast-meta-import
 * Description: Upload an Excel file and interactively map columns to Yoast SEO title & meta description before importing.
 * Version:     1.0.0
 * Author:      Arne De Belser
 * License:     GPL-2.0+
 * Text Domain: yoast-meta-import
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YMI_VERSION', '1.0.0');
define('YMI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YMI_PLUGIN_URL', plugin_dir_url(__FILE__));

// --- Load the bundled XLSX reader ---
require_once YMI_PLUGIN_DIR . 'lib/SimpleXLSX.php';

// ============================================================
// 1. ADMIN MENU & PAGE
// ============================================================

add_action('admin_menu', 'ymi_add_admin_page');

function ymi_add_admin_page()
{
    add_management_page(
        __('Yoast SEO Meta Importer', 'yoast-meta-import'),
        __('SEO Meta Import', 'yoast-meta-import'),
        'manage_options',
        'yoast-meta-import',
        'ymi_render_admin_page'
    );
}

// ============================================================
// 2. ENQUEUE ASSETS
// ============================================================

add_action('admin_enqueue_scripts', 'ymi_enqueue_assets');

function ymi_enqueue_assets($hook)
{
    if ($hook !== 'tools_page_yoast-meta-import') {
        return;
    }

    wp_enqueue_style('ymi-admin', YMI_PLUGIN_URL . 'assets/css/admin.css', [], YMI_VERSION);
    wp_enqueue_script('ymi-admin', YMI_PLUGIN_URL . 'assets/js/admin.js', [], YMI_VERSION, true);
    wp_localize_script('ymi-admin', 'ymi_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ymi_ajax_nonce'),
    ]);
}

// ============================================================
// 3. RENDER THE ADMIN PAGE
// ============================================================

function ymi_render_admin_page()
{
?><div class="wrap ymi-wrap">
        <h1><?php _e('Yoast SEO Meta Importer', 'yoast-meta-import');
            ?></h1>
        <p class="ymi-intro"><?php _e('Upload an Excel file (.xlsx) to bulk-import Yoast SEO titles and meta descriptions. You will be able to review changes before they are saved.', 'yoast-meta-import');
                                ?></p>
        <div id="ymi-step-1" class="ymi-step">
            <div class="ymi-card">
                <h2><?php _e('Step 1: Upload & Map Columns', 'yoast-meta-import');
                    ?></h2>
                <form id="ymi-upload-form" enctype="multipart/form-data" method="post">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="ymi-file"><?php _e('Excel File (.xlsx)', 'yoast-meta-import');
                                                                    ?></label></th>
                            <td><input type="file" id="ymi-file" name="ymi_file" accept=".xlsx" required />
                                <p class="description"><?php _e('Select the .xlsx file containing your SEO metadata.', 'yoast-meta-import');
                                                        ?></p>
                            </td>
                        </tr>
                    </table>
                    <div id="ymi-mapping-section" style="display:none;">
                        <h3><?php _e('Column Mapping', 'yoast-meta-import');
                            ?></h3>
                        <p class="description"><?php _e('Map the columns from your spreadsheet to the corresponding fields.', 'yoast-meta-import');
                                                ?></p>
                        <table class="form-table" id="ymi-mapping-table">
                            <tr>
                                <th><?php _e('Field', 'yoast-meta-import');
                                    ?></th>
                                <th><?php _e('Spreadsheet Column', 'yoast-meta-import');
                                    ?></th>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Page URL', 'yoast-meta-import');
                                            ?></strong></td>
                                <td><select name="ymi_map_url" id="ymi-map-url"></select></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('SEO Title', 'yoast-meta-import');
                                            ?></strong></td>
                                <td><select name="ymi_map_title" id="ymi-map-title"></select></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Meta Description', 'yoast-meta-import');
                                            ?></strong></td>
                                <td><select name="ymi_map_desc" id="ymi-map-desc"></select></td>
                            </tr>
                        </table>
                    </div>
                    <p class="submit"><button type="submit" class="button button-primary" id="ymi-btn-upload"><?php _e('Upload & Preview', 'yoast-meta-import');
                                                                                                                ?></button><span class="spinner"></span></p>
                </form>
            </div>
        </div>
        <div id="ymi-step-2" class="ymi-step" style="display:none;">
            <div class="ymi-card">
                <h2><?php _e('Step 2: Preview & Import', 'yoast-meta-import');
                    ?></h2>
                <p class="description"><?php _e('Review the changes below. The table shows each page\'s current Yoast values alongside the new values from your spreadsheet. Uncheck any row you want to skip.', 'yoast-meta-import');
                                        ?></p>
                <div id="ymi-preview-stats"></div>
                <div id="ymi-preview-wrapper" style="overflow-x:auto;">
                    <table class="wp-list-table widefat fixed striped" id="ymi-preview-table">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" id="ymi-select-all" checked /></th>
                                <th><?php _e('Page / Post', 'yoast-meta-import');
                                    ?></th>
                                <th><?php _e('URL', 'yoast-meta-import');
                                    ?></th>
                                <th><?php _e('SEO Title', 'yoast-meta-import');
                                    ?></th>
                                <th><?php _e('Meta Description', 'yoast-meta-import');
                                    ?></th>
                            </tr>
                        </thead>
                        <tbody id="ymi-preview-tbody"></tbody>
                    </table>
                </div>
                <p class="submit" style="margin-top:20px;"><button type="button" class="button button-primary" id="ymi-btn-import"><?php _e('Import Selected Rows', 'yoast-meta-import');
                                                                                                                                    ?></button><button type="button" class="button" id="ymi-btn-back"><?php _e('← Back', 'yoast-meta-import');
                                                                                                                                                                                                        ?></button><span class="spinner"></span></p>
                <div id="ymi-import-results" style="display:none;"></div>
            </div>
        </div>
    </div><?php
        }

        // ============================================================
        // 4. AJAX: PARSE UPLOADED FILE
        // ============================================================

        add_action('wp_ajax_ymi_parse_file', 'ymi_ajax_parse_file');

        function ymi_ajax_parse_file()
        {
            check_ajax_referer('ymi_ajax_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions.']);
            }

            if (empty($_FILES['ymi_file'])) {
                wp_send_json_error(['message' => 'No file uploaded.']);
            }

            $file = $_FILES['ymi_file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => 'Upload error code: ' . $file['error']]);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'xlsx') {
                wp_send_json_error(['message' => 'Only .xlsx files are supported.']);
            }

            // Parse with SimpleXLSX
            $xlsx = new Shuchkin\SimpleXLSX($file['tmp_name']);

            if (!$xlsx) {
                wp_send_json_error(['message' => 'Failed to parse file: ' . Shuchkin\SimpleXLSX::parseError()]);
            }

            $rows = $xlsx->rows();

            if (empty($rows) || count($rows) < 2) {
                wp_send_json_error(['message' => 'File appears empty or has no data rows (needs at least a header row + 1 data row).']);
            }

            $headers = $rows[0];
            $data_rows = array_slice($rows, 1);

            // Store parsed data in a transient for later use
            $transient_key = 'ymi_data_' . get_current_user_id();
            set_transient($transient_key, [
                'headers' => $headers,
                'rows' => $data_rows,
                'timestamp' => time(),
            ], HOUR_IN_SECONDS);

            wp_send_json_success([
                'headers' => $headers,
                'row_count' => count($data_rows),
                'transient_key' => $transient_key,
            ]);
        }

        // ============================================================
        // 5. AJAX: PREVIEW (resolve URLs, get current Yoast values)
        // ============================================================

        add_action('wp_ajax_ymi_preview', 'ymi_ajax_preview');

        function ymi_ajax_preview()
        {
            check_ajax_referer('ymi_ajax_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions.']);
            }

            $map_url = sanitize_text_field($_POST['map_url'] ?? '');
            $map_title = sanitize_text_field($_POST['map_title'] ?? '');
            $map_desc = sanitize_text_field($_POST['map_desc'] ?? '');

            if (empty($map_url) || empty($map_title) || empty($map_desc)) {
                wp_send_json_error(['message' => 'All column mappings are required.']);
            }

            $transient_key = sanitize_text_field($_POST['transient_key'] ?? '');
            $data = get_transient($transient_key);

            if (!$data) {
                wp_send_json_error(['message' => 'Session expired. Please re-upload the file.']);
            }

            $headers = $data['headers'];
            $rows = $data['rows'];

            // Find column indexes by header name
            $idx_url = array_search($map_url, $headers);
            $idx_title = array_search($map_title, $headers);
            $idx_desc = array_search($map_desc, $headers);

            if ($idx_url === false || $idx_title === false || $idx_desc === false) {
                wp_send_json_error(['message' => 'Column mapping error: one or more headers not found.']);
            }

            $site_url = untrailingslashit(home_url());
            $preview = [];

            foreach ($rows as $row) {
                $raw_url   = trim($row[$idx_url] ?? '');
                $new_title = trim($row[$idx_title] ?? '');
                $new_desc  = trim($row[$idx_desc] ?? '');

                if (empty($raw_url)) {
                    continue;
                }

                // Resolve URL to a WordPress entity (post, home, archive, etc.)
                $entity = ymi_resolve_url($raw_url, $site_url);
                $current = ymi_get_yoast_values($entity);

                $entry = [
                    'url'            => $raw_url,
                    'entity_type'    => $entity['entity_type'],
                    'entity_id'      => $entity['id'],
                    'entity_label'   => $entity['label'] ?: $raw_url,
                    'post_type'      => $entity['post_type'] ?? '',
                    'taxonomy'       => $entity['taxonomy'] ?? '',
                    'current_title'  => $current['title'],
                    'current_desc'   => $current['desc'],
                    'new_title'      => $new_title,
                    'new_desc'       => $new_desc,
                    'status'         => $entity['entity_type'] !== 'unknown' ? 'found' : 'not_found',
                ];

                $preview[] = $entry;
            }

            // Store the mapped data for the import step
            $import_transient = 'ymi_import_' . get_current_user_id();
            set_transient($import_transient, [
                'preview' => $preview,
                'timestamp' => time(),
            ], HOUR_IN_SECONDS);

            wp_send_json_success([
                'preview' => $preview,
                'import_key' => $import_transient,
                'found_count' => count(array_filter($preview, fn($e) => $e['status'] === 'found')),
                'notfound_count' => count(array_filter($preview, fn($e) => $e['status'] === 'not_found')),
            ]);
        }

        // ============================================================
        // 6. AJAX: DO THE IMPORT
        // ============================================================

        add_action('wp_ajax_ymi_import', 'ymi_ajax_import');

        function ymi_ajax_import()
        {
            check_ajax_referer('ymi_ajax_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Insufficient permissions.']);
            }

            $import_key = sanitize_text_field($_POST['import_key'] ?? '');
            $selected = json_decode(wp_unslash($_POST['selected'] ?? '[]'), true);

            if (empty($selected) || !is_array($selected)) {
                wp_send_json_error(['message' => 'No rows selected for import.']);
            }

            $data = get_transient($import_key);

            if (!$data) {
                wp_send_json_error(['message' => 'Session expired. Please re-upload the file.']);
            }

            $preview = $data['preview'];
            $selected_set = array_flip($selected);

            $updated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($preview as $index => $entry) {
                if (!isset($selected_set[$index])) {
                    $skipped++;
                    continue;
                }

                if ($entry['status'] === 'not_found' || $entry['entity_type'] === 'unknown') {
                    $errors[] = sprintf(__('Row %d: Could not resolve URL "%s" to any page, archive, or taxonomy.', 'yoast-meta-import'), $index + 1, $entry['url']);
                    continue;
                }

                $entity = [
                    'entity_type' => $entry['entity_type'],
                    'id'          => $entry['entity_id'],
                    'post_type'   => $entry['post_type'] ?? '',
                    'taxonomy'    => $entry['taxonomy'] ?? '',
                ];
                ymi_set_yoast_values($entity, $entry['new_title'], $entry['new_desc']);

                $updated++;
            }

            // Clean up transient
            delete_transient($import_key);

            wp_send_json_success([
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        }

        // ============================================================
        // 7. HELPER: Resolve any URL to a WordPress entity
        // ============================================================
        // Returns [ 'entity_type' => 'post'|'home'|'ptarchive'|'taxonomy',
        //           'id' => post_id|term_id,  'label' => human label,
        //           'post_type'/'taxonomy' => string,  'slug' => string ]

        function ymi_resolve_url($url, $site_url)
        {
            // Extract only the path from the URL — works regardless of domain
            // (e.g. https://climatoni.be/realisaties/ → /realisaties/)
            $parsed = parse_url($url);
            $path   = isset($parsed['path']) ? '/' . trim($parsed['path'], '/') : '/';

            // --- Home page ---
            if ($path === '/' || $path === '') {
                $front_page_id = get_option('page_on_front');
                if ($front_page_id) {
                    $post = get_post($front_page_id);
                    return [
                        'entity_type' => 'post',
                        'id'          => $front_page_id,
                        'label'       => $post ? $post->post_title : 'Front Page',
                        'post_type'   => 'page',
                        'slug'        => '',
                    ];
                }
                return [
                    'entity_type' => 'home',
                    'id'          => 0,
                    'label'       => 'Homepage (Blog)',
                    'post_type'   => '',
                    'slug'        => '',
                ];
            }

            $slug = trim($path, '/');

            // --- Try as a regular post/page ---
            $post_id = url_to_postid($site_url . '/' . $slug);
            if (!$post_id) {
                $post_id = url_to_postid($site_url . '/' . $slug . '/');
            }
            if (!$post_id) {
                $post_id = url_to_postid($site_url . '/' . rtrim($slug, '/'));
            }
            if (!$post_id && !empty($slug)) {
                $parts = explode('/', $slug);
                $final = end($parts);
                global $wpdb;
                $post_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_status = 'publish' AND post_type IN ('post','page') LIMIT 1",
                    $final
                ));
            }

            if ($post_id) {
                $post = get_post($post_id);
                return [
                    'entity_type' => 'post',
                    'id'          => (int) $post_id,
                    'label'       => $post ? $post->post_title : 'Post #' . $post_id,
                    'post_type'   => $post ? $post->post_type : '',
                    'slug'        => $slug,
                ];
            }

            // --- Try as a post type archive ---
            // CPT archives have URLs like /realisaties/ — match against rewrite rules
            $slug_parts = explode('/', $slug);
            $first_slug = $slug_parts[0];

            $post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
            foreach ($post_types as $pt) {
                $archive_slug = $pt->has_archive;
                if (is_string($archive_slug) && $archive_slug === $first_slug) {
                    return [
                        'entity_type' => 'ptarchive',
                        'id'          => 0,
                        'label'       => $pt->labels->name . ' Archive',
                        'post_type'   => $pt->name,
                        'slug'        => $slug,
                    ];
                }
                if ($archive_slug === true && $pt->rewrite['slug'] === $first_slug) {
                    return [
                        'entity_type' => 'ptarchive',
                        'id'          => 0,
                        'label'       => $pt->labels->name . ' Archive',
                        'post_type'   => $pt->name,
                        'slug'        => $slug,
                    ];
                }
            }

            // --- Try as a taxonomy term archive ---
            $taxonomies = get_taxonomies(['public' => true], 'objects');
            foreach ($taxonomies as $tax) {
                $tax_slug = $tax->rewrite['slug'] ?? $tax->name;
                if (strpos($slug, $tax_slug . '/') === 0) {
                    $term_slug = substr($slug, strlen($tax_slug) + 1);
                    $term = get_term_by('slug', $term_slug, $tax->name);
                    if ($term && !is_wp_error($term)) {
                        return [
                            'entity_type' => 'taxonomy',
                            'id'          => $term->term_id,
                            'label'       => $term->name,
                            'taxonomy'    => $tax->name,
                            'slug'        => $slug,
                        ];
                    }
                }
            }

            // No match
            return [
                'entity_type' => 'unknown',
                'id'          => 0,
                'label'       => '',
                'slug'        => $slug,
            ];
        }

        /**
         * Get current Yoast SEO values for a resolved entity.
         */
        function ymi_get_yoast_values($entity)
        {
            $title = '';
            $desc  = '';

            switch ($entity['entity_type']) {
                case 'post':
                    $title = get_post_meta($entity['id'], '_yoast_wpseo_title', true) ?: '';
                    $desc  = get_post_meta($entity['id'], '_yoast_wpseo_metadesc', true) ?: '';
                    break;

                case 'home':
                    $wpseo = get_option('wpseo_titles', []);
                    $title = $wpseo['title-home-wpseo'] ?? '';
                    $desc  = $wpseo['metadesc-home-wpseo'] ?? '';
                    break;

                case 'ptarchive':
                    $wpseo = get_option('wpseo_titles', []);
                    $pt    = $entity['post_type'];
                    $title = $wpseo["title-ptarchive-{$pt}"] ?? '';
                    $desc  = $wpseo["metadesc-ptarchive-{$pt}"] ?? '';
                    break;

                case 'taxonomy':
                    $title = get_term_meta($entity['id'], '_yoast_wpseo_title', true) ?: '';
                    $desc  = get_term_meta($entity['id'], '_yoast_wpseo_metadesc', true) ?: '';
                    // Fallback: Yoast also uses wpseo_taxonomy_meta option
                    if (empty($title) || empty($desc)) {
                        $tax_meta = get_option('wpseo_taxonomy_meta', []);
                        $t = $entity['taxonomy'] ?? '';
                        $tid = $entity['id'];
                        if (empty($title)) {
                            $title = $tax_meta[$t][$tid]['wpseo_title'] ?? '';
                        }
                        if (empty($desc)) {
                            $desc = $tax_meta[$t][$tid]['wpseo_desc'] ?? '';
                        }
                    }
                    break;
            }

            return ['title' => $title, 'desc' => $desc];
        }

        /**
         * Write Yoast SEO values for a resolved entity.
         */
        function ymi_set_yoast_values($entity, $title, $desc)
        {
            switch ($entity['entity_type']) {
                case 'post':
                    if (!empty($title)) {
                        update_post_meta($entity['id'], '_yoast_wpseo_title', $title);
                    }
                    if (!empty($desc)) {
                        update_post_meta($entity['id'], '_yoast_wpseo_metadesc', $desc);
                    }
                    break;

                case 'home':
                    $wpseo = get_option('wpseo_titles', []);
                    if (!empty($title)) {
                        $wpseo['title-home-wpseo'] = $title;
                    }
                    if (!empty($desc)) {
                        $wpseo['metadesc-home-wpseo'] = $desc;
                    }
                    update_option('wpseo_titles', $wpseo);
                    break;

                case 'ptarchive':
                    $wpseo = get_option('wpseo_titles', []);
                    $pt = $entity['post_type'];
                    if (!empty($title)) {
                        $wpseo["title-ptarchive-{$pt}"] = $title;
                    }
                    if (!empty($desc)) {
                        $wpseo["metadesc-ptarchive-{$pt}"] = $desc;
                    }
                    update_option('wpseo_titles', $wpseo);
                    break;

                case 'taxonomy':
                    if (!empty($title)) {
                        update_term_meta($entity['id'], '_yoast_wpseo_title', $title);
                        // Also update Yoast's taxonomy meta option
                        $tax_meta = get_option('wpseo_taxonomy_meta', []);
                        $t = $entity['taxonomy'] ?? '';
                        $tax_meta[$t][$entity['id']]['wpseo_title'] = $title;
                        update_option('wpseo_taxonomy_meta', $tax_meta);
                    }
                    if (!empty($desc)) {
                        update_term_meta($entity['id'], '_yoast_wpseo_metadesc', $desc);
                        $tax_meta = get_option('wpseo_taxonomy_meta', []);
                        $t = $entity['taxonomy'] ?? '';
                        $tax_meta[$t][$entity['id']]['wpseo_desc'] = $desc;
                        update_option('wpseo_taxonomy_meta', $tax_meta);
                    }
                    break;
            }
        }
