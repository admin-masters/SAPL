<?php
/**
 * Paid Webinar Registration Page - FIXED VERSION
 * Shortcode: [paid_webinar_registration]
 * Usage: /paid-webinar-registration?event-id=123
 *
 * KEY FIXES (based on working sign-contract.php flow):
 *  ? Direct WooCommerce order creation (NO cart approach)
 *  ? WC_Order_Item_Fee for event fee (exactly like contract flow)
 *  ? Razorpay payment method assignment
 *  ? Order metadata tracking (_amelia_event_id)
 *  ? Immediate redirect to payment URL after order creation
 *  ? Simple POST form submission (no AJAX complexity)
 *  ? Proper error handling and user feedback
 */

if (!defined('ABSPATH')) exit;

add_shortcode('paid_webinar_registration', 'render_paid_webinar_registration');



function render_paid_webinar_registration() {
    if (!is_user_logged_in()) {
        auth_redirect();
        return;
    }

    $event_id = absint($_GET['event-id'] ?? 0);
    if (!$event_id) {
        return '<div style="padding:20px;color:#dc2626;">No event ID provided.</div>';
    }

    // Fetch event details
    global $wpdb;
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT e.id, e.name, COALESCE(e.price, 0) AS price FROM {$wpdb->prefix}amelia_events e WHERE e.id = %d",
        $event_id
    ));

    if (!$event) {
        return '<div style="padding:20px;color:#dc2626;">Event not found.</div>';
    }

    // If event is free, redirect to free registration
    $price = floatval($event->price);
    if ($price <= 0) {
        $free_url = add_query_arg('event-id', $event_id, home_url('/free-webinar-payment'));
        wp_redirect($free_url);
        exit;
    }



    $current_user   = wp_get_current_user();
    $user_name      = $current_user->display_name;
    $user_first     = $current_user->user_firstname ?: explode(' ', $user_name)[0];
    $user_avatar    = get_avatar_url($current_user->ID, ['size' => 40]);
    $event_name     = $event->name;
    $total          = $price;

    $url_dashboard = home_url('/webinar-dashboard');
    $url_library   = home_url('/webinar-library');
    $url_myevents  = home_url('/my-events');

    // Handle POST: Create WooCommerce order (EXACTLY like contract flow)
    $order_error   = '';
    $payment_url   = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' 
        && !empty($_POST['_pwr_payment_nonce'])
        && wp_verify_nonce($_POST['_pwr_payment_nonce'], 'pwr_create_order_' . $event_id)
    ) {
        if (!function_exists('wc_create_order') || !function_exists('wc_get_order')) {
            $order_error = 'WooCommerce is not active. Please contact support.';
        } else {
            try {
                // Step 1: Create WooCommerce order (same as contract flow)
                $wc_order = wc_create_order(['customer_id' => get_current_user_id()]);
                
                // Step 2: Add fee item for the webinar (same as contract flow)
                $fee_item = new WC_Order_Item_Fee();
                $fee_item->set_name('Webinar: ' . $event_name . ' (Event #' . $event_id . ')');
                $fee_item->set_amount($price);
                $fee_item->set_total($price);
                $wc_order->add_item($fee_item);
                
                // Step 3: Add metadata (same as contract flow uses _platform_request_id)
                $wc_order->update_meta_data('_amelia_event_id', $event_id);
                $wc_order->update_meta_data('_webinar_registration', 'yes');
                
                // Step 4: Set payment method to Razorpay (same as contract)
                $wc_order->set_payment_method('razorpay');
                
                // Step 5: Calculate totals (same as contract)
                $wc_order->calculate_totals(false);
                
                // Step 6: Set order status to pending (same as contract)
                $wc_order->set_status('pending');
                
                // Step 7: Save the order (same as contract)
                $wc_order->save();
                
                // Step 8: Get payment URL (same as contract)
                $payment_url = $wc_order->get_checkout_payment_url();
                
                // Step 9: Redirect immediately (same as contract redirects after signing)
                if ($payment_url) {
                    wp_redirect($payment_url);
                    exit;
                }
                
            } catch (Exception $e) {
                $order_error = 'Failed to create order: ' . $e->getMessage();
                error_log('Webinar order creation error: ' . $e->getMessage());
            }
        }
    }

    // Nonces
    $payment_nonce = wp_create_nonce('pwr_create_order_' . $event_id);
    $debug_nonce = wp_create_nonce('amelia_debug_panel');

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
        --error:#dc2626;
    }

    body{
        font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        background:var(--bg);
        color:var(--text);
        line-height:1.6;
    }

    /* Navbar */
    .pwr-nav{
        background:rgba(255,255,255,.97);backdrop-filter:blur(14px);
        border-bottom:1px solid var(--border);position:sticky;top:0;z-index:200;
        box-shadow:0 1px 0 var(--border),0 3px 14px rgba(13,16,37,.05);
    }
    .pwr-nav-inner{
        max-width:1200px;margin:auto;padding:0 32px;height:60px;
        display:flex;align-items:center;gap:16px;
    }
    .pwr-nav-logo{
        display:flex;align-items:center;gap:8px;font-size:16px;font-weight:800;
        color:var(--primary);text-decoration:none;flex-shrink:0;
    }
    .pwr-nav-logo-box{
        width:32px;height:32px;background:var(--primary);border-radius:8px;
        display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:14px;
    }
    .pwr-nav-links{display:flex;gap:2px;margin-left:8px;}
    .pwr-nav-links a{
        padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;
        color:var(--text-light);text-decoration:none;transition:background .16s,color .16s;
    }
    .pwr-nav-links a:hover{background:#eff4ff;color:var(--primary);}
    .pwr-nav-r{margin-left:auto;display:flex;align-items:center;gap:12px;}
    .pwr-nav-bell{
        position:relative;width:40px;height:40px;border-radius:50%;
        background:var(--bg);border:1.5px solid var(--border);
        display:flex;align-items:center;justify-content:center;cursor:pointer;
    }
    .pwr-nav-bell-dot{
        position:absolute;top:8px;right:8px;width:8px;height:8px;
        background:#ef4444;border-radius:50%;border:2px solid var(--card);
    }
    .pwr-nav-user{display:flex;align-items:center;gap:10px;}
    .pwr-nav-user img{width:36px;height:36px;border-radius:50%;border:2px solid var(--border);}
    .pwr-nav-uname{font-size:13px;font-weight:700;color:var(--text);}
    .pwr-nav-logout{
        padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:700;
        background:var(--text);color:#fff;text-decoration:none;
    }
    @media(max-width:768px){.pwr-nav-links{display:none;}.pwr-nav-inner{padding:0 16px;}}

    /* Container */
    .pwr-container{max-width:1200px;margin:40px auto;padding:0 32px;}
    .pwr-back{
        display:inline-flex;align-items:center;gap:8px;color:var(--text);
        text-decoration:none;font-size:14px;font-weight:500;margin-bottom:24px;
    }
    .pwr-back svg{width:20px;height:20px;}
    .pwr-grid{display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;}
    .pwr-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:32px;}
    .pwr-section-title{font-size:18px;font-weight:700;color:var(--text);margin-bottom:20px;}

    /* Payment Options */
    .pwr-payment-label{font-size:14px;font-weight:600;color:var(--text);margin-bottom:16px;display:block;}
    .pwr-payment-grid{display:grid;grid-template-columns:1fr;gap:12px;margin-bottom:32px;}
    .pwr-payment-option{
        position:relative;border:2px solid var(--border);border-radius:8px;padding:16px;
        cursor:pointer;background:var(--card);transition:all 0.2s;
    }
    .pwr-payment-option:hover{border-color:var(--primary);background:#fafbff;}
    .pwr-payment-option input{position:absolute;opacity:0;}
    .pwr-payment-option input:checked ~ .pwr-payment-option-content{border-color:var(--primary);}
    .pwr-payment-option-content{display:flex;align-items:center;gap:12px;}
    .pwr-payment-radio{
        width:20px;height:20px;border:2px solid var(--border);
        border-radius:50%;flex-shrink:0;position:relative;
    }
    .pwr-payment-option input:checked ~ .pwr-payment-option-content .pwr-payment-radio{
        border-color:var(--primary);background:var(--primary);
    }
    .pwr-payment-option input:checked ~ .pwr-payment-option-content .pwr-payment-radio::after{
        content:'';position:absolute;width:8px;height:8px;background:white;
        border-radius:50%;top:50%;left:50%;transform:translate(-50%,-50%);
    }
    .pwr-payment-info{flex:1;}
    .pwr-payment-name{font-size:14px;font-weight:600;color:var(--text);margin-bottom:2px;}
    .pwr-payment-desc{font-size:12px;color:var(--text-light);}
    .pwr-payment-icon{font-size:24px;}

    /* Summary */
    .pwr-summary-row{
        display:flex;justify-content:space-between;padding:12px 0;
        border-bottom:1px solid var(--border);
    }
    .pwr-summary-row:last-child{
        border-bottom:none;padding-top:16px;margin-top:8px;
        font-weight:700;font-size:16px;
    }
    .pwr-summary-label{font-size:14px;color:var(--text-light);}
    .pwr-summary-value{font-size:14px;font-weight:600;color:var(--text);}

    /* Button */
    .pwr-pay-btn{
        width:100%;padding:16px;background:var(--primary);color:white;
        border:none;border-radius:8px;font-size:16px;font-weight:600;
        cursor:pointer;margin-top:24px;transition:background 0.2s;
    }
    .pwr-pay-btn:hover:not(:disabled){background:var(--primary-dark);}
    .pwr-pay-btn:disabled{opacity:.6;cursor:not-allowed;}
    .pwr-pay-note{text-align:center;font-size:12px;color:var(--text-light);margin-top:12px;}

    /* Error */
    .pwr-error{
        margin-bottom:20px;padding:12px 16px;background:#fef2f2;
        border:1px solid #fecaca;border-radius:8px;color:var(--error);font-size:13px;
    }

    /* Security */
    .pwr-security-item{display:flex;gap:12px;margin-bottom:16px;}
    .pwr-security-icon{width:20px;height:20px;flex-shrink:0;margin-top:2px;}
    .pwr-security-content h4{font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px;}
    .pwr-security-content p{font-size:13px;color:var(--text-light);}
    .pwr-support{background:var(--bg);border-radius:8px;padding:20px;margin-top:24px;}
    .pwr-support h3{font-size:16px;font-weight:700;color:var(--text);margin-bottom:8px;}
    .pwr-support p{font-size:13px;color:var(--text-light);margin-bottom:16px;}
    .pwr-support-btn{
        width:100%;padding:12px;background:var(--text);color:white;
        border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;
    }

    @media(max-width:968px){.pwr-grid{grid-template-columns:1fr;}.pwr-container{padding:0 16px;}}
    </style>

    <nav class="pwr-nav">
        <div class="pwr-nav-inner">
            <a href="<?php echo esc_url(home_url()); ?>" class="pwr-nav-logo">
                <div class="pwr-nav-logo-box">L</div>
                <span><?php echo esc_html(get_bloginfo('name')); ?></span>
            </a>
            <div class="pwr-nav-links">
                <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
                <a href="<?php echo esc_url($url_library); ?>">Library</a>
                <a href="<?php echo esc_url($url_myevents); ?>">My Events</a>
            </div>
            <div class="pwr-nav-r">
                <div class="pwr-nav-bell">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <div class="pwr-nav-bell-dot"></div>
                </div>
                <div class="pwr-nav-user">
                    <img src="<?php echo esc_url($user_avatar); ?>" alt="<?php echo esc_attr($user_name); ?>">
                    <span class="pwr-nav-uname">Hi, <?php echo esc_html($user_first); ?></span>
                </div>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pwr-nav-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="pwr-container">
        <a href="javascript:history.back()" class="pwr-back">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            <?php echo esc_html($event_name); ?>
        </a>

        <div class="pwr-grid">
            <div class="pwr-card">
                <h2 class="pwr-section-title">Payment Details</h2>

                <?php if ($order_error): ?>
                <div class="pwr-error"><?php echo esc_html($order_error); ?></div>
                <?php endif; ?>

                <form method="POST" id="pwr-payment-form">
                    <?php wp_nonce_field('pwr_create_order_' . $event_id, '_pwr_payment_nonce'); ?>
                    
                    <label class="pwr-payment-label">Payment Method</label>
                    <div class="pwr-payment-grid">
                        <label class="pwr-payment-option">
                            <input type="radio" name="payment_method" value="razorpay" checked>
                            <div class="pwr-payment-option-content">
                                <div class="pwr-payment-radio"></div>
                                <div class="pwr-payment-info">
                                    <div class="pwr-payment-name">Razorpay</div>
                                    <div class="pwr-payment-desc">Cards, UPI, Wallets & More</div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <h3 class="pwr-section-title">Order Summary</h3>
                    <div class="pwr-summary-row">
                        <span class="pwr-summary-label">Webinar Registration</span>
                        <span class="pwr-summary-value">&#8377;<?php echo number_format($price, 2); ?></span>
                    </div>
                    <div class="pwr-summary-row">
                        <span>Total Amount</span>
                        <span>&#8377;<?php echo number_format($total, 2); ?></span>
                    </div>

                    <button type="submit" class="pwr-pay-btn" id="pwr-pay-btn">
                        Proceed to Payment
                    </button>
                    <p class="pwr-pay-note">
                        <svg style="display:inline;width:14px;height:14px;margin-right:4px;vertical-align:middle;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Secure payment via Razorpay
                    </p>
                </form>
            </div>

            <div>
                <div class="pwr-card">
                    <h2 class="pwr-section-title">Payment Security</h2>

                    <div class="pwr-security-item">
                        <svg class="pwr-security-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <div class="pwr-security-content">
                            <h4>Secure Transaction</h4>
                            <p>256-bit SSL encryption</p>
                        </div>
                    </div>

                    <div class="pwr-security-item">
                        <svg class="pwr-security-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <div class="pwr-security-content">
                            <h4>PCI DSS Compliant</h4>
                            <p>Payment data protected</p>
                        </div>
                    </div>

                    <div class="pwr-security-item">
                        <svg class="pwr-security-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="pwr-security-content">
                            <h4>Refund Policy</h4>
                            <p>Full refund if cancelled</p>
                        </div>
                    </div>
                </div>

                <div class="pwr-support">
                    <h3>Payment Support</h3>
                    <p>Need help? Our team is here</p>
                    <button type="button" class="pwr-support-btn" onclick="window.location.href='mailto:support@example.com'">
                        Contact Support
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var form = document.getElementById('pwr-payment-form');
        var btn  = document.getElementById('pwr-pay-btn');

        if (form && btn) {
            form.addEventListener('submit', function () {
                btn.disabled = true;
                btn.textContent = 'Creating order...';
            });
        }
    })();
    </script>

    <?php
    return ob_get_clean();
}

