<?php
/**
 * College Sessions Dashboard - Enhanced Implementation
 * Shortcode: [platform_college_sessions_dashboard]
 * UPDATED: Removed Zoom links, debug code, made sessions clickable
 */

add_shortcode('platform_college_sessions_dashboard', 'platform_render_college_sessions_dashboard');

function platform_dashboard_get_available_educators($limit = 5) {
    if (!function_exists('platform_core_amelia_api_headers')) return [];

    $api_url    = "https://staging-68a5-inditechsites.wpcomstaging.com/amelia/wp-admin/admin-ajax.php";
    $service_id = 6;
    $url = $api_url . '?action=wpamelia_api&call=/api/v1/users/providers&services[0]=' . $service_id;

    $response = wp_remote_get($url, ['headers'=>platform_core_amelia_api_headers(),'timeout'=>45,'sslverify'=>false,'httpversion'=>'1.1']);
    if (is_wp_error($response)) return [];

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) return [];

    $decoded = json_decode($body, true);
    if (empty($decoded['data']['users'])) return [];

    $providers    = $decoded['data']['users'];
    $provider_map = [];
    foreach ($providers as $provider) {
        $email = strtolower(trim($provider['email'] ?? ''));
        if (!empty($email)) {
            $provider_map[$email] = ['provider_id'=>$provider['id'],'email'=>$email,'first_name'=>$provider['firstName']??'','last_name'=>$provider['lastName']??'','activity'=>$provider['activity']??'away'];
        }
    }

    global $wpdb;
    $all_wp_users = $wpdb->get_results("
        SELECT u.ID, u.user_email, u.display_name, m.meta_value as roles
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->usermeta} m ON u.ID = m.user_id AND m.meta_key = '{$wpdb->prefix}capabilities'
        ORDER BY u.display_name
    ");

    $matched_experts = [];
    foreach ($all_wp_users as $wp_user) {
        $roles = maybe_unserialize($wp_user->roles);
        if (empty($roles) || !is_array($roles) || !array_key_exists('expert', $roles)) continue;
        $wp_email = strtolower(trim($wp_user->user_email));
        if (isset($provider_map[$wp_email]) && $provider_map[$wp_email]['activity'] === 'available') {
            $matched_experts[] = (object)['ID'=>$wp_user->ID,'display_name'=>$wp_user->display_name,'user_email'=>$wp_user->user_email,'provider_id'=>$provider_map[$wp_email]['provider_id'],'activity'=>$provider_map[$wp_email]['activity']];
        }
        if (count($matched_experts) >= $limit) break;
    }
    return $matched_experts;
}

