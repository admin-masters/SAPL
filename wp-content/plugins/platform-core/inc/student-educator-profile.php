<?php
/**
 * Student Educator Profile (Public View Profile) page
 * Shortcode: [student_educator_profile]
 * Accessible to all logged-in users (students, educators, college admins)
 */

if (!defined('ABSPATH')) exit;

class PlatformCore_StudentEducatorProfile_Flow {

    private function log_error($label, $context = []) {
        $msg = '[PCORE StudentEducatorProfile] ' . $label;
        if (!empty($context)) {
            $msg .= ' | ' . wp_json_encode($context);
        }
        error_log($msg);
    }

    public function __construct() {
        add_shortcode('student_educator_profile', [$this, 'sc_render']);
    }

    /**
     * ===== Shortcode renderer =====
     */
    public function sc_render($atts = []) {
        if (!is_user_logged_in()) {
            return '<div style="padding:20px; color:#b91c1c;">Please log in to view educator profiles.</div>';
        }

        $expert_id = absint($_GET['expert_id'] ?? $_GET['educator_id'] ?? 0);
        if (!$expert_id) {
            return '<div style="padding:20px; color:#b91c1c;">Missing educator id. Please provide a valid educator ID.</div>';
        }

        $expert = get_userdata($expert_id);
        if (!$expert || !in_array('expert', (array)$expert->roles, true)) {
            return '<div style="padding:20px; color:#b91c1c;">Invalid educator.</div>';
        }

        // --- Educator data ---
        $educator_name = $expert->display_name;

        // About Me: prefer the registration field; fall back to WP bio if empty
        $educator_about = (string) get_user_meta($expert_id, '_tutor_instructor_about_me', true);
        if ($educator_about === '') {
            $educator_about = (string) get_user_meta($expert_id, 'description', true);
        }
        if ($educator_about === '') {
            $educator_about = 'No biography available.';
        }

        $raw_spec = (string) get_user_meta($expert_id, '_tutor_instructor_speciality', true);
        $specs = [];
        if (!empty($raw_spec)) {
            $specs = array_values(array_filter(array_map('trim', explode(',', $raw_spec))));
        }

        $educator_headline = !empty($specs) ? implode(', ', array_slice($specs, 0, 2)) : 'Educator';
        $educator_avatar   = get_avatar_url($expert_id, ['size' => 144]);

        $is_verified = (get_user_meta($expert_id, '_tutor_instructor_status', true) === 'approved');
        
        // Get experience years
        $exp_years = (int) get_user_meta($expert_id, '_tutor_instructor_experience', true);

        // --- Current user (navbar) ---
        $cu = wp_get_current_user();
        $cu_roles = (array) $cu->roles;
        
        // Determine user display name
        $nav_fn = get_user_meta($cu->ID, 'first_name', true);
        if (!empty(trim($nav_fn))) {
            $nav_display = trim($nav_fn);
        } elseif (!empty($cu->display_name) && strpos($cu->display_name, '@') === false) {
            $nav_display = $cu->display_name;
        } else {
            $nav_display = explode('@', $cu->user_email)[0];
        }
        $nav_avatar = get_avatar_url($cu->ID, ['size' => 36, 'default' => 'mystery']);

        // Navigation links (same for all users)
        $url_dashboard = home_url('/webinar-dashboard');
        $url_library   = home_url('/webinar-library');
        $url_myevents  = home_url('/my-events');

        ob_start();
        ?>

        <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');

        /* Reset WordPress defaults */
        #wpadminbar{display:none!important;}
        html{margin-top:0!important;}
        header,#masthead,.site-header,.main-header,#header,
        .elementor-location-header,.ast-main-header-wrap,#site-header,
        .fusion-header-wrapper,.header-wrap,.nav-primary,.navbar,
        div[data-elementor-type="header"]{display:none!important;}
        .page-template-default .site-content,.site-main,#content,#page{
            margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;
        }
        footer.site-footer,.site-footer,#colophon,#footer,
        .footer-area,.ast-footer-overlay,.footer-widgets-area,.footer-bar,
        div[data-elementor-type="footer"],.elementor-location-footer{
            display:none!important;
        }

