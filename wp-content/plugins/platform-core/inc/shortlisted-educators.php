<?php
// platform-core/inc/shortlisted-educators.php
if (!defined('ABSPATH')) exit;

class PlatformCore_Shortlisted_Educators_Page {
    private $tbl_shortlists;
    private $service_id = 6;

    private function amelia_get_providers_map(): array {
        $url = platform_core_amelia_api_base('/users/providers&services[0]=' . (int)$this->service_id);
        $res = wp_remote_get($url, ['headers' => platform_core_amelia_api_headers(), 'timeout' => 45, 'sslverify' => false]);
        if (is_wp_error($res)) return [];
        $decoded = json_decode(wp_remote_retrieve_body($res), true);
        $users   = $decoded['data']['users'] ?? [];
        $map     = [];
        foreach ($users as $p) {
            $email = strtolower(trim($p['email'] ?? ''));
            if ($email) {
                $map[$email] = [
                    'activity'   => $p['activity']  ?? 'away',
                    'first_name' => $p['firstName']  ?? '',
                    'last_name'  => $p['lastName']   ?? '',
                ];
            }
        }
        return $map;
    }

    public function render() {
        // Redirect guests to landing page
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/college-dashboard/'));
            exit;
        }

        $u       = wp_get_current_user();
        $allowed = in_array('college_admin', (array)$u->roles, true) || current_user_can('manage_options');
        if (!$allowed) {
            return '<div style="padding:20px;">You do not have permission to view this page.</div>';
        }

        $first_name = get_user_meta($u->ID, 'first_name', true);
        if (!empty(trim($first_name))) {
            $pc_nav_name = trim($first_name);
        } elseif (!empty($u->display_name) && strpos($u->display_name, '@') === false) {
            $pc_nav_name = $u->display_name;
        } else {
            $pc_nav_name = explode('@', $u->user_email)[0];
        }
        $pc_nav_avatar = get_avatar_url($u->ID, array('size' => 36, 'default' => 'mystery'));

        global $wpdb;
        $current_user_id = get_current_user_id();
        $expert_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT expert_user_id FROM {$this->tbl_shortlists} WHERE college_user_id = %d",
                $current_user_id
            )
        );

        $providers = $this->amelia_get_providers_map();
        $experts   = [];
        foreach ($expert_ids as $eid) {
            $user = get_userdata($eid);
            if (!$user) continue;

            $email   = strtolower($user->user_email);
            $p       = $providers[$email] ?? null;
            $avail   = $p && ($p['activity'] === 'available');
            $specRaw = get_user_meta($eid, '_tutor_instructor_speciality', true);
            $specs   = array_filter(array_map('trim', explode(',', (string)$specRaw)));
            $expYrs  = (int)(get_user_meta($eid, '_tutor_instructor_experience', true) ?: 1);
            $avatar  = get_avatar_url($eid);

            $experts[] = [
                'ID'                => $eid,
                'name'              => $user->display_name,
                'email'             => $email,
                'available'         => $avail,
                'availability_text' => $avail ? 'Available now' : 'Unavailable',
                'specialties'       => $specs ?: ['General'],
                'experience'        => $expYrs,
                'avatar'            => $avatar,
                'book_url'          => esc_url(site_url('/college/request-class?expert_id=' . $eid)),
                'profile_url'       => '#',
            ];
        }

        // --- Nav URLs ---
        $url_dashboard   = home_url('/platform-dashboard');
        $url_find        = home_url('/find_educators');
        $url_sessions    = home_url('/college-sessions');
        $url_contracts   = home_url('/contracts-sessions');
        $url_shortlisted = get_permalink();

        ob_start();
        $pc_shortlist_nonce = wp_create_nonce('pc_shortlist_nonce');
        ?>

        <style>
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

        footer.site-footer,.site-footer,#colophon,#footer,.footer-area,
        .ast-footer-overlay,.footer-widgets-area,.footer-bar,
        div[data-elementor-type="footer"],.elementor-location-footer{display:none!important;}
        </style>

        <nav class="pc-nav">
            <div class="pc-nav-inner">
                <a href="<?php echo esc_url(home_url()); ?>" class="pc-nav-logo">LOGO</a>
                <div class="pc-nav-links">
                    <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
                    <a href="<?php echo esc_url($url_find); ?>">Find Educators</a>
                    <a href="<?php echo esc_url($url_sessions); ?>">Sessions</a>
                    <a href="<?php echo esc_url($url_contracts); ?>">Contracts</a>
                    <a href="<?php echo esc_url($url_shortlisted); ?>" class="active">Shortlisted</a>
                </div>
                <div class="pc-nav-right">
                    <img src="<?php echo esc_url($pc_nav_avatar); ?>" alt="Profile">
                    <span class="pc-nav-username">Hi, <?php echo esc_html($pc_nav_name); ?></span>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pc-nav-btn">Logout</a>
                </div>
            </div>
        </nav>

        <?php
        include plugin_dir_path(__FILE__) . '../templates/shortlisted-educators.php';

        return ob_get_clean();
    }

    public function __construct() {
        global $wpdb;
        $this->tbl_shortlists = $wpdb->prefix . 'platform_shortlists';
        add_shortcode('platform_shortlisted_educators', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        if (!is_singular()) return;
        global $post;
        if (!$post) return;

        if (has_shortcode($post->post_content, 'platform_shortlisted_educators') || is_page('shortlisted-educators')) {
            $plugin_main = dirname(__FILE__) . '/../platform-core.php';
            $css_url     = plugin_dir_url($plugin_main) . 'assets/css/shortlisted-educators.css';

            wp_enqueue_style('pc-shortlisted-educators', $css_url, [], '1.0.6');

            wp_add_inline_style(
                'pc-shortlisted-educators',
                '.fe-wrapper .hidden{display:none !important;}
                 .sl-wrap .hidden{display:none !important;}
                 .fe-wrapper .expert-card.hidden,
                 .fe-wrapper .educator-card.hidden,
                 .sl-wrap .expert-card.hidden,
                 .sl-wrap .educator-card.hidden{display:none !important;}'
            );
        }
    }
}

new PlatformCore_Shortlisted_Educators_Page();