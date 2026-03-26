<?php
/**
 * Flow 10 — AI‑CME credit bridge (NEW)
 *
 * Drop this file into: wp-content/plugins/platform-core/inc/flow10-ai-cme-credits.php
 * Then add the following line near the top of platform-core.php (after other require_once):
 *   require_once plugin_dir_path(__FILE__) . 'inc/flow10-ai-cme-credits.php';
 *
 * What this file provides
 * -----------------------
 * 1) Credit ledger + balance helpers
 * 2) WooCommerce "credit pack" product field and order-completed crediting
 * 3) Launch endpoint & shortcode that POSTs user_id + current credits to the external AI-CME API
 *    and redirects the learner to the AI platform
 * 4) RETURN API (REST + browser GET) used by the AI platform to POST back the
 *    final credits available; we replace the balance and log the session; then redirect to home
 * 5) Admin settings page to configure external endpoints + shared secret
 */

add_filter('allowed_redirect_hosts', function($hosts){
  $hosts[] = '20.163.3.202';
  return $hosts;
});


if (!defined('ABSPATH')) exit;

global $wpdb;

//
// ---------------------------------------------------------------------
// 0) INSTALL / MIGRATION — tables + options
// ---------------------------------------------------------------------
function pcore_ai10_install() {
  global $wpdb;
  $charset = $wpdb->get_charset_collate();

  // 10.a Credit ledger
  $sql1 = "CREATE TABLE {$wpdb->prefix}ai_cme_credit_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    delta INT NOT NULL,
    old_balance INT NOT NULL,
    new_balance INT NOT NULL,
    reason VARCHAR(64) NOT NULL,
    ref VARCHAR(191) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_reason (reason)
  ) $charset;";

  // 10.b Sessions (extend if table already exists)
  $sql2 = "CREATE TABLE {$wpdb->prefix}ai_cme_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NULL,
    external_session_id VARCHAR(191) NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    credits_before INT UNSIGNED NULL,
    credits_after INT UNSIGNED NULL,
    score DECIMAL(6,2) NULL,
    summary_json LONGTEXT NULL,
    payload_out LONGTEXT NULL,
    payload_in LONGTEXT NULL,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_external (external_session_id)
  ) $charset;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql1);
  dbDelta($sql2);

  // Ensure options exist
  add_option('pcore_ai10_api_start_url', '');
  add_option('pcore_ai10_return_redirect_url', home_url('/'));
  add_option('pcore_ai10_frontend_url', ''); // or 'http://20.163.3.202/'

  // Store shared secret encrypted with platform-core utilities if present
  if (!get_option('pcore_ai10_shared_secret')) {
    if (function_exists('pcore_encrypt')) {
      update_option('pcore_ai10_shared_secret', pcore_encrypt(wp_generate_password(24, false)), false);
    } else {
      update_option('pcore_ai10_shared_secret', wp_generate_password(24, false), false);
    }
  }
}
add_action('plugins_loaded', 'pcore_ai10_install');

//
// ---------------------------------------------------------------------
// 1) CREDIT BALANCE HELPERS
// ---------------------------------------------------------------------
function pcore_ai10_get_balance($user_id) {
  return (int) get_user_meta($user_id, 'ai_cme_credits', true);
}
function pcore_ai10_set_balance($user_id, $new, $reason = 'adjust', $ref = null) {
  global $wpdb;
  $old = pcore_ai10_get_balance($user_id);
  update_user_meta($user_id, 'ai_cme_credits', (int)$new);
  $wpdb->insert($wpdb->prefix . 'ai_cme_credit_ledger', [
    'user_id'     => $user_id,
    'delta'       => (int)$new - (int)$old,
    'old_balance' => (int)$old,
    'new_balance' => (int)$new,
    'reason'      => $reason,
    'ref'         => $ref,
    'created_at'  => current_time('mysql', 1),
  ], ['%d','%d','%d','%d','%s','%s','%s']);
}
function pcore_ai10_add_credits($user_id, $delta, $reason='purchase', $ref=null){
  $cur = pcore_ai10_get_balance($user_id);
  pcore_ai10_set_balance($user_id, (int)$cur + (int)$delta, $reason, $ref);
}

