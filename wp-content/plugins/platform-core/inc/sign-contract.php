<?php
/**
 * Sign Contract Page
 * Shortcode: [platform_sign_contract]
 * URL: /sign-contract?pc_contract=TOKEN
 */

if (!defined('ABSPATH')) exit;

add_shortcode('platform_sign_contract', 'pcore_sc_sign_contract_page');

if (!function_exists('pcore_pdf_path_to_url')) {
    function pcore_pdf_path_to_url(string $pdf_path): string {
        if (empty($pdf_path)) return '';
        if (filter_var($pdf_path, FILTER_VALIDATE_URL)) return $pdf_path;
        $uploads  = wp_upload_dir();
        $base_dir = $uploads['basedir'];
        $base_url = $uploads['baseurl'];
        $real_pdf  = realpath($pdf_path) ?: $pdf_path;
        $real_base = realpath($base_dir) ?: rtrim($base_dir, '/');
        if (strpos($real_pdf, $real_base) === 0) {
            return rtrim($base_url, '/') . '/' . ltrim(substr($real_pdf, strlen($real_base)), '/\\');
        }
        $stripped = str_replace([rtrim($base_dir, '/') . '/', rtrim($base_dir, '/')], '', $pdf_path);
        if ($stripped !== $pdf_path) {
            return rtrim($base_url, '/') . '/' . ltrim($stripped, '/');
        }
        return '';
    }
}

