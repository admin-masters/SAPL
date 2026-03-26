<?php
/**
 * Flow 7 � College user requests/confirm/pays class
 * Shortcodes:
 *   [platform_college_request_class]  -> /college/request-class
 *   [platform_college_my_classes]     -> /college/my-classes
 *   [platform_expert_college_requests] -> /expert/college-requests
 *
 * REST:
 *   POST  /platform-core/v1/college/response      (expert accepts/rejects/counter)
 *   POST  /platform-core/v1/college/contract/sign (college signs contract)
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

class PlatformCore_Flow7_College {

    /** Option keys and defaults */
    const OPTS_KEY                        = 'platform_core_college_settings';
    const OPT_REMOTE_CLASS_SERVICE_ID     = 'remote_class_service_id';
    const OPT_CONTRACT_TERMS_HTML         = 'contract_terms_html';
    const OPT_COMPANY_NAME                = 'company_name';
    const OPT_COMPANY_ADDRESS             = 'company_address';

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

        // // Woo hooks
        // add_action('woocommerce_payment_complete',      [$this, 'on_payment_complete']);
        // add_action('woocommerce_order_status_processing', [$this, 'on_payment_complete']);
        // add_action('woocommerce_order_status_completed',  [$this, 'on_payment_complete']);

        // Admin settings
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        // Robust finalization hooks
        add_action('woocommerce_payment_complete',            [$this, 'finalize_order_booking'], 10, 1);
        add_action('woocommerce_thankyou',                    [$this, 'finalize_order_booking'], 10, 1);
        add_action('woocommerce_order_status_completed',      [$this, 'finalize_order_booking'], 10, 1);
        add_action('woocommerce_order_status_processing',     [$this, 'finalize_order_booking'], 10, 1);
        add_action('woocommerce_order_status_changed', function($order_id, $old, $new) {
            if (in_array($new, ['processing','completed'], true)) {
                $this->finalize_order_booking($order_id);
            }
        }, 10, 3);


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

    // Requests table
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

    // Responses table
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

    // Contracts table - THIS IS THE MISSING ONE
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

    // Calendar map table
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
    }

    private function get_opt($key, $default = '') {
        $opts = get_option(self::OPTS_KEY, []);
        return isset($opts[$key]) ? $opts[$key] : $default;
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
        SELECT r.*, c.status AS contract_status, c.total_amount
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
        .pc-expert-requests table { width: 100%; margin: 20px 0; }
        .pc-expert-requests details { margin: 10px 0; }
        .pc-actions form { margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px; }
        .pc-actions label { display: block; margin: 8px 0; }
        .pc-actions input[type="number"],
        .pc-actions input[type="datetime-local"],
        .pc-actions input[type="text"] { width: 100%; max-width: 300px; }
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
              ?>
                <tr data-id="<?php echo (int)$r->id; ?>">
                  <td>#<?php echo (int)$r->id; ?></td>
                  <td><?php echo esc_html($college_name); ?></td>
                  <td><?php echo esc_html($r->topic); ?><br><small><?php echo esc_html(substr($r->description, 0, 100)); ?></small></td>
                  <td><?php echo esc_html($r->proposed_start_iso); ?></td>
                  <td><?php echo (int)$r->duration_minutes; ?> min</td>
                  <td><?php echo (int)$r->capacity; ?> students</td>
                  <td><?php echo esc_html(number_format($r->price_offer, 2)); ?></td>
                  <td><strong><?php echo esc_html($r->status); ?></strong></td>
                  <td>
                    <?php if (in_array($r->status, ['requested','pending_contract'], true)): ?>
                      <details>
                        <summary class="button">Respond</summary>
                        <div class="pc-actions">
                          <h4>Accept</h4>
                          <form class="pc-accept" data-action="accept">
                            <input type="hidden" name="action" value="accept">
                            <label>Price: <input type="number" step="0.01" name="price" value="<?php echo esc_attr($r->price_offer ?: 0); ?>" required></label>
                            <label>Start: <input type="datetime-local" name="start_iso" value="<?php echo esc_attr(str_replace(' ', 'T', $r->proposed_start_iso)); ?>" required></label>
                            <label>Duration (min): <input type="number" name="duration_minutes" value="<?php echo (int)$r->duration_minutes; ?>" required></label>
                            <button type="submit" class="button button-primary">Accept Request</button>
                          </form>
                          
                          <h4>Send Counter-Offer</h4>
                          <form class="pc-counter" data-action="counter">
                            <input type="hidden" name="action" value="counter">
                            <label>Price: <input type="number" step="0.01" name="price" value="<?php echo esc_attr($r->price_offer ?: 0); ?>" required></label>
                            <label>Start: <input type="datetime-local" name="start_iso" value="<?php echo esc_attr(str_replace(' ', 'T', $r->proposed_start_iso)); ?>" required></label>
                            <label>Duration (min): <input type="number" name="duration_minutes" value="<?php echo (int)$r->duration_minutes; ?>" required></label>
                            <label>Note: <input type="text" name="note" placeholder="Reason for counter-offer"></label>
                            <button type="submit" class="button">Send Counter</button>
                          </form>
                          
                          <h4>Reject</h4>
                          <form class="pc-reject" data-action="reject">
                            <input type="hidden" name="action" value="reject">
                            <label>Reason: <input type="text" name="note" placeholder="Optional reason"></label>
                            <button type="submit" class="button">Reject Request</button>
                          </form>
                        </div>
                      </details>
                    <?php else: ?>
                      <em>�</em>
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
            const payload = {
              request_id: parseInt(id, 10),
              action: formData.get('action'),
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
                alert(json && json.error ? json.error : 'Request failed');
                return;
              }
              
              alert('Response saved successfully!');
              location.reload();
            } catch (error) {
              console.error('Error:', error);
              alert('An error occurred. Check console for details.');
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
                        <option value="">� Choose an expert �</option>
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
                        <input type="number" name="capacity" min="5" step="1" value="60" required />
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
            SELECT r.*, c.status AS contract_status, c.pdf_path, c.sign_token, c.sign_token_expires, c.total_amount, c.order_id
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
                    <th>ID</th><th>Expert</th><th>Topic</th><th>When</th><th>Status</th><th>Contract</th><th>Payment</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                <tr><td colspan="8">No requests yet. <a href="<?php echo esc_url(site_url('/college/request-class')); ?>">Request a class</a></td></tr>
                <?php else:
                    foreach ($rows as $r):
                        $expert = get_userdata($r->expert_user_id);
                        $pay_url = '';
                        if (!empty($r->order_id) && function_exists('wc_get_order')) {
                            $order = wc_get_order($r->order_id);
                            if ($order && $order->has_status(['pending','failed'])) {
                                $pay_url = $order->get_checkout_payment_url();
                            }
                        }
                        ?>
                        <tr>
                            <td>#<?php echo (int)$r->id; ?></td>
                            <td><?php echo esc_html($expert ? $expert->display_name : '�'); ?></td>
                            <td><?php echo esc_html($r->topic); ?></td>
                            <td><?php echo esc_html($r->proposed_start_iso); ?> (<?php echo (int)$r->duration_minutes; ?>m)</td>
                            <td><?php echo esc_html($r->status); ?></td>
                            <td>
                                <?php
                                if ($r->contract_status === 'signed' && $r->pdf_path) {
                                    $uploads = wp_get_upload_dir();
$url = str_replace($uploads['basedir'], $uploads['baseurl'], (string)$r->pdf_path);
echo '<a href="'. esc_url($url) .'" target="_blank">Download PDF</a>';
                                } elseif ($r->contract_status === 'generated' && $r->sign_token && $this->token_valid($r->sign_token_expires)) {
                                    $link = add_query_arg(['pc_contract' => $r->sign_token], site_url('/college/my-classes'));
                                    echo '<a class="button" href="'. esc_url($link) .'">Review & Sign</a>';
                                } else {
                                    echo '�';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($r->order_id) && isset($order)) {
                                    echo esc_html(ucfirst($order->get_status()));
                                    if ($pay_url && $order->has_status(['pending','failed'])) {
                                        echo ' <a class="button" href="'.esc_url($pay_url).'">Pay now</a>';
                                    }
                                } else {
                                    echo '�';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($r->status === 'booked') {
                                    echo '<em>Scheduled</em>';
                                } else {
                                    echo '�';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php

        // Contract signer
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
        <p><strong>Request #<?php echo (int)$r->id; ?></strong> � <?php echo esc_html($r->topic); ?></p>
        
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
            return new WP_REST_Response(['error' => 'Bad request'], 400);
        }

        global $wpdb;
        $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_requests} WHERE id = %d", $request_id));
        if (!$r || (int)$r->expert_user_id !== get_current_user_id()) {
            return new WP_REST_Response(['error' => 'Not allowed'], 403);
        }

        // Store response
        $wpdb->insert($this->tbl_responses, [
            'request_id'       => $request_id,
            'expert_user_id'   => get_current_user_id(),
            'response'         => $action,
            'price'            => $price,
            'proposed_start_iso'=> $start_iso ?: $r->proposed_start_iso,
            'duration_minutes' => $duration_minutes ?: $r->duration_minutes,
            'note'             => $note,
            'created_at'       => current_time('mysql')
        ], ['%d','%d','%s','%f','%s','%d','%s','%s']);

        if ($action === 'reject') {
            $wpdb->update($this->tbl_requests, ['status' => 'rejected', 'updated_at' => current_time('mysql')], ['id' => $request_id]);
            return ['ok' => true, 'status' => 'rejected'];
        }

        // Accept/counter -> create contract
        $total = max(0, $price);
        $contract_id = $this->create_contract_from_response($request_id, $total, $start_iso ?: $r->proposed_start_iso, $duration_minutes ?: $r->duration_minutes);
        $wpdb->update($this->tbl_requests, [
            'status'     => 'pending_contract',
            'updated_at' => current_time('mysql')
        ], ['id' => $request_id]);

        return ['ok' => true, 'status' => 'pending_contract', 'contract_id' => $contract_id];
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
     *  Woo payment complete
     * --------------------------- */
    public function finalize_order_booking($order_id) {
            if (!function_exists('wc_get_order')) return;
            $order = wc_get_order($order_id);
            if (!$order) return;

            // Idempotency
            if ((int)$order->get_meta('_platform_finalized') === 1) {
                return;
            }

            // 1) Resolve request id
            $request_id = (int)$order->get_meta('_platform_request_id');

            if (!$request_id) {
                global $wpdb;
                // Fallback: find request via contract mapping
                $contract = $wpdb->get_row($wpdb->prepare(
                    "SELECT request_id FROM {$this->tbl_contracts} WHERE order_id = %d LIMIT 1", $order_id
                ));
                if ($contract) {
                    $request_id = (int)$contract->request_id;
                    // (optional) backfill meta so future calls are cheap
                    $order->update_meta_data('_platform_request_id', $request_id);
                    $order->save_meta_data();
                }
            }

            if (!$request_id) {
                $order->add_order_note('platform-core: finalize skipped (no request_id found on order or contract).');
                return;
            }

            // 2) Create Amelia appointment if missing, else fetch Zoom
            global $wpdb;
            $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_requests} WHERE id = %d", $request_id));
            if (!$req) {
                $order->add_order_note('platform-core: finalize skipped (request not found).');
                return;
            }

            $booking = null;
            if (empty($req->appointment_id)) {
                $booking = $this->create_amelia_booking_from_request($request_id);
                // if (!$booking) {
                //     $order->add_order_note('platform-core: Amelia booking failed (check service id / employee mapping).');
                //     return;
                // }
                if (!$booking || empty($booking['appointmentId'])) {
                    $order->add_order_note('platform-core: Amelia booking failed – not finalized. Check Service ID / Employee mapping and Amelia logs.');
                    return;
                }
                if (!empty($booking['appointmentId'])) {
                    $wpdb->update($this->tbl_requests, ['appointment_id' => (int)$booking['appointmentId']], ['id' => $request_id]);
                }
            } else {
                // Already have appointment (idempotency) — try to hydrate Zoom/calendar
                $booking = [
                    'start'        => $req->class_start_iso ?? $req->proposed_start_iso,
                    'end'          => '', // not essential for calendar patch here
                    'zoomJoinUrl'  => ''
                ];
            }

            // 3) Calendar insert (idempotent insert; if row exists, patch instead)
            $this->insert_google_events_from_booking($request_id, $booking);

            // 4) Status -> booked
            $wpdb->update($this->tbl_requests, [
                'status'     => 'booked',
                'updated_at' => current_time('mysql')
            ], ['id' => $request_id]);

            // 5) Idempotency mark + order note
            $order->update_meta_data('_platform_finalized', 1);
            $order->add_order_note('platform-core: class finalized (Amelia booking + calendar + status=booked).');
            $order->save();
        }

    public function on_payment_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $request_id = (int)$order->get_meta('_platform_request_id');
        if (!$request_id) return;

        $booking = $this->create_amelia_booking_from_request($request_id);
        if (!$booking) return;

        global $wpdb;

        if (!empty($booking['appointmentId'])) {
            $wpdb->update($this->tbl_requests, ['appointment_id' => (int)$booking['appointmentId']], ['id' => $request_id]);
        }

        $this->insert_google_events_from_booking($request_id, $booking);

        $wpdb->update($this->tbl_requests, [
            'status'     => 'booked',
            'updated_at' => current_time('mysql')
        ], ['id' => $request_id]);
    }

    /* -----------------------------
     *  Amelia booking
     * --------------------------- */
    private function create_amelia_booking_from_request($request_id) {
        global $wpdb;
        $r = $wpdb->get_row($wpdb->prepare("SELECT r.*, c.class_start_iso, c.duration_minutes
                                            FROM {$this->tbl_requests} r
                                            JOIN {$this->tbl_contracts} c ON c.request_id = r.id
                                            WHERE r.id = %d", $request_id));
        if (!$r) return null;

        $serviceId  = (int)$this->get_opt(self::OPT_REMOTE_CLASS_SERVICE_ID, 6);
        // AFTER (fallback + clearer logging)
        $employeeId = (int)get_user_meta($r->expert_user_id, 'platform_amelia_employee_id', true);
        if (!$employeeId) {
            $employeeId = (int)get_user_meta($r->expert_user_id, 'amelia_employee_id', true);
        }
        if (!$serviceId || !$employeeId) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(sprintf(
                '[platform-core] Amelia booking missing mapping: serviceId=%s, employeeId=%s, expertUser=%d',
                $serviceId ?: '∅', $employeeId ?: '∅', $r->expert_user_id
                ), ['source' => 'platform-core']);
            }
            return null;
        }

        $start   = $r->class_start_iso ?: $r->proposed_start_iso;
        $end_iso = gmdate('Y-m-d\TH:i:s\Z', strtotime($start) + (int)$r->duration_minutes * 60);

        // if (!class_exists('\AmeliaBooking\Infrastructure\WP\AmeliaBookingPlugin')) {
        //     return ['start' => $start, 'end' => $end_iso, 'zoomJoinUrl' => '', 'appointmentId' => 1];
        // }

        // $payload = [
        //     'serviceId'   => $serviceId,
        //     'providerId'  => $employeeId,
        //     'status'      => 'approved',
        //     'bookingStart'=> $start,
        //     'bookingEnd'  => $end_iso,
        //     'utcOffset'   => 0,
        //     'customers' => [[
        //         'id'     => (int)$r->college_user_id,
        //         'status' => 'approved',
        //         'info'   => ['email' => get_userdata($r->college_user_id)->user_email]]
        //     ]];

        //     $wpCustomer = get_userdata($r->college_user_id);
        //     $ameliaCustomerId = (int)$wpdb->get_var($wpdb->prepare(
        //         "SELECT id FROM {$wpdb->prefix}amelia_users WHERE email = %s AND type = 'customer'",
        //         $wpCustomer->user_email
        //     ));

        //     $customerBlock = $ameliaCustomerId
        //         ? ['id' => $ameliaCustomerId, 'status' => 'approved']
        //         : [
        //             'status' => 'approved',
        //             'info'   => [
        //                 'firstName' => $wpCustomer->first_name ?: $wpCustomer->display_name,
        //                 'lastName'  => $wpCustomer->last_name  ?: '',
        //                 'email'     => $wpCustomer->user_email
        //             ]
        //         ];      
        
        // 'customers' => [ $customerBlock ],
        // Build a customer block Amelia will accept: use existing Amelia customer if found, else let Amelia create one.
        $wpCustomer = get_userdata($r->college_user_id);
        $customerEmail = $wpCustomer ? $wpCustomer->user_email : '';

        global $wpdb;
        $ameliaCustomerId = (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}amelia_users WHERE email = %s AND type = 'customer' LIMIT 1",
                $customerEmail
            )
        );

        $customerBlock = $ameliaCustomerId
            ? ['id' => $ameliaCustomerId, 'status' => 'approved']
            : [
                'status' => 'approved',
                'info'   => [
                    'firstName' => $wpCustomer && $wpCustomer->first_name ? $wpCustomer->first_name : ($wpCustomer ? $wpCustomer->display_name : ''),
                    'lastName'  => $wpCustomer && $wpCustomer->last_name  ? $wpCustomer->last_name  : '',
                    'email'     => $customerEmail
                ]
            ];