//
// ---------------------------------------------------------------------
// 2) WOOCOMMERCE — Credit pack field + purchase crediting
// ---------------------------------------------------------------------
// if (class_exists('WooCommerce')) {
//   // Product field
//   add_action('woocommerce_product_options_general_product_data', function () {
//     echo '<div class="options_group">';
//     woocommerce_wp_text_input([
//       'id'          => '_pcore_ai10_credits',
//       'label'       => __('AI‑CME Credits in this product', 'platform-core'),
//       'description' => __('How many credits should the buyer receive when this product is purchased?', 'platform-core'),
//       'type'        => 'number',
//       'custom_attributes' => ['min' => '0', 'step' => '1'],
//     ]);
//     echo '</div>';
//   });
//   add_action('woocommerce_process_product_meta', function ($post_id) {
//     if (isset($_POST['_pcore_ai10_credits'])) {
//       update_post_meta($post_id, '_pcore_ai10_credits', (int) $_POST['_pcore_ai10_credits']);
//     }
//   });

//   // Order → credit the account on completion
//   add_action('woocommerce_order_status_completed', function ($order_id) {
//     $order = wc_get_order($order_id);
//     if (!$order) return;
//     $user_id = $order->get_user_id();
//     if (!$user_id) return; // we require a user account

//     foreach ($order->get_items('line_item') as $item) {
//       $pid = (int)$item->get_product_id();
//       $credits = (int)get_post_meta($pid, '_pcore_ai10_credits', true);
//       if ($credits > 0) {
//         pcore_ai10_add_credits($user_id, $credits, 'woo_purchase', 'order#'.$order_id.'/product#'.$pid);
//       }
//     }
//   });
// }

// --- WooCommerce integration (safe timing) ---
add_action('plugins_loaded', function () {
  if ( ! class_exists('WooCommerce') ) return; // Woo not active

  // Admin product field (classic editor)
  add_action('woocommerce_product_options_general_product_data', 'pcore_ai10_admin_credit_field');
  add_action('woocommerce_process_product_meta', 'pcore_ai10_admin_save_credit_field');

  // Credit the user when an order is marked Completed
  add_action('woocommerce_order_status_completed', 'pcore_ai10_grant_credits_on_completed', 10, 1);
}, 20);

function pcore_ai10_admin_credit_field() {
  if ( ! function_exists('woocommerce_wp_text_input') ) return; // ensures we're in classic editor admin
  echo '<div class="options_group">';
  woocommerce_wp_text_input([
    'id'                => '_pcore_ai10_credits',
    'label'             => __('AI‑CME Credits in this product', 'platform-core'),
    'description'       => __('How many credits should the buyer receive when this product is purchased (on order completion)?', 'platform-core'),
    'type'              => 'number',
    'custom_attributes' => ['min' => '0', 'step' => '1'],
  ]);
  echo '</div>';
}

function pcore_ai10_admin_save_credit_field($post_id) {
  if (isset($_POST['_pcore_ai10_credits'])) {
    update_post_meta($post_id, '_pcore_ai10_credits', (int) $_POST['_pcore_ai10_credits']);
  }
}

function pcore_ai10_grant_credits_on_completed($order_id) {
  $order = wc_get_order($order_id);
  if ( ! $order ) return;
  $user_id = $order->get_user_id();
  if ( ! $user_id ) return;

  // (Optional) idempotency: prevent double credit if status is re‑set to completed
  if ( $order->get_meta('_pcore_ai10_credited') ) return;

  foreach ($order->get_items('line_item') as $item) {
    $pid = (int) $item->get_product_id();
    $credits = (int) get_post_meta($pid, '_pcore_ai10_credits', true);
    if ($credits > 0) {
      pcore_ai10_add_credits($user_id, $credits, 'woo_purchase', 'order#'.$order_id.'/product#'.$pid);
    }
  }
  $order->update_meta_data('_pcore_ai10_credited', 'yes');
  $order->save();
}


