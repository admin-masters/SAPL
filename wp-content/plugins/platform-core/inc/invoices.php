<?php
/**
 * Expert Invoices Dashboard
 * Shortcode: [platform_expert_invoices]
 * FINAL: Shows invoice details inline, no admin redirects
 */

add_shortcode('platform_expert_invoices', 'platform_render_expert_invoices');

function platform_render_expert_invoices() {
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(get_permalink()));
        exit;
    }

    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id = get_current_user_id();

    if (!current_user_can('expert') && !current_user_can('administrator')) {
        return '<div class="error-message">This page is only accessible to experts.</div>';
    }

    $amelia_users_table = $wpdb->prefix . 'amelia_users';
    $amelia_provider = $wpdb->get_row($wpdb->prepare("
        SELECT id, firstName, lastName, email, type
        FROM {$amelia_users_table}
        WHERE email = %s AND type = 'provider'
        LIMIT 1
    ", $current_user->user_email));

    if (!$amelia_provider) {
        return '<div class="error-message">No Amelia provider account found for your email address.</div>';
    }

    $provider_id = $amelia_provider->id;
    $appointments_table = $wpdb->prefix . 'amelia_appointments';
    $bookings_table = $wpdb->prefix . 'amelia_customer_bookings';
    $payments_table = $wpdb->prefix . 'amelia_payments';
    $services_table = $wpdb->prefix . 'amelia_services';
    $customers_table = $wpdb->prefix . 'amelia_users';

    // Get appointments with full details
    $invoices = $wpdb->get_results($wpdb->prepare("
        SELECT 
            a.id AS appointment_id,
            a.bookingStart,
            a.bookingEnd,
            a.internalNotes,
            s.name AS service_name,
            s.description AS service_description,
            s.price AS service_price,
            s.duration AS service_duration,
            cb.id AS booking_id,
            cb.price AS booking_price,
            cb.persons,
            cb.status AS booking_status,
            cb.info AS booking_info,
            cu.firstName AS customer_first_name,
            cu.lastName AS customer_last_name,
            cu.email AS customer_email,
            cu.phone AS customer_phone,
            p.id AS payment_id,
            p.amount AS payment_amount,
            p.dateTime AS payment_date,
            p.status AS payment_status,
            p.gateway AS payment_gateway,
            p.gatewayTitle AS payment_gateway_title,
            p.transactionId AS transaction_id
        FROM {$appointments_table} a
        INNER JOIN {$bookings_table} cb ON cb.appointmentId = a.id
        INNER JOIN {$services_table} s ON s.id = a.serviceId
        LEFT JOIN {$customers_table} cu ON cu.id = cb.customerId
        LEFT JOIN {$payments_table} p ON p.customerBookingId = cb.id
        WHERE a.providerId = %d
        AND cb.status IN ('approved', 'pending')
        ORDER BY a.bookingStart DESC
    ", $provider_id));

    $events_table = $wpdb->prefix . 'amelia_events';
    $event_bookings_table = $wpdb->prefix . 'amelia_customer_bookings_to_events_periods';
    $event_periods_table = $wpdb->prefix . 'amelia_events_periods';

    // Get events with full details
    $event_invoices = $wpdb->get_results($wpdb->prepare("
        SELECT 
            e.id AS event_id,
            e.name AS event_name,
            e.description AS event_description,
            e.price AS event_price,
            ep.periodStart,
            ep.periodEnd,
            cb.id AS booking_id,
            cb.price AS booking_price,
            cb.persons,
            cb.status AS booking_status,
            cb.info AS booking_info,
            cu.firstName AS customer_first_name,
            cu.lastName AS customer_last_name,
            cu.email AS customer_email,
            cu.phone AS customer_phone,
            p.id AS payment_id,
            p.amount AS payment_amount,
            p.dateTime AS payment_date,
            p.status AS payment_status,
            p.gateway AS payment_gateway,
            p.gatewayTitle AS payment_gateway_title,
            p.transactionId AS transaction_id
        FROM {$events_table} e
        INNER JOIN {$event_periods_table} ep ON ep.eventId = e.id
        INNER JOIN {$event_bookings_table} cbep ON cbep.eventPeriodId = ep.id
        INNER JOIN {$bookings_table} cb ON cb.id = cbep.customerBookingId
        LEFT JOIN {$customers_table} cu ON cu.id = cb.customerId
        LEFT JOIN {$payments_table} p ON p.customerBookingId = cb.id
        WHERE e.id IN (
            SELECT etp.eventId 
            FROM {$wpdb->prefix}amelia_events_to_providers etp 
            WHERE etp.userId = %d
        )
        AND cb.status IN ('approved', 'pending')
        ORDER BY ep.periodStart DESC
    ", $provider_id));

    $all_invoices = [];

    // Process appointments
    foreach ($invoices as $invoice) {
        $actual_payment_status = 'pending';
        if ($invoice->payment_id && $invoice->payment_status) {
            $actual_payment_status = $invoice->payment_status;
        } elseif ($invoice->booking_status === 'approved') {
            $actual_payment_status = 'paid';
        }

        // Try to find the platform_request_id via appointment_id
        $platform_request_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}platform_requests WHERE appointment_id = %d LIMIT 1",
            $invoice->appointment_id
        ));

        $invoice_data = [
            'type' => 'appointment',
            'id' => $invoice->booking_id,
            'appointment_id' => $invoice->appointment_id,
            'platform_request_id' => $platform_request_id,
            'service_name' => $invoice->service_name,
            'service_description' => $invoice->service_description,
            'service_duration' => $invoice->service_duration,
            'date' => $invoice->bookingStart,
            'end_date' => $invoice->bookingEnd,
            'customer_name' => trim($invoice->customer_first_name . ' ' . $invoice->customer_last_name),
            'customer_email' => $invoice->customer_email,
            'customer_phone' => $invoice->customer_phone,
            'amount' => $invoice->booking_price ?: $invoice->service_price,
            'persons' => $invoice->persons,
            'payment_id' => $invoice->payment_id,
            'payment_amount' => $invoice->payment_amount ?: ($invoice->booking_price ?: $invoice->service_price),
            'payment_date' => $invoice->payment_date,
            'payment_status' => $actual_payment_status,
            'payment_gateway' => $invoice->payment_gateway_title ?: $invoice->payment_gateway,
            'transaction_id' => $invoice->transaction_id,
            'booking_status' => $invoice->booking_status,
            'notes' => $invoice->internalNotes,
        ];
        $all_invoices[] = (object)$invoice_data;
    }

    // Process events
    foreach ($event_invoices as $invoice) {
        $actual_payment_status = 'pending';
        if ($invoice->payment_id && $invoice->payment_status) {
            $actual_payment_status = $invoice->payment_status;
        } elseif ($invoice->booking_status === 'approved') {
            $actual_payment_status = 'paid';
        }

        $invoice_data = [
            'type' => 'event',
            'id' => $invoice->booking_id,
            'event_id' => $invoice->event_id,
            'service_name' => $invoice->event_name,
            'service_description' => $invoice->event_description,
            'date' => $invoice->periodStart,
            'end_date' => $invoice->periodEnd,
            'customer_name' => trim($invoice->customer_first_name . ' ' . $invoice->customer_last_name),
            'customer_email' => $invoice->customer_email,
            'customer_phone' => $invoice->customer_phone,
            'amount' => $invoice->booking_price ?: $invoice->event_price,
            'persons' => $invoice->persons,
            'payment_id' => $invoice->payment_id,
            'payment_amount' => $invoice->payment_amount ?: ($invoice->booking_price ?: $invoice->event_price),
            'payment_date' => $invoice->payment_date,
            'payment_status' => $actual_payment_status,
            'payment_gateway' => $invoice->payment_gateway_title ?: $invoice->payment_gateway,
            'transaction_id' => $invoice->transaction_id,
            'booking_status' => $invoice->booking_status,
        ];
        $all_invoices[] = (object)$invoice_data;
    }

    usort($all_invoices, function($a, $b) {
        return strtotime($b->date) - strtotime($a->date);
    });

    $grouped_invoices = [];
    foreach ($all_invoices as $invoice) {
        $month_key = date('F Y', strtotime($invoice->date));
        if (!isset($grouped_invoices[$month_key])) {
            $grouped_invoices[$month_key] = [];
        }
        $grouped_invoices[$month_key][] = $invoice;
    }

    $total_paid = 0;
    $total_pending = 0;
    $total_invoices = count($all_invoices);

    foreach ($all_invoices as $invoice) {
        if ($invoice->payment_status === 'paid') {
            $total_paid += (float)$invoice->payment_amount;
        } else {
            $total_pending += (float)$invoice->amount;
        }
    }

    $url_dashboard = home_url('/platform-dashboard');
    $url_sessions = home_url('/college-sessions');
    $url_invoices = get_permalink();
    $nav_avatar = get_avatar_url($user_id, ['size' => 36]);
    $user_name = $current_user->display_name;

    ob_start();
    ?>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Inter, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }

    .pc-nav{background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);border-bottom:1px solid #e4e7ef;position:sticky;top:0;z-index:200;box-shadow:0 1px 0 #e4e7ef,0 2px 12px rgba(0,0,0,.04);}
    .pc-nav-inner{max-width:1400px;margin:auto;padding:0 36px;height:58px;display:flex;justify-content:space-between;align-items:center;}
    .pc-nav-logo{font-weight:800;color:#4338ca;font-size:20px;text-decoration:none;letter-spacing:-.5px;}
    .pc-nav-links{display:flex;gap:2px;}
    .pc-nav-links a{padding:7px 16px;border-radius:8px;font-size:14px;font-weight:500;color:#6b7280;text-decoration:none;transition:background .18s,color .18s;}
    .pc-nav-links a:hover{background:#eef2ff;color:#4338ca;}
    .pc-nav-links a.active{background:#eef2ff;color:#4338ca;font-weight:600;}
    .pc-nav-right{display:flex;align-items:center;gap:14px;}
    .pc-nav-right img{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;box-shadow:0 0 0 2px #eef2ff;}
    .pc-nav-username{font-weight:600;font-size:13px;color:#0f172a;}
    .pc-nav-btn{padding:7px 18px;border-radius:8px;font-size:13px;font-weight:600;background:#0f172a;color:#fff;text-decoration:none;transition:opacity .15s;}
    .pc-nav-btn:hover{opacity:.88;}

    .container { max-width: 1400px; margin: 24px auto; padding: 0 24px; }
    .page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 40px; border-radius: 20px; margin-bottom: 32px; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.2); }
    .page-title { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
    .page-subtitle { font-size: 16px; opacity: 0.9; }

    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 32px; }
    .stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .stat-label { font-size: 14px; color: #64748b; font-weight: 500; margin-bottom: 8px; }
    .stat-value { font-size: 32px; font-weight: 700; color: #0f172a; }
    .stat-value.paid { color: #10b981; }
    .stat-value.pending { color: #f59e0b; }

    .month-section { margin-bottom: 32px; }
    .month-header { background: #f8fafc; padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
    .month-title { font-size: 18px; font-weight: 600; color: #0f172a; }
    .month-count { font-size: 14px; color: #64748b; }

    .invoice-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 20px; margin-bottom: 12px; transition: all 0.2s; }
    .invoice-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: #cbd5e1; }
    .invoice-card.expanded { box-shadow: 0 8px 24px rgba(0,0,0,0.12); }

    .invoice-summary { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
    .invoice-main { flex: 1; }
    .invoice-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .invoice-type { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .invoice-type.appointment { background: #dbeafe; color: #1e40af; }
    .invoice-type.event { background: #fae8ff; color: #86198f; }
    .invoice-title { font-size: 16px; font-weight: 600; color: #0f172a; }

    .invoice-details { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 8px; }
    .invoice-detail { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #64748b; }
    .invoice-detail svg { width: 14px; height: 14px; }

    .invoice-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
    .invoice-amount { font-size: 24px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
    .invoice-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .invoice-status.paid { background: #dcfce7; color: #166534; }
    .invoice-status.pending { background: #fef3c7; color: #92400e; }

    .invoice-btn-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }

    .invoice-full-details { display: none; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f1f5f9; }
    .invoice-full-details.visible { display: block; }
    
    .detail-section { background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 12px; }
    .detail-section-title { font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 12px; }
    .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { color: #64748b; font-size: 13px; }
    .detail-value { color: #0f172a; font-weight: 500; font-size: 13px; }

    .btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; border: none; }
    .btn:hover { transform: translateY(-1px); }
    .btn svg { width: 14px; height: 14px; }
    .btn-primary { background: #6366f1; color: #fff; }
    .btn-primary:hover { background: #4f46e5; }
    .btn-secondary { background: #e5e7eb; color: #0f172a; }
    .btn-secondary:hover { background: #d1d5db; }
    .btn-view-details { background: #0f172a; color: #fff; }
    .btn-view-details:hover { background: #1e293b; }

    .empty-state { text-align: center; padding: 60px 20px; background: #fff; border-radius: 16px; border: 2px dashed #e5e7eb; }
    .empty-state svg { width: 64px; height: 64px; color: #cbd5e1; margin-bottom: 16px; }
    .empty-state h3 { font-size: 20px; font-weight: 600; color: #0f172a; margin-bottom: 8px; }
    .empty-state p { color: #64748b; font-size: 14px; }

    .error-message { background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; padding: 20px; border-radius: 12px; text-align: center; font-weight: 600; }

    @media(max-width:768px) {
        .stats-grid { grid-template-columns: 1fr; }
        .pc-nav-links { display: none; }
        .invoice-summary { flex-direction: column; align-items: flex-start; }
        .invoice-actions { width: 100%; flex-direction: row; justify-content: space-between; margin-top: 12px; }
        .invoice-btn-row { flex-wrap: wrap; }
    }
    </style>

    <script>
    function toggleInvoice(invoiceId) {
        const detailsEl = document.getElementById('invoice-details-' + invoiceId);
        const cardEl = detailsEl.closest('.invoice-card');
        
        if (detailsEl.classList.contains('visible')) {
            detailsEl.classList.remove('visible');
            cardEl.classList.remove('expanded');
        } else {
            detailsEl.classList.add('visible');
            cardEl.classList.add('expanded');
        }
    }
    </script>

    <nav class="pc-nav">
        <div class="pc-nav-inner">
            <a href="<?php echo esc_url(home_url()); ?>" class="pc-nav-logo">LOGO</a>
            <div class="pc-nav-links">
                <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
                <a href="<?php echo esc_url($url_sessions); ?>">Sessions</a>
                <a href="<?php echo esc_url($url_invoices); ?>" class="active">Invoices</a>
            </div>
            <div class="pc-nav-right">
                <img src="<?php echo esc_url($nav_avatar); ?>" alt="Profile">
                <span class="pc-nav-username">Hi, <?php echo esc_html($user_name); ?></span>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pc-nav-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Invoices & Payments</h1>
            <p class="page-subtitle">View all your appointment and webinar invoices</p>
        </div>

        <?php if (empty($all_invoices)): ?>
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3>No Invoices Yet</h3>
                <p>Your invoices will appear here once you have appointments or webinar bookings.</p>
            </div>
        <?php else: ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Invoices</div>
                    <div class="stat-value"><?php echo number_format($total_invoices); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Paid</div>
                    <div class="stat-value paid"><?php echo wc_price($total_paid); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Payment</div>
                    <div class="stat-value pending"><?php echo wc_price($total_pending); ?></div>
                </div>
            </div>

            <?php foreach ($grouped_invoices as $month => $invoices_list): ?>
                <div class="month-section">
                    <div class="month-header">
                        <div class="month-title"><?php echo esc_html($month); ?></div>
                        <div class="month-count"><?php echo count($invoices_list); ?> invoice<?php echo count($invoices_list) !== 1 ? 's' : ''; ?></div>
                    </div>

                    <?php foreach ($invoices_list as $invoice): 
                        $invoice_date = date('M j, Y', strtotime($invoice->date));
                        $invoice_time = date('g:i A', strtotime($invoice->date));
                        $invoice_unique_id = $invoice->type . '-' . $invoice->id;

                        // Build the "View Details" URL
                        if ($invoice->type === 'event' && !empty($invoice->event_id)) {
                            $details_url = add_query_arg('event-id', $invoice->event_id, home_url('/webinar-info'));
                            $details_label = 'View Webinar';
                        } elseif ($invoice->type === 'appointment') {
                            // Prefer platform_request_id if found, fall back to appointment_id
                            if (!empty($invoice->platform_request_id)) {
                                $details_url = add_query_arg('session_id', $invoice->platform_request_id, home_url('/session-info/'));
                            } elseif (!empty($invoice->appointment_id)) {
                                $details_url = add_query_arg('session_id', $invoice->appointment_id, home_url('/session-info/'));
                            } else {
                                $details_url = '';
                            }
                            $details_label = 'View Session';
                        } else {
                            $details_url = '';
                            $details_label = '';
                        }
                    ?>
                        <div class="invoice-card">
                            <div class="invoice-summary" onclick="toggleInvoice('<?php echo esc_js($invoice_unique_id); ?>')">
                                <div class="invoice-main">
                                    <div class="invoice-header">
                                        <span class="invoice-type <?php echo esc_attr($invoice->type); ?>">
                                            <?php if ($invoice->type === 'appointment'): ?>
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:12px;height:12px;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                Appointment
                                            <?php else: ?>
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:12px;height:12px;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                                Webinar
                                            <?php endif; ?>
                                        </span>
                                        <h3 class="invoice-title"><?php echo esc_html($invoice->service_name); ?></h3>
                                    </div>
                                    <div class="invoice-details">
                                        <div class="invoice-detail">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <?php echo esc_html($invoice->customer_name ?: $invoice->customer_email); ?>
                                        </div>
                                        <div class="invoice-detail">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <?php echo esc_html($invoice_date); ?> at <?php echo esc_html($invoice_time); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="invoice-actions">
                                    <div class="invoice-amount"><?php echo wc_price($invoice->payment_amount ?: $invoice->amount); ?></div>
                                    <span class="invoice-status <?php echo esc_attr($invoice->payment_status ?: 'pending'); ?>">
                                        <?php echo esc_html(ucfirst($invoice->payment_status ?: 'Pending')); ?>
                                    </span>
                                    <div class="invoice-btn-row">
                                        <button class="btn btn-primary" onclick="event.stopPropagation(); toggleInvoice('<?php echo esc_js($invoice_unique_id); ?>')">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View Invoice
                                        </button>
                                        <?php if ($details_url): ?>
                                        <a href="<?php echo esc_url($details_url); ?>"
                                           class="btn btn-view-details"
                                           onclick="event.stopPropagation()"
                                           target="_blank">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                            <?php echo esc_html($details_label); ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div id="invoice-details-<?php echo esc_attr($invoice_unique_id); ?>" class="invoice-full-details">
                                <div class="detail-section">
                                    <div class="detail-section-title">Customer Information</div>
                                    <div class="detail-row">
                                        <span class="detail-label">Name</span>
                                        <span class="detail-value"><?php echo esc_html($invoice->customer_name); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Email</span>
                                        <span class="detail-value"><?php echo esc_html($invoice->customer_email); ?></span>
                                    </div>
                                    <?php if ($invoice->customer_phone): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Phone</span>
                                        <span class="detail-value"><?php echo esc_html($invoice->customer_phone); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="detail-section">
                                    <div class="detail-section-title">Booking Details</div>
                                    <div class="detail-row">
                                        <span class="detail-label">Service</span>
                                        <span class="detail-value"><?php echo esc_html($invoice->service_name); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Date & Time</span>
                                        <span class="detail-value"><?php echo esc_html($invoice_date . ' at ' . $invoice_time); ?></span>
                                    </div>
                                    <?php if ($invoice->persons > 1): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Number of Persons</span>
                                        <span class="detail-value"><?php echo esc_html($invoice->persons); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Booking Status</span>
                                        <span class="detail-value"><?php echo esc_html(ucfirst($invoice->booking_status)); ?></span>
                                    </div>
                                    <?php if ($details_url): ?>
                                    <div class="detail-row">
                                        <span class="detail-label"><?php echo $invoice->type === 'event' ? 'Webinar Page' : 'Session Page'; ?></span>
                                        <span class="detail-value">
                                            <a href="<?php echo esc_url($details_url); ?>" target="_blank"
                                               style="color:#6366f1;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                                <?php echo esc_html($details_label); ?>
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:12px;height:12px;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                            </a>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="detail-section">
                                    <div class="detail-section-title">Payment Information</div>
                                    <div class="detail-row">
                                        <span class="detail-label">Amount</span>
                                        <span class="detail-value"><?php echo wc_price($invoice->payment_amount ?: $invoice->amount); ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Payment Status</span>
                                        <span class="detail-value"><?php echo esc_html(ucfirst($invoice->payment_status)); ?></span>
                                    </div>
                                    <?php if ($invoice->payment_gateway): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Payment Method</span>
                                        <span class="detail-value"><?php echo esc_html($invoice->payment_gateway); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($invoice->payment_date): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Payment Date</span>
                                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($invoice->payment_date)); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($invoice->transaction_id): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Transaction ID</span>
                                        <span class="detail-value"><?php echo esc_html($invoice->transaction_id); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php
    return ob_get_clean();
}