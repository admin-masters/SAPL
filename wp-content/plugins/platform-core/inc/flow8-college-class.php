<?php
/**
 * Flow 8 — Expert provides college class
 * What this does:
 *  - Seeds an Amelia Service "Remote College Class" (approval required) if not present
 *  - Shows experts a list of their college classes (from Flow 7) with Zoom checks and reschedule guidance
 *  - Provides a post-class "Upload Materials" panel, stores a class_session CPT, and emails the college
 *  - Adds a webhook endpoint for Amelia appointment updates to keep Google Calendar in sync on reschedule
 *
 * Shortcodes:
 *   [platform_expert_college_classes]  -> expert-facing panel (list + upload)
 *
 * REST:
 *   POST /platform-core/v1/amelia/appointment-updated  (optional webhook; patches calendar on reschedule)
 *   POST /platform-core/v1/college/refresh-zoom        (attempt fetch/refresh Zoom link for a booking)
 */

if (!defined('ABSPATH')) { exit; }

// Helper function to check if user is expert
if (!function_exists('platform_core_user_is_expert')) {
    function platform_core_user_is_expert() {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        return in_array('expert', (array) $user->roles) || current_user_can('manage_options');
    }
}

// CRITICAL FIX 1: Cache Amelia customer ID to prevent repeated API calls
function platform_core_get_cached_amelia_customer_id($wp_user_id, $user_payload) {
    $cache_key = 'amelia_customer_'.$wp_user_id;
    $cached = get_transient($cache_key);
    if ($cached) {
        return $cached;
    }

    $customer = platform_core_amelia_find_or_create_customer($user_payload);
    if (!empty($customer['id'])) {
        set_transient($cache_key, $customer['id'], DAY_IN_SECONDS);
        return $customer['id'];
    }

    return null;
}

class PlatformCore_Flow8_CollegeClass {

    const CPT_CLASS_SESSION = 'class_session';

    // Reuse Flow-7 options for the service mapping
    const OPTS_KEY                        = 'platform_core_college_settings';
    const OPT_REMOTE_CLASS_SERVICE_ID     = 'remote_class_service_id';

    private $tbl_requests;
    private $tbl_contracts;
    private $tbl_calendar_map;

    public function __construct() {
        global $wpdb;
        $this->tbl_requests     = $wpdb->prefix . 'platform_requests';
        $this->tbl_contracts    = $wpdb->prefix . 'platform_contracts';
        $this->tbl_calendar_map = $wpdb->prefix . 'platform_calendar_map';

        // Check if required tables exist
        if (!$this->verify_tables_exist()) {
            add_action('admin_notices', [$this, 'show_missing_tables_notice']);
            return; // Don't initialize if tables are missing
        }

        add_action('init',               [$this, 'register_cpt']);
        add_action('admin_init',         [$this, 'maybe_seed_remote_service']);
        add_shortcode('platform_expert_college_classes', [$this, 'sc_expert_panel']);

        add_action('rest_api_init',      [$this, 'register_routes']);

        // Optional: gentle reminder after class with no materials in 24h
        add_action('platform_core_hourly', [$this, 'remind_missing_materials']);
    }