//
// ---------------------------------------------------------------------
// 3) SETTINGS — External API + secret
// ---------------------------------------------------------------------
add_action('admin_menu', function () {
  add_options_page('AI‑CME Bridge', 'AI‑CME Bridge', 'manage_options', 'pcore-ai10', 'pcore_ai10_settings_page');
});

function pcore_ai10_settings_page() {
  if (!current_user_can('manage_options')) return;

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('pcore_ai10_settings')) {
    update_option('pcore_ai10_api_start_url', esc_url_raw($_POST['api_start_url'] ?? ''));
    update_option('pcore_ai10_return_redirect_url', esc_url_raw($_POST['return_redirect_url'] ?? home_url('/')));
    update_option('pcore_ai10_frontend_url', esc_url_raw($_POST['frontend_url'] ?? ''));
    $secret = sanitize_text_field($_POST['shared_secret'] ?? '');
    if (!empty($secret)) {
      if (function_exists('pcore_encrypt')) {
        update_option('pcore_ai10_shared_secret', pcore_encrypt($secret), false);
      } else {
        update_option('pcore_ai10_shared_secret', $secret, false);
      }
    }
    echo '<div class="updated"><p>Saved.</p></div>';
  }

  $api = get_option('pcore_ai10_api_start_url', '');
  $redir = get_option('pcore_ai10_return_redirect_url', home_url('/'));
  $frontend = get_option('pcore_ai10_frontend_url', '');
  $secret = get_option('pcore_ai10_shared_secret', '');
  if (function_exists('pcore_decrypt') && !empty($secret)) $secret = pcore_decrypt($secret);

  ?>
  <div class="wrap">
    <h1>AI‑CME Bridge (credit flow)</h1>
    <form method="post">
      <?php wp_nonce_field('pcore_ai10_settings'); ?>
      <table class="form-table" role="presentation">
        <tr>
          <th><label for="api_start_url">AI_CME_API (start session) URL</label></th>
          <td><input type="url" class="regular-text" id="api_start_url" name="api_start_url" value="<?php echo esc_attr($api); ?>" placeholder="https://ai.example.com/api/start" required></td>
        </tr>
        <tr>
          <th><label for="return_redirect_url">Return redirect URL</label></th>
          <td><input type="url" class="regular-text" id="return_redirect_url" name="return_redirect_url" value="<?php echo esc_attr($redir); ?>"></td>
        </tr>
        <tr>
          <th><label for="frontend_url">Frontend URL (redirect after launch)</label></th>
          <td><input type="url" class="regular-text" id="frontend_url" name="frontend_url"
                value="<?php echo esc_attr($frontend); ?>"
                placeholder="http://20.163.3.202/" required></td>
        </tr>
        <tr>
          <th><label for="shared_secret">Shared secret</label></th>
          <td>
            <input type="text" class="regular-text" id="shared_secret" name="shared_secret" value="<?php echo esc_attr($secret); ?>" placeholder="use a long random string">
            <p class="description">Used to sign/verify JWT tokens exchanged with the AI-CME platform.</p>
          </td>
        </tr>
      </table>
      <p><button type="submit" class="button button-primary">Save settings</button></p>
      <h2>Callback endpoints you expose</h2>
      <p><code><?php echo esc_html( home_url('/wp-json/platform-core/v1/ai-cme/return') ); ?></code> (server‑to‑server POST)</p>
      <p><code><?php echo esc_html( home_url('/ai-cme-dashboard/') ); ?></code> (browser redirect GET)</p>
    </form>
  </div>
  <?php
}

