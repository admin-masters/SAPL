<?php
/**
 * Flow 7 — College user requests/confirm/pays class
 * FIXED VERSION - Clean and working
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
function pcore_amelia_api_call($endpoint, $method = 'GET', $payload = null) {
    $url = admin_url('admin-ajax.php?action=wpamelia_api&call=' . ltrim($endpoint, '/'));

    $args = [
        'method'  => $method,
        'headers' => [
            'Content-Type' => 'application/json',
            'Amelia'       => 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm'
        ],
        'timeout' => 20
    ];

    if ($payload !== null) {
        $args['body'] = json_encode($payload);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return [
            'error' => true,
            'msg'   => $response->get_error_message()
        ];
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

class PlatformCore_Flow7_College {

    /** Option keys and defaults */
    const OPTS_KEY                        = 'platform_core_college_settings';
    const OPT_REMOTE_CLASS_SERVICE_ID     = 'remote_class_service_id';
    const OPT_CONTRACT_TERMS_HTML         = 'contract_terms_html';
    const OPT_COMPANY_NAME                = 'company_name';
    const OPT_COMPANY_ADDRESS             = 'company_address';
    const OPT_AMELIA_API_KEY              = 'amelia_api_key';
    const OPT_AMELIA_LOCATION_ID          = 'default_location_id';

    /** DB table names */
    private $tbl_requests;
    private $tbl_responses;
    private $tbl_contracts;
    private $tbl_calendar_map;

    /** constructor */
    public function __construct() {
        global $wpdb;
        $this->tbl_requests     = $wpdb->prefix . 'platform_requests';
        $this->tbl_responses    = $wpdb->prefix . 'platform_request_responses';
        $this->tbl_contracts    = $wpdb->prefix . 'platform_contracts';
        $this->tbl_calendar_map = $wpdb->prefix . 'platform_calendar_map';
        
        // Register shortcodes
        add_shortcode('platform_college_request_class', [$this, 'sc_request_form']);
        add_shortcode('platform_college_my_classes',    [$this, 'sc_my_classes']);
        add_shortcode('platform_expert_college_requests', [$this, 'sc_expert_inbox']);

        // REST API
        add_action('rest_api_init', [$this, 'register_routes']);

        // Admin settings
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        
        // Payment completion hooks
        add_action('woocommerce_order_status_completed', [$this, 'finalize_order_booking'], 10, 1);
            }

    /* -----------------------------
     *  Admin Settings
     * --------------------------- */
    public function add_settings_page() {
        add_options_page(
            'College Classes Settings',
            'College Classes',
            'manage_options',
            self::OPTS_KEY,
            [$this, 'render_settings_page']
        );
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql_requests = "CREATE TABLE {$this->tbl_requests} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            college_user_id bigint(20) NOT NULL,
            expert_user_id bigint(20) NOT NULL,
            topic varchar(255) NOT NULL,
            description text,
            proposed_start_iso varchar(30),
            duration_minutes int(11) DEFAULT 60,
            capacity int(11) DEFAULT 100,
            price_offer decimal(10,2) DEFAULT 0.00,
            status varchar(50) DEFAULT 'requested',
            order_id bigint(20) DEFAULT NULL,
            appointment_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY college_user_id (college_user_id),
            KEY expert_user_id (expert_user_id)
        ) $charset_collate;";

        $sql_responses = "CREATE TABLE {$this->tbl_responses} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id bigint(20) NOT NULL,
            expert_user_id bigint(20) NOT NULL,
            response varchar(50) NOT NULL,
            price decimal(10,2) DEFAULT 0.00,
            proposed_start_iso varchar(30),
            duration_minutes int(11) DEFAULT 60,
            note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id)
        ) $charset_collate;";

        $sql_contracts = "CREATE TABLE {$this->tbl_contracts} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            request_id bigint(20) NOT NULL,
            status varchar(50) DEFAULT 'generated',
            total_amount decimal(10,2) DEFAULT 0.00,
            class_start_iso varchar(30),
            duration_minutes int(11) DEFAULT 60,
            sign_token varchar(100),
            sign_token_expires datetime,
            signed_at datetime,
            signed_by_user_id bigint(20),
            signed_name varchar(255),
            signed_ip varchar(100),
            pdf_path text,
            order_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY sign_token (sign_token)
        ) $charset_collate;";

        $sql_calendar = "CREATE TABLE {$this->tbl_calendar_map} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source varchar(50) NOT NULL,
            object_id bigint(20) NOT NULL,
            google_event_id varchar(255),
            zoom_url text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_object (source, object_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_requests);
        dbDelta($sql_responses);
        dbDelta($sql_contracts);
        dbDelta($sql_calendar);
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>College Classes Settings (Flow 7)</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTS_KEY);
                do_settings_sections(self::OPTS_KEY);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting(self::OPTS_KEY, self::OPTS_KEY);

        add_settings_section('flow7', 'College Classes (Flow 7)', function () {
            echo '<p>Settings used by Flow 7 (Remote College Class bookings via Woo ? Amelia ? Zoom).</p>';
        }, self::OPTS_KEY);

        add_settings_field(self::OPT_REMOTE_CLASS_SERVICE_ID, 'Amelia Service ID (Remote College Class)', function () {
            $v = $this->get_opt(self::OPT_REMOTE_CLASS_SERVICE_ID);
            echo '<input type="number" min="1" name="'.self::OPTS_KEY.'['.self::OPT_REMOTE_CLASS_SERVICE_ID.']" value="' . esc_attr($v) . '" class="regular-text" />';
            echo '<p class="description">Enter the Amelia Service ID for remote college classes.</p>';
        }, self::OPTS_KEY,'flow7');

        add_settings_field(self::OPT_COMPANY_NAME, 'Contract: Company/Platform Name', function () {
            $v = $this->get_opt(self::OPT_COMPANY_NAME, get_bloginfo('name'));
            echo '<input type="text" name="'.self::OPTS_KEY.'['.self::OPT_COMPANY_NAME.']" value="' . esc_attr($v) . '" class="regular-text" />';
        }, self::OPTS_KEY,'flow7');

        add_settings_field(self::OPT_COMPANY_ADDRESS, 'Contract: Company Address (footer)', function () {
            $v = $this->get_opt(self::OPT_COMPANY_ADDRESS, '');
            echo '<textarea name="'.self::OPTS_KEY.'['.self::OPT_COMPANY_ADDRESS.']" rows="3" class="large-text">' . esc_textarea($v) . '</textarea>';
        }, self::OPTS_KEY,'flow7');

        add_settings_field(self::OPT_CONTRACT_TERMS_HTML, 'Contract Terms (HTML)', function () {
            $v = $this->get_opt(self::OPT_CONTRACT_TERMS_HTML, '<p>Standard service terms apply. Cancellations 48h prior receive full refund; otherwise 50% fee.</p>');
            echo '<textarea name="'.self::OPTS_KEY.'['.self::OPT_CONTRACT_TERMS_HTML.']" rows="8" class="large-text code">' . esc_textarea($v) . '</textarea>';
        }, self::OPTS_KEY,'flow7');

        add_settings_field(self::OPT_AMELIA_API_KEY, 'Amelia API Key', function () {
            $v = $this->get_opt(self::OPT_AMELIA_API_KEY, '');
            echo '<input type="text" name="'.self::OPTS_KEY.'['.self::OPT_AMELIA_API_KEY.']" value="' . esc_attr($v) . '" class="regular-text" />';
            echo '<p class="description">Amelia ? Settings ? API Keys. Paste the key here.</p>';
        }, self::OPTS_KEY,'flow7');

        add_settings_field(self::OPT_AMELIA_LOCATION_ID, 'Default Amelia Location ID', function () {
            $v = (int)$this->get_opt(self::OPT_AMELIA_LOCATION_ID, 1);
            echo '<input type="number" min="1" name="'.self::OPTS_KEY.'['.self::OPT_AMELIA_LOCATION_ID.']" value="' . esc_attr($v) . '" class="small-text" />';
            echo '<p class="description">ID of the default Location (Amelia ? Locations). Used when creating appointments.</p>';
        }, self::OPTS_KEY,'flow7');
    } 

    private function get_opt($key, $default = '') {
        $opts = get_option(self::OPTS_KEY, []);
        return isset($opts[$key]) ? $opts[$key] : $default;
    }

    /* -----------------------------
     *  Payment Completion Handler
     * --------------------------- */
    public function finalize_order_booking($order_id) {
if (get_post_meta($order_id, '_platform_finalizing', true)) {
    return;
}
update_post_meta($order_id, '_platform_finalizing', 1);
        if (!function_exists('wc_get_order')) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Idempotency check
        if ((int)$order->get_meta('_platform_finalized') === 1) {
 $this->release_finalize_lock($order_id);
            return;
        }

        // Get request ID
        $request_id = (int)$order->get_meta('_platform_request_id');
        if (!$request_id) {
            global $wpdb;
            $contract = $wpdb->get_row($wpdb->prepare(
                "SELECT request_id FROM {$this->tbl_contracts} WHERE order_id = %d LIMIT 1", 
                $order_id
            ));
            if ($contract) {
                $request_id = (int)$contract->request_id;
                $order->update_meta_data('_platform_request_id', $request_id);
                $order->save_meta_data();
            }
        }

        if (!$request_id) {
            $order->add_order_note('Finalize skipped: no request_id found.');
$this->release_finalize_lock($order_id);
            return;
        }

        // Get request details
        global $wpdb;
        $req = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, c.class_start_iso, c.duration_minutes
            FROM {$this->tbl_requests} r
            JOIN {$this->tbl_contracts} c ON c.request_id = r.id
            WHERE r.id = %d
        ", $request_id));

        if (!$req) {
            $order->add_order_note('Finalize skipped: request not found.');
$this->release_finalize_lock($order_id);
            return;
        }

        // Skip if already has appointment
        if (!empty($req->appointment_id)) {
            $order->update_meta_data('_platform_finalized', 1);
            $order->add_order_note('Already has appointment ID: ' . $req->appointment_id);
            $order->save();
$this->release_finalize_lock($order_id);
            return;
        }

        // Create Amelia appointment
        $booking = $this->create_amelia_appointment($request_id, $req);

        if (empty($booking) || empty($booking['appointmentId'])) {
            $error_msg = isset($booking['error']) ? $booking['error'] : 'Unknown error';
            $order->add_order_note('? Amelia booking failed: ' . $error_msg);
            $order->save();

$this->release_finalize_lock($order_id);
            return;
        }

        // Save appointment ID
        $wpdb->update(
            $this->tbl_requests, 
            ['appointment_id' => (int)$booking['appointmentId']], 
            ['id' => $request_id]
        );

        // Insert calendar mapping
        $this->insert_google_events_from_booking($request_id, $booking);

        // Update status to booked
        $wpdb->update(
            $this->tbl_requests, 
            ['status' => 'booked', 'updated_at' => current_time('mysql')],
            ['id' => $request_id]
        );

        // Mark as finalized
        $order->update_meta_data('_platform_finalized', 1);
        $order->add_order_note('? Amelia appointment created: ID #' . $booking['appointmentId']);
        $order->save();
update_post_meta($order_id, '_platform_finalized', 1);
delete_post_meta($order_id, '_platform_finalizing');


    }

    /* -----------------------------
     *  Amelia Appointment Creation
     * --------------------------- */
    private function release_finalize_lock($order_id) {
    delete_post_meta($order_id, '_platform_finalizing');
}

    /* -----------------------------
     *  Amelia API Helper Methods
     * --------------------------- */
