<?php
/**
 * Plugin Name: Platform Core (roles & basics)
 * Description: Adds custom roles, post types, secure encryption utilities, and foundational hooks for the education platform.
 * Version: 0.1.1
 * Author: Inditech
 */

if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'inc/support-modal.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinars.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinar-invites.php';
require_once plugin_dir_path(__FILE__) . 'inc/tutorials.php';
require_once plugin_dir_path(__FILE__) . 'inc/flow5-expert-tutorials.php';
require_once plugin_dir_path(__FILE__) . 'inc/expert-hub-tabs.php';
require_once plugin_dir_path(__FILE__) . 'inc/publishing-flow6.php'; 
require_once plugin_dir_path(__FILE__) . 'inc/flow7-college.php';
require_once plugin_dir_path(__FILE__) . 'inc/flow7-diagnostics.php';  
require_once plugin_dir_path(__FILE__) . 'inc/flow8-college-class.php'; 
require_once plugin_dir_path(__FILE__) . 'inc/flow9-college-recap.php'; 
require_once plugin_dir_path(__FILE__) . 'inc/flow10-ai-cme-credits.php';
require_once plugin_dir_path(__FILE__) . 'inc/core-amelia-helpers.php';
require_once plugin_dir_path(__FILE__) . 'inc/college_admin_register.php';
require_once plugin_dir_path(__FILE__) . 'inc/student_dashboard.php';
require_once plugin_dir_path(__FILE__) . 'inc/platform_college_dashboard_ui.php';
require_once plugin_dir_path(__FILE__) . 'inc/pediatric_landing_page.php';
require_once plugin_dir_path(__FILE__) . 'inc/update_availability.php';
require_once plugin_dir_path(__FILE__) . 'inc/flow7-college-expert-ui.php';
require_once plugin_dir_path(__FILE__) . 'inc/upload_material.php';
require_once plugin_dir_path(__FILE__) . 'inc/platform_college_sessions_dashboard.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinar_schedule.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinar_payment.php';
require_once plugin_dir_path(__FILE__) . 'inc/contract-sessions.php';
require_once plugin_dir_path(__FILE__) . 'inc/shortlisted-educators.php';
require_once plugin_dir_path(__FILE__) . 'inc/educator-profile.php';
require_once plugin_dir_path(__FILE__) . 'inc/sign-contract.php';
require_once plugin_dir_path(__FILE__) . 'inc/role-selector.php';
require_once plugin_dir_path(__FILE__) . 'inc/expert-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'inc/medical-expert-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinar-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinar-library.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinar-info.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinar-expert-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'inc/student-educator-profile.php';
require_once plugin_dir_path(__FILE__) . 'inc/free-webinar-payment.php';
require_once plugin_dir_path(__FILE__) . 'inc/paid-webinar-payment.php';
require_once plugin_dir_path(__FILE__) . 'inc/webinar-booking-expiry.php';
require_once plugin_dir_path(__FILE__) . 'inc/session-info.php';
require_once plugin_dir_path(__FILE__) . 'inc/invoices.php';







/**
 * ---------------------------------------------------------
 * ROLE CREATION
 * ---------------------------------------------------------
 */

function platform_core_add_roles() {
  add_role('student', 'Student', [
    'read' => true,
  ]);
  add_role('expert', 'Expert', [
    'read' => true,
    'upload_files' => true,
  ]);
  add_role('college_admin', 'College Admin', [
    'read' => true,
    'upload_files' => true,
  ]);
  add_role('publisher', 'Publisher', [
    'read'                   => true,
    'upload_files'           => true,
    'edit_posts'             => true,
    'edit_published_posts'   => true,
    'delete_posts'           => false,
    'delete_published_posts' => false,
  ]);
}
register_activation_hook(__FILE__, 'platform_core_add_roles');

add_action('admin_init', function () {
  foreach (['student', 'expert', 'college_admin', 'publisher'] as $role) {
    if (!get_role($role)) platform_core_add_roles();
  }
});

/**
 * ---------------------------------------------------------
 * ENCRYPTION UTILITIES
 * ---------------------------------------------------------
 */
function platform_core_user_is_expert($user_id = null) {
    $u = $user_id ? get_userdata($user_id) : wp_get_current_user();
    return $u && in_array('expert', (array) $u->roles, true);
}

function pcore_secret_key() {
  return hash('sha256', wp_salt('auth') . wp_salt('secure_auth'));
}

function pcore_encrypt($plain) {
  $key = pcore_secret_key();
  $iv  = substr(hash('sha256', wp_salt('logged_in')), 0, 16);
  return base64_encode(openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));
}

function pcore_decrypt($cipher) {
  $key = pcore_secret_key();
  $iv  = substr(hash('sha256', wp_salt('logged_in')), 0, 16);
  return openssl_decrypt(base64_decode($cipher), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * ---------------------------------------------------------
 * LIGHTWEIGHT HEALTHCHECK
 * ---------------------------------------------------------
 */
add_action('template_redirect', function () {
  if (!array_key_exists('healthcheck', $_GET)) {
    return;
  }

  nocache_headers();
  status_header(200);
  header('Content-Type: text/plain; charset=utf-8');
  header('X-Platform-Core-Healthcheck: ok');

  echo 'platform-core-healthcheck-ok';
  exit;
}, 0);

/**
 * ---------------------------------------------------------
 * GOOGLE OAUTH (SECURE STORAGE)
 * ---------------------------------------------------------
 */
function pcore_save_google_secrets() {
  if (!current_user_can('manage_options')) return;
  update_option(
    'pcore_google_site_client_id',
    pcore_encrypt('57926672783-6d4vr7f7ec7bjrspqbpoehcfrvdh06hp.apps.googleusercontent.com'),
    false
  );
  update_option(
    'pcore_google_site_client_secret',
    pcore_encrypt('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'),
    false
  );
  error_log('? Google OAuth secrets saved successfully (encrypted).');
}
// add_action('init', 'pcore_save_google_secrets');

/**
 * ---------------------------------------------------------
 * GOOGLE OAUTH REST ENDPOINTS
 * ---------------------------------------------------------
 */
add_action('rest_api_init', function () {
  register_rest_route('platform-core/v1', '/google/oauth/start', [
    'methods'  => 'GET',
    'callback' => 'pcore_google_oauth_start',
    'permission_callback' => '__return_true',
  ]);
  register_rest_route('platform-core/v1', '/google/oauth/callback', [
    'methods'  => 'GET',
    'callback' => 'pcore_google_oauth_callback',
    'permission_callback' => '__return_true',
  ]);
});

function pcore_google_oauth_start(WP_REST_Request $r) {
  $client_id = pcore_decrypt(get_option('pcore_google_site_client_id'));
  $redirect  = site_url('/wp-json/platform-core/v1/google/oauth/callback');
  $state     = wp_create_nonce('pcore_gcal_oauth');
  $scopes    = urlencode('https://www.googleapis.com/auth/calendar');
  $auth = 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code'
       . "&client_id={$client_id}&redirect_uri=" . urlencode($redirect)
       . "&scope={$scopes}&access_type=offline&include_granted_scopes=true&prompt=consent&state={$state}";
  return new WP_REST_Response(['auth_url' => $auth]);
}

function pcore_google_oauth_callback(WP_REST_Request $r) {
  $code  = $r->get_param('code');
  $state = $r->get_param('state');
  if (!wp_verify_nonce($state, 'pcore_gcal_oauth')) {
    return new WP_REST_Response(['error' => 'Invalid state'], 400);
  }
  $client_id     = pcore_decrypt(get_option('pcore_google_site_client_id'));
  $client_secret = pcore_decrypt(get_option('pcore_google_site_client_secret'));
  $redirect      = site_url('/wp-json/platform-core/v1/google/oauth/callback');
  $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    'body'    => [
      'code' => $code,
      'client_id' => $client_id,
      'client_secret' => $client_secret,
      'redirect_uri' => $redirect,
      'grant_type' => 'authorization_code',
    ],
    'timeout' => 20,
  ]);
  if (is_wp_error($resp))
    return new WP_REST_Response(['error' => $resp->get_error_message()], 500);
  $data = json_decode(wp_remote_retrieve_body($resp), true);
  $user_id = get_current_user_id();
  update_user_meta($user_id, 'pcore_gcal_refresh', pcore_encrypt($data['refresh_token'] ?? ''));
  update_user_meta($user_id, 'pcore_gcal_access',  pcore_encrypt($data['access_token'] ?? ''));
  update_user_meta($user_id, 'pcore_gcal_token_exp', time() + intval($data['expires_in'] ?? 0));
  return new WP_REST_Response(['status' => 'connected']);
}

