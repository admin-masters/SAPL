<?php
/**
 * Free Webinar Registration Page
 * Shortcode: [free_webinar_registration]
 * Usage: /free-webinar-registration?event-id=123
 */

if (!defined('ABSPATH')) exit;

add_shortcode('free_webinar_registration', 'render_free_webinar_registration');

function render_free_webinar_registration() {
    if (!is_user_logged_in()) {
        auth_redirect();
        return;
    }

    $event_id = absint($_GET['event-id'] ?? 0);
    if (!$event_id) {
        return '<div style="padding:20px;color:#dc2626;">No event ID provided.</div>';
    }

    // Fetch event details to check if it's actually free
    global $wpdb;
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT e.name, COALESCE(e.price, 0) AS price FROM {$wpdb->prefix}amelia_events e WHERE e.id = %d",
        $event_id
    ));

    if (!$event) {
        return '<div style="padding:20px;color:#dc2626;">Event not found.</div>';
    }

    // If event is paid, redirect to paid registration page
    $price = floatval($event->price);
    if ($price > 0) {
        $paid_url = add_query_arg('event-id', $event_id, home_url('/paid-webinar-payment'));
        wp_redirect($paid_url);
        exit;
    }


    // If paid event and user has already completed payment, redirect to webinar info
$already_registered = $wpdb->get_var($wpdb->prepare(
    "SELECT cb.id 
     FROM {$wpdb->prefix}amelia_customer_bookings cb
     INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods cbep 
         ON cbep.customerBookingId = cb.id
     INNER JOIN {$wpdb->prefix}amelia_events_periods ep 
         ON ep.id = cbep.eventPeriodId
     INNER JOIN {$wpdb->prefix}amelia_users au 
         ON au.id = cb.customerId
     WHERE ep.eventId = %d
       AND au.email = %s
       AND cb.status = 'approved'
     LIMIT 1",
    $event_id,
    wp_get_current_user()->user_email
));

