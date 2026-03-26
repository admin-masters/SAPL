<?php
/*
 * ============================================================
 * 8. PEDIATRIC LANDING PAGE SHORTCODE
 * Usage: [pediatric_landing_page]
 * ============================================================
 */
add_shortcode('pediatric_landing_page2', 'platform_core_render_landing_page');

function platform_core_render_landing_page() {
    ob_start();

    $is_logged_in     = is_user_logged_in();
    $dashboard_url    = home_url();
    $button_text      = 'Dashboard';
    $custom_login_url = home_url('/login');
    $custom_reg_url   = 'https://staging-68a5-inditechsites.wpcomstaging.com/college_registration/';

    if ($is_logged_in) {
        $current_user = wp_get_current_user();
        $button_text  = $current_user->display_name;
        $roles        = (array) $current_user->roles;
        if (in_array('administrator', $roles))     $dashboard_url = admin_url();
        elseif (in_array('college_admin', $roles)) $dashboard_url = home_url('/college-classes/my-classes');
        elseif (in_array('expert', $roles))        $dashboard_url = home_url('/expert-dashboard');
        elseif (in_array('student', $roles))       $dashboard_url = home_url('/webinars');
    }

    $star_svg = '<svg style="width:20px;height:20px;fill:#FFC107;display:inline-block;" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
    ?>

    <div id="plp-root">

        <style>
            /* Force full takeover */
            html, body { margin: 0 !important; padding: 0 !important; overflow: hidden !important; height: 100% !important; }
            #wpadminbar { display: none !important; }
            #masthead, #colophon, .site-header, .site-footer,
            .elementor-location-header, .elementor-location-footer,
            #header, .top-bar, .main-navigation, .nav-primary,
            footer, header { display: none !important; }

            /* Root overlay */
            #plp-root {
                position: fixed;
                top: 0; left: 0;
                width: 100vw; height: 100vh;
                overflow-y: auto; overflow-x: hidden;
                z-index: 2147483647;
                background: #fff;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                color: #333;
                line-height: 1.6;
                box-sizing: border-box;
            }
            #plp-root *, #plp-root *::before, #plp-root *::after { box-sizing: border-box; }

            /* ---------- HEADER ---------- */
            #plp-root .plp-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 40px;
                background: #fff;
                border-bottom: 1px solid #f1f5f9;
                position: sticky;
                top: 0;
                z-index: 100;
                width: 100%;
            }
            #plp-root .plp-logo {
                font-weight: 900; font-size: 24px; color: #4f46e5;
                font-style: italic; text-decoration: none;
                display: flex; align-items: center; gap: 5px;
            }
            #plp-root .plp-nav { display: flex; gap: 40px; }
            #plp-root .plp-nav a {
                text-decoration: none; color: #64748b;
                font-weight: 500; font-size: 15px; transition: color 0.2s;
            }
            #plp-root .plp-nav a:hover,
            #plp-root .plp-nav a.active { color: #0f172a; font-weight: 600; }
            #plp-root .plp-auth-buttons { display: flex; align-items: center; gap: 20px; }
            #plp-root .plp-login-link {
                text-decoration: none; color: #64748b;
                font-weight: 600; font-size: 15px;
            }
            #plp-root .plp-join-btn {
                background: #000; color: #fff;
                padding: 10px 24px; border-radius: 6px;
                text-decoration: none; font-weight: 600;
                font-size: 14px; transition: opacity 0.2s;
                display: inline-block;
            }
            #plp-root .plp-join-btn:hover { opacity: 0.8; color: #fff; }

            /* ---------- CONTAINER ---------- */
            #plp-root .plp-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }

            /* ---------- HERO ---------- */
            #plp-root .plp-hero {
                padding: 80px 0;
                background: linear-gradient(105deg, #eff6ff 0%, #ffffff 50%);
                display: flex; align-items: center;
                min-height: 650px; width: 100%;
            }
            #plp-root .plp-hero-inner {
                display: flex; align-items: center;
                gap: 60px; width: 100%;
            }
            #plp-root .plp-hero-content { flex: 1; max-width: 600px; }
            #plp-root .plp-hero-image {
                flex: 1; display: flex; justify-content: center;
            }
            #plp-root .plp-hero h1 {
                font-size: 52px; font-weight: 800; color: #0f172a;
                line-height: 1.15; margin: 0 0 24px; letter-spacing: -0.02em;
            }
            #plp-root .plp-hero p {
                font-size: 18px; color: #475569;
                margin: 0 0 32px; max-width: 480px; line-height: 1.6;
            }
            #plp-root .plp-btn-outline {
                display: inline-block; padding: 14px 32px;
                background: transparent; border: 2px solid #0f172a;
                color: #0f172a; font-weight: 700; text-decoration: none;
                border-radius: 6px; transition: all 0.2s ease;
            }
            #plp-root .plp-btn-outline:hover { background: #0f172a; color: #fff; }
            #plp-root .plp-img-placeholder {
                width: 100%; height: auto; border-radius: 12px;
                box-shadow: 0 20px 50px -12px rgba(0,0,0,0.15);
                object-fit: contain;
            }

            /* ---------- FEATURES ---------- */
            #plp-root .plp-features { padding: 100px 0; background: #fff; width: 100%; }
            #plp-root .plp-features-grid {
                display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px;
            }
            #plp-root .plp-feature-card {
                background: #f8fafc; padding: 30px;
                border: 1px solid #e2e8f0; border-radius: 10px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
                display: flex; flex-direction: column;
                align-items: flex-start; text-align: left;
            }
            #plp-root .plp-feature-card h3 {
                font-size: 18px; font-weight: 700;
                margin: 0 0 10px; color: #0f172a;
            }
            #plp-root .plp-feature-card p {
                font-size: 14px; color: #64748b; line-height: 1.5; margin: 0;
            }
            #plp-root .plp-feature-icon {
                width: 120px; height: auto; display: block;
                margin-bottom: 20px; margin-left: 0; margin-right: auto;
            }

            /* ---------- STATS ---------- */
            #plp-root .plp-stats {
                padding: 80px 0; background: #fff;
                border-top: 1px solid #e2e8f0; width: 100%;
            }
            #plp-root .plp-stats-grid {
                display: grid; grid-template-columns: repeat(3, 1fr);
                gap: 30px; text-align: center;
            }
            #plp-root .plp-stat-card {
                padding: 40px; background: #f8fafc;
                border: 1px solid #e2e8f0; border-radius: 10px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            }
            #plp-root .plp-stat-number {
                display: block; font-size: 36px; font-weight: 800;
                color: #0f172a; margin-bottom: 8px;
            }
            #plp-root .plp-stat-label {
                font-size: 14px; color: #64748b; font-weight: 500;
            }

            /* ---------- TESTIMONIALS ---------- */
            #plp-root .plp-testimonials {
                padding: 100px 0; background: #f8fafc; width: 100%;
            }
            #plp-root .plp-section-title {
                text-align: center; font-size: 32px; font-weight: 800;
                margin: 0 0 60px; color: #0f172a;
            }
            #plp-root .plp-testi-grid {
                display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;
            }
            #plp-root .plp-testi-card {
                background: #fff; padding: 40px; border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.05); text-align: center;
            }
            #plp-root .plp-stars {
                margin-bottom: 20px; display: flex;
                justify-content: center; gap: 4px;
            }
            #plp-root .plp-quote {
                font-size: 15px; color: #334155;
                margin: 0 0 24px; line-height: 1.6;
            }
            #plp-root .plp-author {
                font-weight: 700; color: #0f172a;
                font-size: 15px; display: block; margin-bottom: 4px;
            }
            #plp-root .plp-role { font-size: 13px; color: #94a3b8; font-weight: 500; }

            /* ---------- FOOTER ---------- */
            #plp-root .plp-footer {
                background: #0f172a; color: #94a3b8;
                padding: 80px 0 30px; font-size: 14px; width: 100%;
            }
            #plp-root .plp-footer-grid {
                display: grid; grid-template-columns: 2fr 1fr 1fr 1fr;
                gap: 60px; margin-bottom: 60px;
            }
            #plp-root .plp-footer h4 {
                color: #fff; margin: 0 0 24px; font-size: 16px; font-weight: 600;
            }
            #plp-root .plp-footer ul { list-style: none; padding: 0; margin: 0; }
            #plp-root .plp-footer li { margin-bottom: 12px; }
            #plp-root .plp-footer a { color: #94a3b8; text-decoration: none; transition: color 0.2s; }
            #plp-root .plp-footer a:hover { color: #fff; }
            #plp-root .plp-copyright {
                text-align: center; padding-top: 30px;
                border-top: 1px solid #1e293b; font-size: 13px;
            }

            /* ---------- RESPONSIVE ---------- */
            @media (max-width: 900px) {
                #plp-root .plp-header { padding: 15px 20px; }
                #plp-root .plp-nav { display: none; }
                #plp-root .plp-hero-inner { flex-direction: column; text-align: center; gap: 40px; }
                #plp-root .plp-hero-content { margin: 0 auto; }
                #plp-root .plp-hero h1 { font-size: 32px; }
                #plp-root .plp-features-grid,
                #plp-root .plp-stats-grid,
                #plp-root .plp-testi-grid,
                #plp-root .plp-footer-grid { grid-template-columns: 1fr; gap: 24px; }
                #plp-root .plp-feature-icon { margin: 0 auto 20px auto; }
                #plp-root .plp-feature-card { text-align: center; align-items: center; }
            }
        </style>

        <!-- HEADER -->
        <div class="plp-header">
            <a href="<?php echo esc_url(home_url()); ?>" class="plp-logo">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                LOGO
            </a>
            <nav class="plp-nav">
                <a href="#" class="active">Home</a>
                <a href="#">About</a>
                <a href="#">Tutors</a>
            </nav>
            <div class="plp-auth-buttons">
                <?php if ($is_logged_in): ?>
                    <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="plp-login-link">Logout</a>
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="plp-join-btn"><?php echo esc_html($button_text); ?></a>
                <?php else: ?>
                    <a href="<?php echo esc_url($custom_login_url); ?>" class="plp-login-link">Login</a>
                    <a href="<?php echo esc_url($custom_reg_url); ?>" class="plp-join-btn">Register</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- HERO -->
        <section class="plp-hero">
            <div class="plp-container plp-hero-inner">
                <div class="plp-hero-content">
                    <h1>Bridging Pediatric Education Gaps Across South Asia</h1>
                    <p>Find expert educators, access top-quality content, and schedule interactive sessions for comprehensive pediatric education.</p>
                    <a href="<?php echo esc_url(home_url('/tutors')); ?>" class="plp-btn-outline">Find a Tutor</a>
                </div>
                <div class="plp-hero-image">
                    <img src="https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/2025/12/ChatGPT-Image-Dec-16-2025-02_27_54-PM.png" alt="Pediatric Scene" class="plp-img-placeholder">
                </div>
            </div>
        </section>

        <!-- FEATURES -->
        <section class="plp-features">
            <div class="plp-container plp-features-grid">
                <div class="plp-feature-card">
                    <img src="https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/2025/12/ChatGPT-Image-Dec-16-2025-04_04_53-PM.png" alt="Student Icon" class="plp-feature-icon">
                    <h3>Expert-Led Education</h3>
                    <p>Learn from leading pediatricians with years of practical experience.</p>
                </div>
                <div class="plp-feature-card">
                    <img src="https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/2025/12/ChatGPT-Image-Dec-16-2025-04_04_57-PM.png" alt="Video Icon" class="plp-feature-icon">
                    <h3>Live &amp; Recorded Sessions</h3>
                    <p>Access structured learning content at your own convenience.</p>
                </div>
                <div class="plp-feature-card">
                    <img src="https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/2025/12/ChatGPT-Image-Dec-16-2025-04_05_01-PM.png" alt="Price Icon" class="plp-feature-icon">
                    <h3>Flexible Pricing</h3>
                    <p>Choose between per-session or bundled subscription plans.</p>
                </div>
                <div class="plp-feature-card">
                    <img src="https://staging-68a5-inditechsites.wpcomstaging.com/wp-content/uploads/2025/12/ChatGPT-Image-Dec-16-2025-04_05_17-PM.png" alt="College Icon" class="plp-feature-icon">
                    <h3>For Medical Colleges</h3>
                    <p>Hire educators and host hybrid lectures for your institution.</p>
                </div>
            </div>
        </section>

        <!-- STATS -->
        <section class="plp-stats">
            <div class="plp-container plp-stats-grid">
                <div class="plp-stat-card">
                    <span class="plp-stat-number">100+</span>
                    <span class="plp-stat-label">Expert Educators</span>
                </div>
                <div class="plp-stat-card">
                    <span class="plp-stat-number">500+</span>
                    <span class="plp-stat-label">Sessions Conducted</span>
                </div>
                <div class="plp-stat-card">
                    <span class="plp-stat-number">10,000+</span>
                    <span class="plp-stat-label">Active Learners</span>
                </div>
            </div>
        </section>

        <!-- TESTIMONIALS -->
        <section class="plp-testimonials">
            <div class="plp-container">
                <h2 class="plp-section-title">What Our Users Say</h2>
                <div class="plp-testi-grid">
                    <div class="plp-testi-card">
                        <div class="plp-stars"><?php echo str_repeat($star_svg, 5); ?></div>
                        <p class="plp-quote">"The quality of education and flexibility of learning has been incredible. Highly recommended for medical students."</p>
                        <span class="plp-author">Dr. Sarah Khan</span>
                        <span class="plp-role">Pediatric Resident</span>
                    </div>
                    <div class="plp-testi-card">
                        <div class="plp-stars"><?php echo str_repeat($star_svg, 5); ?></div>
                        <p class="plp-quote">"As a medical college, we've seen significant improvement in our students' understanding of pediatrics."</p>
                        <span class="plp-author">Dr. Rajesh Patel</span>
                        <span class="plp-role">Medical College Director</span>
                    </div>
                    <div class="plp-testi-card">
                        <div class="plp-stars"><?php echo str_repeat($star_svg, 5); ?></div>
                        <p class="plp-quote">"The platform has helped me stay updated with the latest in pediatric care while managing my practice."</p>
                        <span class="plp-author">Dr. Amina Rahman</span>
                        <span class="plp-role">Practicing Pediatrician</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="plp-footer">
            <div class="plp-container">
                <div class="plp-footer-grid">
                    <div>
                        <h4 style="font-style:italic;color:#4f46e5;font-size:24px;">LOGO</h4>
                        <p>Empowering medical education across South Asia.</p>
                    </div>
                    <div>
                        <h4>Quick Links</h4>
                        <ul>
                            <li><a href="#">About Us</a></li>
                            <li><a href="#">Courses</a></li>
                            <li><a href="#">Tutors</a></li>
                            <li><a href="#">Pricing</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4>Contact</h4>
                        <ul>
                            <li><a href="#">contact@southasiacare.com</a></li>
                            <li>+91 123 456 7890</li>
                        </ul>
                    </div>
                    <div>
                        <h4>Follow Us</h4>
                        <div style="display:flex;gap:10px;">
                            <a href="#">FB</a>
                            <a href="#">TW</a>
                            <a href="#">LI</a>
                            <a href="#">IG</a>
                        </div>
                    </div>
                </div>
                <div class="plp-copyright">
                    &copy; <?php echo date('Y'); ?> SouthAsiaCare. All rights reserved.
                </div>
            </div>
        </footer>

    </div><!-- #plp-root -->

    <script>
    // Hide theme chrome immediately, no waiting for DOMContentLoaded
    (function() {
        var style = document.createElement('style');
        style.innerHTML = 'html,body{overflow:hidden!important;margin:0!important;padding:0!important;}' +
            '#wpadminbar,#masthead,#colophon,.site-header,.site-footer,.main-navigation,nav.main-navigation{display:none!important;}';
        document.head.appendChild(style);
    })();
    </script>

    <?php
    return ob_get_clean();
}