        /* Navigation */
        .sep-nav{
            background:rgba(255,255,255,0.95);
            backdrop-filter:blur(12px);
            -webkit-backdrop-filter:blur(12px);
            border-bottom:1px solid #e4e7ef;
            position:sticky;
            top:0;
            z-index:200;
            box-shadow:0 1px 0 #e4e7ef,0 2px 12px rgba(0,0,0,.04);
        }
        .sep-nav-inner{
            max-width:1400px;
            margin:auto;
            padding:0 36px;
            height:58px;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .sep-nav-logo{
            font-weight:800;
            color:#4338ca;
            font-size:20px;
            text-decoration:none;
            letter-spacing:-.5px;
        }
        .sep-nav-links{
            display:flex;
            gap:2px;
        }
        .sep-nav-links a{
            padding:7px 16px;
            border-radius:8px;
            font-size:14px;
            font-weight:500;
            color:#6b7280;
            text-decoration:none;
            transition:background .18s,color .18s;
            letter-spacing:-.1px;
        }
        .sep-nav-links a:hover{
            background:#eef2ff;
            color:#4338ca;
        }
        .sep-nav-right{
            display:flex;
            align-items:center;
            gap:14px;
        }
        .sep-nav-right img{
            width:34px;
            height:34px;
            border-radius:50%;
            object-fit:cover;
            border:2px solid #e5e7eb;
            box-shadow:0 0 0 2px #eef2ff;
        }
        .sep-nav-username{
            font-weight:600;
            font-size:13px;
            color:#0f172a;
        }
        .sep-nav-btn{
            padding:7px 18px;
            border-radius:8px;
            font-size:13px;
            font-weight:600;
            background:#0f172a;
            color:#fff;
            text-decoration:none;
            transition:opacity .15s,transform .15s;
        }
        .sep-nav-btn:hover{
            opacity:.88;
            transform:translateY(-1px);
        }

        /* Main container */
        .sep-wrap{
            font-family:'DM Sans',sans-serif;
            background:#f8fafc;
            color:#1e293b;
            min-height:100vh;
            padding:40px 24px 80px;
        }
        .sep-container{
            max-width:900px;
            margin:0 auto;
        }

        /* Back button */
        .sep-back{
            display:inline-flex;
            align-items:center;
            gap:8px;
            color:#64748b;
            text-decoration:none;
            font-size:14px;
            font-weight:500;
            margin-bottom:24px;
            transition:color .2s;
        }
        .sep-back:hover{
            color:#4338ca;
        }
        .sep-back svg{
            width:16px;
            height:16px;
        }

        /* Profile card */
        .sep-card{
            background:#fff;
            border-radius:16px;
            box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 20px rgba(0,0,0,.05);
            overflow:hidden;
        }

        /* Header section */
        .sep-header{
            background:linear-gradient(135deg,#4338ca 0%,#6366f1 100%);
            padding:40px 32px 32px;
            position:relative;
        }
        .sep-header::before{
            content:'';
            position:absolute;
            top:0;
            left:0;
            right:0;
            bottom:0;
            background:url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="white" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity:0.3;
        }
        .sep-header-content{
            position:relative;
            z-index:1;
            display:flex;
            align-items:center;
            gap:24px;
        }
        .sep-avatar{
            width:120px;
            height:120px;
            border-radius:50%;
            object-fit:cover;
            border:4px solid rgba(255,255,255,0.2);
            box-shadow:0 8px 24px rgba(0,0,0,.2);
        }
        .sep-header-info{
            flex:1;
        }
        .sep-name{
            font-family:'Playfair Display',serif;
            font-size:32px;
            font-weight:700;
            color:#fff;
            margin:0 0 8px;
            display:flex;
            align-items:center;
            gap:12px;
        }
        .sep-verified{
            display:inline-flex;
            align-items:center;
            gap:4px;
            background:rgba(34,197,94,0.2);
            color:#fff;
            padding:4px 12px;
            border-radius:20px;
            font-size:12px;
            font-weight:600;
            border:1px solid rgba(34,197,94,0.3);
        }
        .sep-verified svg{
            width:14px;
            height:14px;
        }
        .sep-headline{
            font-size:16px;
            color:rgba(255,255,255,0.9);
            margin:0;
        }

        /* Stats bar */
        .sep-stats{
            display:flex;
            gap:32px;
            margin-top:20px;
            padding-top:20px;
            border-top:1px solid rgba(255,255,255,0.2);
        }
        .sep-stat{
            display:flex;
            flex-direction:column;
        }
        .sep-stat-value{
            font-size:24px;
            font-weight:700;
            color:#fff;
        }
        .sep-stat-label{
            font-size:12px;
            color:rgba(255,255,255,0.7);
            text-transform:uppercase;
            letter-spacing:0.5px;
        }

        /* Content section */
        .sep-content{
            padding:32px;
        }

        /* Section */
        .sep-section{
            margin-bottom:32px;
        }
        .sep-section:last-child{
            margin-bottom:0;
        }
        .sep-section-title{
            font-size:18px;
            font-weight:700;
            color:#1e293b;
            margin:0 0 16px;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .sep-section-title svg{
            width:20px;
            height:20px;
            color:#4338ca;
        }

        /* About text */
        .sep-about{
            font-size:15px;
            line-height:1.7;
            color:#475569;
        }

        /* Specializations */
        .sep-tags{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }
        .sep-tag{
            display:inline-flex;
            align-items:center;
            padding:8px 16px;
            background:#eef2ff;
            color:#4338ca;
            border-radius:8px;
            font-size:14px;
            font-weight:600;
            border:1px solid #c7d2fe;
        }

        /* Info grid */
        .sep-info-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
            gap:16px;
        }
        .sep-info-item{
            display:flex;
            align-items:center;
            gap:12px;
            padding:16px;
            background:#f8fafc;
            border-radius:8px;
            border:1px solid #e2e8f0;
        }
        .sep-info-icon{
            width:40px;
            height:40px;
            background:#eef2ff;
            border-radius:8px;
            display:flex;
            align-items:center;
            justify-content:center;
            flex-shrink:0;
        }
        .sep-info-icon svg{
            width:20px;
            height:20px;
            color:#4338ca;
        }
        .sep-info-text{
            flex:1;
        }
        .sep-info-label{
            font-size:12px;
            color:#64748b;
            margin:0 0 4px;
        }
        .sep-info-value{
            font-size:14px;
            font-weight:600;
            color:#1e293b;
            margin:0;
        }

        /* Responsive */
        @media(max-width:768px){
            .sep-nav-links{display:none;}
            .sep-header-content{flex-direction:column;text-align:center;}
            .sep-stats{justify-content:center;flex-wrap:wrap;}
            .sep-name{flex-direction:column;gap:8px;}
            .sep-info-grid{grid-template-columns:1fr;}
        }
        </style>

        <!-- Navigation -->
        <nav class="sep-nav">
            <div class="sep-nav-inner">
                <a href="<?php echo esc_url(home_url()); ?>" class="sep-nav-logo">LOGO</a>
                <div class="sep-nav-links">
                    <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
                    <a href="<?php echo esc_url($url_library); ?>">Webinar Library</a>
                    <a href="<?php echo esc_url($url_myevents); ?>">My Events</a>
                </div>
                <div class="sep-nav-right">
                    <img src="<?php echo esc_url($nav_avatar); ?>" alt="Profile">
                    <span class="sep-nav-username">Hi, <?php echo esc_html($nav_display); ?></span>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="sep-nav-btn">Logout</a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="sep-wrap">
            <div class="sep-container">
                <a href="javascript:history.back()" class="sep-back">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>

                <div class="sep-card">
                    <!-- Header Section -->
                    <div class="sep-header">
                        <div class="sep-header-content">
                            <img src="<?php echo esc_url($educator_avatar); ?>" alt="<?php echo esc_attr($educator_name); ?>" class="sep-avatar">
                            <div class="sep-header-info">
                                <h1 class="sep-name">
                                    <?php echo esc_html($educator_name); ?>
                                    <?php if ($is_verified) : ?>
                                        <span class="sep-verified">
                                            <svg fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Verified
                                        </span>
                                    <?php endif; ?>
                                </h1>
                                <p class="sep-headline"><?php echo esc_html($educator_headline); ?></p>
                                
                                <div class="sep-stats">
                                    <?php if ($exp_years > 0) : ?>
                                    <div class="sep-stat">
                                        <div class="sep-stat-value"><?php echo $exp_years; ?>+</div>
                                        <div class="sep-stat-label">Years Experience</div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="sep-stat">
                                        <div class="sep-stat-value"><?php echo count($specs); ?></div>
                                        <div class="sep-stat-label">Specializations</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Section -->
                    <div class="sep-content">
                        <!-- About Section -->
                        <div class="sep-section">
                            <h2 class="sep-section-title">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                About
                            </h2>
                            <p class="sep-about"><?php echo esc_html($educator_about); ?></p>
                        </div>

                        <!-- Specializations Section -->
                        <?php if (!empty($specs)) : ?>
                        <div class="sep-section">
                            <h2 class="sep-section-title">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                                Specializations
                            </h2>
                            <div class="sep-tags">
                                <?php foreach ($specs as $spec) : ?>
                                    <span class="sep-tag"><?php echo esc_html($spec); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Additional Info -->
                        <div class="sep-section">
                            <h2 class="sep-section-title">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Professional Information
                            </h2>
                            <div class="sep-info-grid">
                                <div class="sep-info-item">
                                    <div class="sep-info-icon">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="sep-info-text">
                                        <p class="sep-info-label">Experience</p>
                                        <p class="sep-info-value"><?php echo $exp_years > 0 ? $exp_years . '+ years' : 'Not specified'; ?></p>
                                    </div>
                                </div>
                                <div class="sep-info-item">
                                    <div class="sep-info-icon">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div class="sep-info-text">
                                        <p class="sep-info-label">Status</p>
                                        <p class="sep-info-value"><?php echo $is_verified ? 'Verified Educator' : 'Educator'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }
}

new PlatformCore_StudentEducatorProfile_Flow();