//
// ---------------------------------------------------------------------
// 4) JWT helpers (HS256, minimal)
// ---------------------------------------------------------------------
function pcore_ai10_b64url($data) { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
function pcore_ai10_jwt_sign(array $payload, $secret) {
  $header   = ['alg' => 'HS256', 'typ' => 'JWT'];
  $segments = [ pcore_ai10_b64url(json_encode($header)), pcore_ai10_b64url(json_encode($payload)) ];
  $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
  $segments[] = pcore_ai10_b64url($signature);
  return implode('.', $segments);
}
function pcore_ai10_jwt_decode($jwt, $secret) {
  $parts = explode('.', $jwt);
  if (count($parts) !== 3) return new WP_Error('bad_token', 'Malformed token');
  list($h, $p, $s) = $parts;
  $sig = base64_decode(strtr($s, '-_', '+/'));
  $calc = hash_hmac('sha256', $h.'.'.$p, $secret, true);
  if (!hash_equals($calc, $sig)) return new WP_Error('bad_sig', 'Signature mismatch');
  $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
  if (isset($payload['exp']) && time() >= (int)$payload['exp']) return new WP_Error('token_expired','Token expired');
  return $payload;
}
function pcore_ai10_shared_secret() {
  $raw = get_option('pcore_ai10_shared_secret', '');
  if (function_exists('pcore_decrypt') && !empty($raw)) return pcore_decrypt($raw);
  return $raw;
}

//
// ---------------------------------------------------------------------
// 5) LAUNCH SHORTCODE + HANDLER
// ---------------------------------------------------------------------
add_shortcode('ai_cme_launch', function($atts){
  if (!is_user_logged_in()) {
    return '<p>Please <a href="'.esc_url(wp_login_url(get_permalink())).'">log in</a> to access AI‑CME.</p>';
  }
  $u = wp_get_current_user();
  $bal = pcore_ai10_get_balance($u->ID);
  $action = esc_url( admin_url('admin-post.php') );
  $nonce  = wp_create_nonce('pcore_ai10_launch');

  ob_start(); ?>
  <div class="ai-cme-launcher">
    <p><strong>AI‑CME credits:</strong> <?php echo esc_html($bal); ?></p>
    <form method="post" action="<?php echo $action; ?>">
      <input type="hidden" name="action" value="pcore_ai10_launch">
      <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
      <button class="button button-primary">Open AI‑CME</button>
    </form>
  </div>
  <?php
  return ob_get_clean();
});

  // POST handler → call AI_CME backend then redirect to the AI frontend
// POST handler ? call AI_CME backend then redirect to the AI frontend
add_action('admin_post_pcore_ai10_launch', 'pcore_ai10_handle_launch');
function pcore_ai10_handle_launch() {
  if (!is_user_logged_in()) wp_safe_redirect(wp_login_url());
  if (!check_admin_referer('pcore_ai10_launch')) wp_die('Bad nonce');

  $launch_url   = trim(get_option('pcore_ai10_api_start_url', ''));   // e.g. http://20.163.3.202/api/api/launch-from-platform
  $frontend_url = trim(get_option('pcore_ai10_frontend_url', ''));    // e.g. http://20.163.3.202/
  $secret       = pcore_ai10_shared_secret();

  if (empty($launch_url))   wp_die('AI_CME_API not configured');
  if (empty($frontend_url)) wp_die('Frontend URL not configured');
  if (empty($secret))       wp_die('Shared secret not configured');

  $u   = wp_get_current_user();
  $uid = (int) $u->ID;
  $bal = (int) pcore_ai10_get_balance($uid);

  // Build exactly the JSON the backend expects; sign the RAW JSON string
  $body_arr = [
    'credits'         => $bal,
    'email'           => (string) $u->user_email,
    'exp'             => time() + 300,
    'iat'             => time(),
    'return_url_get'  => home_url('/ai-cme/return'),
    'return_url_post' => home_url('/wp-json/platform-core/v1/ai-cme/return'),
    'uid'             => $uid,
  ];
  $raw_json = wp_json_encode($body_arr, JSON_UNESCAPED_SLASHES);
  $sig      = hash_hmac('sha256', $raw_json, $secret); // hex string

  // POST to backend
  $resp = wp_remote_post($launch_url, [
    'timeout' => 20,
    'headers' => [
      'Content-Type'      => 'application/json',
      'X-Launch-Signature'=> $sig,
    ],
    'body'    => $raw_json,
  ]);

  if (is_wp_error($resp)) {
    wp_die('AI-CME API error: ' . esc_html($resp->get_error_message()));
  }

  $code      = (int) wp_remote_retrieve_response_code($resp);
  $resp_body = json_decode(wp_remote_retrieve_body($resp), true);

  // Expected success sample:
  // { "status":"success", "user_id":"5298f619-...-96379", "message":"Launch accepted" }
  if ($code !== 200 || !is_array($resp_body) || ($resp_body['status'] ?? '') !== 'success') {
    wp_die('AI-CME launch failed. Status code '.$code);
  }

  // Extract the user_id from the backend response
  $adaptive_user_id = isset($resp_body['user_id']) ? sanitize_text_field($resp_body['user_id']) : null;

  if (empty($adaptive_user_id)) {
    wp_die('AI-CME backend did not return a user_id');
  }

  // Log a "session start" for traceability (store the backend user_id)
  global $wpdb;
  $wpdb->insert($wpdb->prefix.'ai_cme_sessions', [
    'user_id'             => $uid,
    'external_session_id' => $adaptive_user_id,
    'started_at'          => current_time('mysql', 1),
    'credits_before'      => $bal,
    'payload_out'         => $raw_json,                         // what we sent
    'payload_in'          => wp_json_encode($resp_body),        // backend ack
  ], ['%d','%s','%s','%d','%s','%s']);

  // *** FIX: Include user_id in the redirect URL ***
  $redirect_url = add_query_arg('user_id', $adaptive_user_id, $frontend_url);
  
  wp_safe_redirect($redirect_url);
  exit;
}



//
// ---------------------------------------------------------------------
// 6) RETURN API (server POST) — /wp-json/platform-core/v1/ai-cme/return
// ---------------------------------------------------------------------
add_action('rest_api_init', function(){
  register_rest_route('platform-core/v1', '/ai-cme/return', [
    'methods'  => 'POST',
    'permission_callback' => '__return_true',
    'callback' => function(WP_REST_Request $r){
      $secret = pcore_ai10_shared_secret();
      $raw = $r->get_body();
      $hdr = $r->get_header('X-Launch-Signature');
      if ($raw && $hdr) {
        $calc = hash_hmac('sha256', $raw, pcore_ai10_shared_secret());
        if (!hash_equals(strtolower($calc), strtolower($hdr))) {
          return new WP_REST_Response(['error' => 'Bad signature'], 401);
        }
        $payload = json_decode($raw, true);
      } 
      $token = $r->get_param('token');
      if (!$token) {
        return new WP_REST_Response(['error' => 'Missing token'], 400);
      }
      $payload = pcore_ai10_jwt_decode($token, $secret);
      if (is_wp_error($payload)) {
        return new WP_REST_Response(['error' => $payload->get_error_message()], 401);
      }

      $uid     = (int)($payload['uid'] ?? 0);
      $credits = (int)($payload['credits'] ?? -1);
      $ext_sid = sanitize_text_field($payload['session_id'] ?? '');
      if (!$uid || $credits < 0) {
        return new WP_REST_Response(['error' => 'Invalid payload'], 422);
      }

      // Replace balance (per flow) and close any open session with this external id
      $old = pcore_ai10_get_balance($uid);
      pcore_ai10_set_balance($uid, $credits, 'ai_return', $ext_sid);

      global $wpdb;
      $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->prefix}ai_cme_sessions
         SET finished_at = %s, credits_after = %d, payload_in = %s
         WHERE user_id = %d AND (external_session_id = %s OR external_session_id IS NULL)
         ORDER BY id DESC LIMIT 1",
         current_time('mysql', 1), $credits, wp_json_encode($payload), $uid, $ext_sid
      ));

      // Email summary
      pcore_ai10_email_summary($uid, $old, $credits, $ext_sid);

      return new WP_REST_Response(['ok' => true]);
    }
  ]);
});

