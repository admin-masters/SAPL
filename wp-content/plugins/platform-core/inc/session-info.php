<?php
/**
 * Session Info Page
 * Shortcode: [platform_session_info]
 * URL: /session-info/?session_id=XXX or /session-info/?contract_id=XXX
 */

add_shortcode('platform_session_info', 'platform_render_session_info_page');

function platform_render_session_info_page() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(get_permalink()));
        exit;
    }

    global $wpdb;
    $user_id = get_current_user_id();

    $session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
    $contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

    if (!$session_id && !$contract_id) {
        return '<div class="error-message">Invalid session. Please provide a session_id or contract_id parameter.</div>';
    }

    $tbl_requests = $wpdb->prefix . 'platform_requests';
    $tbl_contracts = $wpdb->prefix . 'platform_contracts';
    $tbl_calendar = $wpdb->prefix . 'platform_calendar_map';

    if ($session_id) {
        $query = $wpdb->prepare("
            SELECT 
                r.id AS request_id,
                r.topic,
                r.description,
                r.proposed_start_iso,
                r.duration_minutes,
                r.capacity,
                r.college_user_id,
                r.expert_user_id,
                r.status AS request_status,
                r.appointment_id,
                c.id AS contract_id,
                c.status AS contract_status,
                c.total_amount,
                c.class_start_iso,
                c.pdf_path,
                c.sign_token,
                c.signed_at,
                c.order_id,
                COALESCE(cal1.zoom_url, cal2.zoom_url, cal3.zoom_url) AS zoom_url,
                amelia.zoomMeeting AS amelia_zoom_json
            FROM {$tbl_requests} r
            LEFT JOIN {$tbl_contracts} c ON c.request_id = r.id
            LEFT JOIN {$tbl_calendar} cal1 ON cal1.object_id = r.id AND cal1.source = 'platform_request'
            LEFT JOIN {$tbl_calendar} cal2 ON cal2.object_id = r.appointment_id AND cal2.source = 'amelia_appointment'
            LEFT JOIN {$tbl_calendar} cal3 ON cal3.object_id = c.id AND cal3.source = 'platform_contract'
            LEFT JOIN {$wpdb->prefix}amelia_appointments amelia ON amelia.id = r.appointment_id
            WHERE r.id = %d
            LIMIT 1
        ", $session_id);
    } else {
        $query = $wpdb->prepare("
            SELECT 
                r.id AS request_id,
                r.topic,
                r.description,
                r.proposed_start_iso,
                r.duration_minutes,
                r.capacity,
                r.college_user_id,
                r.expert_user_id,
                r.status AS request_status,
                r.appointment_id,
                c.id AS contract_id,
                c.status AS contract_status,
                c.total_amount,
                c.class_start_iso,
                c.pdf_path,
                c.sign_token,
                c.signed_at,
                c.order_id,
                COALESCE(cal1.zoom_url, cal2.zoom_url, cal3.zoom_url) AS zoom_url,
                amelia.zoomMeeting AS amelia_zoom_json
            FROM {$tbl_contracts} c
            INNER JOIN {$tbl_requests} r ON r.id = c.request_id
            LEFT JOIN {$tbl_calendar} cal1 ON cal1.object_id = r.id AND cal1.source = 'platform_request'
            LEFT JOIN {$tbl_calendar} cal2 ON cal2.object_id = r.appointment_id AND cal2.source = 'amelia_appointment'
            LEFT JOIN {$tbl_calendar} cal3 ON cal3.object_id = c.id AND cal3.source = 'platform_contract'
            LEFT JOIN {$wpdb->prefix}amelia_appointments amelia ON amelia.id = r.appointment_id
            WHERE c.id = %d
            LIMIT 1
        ", $contract_id);
    }

    $session = $wpdb->get_row($query);

    if ($session && empty($session->zoom_url) && !empty($session->amelia_zoom_json)) {
        $amelia_zoom_data = json_decode($session->amelia_zoom_json, true);
        if (is_array($amelia_zoom_data)) {
            $possible_keys = ['joinUrl', 'join_url', 'startUrl', 'start_url', 'url'];
            foreach ($possible_keys as $key) {
                if (!empty($amelia_zoom_data[$key])) {
                    $session->zoom_url = $amelia_zoom_data[$key];
                    break;
                }
            }
        }
    }

    if (!$session) {
        return '<div class="error-message">Session not found.</div>';
    }

    $is_admin = current_user_can('manage_options') || current_user_can('administrator');
    $has_access = ($session->college_user_id == $user_id) || 
                  ($session->expert_user_id == $user_id) || 
                  $is_admin;

    if (!$has_access) {
        return '<div class="error-message">You do not have permission to view this session.</div>';
    }

    $is_college = ($session->college_user_id == $user_id);
    $is_expert = ($session->expert_user_id == $user_id);

    $expert_data = get_userdata($session->expert_user_id);
    $expert_name = $expert_data ? $expert_data->display_name : 'Expert';
    $expert_email = $expert_data ? $expert_data->user_email : '';
    $expert_avatar = get_avatar_url($session->expert_user_id, ['size' => 80]);
    $expert_bio = get_user_meta($session->expert_user_id, 'description', true);

    $session_datetime = new DateTime($session->class_start_iso ?: $session->proposed_start_iso);
    $formatted_date = $session_datetime->format('F j, Y');
    $formatted_time = $session_datetime->format('g:i A');
    $end_datetime = clone $session_datetime;
    $end_datetime->add(new DateInterval('PT' . $session->duration_minutes . 'M'));
    $formatted_end_time = $end_datetime->format('g:i A');

    $now = new DateTime();
    $time_until = $session_datetime->getTimestamp() - $now->getTimestamp();
    $time_since = $now->getTimestamp() - $session_datetime->getTimestamp();

    if ($time_until > 900) {
        $time_status = 'upcoming';
        $time_status_text = 'Upcoming';
    } elseif ($time_until > 0) {
        $time_status = 'soon';
        $time_status_text = 'Starting Soon';
    } elseif ($time_since < ($session->duration_minutes * 60)) {
        $time_status = 'live';
        $time_status_text = 'Live Now';
    } else {
        $time_status = 'completed';
        $time_status_text = 'Completed';
    }

    $contract_status_label = 'Pending';
    $contract_status_class = 'pending';
    if ($session->signed_at) {
        $contract_status_label = 'Signed';
        $contract_status_class = 'signed';
    }

    $payment_status = 'pending';
    $payment_label = 'Payment Pending';
    if ($session->order_id) {
        $payment_status = 'paid';
        $payment_label = 'Payment Complete';
    }

    ob_start();
    ?>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Inter, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
    
    .session-info-container { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }
    
    .session-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 32px; border-radius: 20px; margin-bottom: 32px; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.2); }
    .session-header-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .session-title { font-size: 32px; font-weight: 700; margin-bottom: 16px; line-height: 1.2; }
    .session-meta { display: flex; gap: 24px; flex-wrap: wrap; }
    .session-meta-item { display: flex; align-items: center; gap: 8px; font-size: 15px; }
    .session-meta svg { width: 18px; height: 18px; }
    .status-badge { padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
    .status-badge.upcoming { background: rgba(255, 255, 255, 0.2); }
    .status-badge.soon { background: #fef3c7; color: #92400e; }
    .status-badge.live { background: #dc2626; animation: pulse 2s infinite; }
    .status-badge.completed { background: rgba(255, 255, 255, 0.15); }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
    
    .session-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
    
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; }
    .card-title { font-size: 18px; font-weight: 600; color: #0f172a; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
    
    .expert-profile { display: flex; gap: 20px; }
    .expert-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 4px solid #eef2ff; }
    .expert-info { flex: 1; }
    .expert-name { font-size: 20px; font-weight: 600; color: #0f172a; margin-bottom: 4px; }
    .expert-email { color: #64748b; font-size: 14px; margin-bottom: 12px; }
    .expert-bio { color: #475569; font-size: 14px; line-height: 1.6; }
    
    .timeline { position: relative; padding-left: 30px; }
    .timeline-item { position: relative; padding-bottom: 24px; }
    .timeline-item::before { content: ''; position: absolute; left: -23px; top: 6px; width: 12px; height: 12px; border-radius: 50%; background: #6366f1; border: 3px solid #fff; box-shadow: 0 0 0 2px #6366f1; }
    .timeline-item::after { content: ''; position: absolute; left: -18px; top: 18px; width: 2px; height: calc(100% - 12px); background: #e5e7eb; }
    .timeline-item:last-child::after { display: none; }
    .timeline-item.completed::before { background: #10b981; box-shadow: 0 0 0 2px #10b981; }
    .timeline-item.pending::before { background: #e5e7eb; box-shadow: 0 0 0 2px #e5e7eb; }
    .timeline-content { background: #f8fafc; padding: 12px 16px; border-radius: 8px; }
    .timeline-title { font-weight: 600; font-size: 14px; color: #0f172a; margin-bottom: 4px; }
    .timeline-time { font-size: 12px; color: #64748b; }
    
    .payment-status { padding: 16px; border-radius: 12px; margin-bottom: 16px; }
    .payment-status.paid { background: #d1fae5; border: 2px solid #10b981; }
    .payment-status.pending { background: #fef3c7; border: 2px solid #f59e0b; }
    .payment-amount { font-size: 28px; font-weight: 700; color: #0f172a; }
    .payment-label { font-size: 13px; color: #64748b; margin-bottom: 8px; }
    
    .action-buttons { display: flex; flex-direction: column; gap: 12px; margin-top: 20px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: all 0.2s; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .btn svg { width: 16px; height: 16px; }
    .btn-primary { background: #6366f1; color: #fff; }
    .btn-primary:hover { background: #4f46e5; }
    .btn-success { background: #10b981; color: #fff; }
    .btn-success:hover { background: #059669; }
    .btn-warning { background: #f59e0b; color: #fff; }
    .btn-warning:hover { background: #d97706; }
    .btn-secondary { background: #e5e7eb; color: #0f172a; }
    .btn-secondary:hover { background: #d1d5db; }
    
    .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: #64748b; font-size: 14px; }
    .info-value { font-weight: 600; color: #0f172a; font-size: 14px; }
    
    .error-message { background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; padding: 20px; border-radius: 12px; text-align: center; font-weight: 600; }
    
    @media(max-width:768px) { .session-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="session-info-container">
        <div class="session-header">
            <div class="session-header-top">
                <div>
                    <h1 class="session-title"><?php echo esc_html($session->topic); ?></h1>
                    <div class="session-meta">
                        <div class="session-meta-item">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <?php echo esc_html($formatted_date); ?>
                        </div>
                        <div class="session-meta-item">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <?php echo esc_html($formatted_time); ?> - <?php echo esc_html($formatted_end_time); ?>
                        </div>
                        <div class="session-meta-item">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <?php echo esc_html($session->duration_minutes); ?> minutes
                        </div>
                    </div>
                </div>
                <span class="status-badge <?php echo esc_attr($time_status); ?>">
                    <?php if ($time_status === 'live'): ?>
                        <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>
                    <?php endif; ?>
                    <?php echo esc_html($time_status_text); ?>
                </span>
            </div>
        </div>

        <div class="session-grid">
            <div>
                <?php if ($session->description): ?>
                <div class="card">
                    <h2 class="card-title">Session Description</h2>
                    <p style="color: #475569; line-height: 1.7;"><?php echo nl2br(esc_html($session->description)); ?></p>
                </div>
                <?php endif; ?>

                <div class="card">
                    <h2 class="card-title">Session Details</h2>
                    <div class="info-row">
                        <span class="info-label">Capacity</span>
                        <span class="info-value"><?php echo esc_html($session->capacity); ?> students</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Duration</span>
                        <span class="info-value"><?php echo esc_html($session->duration_minutes); ?> minutes</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Session Status</span>
                        <span class="info-value"><?php echo esc_html(ucfirst($session->request_status)); ?></span>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Expert Information</h2>
                    <div class="expert-profile">
                        <img src="<?php echo esc_url($expert_avatar); ?>" alt="<?php echo esc_attr($expert_name); ?>" class="expert-avatar">
                        <div class="expert-info">
                            <div class="expert-name"><?php echo esc_html($expert_name); ?></div>
                            <div class="expert-email"><?php echo esc_html($expert_email); ?></div>
                            <?php if ($expert_bio): ?>
                                <p class="expert-bio"><?php echo esc_html($expert_bio); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <?php if ($session->total_amount): ?>
                <div class="card">
                    <h2 class="card-title">Payment Information</h2>
                    <div class="payment-status <?php echo esc_attr($payment_status); ?>">
                        <div class="payment-label"><?php echo esc_html($payment_label); ?></div>
                        <div class="payment-amount"><?php echo wc_price($session->total_amount); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <h2 class="card-title">Timeline</h2>
                    <div class="timeline">
                        <div class="timeline-item completed">
                            <div class="timeline-content">
                                <div class="timeline-title">Session Requested</div>
                                <div class="timeline-time">Request submitted</div>
                            </div>
                        </div>
                        <?php if ($session->contract_id): ?>
                        <div class="timeline-item <?php echo $session->signed_at ? 'completed' : 'pending'; ?>">
                            <div class="timeline-content">
                                <div class="timeline-title">Contract <?php echo $session->signed_at ? 'Signed' : 'Pending'; ?></div>
                                <div class="timeline-time">
                                    <?php if ($session->signed_at): ?>
                                        Signed on <?php echo date('M j, Y', strtotime($session->signed_at)); ?>
                                    <?php else: ?>
                                        Awaiting signature
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="timeline-item <?php echo $payment_status === 'paid' ? 'completed' : 'pending'; ?>">
                            <div class="timeline-content">
                                <div class="timeline-title">Payment <?php echo $payment_status === 'paid' ? 'Complete' : 'Pending'; ?></div>
                                <div class="timeline-time">
                                    <?php if ($payment_status === 'paid'): ?>
                                        Payment received
                                    <?php else: ?>
                                        Awaiting payment
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo $time_status === 'completed' ? 'completed' : 'pending'; ?>">
                            <div class="timeline-content">
                                <div class="timeline-title">Session <?php echo $time_status === 'completed' ? 'Completed' : 'Scheduled'; ?></div>
                                <div class="timeline-time"><?php echo esc_html($formatted_date); ?> at <?php echo esc_html($formatted_time); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Actions</h2>
                    <div class="action-buttons">
                        <?php if ($session->pdf_path && $session->contract_id): ?>
                            <a href="<?php echo esc_url(home_url('/wp-content/uploads/platform-contracts/contract-' . $session->contract_id . '.html')); ?>" class="btn btn-primary" target="_blank">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                View Contract
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($session->sign_token && !$session->signed_at && $is_college): ?>
                            <a href="<?php echo esc_url(home_url('/sign-contract/?pc_contract=' . $session->sign_token)); ?>" class="btn btn-warning">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                                Sign Contract
                            </a>
                        <?php endif; ?>
                        
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}