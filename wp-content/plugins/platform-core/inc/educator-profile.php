<?php
/**
 * Educator Profile (View Profile) page
 * Shortcode: [platform_educator_profile]
 */

if (!defined('ABSPATH')) exit;

class PlatformCore_EducatorProfile_Flow {

    private $tbl_requests;

    private function amelia_admin_ajax_url() {
        return home_url('/amelia/wp-admin/admin-ajax.php');
    }

    private function log_error($label, $context = []) {
        $msg = '[PCORE EducatorProfile] ' . $label;
        if (!empty($context)) {
            $msg .= ' | ' . wp_json_encode($context);
        }
        error_log($msg);
    }

    public function __construct() {
        global $wpdb;
        $this->tbl_requests = $wpdb->prefix . 'platform_requests';

        add_shortcode('platform_educator_profile', [$this, 'sc_render']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    private function can_access() {
        return is_user_logged_in() && (current_user_can('college_admin') || current_user_can('manage_options'));
    }

    public function register_routes() {
        register_rest_route('platform-core/v1', '/educator/slots', [
            'methods'             => 'GET',
            'callback'            => [$this, 'api_get_slots'],
            'permission_callback' => function () { return $this->can_access(); },
            'args' => [
                'expert_id'        => ['required' => true],
                'date'             => ['required' => true],
                'duration_minutes' => ['required' => false],
            ]
        ]);

        register_rest_route('platform-core/v1', '/educator/book', [
            'methods'             => 'POST',
            'callback'            => [$this, 'api_book'],
            'permission_callback' => function () { return $this->can_access(); },
        ]);
    }

    /**
     * ===== Shortcode renderer =====
     */
    public function sc_render($atts = []) {
        if (!$this->can_access()) {
            return '<div style="padding:20px; color:#b91c1c;">You need a College Admin account to view this page.</div>';
        }

        $expert_id = absint($_GET['expert_id'] ?? $_GET['educator_id'] ?? 0);
        if (!$expert_id) {
            return '<div style="padding:20px; color:#b91c1c;">Missing educator id. Please open this page from Find Educator / Shortlisted Educator.</div>';
        }

        $expert = get_userdata($expert_id);
        if (!$expert || !in_array('expert', (array)$expert->roles, true)) {
            return '<div style="padding:20px; color:#b91c1c;">Invalid educator.</div>';
        }

        // --- Educator data ---
        $educator_name = $expert->display_name;

        // About Me: prefer the registration field; fall back to WP bio if empty
        $educator_about = (string) get_user_meta($expert_id, '_tutor_instructor_about_me', true);
        if ( $educator_about === '' ) {
            $educator_about = (string) get_user_meta($expert_id, 'description', true);
        }

        $raw_spec = (string) get_user_meta($expert_id, '_tutor_instructor_speciality', true);
        $specs = [];
        if (!empty($raw_spec)) {
            $specs = array_values(array_filter(array_map('trim', explode(',', $raw_spec))));
        }

        $educator_headline = !empty($specs) ? implode(', ', array_slice($specs, 0, 2)) : 'Educator';
        $educator_avatar   = get_avatar_url($expert_id, ['size' => 144]);

        $is_verified = (get_user_meta($expert_id, '_tutor_instructor_status', true) === 'approved');

        // --- Current user (header) ---
        $cu = wp_get_current_user();
        $cu_name   = $cu->display_name ?: $cu->user_email;
        $cu_avatar = get_avatar_url($cu->ID, ['size' => 64]);

        // --- Template loading ---
        $tpl_path = plugin_dir_path(__FILE__) . '../templates/educatorprofile.php';
        if (!file_exists($tpl_path)) {
            $this->log_error('Template missing', ['path' => $tpl_path]);
            return '<div style="padding:20px; color:#b91c1c;">Template not found: templates/educatorprofile.php</div>';
        }

        ob_start();
        include $tpl_path;
        $raw = ob_get_clean();

        // Extract CSS from template
        $css = '';
        if (preg_match_all('~<style[^>]*>(.*?)</style>~is', $raw, $m_styles)) {
            $css = trim(implode("\n\n", array_map('trim', $m_styles[1])));
        }

        // Extract <body> inner HTML
        $html = $raw;
        if (preg_match('~<body[^>]*>(.*)</body>~is', $raw, $m_body)) {
            $html = $m_body[1];
        }

        // --- Strip the template's own header entirely (replaced by injected navbar) ---
        $html = preg_replace('~<div\s+class="header"[^>]*>.*?</div>\s*</div>~is', '', $html, 1);

        // --- Platform overrides ---
        $css .= "\n\n/* Platform overrides */\n";
        $css .= "#wpadminbar{display:none!important;}\n";
        $css .= "html{margin-top:0!important;}\n";
        $css .= "header,#masthead,.site-header,.main-header,#header,.elementor-location-header,.ast-main-header-wrap,#site-header,.fusion-header-wrapper,.header-wrap,.nav-primary,.navbar,div[data-elementor-type=\"header\"]{display:none!important;}\n";
        $css .= ".page-template-default .site-content,.site-main,#content,#page{margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;}\n";

        // Hide discount block
        $css .= ".discount{display:none!important;}\n";

        // Active states
        $css .= ".date{cursor:pointer;}\n";
        $css .= ".date.pc-active{background:#000;color:#fff;border:none;}\n";
        $css .= ".date.pc-disabled{opacity:.35;cursor:not-allowed;}\n";
        $css .= ".slot.pc-active{background:#000;color:#fff;border:none;}\n";
        $css .= ".duration div{cursor:pointer;}\n";

        // Duration buttons: force single row, compact
        $css .= ".duration{display:flex!important;flex-wrap:nowrap!important;gap:6px!important;}\n";
        $css .= ".duration div{flex:1 1 0!important;min-width:0!important;text-align:center!important;padding:4px 6px!important;font-size:12px!important;box-sizing:border-box!important;}\n";

        // --- Inject educator avatar ---
        $html = preg_replace('~<img\s+src="assets/images/doctor\.jpg"\s*>~i', '<img src="'.esc_url($educator_avatar).'" alt="Educator">', $html, 1);

        // --- Inject educator name + verified ---
        $h2 = '<h2>' . esc_html($educator_name);
        if ($is_verified) {
            $h2 .= ' <span class="verified">&#10003; Verified</span>';
        }
        $h2 .= '</h2>';
        $html = preg_replace('~<h2>.*?</h2>~is', $h2, $html, 1);

        // --- Inject educator headline ---
        $html = preg_replace('~<small>\s*Medical Education Specialist\s*</small>~is', '<small>'.esc_html($educator_headline).'</small>', $html, 1);

        // --- Inject About Me ---
        // Uses _tutor_instructor_about_me (from registration) with WP bio as fallback
        $html = preg_replace(
            '~(<h4>\s*About Me\s*</h4>\s*<p>)(.*?)(</p>)~is',
            '$1' . esc_html($educator_about) . '$3',
            $html,
            1
        );

        // --- Inject Specializations tags ---
        $tags_html = '';
        foreach ($specs as $s) {
            $tags_html .= '<span>' . esc_html($s) . '</span>';
        }
        $html = preg_replace(
            '~(<h4>\s*Specializations\s*</h4>\s*<div\s+class="tags"\s*>)(.*?)(</div>)~is',
            '$1' . $tags_html . '$3',
            $html,
            1
        );

        // --- Change "Book Session" button text to "Request Session" ---
        $html = preg_replace(
            '~(<button[^>]*id=["\']pc-book-btn["\'][^>]*>)\s*Book Session\s*(</button>)~is',
            '$1Request Session$2',
            $html
        );
        // Fallback: generic button text replacement if no id match
        $html = preg_replace(
            '~(<button[^>]*class="[^"]*book[^"]*"[^>]*>)\s*Book Session\s*(</button>)~is',
            '$1Request Session$2',
            $html
        );

        // --- Clear placeholder calendar dates and slots (JS will rebuild) ---
        $html = preg_replace('~(<div[^>]*class="[^"]*\bdates\b[^"]*"[^>]*>)(.*?)(</div>)~is', '$1$3', $html);
        $html = preg_replace('~(<div[^>]*class="[^"]*\bslots\b[^"]*"[^>]*>)(.*?)(</div>)~is', '$1$3', $html);

        // --- Fetch expert pricing (for JS localization only – not injected into HTML) ---
        $expert_prices = $this->get_service_prices_for_expert($expert_id);

        // Root wrapper
        $out = '';
        if ($css) {
            $out .= '<style id="pc-educatorprofile-inline">' . $css . '</style>';
        }

        // Enqueue JS
        $handle = 'pcore-educator-profile';
        wp_register_script(
            $handle,
            plugins_url('../assets/educator-profile.js', __FILE__),
            [],
            '1.5.0',
            true
        );

        $tz    = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $today = (new DateTime('now', $tz))->format('Y-m-d');

        wp_localize_script($handle, 'pcoreEducatorProfile', [
            'expertId'      => (int)$expert_id,
            'slotsEndpoint' => esc_url_raw(rest_url('platform-core/v1/educator/slots')),
            'bookEndpoint'  => esc_url_raw(rest_url('platform-core/v1/educator/book')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'today'         => $today,
            'prices'        => $expert_prices,
            // JS will use these labels for button text and alert messages
            'labels'        => [
                'bookBtn'       => 'Request Session',
                'successAlert'  => 'Session requested successfully! The educator will review and respond to your request.',
                'successTitle'  => 'Session Requested',
            ],
        ]);
        wp_enqueue_script($handle);

        // ---- Navbar: logo + nav links + avatar/name + logout ----
        $url_dashboard   = home_url('/platform-dashboard');
        $url_find        = home_url('/find_educators');
        $url_sessions    = home_url('/college-sessions');
        $url_contracts   = home_url('/college-contracts');
        $url_shortlisted = home_url('/shortlisted-educators');

        $nav_avatar = get_avatar_url($cu->ID, ['size' => 36, 'default' => 'mystery']);
        $first_name = $cu->user_firstname ? $cu->user_firstname : $cu->display_name;

        $navbar_css = '<style id="pc-educatorprofile-navbar-css">
.pc-nav{background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid #e4e7ef;position:sticky;top:0;z-index:200;box-shadow:0 1px 0 #e4e7ef,0 2px 12px rgba(0,0,0,.04);}
.pc-nav-inner{max-width:1400px;margin:auto;padding:0 36px;height:58px;display:flex;justify-content:space-between;align-items:center;}
.pc-nav-logo{font-weight:800;color:#4338ca;font-size:20px;text-decoration:none;letter-spacing:-.5px;}
.pc-nav-links{display:flex;gap:2px;}
.pc-nav-links a{padding:7px 16px;border-radius:8px;font-size:14px;font-weight:500;color:#6b7280;text-decoration:none;transition:background .18s,color .18s;letter-spacing:-.1px;}
.pc-nav-links a:hover{background:#eef2ff;color:#4338ca;}
.pc-nav-right{display:flex;align-items:center;gap:14px;}
.pc-nav-right img{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;box-shadow:0 0 0 2px #eef2ff;}
.pc-nav-username{font-weight:600;font-size:13px;color:#0f172a;}
.pc-nav-btn{padding:7px 18px;border-radius:8px;font-size:13px;font-weight:600;background:#0f172a;color:#fff;text-decoration:none;transition:opacity .15s,transform .15s;}
.pc-nav-btn:hover{opacity:.88;transform:translateY(-1px);}
@media(max-width:768px){.pc-nav-links{display:none;}}
</style>';

        $navbar_html = sprintf(
            '<nav class="pc-nav"><div class="pc-nav-inner">
                <a href="%s" class="pc-nav-logo">LOGO</a>
                <div class="pc-nav-links">
                    <a href="%s">Dashboard</a>
                    <a href="%s">Find Educators</a>
                    <a href="%s">Sessions</a>
                    <a href="%s">Contracts</a>
                    <a href="%s">Shortlisted</a>
                </div>
                <div class="pc-nav-right">
                    <img src="%s" alt="Profile">
                    <span class="pc-nav-username">Hi, %s</span>
                    <a href="%s" class="pc-nav-btn">Logout</a>
                </div>
            </div></nav>',
            esc_url(home_url()),
            esc_url($url_dashboard),
            esc_url($url_find),
            esc_url($url_sessions),
            esc_url($url_contracts),
            esc_url($url_shortlisted),
            esc_url($nav_avatar),
            esc_html($first_name),
            esc_url(wp_logout_url(home_url()))
        );

        $out = $navbar_css . $navbar_html . $out;
        $out .= '<div id="pc-educatorprofile-root">' . $html . '</div>';

        // Build the redirect URL as a PHP variable so it interpolates correctly
        $contracts_redirect_url = esc_js( home_url('/college-contracts') );

        // Inline JS patch: rename button label + redirect to contracts on success
        $out .= "<script>
(function(){
    var CONTRACTS_URL = '{$contracts_redirect_url}';

    document.addEventListener('DOMContentLoaded', function(){
        /* ---- Rename any remaining 'Book Session' button text ---- */
        document.querySelectorAll('button, input[type=\"submit\"], input[type=\"button\"]').forEach(function(el){
            if (/book\\s+session/i.test(el.textContent || el.value || '')) {
                if (el.tagName === 'INPUT') { el.value = 'Request Session'; }
                else { el.textContent = 'Request Session'; }
            }
        });
    });

    /* ---- Intercept window.alert — redirect to contracts on success ---- */
    var _origAlert = window.alert;
    window.alert = function(msg){
        if (typeof msg === 'string' && /booked\\s+successfully/i.test(msg)) {
            window.location.href = CONTRACTS_URL;
            return;
        }
        _origAlert.call(window, msg);
    };

    /* ---- Intercept fetch — redirect to contracts when book endpoint succeeds ---- */
    var _origFetch = window.fetch;
    window.fetch = function(url, opts) {
        var p = _origFetch.apply(this, arguments);
        if (typeof url === 'string' && url.indexOf('/educator/book') !== -1) {
            p.then(function(res) {
                var clone = res.clone();
                clone.json().then(function(data) {
                    if (data && (data.success || data.requested)) {
                        window.location.href = CONTRACTS_URL;
                    }
                }).catch(function(){});
            }).catch(function(){});
        }
        return p;
    };

})();
</script>";

        return $out;
    }

    /**
     * ===== REST: Get slots =====
     */
    public function api_get_slots(WP_REST_Request $r) {
        $expert_id = absint($r->get_param('expert_id'));
        $date      = sanitize_text_field($r->get_param('date'));
        $duration  = absint($r->get_param('duration_minutes') ?: 60);

        // Clamp to supported durations
        if (!in_array($duration, [30, 60, 90], true)) {
            $duration = 60;
        }

        if (!$expert_id || !$this->is_valid_date($date)) {
            return new WP_REST_Response(['message' => 'Invalid parameters.'], 400);
        }

        $tz    = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $today = (new DateTime('now', $tz))->format('Y-m-d');
        if ($date < $today) {
            return new WP_REST_Response(['message' => 'Past dates are not allowed.'], 400);
        }

        $expert = get_userdata($expert_id);
        if (!$expert || !in_array('expert', (array)$expert->roles, true)) {
            return new WP_REST_Response(['message' => 'Invalid educator.'], 404);
        }

        try {
            $slots  = $this->get_available_hour_slots($expert_id, $date, $duration);
            $prices = $this->get_service_prices_for_expert($expert_id);
            return new WP_REST_Response([
                'slots'  => $slots,
                'prices' => $prices,
            ]);
        } catch (Exception $e) {
            $this->log_error('api_get_slots exception', ['expert_id' => $expert_id, 'date' => $date, 'err' => $e->getMessage()]);
            return new WP_REST_Response(['message' => 'Failed to load slots.'], 500);
        }
    }

    /**
     * ===== REST: Book (Request Session) =====
     */
    public function api_book(WP_REST_Request $r) {
        $payload   = $r->get_json_params();
        $expert_id = absint($payload['expert_id'] ?? 0);
        $date      = sanitize_text_field($payload['date'] ?? '');
        $time      = sanitize_text_field($payload['time'] ?? '');
        $duration  = absint($payload['duration_minutes'] ?? 0);

        if (!$expert_id || !$this->is_valid_date($date) || !$this->is_valid_hour_time($time)) {
            return new WP_REST_Response(['message' => 'Invalid parameters.'], 400);
        }

        if (!in_array($duration, [30, 60, 90], true)) {
            return new WP_REST_Response(['message' => 'Invalid duration.'], 400);
        }

        $tz    = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $today = (new DateTime('now', $tz))->format('Y-m-d');
        if ($date < $today) {
            return new WP_REST_Response(['message' => 'Past dates are not allowed.'], 400);
        }

        $available = $this->get_available_hour_slots($expert_id, $date, $duration);
        $found = false;
        foreach ($available as $s) {
            if (($s['time'] ?? '') === $time) { $found = true; break; }
        }
        if (!$found) {
            return new WP_REST_Response(['message' => 'This slot is no longer available. Please refresh and try again.'], 409);
        }

        $expert = get_userdata($expert_id);
        if (!$expert || !in_array('expert', (array)$expert->roles, true)) {
            return new WP_REST_Response(['message' => 'Invalid educator.'], 404);
        }

        $start_iso = $date . ' ' . $time . ':00';
        $topic     = sanitize_text_field($payload['class_title'] ?? 'Session Request');
        $desc      = sanitize_textarea_field(
            $payload['description']
            ?? sprintf('Requested via Educator Profile. Duration: %d minutes.', $duration)
        );

        $offered_price = isset($payload['offered_price']) ? floatval($payload['offered_price']) : 0;
        if ($offered_price > 0) {
            $price = $offered_price;
        } else {
            $service_prices = $this->get_service_prices_for_expert($expert_id);
            $price = isset($service_prices[$duration]) ? $service_prices[$duration] : 1;
        }

        global $wpdb;
        $ok = $wpdb->insert(
            $this->tbl_requests,
            [
                'college_user_id'    => get_current_user_id(),
                'expert_user_id'     => $expert_id,
                'topic'              => $topic,
                'description'        => $desc,
                'proposed_start_iso' => $start_iso,
                'duration_minutes'   => $duration,
                'capacity'           => 1,
                'price_offer'        => $price,
                'status'             => 'requested',
                'created_at'         => current_time('mysql'),
                'updated_at'         => current_time('mysql'),
            ],
            ['%d','%d','%s','%s','%s','%d','%d','%f','%s','%s','%s']
        );

        if (!$ok) {
            $this->log_error('DB insert failed', ['err' => $wpdb->last_error]);
            return new WP_REST_Response(['message' => 'Database error.'], 500);
        }

        // Return "requested" flag so JS knows to show the correct message
        return new WP_REST_Response([
            'success'    => true,
            'requested'  => true,
            'request_id' => (int)$wpdb->insert_id,
            'message'    => 'Session requested successfully! The educator will review and respond to your request.',
        ]);
    }

    /**
     * Fetch expert pricing from Amelia service
     */
    private function get_service_prices_for_expert($expert_id) {
        $cache_key = 'pc_ep_prices_' . $expert_id;
        $cached    = get_transient($cache_key);

        $provider_id = $this->get_provider_id_for_expert($expert_id);
        if (!$provider_id) {
            return [30 => 1000, 60 => 1800, 90 => 3000];
        }

        $service_id = $this->get_service_id();
        $resp       = $this->amelia_call('/api/v1/users/providers/' . (int)$provider_id);

        if (empty($resp['data']['user']['serviceList']) || !is_array($resp['data']['user']['serviceList'])) {
            $this->log_error('Provider service list empty', ['expert_id' => $expert_id, 'provider_id' => $provider_id]);
            return [30 => 1000, 60 => 1800, 90 => 3000];
        }

        // Find the matching service by ID
        $service = null;
        foreach ($resp['data']['user']['serviceList'] as $svc) {
            if ((int)($svc['id'] ?? 0) === (int)$service_id) {
                $service = $svc;
                break;
            }
        }

        if (!$service) {
            $this->log_error('Service not found in provider list', ['expert_id' => $expert_id, 'service_id' => $service_id]);
            return [30 => 1000, 60 => 1800, 90 => 3000];
        }

        $base_price = isset($service['price']) ? floatval($service['price']) : 1800;

        $custom = [];
        $custom_pricing_json = $service['customPricing'] ?? '';
        if (is_string($custom_pricing_json) && !empty($custom_pricing_json)) {
            $decoded = json_decode($custom_pricing_json, true);
            if (is_array($decoded)) {
                $custom = $decoded;
            }
        }

        $prices = [];
        $prices[30] = isset($custom['durations']['1800']['price'])
            ? floatval($custom['durations']['1800']['price'])
            : round($base_price * 0.55);
        $prices[60] = $base_price;
        $prices[90] = isset($custom['durations']['5400']['price'])
            ? floatval($custom['durations']['5400']['price'])
            : round($base_price * 1.39);

        set_transient($cache_key, $prices, HOUR_IN_SECONDS);
        return $prices;
    }

    // -- Helpers --------------------------------------------------------------

    private function is_valid_date($date) {
        return (bool) preg_match('~^\d{4}-\d{2}-\d{2}$~', $date);
    }

    private function is_valid_hour_time($time) {
        return (bool) preg_match('~^(?:[01]?\d|2[0-3]):[03]0$~', $time);
    }

    private function format_time_label($time_hhmm, $duration_minutes = 60) {
        $ts = strtotime('1970-01-01 ' . $time_hhmm . ':00');
        if ($ts === false) return $time_hhmm;
        $start = date('g:i A', $ts);
        $end   = date('g:i A', $ts + ($duration_minutes * 60));
        return $start . ' - ' . $end;
    }

    private function get_flow7_settings() {
        $opts = get_option('platform_core_college_settings', []);
        return is_array($opts) ? $opts : [];
    }

    private function get_service_id() {
        $opts = $this->get_flow7_settings();
        $sid  = isset($opts['remote_class_service_id']) ? (int)$opts['remote_class_service_id'] : 0;
        return $sid > 0 ? $sid : 6;
    }

    private function get_location_id() {
        $opts = $this->get_flow7_settings();
        $lid  = isset($opts['default_location_id']) ? (int)$opts['default_location_id'] : 0;
        return $lid > 0 ? $lid : 1;
    }

    private function amelia_call($endpoint) {
        $url  = $this->amelia_admin_ajax_url() . '?action=wpamelia_api&call=' . ltrim($endpoint, '/');
        $args = [
            'headers'     => function_exists('platform_core_amelia_api_headers') ? platform_core_amelia_api_headers() : ['Content-Type' => 'application/json'],
            'timeout'     => 45,
            'sslverify'   => false,
            'httpversion' => '1.1',
        ];

        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            $this->log_error('Amelia call failed', ['endpoint' => $endpoint, 'err' => $res->get_error_message()]);
            return null;
        }

        $body = wp_remote_retrieve_body($res);
        if (!$body) {
            $this->log_error('Amelia empty response', ['endpoint' => $endpoint]);
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $this->log_error('Amelia non-JSON response', ['endpoint' => $endpoint, 'body' => substr($body, 0, 300)]);
            return null;
        }

        return $decoded;
    }

    private function get_provider_id_for_expert($expert_id) {
        $expert = get_userdata($expert_id);
        if (!$expert) return 0;

        $email = strtolower(trim($expert->user_email));
        if (!$email) return 0;

        $cache_key = 'pc_ep_provider_' . md5($email);
        $cached    = get_transient($cache_key);
        if ($cached) return (int)$cached;

        $service_id = $this->get_service_id();
        $resp       = $this->amelia_call('/api/v1/users/providers&services[0]=' . (int)$service_id);

        if (empty($resp['data']['users']) || !is_array($resp['data']['users'])) {
            $this->log_error('Provider lookup failed', ['expert_id' => $expert_id]);
            return 0;
        }

        foreach ($resp['data']['users'] as $p) {
            $p_email = strtolower(trim($p['email'] ?? ''));
            if ($p_email && $p_email === $email) {
                $pid = (int)($p['id'] ?? 0);
                if ($pid > 0) {
                    set_transient($cache_key, $pid, DAY_IN_SECONDS);
                    return $pid;
                }
            }
        }

        $this->log_error('Provider not found for email', ['expert_id' => $expert_id, 'email' => $email]);
        return 0;
    }

    private function get_reserved_blocks($expert_id, $date) {
        global $wpdb;
        $like1 = $date . '%';
        $like2 = $date . 'T%';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT proposed_start_iso, duration_minutes
             FROM {$this->tbl_requests}
             WHERE expert_user_id = %d
               AND status <> 'rejected'
               AND (proposed_start_iso LIKE %s OR proposed_start_iso LIKE %s)",
            $expert_id, $like1, $like2
        ));

        $blocks = [];
        foreach ($rows as $row) {
            $ts = strtotime($row->proposed_start_iso);
            if (!$ts) continue;
            $h = (int) date('H', $ts);
            $m = (int) date('i', $ts);
            $start_min = $h * 60 + $m;
            $dur       = max(30, (int) $row->duration_minutes);
            $blocks[]  = [
                'start_minutes' => $start_min,
                'end_minutes'   => $start_min + $dur,
            ];
        }
        return $blocks;
    }

    private function get_available_hour_slots($expert_id, $date, $duration_minutes = 60) {
        $provider_id = $this->get_provider_id_for_expert($expert_id);
        if (!$provider_id) throw new Exception('Amelia provider id not found');

        $service_id            = $this->get_service_id();
        $location_id           = $this->get_location_id();
        $service_duration_secs = $duration_minutes * 60;

        $startDateTime = rawurlencode($date . ' 00:00');
        $endpoint = '/api/v1/slots'
            . '&locationId='      . (int)$location_id
            . '&serviceId='       . (int)$service_id
            . '&serviceDuration=' . (int)$service_duration_secs
            . '&providerIds='     . (int)$provider_id
            . '&persons=1'
            . '&startDateTime='   . $startDateTime
            . '&extras=[]';

        $resp = $this->amelia_call($endpoint);
        if (empty($resp['data']['slots']) || !is_array($resp['data']['slots'])) {
            $this->log_error('Slots missing in response', ['endpoint' => $endpoint]);
            return [];
        }

        $day = $resp['data']['slots'][$date] ?? [];
        if (!is_array($day)) return [];

        $times = array_keys($day);
        $times = array_filter($times, function ($t) {
            return (bool) preg_match('~^(?:[01]?\d|2[0-3]):[03]0$~', $t);
        });
        sort($times);

        $reserved_blocks = $this->get_reserved_blocks($expert_id, $date);
        $times = array_values(array_filter($times, function ($t) use ($reserved_blocks, $duration_minutes) {
            list($h, $m) = explode(':', $t);
            $new_start = (int)$h * 60 + (int)$m;
            $new_end   = $new_start + $duration_minutes;
            foreach ($reserved_blocks as $block) {
                if ($new_start < $block['end_minutes'] && $new_end > $block['start_minutes']) {
                    return false;
                }
            }
            return true;
        }));

        $tz    = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $now   = new DateTime('now', $tz);
        $today = $now->format('Y-m-d');

        $out = [];
        foreach ($times as $t) {
            if ($date === $today) {
                $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $t, $tz);
                if ($dt && $dt <= $now) continue;
            }
            $out[] = ['time' => $t, 'label' => $this->format_time_label($t, $duration_minutes)];
        }

        return $out;
    }
}

new PlatformCore_EducatorProfile_Flow();