/**
 * ---------------------------------------------------------
 * CUSTOM POST TYPES
 * ---------------------------------------------------------
 */
add_action('init', function () {
  register_post_type('webinar_archive', [
    'label' => 'Webinar Archives',
    'public' => true,
    'publicly_queryable' => true,
    'show_in_rest' => true,
    'hierarchical' => false,
    'menu_icon' => 'dashicons-video-alt3',
    'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'author', 'revisions'],
    'rewrite' => ['slug' => 'webinar-archive'],
    'has_archive' => true,
  ]);
  register_post_type('ai_cme_module', [
    'label' => 'AI-CME Modules',
    'public' => true,
    'publicly_queryable' => true,
    'show_in_rest' => true,
    'hierarchical' => false,
    'menu_icon' => 'dashicons-welcome-learn-more',
    'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
    'rewrite' => ['slug' => 'ai-cme'],
    'has_archive' => true,
  ]);
});

/**
 * ---------------------------------------------------------
 * AI-CME MODULE META BOX (Linked Product)
 * ---------------------------------------------------------
 */
add_action('add_meta_boxes', function () {
  add_meta_box(
    'pcore_ai_module_product',
    'Linked Product',
    'pcore_ai_module_product_box',
    'ai_cme_module',
    'side',
    'default'
  );
});

function pcore_ai_module_product_box($post) {
  wp_nonce_field('pcore_save_ai_module_product', 'pcore_ai_module_product_nonce');
  $product_id = (int) get_post_meta($post->ID, 'linked_product_id', true);
  if (function_exists('wp_enqueue_script')) {
    wp_enqueue_script('wc-enhanced-select');
    wp_enqueue_style('woocommerce_admin_styles');
  }
  ?>
  <p><label for="pcore_linked_product_id"><strong>Woo Product</strong></label></p>
  <select
    class="wc-product-search"
    style="width:100%;"
    id="pcore_linked_product_id"
    name="pcore_linked_product_id"
    data-placeholder="<?php esc_attr_e('Search for a product ','platform-core');?>"
    data-action="woocommerce_json_search_products_and_variations"
    data-allow_clear="true">
      <?php if ($product_id) : ?>
        <option value="<?php echo esc_attr($product_id); ?>" selected>
          <?php echo esc_html( get_the_title($product_id) ); ?>
        </option>
      <?php endif; ?>
  </select>
  <p class="description">Choose the WooCommerce product that sells access to this module.</p>
  <?php
}

add_action('save_post_ai_cme_module', function ($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!isset($_POST['pcore_ai_module_product_nonce']) ||
      !wp_verify_nonce($_POST['pcore_ai_module_product_nonce'], 'pcore_save_ai_module_product')) return;
  if (!current_user_can('edit_post', $post_id)) return;
  $new = isset($_POST['pcore_linked_product_id']) ? absint($_POST['pcore_linked_product_id']) : 0;
  if ($new) {
    update_post_meta($post_id, 'linked_product_id', $new);
  } else {
    delete_post_meta($post_id, 'linked_product_id');
  }
});

add_action('admin_enqueue_scripts', function ($hook) {
  global $typenow;
  if (($hook === 'post-new.php' || $hook === 'post.php') && $typenow === 'ai_cme_module') {
    wp_enqueue_script('wc-enhanced-select');
    wp_enqueue_style('woocommerce_admin_styles');
  }
});

function pcore_user_can_launch_module($user_id, $module_id){
  global $wpdb;
  $tbl = $wpdb->prefix.'ai_cme_entitlements';
  $active = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $tbl
     WHERE user_id=%d AND module_id=%d
       AND status='active'
       AND (expires_at IS NULL OR expires_at > NOW())",
    $user_id, $module_id
  ));
  if ($active) return true;
  return false;
}

function pcore_get_launch_url($module_id){
  return add_query_arg('module', (int)$module_id, site_url('/ai-cme/launch'));
}

/**
 * ---------------------------------------------------------
 * AI-CME LAUNCHER
 * ---------------------------------------------------------
 */
add_action('tutor_after_student_signup', function ($user_id) {
    if (!$user_id) return;
    $user = new WP_User($user_id);
    $user->set_role('student');
});

add_action('init', function () {
  add_rewrite_rule('^ai-cme/launch/?$', 'index.php?pcore_launch=1', 'top');
});

add_filter('query_vars', function ($vars) {
  $vars[] = 'pcore_launch';
  $vars[] = 'module';
  return $vars;
});

add_filter('amelia_frontend_timezone', function($tz) {
    return 'Asia/Kolkata';
});

