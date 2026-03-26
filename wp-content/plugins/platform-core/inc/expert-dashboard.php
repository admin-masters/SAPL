<?php
/**
 * Expert Dashboard Shortcode
 * Shortcode: [expert_dashboard]
 * 
 * Extracted from platform_core.php
 * Require this file in platform_core.php:
 *   require_once plugin_dir_path(__FILE__) . 'inc/expert-dashboard.php';
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// SHORTCODE: [expert_dashboard]
// ============================================================
add_shortcode('expert_dashboard', 'platform_core_render_expert_dashboard');
function platform_core_render_expert_dashboard() {
    if (!current_user_can('administrator') && !platform_core_user_is_expert()) {
        return '<div style="padding: 40px; text-align: center;"><p>Access denied. This page is only available to experts.</p></div>';
    }

    $current_user = wp_get_current_user();
    $user_name    = $current_user->display_name;
    $employee_id  = platform_core_get_employee_id_by_email($current_user->user_email);

    $tutorial_data = platform_core_get_tutorial_stats($employee_id);
    $webinar_data  = platform_core_get_webinar_stats($employee_id);
    $class_data    = platform_core_get_medical_class_stats($employee_id);

    ob_start();
    ?>
    <!– Inline styles scoped to the dashboard wrapper –>
    <style>
    /* =====================================================
       EXPERT MAIN DASHBOARD – matches provided UI mockup
       ===================================================== */
    #wpadminbar { display: none !important; }
    html { margin-top: 0 !important; }
    header, #masthead, .site-header, .main-header, #header,
    .elementor-location-header, .ast-main-header-wrap, #site-header,
    .fusion-header-wrapper, .header-wrap, .nav-primary,
    div[data-elementor-type="header"] { display: none !important; }
    footer.site-footer, .site-footer, #colophon, #footer,
    .footer-area, .ast-footer-overlay, .footer-widgets-area, .footer-bar,
    div[data-elementor-type="footer"], .elementor-location-footer { display: none !important; }
    .page-template-default .site-content, .site-main, #content, #page {
        margin: 0 !important; padding: 0 !important;
        max-width: 100% !important; width: 100% !important;
    }

    .edash-root {
        --font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        --ink: #111827;
        --ink2: #374151;
        --ink3: #6b7280;
        --border: #e5e7eb;
        --bg: #f9fafb;
        --surface: #ffffff;
        --navy: #000000;
        --accent: #4f46e5;
        --webinar-bg: #eff6ff;
        --webinar-clr: #2563eb;
        --tutorial-bg: #f0fdf4;
        --tutorial-clr: #16a34a;
        --class-bg: #fef3c7;
        --class-clr: #d97706;

        font-family: var(--font);
        background: var(--bg);
        min-height: 100vh;
        color: var(--ink);
    }

    /* ---- Topbar ---- */
    .edash-topbar {
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 0 32px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 100;
    }
    .edash-logo {
        font-weight: 800;
        font-size: 20px;
        color: var(--accent);
        text-decoration: none;
        letter-spacing: -0.5px;
    }
    .edash-topbar-right {
        display: flex;
        align-items: center;
        gap: 18px;
    }
    .edash-user-name {
        font-size: 14px;
        font-weight: 500;
        color: var(--ink2);
    }
    .edash-logout {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--navy);
        color: #fff;
        padding: 7px 16px;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: opacity 0.15s;
    }
    .edash-logout:hover { opacity: 0.82; color: #fff; }

    /* ---- Page body ---- */
    .edash-body {
        max-width: 1300px;
        margin: 0 auto;
        padding: 40px 28px 64px;
    }

    /* ---- Page header ---- */
    .edash-page-header {
        margin-bottom: 40px;
        padding-bottom: 28px;
        border-bottom: 1px solid var(--border);
    }
    .edash-page-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: var(--ink);
        margin: 0 0 6px;
        line-height: 1.25;
    }
    .edash-page-header p {
        font-size: 14px;
        color: var(--ink3);
        margin: 0;
    }

    /* ---- Cards grid ---- */
    .edash-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 24px;
        margin-bottom: 56px;
    }

    .edash-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 28px 24px;
        position: relative;
        overflow: hidden;
        transition: box-shadow 0.22s, transform 0.22s, border-color 0.22s;
    }
    .edash-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }
    .edash-card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        transform: translateY(-3px);
        border-color: #d1d5db;
    }
    .edash-card:hover::before { transform: scaleX(1); }

    /* Card icon */
    .edash-card-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
    }
    .edash-card-icon.webinar { background: var(--webinar-bg); color: var(--webinar-clr); }
    .edash-card-icon.tutorial { background: var(--tutorial-bg); color: var(--tutorial-clr); }
    .edash-card-icon.class { background: var(--class-bg); color: var(--class-clr); }

    .edash-card-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--ink);
        margin: 0 0 4px;
    }
    .edash-card-desc {
        font-size: 13px;
        color: var(--ink3);
        margin: 0 0 22px;
    }

    /* Stats box */
    .edash-stats {
        background: #f9fafb;
        border-radius: 8px;
        padding: 18px;
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .edash-stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .edash-stat-row.divider {
        padding-top: 14px;
        border-top: 1px solid var(--border);
    }
    .edash-stat-label {
        font-size: 14px;
        color: var(--ink3);
        font-weight: 500;
    }
    .edash-stat-value {
        font-size: 17px;
        font-weight: 700;
        color: var(--ink);
    }

    /* Card CTA button */
    .edash-card-btn {
        display: block;
        width: 100%;
        padding: 13px 20px;
        background: var(--navy);
        color: #fff;
        text-align: center;
        text-decoration: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        transition: opacity 0.15s, transform 0.15s;
    }
    .edash-card-btn:hover {
        opacity: 0.85;
        color: #fff;
        transform: translateY(-1px);
    }

    /* ---- Footer links ---- */
    .edash-footer {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 40px;
        padding: 36px 0 0;
        border-top: 1px solid var(--border);
    }
    .edash-footer h4 {
        font-size: 15px;
        font-weight: 600;
        color: var(--ink);
        margin: 0 0 14px;
    }
    .edash-footer ul {
        list-style: none;
        margin: 0; padding: 0;
    }
    .edash-footer ul li {
        margin-bottom: 9px;
        font-size: 13px;
        color: var(--ink3);
        line-height: 1.6;
    }
    .edash-footer ul li a {
        color: var(--ink3);
        text-decoration: none;
        transition: color 0.15s;
    }
    .edash-footer ul li a:hover { color: var(--ink); }

    @media (max-width: 768px) {
        .edash-body { padding: 24px 16px 48px; }
        .edash-cards { grid-template-columns: 1fr; }
        .edash-page-header h1 { font-size: 22px; }
        .edash-topbar { padding: 0 16px; }
    }
    /* Hide WordPress/Jetpack social sharing widgets that bleed below the page */
    .sharedaddy,.sd-sharing-enabled,.sd-block,.jp-relatedposts,
    .wpcnt,.post-likes-widget-placeholder,.wp-block-jetpack-likes,
    .entry-footer,.post-footer,.post-tags,.post-categories,
    .navigation,.post-navigation{display:none!important;}
    </style>

    <div class="edash-root">

        <!-- Topbar -->
        <div class="edash-topbar">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="edash-logo">
                <span style="font-style:italic;">LO</span><span style="font-style:italic;">GO</span>
            </a>
            <div class="edash-topbar-right">
                <span class="edash-user-name">Dr. <?php echo esc_html($user_name); ?></span>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="edash-logout">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                        <path d="M6 14H3.33333C2.97971 14 2.64057 13.8595 2.39052 13.6095C2.14048 13.3594 2 13.0203 2 12.6667V3.33333C2 2.97971 2.14048 2.64057 2.39052 2.39052C2.64057 2.14048 2.97971 2 3.33333 2H6M10.6667 11.3333L14 8M14 8L10.6667 4.66667M14 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Logout
                </a>
            </div>
        </div>

        <!-- Body -->
        <div class="edash-body">

            <!-- Page header -->
            <div class="edash-page-header">
                <h1>Expert Dashboard</h1>
                <p>Manage your educational activities across all platforms</p>
            </div>

            <!-- Cards -->
            <div class="edash-cards">

                <!-- Webinar Expert -->
                <div class="edash-card">
                    <div class="edash-card-icon webinar">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect x="2" y="3" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                            <path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="edash-card-title">Webinar Expert</div>
                    <div class="edash-card-desc">Manage your live sessions</div>
                    <div class="edash-stats">
                        <div class="edash-stat-row">
                            <span class="edash-stat-label">Total Webinars</span>
                            <span class="edash-stat-value"><?php echo esc_html($webinar_data['total']); ?></span>
                        </div>
                        <div class="edash-stat-row divider">
                            <span class="edash-stat-label">Next Webinar</span>
                            <span class="edash-stat-value"><?php echo esc_html($webinar_data['next_date']); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo esc_url(home_url('/expert/webinars')); ?>" class="edash-card-btn">View Webinar Dashboard</a>
                </div>

                <!-- Tutorial Expert -->
                <div class="edash-card">
                    <div class="edash-card-icon tutorial">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="edash-card-title">Tutorial Expert</div>
                    <div class="edash-card-desc">Manage your tutorial content</div>
                    <div class="edash-stats">
                        <div class="edash-stat-row">
                            <span class="edash-stat-label">Total Tutorials</span>
                            <span class="edash-stat-value"><?php echo esc_html($tutorial_data['total']); ?></span>
                        </div>
                        <div class="edash-stat-row divider">
                            <span class="edash-stat-label">Active Tutorials</span>
                            <span class="edash-stat-value"><?php echo esc_html($tutorial_data['active']); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo esc_url(home_url('/expert/tutorial')); ?>" class="edash-card-btn">View Tutorial Dashboard</a>
                </div>

                <!-- Medical Class Expert -->
                <div class="edash-card">
                    <div class="edash-card-icon class">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="edash-card-title">Medical Class Expert</div>
                    <div class="edash-card-desc">Manage your medical classes</div>
                    <div class="edash-stats">
                        <div class="edash-stat-row">
                            <span class="edash-stat-label">Total Classes</span>
                            <span class="edash-stat-value"><?php echo esc_html($class_data['total']); ?></span>
                        </div>
                        <div class="edash-stat-row divider">
                            <span class="edash-stat-label">Upcoming Classes</span>
                            <span class="edash-stat-value"><?php echo esc_html($class_data['upcoming']); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo esc_url(home_url('/expert/classes')); ?>" class="edash-card-btn">View Medical Class Dashboard</a>
                </div>

            </div><!-- /.edash-cards -->

            <!-- Footer quick links -->
            <div class="edash-footer">
                <div>
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo esc_url(home_url('/expert/webinars')); ?>">Webinar Expert</a></li>
                        <li><a href="<?php echo esc_url(home_url('/expert/tutorial')); ?>">Tutorial Expert</a></li>
                        <li><a href="<?php echo esc_url(home_url('/expert/classes')); ?>">Medical Class Expert</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Contact</h4>
                    <ul>
                        <li>Email: support@medical-edu.com</li>
                        <li>Phone: +1 (555) 123-4567</li>
                    </ul>
                </div>
            </div>

        </div><!-- /.edash-body -->
    </div><!-- /.edash-root -->
    <?php
    return ob_get_clean();
}