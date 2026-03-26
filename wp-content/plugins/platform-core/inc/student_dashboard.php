<?php
/**
 * ============================================================
 * STUDENT DASHBOARD SHORTCODE
 * Usage: [student_dashboard]
 * ============================================================
 */
add_shortcode('student_dashboard', 'platform_core_render_student_dashboard');

function platform_core_render_student_dashboard() {
    ob_start();

    // 1. PREPARE USER DATA
    $is_logged_in = is_user_logged_in();
    $user_name = '';
    $avatar = '';
    $login_link = wp_login_url(get_permalink()); 
    
    if ($is_logged_in) {
        $u = wp_get_current_user();
        $user_name = $u->display_name;
        $avatar = get_avatar_url($u->ID, ['size' => 96]);
    }
    ?>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Tag the body to trigger CSS theme hiding
        document.body.classList.add('sd-active-page');

        // 2. Move our dashboard to the root (Super Overlay)
        var wrapper = document.getElementById("student-dashboard-root");
        if (wrapper) {
            document.body.appendChild(wrapper);
        }
        
        // 3. Ensure scrolling
        document.documentElement.style.overflow = "auto";
        document.body.style.overflow = "auto";
    });
    </script>

    <div id="student-dashboard-root">
        <div class="sd-wrapper">
            
            <header class="sd-header">
                <a href="#" class="sd-logo">LOGO</a>
                <div class="sd-profile">
                    <?php if ($is_logged_in): ?>
                        <img src="<?php echo esc_url($avatar); ?>" alt="Profile" class="sd-avatar">
                        <span class="sd-username"><?php echo esc_html($user_name); ?></span>
                        <a href="<?php echo wp_logout_url(home_url()); ?>" style="font-size:12px; color:#666; text-decoration:none; margin-left:10px;">(Logout)</a>
                    <?php else: ?>
                        <a href="<?php echo esc_url($login_link); ?>" class="sd-login-btn">Log In</a>
                    <?php endif; ?>
                </div>
            </header>

            <div class="sd-main">
                <div class="sd-grid">
                    
                    <div class="sd-card">
                        <div class="sd-card-header">
                            <h3 class="sd-card-title">Expert Tutorials</h3>
                            <a href="#" class="view-all-blue">View All</a>
                        </div>
                        <div class="sd-card-body">
                            <div>
                                <span class="sd-inner-title">Access Expert Tutorials</span>
                                <p class="sd-desc">Book 1 on 1 /group tutorials with experts</p>
                            </div>
                            <a href="<?php echo home_url('/expert-tutorials'); ?>" class="sd-btn btn-blue">Explore</a>
                        </div>
                    </div>

                    <div class="sd-card">
                        <div class="sd-card-header">
                            <h3 class="sd-card-title">AI Interactive & CME</h3>
                            <a href="#" class="view-all-green">View All</a>
                        </div>
                        <div class="sd-card-body">
                            <div>
                                <span class="sd-inner-title">Interactive Learning</span>
                                <p class="sd-desc">AI powered case studies and simulations</p>
                            </div>
                            <a href="<?php echo home_url('/ai-interactive'); ?>" class="sd-btn btn-green">Explore</a>
                        </div>
                    </div>

                    <div class="sd-card">
                        <div class="sd-card-header">
                            <h3 class="sd-card-title">Webinars</h3>
                            <a href="#" class="view-all-purple">View All</a>
                        </div>
                        <div class="sd-card-body">
                            <div>
                                <span class="sd-inner-title">Live Sessions</span>
                                <p class="sd-desc">Access upcoming and recorded webinars</p>
                            </div>
                            <a href="<?php echo home_url('/webinars'); ?>" class="sd-btn btn-purple">Explore</a>
                        </div>
                    </div>

                </div>
            </div>

            <footer class="sd-footer">
                <div class="sd-footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#">Medical Education</a></li>
                        <li><a href="#">AI Interactive</a></li>
                        <li><a href="#">Webinars</a></li>
                    </ul>
                </div>
                <div class="sd-footer-col">
                    <h4>Contact</h4>
                    <ul>
                        <li>Email: support@medical-edu.com</li>
                        <li>Phone: +1 (555) 123-4567</li>
                    </ul>
                </div>
            </footer>

        </div>
    </div>
    <?php
    return ob_get_clean();
}