// ...
        $payload = [
            'serviceId'    => $serviceId,
            'providerId'   => $employeeId,
            'status'       => 'approved',
            'bookingStart' => $start,
            'bookingEnd'   => $end_iso,
            'utcOffset'    => 0,
            'customers'    => [ $customerBlock ],
        ];

        if (function_exists('platform_core_amelia_create_appointment')) {
            $res = platform_core_amelia_create_appointment($payload);
        } else {
            $res = $this->amelia_http('appointments', $payload);
        }

        // $apptId = 0;
        // if (is_array($res) && !empty($res['data']['appointments'][0]['id'])) {
        //     $apptId = (int)$res['data']['appointments'][0]['id'];
        // }

        $zoomLink = '';
        if (is_array($res) && !empty($res['data']['appointments'][0]['zoomJoinUrl'])) {
            $zoomLink = $res['data']['appointments'][0]['zoomJoinUrl'];
        }

        // return ['start' => $start, 'end' => $end_iso, 'zoomJoinUrl' => $zoomLink, 'appointmentId' => $apptId];
        if (!is_array($res) || empty($res['data']['appointments'][0]['id'])) {
             // Let caller treat this as a failure
             return null;
        }
        $apptId  = (int)$res['data']['appointments'][0]['id'];
        $zoomLink = !empty($res['data']['appointments'][0]['zoomJoinUrl'])
            ? $res['data']['appointments'][0]['zoomJoinUrl']
            : '';
        return ['start' => $start, 'end' => $end_iso, 'zoomJoinUrl' => $zoomLink, 'appointmentId' => $apptId];
    }

    private function amelia_http($resource, $payload) {
        // $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/appointments');
        $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/' . ltrim($resource, '/'));
        $resp = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload)
        ]);
        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('[platform-core] Amelia HTTP '.$code.' '.$body, ['source' => 'platform-core']);
            }
            return null;
        }
        if (is_wp_error($resp)) return null;
        return json_decode(wp_remote_retrieve_body($resp), true);
    }

    /* -----------------------------
     *  Google Calendar
     * --------------------------- */
    private function insert_google_events_from_booking($request_id, array $booking) {
        if (!function_exists('platform_core_google_insert_event')) {
            return;
        }

        global $wpdb;
        $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_requests} WHERE id = %d", $request_id));
        $expert  = get_userdata($r->expert_user_id);
        $college = get_userdata($r->college_user_id);

        $summary = 'College Class: ' . $r->topic;
        $desc    = "Expert: {$expert->display_name}\nZoom: {$booking['zoomJoinUrl']}";
        $att     = array_filter([
            $college ? $college->user_email : '',
            $expert  ? $expert->user_email : '',
        ]);

        $eventId = platform_core_google_insert_event([
            'summary'     => $summary,
            'description' => $desc,
            'start'       => $booking['start'],
            'end'         => $booking['end'],
            'attendees'   => $att
        ]);

        if ($eventId) {
            $wpdb->insert($this->tbl_calendar_map, [
                'source'        => 'flow7_college',
                'object_id'     => $request_id,
                'google_event_id'=> $eventId,
                'zoom_url'      => $booking['zoomJoinUrl'],
                'created_at'    => current_time('mysql')
            ], ['%s','%d','%s','%s','%s']);
        }
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