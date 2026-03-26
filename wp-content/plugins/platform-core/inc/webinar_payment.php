<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Find Amelia Provider ID
 */
function platform_core_get_current_provider_id() {
    static $provider_id = null;
    if ($provider_id !== null) return $provider_id;
    if (!is_user_logged_in()) return 0;

    $user  = wp_get_current_user();
    $email = $user->user_email;

    $url = admin_url(
        'admin-ajax.php?action=wpamelia_api&call=/api/v1/users/providers&search=' . urlencode($email)
    );

    for ($i = 0; $i < 10; $i++) {
        $res = wp_remote_get($url, [
            'headers' => ['Amelia' => 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm'],
            'timeout' => 10
        ]);
        if (is_wp_error($res)) { sleep(1); continue; }
        if (wp_remote_retrieve_response_code($res) === 429) { sleep(5); continue; }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($body['data']['users'][0]['id'])) {
            return $provider_id = (int) $body['data']['users'][0]['id'];
        }
    }
    return $provider_id = 0;
}

/**
 * Get Event Payments (for webinars)
 */
function platform_core_get_event_payments($date_from, $date_to) {
    global $wpdb;

    $provider_id = platform_core_get_current_provider_id();
    if (!$provider_id) {
        $user_id = get_current_user_id();
        $provider_id = (int) get_user_meta($user_id, 'amelia_employee_id', true);
    }
    if (!$provider_id) return ['payments' => []];

    $tbl_payments          = $wpdb->prefix . 'amelia_payments';
    $tbl_bookings          = $wpdb->prefix . 'amelia_customer_bookings';
    $tbl_booking_to_period = $wpdb->prefix . 'amelia_customer_bookings_to_events_periods';
    $tbl_event_periods     = $wpdb->prefix . 'amelia_events_periods';
    $tbl_events            = $wpdb->prefix . 'amelia_events';
    $tbl_users             = $wpdb->prefix . 'amelia_users';

    $sql = $wpdb->prepare("
        SELECT
            p.id AS payment_id,
            p.amount,
            p.dateTime,
            p.status,
            p.gateway,
            u.firstName,
            u.lastName,
            u.email,
            e.name AS eventName,
            ep.periodStart,
            ep.periodEnd
        FROM {$tbl_payments} p
        INNER JOIN {$tbl_bookings} b ON b.id = p.customerBookingId
        LEFT JOIN {$tbl_booking_to_period} btp ON btp.customerBookingId = b.id
        LEFT JOIN {$tbl_event_periods} ep ON ep.id = btp.eventPeriodId
        LEFT JOIN {$tbl_events} e ON e.id = ep.eventId
        LEFT JOIN {$tbl_users} u ON u.id = b.customerId
        WHERE
            p.entity = 'event'
            AND e.organizerId = %d
            AND DATE(p.dateTime) BETWEEN %s AND %s
        ORDER BY p.dateTime DESC
    ", $provider_id, $date_from, $date_to);

    $rows     = $wpdb->get_results($sql, ARRAY_A);
    $payments = [];

    if ($rows) {
        foreach ($rows as $r) {
            $gateway = $r['gateway'];
            $status  = $r['status'];
            if ($gateway === 'onSite' && $status === 'pending') continue;
            $payments[] = [
                'payment_id'   => $r['payment_id'],
                'status'       => $status ?: 'paid',
                'gateway'      => $gateway,
                'amount'       => (float) $r['amount'],
                'dateTime'     => $r['dateTime'],
                'bookableName' => $r['eventName'] ?: 'Webinar',
                'periodStart'  => $r['periodStart'],
                'periodEnd'    => $r['periodEnd'],
                'customer'     => [
                    'firstName' => $r['firstName'] ?: 'Learner',
                    'lastName'  => $r['lastName'] ?: '',
                    'email'     => $r['email'] ?: ''
                ]
            ];
        }
    }

    return ['payments' => $payments];
}

/**
 * Get Total Revenue (Events only)
 */
function platform_core_get_event_finance_data($args) {
    global $wpdb;

    $provider_id = platform_core_get_current_provider_id();
    if (!$provider_id) {
        $user_id = get_current_user_id();
        $provider_id = (int) get_user_meta($user_id, 'amelia_employee_id', true);
    }
    if (!$provider_id) return ['totalPrice' => 0];

    $dates                 = json_decode($args['dates'], true);
    $tbl_payments          = $wpdb->prefix . 'amelia_payments';
    $tbl_bookings          = $wpdb->prefix . 'amelia_customer_bookings';
    $tbl_booking_to_period = $wpdb->prefix . 'amelia_customer_bookings_to_events_periods';
    $tbl_event_periods     = $wpdb->prefix . 'amelia_events_periods';
    $tbl_events            = $wpdb->prefix . 'amelia_events';

    $sql = $wpdb->prepare("
        SELECT SUM(p.amount)
        FROM {$tbl_payments} p
        INNER JOIN {$tbl_bookings} b ON b.id = p.customerBookingId
        LEFT JOIN {$tbl_booking_to_period} btp ON btp.customerBookingId = b.id
        LEFT JOIN {$tbl_event_periods} ep ON ep.id = btp.eventPeriodId
        LEFT JOIN {$tbl_events} e ON e.id = ep.eventId
        WHERE
            p.gateway = 'wc'
            AND p.entity = 'event'
            AND p.status != 'refunded'
            AND e.organizerId = %d
            AND DATE(p.dateTime) BETWEEN %s AND %s
    ", $provider_id, $dates[0], $dates[1]);

    return ['totalPrice' => (float) $wpdb->get_var($sql)];
}

/**
 * Shortcode: [webinar_payments]
 */
function platform_core_webinar_payments_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p style="text-align:center;padding:40px;color:#888;">Please log in to view your payments.</p>';
    }

    $current_user = wp_get_current_user();
    $user_id      = get_current_user_id();
    $nav_avatar   = get_avatar_url($user_id, ['size' => 36, 'default' => 'mystery']);

    $first_name = get_user_meta($user_id, 'first_name', true);
    if (!empty(trim($first_name))) {
        $user_name = trim($first_name);
    } elseif (!empty($current_user->display_name) && strpos($current_user->display_name, '@') === false) {
        $user_name = $current_user->display_name;
    } else {
        $user_name = explode('@', $current_user->user_email)[0];
    }

    $url_dashboard    = home_url('/webinar_expert');
    $url_calendar     = home_url('/webinar_calender');
    $url_webinars     = home_url('/webinar_schedule');
    $url_transactions = get_permalink();

    // FIX: use current_time('timestamp') for IST-aware date ranges
    $today_ist     = date('Y-m-d', current_time('timestamp'));
    $two_years_ago = date('Y-m-d', current_time('timestamp') - (2 * YEAR_IN_SECONDS));

    $finance_data         = platform_core_get_event_finance_data(['dates' => json_encode(['2020-01-01', $today_ist])]);
    $total_global_revenue = $finance_data['totalPrice'] ?? 0;
    $all_payments_data    = platform_core_get_event_payments($two_years_ago, $today_ist);
    $transactions         = $all_payments_data['payments'] ?? [];

    // Guarantee 0-based sequential keys so PHP $idx always matches JS array index
    $transactions = array_values($transactions);

    // Sanitised JSON for JS invoice generator
    $txns_json = json_encode(array_map(function($p) {
        return [
            'payment_id'   => (int)($p['payment_id'] ?? 0),
            'bookableName' => wp_strip_all_tags($p['bookableName'] ?? 'Webinar'),
            'amount'       => (float)($p['amount'] ?? 0),
            'status'       => sanitize_text_field($p['status'] ?? 'paid'),
            'gateway'      => sanitize_text_field($p['gateway'] ?? ''),
            'dateTime'     => sanitize_text_field($p['dateTime'] ?? ''),
            'periodStart'  => sanitize_text_field($p['periodStart'] ?? ''),
            'periodEnd'    => sanitize_text_field($p['periodEnd'] ?? ''),
            'customer'     => [
                'firstName' => wp_strip_all_tags($p['customer']['firstName'] ?? 'Learner'),
                'lastName'  => wp_strip_all_tags($p['customer']['lastName'] ?? ''),
                'email'     => sanitize_email($p['customer']['email'] ?? ''),
            ],
        ];
    }, $transactions));

    ob_start();
    ?>
    <style>
    #wpadminbar{display:none!important;}
    html{margin-top:0!important;}
    header,#masthead,.site-header,.main-header,#header,
    .elementor-location-header,.ast-main-header-wrap,#site-header,
    .fusion-header-wrapper,.header-wrap,.nav-primary,
    div[data-elementor-type="header"]{display:none!important;}
    .page-template-default .site-content,.site-main,#content,#page{margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;}
    footer.site-footer,.site-footer,#colophon,#footer,
    .footer-area,.ast-footer-overlay,.footer-widgets-area,.footer-bar,
    div[data-elementor-type="footer"],.elementor-location-footer{display:none!important;}

    /* NAV */
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

    /* PAGE */
    *{box-sizing:border-box;}
    .webinar-payments-wrapper{font-family:'DM Sans',-apple-system,sans-serif;background:#f8f9fa;width:100%;margin:0;padding:40px 20px;}
    .webinar-payments-container{max-width:1200px;margin:0 auto;}
    .wp-header{background:#fff;border:1px solid #eee;border-radius:16px;padding:30px;margin-bottom:30px;box-shadow:0 4px 12px rgba(0,0,0,0.02);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:20px;}
    .wp-header-left h2{margin:0 0 5px 0;font-size:24px;font-weight:700;color:#111;}
    .wp-header-left small{color:#888;font-size:14px;}
    .wp-header-right{display:flex;align-items:center;gap:20px;}
    .btn-invoice-all{background:#000;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;transition:opacity .15s;}
    .btn-invoice-all:hover{opacity:.85;}
    .profile-img{width:42px;height:42px;border-radius:50%;border:1px solid #eee;object-fit:cover;}
    .history-card{background:#fff;border:1px solid #eee;border-radius:16px;padding:30px;box-shadow:0 4px 12px rgba(0,0,0,0.02);}
    .history-card h3{margin:0 0 20px 0;font-size:20px;font-weight:700;color:#111;}
    .earning-box{background:#fcfcfc;padding:20px;border-radius:12px;margin:20px 0;border:1px solid #f0f0f0;}
    .earning-box-header{display:flex;justify-content:space-between;color:#888;font-size:13px;font-weight:500;}
    .earning-box-amount{font-size:36px;font-weight:800;margin-top:8px;color:#000;}
    .txn-list{margin-top:20px;}
    .txn-row{border:1px solid #f5f5f5;border-radius:12px;padding:20px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;}
    .txn-details{display:flex;flex-direction:column;gap:5px;flex:1;min-width:250px;}
    .txn-title{font-weight:700;color:#111;font-size:16px;}
    .txn-student{font-size:13px;color:#555;font-weight:500;}
    .txn-event{font-size:12px;color:#777;margin-top:4px;}
    .txn-meta{margin-top:10px;font-size:12px;color:#999;display:flex;align-items:center;gap:10px;font-weight:500;flex-wrap:wrap;}
    .txn-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;}
    .txn-amount{font-weight:800;font-size:20px;white-space:nowrap;}
    .status-pill{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;text-transform:capitalize;}
    .bg-paid{background:#e6fcf5;color:#0ca678;}
    .bg-pending{background:#fff4e6;color:#fcc419;}
    .bg-refunded{background:#ffe6e6;color:#fa5252;}
    .gateway-badge{background:#f0f0f0;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:600;}
    .no-transactions{text-align:center;padding:60px;color:#888;border:1px dashed #ddd;border-radius:12px;}
    .no-transactions p{font-size:16px;margin:0;}
    .btn-dl-invoice{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;font-weight:600;color:#334155;cursor:pointer;transition:background .15s,border-color .15s;white-space:nowrap;}
    .btn-dl-invoice:hover{background:#e0e7ff;border-color:#c7d2fe;color:#4338ca;}
    .btn-dl-invoice svg{width:14px;height:14px;flex-shrink:0;}
    @media(max-width:768px){
        .webinar-payments-wrapper{padding:20px 10px;}
        .wp-header{padding:20px;}
        .history-card{padding:20px;}
        .earning-box-amount{font-size:28px;}
        .txn-row{flex-direction:column;align-items:flex-start;}
        .txn-right{align-items:flex-start;}
        .txn-amount{font-size:18px;}
    }
    </style>

    <nav class="pc-nav">
        <div class="pc-nav-inner">
            <a href="<?php echo esc_url(home_url()); ?>" class="pc-nav-logo">LOGO</a>
            <div class="pc-nav-links">
                <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
                <a href="<?php echo esc_url($url_calendar); ?>">Calendar</a>
                <a href="<?php echo esc_url($url_webinars); ?>">Webinars</a>
                <a href="<?php echo esc_url($url_transactions); ?>" class="active">Transactions</a>
            </div>
            <div class="pc-nav-right">
                <img src="<?php echo esc_url($nav_avatar); ?>" alt="Profile">
                <span class="pc-nav-username">Hi, <?php echo esc_html($user_name); ?></span>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pc-nav-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="webinar-payments-wrapper">
        <div class="webinar-payments-container">
            <div class="wp-header">
                <div class="wp-header-left">
                    <h2>Global Payments</h2>
                    <!-- FIX: use current_time('timestamp') for IST date display -->
                    <small><?php echo date('l, F d, Y', current_time('timestamp')); ?></small>
                </div>
                <div class="wp-header-right">
                    <button class="btn-invoice-all" onclick="pcDownloadAllInvoices()">
                        <svg style="width:14px;height:14px;fill:none;stroke:white;stroke-width:2.5;" viewBox="0 0 24 24">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        Download All Invoices
                    </button>
                    <img src="<?php echo esc_url(get_avatar_url($user_id)); ?>" class="profile-img" alt="Profile">
                </div>
            </div>

            <div class="history-card">
                <h3>All Transactions</h3>
                <div class="earning-box">
                    <div class="earning-box-header">
                        <span>Total Revenue (Gross)</span>
                        <span>Global History</span>
                    </div>
                    <div class="earning-box-amount">Rs <?php echo number_format($total_global_revenue, 2); ?></div>
                </div>

                <div class="txn-list">
                    <?php
                    if (!empty($transactions)) :
                        foreach ($transactions as $idx => $p) :
                            $amount        = (float)($p['amount'] ?? 0);
                            $status        = strtolower($p['status'] ?? 'pending');
                            $gateway       = $p['gateway'] ?? 'onSite';
                            $is_refunded   = ($status === 'refunded');
                            $student_name  = esc_html($p['customer']['firstName'] ?? 'Learner');
                            $student_last  = esc_html($p['customer']['lastName'] ?? '');
                            $student_email = esc_html($p['customer']['email'] ?? 'No email');
                            $status_class  = 'bg-pending';
                            if ($is_refunded)          { $status_class = 'bg-refunded'; }
                            elseif ($status === 'paid') { $status_class = 'bg-paid'; }
                            $amount_color = $is_refunded ? '#fa5252' : '#0ca678';

                            // FIX: add 19800s offset so event and payment times display in IST
                            $period_start_ist = !empty($p['periodStart']) ? strtotime($p['periodStart']) + 19800 : 0;
                            $period_end_ist   = !empty($p['periodEnd'])   ? strtotime($p['periodEnd'])   + 19800 : 0;
                            $pay_date_ist     = !empty($p['dateTime'])    ? strtotime($p['dateTime'])    + 19800 : 0;
                    ?>
                    <div class="txn-row">
                        <div class="txn-details">
                            <div class="txn-title"><?php echo esc_html($p['bookableName'] ?? 'Webinar'); ?></div>
                            <div class="txn-student">By: <?php echo $student_name . ' ' . $student_last; ?> (<?php echo $student_email; ?>)</div>
                            <?php if ($period_start_ist && $period_end_ist) : ?>
                                <div class="txn-event">
                                    Event: <?php echo date('M d, Y h:i A', $period_start_ist); ?> &ndash; <?php echo date('h:i A', $period_end_ist); ?>
                                </div>
                            <?php endif; ?>
                            <div class="txn-meta">
                                <svg style="width:14px;height:14px;fill:#94a3b8;" viewBox="0 0 24 24"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z"/></svg>
                                <!-- FIX: payment date in IST -->
                                Payment: <?php echo $pay_date_ist ? date('M d, Y', $pay_date_ist) : '-'; ?>
                                <?php if ($gateway === 'wc') : ?>
                                    <span class="status-pill bg-paid">Paid</span>
                                    <span class="gateway-badge">Razorpay</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="txn-right">
                            <div class="txn-amount" style="color:<?php echo $amount_color; ?>;">
                                <?php echo $is_refunded ? '-' : '+'; ?>Rs <?php echo number_format($amount, 2); ?>
                            </div>
                            <button class="btn-dl-invoice" data-idx="<?php echo (int)$idx; ?>" title="Download invoice">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Invoice
                            </button>
                        </div>
                    </div>
                    <?php endforeach;
                    else : ?>
                        <div class="no-transactions"><p>No transactions found in system.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    var PC_TXN_DATA = <?php echo $txns_json; ?>;
    var PC_EXPERT   = <?php echo json_encode([
        'name'  => $user_name,
        'email' => $current_user->user_email,
        'site'  => get_bloginfo('name'),
    ]); ?>;

    // IST offset in ms for JS date display (+5:30)
    var IST_OFFSET_MS = 5.5 * 60 * 60 * 1000;

    function pcISTDate(dateStr, opts) {
        if (!dateStr) return '-';
        var utc = new Date(dateStr).getTime();
        var ist = new Date(utc + IST_OFFSET_MS);
        return ist.toLocaleDateString('en-IN', opts);
    }
    function pcISTTime(dateStr, opts) {
        if (!dateStr) return '-';
        var utc = new Date(dateStr).getTime();
        var ist = new Date(utc + IST_OFFSET_MS);
        return ist.toLocaleTimeString('en-IN', opts);
    }

    function pcEsc(str) {
        return String(str || '')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;');
    }

    function pcBuildInvoiceHTML(t, idx) {
        var invNum    = 'INV-' + String(new Date().getFullYear()) + '-' + String(idx + 1).padStart(4, '0');
        // FIX: invoice dates rendered in IST
        var payDate   = t.dateTime
            ? pcISTDate(t.dateTime, {day:'2-digit', month:'long', year:'numeric'})
            : '-';
        var eventDate = '';
        if (t.periodStart) {
            eventDate = pcISTDate(t.periodStart, {day:'2-digit', month:'long', year:'numeric'})
                      + ' ' + pcISTTime(t.periodStart, {hour:'2-digit', minute:'2-digit'});
        }
        var customer    = ((t.customer.firstName || '') + ' ' + (t.customer.lastName || '')).trim() || 'Learner';
        var amount      = parseFloat(t.amount || 0).toFixed(2);
        var isRefund    = (t.status === 'refunded');
        var statusColor = isRefund ? '#fa5252' : '#0ca678';
        var statusLabel = isRefund ? 'Refunded' : 'Paid';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Invoice ' + pcEsc(invNum) + '</title>'
            + '<style>'
            + 'body{font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:32px;}'
            + '.inv{background:#fff;max-width:680px;margin:0 auto;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);}'
            + '.inv-head{background:#0f172a;color:#fff;padding:32px 36px;display:flex;justify-content:space-between;align-items:flex-start;}'
            + '.inv-head h1{margin:0;font-size:28px;letter-spacing:-.5px;}'
            + '.inv-meta{text-align:right;font-size:13px;opacity:.75;line-height:1.8;}'
            + '.inv-body{padding:32px 36px;}'
            + '.inv-parties{display:flex;justify-content:space-between;gap:24px;margin-bottom:28px;}'
            + '.inv-party h4{margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;}'
            + '.inv-party p{margin:0;font-size:14px;color:#0f172a;line-height:1.7;}'
            + '.inv-table{width:100%;border-collapse:collapse;margin-bottom:24px;}'
            + '.inv-table th{background:#f4f6fb;padding:10px 14px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1px solid #e2e8f0;}'
            + '.inv-table td{padding:12px 14px;font-size:14px;border-bottom:1px solid #f1f5f9;color:#0f172a;}'
            + '.inv-total{display:flex;justify-content:flex-end;}'
            + '.inv-total-box{background:#f4f6fb;border-radius:10px;padding:16px 24px;min-width:200px;}'
            + '.t-row{display:flex;justify-content:space-between;gap:32px;font-size:14px;color:#64748b;margin-bottom:6px;}'
            + '.t-row.final{font-size:18px;font-weight:800;color:#0f172a;border-top:1px solid #e2e8f0;padding-top:10px;margin-top:4px;}'
            + '.inv-status{display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;background:' + (isRefund ? '#ffe6e6' : '#e6fcf5') + ';color:' + statusColor + ';}'
            + '.inv-footer{padding:20px 36px;border-top:1px solid #f1f5f9;font-size:12px;color:#94a3b8;text-align:center;}'
            + '@media print{body{background:#fff;padding:0;}.inv{box-shadow:none;border-radius:0;}}'
            + '</style></head><body>'
            + '<div class="inv">'
            +   '<div class="inv-head">'
            +     '<div><h1>' + pcEsc(PC_EXPERT.site || 'Platform') + '</h1>'
            +     '<div style="font-size:13px;opacity:.6;margin-top:4px;">Invoice</div></div>'
            +     '<div class="inv-meta">'
            +       '<div><strong>' + pcEsc(invNum) + '</strong></div>'
            +       '<div>Date: ' + pcEsc(payDate) + '</div>'
            +       '<div><span class="inv-status">' + pcEsc(statusLabel) + '</span></div>'
            +     '</div>'
            +   '</div>'
            +   '<div class="inv-body">'
            +     '<div class="inv-parties">'
            +       '<div class="inv-party"><h4>From</h4><p><strong>' + pcEsc(PC_EXPERT.name) + '</strong><br>' + pcEsc(PC_EXPERT.email) + '</p></div>'
            +       '<div class="inv-party"><h4>Bill To</h4><p><strong>' + pcEsc(customer) + '</strong><br>' + pcEsc(t.customer.email || '') + '</p></div>'
            +     '</div>'
            +     '<table class="inv-table">'
            +       '<thead><tr><th>Description</th><th>Event Date</th><th>Gateway</th><th>Amount</th></tr></thead>'
            +       '<tbody><tr>'
            +         '<td>' + pcEsc(t.bookableName) + '</td>'
            +         '<td>' + pcEsc(eventDate || '-') + '</td>'
            +         '<td>' + (t.gateway === 'wc' ? 'Razorpay' : pcEsc(t.gateway || 'Online')) + '</td>'
            +         '<td style="font-weight:700;color:' + statusColor + ';">' + (isRefund ? '-' : '') + 'Rs\u00a0' + amount + '</td>'
            +       '</tr></tbody>'
            +     '</table>'
            +     '<div class="inv-total"><div class="inv-total-box">'
            +       '<div class="t-row"><span>Subtotal</span><span>Rs ' + amount + '</span></div>'
            +       '<div class="t-row"><span>Tax (incl.)</span><span>Rs 0.00</span></div>'
            +       '<div class="t-row final"><span>Total</span><span>Rs ' + amount + '</span></div>'
            +     '</div></div>'
            +   '</div>'
            +   '<div class="inv-footer">Thank you for your participation \u2014 ' + pcEsc(PC_EXPERT.site) + '</div>'
            + '</div></body></html>';
    }

    function pcTriggerDownload(html, filename) {
        try {
            var blob = new Blob([html], {type: 'text/html'});
            var a    = document.createElement('a');
            a.href   = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(function() {
                document.body.removeChild(a);
                URL.revokeObjectURL(a.href);
            }, 300);
        } catch(e) {
            var w = window.open('', '_blank', 'width=750,height=900');
            if (w) {
                w.document.open();
                w.document.write(html);
                w.document.close();
                w.onload = function() { w.print(); };
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-dl-invoice').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.getAttribute('data-idx'), 10);
                var t   = PC_TXN_DATA[idx];
                if (!t) { alert('Transaction data not found for index ' + idx); return; }
                var payDate  = t.dateTime ? t.dateTime.slice(0,10).replace(/-/g,'') : 'unknown';
                var filename = 'invoice-' + String(idx + 1) + '-'
                             + (t.bookableName || 'webinar').replace(/\s+/g,'-').toLowerCase()
                             + '-' + payDate + '.html';
                pcTriggerDownload(pcBuildInvoiceHTML(t, idx), filename);
            });
        });
    });

    function pcDownloadAllInvoices() {
        if (!PC_TXN_DATA || !PC_TXN_DATA.length) { alert('No transactions to export.'); return; }
        if (!confirm('This will download ' + PC_TXN_DATA.length + ' invoice file(s). Continue?')) return;
        PC_TXN_DATA.forEach(function(t, idx) {
            setTimeout(function() {
                var payDate  = t.dateTime ? t.dateTime.slice(0,10).replace(/-/g,'') : 'unknown';
                var filename = 'invoice-' + String(idx + 1) + '-'
                             + (t.bookableName || 'webinar').replace(/\s+/g,'-').toLowerCase()
                             + '-' + payDate + '.html';
                pcTriggerDownload(pcBuildInvoiceHTML(t, idx), filename);
            }, idx * 600);
        });
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('webinar_payments', 'platform_core_webinar_payments_shortcode');