    /* -----------------------------
     *  Verify tables exist
     * --------------------------- */
    private function verify_tables_exist() {
        global $wpdb;
        $tables = [
            $this->tbl_requests,
            $this->tbl_contracts,
            $this->tbl_calendar_map
        ];
        
        foreach ($tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
                return false;
            }
        }
        return true;
    }

    public function show_missing_tables_notice() {
        echo '<div class="notice notice-error"><p><strong>Platform Core Flow 8:</strong> Required database tables are missing. Please ensure Flow 7 is properly installed and activated first.</p></div>';
    }

    /* -----------------------------
     *  CPT: class_session
     * --------------------------- */
    public function register_cpt() {
        register_post_type(self::CPT_CLASS_SESSION, [
            'label'         => 'Class Sessions',
            'public'        => false,
            'show_ui'       => true,
            'capability_type'=> 'post',
            'supports'      => ['title','editor','author'],
            'menu_position' => 27,
            'menu_icon'     => 'dashicons-portfolio'
        ]);
    }

    /* -----------------------------
     *  CRITICAL FIX 2: Service must be retrieved from options, NOT created during user flow
     * --------------------------- */
    public function maybe_seed_remote_service() {
        $opts = get_option(self::OPTS_KEY, []);
        if (!empty($opts[self::OPT_REMOTE_CLASS_SERVICE_ID])) {
            return;
        }
        if (!class_exists('\AmeliaBooking\Infrastructure\WP\AmeliaBookingPlugin')) {
            return; // Amelia not active in this environment
        }

        // Try to find an existing service by name first
        $serviceId = $this->amelia_find_service_id_by_name('Remote College Class');
        if (!$serviceId) {
            $serviceId = $this->amelia_create_remote_class_service();
        }
        if ($serviceId) {
            $opts[self::OPT_REMOTE_CLASS_SERVICE_ID] = (int)$serviceId;
            update_option(self::OPTS_KEY, $opts);
        }
    }

    private function amelia_find_service_id_by_name($name) {
        // Adjust to your existing Amelia wrapper if available
        if (function_exists('platform_core_amelia_list_services')) {
            $services = platform_core_amelia_list_services();
        } else {
            // Fallback: query Amelia REST
            $services = $this->amelia_http_get('/services');
        }
        if (!is_array($services)) return 0;
        foreach ($services as $s) {
            // both wrapper and REST can differ—support common shapes
            $sName = '';
            if (isset($s['name'])) {
                $sName = $s['name'];
            } elseif (isset($s['translations']) && is_array($s['translations']) && isset($s['translations']['name'])) {
                $sName = $s['translations']['name'];
            }
            
            $id = (int)($s['id'] ?? 0);
            if ($id && is_string($sName) && trim(strtolower($sName)) === trim(strtolower($name))) {
                return $id;
            }
        }
        return 0;
    }

    private function amelia_create_remote_class_service() {
        $payload = [
            // Minimal payload; tailor fields as per your Amelia version
            'name'              => 'Remote College Class',
            // depending on Amelia version either 'status' or 'show' is used — include both to be safe
            'status'            => 'visible',
            'show'              => false,
            'duration'          => 60,         // minutes (Flow 7 overrides per contract)
            'maxCapacity'       => 100,        // lecture size
            'minCapacity'       => 1,
            'settings'          => [
                'bookingsApprovalRequired' => true,
                'recurringEnabled'         => false
            ]
        ];
        if (function_exists('platform_core_amelia_create_service')) {
            $res = platform_core_amelia_create_service($payload);
            return (int)($res['id'] ?? 0);
        }
        $res = $this->amelia_http_post('/services', $payload);
        // Support different response shapes
        if (is_array($res)) {
            if (!empty($res['data']['service']['id'])) return (int)$res['data']['service']['id'];
            if (!empty($res['id'])) return (int)$res['id'];
            if (!empty($res['data']['id'])) return (int)$res['data']['id'];
        }
        return 0;
    }

    // CRITICAL FIX 4: Add 429 rate limit hard stop
    private function amelia_http_get($path) {
        $url = platform_core_amelia_api_base($path);
        $args = ['timeout' => 20, 'headers' => platform_core_amelia_api_headers()];
        $resp = wp_remote_get($url, $args);
        
        if (is_wp_error($resp)) return null;
        
        // CRITICAL: Check for 429 rate limit
        $code = wp_remote_retrieve_response_code($resp);
        if ($code === 429) {
            error_log('[FLOW8] AMELIA RATE LIMITED (429) — ABORTING');
            return null;
        }
        
        $body = wp_remote_retrieve_body($resp);
        platform_core_log_amelia('Flow8 GET ' . $path, $body);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) return null;
        // Normalize common shapes
        if (isset($decoded['data']['services'])) return $decoded['data']['services'];
        if (isset($decoded['data']['items'])) return $decoded['data']['items'];
        if (isset($decoded['data'])) return $decoded['data'];
        return $decoded;
    }

    // CRITICAL FIX 4: Add 429 rate limit hard stop
    private function amelia_http_post($path, $payload) {
        $url = platform_core_amelia_api_base($path);
        $args = [
            'timeout' => 25,
            'headers' => array_merge(['Content-Type' => 'application/json'], platform_core_amelia_api_headers()),
            'body' => wp_json_encode($payload)
        ];
        platform_core_log_amelia('Flow8 POST ' . $path . ' payload', $payload);
        $resp = wp_remote_post($url, $args);
        
        if (is_wp_error($resp)) {
            platform_core_log_amelia('Flow8 POST error ' . $path, $resp->get_error_message());
            return null;
        }
        
        // CRITICAL: Check for 429 rate limit
        $code = wp_remote_retrieve_response_code($resp);
        if ($code === 429) {
            error_log('[FLOW8] AMELIA RATE LIMITED (429) — ABORTING');
            return null;
        }
        
        $body = wp_remote_retrieve_body($resp);
        platform_core_log_amelia('Flow8 POST ' . $path . ' response', $body);
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    /* -----------------------------
     *  Expert panel: list classes + materials upload
     * --------------------------- */
    public function sc_expert_panel() {
        if (!is_user_logged_in()) {
            return '<div class="notice notice-error"><p>Please sign in to view this page.</p></div>';
        }
        
        if (!platform_core_user_is_expert() && !current_user_can('manage_options')) {
            return '<div class="notice notice-error"><p>You need an Expert account to view this page.</p></div>';
        }

        // Handle file uploads first (POST)
        $notice = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pc_action'])) {
            if ($_POST['pc_action'] === 'pc_upload_materials') {
                $notice = $this->handle_upload_materials();
            } elseif ($_POST['pc_action'] === 'pc_refresh_zoom') {
                $notice = $this->handle_refresh_zoom();
            }
        }

        global $wpdb;
        $expertId = get_current_user_id();
        // Show all classes that have contracts (pending_contract, booked)
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, c.class_start_iso, c.duration_minutes, c.status AS contract_status, 
                   cm.google_event_id, cm.zoom_url, r.appointment_id, c.order_id
            FROM {$this->tbl_requests} r
            JOIN {$this->tbl_contracts} c  ON c.request_id = r.id
            LEFT JOIN {$this->tbl_calendar_map} cm ON cm.source = 'flow7_college' AND cm.object_id = r.id
            WHERE r.expert_user_id = %d 
            AND r.status IN ('pending_contract', 'booked')
            ORDER BY c.class_start_iso DESC
        ", $expertId));

        ob_start();
        if ($notice) {
            echo $notice;
        }
        ?>
        <style>
        .pc-expert-classes { margin: 20px 0; }
        .pc-expert-classes table { width: 100%; border-collapse: collapse; }
        .pc-expert-classes table th,
        .pc-expert-classes table td { padding: 10px; text-align: left; }
        .pc-expert-classes .pc-zoom-missing { color: #d63638; }
        .pc-expert-classes .pc-zoom-link { color: #2271b1; }
        .pc-upload-form { margin: 10px 0; padding: 10px; background: #f0f0f1; border-radius: 4px; }
        .pc-upload-form label { display: block; margin: 8px 0; }
        .pc-upload-form textarea { width: 100%; max-width: 400px; }
        </style>
        
        <div class="pc-expert-classes">
            <h3>Your College Classes</h3>
            
            <?php if ($rows && count($rows) > 0): ?>
                <div class="notice notice-info" style="margin-bottom: 20px;">
                    <p><strong>Class Status Guide:</strong></p>
                    <ul style="margin: 10px 0;">
                        <li><strong style="color: orange;">Awaiting Payment</strong> - College is reviewing/signing contract</li>
                        <li><strong style="color: green;">Confirmed</strong> - Payment completed, class scheduled</li>
                    </ul>
                    <p><em>You can only upload materials after a confirmed class has ended.</em></p>
                </div>
            <?php endif; ?>
            
            <?php if (!$rows || count($rows) === 0): ?>
                <div class="notice notice-info">
                    <p>No classes yet. Waiting for colleges to request classes from you.</p>
                    <p><small>Your Expert User ID: <?php echo (int)$expertId; ?></small></p>
                </div>
            <?php else: ?>
            
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th><th>Topic</th><th>College</th><th>Date/Time</th><th>Duration</th><th>Status</th><th>Contract</th><th>Zoom</th><th>Reschedule</th><th>Materials</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    foreach ($rows as $r):
                        $start = strtotime($r->class_start_iso);
                        $end   = $start + ((int)$r->duration_minutes * 60);
                        $past  = time() > $end + 300; // 5-minute buffer
                        $college = get_userdata($r->college_user_id);
                        $hasZoom = !empty($r->zoom_url);
                        $panelUrl = site_url('/expert/panel'); // Employee Panel page
                        
                        // Status badge
                        $status_label = $r->status;
                        $status_color = 'gray';
                        if ($r->status === 'booked') {
                            $status_label = 'Confirmed';
                            $status_color = 'green';
                        } elseif ($r->status === 'pending_contract') {
                            $status_label = 'Awaiting Payment';
                            $status_color = 'orange';
                        }
                        
                        // Order status
                        $order_status = '';
                        if ($r->order_id && function_exists('wc_get_order')) {
                            $order = wc_get_order($r->order_id);
                            if ($order) {
                                $order_status = ' (' . ucfirst($order->get_status()) . ')';
                            }
                        }
                        ?>
                        <tr>
                            <td>#<?php echo (int)$r->id; ?></td>
                            <td><strong><?php echo esc_html($r->topic); ?></strong></td>
                            <td><?php echo esc_html($college ? $college->display_name : 'Unknown'); ?></td>
                            <td><?php echo esc_html(gmdate('Y-m-d H:i', $start)); ?></td>
                            <td><?php echo (int)$r->duration_minutes; ?> min</td>
                            <td>
                                <span style="color: <?php echo esc_attr($status_color); ?>; font-weight: bold;">
                                    <?php echo esc_html($status_label . $order_status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r->contract_status === 'signed'): ?>
                                    <span style="color: green;">? Signed</span>
                                <?php elseif ($r->contract_status === 'generated'): ?>
                                    <span style="color: orange;">? Pending Signature</span>
                                <?php else: ?>
                                    <span style="color: gray;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r->status === 'booked'): ?>
                                    <?php if ($hasZoom): ?>
                                        <a href="<?php echo esc_url($r->zoom_url); ?>" target="_blank" class="button button-small pc-zoom-link">Join Zoom</a>
                                    <?php else: ?>
                                        <span class="pc-zoom-missing">Missing</span>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('pc_refresh_zoom_'.$r->id, '_pc_nonce'); ?>
                                            <input type="hidden" name="pc_action" value="pc_refresh_zoom">
                                            <input type="hidden" name="pc_request_id" value="<?php echo (int)$r->id; ?>">
                                            <button type="submit" class="button button-small">Refresh</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: gray;">Pending payment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r->status === 'booked' && $r->appointment_id): ?>
                                    <a class="button button-small" href="<?php echo esc_url($panelUrl); ?>" target="_blank">Employee Panel</a>
                                    <br><small class="description">Appt #<?php echo (int)$r->appointment_id; ?></small>
                                <?php else: ?>
                                    <span style="color: gray;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r->status === 'booked' && $past): ?>
                                    <?php echo $this->render_upload_form((int)$r->id, $college ? $college->display_name : 'College Admin'); ?>
                                <?php elseif ($r->status === 'booked'): ?>
                                    <em>After class ends</em>
                                <?php else: ?>
                                    <span style="color: gray;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    private function render_upload_form($requestId, $collegeName) {
        ob_start(); ?>
        <details class="pc-upload-form">
            <summary class="button button-primary button-small">Upload Materials</summary>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('pc_upload_materials_'.$requestId, '_pc_nonce'); ?>
                <input type="hidden" name="pc_action" value="pc_upload_materials">
                <input type="hidden" name="pc_request_id" value="<?php echo (int)$requestId; ?>">
                
                <label><strong>Upload Files (PDF/Slides/Zip):</strong>
                    <input type="file" name="pc_files[]" multiple accept=".pdf,.ppt,.pptx,.zip,.doc,.docx">
                </label>
                
                <label><strong>Notes to <?php echo esc_html($collegeName); ?>:</strong>
                    <textarea name="pc_notes" rows="3" placeholder="Add any notes about the materials..."></textarea>
                </label>
                
                <button type="submit" class="button button-primary">Save & Email College</button>
            </form>
        </details>
        <?php
        return ob_get_clean();
    }

    // CRITICAL FIX 3: Stop follow-up appointment fetch - use single API call only
    private function handle_refresh_zoom() {
        $requestId = absint($_POST['pc_request_id'] ?? 0);
        if (!$requestId || !isset($_POST['_pc_nonce']) || !wp_verify_nonce($_POST['_pc_nonce'], 'pc_refresh_zoom_'.$requestId)) {
            return '<div class="notice notice-error"><p>Invalid request.</p></div>';
        }
        if (!platform_core_user_is_expert() && !current_user_can('manage_options')) {
            return '<div class="notice notice-error"><p>Permission denied.</p></div>';
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, c.class_start_iso, c.duration_minutes, cm.google_event_id, cm.zoom_url, r.appointment_id
            FROM {$this->tbl_requests} r
            JOIN {$this->tbl_contracts} c  ON c.request_id = r.id
            LEFT JOIN {$this->tbl_calendar_map} cm ON cm.source = 'flow7_college' AND cm.object_id = r.id
            WHERE r.id = %d AND r.expert_user_id = %d
        ", $requestId, get_current_user_id()));
        
        if (!$row) {
            return '<div class="notice notice-error"><p>Class not found or access denied.</p></div>';
        }

        // CRITICAL FIX 3: ONE booking call only - NO follow-up GET
        $newZoom = '';
        if ($row->appointment_id && class_exists('\AmeliaBooking\Infrastructure\WP\AmeliaBookingPlugin')) {
            $appt = $this->amelia_http_get('/appointments/' . (int)$row->appointment_id);
            if (is_array($appt)) {
                // Try multiple possible response structures
                $newZoom = $appt['zoomJoinUrl'] ?? 
                          ($appt['data']['appointments'][0]['zoomJoinUrl'] ?? 
                          ($appt['data']['zoomJoinUrl'] ?? ''));
            }
        }

        if ($newZoom) {
            $updated = $wpdb->update(
                $this->tbl_calendar_map, 
                ['zoom_url' => $newZoom], 
                [
                    'source' => 'flow7_college', 
                    'object_id' => $requestId
                ],
                ['%s'],
                ['%s', '%d']
            );
            
            if ($updated !== false) {
                return '<div class="notice notice-success"><p>? Zoom link refreshed successfully.</p></div>';
            } else {
                return '<div class="notice notice-error"><p>Database update failed.</p></div>';
            }
        }
        
        return '<div class="notice notice-warning"><p>? Zoom link still missing. Please ensure:<ul><li>Zoom integration is enabled for your Amelia employee account</li><li>The appointment status is "Approved"</li><li>Zoom is properly configured in Amelia settings</li></ul></p></div>';
    }

    // CRITICAL FIX 5: Prevent duplicate booking on refresh/double-click
    private function handle_upload_materials() {
        $requestId = absint($_POST['pc_request_id'] ?? 0);
        if (!$requestId || !isset($_POST['_pc_nonce']) || !wp_verify_nonce($_POST['_pc_nonce'], 'pc_upload_materials_'.$requestId)) {
            return '<div class="notice notice-error"><p>Invalid request.</p></div>';
        }
        if (!platform_core_user_is_expert() && !current_user_can('manage_options')) {
            return '<div class="notice notice-error"><p>Permission denied.</p></div>';
        }

        // CRITICAL FIX 5: Duplicate booking prevention
        $lock_key = 'amelia_upload_lock_'.get_current_user_id().'_'.$requestId;
        if (get_transient($lock_key)) {
            return '<div class="notice notice-error"><p>Upload already in progress. Please wait.</p></div>';
        }
        set_transient($lock_key, 1, 30);

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, c.class_start_iso, c.duration_minutes
            FROM {$this->tbl_requests} r
            JOIN {$this->tbl_contracts} c ON c.request_id = r.id
            WHERE r.id = %d AND r.expert_user_id = %d
        ", $requestId, get_current_user_id()));
        
        if (!$row) {
            delete_transient($lock_key);
            return '<div class="notice notice-error"><p>Class not found or access denied.</p></div>';
        }

        // Check if session already exists
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID 
            WHERE p.post_type = %s 
            AND pm.meta_key = '_pc_request_id' 
            AND pm.meta_value = %d
            LIMIT 1
        ", self::CPT_CLASS_SESSION, $requestId));

        if ($existing) {
            $sessionId = (int)$existing;
            // Update existing post
            wp_update_post([
                'ID'           => $sessionId,
                'post_content' => sanitize_textarea_field($_POST['pc_notes'] ?? '')
            ]);
        } else {
            // Create new class_session CPT
            $title = 'Class Session #' . (int)$requestId . ' — ' . $row->topic;
            $sessionId = wp_insert_post([
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_author' => get_current_user_id(),
                'post_content'=> sanitize_textarea_field($_POST['pc_notes'] ?? '')
            ]);

            if (is_wp_error($sessionId)) {
                delete_transient($lock_key);
                return '<div class="notice notice-error"><p>Failed to create class session.</p></div>';
            }

            // Persist meta
            update_post_meta($sessionId, '_pc_request_id', (int)$requestId);
            update_post_meta($sessionId, '_pc_expert_user_id', (int)$row->expert_user_id);
            update_post_meta($sessionId, '_pc_college_user_id', (int)$row->college_user_id);
            update_post_meta($sessionId, '_pc_class_start_iso', $row->class_start_iso);
            update_post_meta($sessionId, '_pc_duration_minutes', (int)$row->duration_minutes);
        }

        // Handle files with validation
        $upload_ids = get_post_meta($sessionId, '_pc_material_ids', true) ?: [];
        
        if (!empty($_FILES['pc_files']['name'][0])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            
            $files = $_FILES['pc_files'];
            $uploaded_count = 0;
            $max_size = wp_max_upload_size();
            $allowed_types = ['pdf', 'ppt', 'pptx', 'zip', 'doc', 'docx'];
            
            foreach ($files['name'] as $i => $name) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                
                // Validate file size
                if ($files['size'][$i] > $max_size) {
                    continue;
                }
                
                // Validate file type
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_types)) {
                    continue;
                }
                
                $file = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => 0,
                    'size'     => $files['size'][$i]
                ];
                
                $_FILES['tmp_pc_file'] = $file;
                $aid = media_handle_upload('tmp_pc_file', $sessionId);
                
                if (!is_wp_error($aid)) {
                    $upload_ids[] = $aid;
                    $uploaded_count++;
                }
                unset($_FILES['tmp_pc_file']);
            }
        }
        
        update_post_meta($sessionId, '_pc_material_ids', $upload_ids);

        // Trigger Flow 9 to auto-email invitees with recap
        do_action('platform_core_class_session_saved', (int)$requestId, (int)$sessionId);

        // Email the college admin with links
        $college = get_userdata($row->college_user_id);
        if ($college && $college->user_email) {
            $subject = sprintf('[%s] Materials for "%s"', get_bloginfo('name'), $row->topic);
            
            $links = [];
            foreach ($upload_ids as $aid) {
                $url = wp_get_attachment_url($aid);
                if ($url) {
                    $filename = basename(get_attached_file($aid));
                    $links[] = $filename . ': ' . $url;
                }
            }
            
            $body = "Hello " . $college->display_name . ",\n\n";
            $body .= "The expert has uploaded post-class materials for:\n\n";
            $body .= "Topic: {$row->topic}\n";
            $body .= "Date/Time: {$row->class_start_iso}\n";
            $body .= "Duration: {$row->duration_minutes} minutes\n\n";
            
            if (count($links)) {
                $body .= "Uploaded Files:\n" . implode("\n", $links) . "\n\n";
            }
            
            $notes = sanitize_textarea_field($_POST['pc_notes'] ?? '');
            if ($notes) {
                $body .= "Notes from Expert:\n" . $notes . "\n\n";
            }
            
            $body .= "You can view all your class materials at: " . site_url('/college/my-classes') . "\n\n";
            $body .= "Regards,\n" . get_bloginfo('name');

            // wp_mail goes through WP Mail SMTP (SendGrid) per Foundation F3
            $sent = wp_mail($college->user_email, $subject, $body);
            
            // Release lock after successful completion
            delete_transient($lock_key);
            
            if (!$sent) {
                return '<div class="notice notice-warning"><p>? Materials saved but email notification failed. Please inform the college manually.</p></div>';
            }
        } else {
            delete_transient($lock_key);
        }

        return '<div class="notice notice-success"><p>? Materials saved and emailed to the college successfully!</p></div>';
    }

    /* -----------------------------
     *  REST: Amelia webhook (appointment updated) ? patch Google Calendar
     *  Configure in Amelia ? Settings ? Integrations ? Webhooks (if available)
     * --------------------------- */
    public function register_routes() {
        register_rest_route('platform-core/v1', '/amelia/appointment-updated', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_amelia_appt_updated'],
            'permission_callback' => '__return_true' // secure by secret if exposed publicly
        ]);

        register_rest_route('platform-core/v1', '/college/refresh-zoom', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_refresh_zoom'],
            'permission_callback' => function () { 
                return platform_core_user_is_expert() || current_user_can('manage_options'); 
            }
        ]);
    }

    public function api_amelia_appt_updated(WP_REST_Request $req) {
        // Expect: { appointmentId, bookingStart, bookingEnd }
        $appointmentId = absint($req['appointmentId'] ?? 0);
        $bookingStart  = sanitize_text_field($req['bookingStart'] ?? '');
        $bookingEnd    = sanitize_text_field($req['bookingEnd'] ?? '');
        
        if (!$appointmentId) {
            return new WP_REST_Response(['error' => 'missing appointmentId'], 400);
        }

        global $wpdb;
        // We stored appointment_id on Flow-7
        $r = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->tbl_requests} WHERE appointment_id = %d", 
            $appointmentId
        ));
        
        if (!$r) {
            return ['ok' => true, 'skipped' => 'request not found'];
        }

        // Update site Google Calendar event (same rail used earlier)
        if (function_exists('platform_core_google_patch_event')) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT google_event_id FROM {$this->tbl_calendar_map} 
                WHERE source='flow7_college' AND object_id=%d", 
                $r->id
            ));
            
            if ($row && $row->google_event_id) {
                platform_core_google_patch_event($row->google_event_id, [
                    'start' => gmdate('c', strtotime($bookingStart)),
                    'end'   => gmdate('c', strtotime($bookingEnd)),
                ]);
            }
        }
        
        return ['ok' => true, 'request_id' => $r->id];
    }

    public function api_refresh_zoom(WP_REST_Request $req) {
        // Programmatic refresh endpoint
        $requestId = absint($req['request_id'] ?? 0);
        if (!$requestId) {
            return new WP_REST_Response(['error' => 'missing request_id'], 400);
        }
        
        $_POST['pc_action']      = 'pc_refresh_zoom';
        $_POST['pc_request_id']  = $requestId;
        $_POST['_pc_nonce']      = wp_create_nonce('pc_refresh_zoom_' . $requestId);
        
        return ['html' => $this->handle_refresh_zoom()];
    }

    /* -----------------------------
     *  Reminder: materials not uploaded within 24h
     * --------------------------- */
    public function remind_missing_materials() {
        global $wpdb;
        
        // Classes that ended 24-48 hours ago with no class_session
        $since = gmdate('Y-m-d H:i:s', time() - (DAY_IN_SECONDS * 2));
        $until = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT r.id, r.expert_user_id, r.topic, c.class_start_iso, c.duration_minutes
            FROM {$this->tbl_requests} r
            JOIN {$this->tbl_contracts} c ON c.request_id = r.id
            WHERE r.status='booked'
              AND (UNIX_TIMESTAMP(c.class_start_iso) + c.duration_minutes*60) BETWEEN UNIX_TIMESTAMP(%s) AND UNIX_TIMESTAMP(%s)
              AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID 
                WHERE p.post_type=%s
                AND pm.meta_key='_pc_request_id' 
                AND pm.meta_value = r.id
              )
        ", $since, $until, self::CPT_CLASS_SESSION));

        foreach ($rows as $r) {
            $expert = get_userdata($r->expert_user_id);
            if (!$expert || !$expert->user_email) continue;
            
            $subject = sprintf('[%s] Reminder: Upload materials for class #%d', get_bloginfo('name'), (int)$r->id);
            $body = "Hello " . $expert->display_name . ",\n\n";
            $body .= "This is a friendly reminder to upload the post-class materials for:\n\n";
            $body .= "Topic: {$r->topic}\n";
            $body .= "Date/Time: {$r->class_start_iso}\n\n";
            $body .= "Please visit your Expert ? College Classes page to upload files:\n";
            $body .= site_url('/expert/college-classes') . "\n\n";
            $body .= "Regards,\n" . get_bloginfo('name');
            
            wp_mail($expert->user_email, $subject, $body);
        }
    }
}

new PlatformCore_Flow8_CollegeClass();