// =============================================================================
// PAYMENT COMPLETION HOOKS - Update Amelia when payment is successful
// =============================================================================

/**
 * Hook into WooCommerce payment completion to create Amelia booking
 * Triggers when Razorpay payment is successful
 * 
 * WEBINAR TABLE STRUCTURE (different from class bookings):
 * 
 * 1. wp_amelia_users (customer record)
 * 2. wp_amelia_events_periods (event has multiple time periods/sessions)
 * 3. wp_amelia_customer_bookings (main booking record)
 * 4. wp_amelia_customer_bookings_to_events_periods (links booking to period)
 * 5. wp_amelia_payments (payment record)
 * 
 * Note: Classes use amelia_customer_bookings_to_events
 *       Webinars use amelia_customer_bookings + amelia_customer_bookings_to_events_periods
 */
add_action('woocommerce_payment_complete', 'webinar_payment_complete_handler', 10, 1);
add_action('woocommerce_order_status_completed', 'webinar_payment_complete_handler', 10, 1);
add_action('woocommerce_order_status_processing', 'webinar_payment_complete_handler', 10, 1);

function webinar_payment_complete_handler($order_id) {
    error_log("=== WEBINAR PAYMENT HANDLER CALLED ===");
    error_log("Order ID: " . $order_id);
    
    if (!$order_id) {
        error_log("ERROR: No order ID provided");
        return;
    }
    
    // Get the order
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("ERROR: Could not load order #" . $order_id);
        return;
    }
    
    error_log("Order loaded successfully. Status: " . $order->get_status());
    
    // Check if this is a webinar registration order
    $event_id = $order->get_meta('_amelia_event_id');
    $is_webinar = $order->get_meta('_webinar_registration');
    
    error_log("Event ID from meta: " . var_export($event_id, true));
    error_log("Is webinar from meta: " . var_export($is_webinar, true));
    
    if (!$event_id || $is_webinar !== 'yes') {
        error_log("SKIP: Not a webinar order (event_id={$event_id}, is_webinar={$is_webinar})");
        return; // Not a webinar order
    }
    
    // Check if we already processed this payment
    $already_processed = $order->get_meta('_amelia_booking_created');
    error_log("Already processed check: " . var_export($already_processed, true));
    
    if ($already_processed) {
        error_log("SKIP: Already processed this order");
        return; // Already processed
    }
    
    error_log("Proceeding to create Amelia booking...");
    
    global $wpdb;
    
    error_log("Getting event details for event ID: " . $event_id);
    
    // Get event details
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, price, bookingOpens, bookingCloses, maxCapacity 
         FROM {$wpdb->prefix}amelia_events 
         WHERE id = %d",
        $event_id
    ));
    
    if (!$event) {
        error_log("ERROR: Event #{$event_id} not found for order #{$order_id}");
        return;
    }
    
    error_log("Event found: " . $event->name . " (Price: " . $event->price . ")");
    
    $user_id = $order->get_customer_id();
    error_log("Customer user ID: " . $user_id);
    
    $user = get_userdata($user_id);
    
    if (!$user) {
        error_log("ERROR: User not found for order #{$order_id}");
        return;
    }
    
    // Get user details
    $first_name = $user->first_name ?: $user->display_name;
    $last_name = $user->last_name ?: '';
    $email = $user->user_email;
    $phone = get_user_meta($user_id, 'billing_phone', true) ?: '';
    
    error_log("User details: {$first_name} {$last_name} ({$email})");
    
    try {
        error_log("--- STEP 1: Check/Create Amelia Customer ---");
        
        // Step 1: Check/Create customer in Amelia
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_users 
             WHERE email = %s AND type = 'customer' 
             LIMIT 1",
            $email
        ));
        
        if (!$customer) {
            error_log("Customer not found, creating new customer...");
            
            // Create new Amelia customer
            $insert_result = $wpdb->insert(
                $wpdb->prefix . 'amelia_users',
                [
                    'type' => 'customer',
                    'status' => 'visible',
                    'externalId' => $user_id,
                    'firstName' => $first_name,
                    'lastName' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'birthday' => null,
                    'gender' => null,
                    'note' => 'Created via webinar payment',
                ],
                ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            
            if ($insert_result === false) {
                throw new Exception('Failed to insert customer: ' . $wpdb->last_error);
            }
            
            $customer_id = $wpdb->insert_id;
            
            if (!$customer_id) {
                throw new Exception('Failed to get customer ID after insert');
            }
            
            error_log("Customer created successfully. ID: " . $customer_id);
        } else {
            $customer_id = $customer->id;
            error_log("Existing customer found. ID: " . $customer_id);
        }
        
        error_log("--- STEP 2: Get Event Period ---");
        
        // Step 2A: Get event period (webinars have periods, not direct bookings)
        $period = $wpdb->get_row($wpdb->prepare(
            "SELECT id, periodStart, periodEnd 
             FROM {$wpdb->prefix}amelia_events_periods 
             WHERE eventId = %d 
             ORDER BY periodStart ASC 
             LIMIT 1",
            $event_id
        ));
        
        if (!$period) {
            throw new Exception('No event period found for event #' . $event_id);
        }
        
        $period_id = $period->id;
        error_log("Event period found. Period ID: " . $period_id . " (Start: " . $period->periodStart . ")");
        
        error_log("--- STEP 3: Create Customer Booking ---");
        
        // Debug: Check table structure
        $table_structure = $wpdb->get_results("DESCRIBE {$wpdb->prefix}amelia_customer_bookings", ARRAY_A);
        error_log("Table structure: " . print_r($table_structure, true));
        
        // Step 2B: Create customer booking record
        $booking_data = [
            'customerId' => $customer_id,
            'status' => 'approved',
            'price' => floatval($event->price),
            'persons' => 1,
            'couponId' => null,
            'token' => wp_generate_password(10, false), // Max 10 chars as per DB schema
            'customFields' => null,
            'info' => json_encode([
                'firstName' => $first_name,
                'lastName' => $last_name,
                'phone' => $phone,
                'locale' => 'en_US',
            ]),
            'utcOffset' => null,
            'aggregatedPrice' => 1,
            'packageCustomerServiceId' => null,
            'created' => current_time('mysql'),
        ];
        
        error_log("Booking data prepared: " . print_r($booking_data, true));
        
        $booking_insert = $wpdb->insert(
            $wpdb->prefix . 'amelia_customer_bookings',
            $booking_data,
            ['%d', '%s', '%f', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );
        
        error_log("Insert result: " . var_export($booking_insert, true));
        error_log("Insert ID: " . $wpdb->insert_id);
        error_log("Last error: " . $wpdb->last_error);
        
        if ($booking_insert === false) {
            throw new Exception('Failed to insert customer booking: ' . $wpdb->last_error);
        }
        
        $booking_id = $wpdb->insert_id;
        
        if (!$booking_id) {
            throw new Exception('Failed to get booking ID after insert. Last error: ' . $wpdb->last_error);
        }
        
        error_log("Customer booking created. Booking ID: " . $booking_id);
        
        error_log("--- STEP 4: Link Booking to Event Period ---");
        
        // Step 2C: Link booking to event period
        $link_insert = $wpdb->insert(
            $wpdb->prefix . 'amelia_customer_bookings_to_events_periods',
            [
                'customerBookingId' => $booking_id,
                'eventPeriodId' => $period_id,
            ],
            ['%d', '%d']
        );
        
        if ($link_insert === false) {
            throw new Exception('Failed to link booking to event period: ' . $wpdb->last_error);
        }
        
        error_log("Booking linked to event period successfully");
        
        error_log("--- STEP 5: Create Payment Record ---");
        
        // Step 3: Create payment record in amelia_payments
        $payment_insert = $wpdb->insert(
            $wpdb->prefix . 'amelia_payments',
            [
                'customerBookingId' => $booking_id,
                'amount' => floatval($event->price),
                'dateTime' => current_time('mysql'),
                'status' => 'paid',
                'gateway' => 'razorpay',
                'gatewayTitle' => 'Razorpay',
                'data' => json_encode([
                    'wc_order_id' => $order_id,
                    'razorpay_payment_id' => $order->get_transaction_id(),
                ]),
                'packageCustomerId' => null,
                'entity' => null,
                'created' => current_time('mysql'),
                'actionsCompleted' => 1,
            ],
            ['%d', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d']
        );
        
        if ($payment_insert === false) {
            throw new Exception('Failed to insert payment record: ' . $wpdb->last_error);
        }
        
        $payment_id = $wpdb->insert_id;
        
        if (!$payment_id) {
            throw new Exception('Failed to get payment ID after insert');
        }
        
        error_log("Payment record created. Payment ID: " . $payment_id . " (Status: paid)");
        
        error_log("--- STEP 6: Mark Order as Processed ---");
        
        // Step 4: Mark order as processed
        $order->update_meta_data('_amelia_booking_created', 'yes');
        $order->update_meta_data('_amelia_booking_id', $booking_id);
        $order->update_meta_data('_amelia_payment_id', $payment_id);
        $order->update_meta_data('_amelia_period_id', $period_id);
        $order->add_order_note(
            sprintf(
                'Amelia booking created successfully. Booking ID: %d, Payment ID: %d, Period ID: %d',
                $booking_id,
                $payment_id,
                $period_id
            )
        );
        $order->save();
        
        error_log("Order metadata updated and saved");
        
        // Step 5: Send confirmation email (optional - Amelia should handle this)
        // You can trigger Amelia's notification system here if needed
        
        error_log("=== SUCCESS: Webinar payment complete ===");
        error_log("Order #{$order_id}, Event #{$event_id}, Booking #{$booking_id}, Payment #{$payment_id}");
        
    } catch (Exception $e) {
        error_log("=== ERROR: Webinar payment failed ===");
        error_log("Order #{$order_id}: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $order->add_order_note('Error creating Amelia booking: ' . $e->getMessage());
    }
}

/**
 * Optional: Handle payment failures/cancellations
 */
add_action('woocommerce_order_status_cancelled', 'webinar_payment_cancelled_handler', 10, 1);
add_action('woocommerce_order_status_failed', 'webinar_payment_cancelled_handler', 10, 1);

function webinar_payment_cancelled_handler($order_id) {
    if (!$order_id) return;
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $event_id = $order->get_meta('_amelia_event_id');
    $is_webinar = $order->get_meta('_webinar_registration');
    $booking_id = $order->get_meta('_amelia_booking_id');
    
    if (!$event_id || $is_webinar !== 'yes' || !$booking_id) {
        return;
    }
    
    global $wpdb;
    
    // Update booking status to cancelled in Amelia
    $wpdb->update(
        $wpdb->prefix . 'amelia_customer_bookings',
        ['status' => 'canceled'],
        ['id' => $booking_id],
        ['%s'],
        ['%d']
    );
    
    // Update payment status
    $wpdb->update(
        $wpdb->prefix . 'amelia_payments',
        ['status' => 'canceled'],
        ['customerBookingId' => $booking_id],
        ['%s'],
        ['%d']
    );
    
    error_log("Webinar payment cancelled: Order #{$order_id}, Booking #{$booking_id}");
}