function platform_render_college_sessions_dashboard() {
    // Redirect guests to landing page
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/college-dashboard/'));
        exit;
    }

    global $wpdb;

    $current_user = wp_get_current_user();
    $user_id      = get_current_user_id();
    $avatar_url   = get_avatar_url($user_id, ['size' => 96]);

    $first_name = get_user_meta($user_id, 'first_name', true);
    if (!empty(trim($first_name))) {
        $user_name = trim($first_name);
    } elseif (!empty($current_user->display_name) && strpos($current_user->display_name, '@') === false) {
        $user_name = $current_user->display_name;
    } else {
        $user_name = explode('@', $current_user->user_email)[0];
    }

    $tbl_requests  = $wpdb->prefix . 'platform_requests';
    $tbl_contracts = $wpdb->prefix . 'platform_contracts';
    $tbl_calendar  = $wpdb->prefix . 'platform_calendar_map';

    // Fetch all sessions with zoom_url from calendar_map
    $all_sessions = $wpdb->get_results($wpdb->prepare("
        SELECT r.id, r.topic, r.description, r.proposed_start_iso, r.duration_minutes, r.status, r.expert_user_id, r.price_offer,
               c.id AS contract_id, c.status AS contract_status, c.total_amount, c.class_start_iso, c.sign_token, c.sign_token_expires, c.order_id, c.pdf_path,
               COALESCE(
                   cal1.zoom_url,
                   cal2.zoom_url,
                   cal3.zoom_url
               ) AS zoom_url
        FROM {$tbl_requests} r
        LEFT JOIN {$tbl_contracts} c ON c.request_id = r.id
        LEFT JOIN {$tbl_calendar} cal1 ON cal1.object_id = r.id AND cal1.source = 'platform_request'
        LEFT JOIN {$tbl_calendar} cal2 ON cal2.object_id = r.appointment_id AND cal2.source = 'amelia_appointment'
        LEFT JOIN {$tbl_calendar} cal3 ON cal3.object_id = c.id AND cal3.source = 'platform_contract'
        WHERE r.college_user_id = %d AND r.status IN ('booked', 'pending_contract')
        ORDER BY r.proposed_start_iso ASC
    ", $user_id));

    $now = current_time('timestamp');
    $upcoming_sessions = [];
    $past_sessions     = [];
    foreach ($all_sessions as $session) {
        $session_time = strtotime($session->class_start_iso ?: $session->proposed_start_iso);
        if ($session_time >= $now) { $upcoming_sessions[] = $session; } else { $past_sessions[] = $session; }
    }

    $resolve_payment = function($order_id, $request_id) use ($wpdb, $tbl_contracts) {
        $is_paid = false; $pay_url = '';
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order((int)$order_id);
            if ($order) { $is_paid = $order->is_paid(); if (!$is_paid) $pay_url = $order->get_checkout_payment_url(); return compact('is_paid','pay_url'); }
        }
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders(['limit'=>1,'orderby'=>'date','order'=>'DESC','status'=>['pending','failed','on-hold'],'meta_key'=>'_platform_request_id','meta_value'=>(int)$request_id]);
            if (!empty($orders)) { $order=$orders[0]; $is_paid=$order->is_paid(); if(!$is_paid) $pay_url=$order->get_checkout_payment_url(); return compact('is_paid','pay_url'); }
        }
        return ['is_paid'=>true,'pay_url'=>''];
    };

    $pending_contracts_raw = $wpdb->get_results($wpdb->prepare("
        SELECT c.id, c.request_id, c.total_amount, c.sign_token, c.order_id, r.topic, r.proposed_start_iso, c.class_start_iso
        FROM {$tbl_contracts} c
        JOIN {$tbl_requests} r ON r.id = c.request_id
        WHERE r.college_user_id = %d
          AND c.status NOT IN ('booked','rejected','signed','accepted','agreed','approved')
        ORDER BY c.class_start_iso
    ", $user_id));

    $pending_contracts = [];
    foreach ($pending_contracts_raw as $contract) {
        $class_date = $contract->class_start_iso ?: $contract->proposed_start_iso;
        if (empty($class_date) || strtotime($class_date) <= $now) continue;
        if ((float)$contract->total_amount > 0) {
            $pmt = $resolve_payment($contract->order_id, $contract->request_id);
            if ($pmt['is_paid']) continue;
            $contract->pay_url = $pmt['pay_url'];
        } else { $contract->pay_url = ''; }
        $pending_contracts[] = $contract;
    }

    $next_payment      = !empty($pending_contracts) ? $pending_contracts[0] : null;
    $available_experts = platform_dashboard_get_available_educators();

    $display_month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : date('n');
    $display_year  = isset($_GET['cal_year'])  ? intval($_GET['cal_year'])  : date('Y');

    $prev_month = $display_month - 1; $prev_year = $display_year;
    if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
    $next_month = $display_month + 1; $next_year = $display_year;
    if ($next_month > 12) { $next_month = 1; $next_year++; }

    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $display_month, $display_year);
    $first_day     = date('w', strtotime("{$display_year}-{$display_month}-01"));

    $sessions_by_date = [];
    foreach ($all_sessions as $session) {
        $date = date('Y-m-d', strtotime($session->class_start_iso ?: $session->proposed_start_iso));
        $sessions_by_date[$date][] = $session;
    }

    // --- Nav URLs ---
    $url_dashboard   = home_url('/platform-dashboard');
    $url_find        = home_url('/find_educators');
    $url_sessions    = get_permalink();
    $url_contracts   = home_url('/contracts-sessions');
    $url_shortlisted = home_url('/shortlisted-educators');

    $nav_avatar = get_avatar_url($user_id, ['size' => 36, 'default' => 'mystery']);

    ob_start();
    ?>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Inter, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }

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

    .container { max-width: 1400px; margin: 24px auto; padding: 0 24px; }
    .grid { display: grid; grid-template-columns: 2.5fr 1fr; gap: 24px; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
    .card-head h3 { font-size: 18px; font-weight: 600; color: #0f172a; }
    .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .calendar-month { font-size: 16px; font-weight: 600; }
    .calendar-nav { display: flex; gap: 8px; }
    .calendar-nav button { padding: 6px 12px; border: 1px solid #e5e7eb; background: #fff; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
    .calendar-nav button:hover { background: #f8fafc; border-color: #cbd5e1; }
    .calendar-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center; font-size: 13px; color: #64748b; font-weight: 600; margin-bottom: 8px; }
    .calendar-dates { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
    .date { padding: 8px 6px; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 12px; text-align: left; min-height: 90px; cursor: pointer; transition: all 0.2s; background: #fff; }
    .date:hover { border-color: #3b82f6; background: #eff6ff; }
    .date.empty { border: none; background: transparent; cursor: default; }
    .date.today { background: #dbeafe; border-color: #3b82f6; }
    .date.has-session { background: #f0fdf4; border-color: #10b981; }
    .date-num { display: block; font-weight: 600; margin-bottom: 6px; color: #0f172a; font-size: 13px; }
    .date-sessions { display: flex; flex-direction: column; gap: 4px; }
    .date-session-item { background: #10b981; color: #fff; padding: 3px 6px; border-radius: 4px; font-size: 10px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; transition: background 0.2s; }
    .date-session-item:hover { background: #059669; }
    .date-session-time { font-weight: 600; display: block; margin-bottom: 1px; }
    .btn { padding: 8px 16px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; font-size: 13px; cursor: pointer; transition: all 0.2s; font-weight: 500; text-decoration: none; display: inline-block; }
    .btn:hover { background: #f8fafc; }
    .btn-dark { background: #0f172a; color: #fff; border: none; }
    .btn-dark:hover { background: #1e293b; }
    .btn-primary { background: #3b82f6; color: #fff; border: none; }
    .btn-primary:hover { background: #2563eb; }
    .btn-sign { background: #7c3aed; color: #fff; border: none; }
    .btn-sign:hover { background: #6d28d9; }
    .btn-small { padding: 6px 12px; font-size: 12px; }
    
    .tabs { display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 2px solid #e5e7eb; }
    .tab { padding: 10px 16px; border-radius: 8px 8px 0 0; border: none; background: transparent; font-size: 14px; cursor: pointer; font-weight: 500; color: #64748b; transition: all 0.2s; }
    .tab.active { color: #0f172a; background: transparent; border-bottom: 2px solid #3b82f6; margin-bottom: -2px; }
    .sessions-list { max-height: 500px; overflow-y: auto; }
    .session { border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; margin-bottom: 12px; transition: all 0.2s; cursor: pointer; }
    .session:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-color: #cbd5e1; transform: translateY(-1px); }
    .session-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
    .session-title { font-weight: 600; font-size: 16px; color: #0f172a; }
    .session-info { font-size: 13px; color: #64748b; margin: 4px 0; display: flex; align-items: center; gap: 6px; }
    .session-info svg { width: 14px; height: 14px; }
    .badge { font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 600; }
    .upcoming { background: #dcfce7; color: #166534; }
    .past { background: #fef3c7; color: #92400e; }
    .session-actions { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
    .empty-state { text-align: center; padding: 40px; color: #64748b; }
    .summary-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 10px; padding: 8px 0; }
    .summary-label { color: #64748b; }
    .summary-value { font-weight: 600; color: #0f172a; }
    .pay-btn { width: 100%; margin-top: 16px; padding: 12px; background: #0f172a; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: block; text-align: center; }
    .pay-btn:hover { background: #1e293b; }
    .educator { display: flex; gap: 12px; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
    .educator:last-child { border-bottom: none; }
    .educator img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
    .educator-info { flex: 1; }
    .educator-name { font-weight: 600; font-size: 14px; color: #0f172a; }
    .educator-spec { font-size: 12px; color: #64748b; margin-top: 2px; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    @media(max-width:768px) { .grid { grid-template-columns: 1fr; } .pc-nav-links { display: none; } }
    </style>

    <nav class="pc-nav">
        <div class="pc-nav-inner">
            <a href="<?php echo esc_url(home_url()); ?>" class="pc-nav-logo">LOGO</a>
            <div class="pc-nav-links">
                <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
                <a href="<?php echo esc_url($url_find); ?>">Find Educators</a>
                <a href="<?php echo esc_url($url_sessions); ?>" class="active">Sessions</a>
                <a href="<?php echo esc_url($url_contracts); ?>">Contracts</a>
                <a href="<?php echo esc_url($url_shortlisted); ?>">Shortlisted</a>
            </div>
            <div class="pc-nav-right">
                <img src="<?php echo esc_url($nav_avatar); ?>" alt="Profile">
                <span class="pc-nav-username">Hi, <?php echo esc_html($user_name); ?></span>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pc-nav-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="grid">
            <div>
                <div class="card">
                    <div class="calendar-header">
                        <h3><?php echo date('F Y', strtotime("{$display_year}-{$display_month}-01")); ?></h3>
                        <div style="display:flex;gap:12px;align-items:center;">
                            <div class="calendar-nav">
                                <button onclick="navigateCalendar(<?php echo $prev_month; ?>, <?php echo $prev_year; ?>)">&#8592; Prev</button>
                                <button onclick="navigateCalendar(<?php echo date('n'); ?>, <?php echo date('Y'); ?>)">Today</button>
                                <button onclick="navigateCalendar(<?php echo $next_month; ?>, <?php echo $next_year; ?>)">Next &#8594;</button>
                            </div>
                            <a href="<?php echo site_url('/college/request-class'); ?>" class="btn btn-dark">+ Book New Session</a>
                        </div>
                    </div>
                    <div class="calendar-days">
                        <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                    </div>
                    <div class="calendar-dates">
                        <?php
                        for ($i = 0; $i < $first_day; $i++) echo '<div class="date empty"></div>';
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date_str    = sprintf('%04d-%02d-%02d', $display_year, $display_month, $day);
                            $is_today    = ($date_str === date('Y-m-d'));
                            $has_session = isset($sessions_by_date[$date_str]);
                            $classes     = ['date'];
                            if ($is_today)    $classes[] = 'today';
                            if ($has_session) $classes[] = 'has-session';
                            echo '<div class="' . implode(' ', $classes) . '">';
                            echo '<span class="date-num">' . $day . '</span>';
                            if ($has_session) {
                                echo '<div class="date-sessions">';
                                $count = 0;
                                foreach ($sessions_by_date[$date_str] as $session) {
                                    if ($count >= 3) { 
                                        echo '<div class="date-session-item">+' . (count($sessions_by_date[$date_str]) - 3) . ' more</div>'; 
                                        break; 
                                    }
                                    $t     = date('g:i A', strtotime($session->class_start_iso ?: $session->proposed_start_iso));
                                    $topic = strlen($session->topic) > 20 ? substr($session->topic, 0, 20) . '...' : $session->topic;
                                    $session_url = esc_url(add_query_arg(['session_id' => $session->id], home_url('/session-info/')));
                                    echo '<div class="date-session-item" onclick="window.location.href=\'' . $session_url . '\';event.stopPropagation();" title="' . esc_attr($session->topic) . '"><span class="date-session-time">' . esc_html($t) . '</span>' . esc_html($topic) . '</div>';
                                    $count++;
                                }
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="card" style="margin-top:20px;">
                    <div class="card-head"><h3>Your Sessions</h3></div>
                    <div class="tabs">
                        <button class="tab active" onclick="switchTab(event,'upcoming')">Upcoming (<?php echo count($upcoming_sessions); ?>)</button>
                        <button class="tab" onclick="switchTab(event,'past')">Past (<?php echo count($past_sessions); ?>)</button>
                    </div>
                    <div id="upcoming" class="tab-content active sessions-list">
                        <?php if (empty($upcoming_sessions)): ?>
                            <div class="empty-state"><p>No upcoming sessions.</p><a href="<?php echo site_url('/college/request-class'); ?>" class="btn btn-primary" style="margin-top:12px;">Book Your First Session</a></div>
                        <?php else: ?>
                            <?php foreach ($upcoming_sessions as $session):
                                $expert       = get_userdata($session->expert_user_id);
                                $session_date = $session->class_start_iso ?: $session->proposed_start_iso;
                                $fmt_date     = date('l, M j, Y', strtotime($session_date));
                                $fmt_time     = date('g:i A', strtotime($session_date));
                                $session_needs_pay = false; $session_pay_url = '';
                                if ($session->status === 'pending_contract' && in_array($session->contract_status, ['generated','awaiting_payment'], true) && (float)$session->total_amount > 0) {
                                    $pmt = $resolve_payment($session->order_id, $session->id);
                                    if (!$pmt['is_paid']) { $session_needs_pay = true; $session_pay_url = $pmt['pay_url']; }
                                }
                                $session_url = esc_url(add_query_arg(['session_id' => $session->id], home_url('/session-info/')));
                            ?>
                            <div class="session" onclick="window.location.href='<?php echo $session_url; ?>'">
                                <div class="session-head">
                                    <div>
                                        <div class="session-title"><?php echo esc_html($session->topic); ?></div>
                                        <div class="session-info"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg><?php echo $expert ? esc_html($expert->display_name) : 'Expert'; ?></div>
                                        <div class="session-info"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><?php echo esc_html($fmt_date); ?> at <?php echo esc_html($fmt_time); ?></div>
                                        <div class="session-info"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><?php echo esc_html($session->duration_minutes); ?> minutes</div>
                                    </div>
                                    <span class="badge upcoming">Upcoming</span>
                                </div>
                                <div class="session-actions" onclick="event.stopPropagation();">
                                    <?php if ($session->status === 'pending_contract' && $session->contract_status === 'generated' && $session->sign_token): ?>
                                        <a href="<?php echo esc_url(add_query_arg(['pc_contract'=>$session->sign_token], site_url('/sign-contract/'))); ?>" class="btn btn-sign btn-small">&#9998; Review &amp; Sign Contract</a>
                                    <?php endif; ?>
                                    <?php if ($session_needs_pay && $session_pay_url): ?>
                                        <a href="<?php echo esc_url($session_pay_url); ?>" class="btn btn-primary btn-small">Complete Payment</a>
                                    <?php endif; ?>
                                    <button class="btn btn-small">Add to Calendar</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div id="past" class="tab-content sessions-list">
                        <?php if (empty($past_sessions)): ?>
                            <div class="empty-state"><p>No past sessions yet.</p></div>
                        <?php else: ?>
                            <?php foreach ($past_sessions as $session):
                                $expert       = get_userdata($session->expert_user_id);
                                $session_date = $session->class_start_iso ?: $session->proposed_start_iso;
                                $fmt_date     = date('l, M j, Y', strtotime($session_date));
                                $fmt_time     = date('g:i A', strtotime($session_date));
                                $session_url = esc_url(add_query_arg(['session_id' => $session->id], home_url('/session-info/')));
                            ?>
                            <div class="session" onclick="window.location.href='<?php echo $session_url; ?>'">
                                <div class="session-head">
                                    <div>
                                        <div class="session-title"><?php echo esc_html($session->topic); ?></div>
                                        <div class="session-info"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg><?php echo $expert ? esc_html($expert->display_name) : 'Expert'; ?></div>
                                        <div class="session-info"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><?php echo esc_html($fmt_date); ?> at <?php echo esc_html($fmt_time); ?></div>
                                        <div class="session-info"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><?php echo esc_html($session->duration_minutes); ?> minutes</div>
                                    </div>
                                    <span class="badge past">Completed</span>
                                </div>
                                <div class="session-actions" onclick="event.stopPropagation();">
                                    <?php if ($session->pdf_path): $uploads=wp_get_upload_dir(); $pdf_url=str_replace($uploads['basedir'],$uploads['baseurl'],$session->pdf_path); ?>
                                        <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="btn btn-small">View Contract</a>
                                    <?php endif; ?>
                                    <button class="btn btn-small">Leave Review</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <h3 style="margin-bottom:16px;">Payment Summary</h3>
                    <?php if ($next_payment): ?>
                        <div class="summary-row"><span class="summary-label">Next Payment</span><strong class="summary-value"><?php echo wc_price($next_payment->total_amount); ?></strong></div>
                        <div class="summary-row"><span class="summary-label">Session</span><strong class="summary-value"><?php echo esc_html($next_payment->topic); ?></strong></div>
                        <div class="summary-row"><span class="summary-label">Due Date</span><strong class="summary-value"><?php echo date('M j, Y', strtotime($next_payment->class_start_iso ?: $next_payment->proposed_start_iso)); ?></strong></div>
                        <?php if (!empty($next_payment->pay_url)): ?>
                            <a href="<?php echo esc_url($next_payment->pay_url); ?>" class="pay-btn">Pay Now</a>
                        <?php elseif ($next_payment->sign_token): ?>
                            <a href="<?php echo esc_url(add_query_arg(['pc_contract'=>$next_payment->sign_token], site_url('/sign-contract/'))); ?>" class="pay-btn" style="background:#7c3aed;">&#9998; Review Contract</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding:20px;"><p>&#10003; No pending payments</p></div>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin-top:20px;">
                    <div class="card-head">
                        <h3>Available Educators</h3>
                        <a href="<?php echo esc_url($url_find); ?>" class="btn btn-small">View All</a>
                    </div>
                    <?php if (empty($available_experts)): ?>
                        <div class="empty-state" style="padding:20px;"><p>No educators available</p></div>
                    <?php else: ?>
                        <?php foreach ($available_experts as $expert):
                            $expert_avatar = get_avatar_url($expert->ID, ['size'=>96]);
                            $speciality    = get_user_meta($expert->ID, '_tutor_instructor_speciality', true);
                            $spec_display  = $speciality ? explode(',', $speciality)[0] : 'Medical Education';
                        ?>
                        <div class="educator">
                            <img src="<?php echo esc_url($expert_avatar); ?>" alt="<?php echo esc_attr($expert->display_name); ?>">
                            <div class="educator-info">
                                <div class="educator-name"><?php echo esc_html($expert->display_name); ?></div>
                                <div class="educator-spec"><?php echo esc_html(trim($spec_display)); ?> <span style="display:inline-block;width:8px;height:8px;background:#10b981;border-radius:50%;margin-left:6px;" title="Available Now"></span></div>
                            </div>
                            <a href="<?php echo esc_url(site_url('/college/request-class?expert_id='.$expert->ID)); ?>" class="btn btn-primary btn-small">Book</a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function switchTab(event, tabName) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
    }
    function navigateCalendar(month, year) {
        const url = new URL(window.location.href);
        url.searchParams.set('cal_month', month);
        url.searchParams.set('cal_year', year);
        window.location.href = url.toString();
    }
    </script>
    <?php
    return ob_get_clean();
}