private function pcore_get_customer_id_by_email($email) {

    $resp = pcore_amelia_api_call('/api/v1/users/customers', 'GET');

    if (empty($resp['data']['users'])) {
        return 0;
    }

    foreach ($resp['data']['users'] as $customer) {
        if (!empty($customer['email']) && strcasecmp($customer['email'], $email) === 0) {
            return (int) $customer['id'];
        }
    }

    return 0;
}
private function pcore_get_provider_id_by_email($email) {

    $resp = pcore_amelia_api_call('/api/v1/users/providers', 'GET');

    if (empty($resp['data']['users'])) {
        return 0;
    }

    foreach ($resp['data']['users'] as $provider) {
        if (!empty($provider['email']) && strcasecmp($provider['email'], $email) === 0) {
            return (int) $provider['id'];
        }
    }

    return 0;
}
private function create_amelia_appointment($request_id, $req) {

    $serviceId = (int) $this->get_opt(self::OPT_REMOTE_CLASS_SERVICE_ID, 0);
    if (!$serviceId) {
        return ['error' => 'Service ID not configured'];
    }

    $college_user = get_userdata($req->college_user_id);
    $expert_user  = get_userdata($req->expert_user_id);

    if (!$college_user || !$expert_user) {
        return ['error' => 'User not found'];
    }

    // ? correct emails
    $customerEmail = $college_user->user_email;
    $providerEmail = $expert_user->user_email;

    // ? lookup only (NO create)
    $customer_id = $this->pcore_get_customer_id_by_email($customerEmail);
    if (!$customer_id) {
        return ['error' => 'Amelia customer not found: ' . $customerEmail];
    }

   $provider_id = $this->pcore_get_provider_id_by_email($providerEmail);
    if (!$provider_id) {
        return ['error' => 'Amelia provider not found: ' . $providerEmail];
    }

    // ? dynamic start time
    $startTime = $req->class_start_iso ?: $req->proposed_start_iso;
    if (!$startTime) {
        return ['error' => 'Start time missing'];
    }

    $dt = new DateTime($startTime, new DateTimeZone(wp_timezone_string()));
    $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $bookingStart = $dt->format('Y-m-d H:i:s');

    // ? dynamic duration (minutes ? seconds)
    $durationSeconds = max(60, (int)$req->duration_minutes * 60);

    $payload = [
        'bookingStart'       => $bookingStart,
        'providerId'         => $provider_id,
        'serviceId'          => $serviceId,
        'notifyParticipants' => 1,
        'timeZone'           => 'Asia/Kolkata',
        'utc'                => false,
        'bookings' => [
            [
                'customerId' => $customer_id,
                'duration'   => $durationSeconds,
                'persons'    => 1,
                'status'     => 'approved'
            ]
        ]
    ];

    // ? ONLY this function
    $response = pcore_amelia_api_call('/api/v1/appointments', 'POST', $payload);

    // ?? extract appointment ID
    if (!empty($response['data']['appointment']['id'])) {
        return [
            'appointmentId' => (int)$response['data']['appointment']['id'],
            'start'         => $bookingStart,
            'end'           => $dt->modify("+{$req->duration_minutes} minutes")->format('Y-m-d H:i:s'),
            'raw'           => $response
        ];
    }

    if (!empty($response['data']['bookings'][0]['appointmentId'])) {
        return [
            'appointmentId' => (int)$response['data']['bookings'][0]['appointmentId'],
            'start'         => $bookingStart,
            'end'           => $dt->modify("+{$req->duration_minutes} minutes")->format('Y-m-d H:i:s'),
            'raw'           => $response
        ];
    }

    return [
        'error' => 'Appointment creation failed',
        'raw'   => $response
    ];
}


   private function amelia_get_user_id_by_email($email, $type = 'customers') {

    $cache_key = 'amelia_' . $type . '_' . md5($email);
    $cached = get_transient($cache_key);

    if ($cached) {
        return (int)$cached;
    }

    $response = $this->amelia_api(
        '/users/' . $type . '?page=1&search=' . urlencode($email),
        'GET'
    );

    if (!empty($response['data']['users'][0]['id'])) {
        set_transient($cache_key, (int)$response['data']['users'][0]['id'], DAY_IN_SECONDS);
        return (int)$response['data']['users'][0]['id'];
    }

    return 0;
}

    private function amelia_create_customer($wp_user_id) {
        $u = get_userdata($wp_user_id);
        if (!$u) return 0;

        $first = get_user_meta($wp_user_id, 'first_name', true);
        $last  = get_user_meta($wp_user_id, 'last_name', true);
        
        if (!$first && !$last) {
            $parts = preg_split('/\s+/', trim($u->display_name), 2);
            $first = $parts[0] ?: 'User';
            $last  = $parts[1] ?? '-';
        }

        $payload = [
            'firstName'  => $first ?: 'User',
            'lastName'   => $last ?: '-',
            'email'      => $u->user_email,
            'externalId' => (int)$wp_user_id,
        ];

        $response = $this->amelia_api('/users/customers', 'POST', $payload);
        
        return !empty($response['data']['user']['id']) ? (int)$response['data']['user']['id'] : 0;
    }

    private function amelia_api($path, $method = 'POST', $payload = null) {
    $apiKey = trim((string)$this->get_opt(self::OPT_AMELIA_API_KEY, ''));

    if (!$apiKey) {
        return ['error' => 'Amelia API key not configured'];
    }

    $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1' . $path);

    $args = [
        'timeout' => 20,
        'headers' => [
            'Amelia'       => $apiKey,
            'Content-Type' => 'application/json'
        ]
    ];

    if ($method === 'GET') {
        $response = wp_remote_get($url, $args);
    } else {
        $args['body'] = wp_json_encode($payload);
        $response = wp_remote_post($url, $args);
    }

    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    // ? CORRECT PLACE for 429 handling
    if ($code === 429) {
sleep(2);
        return [
            'error' => 'Amelia rate limit hit',
            '_http' => ['code' => 429],
            'raw'   => $body
        ];
    }

    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        return [
            'error' => 'Invalid JSON response',
            '_http' => ['code' => $code],
            'raw'   => $body
        ];
    }

    $decoded['_http'] = ['code' => $code];
    return $decoded;
}

    /* -----------------------------
     *  Google Calendar Integration
     * --------------------------- */
    private function insert_google_events_from_booking($request_id, array $booking) {
        if (!function_exists('platform_core_google_insert_event')) {
            return;
        }

        global $wpdb;
        $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_requests} WHERE id = %d", $request_id));
        
        if (!$r) return;

        $expert  = get_userdata($r->expert_user_id);
        $college = get_userdata($r->college_user_id);

        $summary = 'College Class: ' . $r->topic;
        $desc    = "Expert: " . ($expert ? $expert->display_name : 'Unknown') . "\n";
        
        if (!empty($booking['zoomJoinUrl'])) {
            $desc .= "Zoom: {$booking['zoomJoinUrl']}";
        }

        $attendees = array_filter([
            $college ? $college->user_email : '',
            $expert  ? $expert->user_email : '',
        ]);

        $eventId = platform_core_google_insert_event([
            'summary'     => $summary,
            'description' => $desc,
            'start'       => $booking['start'],
            'end'         => $booking['end'],
            'attendees'   => $attendees
        ]);

        if ($eventId) {
            $wpdb->insert($this->tbl_calendar_map, [
                'source'          => 'flow7_college',
                'object_id'       => $request_id,
                'google_event_id' => $eventId,
                'zoom_url'        => $booking['zoomJoinUrl'] ?? '',
                'created_at'      => current_time('mysql')
            ], ['%s','%d','%s','%s','%s']);
        }
    }

    /* -----------------------------
     *  Shortcode: Expert Inbox
     * --------------------------- */
    public function sc_expert_inbox() {
    if (!is_user_logged_in()) {
        return '<div class="notice notice-error"><p>Please sign in to view this page.</p></div>';
    }
    
    if (!platform_core_user_is_expert() && !current_user_can('manage_options')) {
        return '<div class="notice notice-error"><p>You need an Expert account to view this page.</p></div>';
    }
    
    global $wpdb;
    $current_user_id = get_current_user_id();
    
    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT r.*, c.status AS contract_status, c.total_amount, c.sign_token
        FROM {$this->tbl_requests} r
        LEFT JOIN {$this->tbl_contracts} c ON c.request_id = r.id
        WHERE r.expert_user_id = %d
        ORDER BY r.id DESC
    ", $current_user_id));

    $nonce = wp_create_nonce('wp_rest');
    $rest  = esc_url_raw(rest_url('platform-core/v1/college/response'));

    ob_start(); 
    ?>
    <style>
    .pc-expert-requests table { width: 100%; margin: 20px 0; border-collapse: collapse; }
    .pc-expert-requests th, .pc-expert-requests td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    .pc-expert-requests details { margin: 10px 0; }
    .pc-actions { margin: 15px 0; padding: 15px; background: #f5f5f5; border-radius: 6px; }
    .pc-actions h4 { margin: 0 0 10px 0; padding-bottom: 8px; border-bottom: 2px solid #ddd; }
    .pc-actions form { margin: 15px 0; padding: 15px; background: white; border-radius: 4px; border: 1px solid #ddd; }
    .pc-actions label { display: block; margin: 10px 0; font-weight: 500; }
    .pc-actions input[type="number"],
    .pc-actions input[type="datetime-local"],
    .pc-actions input[type="text"] { width: 100%; max-width: 350px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    .pc-actions button { margin-top: 10px; }
    .pc-status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; }
    .pc-status-requested { background: #fff3cd; color: #856404; }
    .pc-status-pending_contract { background: #cce5ff; color: #004085; }
    .pc-status-rejected { background: #f8d7da; color: #721c24; }
    .pc-status-booked { background: #d4edda; color: #155724; }
    </style>
    
    <div class="pc-expert-requests">
      <h3>College Class Requests</h3>
      
      <?php if (!$rows): ?>
        <div class="notice notice-info">
            <p>No requests yet.</p>
            <p><small>Your user ID: <?php echo $current_user_id; ?></small></p>
        </div>
      <?php else: ?>
        <table class="widefat striped">
          <thead><tr>
            <th>#</th><th>College</th><th>Topic</th><th>Proposed</th><th>Duration</th><th>Capacity</th><th>Budget</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): 
            $college_user = get_userdata($r->college_user_id);
            $college_name = $college_user ? $college_user->display_name : 'Unknown';
            
            // Status badge
            $status_class = 'pc-status-' . str_replace(['_', '-'], '_', strtolower($r->status));
            $status_display = ucwords(str_replace('_', ' ', $r->status));
            
            // Show contract status if exists
            if ($r->status === 'pending_contract' && $r->contract_status) {
                $status_display .= ' (' . ucwords(str_replace('_', ' ', $r->contract_status)) . ')';
            }
          ?>
            <tr data-id="<?php echo (int)$r->id; ?>">
              <td>#<?php echo (int)$r->id; ?></td>
              <td><?php echo esc_html($college_name); ?></td>
              <td>
                <strong><?php echo esc_html($r->topic); ?></strong>
                <?php if ($r->description): ?>
                  <br><small><?php echo esc_html(substr($r->description, 0, 100)); ?></small>
                <?php endif; ?>
              </td>
              <td><?php echo esc_html($r->proposed_start_iso); ?></td>
              <td><?php echo (int)$r->duration_minutes; ?> min</td>
              <td><?php echo (int)$r->capacity; ?> students</td>
              <td>$<?php echo esc_html(number_format($r->price_offer, 2)); ?></td>
              <td>
                <span class="pc-status-badge <?php echo esc_attr($status_class); ?>">
                  <?php echo esc_html($status_display); ?>
                </span>
              </td>
              <td>
                <?php if (in_array($r->status, ['requested', 'pending_contract'], true)): ?>
                  <details>
                    <summary class="button">
                      <?php echo $r->status === 'pending_contract' ? 'Update Response' : 'Respond'; ?>
                    </summary>
                    <div class="pc-actions">
                      
                      <!-- ACCEPT SECTION -->
                      <h4>? Accept Request</h4>
                      <form class="pc-accept" data-action="accept">
                        <input type="hidden" name="action" value="accept">
                        <label>Final Price: 
                          <input type="number" step="0.01" name="price" 
                                 value="<?php echo esc_attr($r->price_offer ?: 0); ?>" required>
                        </label>
                        <label>Confirmed Start Time: 
                          <input type="datetime-local" name="start_iso" 
                                 value="<?php echo esc_attr(str_replace(' ', 'T', $r->proposed_start_iso)); ?>" required>
                        </label>
                        <label>Duration (minutes): 
                          <input type="number" name="duration_minutes" 
                                 value="<?php echo (int)$r->duration_minutes; ?>" required>
                        </label>
                        <button type="submit" class="button button-primary">
                          ? Accept & Generate Contract
                        </button>
                      </form>
                      
                      <!-- COUNTER-OFFER SECTION -->
                      <h4>?? Send Counter-Offer</h4>
                      <form class="pc-counter" data-action="counter">
                        <input type="hidden" name="action" value="counter">
                        <label>Counter Price: 
                          <input type="number" step="0.01" name="price" 
                                 value="<?php echo esc_attr($r->price_offer ?: 0); ?>" required>
                        </label>
                        <label>Alternative Start Time: 
                          <input type="datetime-local" name="start_iso" 
                                 value="<?php echo esc_attr(str_replace(' ', 'T', $r->proposed_start_iso)); ?>" required>
                        </label>
                        <label>Duration (minutes): 
                          <input type="number" name="duration_minutes" 
                                 value="<?php echo (int)$r->duration_minutes; ?>" required>
                        </label>
                        <label>Reason for Counter-Offer (optional): 
                          <input type="text" name="note" 
                                 placeholder="e.g., Time conflict, adjusted pricing">
                        </label>
                        <button type="submit" class="button">
                          ?? Send Counter-Offer
                        </button>
                      </form>
                      
                      <!-- REJECT SECTION -->
                      <h4>? Decline Request</h4>
                      <form class="pc-reject" data-action="reject">
                        <input type="hidden" name="action" value="reject">
                        <label>Reason for Declining (optional): 
                          <input type="text" name="note" 
                                 placeholder="e.g., Schedule conflict, outside expertise">
                        </label>
                        <button type="submit" class="button" style="background: #dc3545; color: white;">
                          ? Decline Request
                        </button>
                      </form>
                      
                    </div>
                  </details>
                <?php elseif ($r->status === 'booked'): ?>
                  <span style="color: green; font-weight: bold;">? Confirmed</span>
                <?php elseif ($r->status === 'rejected'): ?>
                  <span style="color: red;">? Declined</span>
                <?php else: ?>
                  <em>—</em>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <script>
    (function(){
      const rest = <?php echo wp_json_encode($rest); ?>;
      const nonce = <?php echo wp_json_encode($nonce); ?>;
      const container = document.querySelector('.pc-expert-requests');

      if (!container) {
        console.error('Expert requests container not found');
        return;
      }

      container.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const form = e.target;
        const tr = form.closest('tr');
        const id = tr.getAttribute('data-id');
        
        if (!id) {
          alert('Request ID not found');
          return;
        }
        
        const formData = new FormData(form);
        const action = formData.get('action');
        
        // Different confirmation messages based on action
        let confirmMsg = '';
        if (action === 'reject') {
          confirmMsg = 'Are you sure you want to DECLINE this request? This cannot be undone.';
        } else if (action === 'counter') {
          confirmMsg = 'Send counter-offer to the college? They will need to review and accept.';
        } else if (action === 'accept') {
          confirmMsg = 'Accept this request? A contract will be generated for the college to sign.';
        }
        
        if (confirmMsg && !confirm(confirmMsg)) {
          return;
        }
        
        const payload = {
          request_id: parseInt(id, 10),
          action: action,
          price: parseFloat(formData.get('price')) || 0,
          start_iso: formData.get('start_iso') || '',
          duration_minutes: parseInt(formData.get('duration_minutes')) || 60,
          note: formData.get('note') || ''
        };

        console.log('Submitting:', payload);

        try {
          const res = await fetch(rest, {
            method: 'POST',
            headers: {
              'X-WP-Nonce': nonce,
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
          });

          const json = await res.json();
          console.log('Response:', json);
          
          if (!res.ok) {
            alert('Error: ' + (json.error || 'Request failed'));
            return;
          }
          
          // Show success message based on action
          let successMsg = '';
          if (action === 'reject') {
            successMsg = '? Request declined successfully!';
          } else if (action === 'counter') {
            successMsg = '? Counter-offer sent! Waiting for college response.';
          } else if (action === 'accept') {
            successMsg = '? Request accepted! Contract generated for college.';
          }
          
          alert(successMsg);
          location.reload();
        } catch (error) {
          console.error('Error:', error);
          alert('An error occurred. Please try again or contact support.');
        }
      });
    })();
    </script>
    <?php
    return ob_get_clean();
}
    /* -----------------------------
     *  Shortcode: /college/request-class
     * --------------------------- */
    public function sc_request_form($atts = []) {
        if (!is_user_logged_in() || !current_user_can('college_admin')) {
            return '<div class="notice notice-error"><p>You need a College Admin account to request a class.</p></div>';
        }

        $nonce_action = 'platform_core_request_class';
        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_pc_nonce']) && wp_verify_nonce($_POST['_pc_nonce'], $nonce_action)) {
            $expert_id         = absint($_POST['expert_id'] ?? 0);
            $topic             = sanitize_text_field($_POST['class_topic'] ?? '');
            $description       = sanitize_textarea_field($_POST['class_description'] ?? '');
            $start_iso         = sanitize_text_field($_POST['start_iso'] ?? '');
            $duration_minutes  = absint($_POST['duration_minutes'] ?? 60);
            $capacity          = absint($_POST['capacity'] ?? 100);
            $price_offer       = floatval($_POST['price_offer'] ?? 0);

            if (!$expert_id) $errors[] = 'Please choose an expert.';
            if (!$topic)     $errors[] = 'Please enter a topic.';
            if (!$start_iso) $errors[] = 'Please select a proposed start date/time.';

            if (!$errors) {
                global $wpdb;
                $result = $wpdb->insert($this->tbl_requests, [
                    'college_user_id'    => get_current_user_id(),
                    'expert_user_id'     => $expert_id,
                    'topic'              => $topic,
                    'description'        => $description,
                    'proposed_start_iso' => $start_iso,
                    'duration_minutes'   => $duration_minutes,
                    'capacity'           => $capacity,
                    'price_offer'        => $price_offer,
                    'status'             => 'requested',
                    'created_at'         => current_time('mysql'),
                    'updated_at'         => current_time('mysql')
                ], ['%d','%d','%s','%s','%s','%d','%d','%f','%s','%s','%s']);

                if ($result) {
                    $success = true;
                } else {
                    $errors[] = 'Database error: ' . $wpdb->last_error;
                }
            }
        }

        ob_start();

        ?>
        <style>
        .pc-form { max-width: 800px; }
        .pc-form label { display: block; margin: 15px 0; font-weight: 600; }
        .pc-form input, .pc-form select, .pc-form textarea { margin-top: 5px; }
        .pc-form .form-row { display: flex; gap: 16px; flex-wrap: wrap; }
        .pc-form .form-row > * { flex: 1; min-width: 200px; }
        </style>
        <?php

        if ($success) {
            echo '<div class="notice notice-success"><p>? Request submitted successfully! The expert will respond shortly. We\'ll notify you by email.</p></div>';
            echo '<p><a href="' . esc_url(site_url('/college/my-classes')) . '" class="button">View My Classes</a></p>';
        } else {
            if ($errors) {
                echo '<div class="notice notice-error"><ul>';
                foreach ($errors as $err) {
                    echo '<li>' . esc_html($err) . '</li>';
                }
                echo '</ul></div>';
            }

            // Fetch experts
            $experts = get_users(['role' => 'expert','orderby' => 'display_name','order' => 'ASC','fields' => ['ID','display_name','user_email']]);

            if (empty($experts)) {
                echo '<div class="notice notice-warning"><p>No experts available at this time. Please contact support.</p></div>';
                return ob_get_clean();
            }

            ?>
            <h3>Request a College Class</h3>
            <form method="post" class="pc-form">
                <?php wp_nonce_field($nonce_action, '_pc_nonce'); ?>

                <label>Select Expert *
                    <select name="expert_id" required>
                        <option value="">— Choose an expert —</option>
                        <?php foreach ($experts as $e): ?>
                            <option value="<?php echo esc_attr($e->ID); ?>">
                                <?php echo esc_html($e->display_name . ' (' . $e->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Class Topic / Title *
                    <input type="text" name="class_topic" required class="regular-text" placeholder="e.g., Introduction to Data Science" />
                </label>

                <label>Description / Learning Objectives
                    <textarea name="class_description" rows="5" class="large-text" placeholder="Describe what students will learn..."></textarea>
                </label>

                <div class="form-row">
                    <label>Proposed Start Date & Time *
                        <input type="datetime-local" name="start_iso" required />
                    </label>
                    <label>Duration (minutes) *
                        <input type="number" name="duration_minutes" min="30" step="15" value="60" required />
                    </label>
                </div>

                <div class="form-row">
                    <label>Expected Capacity (students) *
                        <input type="number" name="capacity" min="5" step="1" value="100" required />
                    </label>
                    <label>Budget / Price Offer *
                        <input type="number" name="price_offer" min="0" step="0.01" placeholder="0.00" required />
                    </label>
                </div>

                <p>
                    <button type="submit" class="button button-primary button-large">Submit Request</button>
                </p>
            </form>
            <?php
        }

        return ob_get_clean();
    }

    /* -----------------------------
     *  Shortcode: /college/my-classes
     * --------------------------- */
    public function sc_my_classes() {
    if (!is_user_logged_in() || !current_user_can('college_admin')) {
        return '<div class="notice notice-error"><p>You need a College Admin account to view this page.</p></div>';
    }

    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT r.*,
               c.status              AS contract_status,
               c.pdf_path,
               c.sign_token,
               c.sign_token_expires,
               c.total_amount,
               c.order_id
        FROM {$this->tbl_requests} r
        LEFT JOIN {$this->tbl_contracts} c ON c.request_id = r.id
        WHERE r.college_user_id = %d
        ORDER BY r.id DESC
    ", get_current_user_id()));

    ob_start();
    ?>
    <h3>My Class Requests</h3>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th><th>Expert</th><th>Topic</th><th>When</th>
                <th>Status</th><th>Contract</th><th>Payment</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="8">No requests yet. <a href="<?php echo esc_url(site_url('/college/request-class')); ?>">Request a class</a></td></tr>
        <?php else:
            foreach ($rows as $r):
                $expert    = get_userdata($r->expert_user_id);

                // KEY FIX: booked = done, no payment UI needed at all
                $is_booked = ($r->status === 'booked');

                $pay_url      = '';
                $order        = null;
                $order_status = '';

                if (!$is_booked && !empty($r->order_id) && function_exists('wc_get_order')) {
                    $order = wc_get_order((int)$r->order_id);
                    if ($order) {
                        $order_status = $order->get_status();
                        if ($order->has_status(['pending', 'failed', 'on-hold'])) {
                            $pay_url = $order->get_checkout_payment_url();
                        }
                    }
                }
                ?>
                <tr>
                    <td>#<?php echo (int)$r->id; ?></td>
                    <td><?php echo esc_html($expert ? $expert->display_name : '—'); ?></td>
                    <td><?php echo esc_html($r->topic); ?></td>
                    <td><?php echo esc_html($r->proposed_start_iso); ?> (<?php echo (int)$r->duration_minutes; ?>m)</td>
                    <td><?php echo esc_html($r->status); ?></td>
                    <td>
                        <?php
                        if ($r->contract_status === 'signed' && $r->pdf_path) {
                            $uploads = wp_get_upload_dir();
                            $url = str_replace($uploads['basedir'], $uploads['baseurl'], (string)$r->pdf_path);
                            echo '<a href="' . esc_url($url) . '" target="_blank">Download PDF</a>';
                        } elseif ($r->contract_status === 'generated' && $r->sign_token && $this->token_valid($r->sign_token_expires)) {
                            $link = add_query_arg(['pc_contract' => $r->sign_token], site_url('/college/my-classes'));
                            echo '<a class="button" href="' . esc_url($link) . '">Review & Sign</a>';
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($is_booked): ?>
                            <!-- KEY FIX: booked ? Payment Completed, no Pay Now ever -->
                            <span style="color:#059669;font-weight:bold;">&#10003; Payment Completed</span>
                        <?php elseif ($order instanceof WC_Abstract_Order): ?>
                            <?php echo esc_html(ucfirst($order_status)); ?>
                            <?php if (!empty($pay_url)): ?>
                                <a class="button" href="<?php echo esc_url($pay_url); ?>">Pay now</a>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $is_booked ? '<em>&#10003; Scheduled</em>' : '—'; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php

    if (isset($_GET['pc_contract'])) {
        $token = sanitize_text_field($_GET['pc_contract']);
        echo $this->render_contract_signer($token);
    }

    return ob_get_clean();
}

    private function token_valid($expires_mysql) {
        if (!$expires_mysql) return false;
        $exp = strtotime($expires_mysql);
        return $exp && $exp > time();
    }

    private function get_user_ip() {
        if (function_exists('wc_get_user_ip')) {
            return wc_get_user_ip();
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function render_contract_signer($token) {
        global $wpdb;
        
        // Validate token
        $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_contracts} WHERE sign_token = %s", $token));
        if (!$c) {
            return '<div class="notice notice-error"><p>Invalid contract link.</p></div>';
        }
        
        if (!$this->token_valid($c->sign_token_expires)) {
            return '<div class="notice notice-error"><p>Expired contract link. Please contact support.</p></div>';
        }

        // Get request
        $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_requests} WHERE id = %d", $c->request_id));
        if (!$r) {
            return '<div class="notice notice-error"><p>Request not found.</p></div>';
        }
        
        if ((int)$r->college_user_id !== get_current_user_id()) {
            return '<div class="notice notice-error"><p>This contract is not available for your account.</p></div>';
        }

        // Get expert info
        $expert = get_userdata($r->expert_user_id);
        if (!$expert) {
            return '<div class="notice notice-error"><p>Expert information not found.</p></div>';
        }
        
        $terms = $this->get_opt(self::OPT_CONTRACT_TERMS_HTML);

        // Handle form submission FIRST (before rendering)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' 
            && isset($_POST['pc_contract_id']) 
            && (int)$_POST['pc_contract_id'] === (int)$c->id
            && isset($_POST['_pc_nonce']) 
            && wp_verify_nonce($_POST['_pc_nonce'], 'pc_sign_contract_'.$c->id)) {

            $signer = sanitize_text_field($_POST['pc_sign_name'] ?? '');
            $agree  = !empty($_POST['pc_agree']);
            
            if ($signer && $agree) {
                // Generate PDF
                $pdf_path = $this->generate_contract_pdf($c->id);
                
                // Update contract
                $wpdb->update($this->tbl_contracts, [
                    'status'            => 'signed',
                    'signed_at'         => current_time('mysql'),
                    'signed_by_user_id' => get_current_user_id(),
                    'signed_name'       => $signer,
                    'signed_ip'         => $this->get_user_ip(),
                    'pdf_path'          => $pdf_path,
                ], ['id' => $c->id], ['%s','%s','%d','%s','%s','%s'], ['%d']);

                // Create order
                $order_id = $this->create_pay_now_order($c->request_id, (float)$c->total_amount, get_current_user_id());
                
                if ($order_id) {
                    $wpdb->update($this->tbl_contracts, ['order_id' => $order_id], ['id' => $c->id]);
                    
                    $order = wc_get_order($order_id);
                    $pay_url = $order ? $order->get_checkout_payment_url() : wc_get_checkout_url();
                    
                    return '<div class="notice notice-success"><p><strong>? Contract signed successfully!</strong></p><p>Please complete payment to finalize your booking.</p><p><a class="button button-primary button-large" href="'. esc_url($pay_url) .'">Proceed to Payment</a></p></div>';
                } else {
                    return '<div class="notice notice-error"><p>Contract signed but order creation failed. Please contact support.</p></div>';
                }
            } else {
                return '<div class="notice notice-error"><p>Please fill in your name and agree to the terms.</p></div>';
            }
        }

        // Render the contract form
        ob_start(); 
        ?>
        <style>
        .pc-contract { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border: 1px solid #ddd; }
        .pc-contract-terms { background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa; }
        </style>
        <div class="pc-contract">
            <h3>?? Contract for College Class</h3>
            <p><strong>Request #<?php echo (int)$r->id; ?></strong> — <?php echo esc_html($r->topic); ?></p>
            
            <table class="widefat">
                <tr><th>Expert:</th><td><?php echo esc_html($expert->display_name); ?></td></tr>
                <tr><th>Proposed Start:</th><td><?php echo esc_html($c->class_start_iso ?: $r->proposed_start_iso); ?></td></tr>
                <tr><th>Duration:</th><td><?php echo (int)($c->duration_minutes ?: $r->duration_minutes); ?> minutes</td></tr>
                <tr><th>Capacity:</th><td><?php echo (int)$r->capacity; ?> students</td></tr>
                <tr><th>Amount:</th><td><strong><?php echo wc_price($c->total_amount); ?></strong></td></tr>
            </table>

            <div class="pc-contract-terms">
                <h4>Terms and Conditions</h4>
                <?php echo wp_kses_post($terms); ?>
            </div>

            <form method="post">
                <?php wp_nonce_field('pc_sign_contract_'.$c->id, '_pc_nonce'); ?>
                <input type="hidden" name="pc_contract_id" value="<?php echo (int)$c->id; ?>" />
                
                <p><label><strong>Your Full Name (Signer):</strong><br>
                    <input type="text" name="pc_sign_name" required class="regular-text" placeholder="Enter your full name" />
                </label></p>
                
                <p><label>
                    <input type="checkbox" name="pc_agree" value="1" required /> 
                    <strong>I have read and agree to the terms and conditions above</strong>
                </label></p>
                
                <p>
                    <button type="submit" class="button button-primary button-large">Sign Contract & Proceed to Payment</button>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /* -----------------------------
     *  REST Routes
     * --------------------------- */
    public function register_routes() {
        register_rest_route('platform-core/v1', '/college/response', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_expert_response'],
            'permission_callback' => [$this, 'perm_expert_response'],
        ]);

        register_rest_route('platform-core/v1', '/college/contract/sign', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_contract_sign'],
            'permission_callback' => function () { return is_user_logged_in() && current_user_can('college_admin'); }
        ]);
    }

    public function perm_expert_response(WP_REST_Request $req) {
        $request_id = absint($req['request_id'] ?? 0);
        if (!$request_id) return platform_core_user_is_expert();
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT expert_user_id FROM {$this->tbl_requests} WHERE id=%d", $request_id));
        return $row && (int)$row->expert_user_id === get_current_user_id();
    }

    public function api_expert_response(WP_REST_Request $req) {
    $action           = sanitize_text_field($req['action'] ?? '');
    $request_id       = absint($req['request_id'] ?? 0);
    $price            = floatval($req['price'] ?? 0);
    $start_iso        = sanitize_text_field($req['start_iso'] ?? '');
    $duration_minutes = absint($req['duration_minutes'] ?? 60);
    $note             = sanitize_text_field($req['note'] ?? '');

    if (!$request_id || !in_array($action, ['accept','reject','counter'], true)) {
        return new WP_REST_Response(['error' => 'Invalid action or request ID'], 400);
    }

    global $wpdb;
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_requests} WHERE id = %d", $request_id));
    
    if (!$r || (int)$r->expert_user_id !== get_current_user_id()) {
        return new WP_REST_Response(['error' => 'Request not found or access denied'], 403);
    }

    // Store expert response
    $wpdb->insert($this->tbl_responses, [
        'request_id'         => $request_id,
        'expert_user_id'     => get_current_user_id(),
        'response'           => $action,
        'price'              => $price,
        'proposed_start_iso' => $start_iso ?: $r->proposed_start_iso,
        'duration_minutes'   => $duration_minutes ?: $r->duration_minutes,
        'note'               => $note,
        'created_at'         => current_time('mysql')
    ], ['%d','%d','%s','%f','%s','%d','%s','%s']);

    if ($action === 'reject') {
        // REJECT: Update request status to rejected
        $wpdb->update(
            $this->tbl_requests, 
            [
                'status'     => 'rejected',
                'updated_at' => current_time('mysql')
            ], 
            ['id' => $request_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // TODO: Email college admin about rejection
        
        return ['ok' => true, 'status' => 'rejected', 'message' => 'Request declined'];
    }

    // ACCEPT or COUNTER: Create/update contract
    $total = max(0, $price);
    
    // Check if contract already exists
    $existing_contract = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$this->tbl_contracts} WHERE request_id = %d",
        $request_id
    ));
    
    if ($existing_contract) {
        // Update existing contract
        $wpdb->update(
            $this->tbl_contracts,
            [
                'total_amount'     => $total,
                'class_start_iso'  => $start_iso ?: $r->proposed_start_iso,
                'duration_minutes' => $duration_minutes ?: $r->duration_minutes,
                'status'           => 'generated',
                'sign_token'       => wp_generate_password(32, false),
                'sign_token_expires' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS * 7)
            ],
            ['id' => $existing_contract],
            ['%f', '%s', '%d', '%s', '%s', '%s'],
            ['%d']
        );
        $contract_id = $existing_contract;
    } else {
        // Create new contract
        $token = wp_generate_password(32, false);
        $exp   = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS * 7);
        
        $wpdb->insert($this->tbl_contracts, [
            'request_id'         => $request_id,
            'status'             => 'generated',
            'total_amount'       => $total,
            'class_start_iso'    => $start_iso ?: $r->proposed_start_iso,
            'duration_minutes'   => $duration_minutes ?: $r->duration_minutes,
            'sign_token'         => $token,
            'sign_token_expires' => $exp,
            'created_at'         => current_time('mysql')
        ], ['%d','%s','%f','%s','%d','%s','%s','%s']);
        
        $contract_id = $wpdb->insert_id;
    }
    
    // Update request status
    $wpdb->update(
        $this->tbl_requests, 
        [
            'status'      => 'pending_contract',
            'price_offer' => $total,
            'updated_at'  => current_time('mysql')
        ], 
        ['id' => $request_id],
        ['%s', '%f', '%s'],
        ['%d']
    );

    // TODO: Email college admin with contract link
    
    $response_msg = ($action === 'accept') ? 'Request accepted! Contract generated.' : 'Counter-offer sent.';
    
    return [
        'ok' => true, 
        'status' => 'pending_contract', 
        'contract_id' => $contract_id,
        'message' => $response_msg
    ];
}

    /* -----------------------------
     *  Contract helpers
     * --------------------------- */
    private function create_contract_from_response($request_id, $amount, $start_iso, $duration_minutes) {
        global $wpdb;
        $token = wp_generate_password(32, false);
        $exp   = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS * 7);

        $wpdb->insert($this->tbl_contracts, [
            'request_id'         => $request_id,
            'status'             => 'generated',
            'total_amount'       => $amount,
            'class_start_iso'    => $start_iso,
            'duration_minutes'   => $duration_minutes,
            'sign_token'         => $token,
            'sign_token_expires' => $exp,
            'created_at'         => current_time('mysql')
        ], ['%d','%s','%f','%s','%d','%s','%s','%s']);

        return (int)$wpdb->insert_id;
    }

    private function generate_contract_pdf($contract_id) {
        global $wpdb;
        $c = $wpdb->get_row($wpdb->prepare("SELECT c.*, r.topic, r.college_user_id, r.expert_user_id
                                            FROM {$this->tbl_contracts} c
                                            JOIN {$this->tbl_requests} r ON r.id = c.request_id
                                            WHERE c.id = %d", $contract_id));
        if (!$c) return '';

        $expert  = get_userdata($c->expert_user_id);
        $college = get_userdata($c->college_user_id);

        $placeholders = [
            '{{COMPANY_NAME}}'   => $this->get_opt(self::OPT_COMPANY_NAME, get_bloginfo('name')),
            '{{COMPANY_ADDRESS}}'=> nl2br(esc_html($this->get_opt(self::OPT_COMPANY_ADDRESS, ''))),
            '{{CONTRACT_ID}}'    => (int)$c->id,
            '{{TOPIC}}'          => esc_html($c->topic),
            '{{START}}'          => esc_html($c->class_start_iso),
            '{{DURATION}}'       => (int)$c->duration_minutes . ' minutes',
            '{{AMOUNT}}'         => wc_price($c->total_amount),
            '{{EXPERT_NAME}}'    => esc_html($expert ? $expert->display_name : ''),
            '{{COLLEGE_NAME}}'   => esc_html($college ? $college->display_name : ''),
            '{{TERMS_HTML}}'     => $this->get_opt(self::OPT_CONTRACT_TERMS_HTML)
        ];

        $tpl = file_get_contents(plugin_dir_path(__FILE__) . '../templates/contract-college.php');
        $html = strtr($tpl, $placeholders);

        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'platform-contracts';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        $pdf_filename = 'contract-'.$c->id.'.pdf';
        $pdf_path     = trailingslashit($dir) . $pdf_filename;

        if (class_exists('\Dompdf\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($pdf_path, $dompdf->output());
        } else {
            $pdf_path = str_replace('.pdf', '.html', $pdf_path);
            file_put_contents($pdf_path, $html);
        }

        return $pdf_path;
    }


    public function generate_contract_pdf_public($contract_id) {
    return $this->generate_contract_pdf($contract_id);
}

    /* -----------------------------
     *  Woo: create pending order
     * --------------------------- */
    private function create_pay_now_order($request_id, $amount, $customer_user_id) {
        if (!function_exists('wc_create_order')) return 0;

        $order = wc_create_order(['customer_id' => $customer_user_id]);
        $item_name = 'College Class (Request #'.(int)$request_id.')';

        $item = new WC_Order_Item_Fee();
        $item->set_name($item_name);
        $item->set_amount($amount);
        $item->set_total($amount);
        $order->add_item($item);

        $order->update_meta_data('_platform_request_id', (int)$request_id);
        $order->set_payment_method('razorpay');
        $order->calculate_totals(false);
        $order->set_status('pending');
        $order->save();

        global $wpdb;
        $wpdb->update($this->tbl_requests, ['order_id' => $order->get_id()], ['id' => $request_id]);

        return $order->get_id();
    }

    /* -----------------------------
     *  REST: Contract sign API
     * --------------------------- */
    public function api_contract_sign(WP_REST_Request $req) {
        $token  = sanitize_text_field($req['token'] ?? '');
        $name   = sanitize_text_field($req['name'] ?? '');
        if (!$token || !$name) return new WP_REST_Response(['error' => 'Missing fields'], 400);

        global $wpdb;
        $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_contracts} WHERE sign_token = %s", $token));
        if (!$c || !$this->token_valid($c->sign_token_expires)) {
            return new WP_REST_Response(['error' => 'Invalid or expired token'], 400);
        }

        if ((int)$c->signed_by_user_id && $c->status === 'signed') {
            return ['ok' => true, 'message' => 'Already signed'];
        }

        $pdf_path = $this->generate_contract_pdf($c->id);
        $wpdb->update($this->tbl_contracts, [
            'status'            => 'signed',
            'signed_at'         => current_time('mysql'),
            'signed_by_user_id' => get_current_user_id(),
            'signed_name'       => $name,
            'signed_ip'         => $this->get_user_ip(),
            'pdf_path'          => $pdf_path,
        ], ['id' => $c->id]);

        $order_id = $this->create_pay_now_order((int)$c->request_id, (float)$c->total_amount, get_current_user_id());
        $wpdb->update($this->tbl_contracts, ['order_id' => $order_id], ['id' => $c->id]);

        return ['ok' => true, 'order_id' => $order_id, 'pay_url' => wc_get_checkout_url()];
    }
}

new PlatformCore_Flow7_College();