//
// ---------------------------------------------------------------------
// 7) RETURN (browser GET) — /ai-cme/return?token=...
// ---------------------------------------------------------------------
add_action('init', function(){
  add_rewrite_rule('^ai-cme/return/?$', 'index.php?pcore_ai10_return=1', 'top');
  add_rewrite_tag('%pcore_ai10_return%', '1');
});
add_action('template_redirect', function(){
  if (get_query_var('pcore_ai10_return')) {
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    $secret = pcore_ai10_shared_secret();
    if ($token && $secret) {
      $payload = pcore_ai10_jwt_decode($token, $secret);
      if (!is_wp_error($payload)) {
        $uid     = (int)($payload['uid'] ?? 0);
        $credits = (int)($payload['credits'] ?? -1);
        $ext_sid = sanitize_text_field($payload['session_id'] ?? '');
        if ($uid && $credits >= 0) {
          $old = pcore_ai10_get_balance($uid);
          pcore_ai10_set_balance($uid, $credits, 'ai_return_get', $ext_sid);

          global $wpdb;
          $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}ai_cme_sessions SET finished_at=%s, credits_after=%d, payload_in=%s
             WHERE user_id=%d AND (external_session_id=%s OR external_session_id IS NULL)
             ORDER BY id DESC LIMIT 1",
            current_time('mysql',1), $credits, wp_json_encode($payload), $uid, $ext_sid
          ));

          pcore_ai10_email_summary($uid, $old, $credits, $ext_sid);
        }
      }
    }
    $to = get_option('pcore_ai10_return_redirect_url', home_url('/'));
    wp_safe_redirect($to);
    exit;
  }
});