function pcore_sc_sign_contract_page() {

    /* -- Auth guard: redirect guests to landing page -- */
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/college-dashboard/'));
        exit;
    }

    global $wpdb;

    $tbl_contracts = $wpdb->prefix . 'platform_contracts';
    $tbl_requests  = $wpdb->prefix . 'platform_requests';

    /* -- Get token from URL -- */
    $token = sanitize_text_field($_GET['pc_contract'] ?? '');

    if (empty($token)) {
        return '<div style="padding:60px;text-align:center;font-family:sans-serif;color:#64748b;">No contract token provided.</div>';
    }

    /* -- Load contract -- */
    $contract = $wpdb->get_row($wpdb->prepare(
        "SELECT
            c.id                  AS id,
            c.request_id          AS contract_request_id,
            c.status              AS status,
            c.total_amount        AS total_amount,
            c.class_start_iso     AS class_start_iso,
            c.duration_minutes    AS duration_minutes,
            c.sign_token          AS sign_token,
            c.sign_token_expires  AS sign_token_expires,
            c.signed_at           AS signed_at,
            c.signed_by_user_id   AS signed_by_user_id,
            c.signed_name         AS signed_name,
            c.signed_ip           AS signed_ip,
            c.pdf_path            AS pdf_path,
            c.order_id            AS order_id,
            r.id                  AS request_id,
            r.topic               AS topic,
            r.expert_user_id      AS expert_user_id,
            r.college_user_id     AS college_user_id,
            r.proposed_start_iso  AS proposed_start_iso,
            r.duration_minutes    AS req_duration,
            r.status              AS request_status
         FROM {$tbl_contracts} c
         JOIN {$tbl_requests}  r ON r.id = c.request_id
         WHERE c.sign_token = %s
         LIMIT 1",
        $token
    ));

    if (!$contract) {
        return '<div style="padding:60px;text-align:center;font-family:sans-serif;color:#64748b;">Invalid or expired contract link.</div>';
    }

    /* -- Ownership check -- */
    if ((int)$contract->college_user_id !== get_current_user_id() && !current_user_can('manage_options')) {
        return '<div style="padding:60px;text-align:center;font-family:sans-serif;color:#dc2626;">Access denied. This contract is not assigned to your account.</div>';
    }

    /* -- Derived data -- */
    $expert      = get_userdata((int)$contract->expert_user_id);
    $expert_name = $expert ? $expert->display_name : 'Expert';
    $speciality  = $expert ? get_user_meta($expert->ID, '_tutor_instructor_speciality', true) : '';
    $experience  = $expert ? (int)get_user_meta($expert->ID, '_tutor_instructor_experience', true) : 0;
    $avatar_url  = $expert ? get_avatar_url($expert->ID, ['size' => 96]) : '';

    $start_raw   = !empty($contract->class_start_iso) ? $contract->class_start_iso : $contract->proposed_start_iso;
    $duration    = (int)($contract->duration_minutes ?: $contract->req_duration ?: 60);
    $session_fee = (float)$contract->total_amount;
    $fmt_date    = 'TBD';
    $fmt_time    = 'TBD';

    if (!empty($start_raw)) {
        $sep       = strpos($start_raw, 'T') !== false ? 'T' : ' ';
        $pos       = strpos($start_raw, $sep);
        $date_part = $pos !== false ? substr($start_raw, 0, $pos) : $start_raw;
        $time_part = $pos !== false ? substr($start_raw, $pos + 1) : '';

        if ($date_part) {
            $dp       = explode('-', $date_part);
            $fmt_date = date('F j, Y', mktime(0, 0, 0, (int)$dp[1], (int)$dp[2], (int)$dp[0]));
        }

        if ($time_part) {
            $tp   = explode(':', $time_part);
            $sh   = (int)$tp[0];
            $sm   = (int)$tp[1];
            $eh   = (int)(($sh * 60 + $sm + $duration) / 60) % 24;
            $em   = ($sh * 60 + $sm + $duration) % 60;
            $sa   = $sh >= 12 ? 'PM' : 'AM';
            $ea   = $eh >= 12 ? 'PM' : 'AM';
            $sh12 = $sh % 12 === 0 ? 12 : $sh % 12;
            $eh12 = $eh % 12 === 0 ? 12 : $eh % 12;
            $sm0  = $sm < 10 ? '0' . $sm : $sm;
            $em0  = $em < 10 ? '0' . $em : $em;
            $fmt_time = $sh12 . ':' . $sm0 . ' ' . $sa . ' - ' . $eh12 . ':' . $em0 . ' ' . $ea;
        }
    }

    /* -- Is already signed? -- */
    $is_signed = (
        !empty($contract->signed_at)
        || in_array($contract->status, ['signed','accepted','agreed','approved'], true)
    );

    /* -- Is booked (payment done)? -- */
    $is_booked = ($contract->request_status === 'booked');

    $pdf_url = pcore_pdf_path_to_url((string)($contract->pdf_path ?? ''));

    /* -- Pay URL (from WooCommerce order) -- */
    $pay_url = '';
    if ($is_signed && !$is_booked && !empty($contract->order_id) && function_exists('wc_get_order')) {
        $order = wc_get_order((int)$contract->order_id);
        if ($order && $order->has_status(['pending', 'failed', 'on-hold'])) {
            $pay_url = $order->get_checkout_payment_url();
        }
    }

    /* -- Handle POST: sign contract -- */
    $sign_error   = '';
    $sign_success = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !empty($_POST['_pcore_sign_nonce'])
        && wp_verify_nonce($_POST['_pcore_sign_nonce'], 'pcore_sign_' . $contract->id)
        && !$is_signed
    ) {
        $signer_name = sanitize_text_field($_POST['signer_name'] ?? '');
        $agreed      = !empty($_POST['terms_agree']);

        if (!$signer_name || !$agreed) {
            $sign_error = 'Please enter your full name and accept the terms to sign.';
        } else {
            $flow7    = new PlatformCore_Flow7_College();
            $pdf_path = $flow7->generate_contract_pdf_public((int)$contract->id);

            $wpdb->update($tbl_contracts, [
                'status'            => 'signed',
                'signed_at'         => current_time('mysql'),
                'signed_by_user_id' => get_current_user_id(),
                'signed_name'       => $signer_name,
                'signed_ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
                'pdf_path'          => $pdf_path ?: '',
            ], ['id' => (int)$contract->id], ['%s','%s','%d','%s','%s','%s'], ['%d']);

            if (empty($contract->order_id) && $session_fee > 0 && function_exists('wc_create_order')) {
                $wc_order = wc_create_order(['customer_id' => get_current_user_id()]);
                $fee_item = new WC_Order_Item_Fee();
                $fee_item->set_name('College Class (Request #' . (int)$contract->request_id . ')');
                $fee_item->set_amount($session_fee);
                $fee_item->set_total($session_fee);
                $wc_order->add_item($fee_item);
                $wc_order->update_meta_data('_platform_request_id', (int)$contract->request_id);
                $wc_order->set_payment_method('razorpay');
                $wc_order->calculate_totals(false);
                $wc_order->set_status('pending');
                $wc_order->save();
                $wpdb->update($tbl_contracts, ['order_id' => $wc_order->get_id()], ['id' => (int)$contract->id]);
                $pay_url = $wc_order->get_checkout_payment_url();
            }

            $is_signed    = true;
            $sign_success = true;
            $pdf_url      = pcore_pdf_path_to_url((string)($pdf_path ?? ''));
        }
    }

    /* -- Current user for nav -- */
    $u          = wp_get_current_user();
    $first_name = get_user_meta($u->ID, 'first_name', true) ?: $u->display_name;
    $nav_avatar = get_avatar_url($u->ID, ['size' => 36, 'default' => 'mystery']);

    $url_dashboard   = home_url('/platform-dashboard');
    $url_find        = home_url('/find_educators');
    $url_sessions    = home_url('/college-sessions');
    $url_contracts   = home_url('/contracts-sessions');
    $url_shortlisted = home_url('/shortlisted-educators');

    $fee_display = function_exists('wc_price') ? wc_price($session_fee) : '?' . number_format($session_fee, 2);

    ob_start();
    ?>
    <style>
    body.page #page,body.page #content,body.page #primary,body.page #main,
    body.page .site,body.page .site-content,body.page .entry-content,
    body.page article,body.page .hentry,body.page .post-content,
    body.page .wp-block-group { all:unset!important; display:block!important; }
    body.page { margin:0!important; padding:0!important; background:#f1f5f9!important; }
    .site-header,.site-footer,header.site-header,footer.site-footer,
    .entry-header,.post-header,.page-header,.wp-block-template-part { display:none!important; }
    #wpadminbar { display:none!important; }
    </style>

    <div id="sc-wrap" style="position:fixed;inset:0;overflow-y:auto;background:#f1f5f9;font-family:'Inter',system-ui,sans-serif;color:#0f172a;z-index:99999;">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --bg:#f0f2f7; --surface:#ffffff; --border:#e2e6ef; --ink:#0d1117; --ink2:#4a5568;
        --ink3:#94a3b8; --accent:#1a1a2e; --blue:#3b5bdb; --blue-lt:#eef2ff;
        --green:#0a7c4e; --green-lt:#ecfdf5; --red:#c0392b; --gold:#b7860a;
        --r:12px; --shadow:0 1px 4px rgba(0,0,0,.06),0 4px 24px rgba(0,0,0,.06);
        font-family:'DM Sans',sans-serif;
    }
    body { background:var(--bg); color:var(--ink); min-height:100vh; }
    .nav { background:rgba(255,255,255,.94); backdrop-filter:blur(14px); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:100; box-shadow:0 1px 0 var(--border),0 2px 12px rgba(0,0,0,.04); }
    .nav-inner { max-width:1300px; margin:auto; padding:0 32px; height:58px; display:flex; align-items:center; justify-content:space-between; }
    .nav-logo { font-weight:800; font-size:20px; color:var(--blue); text-decoration:none; letter-spacing:-.5px; }
    .nav-links { display:flex; gap:2px; }
    .nav-links a { padding:7px 15px; border-radius:8px; font-size:13.5px; font-weight:500; color:#6b7280; text-decoration:none; transition:background .15s,color .15s; }
    .nav-links a:hover { background:var(--blue-lt); color:var(--blue); }
    .nav-links a.active { background:var(--blue-lt); color:var(--blue); font-weight:600; }
    .nav-right { display:flex; align-items:center; gap:12px; }
    .nav-right img { width:34px; height:34px; border-radius:50%; object-fit:cover; border:2px solid var(--border); }
    .nav-username { font-size:13px; font-weight:600; color:var(--ink); }
    .nav-logout { padding:7px 16px; border-radius:8px; background:var(--accent); color:#fff; font-size:13px; font-weight:600; text-decoration:none; transition:opacity .15s; }
    .nav-logout:hover { opacity:.85; }
    @media(max-width:768px){ .nav-links { display:none; } }
    .page { max-width:1100px; margin:36px auto 80px; padding:0 24px; display:grid; grid-template-columns:1fr 380px; gap:24px; align-items:start; }
    @media(max-width:860px){ .page { grid-template-columns:1fr; } }
    .card { background:var(--surface); border:1px solid var(--border); border-radius:18px; box-shadow:var(--shadow); overflow:hidden; }
    .card-body { padding:28px 32px; }
    .card + .card { margin-top:20px; }
    .section-title { font-family:'Instrument Serif',serif; font-size:22px; font-weight:400; color:var(--ink); margin-bottom:22px; letter-spacing:-.2px; }
    .expert-row { display:flex; align-items:center; gap:16px; padding:18px 20px; background:#f8fafd; border:1px solid var(--border); border-radius:12px; margin-bottom:22px; }
    .expert-avatar { width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid var(--border); flex-shrink:0; }
    .expert-name { font-size:16px; font-weight:700; color:var(--ink); }
    .expert-sub  { font-size:12.5px; color:var(--ink2); margin-top:3px; }
    .booking-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .booking-field label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink3); margin-bottom:5px; }
    .booking-field .val { font-size:15px; font-weight:600; color:var(--ink); }
    .divider { height:1px; background:var(--border); margin:24px 0; }
    .terms-box { border:1px solid var(--border); border-radius:10px; padding:16px 18px; background:#fafbfd; max-height:140px; overflow-y:auto; font-size:13px; line-height:1.65; color:var(--ink2); margin-bottom:18px; }
    .checkbox-row { display:flex; align-items:flex-start; gap:12px; margin-bottom:22px; cursor:pointer; }
    .checkbox-row input[type="checkbox"] { width:18px; height:18px; flex-shrink:0; accent-color:var(--blue); margin-top:1px; cursor:pointer; }
    .checkbox-row span { font-size:14px; color:var(--ink2); line-height:1.5; }
    .field-label { display:block; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--ink3); margin-bottom:7px; }
    .field-input { width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:9px; font-family:'DM Sans',sans-serif; font-size:14px; color:var(--ink); background:#fff; outline:none; transition:border-color .18s,box-shadow .18s; margin-bottom:18px; }
    .field-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(59,91,219,.10); }
    .btn-sign { width:100%; padding:13px 20px; background:var(--accent); color:#fff; border:none; border-radius:10px; font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600; cursor:pointer; transition:background .18s,transform .15s; letter-spacing:-.1px; }
    .btn-sign:hover { background:#16213e; transform:translateY(-1px); }
    .btn-sign:disabled { background:#cbd5e1; cursor:not-allowed; transform:none; }
    .btn-view-contract { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:11px 20px; background:var(--green-lt); color:var(--green); border:1px solid #a7f3d0; border-radius:10px; font-family:'DM Sans',sans-serif; font-size:14px; font-weight:600; text-decoration:none; margin-top:12px; transition:background .15s; }
    .btn-view-contract:hover { background:#d1fae5; }
    .signed-strip { display:flex; align-items:center; gap:10px; padding:14px 18px; background:var(--green-lt); border:1px solid #6ee7b7; border-radius:10px; margin-bottom:18px; }
    .signed-strip-icon { width:32px; height:32px; border-radius:50%; background:#10b981; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .signed-strip-icon svg { width:16px; height:16px; }
    .signed-strip-text { font-size:14px; font-weight:600; color:var(--green); }
    .signed-strip-sub  { font-size:12px; color:#059669; margin-top:1px; }
    .notice-error { padding:12px 16px; background:#fef2f2; border:1px solid #fca5a5; border-radius:9px; color:var(--red); font-size:13px; font-weight:500; margin-bottom:18px; }
    .order-title { font-size:18px; font-weight:700; color:var(--ink); padding:24px 28px 0; margin-bottom:20px; }
    .order-lines { padding:0 28px; }
    .order-line { display:flex; justify-content:space-between; align-items:center; padding:11px 0; border-bottom:1px solid var(--border); font-size:14px; color:var(--ink2); }
    .order-line:last-child { border-bottom:none; }
    .order-line.total { font-size:15px; font-weight:700; color:var(--ink); padding-top:14px; }
    .order-line .line-value { font-weight:600; color:var(--ink); }
    .order-line.total .line-value { font-size:18px; }
    .pay-section { padding:20px 28px 24px; }
    .btn-pay { display:block; width:100%; padding:14px; background:var(--accent); color:#fff; border:none; border-radius:11px; font-family:'DM Sans',sans-serif; font-size:15px; font-weight:700; text-align:center; text-decoration:none; cursor:pointer; transition:background .18s,transform .15s; }
    .btn-pay:hover { background:#16213e; transform:translateY(-1px); }
    .btn-pay.disabled { background:#94a3b8; cursor:not-allowed; transform:none; pointer-events:none; }
    .pay-tooltip-wrap { position:relative; }
    .pay-tooltip-wrap .tooltip { display:none; position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; font-size:12px; padding:7px 12px; border-radius:7px; white-space:nowrap; pointer-events:none; z-index:10; }
    .pay-tooltip-wrap .tooltip::after { content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%); border:5px solid transparent; border-top-color:#1e293b; }
    .pay-tooltip-wrap:hover .tooltip { display:block; }
    .secure-note { display:flex; align-items:center; justify-content:center; gap:6px; font-size:12px; color:var(--ink3); margin-top:12px; }
    .help-title { font-size:16px; font-weight:700; color:var(--ink); padding:22px 28px 14px; }
    .help-links { padding:0 28px 22px; display:flex; flex-direction:column; gap:10px; }
    .help-link { display:flex; align-items:center; gap:10px; font-size:13.5px; color:var(--ink2); text-decoration:none; transition:color .15s; }
    .help-link:hover { color:var(--blue); }
    .help-link-icon { width:32px; height:32px; border-radius:8px; background:var(--blue-lt); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .help-link-icon svg { width:15px; height:15px; color:var(--blue); }
    .footer { background:#0d1117; color:#475569; padding:36px 40px 18px; margin-top:60px; }
    .footer-inner { max-width:1100px; margin:0 auto; }
    .footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr; gap:40px; margin-bottom:28px; }
    .footer-logo { font-size:16px; font-weight:800; color:#818cf8; margin-bottom:8px; }
    .footer-desc { font-size:12px; line-height:1.7; }
    .footer h4 { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#e2e8f0; margin-bottom:12px; }
    .footer-links { display:flex; flex-direction:column; gap:8px; }
    .footer-links a { font-size:12.5px; color:#475569; text-decoration:none; transition:color .15s; }
    .footer-links a:hover { color:#e2e8f0; }
    .footer-bottom { border-top:1px solid rgba(255,255,255,.06); padding-top:16px; font-size:11px; text-align:center; color:#2d3748; }
    @media(max-width:600px){ .footer-grid { grid-template-columns:1fr; } }
    </style>

    <!-- Navbar -->
    <nav class="nav">
        <div class="nav-inner">
            <a href="<?php echo esc_url(home_url()); ?>" class="nav-logo">LOGO</a>
            <div class="nav-links">
                <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
                <a href="<?php echo esc_url($url_find); ?>">Find Educators</a>
                <a href="<?php echo esc_url($url_sessions); ?>">Sessions</a>
                <a href="<?php echo esc_url($url_contracts); ?>" class="active">Contracts</a>
                <a href="<?php echo esc_url($url_shortlisted); ?>">Shortlisted</a>
            </div>
            <div class="nav-right">
                <img src="<?php echo esc_url($nav_avatar); ?>" alt="Profile">
                <span class="nav-username">Hi, <?php echo esc_html($first_name); ?></span>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="nav-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="page">
        <div>
            <div class="card">
                <div class="card-body">
                    <p class="section-title">Booking Details</p>
                    <div class="expert-row">
                        <?php if ($avatar_url): ?>
                        <img class="expert-avatar" src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($expert_name); ?>">
                        <?php endif; ?>
                        <div>
                            <div class="expert-name"><?php echo esc_html($expert_name); ?></div>
                            <div class="expert-sub"><?php
                                $parts = [];
                                if ($speciality) $parts[] = trim(explode(',', $speciality)[0]);
                                if ($experience) $parts[] = $experience . '+ years experience';
                                echo esc_html(implode(' | ', $parts) ?: 'Medical Educator');
                            ?></div>
                        </div>
                    </div>
                    <div class="booking-grid">
                        <div class="booking-field"><label>Date</label><div class="val"><?php echo esc_html($fmt_date); ?></div></div>
                        <div class="booking-field"><label>Time</label><div class="val"><?php echo esc_html($fmt_time); ?></div></div>
                        <div class="booking-field"><label>Duration</label><div class="val"><?php echo esc_html($duration); ?> minutes</div></div>
                        <div class="booking-field"><label>Topic</label><div class="val"><?php echo esc_html($contract->topic); ?></div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <p class="section-title"><?php echo $is_signed ? 'Contract Signed' : 'Sign Contract'; ?></p>

                    <?php if ($sign_success): ?>
                    <div class="signed-strip">
                        <div class="signed-strip-icon"><svg fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                        <div><div class="signed-strip-text">Contract signed successfully!</div><div class="signed-strip-sub">Please proceed to payment to confirm your booking.</div></div>
                    </div>
                    <?php elseif ($is_signed): ?>
                    <div class="signed-strip">
                        <div class="signed-strip-icon"><svg fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                        <div>
                            <div class="signed-strip-text">You have already signed this contract.</div>
                            <?php if ($is_booked): ?>
                            <div class="signed-strip-sub">Payment completed — session is confirmed.</div>
                            <?php else: ?>
                            <div class="signed-strip-sub">Complete payment to confirm your booking.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($sign_error): ?>
                    <div class="notice-error"><?php echo esc_html($sign_error); ?></div>
                    <?php endif; ?>

                    <?php if (!$is_signed): ?>
                    <div class="terms-box">
                        <strong>Terms &amp; Conditions</strong><br><br>
                        By signing this contract you agree to the following:
                        <ul style="margin:8px 0 0 18px;line-height:1.8;">
                            <li>The session fee is non-refundable within 48 hours of the scheduled session.</li>
                            <li>Cancellations made more than 48 hours prior are eligible for a full refund.</li>
                            <li>The expert may reschedule with 24 hours' notice.</li>
                            <li>All session content is confidential and may not be recorded without consent.</li>
                            <li>Payment must be completed within 24 hours of signing to secure the booking.</li>
                        </ul>
                    </div>
                    <form method="POST" id="sign-form">
                        <?php wp_nonce_field('pcore_sign_' . $contract->id, '_pcore_sign_nonce'); ?>
                        <label class="field-label" for="signer_name">Your Full Name (as signature)</label>
                        <input class="field-input" type="text" id="signer_name" name="signer_name" placeholder="Enter your full legal name" required autocomplete="name">
                        <label class="checkbox-row" for="terms_agree">
                            <input type="checkbox" id="terms_agree" name="terms_agree" value="1">
                            <span>I have read and accept the <a href="#" onclick="return false;">Terms &amp; Conditions</a> above.</span>
                        </label>
                        <button class="btn-sign" type="submit" id="sign-btn" disabled>Sign Contract</button>
                    </form>
                    <?php else: ?>
                    <?php if ($pdf_url): ?>
                    <a class="btn-view-contract" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener noreferrer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        View Signed Contract
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="order-title">Order Summary</div>
                <div class="order-lines">
                    <div class="order-line"><span>Session Fee</span><span class="line-value"><?php echo $fee_display; ?></span></div>
                    <div class="order-line total"><span>Total Amount</span><span class="line-value"><?php echo $fee_display; ?></span></div>
                </div>
                <div class="pay-section">
                    <?php if ($is_booked): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:13px 16px;background:var(--green-lt);border:1px solid #6ee7b7;border-radius:10px;margin-bottom:10px;">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span style="font-size:14px;font-weight:600;color:var(--green);">Payment Completed</span>
                        </div>
                    <?php elseif ($is_signed && !empty($pay_url)): ?>
                        <a href="<?php echo esc_url($pay_url); ?>" class="btn-pay">Proceed to Pay</a>
                    <?php else: ?>
                        <div class="pay-tooltip-wrap">
                            <div class="tooltip">Sign the contract first</div>
                            <button class="btn-pay disabled" disabled>Proceed to Pay</button>
                        </div>
                    <?php endif; ?>
                    <div class="secure-note">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Secure payment powered by Razorpay
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="help-title">Need Help?</div>
                <div class="help-links">
                    <a href="#" class="help-link"><div class="help-link-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4M12 8h.01"/></svg></div>Payment FAQs</a>
                    <a href="#" class="help-link"><div class="help-link-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg></div>Contact Support</a>
                    <a href="#" class="help-link"><div class="help-link-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>Cancellation Policy</a>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-grid">
                <div><div class="footer-logo">LOGO</div><p class="footer-desc">Connecting medical professionals with expert educators for personalised learning experiences.</p></div>
                <div><h4>Quick Links</h4><div class="footer-links"><a href="<?php echo esc_url($url_find); ?>">Find an Educator</a><a href="<?php echo esc_url($url_sessions); ?>">My Sessions</a><a href="#">Support</a></div></div>
                <div><h4>Contact</h4><div class="footer-links"><a href="mailto:support@medicaledu.com">support@medicaledu.com</a><a href="tel:+919876543210">+91 98765 43210</a></div></div>
            </div>
            <div class="footer-bottom">&copy; <?php echo date('Y'); ?> Medical Educator Platform. All rights reserved.</div>
        </div>
    </footer>

    <script>
    (function () {
        var checkbox  = document.getElementById('terms_agree');
        var nameInput = document.getElementById('signer_name');
        var signBtn   = document.getElementById('sign-btn');
        function updateBtn() {
            if (!signBtn) return;
            signBtn.disabled = !(checkbox && checkbox.checked && nameInput && nameInput.value.trim().length > 1);
        }
        if (checkbox)  checkbox.addEventListener('change', updateBtn);
        if (nameInput) nameInput.addEventListener('input',  updateBtn);
    })();
    </script>

    </div><!-- /sc-wrap -->
    <?php
    return ob_get_clean();
}