function pcore_base64url($data) {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function pcore_jwt_sign(array $payload, $secret) {
  $header   = ['alg' => 'HS256', 'typ' => 'JWT'];
  $segments = [ pcore_base64url(json_encode($header)), pcore_base64url(json_encode($payload)) ];
  $sig      = hash_hmac('sha256', implode('.', $segments), $secret, true);
  $segments[] = pcore_base64url($sig);
  return implode('.', $segments);
}

function pcore_ai_target_url() {
  return get_option('pcore_ai_cme_target_url', '');
}

function pcore_ai_shared_secret() {
  $stored = get_option('pcore_ai_cme_jwt_secret', '');
  if (!empty($stored)) return pcore_decrypt($stored);
  return pcore_secret_key();
}

add_action('template_redirect', function () {
  if (intval(get_query_var('pcore_launch')) !== 1) return;
  if (!is_user_logged_in()) {
    auth_redirect();
    exit;
  }
  $module_id = isset($_GET['module']) ? intval($_GET['module']) : 0;
  if ($module_id <= 0 || get_post_type($module_id) !== 'ai_cme_module') {
    wp_die('Invalid module.', 'AI-CME', 400);
  }
  $user_id = get_current_user_id();
  if (!pcore_user_can_launch_module($user_id, $module_id)) {
    $product_id = (int) get_post_meta($module_id, 'linked_product_id', true);
    if ($product_id) {
      wp_safe_redirect(get_permalink($product_id)); exit;
    }
    wp_die('You do not have access to this module.', 'AI-CME', 403);
  }
  $target = pcore_ai_target_url();
  if (!$target) {
    wp_die('AI target URL not configured.', 'AI-CME', 500);
  }
  $now = time();
  $payload = [
    'iss'        => site_url(),
    'sub'        => (string) $user_id,
    'module_id'  => (int) $module_id,
    'session_id' => wp_generate_uuid4(),
    'iat'        => $now,
    'exp'        => $now + 10 * 60,
    'email'      => wp_get_current_user()->user_email,
  ];
  $jwt = pcore_jwt_sign($payload, pcore_ai_shared_secret());
  $redirect = add_query_arg('token', rawurlencode($jwt), $target);
  wp_safe_redirect($redirect);
  exit;
});

/**
 * ---------------------------------------------------------
 * AI-CME CTA SHORTCODE
 * ---------------------------------------------------------
 */
function pcore_get_linked_product_id($module_id){
  return (int) get_post_meta($module_id, 'linked_product_id', true);
}

function pcore_get_subscription_product_id(){
  return (int) get_option('pcore_ai_cme_subscription_product_id', 0);
}

add_shortcode('ai_cme_cta', function($atts){
  if (get_post_type() !== 'ai_cme_module') return '';
  $module_id = get_the_ID();
  $product_id = pcore_get_linked_product_id($module_id);
  $sub_id     = pcore_get_subscription_product_id();
  $btn = function($label, $url, $outline=false){
    $cls = 'wp-block-button__link wp-element-button'.($outline?' is-style-outline':'');
    return '<a class="'.$cls.'" href="'.esc_url($url).'">'.esc_html($label).'</a>';
  };
  $html = '<div class="ai-cme-cta" style="display:flex;gap:.75rem;flex-wrap:wrap">';
  if (is_user_logged_in() && pcore_user_can_launch_module(get_current_user_id(), $module_id)) {
    $html .= $btn('Launch', pcore_get_launch_url($module_id));
  } else {
    if ($product_id) {
      $buy_url = wc_get_cart_url().'?add-to-cart='.$product_id;
      $html .= $btn('Buy Module', $buy_url);
    }
    if ($sub_id) {
      $html .= $btn('Subscribe', get_permalink($sub_id), true);
    }
    if (!is_user_logged_in()) {
      $html .= $btn('Log in to launch', wp_login_url(get_permalink($module_id)), true);
    }
  }
  $html .= '</div>';
  return $html;
});

/**
 * ---------------------------------------------------------
 * DATABASE SCHEMA
 * ---------------------------------------------------------
 */
register_activation_hook(__FILE__, 'pcore_install_schema');
register_activation_hook(__FILE__, function () {
    if (!get_page_by_path('college/request-class')) {
        wp_insert_post([
            'post_title'   => 'Request a College Class',
            'post_name'    => 'request-class',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => wp_insert_post([
                'post_title'  => 'College',
                'post_name'   => 'college',
                'post_status' => 'publish',
                'post_type'   => 'page'
            ]),
            'post_content' => '[platform_college_request_class]'
        ]);
    }
    if (!get_page_by_path('college/my-classes')) {
        $parent = get_page_by_path('college');
        $parent_id = $parent ? $parent->ID : 0;
        wp_insert_post([
            'post_title'   => 'My Classes',
            'post_name'    => 'my-classes',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => $parent_id,
            'post_content' => '[platform_college_my_classes]'
        ]);
    }
    if (!get_page_by_path('class')) {
        wp_insert_post([
            'post_title'   => 'Class',
            'post_name'    => 'class',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[platform_class_recap]'
        ]);
    }
    flush_rewrite_rules();
});

function pcore_install_schema() {
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  $tables = [];
  $tables[] = "CREATE TABLE {$wpdb->prefix}platform_calendar_map (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    amelia_booking_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'student',
    gcal_event_id VARCHAR(128) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_booking (amelia_booking_id),
    KEY idx_user (user_id)
  ) $charset_collate;";
  
  $tables[] = "CREATE TABLE {$wpdb->prefix}webinar_materials (
    id            bigint(20)   NOT NULL AUTO_INCREMENT,
    event_id      bigint(20)   NOT NULL,
    title         varchar(255) NOT NULL,
    file_url      varchar(500) NOT NULL,
    file_type     varchar(50)  NOT NULL DEFAULT 'pdf',
    material_type varchar(100) NOT NULL DEFAULT 'Handouts / PDF Notes',
    description   text,
    uploaded_at   datetime     NOT NULL,
    uploaded_by   bigint(20)   NOT NULL,
    PRIMARY KEY (id),
    KEY idx_event   (event_id),
    KEY idx_uploader (uploaded_by),
    KEY idx_date    (uploaded_at)
) $charset_collate;";

  $tables[] = "CREATE TABLE {$wpdb->prefix}platform_payouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_item_id BIGINT UNSIGNED NULL,
    amelia_booking_id BIGINT UNSIGNED NULL,
    expert_user_id BIGINT UNSIGNED NOT NULL,
    amount_gross DECIMAL(10,2) NOT NULL DEFAULT 0,
    fee_platform DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_net DECIMAL(10,2) NOT NULL DEFAULT 0,
    month_key CHAR(7) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expert_month (expert_user_id, month_key)
  ) $charset_collate;";
  $tables[] = "CREATE TABLE {$wpdb->prefix}platform_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    college_user_id BIGINT UNSIGNED NOT NULL,
    topic TEXT NULL,
    cohort_size INT UNSIGNED NULL,
    preferred_slots LONGTEXT NULL,
    shortlisted_experts LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_college (college_user_id),
    KEY idx_status (status)
  ) $charset_collate;";
  $tables[] = "CREATE TABLE {$wpdb->prefix}platform_request_responses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    expert_user_id BIGINT UNSIGNED NOT NULL,
    response VARCHAR(20) NOT NULL,
    counter_slots LONGTEXT NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_request (request_id),
    KEY idx_expert (expert_user_id)
  ) $charset_collate;";
  $tables[] = "CREATE TABLE {$wpdb->prefix}platform_contracts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    expert_user_id BIGINT UNSIGNED NOT NULL,
    college_user_id BIGINT UNSIGNED NOT NULL,
    contract_pdf_id BIGINT UNSIGNED NULL,
    template_version VARCHAR(20) NULL,
    accepted_at DATETIME NULL,
    ip VARCHAR(45) NULL,
    PRIMARY KEY (id),
    KEY idx_request (request_id)
  ) $charset_collate;";
  $tables[] = "CREATE TABLE {$wpdb->prefix}platform_invitees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(191) NOT NULL,
    name VARCHAR(191) NULL,
    invited_via VARCHAR(20) NOT NULL DEFAULT 'calendar',
    token_hash VARCHAR(64) NULL,
    mailed_at DATETIME NULL,
    opened_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_booking (booking_id),
    KEY idx_email (email)
  ) $charset_collate;";
  $tables[] = "CREATE TABLE {$wpdb->prefix}ai_cme_entitlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(20) NOT NULL,
    purchased_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_module (user_id, module_id),
    KEY idx_status (status)
  ) $charset_collate;";
  $tables[] = "CREATE TABLE {$wpdb->prefix}ai_cme_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    external_session_id VARCHAR(191) NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    score DECIMAL(6,2) NULL,
    summary_json LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY idx_user_module (user_id, module_id)
  ) $charset_collate;";
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  foreach ($tables as $sql) dbDelta($sql);
}

/**
 * ---------------------------------------------------------
 * MIGRATE wp_webinar_materials — add missing columns
 * ---------------------------------------------------------
 */