//
// ---------------------------------------------------------------------
// 8) SUMMARY EMAIL
// ---------------------------------------------------------------------
function pcore_ai10_email_summary($user_id, $before, $after, $ext_sid='') {
  $user = get_user_by('id', $user_id);
  if (!$user) return;
  $to   = $user->user_email;
  $subj = 'AI‑CME session summary';
  $msg  = "Hi {$user->display_name},\n\n".
          "Your AI‑CME session has ended.\n\n".
          "Credits before: {$before}\n".
          "Credits now:    {$after}\n".
          (!empty($ext_sid) ? "Session ID: {$ext_sid}\n" : '') .
          "\nYou can see your balance here: ". home_url('/my/ai-cme') ."\n\n".
          "— ". get_bloginfo('name');
  wp_mail($to, $subj, $msg);
}

//
// ---------------------------------------------------------------------
// 9) DASHBOARD SHORTCODE — [ai_cme_credits_dashboard]
// ---------------------------------------------------------------------
add_shortcode('ai_cme_credits_dashboard', function(){
  if (!is_user_logged_in()) return '';
  $uid = get_current_user_id();
  $bal = pcore_ai10_get_balance($uid);

  global $wpdb;
  $rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ai_cme_credit_ledger WHERE user_id=%d ORDER BY id DESC LIMIT 25", $uid
  ), ARRAY_A );

  ob_start(); ?>
  <div class="ai-cme-dashboard">
    <h3>AI‑CME credits: <?php echo esc_html($bal); ?></h3>
    <p><a class="button button-primary" href="<?php echo esc_url( admin_url('admin-post.php?action=pcore_ai10_launch&_wpnonce='.wp_create_nonce('pcore_ai10_launch')) ); ?>">Open AI‑CME</a></p>
    <h4>Recent credit activity</h4>
    <table class="wp-list-table widefat fixed striped">
      <thead><tr><th>When</th><th>Change</th><th>Old</th><th>New</th><th>Reason</th><th>Ref</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo esc_html( mysql2date('Y-m-d H:i', $r['created_at']) ); ?></td>
            <td><?php echo esc_html( $r['delta'] ); ?></td>
            <td><?php echo esc_html( $r['old_balance'] ); ?></td>
            <td><?php echo esc_html( $r['new_balance'] ); ?></td>
            <td><?php echo esc_html( $r['reason'] ); ?></td>
            <td><?php echo esc_html( $r['ref'] ); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
  return ob_get_clean();
});
?>