if ($already_registered) {
    wp_redirect(add_query_arg('event-id', $event_id, home_url('/webinar-info')));
    exit;
}

    $current_user = wp_get_current_user();
    $user_name = $current_user->display_name;
    $user_avatar = get_avatar_url($current_user->ID, ['size' => 40]);

    $event_name = $event->name;

    ob_start();
    ?>
    <style>
    /* Reset */
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    #wpadminbar{display:none!important;}
    html{margin-top:0!important;}
    header,#masthead,.site-header,.main-header,#header,.elementor-location-header,
    .ast-main-header-wrap,#site-header,.fusion-header-wrapper,.header-wrap,
    .nav-primary,.navbar,div[data-elementor-type="header"]{display:none!important;}
    .site-content,.site-main,#content,#page{margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;}

    /* Variables */
    :root{
        --primary:#2563eb;
        --primary-dark:#1d4ed8;
        --bg:#f8f9fc;
        --card:#ffffff;
        --text:#1f2937;
        --text-light:#6b7280;
        --border:#e5e7eb;
        --success:#10b981;
        --disabled:#f3f4f6;
        --disabled-text:#9ca3af;
    }

    body{
        font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        background:var(--bg);
        color:var(--text);
        line-height:1.6;
    }

    /* Header */
    .fwr-header{
        background:var(--card);
        border-bottom:1px solid var(--border);
        padding:16px 32px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        position:sticky;
        top:0;
        z-index:100;
    }
    .fwr-logo{
        display:flex;
        align-items:center;
        gap:8px;
        font-size:20px;
        font-weight:700;
        color:var(--primary);
        text-decoration:none;
    }
    .fwr-logo-icon{
        width:32px;
        height:32px;
        background:var(--primary);
        border-radius:8px;
        display:flex;
        align-items:center;
        justify-content:center;
        color:white;
        font-weight:900;
    }
    .fwr-user{
        display:flex;
        align-items:center;
        gap:12px;
    }
    .fwr-user img{
        width:40px;
        height:40px;
        border-radius:50%;
        border:2px solid var(--border);
    }
    .fwr-user-name{
        font-size:14px;
        font-weight:600;
        color:var(--text);
    }
    .fwr-bell{
        position:relative;
        width:40px;
        height:40px;
        border-radius:50%;
        background:var(--bg);
        display:flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        transition:background 0.2s;
    }
    .fwr-bell:hover{background:#e5e7eb;}
    .fwr-bell-dot{
        position:absolute;
        top:8px;
        right:8px;
        width:8px;
        height:8px;
        background:#ef4444;
        border-radius:50%;
        border:2px solid var(--card);
    }

    /* Container */
    .fwr-container{
        max-width:1200px;
        margin:40px auto;
        padding:0 32px;
    }

    /* Back Button */
    .fwr-back{
        display:inline-flex;
        align-items:center;
        gap:8px;
        color:var(--text);
        text-decoration:none;
        font-size:14px;
        font-weight:500;
        margin-bottom:24px;
        transition:color 0.2s;
    }
    .fwr-back:hover{color:var(--primary);}
    .fwr-back svg{width:20px;height:20px;}

    /* Title */
    .fwr-title{
        font-size:28px;
        font-weight:700;
        color:var(--text);
        margin-bottom:32px;
    }

    /* Grid */
    .fwr-grid{
        display:grid;
        grid-template-columns:1fr 380px;
        gap:24px;
        align-items:start;
    }

    /* Card */
    .fwr-card{
        background:var(--card);
        border:1px solid var(--border);
        border-radius:12px;
        padding:32px;
    }

    /* Section Title */
    .fwr-section-title{
        font-size:18px;
        font-weight:700;
        color:var(--text);
        margin-bottom:20px;
    }

    /* Payment Methods */
    .fwr-payment-label{
        font-size:14px;
        font-weight:600;
        color:var(--text);
        margin-bottom:16px;
        display:block;
    }
    .fwr-payment-grid{
        display:grid;
        grid-template-columns:repeat(2,1fr);
        gap:12px;
        margin-bottom:32px;
    }
    .fwr-payment-option{
        position:relative;
        border:2px solid var(--border);
        border-radius:8px;
        padding:16px;
        cursor:not-allowed;
        opacity:0.5;
        background:var(--disabled);
        transition:all 0.2s;
    }
    .fwr-payment-option input{
        position:absolute;
        opacity:0;
        pointer-events:none;
    }
    .fwr-payment-option-content{
        display:flex;
        align-items:center;
        gap:12px;
    }
    .fwr-payment-radio{
        width:20px;
        height:20px;
        border:2px solid var(--border);
        border-radius:50%;
        flex-shrink:0;
        background:var(--disabled);
    }
    .fwr-payment-info{
        flex:1;
    }
    .fwr-payment-name{
        font-size:14px;
        font-weight:600;
        color:var(--disabled-text);
        margin-bottom:2px;
    }
    .fwr-payment-desc{
        font-size:12px;
        color:var(--disabled-text);
    }
    .fwr-payment-icon{
        width:32px;
        height:32px;
        background:var(--disabled);
        border-radius:6px;
        flex-shrink:0;
    }

    /* Order Summary */
    .fwr-summary-row{
        display:flex;
        justify-content:space-between;
        align-items:center;
        padding:12px 0;
        border-bottom:1px solid var(--border);
    }
    .fwr-summary-row:last-child{
        border-bottom:none;
        padding-top:16px;
        margin-top:8px;
        font-weight:700;
        font-size:16px;
    }
    .fwr-summary-label{
        font-size:14px;
        color:var(--text-light);
    }
    .fwr-summary-value{
        font-size:14px;
        font-weight:600;
        color:var(--text);
    }
    .fwr-free-tag{
        color:var(--success);
        font-weight:700;
    }

    /* Register Button */
    .fwr-register-btn{
        width:100%;
        padding:16px;
        background:var(--primary);
        color:white;
        border:none;
        border-radius:8px;
        font-size:16px;
        font-weight:600;
        cursor:pointer;
        transition:background 0.2s;
        margin-top:24px;
    }
    .fwr-register-btn:hover{
        background:var(--primary-dark);
    }
    .fwr-register-note{
        text-align:center;
        font-size:12px;
        color:var(--text-light);
        margin-top:12px;
    }

    /* Security Info */
    .fwr-security-item{
        display:flex;
        align-items:flex-start;
        gap:12px;
        margin-bottom:16px;
    }
    .fwr-security-item:last-child{margin-bottom:0;}
    .fwr-security-icon{
        width:20px;
        height:20px;
        flex-shrink:0;
        margin-top:2px;
    }
    .fwr-security-content h4{
        font-size:14px;
        font-weight:600;
        color:var(--text);
        margin-bottom:4px;
    }
    .fwr-security-content p{
        font-size:13px;
        color:var(--text-light);
        line-height:1.5;
    }

    /* Responsive */
    @media(max-width:968px){
        .fwr-grid{
            grid-template-columns:1fr;
        }
        .fwr-container{padding:0 16px;}
        .fwr-header{padding:12px 16px;}
    }
    </style>

    <div class="fwr-header">
        <a href="<?php echo esc_url(home_url()); ?>" class="fwr-logo">
            <div class="fwr-logo-icon">L</div>
            <span>LOGO</span>
        </a>
        <div class="fwr-user">
            <div class="fwr-bell">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <div class="fwr-bell-dot"></div>
            </div>
            <img src="<?php echo esc_url($user_avatar); ?>" alt="<?php echo esc_attr($user_name); ?>">
            <span class="fwr-user-name"><?php echo esc_html($user_name); ?></span>
        </div>
    </div>

    <div class="fwr-container">
        <a href="javascript:history.back()" class="fwr-back">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            <?php echo esc_html($event_name); ?>
        </a>

        <div class="fwr-grid">
            <!-- Left Column -->
            <div class="fwr-card">
                <h2 class="fwr-section-title">Registration Details</h2>

                <label class="fwr-payment-label">Select Payment Method</label>
                <div class="fwr-payment-grid">
                    <div class="fwr-payment-option">
                        <input type="radio" name="payment" disabled>
                        <div class="fwr-payment-option-content">
                            <div class="fwr-payment-radio"></div>
                            <div class="fwr-payment-info">
                                <div class="fwr-payment-name">UPI</div>
                                <div class="fwr-payment-desc">Pay instantly using UPI</div>
                            </div>
                            <div class="fwr-payment-icon"></div>
                        </div>
                    </div>

                    <div class="fwr-payment-option">
                        <input type="radio" name="payment" disabled>
                        <div class="fwr-payment-option-content">
                            <div class="fwr-payment-radio"></div>
                            <div class="fwr-payment-info">
                                <div class="fwr-payment-name">Credit/Debit Card</div>
                                <div class="fwr-payment-desc">Visa, Mastercard, RuPay</div>
                            </div>
                            <div class="fwr-payment-icon"></div>
                        </div>
                    </div>

                    <div class="fwr-payment-option">
                        <input type="radio" name="payment" disabled>
                        <div class="fwr-payment-option-content">
                            <div class="fwr-payment-radio"></div>
                            <div class="fwr-payment-info">
                                <div class="fwr-payment-name">Net Banking</div>
                                <div class="fwr-payment-desc">All major banks supported</div>
                            </div>
                            <div class="fwr-payment-icon"></div>
                        </div>
                    </div>

                    <div class="fwr-payment-option">
                        <input type="radio" name="payment" disabled>
                        <div class="fwr-payment-option-content">
                            <div class="fwr-payment-radio"></div>
                            <div class="fwr-payment-info">
                                <div class="fwr-payment-name">Mobile Wallets</div>
                                <div class="fwr-payment-desc">PayTM, PhonePe, Google Pay</div>
                            </div>
                            <div class="fwr-payment-icon"></div>
                        </div>
                    </div>
                </div>

                <h3 class="fwr-section-title">Order Summary</h3>
                <div class="fwr-summary-row">
                    <span class="fwr-summary-label">Webinar Registration</span>
                    <span class="fwr-summary-value fwr-free-tag">Free</span>
                </div>
                <div class="fwr-summary-row">
                    <span>Total Amount</span>
                    <span class="fwr-free-tag">Free</span>
                </div>

                <button type="button" class="fwr-register-btn" onclick="registerFreeWebinar(<?php echo $event_id; ?>)">
                    Register Now
                </button>
                <p class="fwr-register-note">You'll receive a confirmation email with access details</p>
            </div>

            <!-- Right Column -->
            <div class="fwr-card">
                <h2 class="fwr-section-title">Payment Security</h2>

                <div class="fwr-security-item">
                    <svg class="fwr-security-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <div class="fwr-security-content">
                        <h4>Secure Transaction</h4>
                        <p>256-bit SSL encryption for all payments</p>
                    </div>
                </div>

                <div class="fwr-security-item">
                    <svg class="fwr-security-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <div class="fwr-security-content">
                        <h4>PCI DSS Compliant</h4>
                        <p>Your payment data is protected</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function registerFreeWebinar(eventId) {
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Registering...';

        // Make AJAX call to register for free webinar
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'register_free_webinar',
                event_id: eventId,
                nonce: '<?php echo wp_create_nonce('free_webinar_registration'); ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Registration successful! You will receive a confirmation email shortly.');
                 window.location.href = '<?php echo esc_url(add_query_arg('event-id', $event_id, home_url('/webinar-info'))); ?>';
            } else {
                alert(data.data.message || 'Registration failed. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Register Now';
            }
        })
        .catch(err => {
            alert('An error occurred. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Register Now';
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// AJAX handler for free webinar registration
add_action('wp_ajax_register_free_webinar', 'handle_free_webinar_registration');

function handle_free_webinar_registration() {
    check_ajax_referer('free_webinar_registration', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in to register.']);
    }

    $event_id = absint($_POST['event_id'] ?? 0);
    if (!$event_id) {
        wp_send_json_error(['message' => 'Invalid event ID.']);
    }

    $user = wp_get_current_user();
    $amelia_key = get_option('platform_amelia_api_key', '');

    // Book the event via Amelia API
    $response = wp_remote_post(home_url('/amelia/wp-admin/admin-ajax.php?action=wpamelia_api&call=/api/v1/bookings'), [
        'headers' => [
            'Content-Type' => 'application/json',
            'Amelia' => $amelia_key
        ],
        'body' => json_encode([
            'type' => 'event',
            'bookings' => [[
                'customerId' => 0,
                'persons' => 1,
                'customer' => [
                    'email' => $user->user_email,
                    'firstName' => $user->user_firstname ?: $user->display_name,
                    'lastName' => $user->user_lastname ?: '',
                    'phone' => ''
                ]
            ]],
            'payment' => [
                'gateway' => 'onSite',
                'currency' => 'INR'
            ],
            'eventId' => $event_id
        ]),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Booking failed. Please try again.']);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($body['data']['booking']['id'])) {
        wp_send_json_success(['message' => 'Registration successful!']);
    } else {
        wp_send_json_error(['message' => $body['message'] ?? 'Registration failed.']);
    }
}