function pcore_migrate_webinar_materials_v2() {
    if ( get_option( 'pcore_webinar_mat_v2_migrated' ) ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'webinar_materials';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) return;

    $columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table`" );

    if ( ! in_array( 'material_type', $columns, true ) ) {
        $wpdb->query(
            "ALTER TABLE `$table`
             ADD COLUMN `material_type` varchar(100) NOT NULL DEFAULT 'Handouts / PDF Notes'
             AFTER `file_type`"
        );
    }

    if ( ! in_array( 'description', $columns, true ) ) {
        $wpdb->query(
            "ALTER TABLE `$table`
             ADD COLUMN `description` text
             AFTER `material_type`"
        );
    }

    update_option( 'pcore_webinar_mat_v2_migrated', 1 );
}
add_action( 'init', 'pcore_migrate_webinar_materials_v2' );

/**
 * ---------------------------------------------------------
 * GRANT ENTITLEMENTS ON ORDER COMPLETED
 * ---------------------------------------------------------
 */
function pcore_get_module_id_by_product($product_id){
  $q = new WP_Query([
    'post_type'      => 'ai_cme_module',
    'post_status'    => 'publish',
    'meta_key'       => 'linked_product_id',
    'meta_value'     => (int) $product_id,
    'fields'         => 'ids',
    'posts_per_page' => 1,
    'no_found_rows'  => true,
  ]);
  return $q->have_posts() ? (int) $q->posts[0] : 0;
}

function pcore_grant_entitlement($user_id, $module_id, $source='purchase'){
  global $wpdb;
  $tbl = $wpdb->prefix.'ai_cme_entitlements';
  $access_days = (int) get_post_meta($module_id, 'access_window_days', true);
  $expires_at  = $access_days ? gmdate('Y-m-d H:i:s', time() + (DAY_IN_SECONDS * $access_days)) : null;
  $now         = current_time('mysql');
  $existing_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM $tbl WHERE user_id=%d AND module_id=%d",
    $user_id, $module_id
  ));
  if ($existing_id) {
    $wpdb->update($tbl, [
      'purchased_at' => $now,
      'expires_at'   => $expires_at,
      'status'       => 'active',
      'source'       => $source
    ], ['id' => $existing_id], ['%s','%s','%s','%s'], ['%d']);
  } else {
    $wpdb->insert($tbl, [
      'user_id'      => $user_id,
      'module_id'    => $module_id,
      'source'       => $source,
      'purchased_at' => $now,
      'expires_at'   => $expires_at,
      'status'       => 'active',
    ], ['%d','%d','%s','%s','%s','%s']);
  }
}

add_action('woocommerce_order_status_completed', function($order_id){
  $order = wc_get_order($order_id);
  if (!$order) return;
  $user_id = $order->get_user_id();
  if (!$user_id) return;
  foreach ($order->get_items('line_item') as $item) {
    $product_id = $item->get_product_id();
    $module_id  = pcore_get_module_id_by_product($product_id);
    if ($module_id) {
      pcore_grant_entitlement($user_id, $module_id, 'purchase');
    }
  }
});

/**
 * ---------------------------------------------------------
 * LOGIN / LOGOUT REDIRECTS
 * ---------------------------------------------------------
 */
function platform_core_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('administrator', $user->roles)) return admin_url();
        if (in_array('expert', $user->roles))        return home_url('/expert-dashboard');
        if (in_array('college_admin', $user->roles)) return home_url('/platform-dashboard');
        if (in_array('tutor-instructor', $user->roles)) return home_url('/publish');
        if (in_array('student', $user->roles))       return home_url('/student-dashboard');
        return home_url('/role-selector');
    }
    return $redirect_to;
}
add_filter('login_redirect', 'platform_core_login_redirect', 10, 3);

function platform_core_woocommerce_login_redirect($redirect, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('administrator', $user->roles)) return admin_url();
        if (in_array('expert', $user->roles))        return home_url('/expert-dashboard');
        if (in_array('college_admin', $user->roles)) return home_url('/platform-dashboard');
        if (in_array('tutor-instructor', $user->roles)) return home_url('/publish');
        if (in_array('student', $user->roles))       return home_url('/student-dashboard');
        return home_url('/role-selector');
    }
    return $redirect;
}
add_filter('woocommerce_login_redirect', 'platform_core_woocommerce_login_redirect', 10, 2);

function platform_core_logout_redirect() {
    wp_redirect(home_url('/login'));
    exit();
}
add_action('wp_logout', 'platform_core_logout_redirect');

/**
 * ---------------------------------------------------------
 * AI-CME SETTINGS (run once)
 * ---------------------------------------------------------
 */
function pcore_configure_ai_settings_once() {
  if (!current_user_can('manage_options')) return;
  update_option('pcore_ai_cme_target_url', 'https://staging-68a5-inditechsites.wpcomstaging.com/launch', false);
  update_option('pcore_ai_cme_jwt_secret', pcore_encrypt('uX9e3L2v5T1rP0qW8kY7dZ4sB9hN6jA2cF5mE0tV3gR8pQ1xC7wJ4lK6oH9iU2zS8yD0bM3fG5aT7rV1qN4pL6cX9eZ2nW8jR0uY3dK5sH7mF9gP1aC4vB6tJ8oQ2lU0xS3yE5wD7nZ9rM2fN4kG6hA8pV1jT3cX5mY7qL9bF0uR2zP4sW6oD8vK1nE3aH5tJ7iC9lQ0gB2xS4mV6yN8rF1uZ3pW5dK7oT9cA2qL4hE6vM8jG0bN2sY4iU6tV8cQ1wL3zK5fR7aJ9mE0xS2oP4dT6vH8yB1nF3lG5cW7qZ9rA0uK2pM4jV6iD8oC1fL3tS5wY7hE9gQ0mR2xB4nU6aZ8vJ1kP3dT5rF7sW9lH0yC2oG4qV6iM8uN1jD3bA5pX7eK9tZ0'), false);
  wp_die('AI-CME settings configured successfully! Now comment out the add_action line.');
}
// add_action('init', 'pcore_configure_ai_settings_once');

/**
 * ---------------------------------------------------------
 * CREATE EXPERT DASHBOARD PAGE ON ACTIVATION
 * ---------------------------------------------------------
 */
register_activation_hook(__FILE__, 'platform_core_create_expert_dashboard_page');
function platform_core_create_expert_dashboard_page() {
    if (!get_page_by_path('expert-panel')) {
        wp_insert_post([
            'post_title'   => 'Expert Dashboard',
            'post_name'    => 'expert-panel',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[expert_dashboard]'
        ]);
        flush_rewrite_rules();
    }
}

/**
 * ---------------------------------------------------------
 * AMELIA HELPER FUNCTIONS
 * ---------------------------------------------------------
 */
function platform_core_get_employee_id_by_email($email) {
    if (!$email) {
        error_log('platform_core_get_employee_id_by_email: No email provided');
        return 0;
    }
    $email = trim($email);
    $wp_user = get_user_by('email', $email);
    if (!$wp_user) {
        error_log("No WordPress user found for email: {$email}");
        return 0;
    }
    $employee_id = (int)get_user_meta($wp_user->ID, 'amelia_employee_id', true);
    if ($employee_id > 0) {
        error_log("Found Amelia employee ID {$employee_id} for WP user #{$wp_user->ID} ({$email})");
        return $employee_id;
    }
    error_log("WordPress user #{$wp_user->ID} ({$email}) has no amelia_employee_id in user meta");
    return 0;
}

function platform_core_get_tutorial_stats($employee_id) {
    global $wpdb;
    $stats = ['total' => 0, 'active' => 0];
    if (!$employee_id) return $stats;
    $appointments_table = $wpdb->prefix . 'amelia_appointments';
    $services_table = $wpdb->prefix . 'amelia_services';
    $now = current_time('mysql');
    $query = $wpdb->prepare("
        SELECT a.id, a.bookingStart, a.bookingEnd, a.status, s.name as service_name
        FROM {$appointments_table} a
        INNER JOIN {$services_table} s ON a.serviceId = s.id
        WHERE a.providerId = %d
        AND s.name NOT LIKE %s
        AND a.status IN ('approved', 'pending')
    ", $employee_id, '%Remote College Class%');
    $appointments = $wpdb->get_results($query);
    if (!$appointments) return $stats;
    foreach ($appointments as $appt) {
        $stats['total']++;
        if ($appt->bookingStart > $now) $stats['active']++;
    }
    return $stats;
}

function platform_core_get_webinar_stats($employee_id) {
    global $wpdb;
    $stats = ['total' => 0, 'next_date' => 'No upcoming'];
    if (!$employee_id) return $stats;
    $events_table = $wpdb->prefix . 'amelia_events';
    $events_providers_table = $wpdb->prefix . 'amelia_events_to_providers';
    $events_periods_table = $wpdb->prefix . 'amelia_events_periods';
    $now = current_time('mysql');
    $query = $wpdb->prepare("
        SELECT DISTINCT e.id, e.name, e.status
        FROM {$events_table} e
        INNER JOIN {$events_providers_table} ep ON e.id = ep.eventId
        WHERE ep.userId = %d AND e.status = 'approved'
    ", $employee_id);
    $events = $wpdb->get_results($query);
    if (!$events) return $stats;
    $stats['total'] = count($events);
    $event_ids = array_map(function($e) { return $e->id; }, $events);
    $event_ids_str = implode(',', array_map('intval', $event_ids));
    if ($event_ids_str) {
        $next_period = $wpdb->get_row("
            SELECT periodStart FROM {$events_periods_table}
            WHERE eventId IN ({$event_ids_str}) AND periodStart > '{$now}'
            ORDER BY periodStart ASC LIMIT 1
        ");
        if ($next_period && $next_period->periodStart) {
            $stats['next_date'] = date_i18n('F j, g:i A', strtotime($next_period->periodStart));
        }
    }
    return $stats;
}

function platform_core_get_medical_class_stats($employee_id) {
    global $wpdb;
    $stats = ['total' => 0, 'upcoming' => 0];
    if (!$employee_id) return $stats;
    $appointments_table = $wpdb->prefix . 'amelia_appointments';
    $services_table = $wpdb->prefix . 'amelia_services';
    $now = current_time('mysql');
    $query = $wpdb->prepare("
        SELECT a.id, a.bookingStart, a.bookingEnd, a.status, s.name as service_name
        FROM {$appointments_table} a
        INNER JOIN {$services_table} s ON a.serviceId = s.id
        WHERE a.providerId = %d AND s.name LIKE %s AND a.status IN ('approved', 'pending')
    ", $employee_id, '%Remote College Class%');
    $appointments = $wpdb->get_results($query);
    if (!$appointments) return $stats;
    foreach ($appointments as $appt) {
        $stats['total']++;
        if ($appt->bookingStart > $now) $stats['upcoming']++;
    }
    return $stats;
}

if (!function_exists('platform_core_amelia_api_headers')) {
    function platform_core_amelia_api_headers() {
        return [
            'Content-Type' => 'application/json',
            'Amelia'       => 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm'
        ];
    }
}

/**
 * ---------------------------------------------------------
 * FIND EDUCATOR FLOW
 * ---------------------------------------------------------
 */
class PlatformCore_FindEducator_Flow {

    private $tbl_shortlists;
    private $max_retries = 5;
    private $retry_gap = 5;
    private $api_url = "https://staging-68a5-inditechsites.wpcomstaging.com/amelia/wp-admin/admin-ajax.php";
    private $service_id = 6;
    private $debug_info = [];

    public function __construct() {
        global $wpdb;
        $this->tbl_shortlists = $wpdb->prefix . 'platform_shortlists';
        add_action('init', [$this, 'create_tables']);
        add_action('wp_ajax_pc_toggle_shortlist', [$this, 'ajax_toggle_shortlist']);
        add_shortcode('platform_find_educator', [$this, 'render_page']);
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->tbl_shortlists} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            college_user_id bigint(20) NOT NULL,
            expert_user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY map_idx (college_user_id, expert_user_id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function amelia_direct_call($endpoint) {
        $url = $this->api_url . '?action=wpamelia_api&call=' . $endpoint;
        $args = [
            'headers' => platform_core_amelia_api_headers(),
            'timeout' => 45,
            'sslverify' => false,
            'httpversion' => '1.1'
        ];
        $this->debug_info[] = ['type' => 'api_request', 'url' => $url, 'endpoint' => $endpoint, 'timestamp' => current_time('mysql')];
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            $this->debug_info[] = ['type' => 'error', 'message' => 'API Error: ' . $response->get_error_message()];
            return null;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            $this->debug_info[] = ['type' => 'error', 'message' => 'Empty response body', 'http_code' => $code];
            return null;
        }
        $decoded = json_decode($body, true);
        $this->debug_info[] = ['type' => 'api_response', 'endpoint' => $endpoint, 'http_code' => $code, 'body_length' => strlen($body), 'has_data' => !empty($decoded['data']), 'timestamp' => current_time('mysql')];
        return $decoded;
    }

    private function get_all_providers() {
        $this->debug_info[] = ['type' => 'step', 'message' => 'Fetching all providers in one call'];
        $providers_response = $this->amelia_direct_call("/api/v1/users/providers&serviceId=6&limit=100");
        if (empty($providers_response['data']['users'])) {
            $this->debug_info[] = ['type' => 'error', 'message' => 'Failed to fetch providers data'];
            return [];
        }
        $providers = $providers_response['data']['users'];
        $this->debug_info[] = ['type' => 'providers_fetched', 'total_count' => count($providers)];
        $provider_map = [];
        foreach ($providers as $provider) {
            $email = strtolower(trim($provider['email'] ?? ''));
            if (!empty($email)) {
                $provider_map[$email] = [
                    'provider_id' => $provider['id'],
                    'email' => $email,
                    'first_name' => $provider['firstName'] ?? '',
                    'last_name' => $provider['lastName'] ?? '',
                    'activity' => $provider['activity'] ?? 'away',
                ];
            }
        }
        $this->debug_info[] = ['type' => 'provider_map_created', 'total_entries' => count($provider_map)];
        return $provider_map;
    }

    public function ajax_toggle_shortlist() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['msg' => 'Login required'], 401);
        }
        $user = wp_get_current_user();
        $is_allowed = in_array('college_admin', (array) $user->roles, true) || current_user_can('manage_options');
        if (!$is_allowed) {
            wp_send_json_error(['msg' => 'Forbidden'], 403);
        }
        check_ajax_referer('pc_shortlist_nonce', 'nonce');
        $expert_id  = absint($_POST['expert_id'] ?? 0);
        $college_id = get_current_user_id();
        if (!$expert_id) {
            wp_send_json_error(['msg' => 'Invalid expert id'], 400);
        }
        $expert = get_userdata($expert_id);
        if (!$expert || !in_array('expert', (array) $expert->roles, true)) {
            wp_send_json_error(['msg' => 'Target user is not an educator'], 400);
        }
        global $wpdb;
        $exists_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tbl_shortlists} WHERE college_user_id = %d AND expert_user_id = %d",
                $college_id, $expert_id
            )
        );
        if ($exists_id) {
            $wpdb->delete($this->tbl_shortlists, ['id' => $exists_id], ['%d']);
            $still_shortlisted_anywhere = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(1) FROM {$this->tbl_shortlists} WHERE expert_user_id = %d", $expert_id)
            );
            update_user_meta($expert_id, 'pc_is_shortlisted', $still_shortlisted_anywhere > 0 ? 1 : 0);
            wp_send_json_success(['status' => 'removed']);
        } else {
            $wpdb->insert(
                $this->tbl_shortlists,
                ['college_user_id' => $college_id, 'expert_user_id' => $expert_id],
                ['%d', '%d']
            );
            update_user_meta($expert_id, 'pc_is_shortlisted', 1);
            wp_send_json_success(['status' => 'saved']);
        }
    }

    public function render_page() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/college-dashboard/'));
            exit;
        }
        $this->debug_info[] = ['type' => 'page_render_start', 'timestamp' => current_time('mysql')];
        $current_user_id = get_current_user_id();
        $retry_key   = 'pc_find_educator_retry_' . ($current_user_id ?: 'guest');
        $retry_count = (int) get_transient($retry_key);

        $provider_map = $this->get_all_providers();
        if (empty($provider_map)) {
            $retry_count++;
            set_transient($retry_key, $retry_count, 10 * MINUTE_IN_SECONDS);
            if ($retry_count < $this->max_retries) {
                echo '<script>setTimeout(function(){window.location.reload();},' . ($this->retry_gap * 1000) . ');</script>';
                return;
            }
            delete_transient($retry_key);
        } else {
            delete_transient($retry_key);
        }

        global $wpdb;
        $all_wp_users = $wpdb->get_results("
            SELECT u.ID, u.user_email, u.display_name, m.meta_value as roles
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} m ON u.ID = m.user_id AND m.meta_key = '{$wpdb->prefix}capabilities'
            ORDER BY u.display_name
        ");

        $experts      = [];
        $skipped_users = [];
        foreach ($all_wp_users as $wp_user) {
            $roles = maybe_unserialize($wp_user->roles);
            if (empty($roles) || !is_array($roles) || !array_key_exists('expert', $roles)) {
                $skipped_users[] = $wp_user->ID;
                continue;
            }
            $wp_email = strtolower(trim($wp_user->user_email));
            if (isset($provider_map[$wp_email])) {
                $experts[] = ['wp_user' => $wp_user, 'provider_data' => $provider_map[$wp_email]];
            }
        }

        $saved_ids = [];
        if (is_user_logged_in()) {
            $saved_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT expert_user_id FROM {$this->tbl_shortlists} WHERE college_user_id = %d",
                $current_user_id
            ));
        }

        $nav_user = wp_get_current_user();
        $nav_fn   = get_user_meta($nav_user->ID, 'first_name', true);
        if (!empty(trim($nav_fn))) {
            $nav_display = trim($nav_fn);
        } elseif (!empty($nav_user->display_name) && strpos($nav_user->display_name, '@') === false) {
            $nav_display = $nav_user->display_name;
        } else {
            $nav_display = explode('@', $nav_user->user_email)[0];
        }
        $nav_avatar = get_avatar_url($nav_user->ID, ['size' => 36, 'default' => 'mystery']);

        $url_dashboard   = home_url('/platform-dashboard');
        $url_find        = get_permalink();
        $url_sessions    = home_url('/college-sessions');
        $url_contracts   = home_url('/contracts-sessions');
        $url_shortlisted = home_url('/shortlisted-educators');

        ob_start();
        ?>

        <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@500;600&display=swap');

        #wpadminbar{display:none!important;}
        html{margin-top:0!important;}
        header,#masthead,.site-header,.main-header,#header,
        .elementor-location-header,.ast-main-header-wrap,#site-header,
        .fusion-header-wrapper,.header-wrap,.nav-primary,
        div[data-elementor-type="header"]{display:none!important;}
        .page-template-default .site-content,.site-main,#content,#page{
            margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;
        }
        footer.site-footer,.site-footer,#colophon,#footer,
        .footer-area,.ast-footer-overlay,.footer-widgets-area,.footer-bar,
        div[data-elementor-type="footer"],.elementor-location-footer{
            display:none!important;
        }

        .pc-nav{background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid #e4e7ef;position:sticky;top:0;z-index:200;box-shadow:0 1px 0 #e4e7ef,0 2px 12px rgba(0,0,0,.04);}
        .pc-nav-inner{max-width:1400px;margin:auto;padding:0 36px;height:58px;display:flex;justify-content:space-between;align-items:center;}
        .pc-nav-logo{font-weight:800;color:#4338ca;font-size:20px;text-decoration:none;letter-spacing:-.5px;}
        .pc-nav-links{display:flex;gap:2px;}
        .pc-nav-links a{padding:7px 16px;border-radius:8px;font-size:14px;font-weight:500;color:#6b7280;text-decoration:none;transition:background .18s,color .18s;letter-spacing:-.1px;}
        .pc-nav-links a:hover{background:#eef2ff;color:#4338ca;}
        .pc-nav-links a.active{background:#eef2ff;color:#4338ca;font-weight:600;}
        .pc-nav-right{display:flex;align-items:center;gap:14px;}
        .pc-nav-right img{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;box-shadow:0 0 0 2px #eef2ff;}
        .pc-nav-username{font-weight:600;font-size:13px;color:#0f172a;}
        .pc-nav-btn{padding:7px 18px;border-radius:8px;font-size:13px;font-weight:600;background:#0f172a;color:#fff;text-decoration:none;transition:opacity .15s,transform .15s;}
        .pc-nav-btn:hover{opacity:.88;transform:translateY(-1px);}
        @media(max-width:768px){.pc-nav-links{display:none;}}

        .fe-wrap{
            --ink:#0c1222;--ink2:#4a5568;--ink3:#94a3b8;--surface:#ffffff;--bg:#f4f6fb;
            --border:#e8edf5;--accent:#4338ca;--accent-lt:#eef2ff;--green:#059669;
            --green-lt:#ecfdf5;--amber:#d97706;--navy:#0f172a;--gold:#f59e0b;
            --font:'DM Sans',sans-serif;--font-serif:'Playfair Display',serif;
            --r:14px;--shadow:0 1px 3px rgba(0,0,0,.06),0 4px 20px rgba(0,0,0,.05);
            --shadow-lg:0 8px 30px rgba(0,0,0,.10),0 2px 8px rgba(0,0,0,.06);
            font-family:var(--font);background:var(--bg);color:var(--ink);min-height:100vh;
            margin-bottom:0!important;padding-bottom:0!important;
        }
        .fe-wrap *,.fe-wrap *::before,.fe-wrap *::after{box-sizing:border-box;margin:0;padding:0;}
        .fe-page{max-width:1100px;margin:0 auto;padding:36px 28px 64px;}
        .fe-page-header{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:32px;flex-wrap:wrap;}
        .fe-page-title{font-family:var(--font-serif);font-size:30px;font-weight:600;color:var(--ink);letter-spacing:-.4px;line-height:1.2;}
        .fe-page-title span{display:block;font-family:var(--font);font-size:13px;font-weight:400;color:var(--ink3);letter-spacing:0;margin-top:5px;}
        .fe-search-row{display:flex;gap:10px;align-items:center;}
        .fe-search-box{position:relative;flex:1;min-width:240px;}
        .fe-search-box svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--ink3);pointer-events:none;width:16px;height:16px;}
        .fe-search-box input{width:100%;padding:10px 14px 10px 40px;border:1px solid var(--border);border-radius:10px;font-family:var(--font);font-size:14px;color:var(--ink);background:var(--surface);outline:none;transition:border-color .18s,box-shadow .18s;}
        .fe-search-box input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,56,202,.10);}
        .fe-search-box input::placeholder{color:var(--ink3);}
        .fe-main-search-btn{padding:10px 22px;background:var(--navy);color:#fff;border:none;border-radius:10px;font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer;transition:background .15s,transform .15s;white-space:nowrap;}
        .fe-main-search-btn:hover{background:#1e293b;transform:translateY(-1px);}
        .fe-count-chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--accent);background:var(--accent-lt);padding:4px 12px;border-radius:20px;margin-bottom:16px;}
        .fe-grid{display:flex;flex-direction:column;gap:16px;}
        .fe-edu-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--shadow);display:grid;grid-template-columns:auto 1fr auto;align-items:center;overflow:hidden;transition:box-shadow .22s,transform .22s,border-color .22s;animation:fe-fadein .35s ease both;position:relative;}
        .fe-edu-card:hover{box-shadow:var(--shadow-lg);transform:translateY(-2px);border-color:#d0d9f0;}
        @keyframes fe-fadein{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
        .fe-edu-card:nth-child(1){animation-delay:.04s;}.fe-edu-card:nth-child(2){animation-delay:.08s;}.fe-edu-card:nth-child(3){animation-delay:.12s;}.fe-edu-card:nth-child(4){animation-delay:.16s;}.fe-edu-card:nth-child(5){animation-delay:.20s;}
        .fe-card-strip{width:4px;align-self:stretch;background:var(--border);transition:background .2s;}
        .fe-edu-card.is-available .fe-card-strip{background:var(--green);}
        .fe-card-body{padding:20px 24px;display:flex;align-items:center;gap:20px;flex:1;}
        .fe-avatar-wrap{position:relative;flex-shrink:0;}
        .fe-avatar{width:68px;height:68px;border-radius:50%;object-fit:cover;border:2px solid var(--border);display:block;}
        .fe-avail-dot{position:absolute;bottom:2px;right:2px;width:13px;height:13px;border-radius:50%;border:2px solid #fff;background:var(--border);}
        .fe-edu-card.is-available .fe-avail-dot{background:var(--green);}
        .fe-info{flex:1;min-width:0;}
        .fe-name{font-size:16px;font-weight:700;color:var(--ink);letter-spacing:-.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .fe-meta{display:flex;align-items:center;gap:14px;margin-top:4px;flex-wrap:wrap;}
        .fe-meta-item{font-size:12px;color:var(--ink3);display:flex;align-items:center;gap:4px;}
        .fe-meta-item svg{width:13px;height:13px;flex-shrink:0;}
        .fe-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;}
        .fe-tag{font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;background:var(--accent-lt);color:var(--accent);letter-spacing:.1px;}
        .fe-tag-avail{background:var(--green-lt);color:var(--green);}
        .fe-tag-unavail{background:#f1f5f9;color:var(--ink3);}
        .fe-exp-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:var(--amber);background:#fffbeb;padding:3px 9px;border-radius:20px;border:1px solid #fde68a;}
        .fe-card-actions{padding:20px 24px 20px 0;display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0;}
        .fe-btn-book{display:inline-block;padding:9px 20px;background:var(--accent);color:#fff;border-radius:9px;font-family:var(--font);font-size:13px;font-weight:600;text-decoration:none;transition:background .15s,transform .15s,box-shadow .15s;white-space:nowrap;box-shadow:0 2px 8px rgba(67,56,202,.25);}
        .fe-btn-book:hover{background:#3730a3;transform:translateY(-1px);box-shadow:0 4px 14px rgba(67,56,202,.35);}
        .fe-save-btn{position:absolute;top:14px;right:14px;background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;justify-content:center;transition:transform .18s;z-index:2;}
        .fe-save-btn:hover{transform:scale(1.2);}
        .fe-save-btn svg{width:20px;height:20px;}
        .fe-save-btn svg path{fill:#ffffff;stroke:var(--gold);stroke-width:1.5;transition:fill .15s;}
        .fe-save-btn.saved svg path{fill:var(--gold);stroke:var(--gold);}
        .fe-empty{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:60px 32px;text-align:center;box-shadow:var(--shadow);}
        .fe-empty-icon{width:56px;height:56px;border-radius:50%;background:var(--accent-lt);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;}
        .fe-empty-icon svg{width:26px;height:26px;color:var(--accent);}
        .fe-empty h3{font-size:17px;font-weight:700;color:var(--ink);margin-bottom:6px;}
        .fe-empty p{font-size:13px;color:var(--ink3);max-width:340px;margin:0 auto 20px;line-height:1.6;}
        .fe-no-results{text-align:center;padding:40px;color:var(--ink3);font-size:14px;display:none;}
        .fe-site-footer{background:var(--navy);color:#64748b;padding:36px 36px 18px;margin-top:0;}
        .fe-footer-inner{max-width:1100px;margin:0 auto;}
        .fe-footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:32px;margin-bottom:28px;}
        .fe-footer-logo{font-size:18px;font-weight:800;color:#818cf8;margin-bottom:8px;letter-spacing:-.3px;}
        .fe-footer-brand p{font-size:12px;line-height:1.7;}
        .fe-site-footer h4{font-size:10px;font-weight:700;color:#e2e8f0;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;}
        .fe-footer-links{display:flex;flex-direction:column;gap:8px;}
        .fe-footer-links a{font-size:12px;color:#64748b;text-decoration:none;transition:color .15s;}
        .fe-footer-links a:hover{color:#e2e8f0;}
        .fe-footer-bottom{border-top:1px solid rgba(255,255,255,.06);padding-top:14px;font-size:11px;text-align:center;color:#334155;}
        .fe-social-icons{display:flex;gap:8px;}
        .fe-social-icons a{width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;color:#64748b;font-size:11px;text-decoration:none;transition:background .15s,color .15s;}
        .fe-social-icons a:hover{background:rgba(255,255,255,.12);color:#e2e8f0;}
        body,#page,.site,.site-content,#content,.wp-site-blocks,main.wp-block-group,.is-layout-flow{padding-bottom:0!important;margin-bottom:0!important;}
        .fe-wrap{margin-bottom:0!important;padding-bottom:0!important;}
        .fe-site-footer{margin-bottom:0!important;}
        @media(max-width:700px){
            .fe-edu-card{grid-template-columns:auto 1fr;grid-template-rows:auto auto;}
            .fe-card-strip{grid-row:1/3;}
            .fe-card-actions{grid-column:2;flex-direction:row;flex-wrap:wrap;padding:0 16px 16px;justify-content:flex-start;}
            .fe-card-body{padding:16px;}
            .fe-page-header{flex-direction:column;align-items:flex-start;}
            .fe-search-row{width:100%;}
            .fe-footer-grid{grid-template-columns:1fr 1fr;gap:20px;}
        }
        .hidden{display:none!important;}
        </style>

        <nav class="pc-nav">
            <div class="pc-nav-inner">
                <a href="<?php echo esc_url(home_url()); ?>" class="pc-nav-logo">LOGO</a>
                <div class="pc-nav-links">
                    <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
                    <a href="<?php echo esc_url($url_find); ?>" class="active">Find Educators</a>
                    <a href="<?php echo esc_url($url_sessions); ?>">Sessions</a>
                    <a href="<?php echo esc_url($url_contracts); ?>">Contracts</a>
                    <a href="<?php echo esc_url($url_shortlisted); ?>">Shortlisted</a>
                </div>
                <div class="pc-nav-right">
                    <?php if (is_user_logged_in()) : ?>
                        <img src="<?php echo esc_url($nav_avatar); ?>" alt="Profile">
                        <span class="pc-nav-username">Hi, <?php echo esc_html($nav_display); ?></span>
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pc-nav-btn">Logout</a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(wp_login_url()); ?>" class="pc-nav-btn">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <div class="fe-wrap">
            <div class="fe-page">
                <div class="fe-page-header">
                    <div>
                        <h1 class="fe-page-title">
                            Find Your Perfect Educator
                            <span>Browse and shortlist qualified medical educators</span>
                        </h1>
                    </div>
                    <div class="fe-search-row">
                        <div class="fe-search-box">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" id="search-input" placeholder="Search by name or specialization..." autocomplete="off">
                        </div>
                        <button type="button" class="fe-main-search-btn" onclick="performSearch()">Search</button>
                    </div>
                </div>

                <div class="fe-grid" id="experts-grid">
                    <?php if (empty($experts)) : ?>
                        <div class="fe-empty">
                            <div class="fe-empty-icon">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                            </div>
                            <h3>No educators found</h3>
                            <p>No matching educators are available at the moment. Please check back later.</p>
                        </div>
                    <?php else : ?>
                        <div class="fe-count-chip">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            <?php echo count($experts); ?> educator<?php echo count($experts) !== 1 ? 's' : ''; ?> available
                        </div>
                        <?php foreach ($experts as $match) :
                            $wp_user       = $match['wp_user'];
                            $provider_data = $match['provider_data'];
                            $is_saved      = in_array($wp_user->ID, $saved_ids);
                            $is_available  = ($provider_data['activity'] === 'available');
                            $avail_class   = $is_available ? 'is-available' : '';
                            $avail_label   = $is_available ? 'Available Now' : 'Unavailable';
                            $avail_tag     = $is_available ? 'fe-tag-avail' : 'fe-tag-unavail';
                            $raw_spec      = get_user_meta($wp_user->ID, '_tutor_instructor_speciality', true);
                            $specs_array   = !empty($raw_spec) ? array_map('trim', explode(',', $raw_spec)) : ['General'];
                            $display_specs = array_slice($specs_array, 0, 3);
                            $exp_years     = (int)(get_user_meta($wp_user->ID, '_tutor_instructor_experience', true) ?: 1);
                            $avatar_url    = get_avatar_url($wp_user->ID);
                        ?>
                        <div class="fe-edu-card expert-card <?php echo esc_attr($avail_class); ?>"
                             data-name="<?php echo esc_attr(strtolower($wp_user->display_name)); ?>"
                             data-specialization="<?php echo esc_attr(strtolower(implode(' ', $specs_array))); ?>">
                            <div class="fe-card-strip"></div>
                            <div class="fe-card-body">
                                <div class="fe-avatar-wrap">
                                    <img class="fe-avatar" src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($wp_user->display_name); ?>">
                                    <span class="fe-avail-dot" title="<?php echo esc_attr($avail_label); ?>"></span>
                                </div>
                                <div class="fe-info">
                                    <div class="fe-name"><?php echo esc_html($wp_user->display_name); ?></div>
                                    <div class="fe-meta">
                                        <?php if ($exp_years > 0) : ?>
                                        <span class="fe-exp-badge">
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <?php echo $exp_years; ?> yr exp
                                        </span>
                                        <?php endif; ?>
                                        <span class="fe-meta-item">
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                                            Remote
                                        </span>
                                    </div>
                                    <div class="fe-tags">
                                        <?php foreach ($display_specs as $spec_name) : ?>
                                            <span class="fe-tag"><?php echo esc_html($spec_name); ?></span>
                                        <?php endforeach; ?>
                                        <span class="fe-tag <?php echo $avail_tag; ?>"><?php echo esc_html($avail_label); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="fe-card-actions">
                                <a class="fe-btn-book" href="<?php echo esc_url(site_url('/college/educator-profile?expert_id=' . $wp_user->ID)); ?>">
                                    View Profile &amp; Book Session
                                </a>
                            </div>
                            <button class="fe-save-btn <?php echo $is_saved ? 'saved' : ''; ?>"
                                    onclick="pcToggleSave(this, <?php echo (int)$wp_user->ID; ?>)"
                                    title="<?php echo $is_saved ? 'Remove from shortlist' : 'Shortlist this educator'; ?>"
                                    aria-pressed="<?php echo $is_saved ? 'true' : 'false'; ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                </svg>
                            </button>
                        </div>
                        <?php endforeach; ?>
                        <div class="fe-no-results" id="fe-no-results">No educators match your search.</div>
                    <?php endif; ?>
                </div>
            </div>

            <footer class="fe-site-footer">
                <div class="fe-footer-inner">
                    <div class="fe-footer-grid">
                        <div class="fe-footer-brand">
                            <div class="fe-footer-logo">LOGO</div>
                            <p>Connecting educators and learners worldwide.</p>
                        </div>
                        <div>
                            <h4>Company</h4>
                            <div class="fe-footer-links">
                                <a href="#">About</a>
                                <a href="#">Careers</a>
                                <a href="#">Contact</a>
                            </div>
                        </div>
                        <div>
                            <h4>Resources</h4>
                            <div class="fe-footer-links">
                                <a href="#">Blog</a>
                                <a href="#">Help Center</a>
                                <a href="#">Terms</a>
                            </div>
                        </div>
                        <div>
                            <h4>Follow Us</h4>
                            <div class="fe-social-icons">
                                <a href="#">Tw</a>
                                <a href="#">Li</a>
                                <a href="#">Fb</a>
                            </div>
                        </div>
                    </div>
                    <div class="fe-footer-bottom">&copy; <?php echo date('Y'); ?> All rights reserved.</div>
                </div>
            </footer>
        </div>

        <script>
        (function () {
            function getInput() {
                return document.getElementById('search-input') || document.querySelector('.fe-search-box input[type="text"]');
            }
            function getCards() { return document.querySelectorAll('.expert-card'); }
            function setHidden(el, hidden) {
                if (hidden) { el.classList.add('hidden'); el.style.setProperty('display','none','important'); el.setAttribute('aria-hidden','true'); }
                else { el.classList.remove('hidden'); el.style.removeProperty('display'); el.removeAttribute('aria-hidden'); }
            }
            function filter() {
                var inp = getInput();
                var q = (inp && inp.value || '').toLowerCase().trim();
                var cards = getCards();
                var noRes = document.getElementById('fe-no-results');
                if (!cards.length) return;
                var visible = 0;
                Array.prototype.slice.call(cards).forEach(function(c) {
                    var name = (c.getAttribute('data-name') || '').toLowerCase();
                    var spec = (c.getAttribute('data-specialization') || '').toLowerCase();
                    var match = !q || (name + ' ' + spec).indexOf(q) !== -1;
                    setHidden(c, !match);
                    if (match) visible++;
                });
                if (noRes) noRes.style.display = (!visible && q) ? 'block' : 'none';
            }
            window.performSearch = filter;
            document.addEventListener('DOMContentLoaded', function () {
                var inp = getInput();
                if (!inp) return;
                inp.addEventListener('input', filter);
                inp.addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); filter(); } });
            });
            window.pcToggleSave = function(btn, expertId) {
                var wasSaved = btn.classList.contains('saved');
                btn.classList.toggle('saved');
                btn.setAttribute('aria-pressed', btn.classList.contains('saved') ? 'true' : 'false');
                btn.title = btn.classList.contains('saved') ? 'Remove from shortlist' : 'Shortlist this educator';
                var data = new FormData();
                data.append('action', 'pc_toggle_shortlist');
                data.append('expert_id', expertId);
                data.append('nonce', '<?php echo esc_js(wp_create_nonce('pc_shortlist_nonce')); ?>');
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(j) { if (!j || j.success !== true) throw new Error(j && j.data && j.data.msg ? j.data.msg : 'Failed'); })
                    .catch(function(err) {
                        if (wasSaved) { btn.classList.add('saved'); btn.setAttribute('aria-pressed', 'true'); }
                        else { btn.classList.remove('saved'); btn.setAttribute('aria-pressed', 'false'); }
                        alert(err.message || 'Unable to update shortlist. Please try again.');
                    });
            };
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
new PlatformCore_FindEducator_Flow();

/**
 * ---------------------------------------------------------
 * TRANSACTION HISTORY SHORTCODE
 * ---------------------------------------------------------
 */
function flow7_render_transaction_history() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'inc/transaction_history.php';
    return ob_get_clean();
}
add_shortcode('flow7_transactions', 'flow7_render_transaction_history');

add_shortcode('upload_material_page', 'render_upload_material_ui');
add_shortcode('webinar_calendar_view', 'render_webinar_calendar_page');

function render_webinar_calendar_page() {
    ob_start();
    $path = plugin_dir_path(__FILE__) . 'inc/webinar_calender.php';
    if (file_exists($path)) include $path;
    return ob_get_clean();
}

add_action('wp_ajax_get_webinar_calendar_grid', 'platform_ajax_get_calendar_grid');
function platform_ajax_get_calendar_grid() {
    $m = intval($_POST['m']);
    $y = intval($_POST['y']);
    if (file_exists(plugin_dir_path(__FILE__) . 'inc/webinar_calender.php')) {
        include plugin_dir_path(__FILE__) . 'inc/webinar_calender.php';
        wp_send_json_success(render_calendar_logic_only($m, $y));
    }
    wp_die();
}

/**
 * ---------------------------------------------------------
 * ADMIN DEBUG PAGE
 * ---------------------------------------------------------
 */
add_action('admin_menu', function() {
    add_submenu_page(null, 'Database Debug', 'Database Debug', 'manage_options', 'amelia-database-debug', 'amelia_database_debug_page');
});

function amelia_database_debug_page() {
    global $wpdb;
    $tables = [
        'amelia_payments'   => $wpdb->prefix . 'amelia_payments',
        'amelia_customer_bookings' => $wpdb->prefix . 'amelia_customer_bookings',
        'amelia_events_periods'    => $wpdb->prefix . 'amelia_events_periods',
        'amelia_customer_bookings_to_events_periods' => $wpdb->prefix . 'amelia_customer_bookings_to_events_periods',
        'amelia_events_to_providers' => $wpdb->prefix . 'amelia_events_to_providers',
        'amelia_events' => $wpdb->prefix . 'amelia_events',
        'amelia_users'  => $wpdb->prefix . 'amelia_users',
        'tbl_contracts' => 'tbl_requests',
    ];
    ?>
    <h2 style="color:#2c3e50;">All Database Tables</h2>
    <div style="border:2px solid #2c3e50;padding:15px;border-radius:6px;margin-bottom:40px;">
    <?php
    $all_tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($wpdb->prefix) . '%'));
    if ($all_tables) {
        echo '<ul style="columns:3;-webkit-columns:3;-moz-columns:3;">';
        foreach ($all_tables as $tbl) echo '<li><strong>' . esc_html($tbl) . '</strong></li>';
        echo '</ul>';
        echo '<p><strong>Total tables:</strong> ' . count($all_tables) . '</p>';
    } else {
        echo '<p style="color:red;">No tables found</p>';
    }
    ?>
    </div>
    <div style="background:#fff;padding:20px;margin:20px;font-family:monospace;font-size:12px;">
        <h1 style="color:#e74c3c;">Amelia Database Structure &amp; Data</h1>
        <?php foreach ($tables as $name => $table) :
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if ($exists != $table) {
                echo "<h2 style='color:red;'>Table does not exist: $name</h2><hr>";
                continue;
            }
        ?>
        <div style="margin-bottom:40px;border:2px solid #3498db;padding:20px;border-radius:8px;">
            <h2 style="color:#3498db;margin-top:0;"><?php echo esc_html($name); ?></h2>
            <p><strong>Full table name:</strong> <?php echo esc_html($table); ?></p>
            <h3 style="color:#2ecc71;">Table Structure</h3>
            <?php $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
            if ($columns) {
                echo '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">';
                echo '<thead><tr style="background:#34495e;color:#fff;"><th style="padding:10px;border:1px solid #ddd;">Field</th><th style="padding:10px;border:1px solid #ddd;">Type</th><th style="padding:10px;border:1px solid #ddd;">Null</th><th style="padding:10px;border:1px solid #ddd;">Key</th><th style="padding:10px;border:1px solid #ddd;">Default</th></tr></thead><tbody>';
                foreach ($columns as $col) {
                    echo '<tr>';
                    echo '<td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html($col->Field) . '</strong></td>';
                    echo '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($col->Type) . '</td>';
                    echo '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($col->Null) . '</td>';
                    echo '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($col->Key) . '</td>';
                    echo '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($col->Default) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } ?>
            <h3 style="color:#e67e22;">Sample Data</h3>
            <?php $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
            echo '<p><strong>Total rows:</strong> ' . number_format($count) . '</p>';
            if ($rows) {
                echo '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:11px;">';
                echo '<thead><tr style="background:#95a5a6;color:#fff;">';
                foreach (array_keys($rows[0]) as $header) echo '<th style="padding:8px;border:1px solid #ddd;white-space:nowrap;">' . esc_html($header) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($row as $value) {
                        $display = $value === null ? '<em style="color:#999;">NULL</em>' : esc_html($value);
                        echo '<td style="padding:6px;border:1px solid #ddd;">' . $display . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            } else {
                echo '<p style="color:#e74c3c;">No data in this table</p>';
            }
            ?>
        </div>
        <hr style="margin:40px 0;border:2px solid #ecf0f1;">
        <?php endforeach; ?>
        <div style="border:2px solid #9b59b6;padding:20px;border-radius:8px;margin-top:40px;">
            <h2 style="color:#9b59b6;">Current Provider ID Test</h2>
            <?php
            if (is_user_logged_in()) {
                $test_provider_id = function_exists('platform_core_get_current_provider_id') ? platform_core_get_current_provider_id() : 0;
                echo '<p><strong>Logged in user:</strong> ' . wp_get_current_user()->user_email . '</p>';
                echo '<p><strong>Provider ID returned:</strong> ' . ($test_provider_id ?: '<span style="color:red;">0 (NOT FOUND)</span>') . '</p>';
                if ($test_provider_id) {
                    $events_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}amelia_events WHERE organizerId = %d", $test_provider_id));
                    echo '<p><strong>Events organized by this provider:</strong> ' . $events_count . '</p>';
                }
            } else {
                echo '<p style="color:red;">No user logged in</p>';
            }
            ?>
        </div>
    </div>
    <?php
}