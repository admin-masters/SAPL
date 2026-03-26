<?php
/* -------------------------------------------------------------------------
 * DASHBOARD UI: College Dashboard Shortcode (Updated)
 * Shortcode: [platform_college_dashboard_ui]
 * ------------------------------------------------------------------------- */
add_shortcode('platform_college_dashboard_ui', 'platform_core_render_college_dashboard');

function platform_core_render_college_dashboard() {

    // Redirect guests to landing page (home) instead of showing inline message
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/college-dashboard/'));
        exit;
    }

    $is_logged_in = true; // guaranteed above
    $user_id      = get_current_user_id();
    $user_name    = '';
    $avatar       = '';

    $current_user = wp_get_current_user();
    // Use first name if set, otherwise fall back to display_name, never show raw email
    $first_name = get_user_meta($current_user->ID, 'first_name', true);
    if (!empty(trim($first_name))) {
        $user_name = trim($first_name);
    } elseif (!empty($current_user->display_name) && strpos($current_user->display_name, '@') === false) {
        $user_name = $current_user->display_name;
    } else {
        // display_name is an email — use the part before @
        $user_name = explode('@', $current_user->user_email)[0];
    }
    $avatar = get_avatar_url($current_user->ID, ['size' => 96]);

    global $wpdb;
    $tbl_requests  = $wpdb->prefix . 'platform_requests';
    $tbl_contracts = $wpdb->prefix . 'platform_contracts';
    $tbl_shortlist = $wpdb->prefix . 'platform_shortlists';
    $tbl_users     = $wpdb->prefix . 'users';
    $tbl_usermeta  = $wpdb->prefix . 'usermeta';

    // ---------- Data ----------
    $upcoming_sessions   = [];
    $past_sessions       = [];
    $pending_requests    = [];
    $pending_count       = 0;
    $contracts_payments  = [];
    $contracts_count     = 0;
    $shortlisted_doctors = [];
    $billing_breakdown   = [];
    $total_spend         = 0;

    // --- Sessions: up to 2 upcoming (ASC), pad with recent past to reach 2 total ---
    $upcoming_sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT r.topic, r.proposed_start_iso, r.duration_minutes, c.class_start_iso, 'upcoming' AS session_type
         FROM $tbl_requests r
         LEFT JOIN $tbl_contracts c ON c.request_id = r.id
         WHERE r.college_user_id = %d
           AND r.status = 'booked'
           AND (COALESCE(c.class_start_iso, r.proposed_start_iso)) >= NOW()
         ORDER BY COALESCE(c.class_start_iso, r.proposed_start_iso) ASC
         LIMIT 2",
        $user_id
    ));

    $needed_past = 2 - count($upcoming_sessions);
    if ($needed_past > 0) {
        $past_sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT r.topic, r.proposed_start_iso, r.duration_minutes, c.class_start_iso, 'past' AS session_type
             FROM $tbl_requests r
             LEFT JOIN $tbl_contracts c ON c.request_id = r.id
             WHERE r.college_user_id = %d
               AND r.status = 'booked'
               AND (COALESCE(c.class_start_iso, r.proposed_start_iso)) < NOW()
             ORDER BY COALESCE(c.class_start_iso, r.proposed_start_iso) DESC
             LIMIT $needed_past",
            $user_id
        ));
    }

    // --- Pending Requests ---
    $pending_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tbl_requests
         WHERE college_user_id = %d AND status = 'requested'
         AND CAST(proposed_start_iso AS DATETIME) > CONVERT_TZ(NOW(), 'UTC', 'Asia/Kolkata')",
        $user_id
    ));
    $pending_requests_db_error   = $wpdb->last_error;
    $pending_requests_last_query = $wpdb->last_query;

    $pending_requests = $wpdb->get_results($wpdb->prepare(
        "SELECT topic, proposed_start_iso
         FROM $tbl_requests
         WHERE college_user_id = %d AND status = 'requested'
         AND CAST(proposed_start_iso AS DATETIME) > CONVERT_TZ(NOW(), 'UTC', 'Asia/Kolkata')
         ORDER BY proposed_start_iso ASC
         LIMIT 2",
        $user_id
    ));

    // --- Contracts & Payments ---
    $contracts_payments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.sign_token, c.total_amount, c.status, r.topic, c.created_at
         FROM $tbl_contracts c
         JOIN $tbl_requests r ON r.id = c.request_id
         WHERE r.college_user_id = %d
           AND c.status IN ('generated', 'awaiting_payment')
           AND CAST(r.proposed_start_iso AS DATETIME) > CONVERT_TZ(NOW(), 'UTC', 'Asia/Kolkata')
         ORDER BY c.created_at DESC
         LIMIT 2",
        $user_id
    ));

    $contracts_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tbl_contracts c
         JOIN $tbl_requests r ON r.id = c.request_id
         WHERE r.college_user_id = %d
           AND c.status IN ('generated', 'awaiting_payment')
           AND CAST(r.proposed_start_iso AS DATETIME) > CONVERT_TZ(NOW(), 'UTC', 'Asia/Kolkata')",
        $user_id
    ));

    // --- Shortlisted Doctors ---
    $shortlisted_doctors = $wpdb->get_results($wpdb->prepare(
        "SELECT
            u.display_name,
            MAX(CASE WHEN um.meta_key = '_tutor_instructor_speciality' THEN um.meta_value END) AS specialty,
            MAX(CASE WHEN um.meta_key = '_tutor_instructor_experience' THEN um.meta_value END) AS experience
         FROM $tbl_shortlist sl
         JOIN $tbl_users u ON u.ID = sl.expert_user_id
         LEFT JOIN $tbl_usermeta um ON um.user_id = sl.expert_user_id
         WHERE sl.college_user_id = %d
         GROUP BY sl.expert_user_id, u.display_name
         LIMIT 2",
        $user_id
    ));
    $shortlist_db_error   = $wpdb->last_error;
    $shortlist_last_query = $wpdb->last_query;

    // --- Billing Breakdown ---
    // Fetch up to 20 rows; first 5 shown immediately, rest revealed on "View More"
    $billing_breakdown = $wpdb->get_results($wpdb->prepare(
        "SELECT r.topic, c.total_amount, c.signed_at
         FROM $tbl_contracts c
         JOIN $tbl_requests r ON r.id = c.request_id
         WHERE c.signed_by_user_id = %d AND c.status = 'signed'
         ORDER BY c.signed_at DESC
         LIMIT 20",
        $user_id
    ));

    // Separate totals query (all signed records, no limit) for the total spend figure
    $billing_all = $wpdb->get_results($wpdb->prepare(
        "SELECT c.total_amount
         FROM $tbl_contracts c
         JOIN $tbl_requests r ON r.id = c.request_id
         WHERE c.signed_by_user_id = %d AND c.status = 'signed'",
        $user_id
    ));

    $total_spend = array_sum(array_column($billing_all, 'total_amount'));

    // --- URLs ---
    $url_dashboard   = get_permalink();
    $url_find        = site_url('/find_educators');
    $url_sessions    = site_url('/college-sessions');
    $url_billing     = site_url('/college/billing');
    $url_contracts   = site_url('/contracts-sessions');
    $url_shortlisted = site_url('/shortlisted-educators');
    $login_link      = home_url('/');
    

    ob_start();
    ?>

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* -- Variables ----------------------------------------- */
        .cdb-wrap {
            --c-bg:        #f0f2f7;
            --c-surface:   #ffffff;
            --c-border:    #e4e7ef;
            --c-ink:       #111827;
            --c-ink2:      #6b7280;
            --c-ink3:      #9ca3af;
            --c-accent:    #4338ca;
            --c-accent-lt: #eef2ff;
            --c-orange:    #ea580c;
            --c-orange-lt: #fff4ed;
            --c-green:     #16a34a;
            --c-green-lt:  #f0fdf4;
            --c-amber:     #d97706;
            --c-amber-lt:  #fffbeb;
            --c-red:       #dc2626;
            --c-red-lt:    #fef2f2;
            --c-navy:      #0f172a;
            --r-card:      14px;
            --shadow-card: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
            --shadow-hover: 0 4px 12px rgba(0,0,0,.1), 0 12px 32px rgba(0,0,0,.06);
            --font: 'Outfit', 'Segoe UI', sans-serif;
            --font-mono: 'DM Mono', monospace;
        }

        /* -- Reset --------------------------------------------- */
        .cdb-wrap *, .cdb-wrap *::before, .cdb-wrap *::after { box-sizing: border-box; margin: 0; padding: 0; }
        .cdb-wrap {
            font-family: var(--font);
            background: var(--c-bg);
            min-height: 100vh;
            color: var(--c-ink);
        }

        /* -- Navbar ------------------------------------------- */
        .cdb-nav {
            position: sticky; top: 0; z-index: 200;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--c-border);
            padding: 0 36px;
            height: 58px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 1px 0 var(--c-border), 0 2px 12px rgba(0,0,0,.04);
        }
        .cdb-nav-logo {
            font-size: 20px; font-weight: 800; color: var(--c-accent);
            letter-spacing: -0.5px; text-decoration: none; line-height: 1;
        }
        .cdb-nav-links { display: flex; gap: 2px; }
        .cdb-nav-links a {
            padding: 7px 16px; border-radius: 8px; font-size: 14px; font-weight: 500;
            color: var(--c-ink2); text-decoration: none;
            transition: background .18s, color .18s;
            letter-spacing: -.1px;
        }
        .cdb-nav-links a:hover { background: var(--c-accent-lt); color: var(--c-accent); }
        .cdb-nav-links a.active {
            background: var(--c-accent-lt); color: var(--c-accent); font-weight: 600;
        }
        .cdb-nav-right { display: flex; align-items: center; gap: 14px; }
        .cdb-nav-avatar {
            width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
            border: 2px solid var(--c-border); box-shadow: 0 0 0 2px var(--c-accent-lt);
        }
        .cdb-username { font-size: 13px; font-weight: 600; color: var(--c-ink); }
        .cdb-nav-btn {
            padding: 7px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
            background: var(--c-navy); color: #fff; text-decoration: none;
            transition: opacity .15s, transform .15s;
            letter-spacing: -.1px;
        }
        .cdb-nav-btn:hover { opacity: .88; transform: translateY(-1px); }

        /* -- Body / layout ------------------------------------- */
        .cdb-body { max-width: 1240px; margin: 0 auto; padding: 28px 28px 48px; }

        /* -- Welcome banner ----------------------------------- */
        .cdb-welcome {
            margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .cdb-welcome-text h2 {
            font-size: 22px; font-weight: 800; color: var(--c-ink); letter-spacing: -.4px;
        }
        .cdb-welcome-text p { font-size: 13px; color: var(--c-ink3); margin-top: 3px; }
        .cdb-welcome-date {
            font-size: 12px; font-weight: 500; color: var(--c-ink3);
            background: var(--c-surface); border: 1px solid var(--c-border);
            padding: 6px 14px; border-radius: 20px;
            font-family: var(--font-mono);
        }

        /* -- Grid --------------------------------------------- */
        .cdb-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        /* -- Card base ---------------------------------------- */
        .cdb-card {
            background: var(--c-surface);
            border-radius: var(--r-card);
            border: 1px solid var(--c-border);
            box-shadow: var(--shadow-card);
            display: flex; flex-direction: column;
            transition: box-shadow .22s, transform .22s;
            overflow: hidden;
        }
        .cdb-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        /* Card header */
        .cdb-card-head {
            padding: 15px 20px 12px;
            border-bottom: 1px solid var(--c-border);
            display: flex; align-items: center; justify-content: space-between;
            background: linear-gradient(to bottom, #fafbff, #fff);
        }
        .cdb-card-head h3 {
            font-size: 11px; font-weight: 700; color: var(--c-ink2);
            letter-spacing: .8px; text-transform: uppercase;
        }

        /* Card body */
        .cdb-card-body {
            padding: 18px 20px 18px;
            flex: 1; display: flex; flex-direction: column; gap: 10px;
        }

        /* -- Typography helpers -------------------------------- */
        .cdb-empty { font-size: 13px; color: var(--c-ink3); font-style: italic; }
        .cdb-label { font-size: 12px; color: var(--c-ink3); font-weight: 500; letter-spacing: .1px; }

        .cdb-big-num {
            font-size: 44px; font-weight: 800; color: var(--c-ink);
            line-height: 1; letter-spacing: -2px; font-family: var(--font);
        }
        .cdb-big-num.warn { color: var(--c-orange); }
        .cdb-big-num.ok   { color: var(--c-green); }

        /* -- View-all link ------------------------------------- */
        .cdb-view-btn {
            display: inline-flex; align-items: center; gap: 4px;
            margin-top: auto; padding-top: 12px;
            font-size: 12px; font-weight: 600; color: var(--c-accent);
            text-decoration: none; outline: none; background: none; border: none;
            transition: gap .15s, opacity .15s;
        }
        .cdb-view-btn:hover { opacity: .75; gap: 7px; }
        .cdb-view-btn:focus, .cdb-view-btn:active, .cdb-view-btn:focus-visible {
            outline: none !important; box-shadow: none !important;
        }

        /* -- Primary button ----------------------------------- */
        .cdb-btn-primary {
            display: inline-block; padding: 10px 20px; border-radius: 9px;
            background: var(--c-navy); color: #fff;
            font-size: 13px; font-weight: 600; text-decoration: none;
            transition: opacity .15s, transform .15s; letter-spacing: -.1px;
        }
        .cdb-btn-primary:hover { opacity: .88; transform: translateY(-1px); }

        /* -- Session list -------------------------------------- */
        .cdb-session-list { list-style: none; display: flex; flex-direction: column; gap: 9px; }
        .cdb-session-list li {
            display: flex; justify-content: space-between; align-items: flex-start;
            background: var(--c-bg); border-radius: 10px; padding: 11px 14px;
            border: 1px solid var(--c-border);
            transition: background .15s;
        }
        .cdb-session-list li:hover { background: var(--c-accent-lt); border-color: #c7d2fe; }
        .cdb-s-topic { font-size: 13px; font-weight: 600; color: var(--c-ink); }
        .cdb-s-date  { font-size: 11px; color: var(--c-ink3); margin-top: 3px; font-family: var(--font-mono); }

        .cdb-badge {
            font-size: 10px; font-weight: 700; padding: 3px 8px;
            border-radius: 20px; white-space: nowrap; letter-spacing: .3px; text-transform: uppercase;
        }
        .cdb-badge-past     { background: var(--c-accent-lt); color: var(--c-accent); }
        .cdb-badge-upcoming { background: var(--c-green-lt);  color: var(--c-green); }

        /* -- Pending request rows ------------------------------ */
        .cdb-req-list { list-style: none; display: flex; flex-direction: column; gap: 7px; }
        .cdb-req-list li {
            display: flex; justify-content: space-between; align-items: center;
            background: var(--c-orange-lt);
            border-left: 3px solid var(--c-orange);
            border-radius: 0 9px 9px 0; padding: 9px 12px;
        }
        .cdb-req-topic { font-size: 12px; font-weight: 600; color: var(--c-ink); }
        .cdb-req-date  { font-size: 11px; color: var(--c-ink3); font-family: var(--font-mono); }

        /* -- Contract rows ------------------------------------- */
        .cdb-contract-list { list-style: none; display: flex; flex-direction: column; gap: 7px; }
        .cdb-contract-list li {
            display: flex; justify-content: space-between; align-items: center;
            background: var(--c-amber-lt);
            border-left: 3px solid var(--c-amber);
            border-radius: 0 9px 9px 0; padding: 9px 12px;
        }
        .cdb-c-topic  { font-size: 12px; font-weight: 600; color: var(--c-ink); }
        .cdb-c-amount { font-size: 11px; color: var(--c-ink2); margin-top: 2px; font-family: var(--font-mono); }
        .cdb-c-action {
            font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 20px;
            text-decoration: none; white-space: nowrap; letter-spacing: .3px; text-transform: uppercase;
            transition: opacity .15s;
        }
        .cdb-c-action:hover { opacity: .8; }
        .cdb-c-action.sig { background: #fee2e2; color: #b91c1c; }
        .cdb-c-action.pay { background: #fef9c3; color: #92400e; }

        /* -- Doctor cards -------------------------------------- */
        .cdb-doc-list { display: flex; flex-direction: column; gap: 10px; }
        .cdb-doc-item {
            display: flex; align-items: center; gap: 13px;
            background: var(--c-bg); border-radius: 10px; padding: 11px 14px;
            border: 1px solid var(--c-border);
            transition: background .15s;
        }
        .cdb-doc-item:hover { background: var(--c-accent-lt); border-color: #c7d2fe; }
        .cdb-doc-avatar {
            width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 800; color: #fff;
            box-shadow: 0 2px 8px rgba(99,102,241,.35);
        }
        .cdb-doc-name { font-size: 13px; font-weight: 700; color: var(--c-ink); }
        .cdb-doc-spec { font-size: 11px; color: var(--c-ink2); margin-top: 2px; }
        .cdb-doc-exp  {
            font-size: 10px; font-weight: 600; color: var(--c-accent);
            background: var(--c-accent-lt); padding: 2px 7px;
            border-radius: 20px; display: inline-block; margin-top: 4px;
            font-family: var(--font-mono);
        }

        /* -- Billing ------------------------------------------- */
        .cdb-billing-breakdown { display: flex; flex-direction: column; gap: 0; }
        .cdb-bill-row {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 12px; color: var(--c-ink2);
            padding: 7px 0;
            border-bottom: 1px dashed var(--c-border);
        }
        .cdb-bill-row:last-child { border-bottom: none; }
        .cdb-bill-row.cdb-bill-extra {
            display: none; /* hidden until "View More" is clicked */
        }
        .cdb-bill-row.cdb-bill-extra.visible {
            display: flex;
        }
        .cdb-bill-topic {
            max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            font-weight: 500;
        }
        .cdb-bill-amt { font-family: var(--font-mono); font-size: 12px; color: var(--c-ink); font-weight: 500; }
        .cdb-bill-total {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 12px; padding: 12px 14px;
            background: var(--c-navy); border-radius: 10px;
        }
        .cdb-bill-total-label { font-size: 12px; font-weight: 700; color: rgba(255,255,255,.7); }
        .cdb-bill-total-amt   {
            font-size: 18px; font-weight: 800; color: #fff;
            font-family: var(--font-mono); letter-spacing: -.5px;
        }
        .cdb-razorpay-tag {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 10px; color: var(--c-ink3); font-weight: 500;
            background: var(--c-bg); border: 1px solid var(--c-border);
            padding: 4px 10px; border-radius: 20px; margin-top: 2px;
        }

        /* -- Billing view-more button -------------------------- */
        .cdb-bill-toggle {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 11px; font-weight: 600; color: var(--c-accent);
            background: var(--c-accent-lt); border: 1px solid #c7d2fe;
            border-radius: 20px; padding: 4px 12px; margin-top: 6px;
            cursor: pointer; transition: background .15s, opacity .15s;
            align-self: flex-start;
        }
        .cdb-bill-toggle:hover { background: #e0e7ff; }
        .cdb-bill-toggle-icon {
            font-size: 13px; line-height: 1;
            display: inline-block;
            transition: transform .2s;
        }

        /* -- Find educator card ------------------------------- */
        .cdb-find-card-body {
            background: linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%);
            border-radius: 10px; padding: 20px; text-align: center;
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 14px;
        }
        .cdb-find-icon {
            width: 48px; height: 48px; border-radius: 50%;
            background: var(--c-accent); display: flex; align-items: center;
            justify-content: center; box-shadow: 0 4px 14px rgba(67,56,202,.35);
        }
        .cdb-find-icon svg { width: 22px; height: 22px; color: #fff; }
        .cdb-find-text { font-size: 13px; color: var(--c-ink2); line-height: 1.5; }

        /* -- Stat layout (count + label) ----------------------- */
        .cdb-stat-block { display: flex; align-items: flex-end; gap: 8px; }
        .cdb-stat-sub   { font-size: 12px; color: var(--c-ink3); padding-bottom: 6px; }

        /* -- Footer ------------------------------------------- */
        .cdb-footer {
            background: var(--c-navy); color: #94a3b8;
            padding: 32px 36px 18px;
            margin-top: 12px;
        }
        .cdb-footer-grid {
            max-width: 1240px; margin: 0 auto;
            display: grid; grid-template-columns: 2.2fr 1fr 1.4fr 1fr; gap: 28px;
        }
        .cdb-footer-logo {
            font-size: 18px; font-weight: 800; color: #818cf8;
            text-decoration: none; display: block; margin-bottom: 8px;
            letter-spacing: -.3px;
        }
        .cdb-footer p  { font-size: 12px; line-height: 1.7; color: #64748b; }
        .cdb-footer h4 {
            font-size: 10px; font-weight: 700; color: #e2e8f0;
            margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;
        }
        .cdb-footer ul { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .cdb-footer ul li a {
            font-size: 12px; color: #64748b; text-decoration: none;
            transition: color .15s;
        }
        .cdb-footer ul li a:hover { color: #e2e8f0; }
        .cdb-footer-contact-item {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; color: #64748b;
        }
        .cdb-footer-contact-item svg { width: 13px; height: 13px; flex-shrink: 0; opacity: .6; }
        .cdb-footer-social { display: flex; gap: 10px; margin-top: 6px; }
        .cdb-footer-social a {
            width: 30px; height: 30px; border-radius: 8px;
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08);
            display: flex; align-items: center; justify-content: center;
            color: #64748b; transition: background .15s, color .15s;
        }
        .cdb-footer-social a:hover { background: rgba(255,255,255,.12); color: #e2e8f0; }
        .cdb-footer-divider {
            max-width: 1240px; margin: 20px auto 0;
            border: none; border-top: 1px solid rgba(255,255,255,.06);
        }
        .cdb-footer-bottom {
            max-width: 1240px; margin: 12px auto 0;
            font-size: 11px; text-align: center; color: #334155;
        }

        /* -- Staggered card entrance animation ----------------- */
        @keyframes cdb-fadeup {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .cdb-grid .cdb-card {
            animation: cdb-fadeup .4s ease both;
        }
        .cdb-grid .cdb-card:nth-child(1) { animation-delay: .05s; }
        .cdb-grid .cdb-card:nth-child(2) { animation-delay: .10s; }
        .cdb-grid .cdb-card:nth-child(3) { animation-delay: .15s; }
        .cdb-grid .cdb-card:nth-child(4) { animation-delay: .20s; }
        .cdb-grid .cdb-card:nth-child(5) { animation-delay: .25s; }
        .cdb-grid .cdb-card:nth-child(6) { animation-delay: .30s; }

        /* -- Responsive --------------------------------------- */
        @media(max-width: 960px) {
            .cdb-grid { grid-template-columns: 1fr 1fr; }
            .cdb-footer-grid { grid-template-columns: 1fr 1fr; gap: 20px; }
        }
        @media(max-width: 600px) {
            .cdb-grid { grid-template-columns: 1fr; }
            .cdb-nav-links { display: none; }
            .cdb-body { padding: 18px 16px 36px; }
            .cdb-welcome { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
    </style>

    <div class="cdb-wrap">

        <!-- -- NAVBAR -- -->
        <nav class="cdb-nav">
            <a href="<?php echo home_url(); ?>" class="cdb-nav-logo">LOGO</a>
            <div class="cdb-nav-links">
                <a href="<?php echo esc_url($url_dashboard); ?>" class="active">Dashboard</a>
                <a href="<?php echo esc_url($url_find); ?>">Find Educators</a>
                <a href="<?php echo esc_url($url_sessions); ?>">Sessions</a>
		<a href="<?php echo esc_url($url_contracts); ?>">Contracts</a>
		<a href="<?php echo esc_url($url_shortlisted); ?>">Shortlisted</a>
            </div>
            <div class="cdb-nav-right">
                <img src="<?php echo esc_url($avatar); ?>" alt="Profile" class="cdb-nav-avatar">
                <span class="cdb-username">Hi, <?php echo esc_html($user_name); ?></span>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="cdb-nav-btn">Logout</a>
            </div>
        </nav>

        <!-- -- BODY -- -->
        <div class="cdb-body">

            <?php if (current_user_can('administrator')): ?>
            <!-- --- ADMIN DEBUG --- (remove this block once tables are confirmed) -->
            <details style="background:#1a1a2e;color:#7ee787;font-family:monospace;font-size:11px;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
                <summary style="cursor:pointer;color:#79c0ff;font-weight:700;font-size:12px;">&#128295; DB Debug (admin only — remove after confirming tables)</summary>
                <br>
                <?php
                $all_tables = $wpdb->get_col("SHOW TABLES");
                $platform_tables = array_filter($all_tables, fn($t) => strpos($t, 'platform') !== false);
                echo '<strong>Platform tables found:</strong><br>';
                foreach ($platform_tables as $pt) {
                    echo '&nbsp;&nbsp;&#9658; ' . esc_html($pt) . '<br>';
                    $cols = $wpdb->get_results("DESCRIBE `$pt`");
                    foreach ($cols as $col) {
                        echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#ffa657">' . esc_html($col->Field) . '</span> <span style="color:#aaa">(' . esc_html($col->Type) . ')</span><br>';
                    }
                    echo '<br>';
                }
                echo '<strong>Current user_id:</strong> ' . intval($user_id) . '<br>';
                $sample_r = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}platform_requests LIMIT 2");
                if ($sample_r) {
                    echo '<strong>Sample platform_requests rows:</strong><br>';
                    foreach ($sample_r as $row) {
                        echo '&nbsp;&nbsp;' . esc_html(print_r((array)$row, true)) . '<br>';
                    }
                }
                ?>
            </details>
            <?php endif; ?>

            <div class="cdb-welcome">
                <div class="cdb-welcome-text">
                    <h2>Welcome back, <?php echo esc_html($user_name); ?>!</h2>
                    <p>Here's your college activity at a glance.</p>
                </div>
                <div class="cdb-welcome-date"><?php echo date('D, d M Y'); ?></div>
            </div>

            <div class="cdb-grid">

                <!-- 1. Past & Upcoming Sessions -->
                <div class="cdb-card">
                    <div class="cdb-card-head"><h3>Past &amp; Upcoming Sessions</h3></div>
                    <div class="cdb-card-body">
                        <?php
                        $display_sessions = array_merge($upcoming_sessions, $past_sessions ?? []);
                        ?>
                        <?php if (!empty($display_sessions)): ?>
                            <ul class="cdb-session-list">
                                <?php foreach ($display_sessions as $s):
                                    $date = $s->class_start_iso ?: $s->proposed_start_iso;
                                    $display_date = date('M d, H:i', strtotime($date));
                                    $is_up = ($s->session_type === 'upcoming');
                                ?>
                                    <li>
                                        <div>
                                            <div class="cdb-s-topic"><?php echo esc_html($s->topic); ?></div>
                                            <div class="cdb-s-date"><?php echo esc_html($display_date); ?></div>
                                        </div>
                                        <span class="cdb-badge <?php echo $is_up ? 'cdb-badge-upcoming' : 'cdb-badge-past'; ?>">
                                            <?php echo $is_up ? 'Upcoming' : 'Past'; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="cdb-empty">No sessions found.</p>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($url_sessions); ?>" class="cdb-view-btn">View All Sessions &rarr;</a>
                    </div>
                </div>

                <!-- 2. Find an Educator -->
                <div class="cdb-card">
                    <div class="cdb-card-head"><h3>Find an Educator</h3></div>
                    <div class="cdb-card-body" style="padding:14px;">
                        <div class="cdb-find-card-body">
                            <div class="cdb-find-icon">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35"/></svg>
                            </div>
                            <p class="cdb-find-text">Search for educators qualified in medical curriculum.</p>
                            <a href="<?php echo esc_url($url_find); ?>" class="cdb-btn-primary">Search Educators</a>
                        </div>
                    </div>
                </div>

                <!-- 3. Pending Requests -->
                <div class="cdb-card">
                    <div class="cdb-card-head"><h3>Pending Requests</h3></div>
                    <div class="cdb-card-body">
                        <?php if (!empty($pending_requests_db_error)): ?>
                            <div style="background:#fff0f0;border-left:3px solid #e53e3e;border-radius:4px;padding:8px 10px;font-size:11px;font-family:monospace;color:#c53030;margin-bottom:8px;">
                                <strong>DB ERROR:</strong> <?php echo esc_html($pending_requests_db_error); ?><br>
                                <strong>Query:</strong> <?php echo esc_html($pending_requests_last_query); ?>
                            </div>
                        <?php endif; ?>
                        <div class="cdb-big-num <?php echo $pending_count > 0 ? 'warn' : ''; ?>"><?php echo intval($pending_count); ?></div>
                        <p class="cdb-label">waiting for response</p>
                        <?php if (!empty($pending_requests)): ?>
                            <ul class="cdb-req-list" style="margin-top:6px;">
                                <?php foreach ($pending_requests as $r): ?>
                                    <li>
                                        <span class="cdb-req-topic"><?php echo esc_html($r->topic); ?></span>
                                        <span class="cdb-req-date"><?php echo date('M d', strtotime($r->proposed_start_iso)); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($url_sessions); ?>" class="cdb-view-btn">View All &rarr;</a>
                    </div>
                </div>

                <!-- 4. Billing Overview -->
                <div class="cdb-card">
                    <div class="cdb-card-head"><h3>Billing Overview</h3></div>
                    <div class="cdb-card-body">
                        <?php if (!empty($billing_breakdown)): ?>
                            <div class="cdb-billing-breakdown" id="cdb-bill-list">
                                <?php foreach ($billing_breakdown as $idx => $b): ?>
                                    <div class="cdb-bill-row<?php echo $idx >= 5 ? ' cdb-bill-extra' : ''; ?>">
                                        <span class="cdb-bill-topic"><?php echo esc_html($b->topic); ?></span>
                                        <span class="cdb-bill-amt"><?php echo wc_price($b->total_amount); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($billing_breakdown) > 5): ?>
                                <button type="button" class="cdb-bill-toggle" id="cdb-bill-toggle-btn">
                                    <span class="cdb-bill-toggle-icon">&#8964;</span>
                                    <span id="cdb-bill-toggle-label">View More</span>
                                </button>
                                <script>
                                (function(){
                                    var btn    = document.getElementById('cdb-bill-toggle-btn');
                                    var label  = document.getElementById('cdb-bill-toggle-label');
                                    var icon   = btn.querySelector('.cdb-bill-toggle-icon');
                                    var extras = document.querySelectorAll('#cdb-bill-list .cdb-bill-extra');
                                    var open   = false;

                                    btn.addEventListener('click', function(){
                                        open = !open;
                                        extras.forEach(function(el){ el.classList.toggle('visible', open); });
                                        label.textContent = open ? 'View Less' : 'View More';
                                        icon.style.transform = open ? 'rotate(180deg)' : '';
                                    });
                                })();
                                </script>
                            <?php endif; ?>
                            <div class="cdb-bill-total">
                                <span class="cdb-bill-total-label">Total Spend</span>
                                <span class="cdb-bill-total-amt"><?php echo wc_price($total_spend ?: 0); ?></span>
                            </div>
                        <?php else: ?>
                            <p class="cdb-empty">No billing records yet.</p>
                        <?php endif; ?>
                        <span class="cdb-razorpay-tag">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" width="11" height="11"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            Razorpay
                        </span>
                    </div>
                </div>

                <!-- 5. Contracts & Payments -->
                <div class="cdb-card">
                    <div class="cdb-card-head"><h3>Contracts &amp; Payments</h3></div>
                    <div class="cdb-card-body">
                        <div class="cdb-big-num <?php echo $contracts_count > 0 ? 'warn' : ''; ?>"><?php echo intval($contracts_count); ?></div>
                        <p class="cdb-label">awaiting signature / payment</p>
                        <?php if (!empty($contracts_payments)): ?>
                            <ul class="cdb-contract-list" style="margin-top:6px;">
                                <?php foreach ($contracts_payments as $c):
                                    $status_label = ($c->status === 'generated') ? 'Sign Required' : 'Pay Now';
                                    $status_class = ($c->status === 'generated') ? 'sig' : 'pay';
                                ?>
                                    <li>
                                        <div>
                                            <div class="cdb-c-topic"><?php echo esc_html($c->topic); ?></div>
                                            <div class="cdb-c-amount"><?php echo wc_price($c->total_amount); ?></div>
                                        </div>
                                        <a href="<?php echo esc_url(add_query_arg(['pc_contract' => $c->sign_token], $url_contracts)); ?>"
                                           class="cdb-c-action <?php echo $status_class; ?>"><?php echo $status_label; ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="cdb-empty" style="margin-top:6px;">All clear! No pending actions.</p>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($url_contracts); ?>" class="cdb-view-btn">View All &rarr;</a>
                    </div>
                </div>

                <!-- 6. Shortlisted Doctors -->
                <div class="cdb-card">
                    <div class="cdb-card-head"><h3>Shortlisted Doctors</h3></div>
                    <div class="cdb-card-body">
                        <?php if (!empty($shortlist_db_error)): ?>
                            <div style="background:#fff0f0;border-left:3px solid #e53e3e;border-radius:4px;padding:8px 10px;font-size:11px;font-family:monospace;color:#c53030;margin-bottom:8px;">
                                <strong>DB ERROR:</strong> <?php echo esc_html($shortlist_db_error); ?><br>
                                <strong>Query:</strong> <?php echo esc_html($shortlist_last_query); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($shortlisted_doctors)): ?>
                            <div class="cdb-doc-list">
                                <?php foreach ($shortlisted_doctors as $doc):
                                    $initials  = strtoupper(substr($doc->display_name, 0, 1));
                                    $specialty = preg_replace('/[^\x20-\x7E]/', '', $doc->specialty ?: '');
                                    $specialty = trim($specialty, " ,\t\n\r");
                                    $specialty = $specialty ?: 'General';
                                    $exp_years = intval($doc->experience);
                                ?>
                                    <div class="cdb-doc-item">
                                        <div class="cdb-doc-avatar"><?php echo esc_html($initials); ?></div>
                                        <div>
                                            <div class="cdb-doc-name"><?php echo esc_html($doc->display_name); ?></div>
                                            <div class="cdb-doc-spec"><?php echo esc_html($specialty); ?></div>
                                            <?php if ($exp_years > 0): ?>
                                                <span class="cdb-doc-exp"><?php echo $exp_years; ?> yr exp</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="cdb-empty">No doctors shortlisted yet.</p>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($url_shortlisted); ?>" class="cdb-view-btn">View All &rarr;</a>
                    </div>
                </div>

            </div><!-- /.cdb-grid -->

        </div><!-- /.cdb-body -->

        <!-- -- FOOTER (compact) -- -->
        <footer class="cdb-footer">
            <div class="cdb-footer-grid">
                <div>
                    <a href="<?php echo home_url(); ?>" class="cdb-footer-logo">LOGO</a>
                    <p>Empowering medical education<br>across South Asia.</p>
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
                        <li class="cdb-footer-contact-item">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            contact@southasiacare.com
                        </li>
                        <li class="cdb-footer-contact-item" style="margin-top:5px;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            +91 123 456 7890
                        </li>
                    </ul>
                </div>
                <div>
                    <h4>Follow Us</h4>
                    <div class="cdb-footer-social">
                        <a href="#"><svg fill="currentColor" viewBox="0 0 24 24" width="16" height="16"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
                        <a href="#"><svg fill="currentColor" viewBox="0 0 24 24" width="16" height="16"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg></a>
                        <a href="#"><svg fill="currentColor" viewBox="0 0 24 24" width="16" height="16"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg></a>
                        <a href="#"><svg fill="currentColor" viewBox="0 0 24 24" width="16" height="16"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path fill="#0f0f1e" d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke="#0f0f1e" stroke-width="1.5"/></svg></a>
                    </div>
                </div>
            </div>
            <div class="cdb-footer-bottom">&copy; <?php echo date('Y'); ?> SouthAsiaCare. All rights reserved.</div>
        </footer>

    </div><!-- /.cdb-wrap -->

    <?php
    return ob_get_clean();
}