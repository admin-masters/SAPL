<?php
/**
 * Role Selector Page
 * Shortcode: [role_selector]
 *
 * Separated from platform-core.php into its own file.
 * All class names prefixed with "rls-" to avoid conflicts with global theme CSS.
 */

if (!defined('ABSPATH')) exit;

add_shortcode('role_selector', 'platform_core_role_selector');

function platform_core_role_selector() {

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (in_array('administrator', $current_user->roles)) {
            wp_redirect(admin_url()); exit;
        } elseif (in_array('expert', $current_user->roles)) {
            wp_redirect(home_url('/expert-dashboard')); exit;
        } elseif (in_array('college_admin', $current_user->roles)) {
            wp_redirect(home_url('/college-dashboard')); exit;
        } elseif (in_array('student', $current_user->roles)) {
            wp_redirect(home_url('/student-dashboard')); exit;
        }
    }

    ob_start();
    ?>
    <style id="rls-styles">
        .rls-page *, .rls-page *::before, .rls-page *::after { box-sizing: border-box; margin: 0; padding: 0; }

        #wpadminbar { display: none !important; }
        html { margin-top: 0 !important; }
        header, #masthead, .site-header, .main-header, #header,
        .elementor-location-header, .ast-main-header-wrap, #site-header,
        .fusion-header-wrapper, .header-wrap, .nav-primary,
        div[data-elementor-type="header"] { display: none !important; }
        footer, .site-footer, #colophon { display: none !important; }
        .site-content, .site-main, #content, #page { margin: 0 !important; padding: 0 !important; }
        body { background: #f9fafb !important; }

        .rls-page {
            min-height: 100vh;
            width: 100%;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #111827;
        }

        .rls-box {
            width: 100%;
            max-width: 780px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .rls-logo { margin-bottom: 32px; }

        .rls-title {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
            text-align: center;
            margin-bottom: 8px;
        }

        .rls-subtitle {
            font-size: 14px;
            color: #6b7280;
            text-align: center;
            margin-bottom: 40px;
        }

        .rls-cards {
            display: flex;
            gap: 20px;
            width: 100%;
            justify-content: center;
            margin-bottom: 36px;
        }

        @media (max-width: 580px) {
            .rls-cards { flex-direction: column; align-items: center; }
        }

        .rls-card {
            flex: 1;
            max-width: 220px;
            min-width: 160px;
            background: #ffffff;
            border: 1.5px solid #e5e7eb;
            border-radius: 14px;
            padding: 24px 16px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.18s;
            user-select: none;
        }

        .rls-card:hover {
            border-color: #9ca3af;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .rls-card.rls-selected {
            border-color: #374151;
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .rls-card-img {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rls-card-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .rls-card-title {
            font-size: 15px;
            font-weight: 600;
            color: #111827;
            text-align: center;
            line-height: 1.3;
        }

        .rls-card-check {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid #d1d5db;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: border-color 0.2s, background 0.2s;
        }

        .rls-card-check svg { display: none; }

        .rls-card.rls-selected .rls-card-check {
            background: #111827;
            border-color: #111827;
        }

        .rls-card.rls-selected .rls-card-check svg { display: block; }

        .rls-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 32px;
            border-radius: 9px;
            border: none;
            background: #374151;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.18s, opacity 0.18s, transform 0.15s;
        }

        .rls-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .rls-btn:not(:disabled):hover { background: #1f2937; transform: translateY(-1px); }
        .rls-btn:not(:disabled):active { transform: translateY(0); }
    </style>

    <div class="rls-page">
        <div class="rls-box">

            <div class="rls-logo">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#e91e63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 17L12 22L22 17"           stroke="#e91e63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12"           stroke="#e91e63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <h1 class="rls-title">Welcome to Educational Platform</h1>
            <p class="rls-subtitle">Select whether you are Student, Expert or Medical</p>

            <div class="rls-cards">

                <div class="rls-card" data-rls-role="student">
                    <div class="rls-card-img">
                        <img src="<?php echo esc_url(home_url('/htdocs/wp-content/uploads/students_09-scaled.png')); ?>" alt="Student">
                    </div>
                    <span class="rls-card-title">Student</span>
                    <div class="rls-card-check">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                            <path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>

                <div class="rls-card" data-rls-role="expert">
                    <div class="rls-card-img">
                        <img src="<?php echo esc_url(home_url('/htdocs/wp-content/uploads/images.png')); ?>" alt="Medical Expert">
                    </div>
                    <span class="rls-card-title">Medical Expert</span>
                    <div class="rls-card-check">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                            <path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>

                <div class="rls-card" data-rls-role="college">
                    <div class="rls-card-img">
                        <img src="<?php echo esc_url(home_url('/htdocs/wp-content/uploads/3f831ee05e5732206b080bed619678d9.png')); ?>" alt="Medical College">
                    </div>
                    <span class="rls-card-title">Medical College</span>
                    <div class="rls-card-check">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                            <path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>

            </div>

            <button class="rls-btn" id="rls-proceed-btn" disabled>
                Proceed
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
                    <path d="M5 12h14M12 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

        </div>
    </div>

    <script>
    (function () {
        var roleUrls = {
            'student': '<?php echo esc_js(home_url('/student-registration')); ?>',
            'expert':  '<?php echo esc_js(home_url('/instructor-registration')); ?>',
            'college': '<?php echo esc_js(home_url('/college_registration')); ?>'
        };
        var selectedRole = null;
        var proceedBtn   = document.getElementById('rls-proceed-btn');
        var cards        = document.querySelectorAll('.rls-card');

        cards.forEach(function (card) {
            card.addEventListener('click', function () {
                cards.forEach(function (c) { c.classList.remove('rls-selected'); });
                card.classList.add('rls-selected');
                selectedRole = card.getAttribute('data-rls-role');
                proceedBtn.disabled = false;
            });
        });

        proceedBtn.addEventListener('click', function () {
            if (selectedRole && roleUrls[selectedRole]) {
                window.location.href = roleUrls[selectedRole];
            }
        });
    })();
    </script>

    <?php
    return ob_get_clean();
}