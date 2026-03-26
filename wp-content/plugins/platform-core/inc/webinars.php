<?php
/**
 * Webinar Tickets (Woo) -> Attendee (Amelia) + Calendar insert
 * Requires: WooCommerce, Amelia (with Elite API), Razorpay gateway, WP Mail SMTP, and table: wp_platform_calendar_map (from Foundation).
 */
if (!defined('ABSPATH')) exit;
add_action('init', function () {
    // Create /webinars if missing
    if (!get_page_by_path('webinars')) {
        wp_insert_post([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_title'  => 'Webinars',
            'post_name'   => 'webinars',
            'post_content'=> '[ameliaeventslistbooking]'
        ]);
    }
    // Ensure /my-events exists as a fallback
    if (!get_page_by_path('my-events')) {
        wp_insert_post([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_title'  => 'My Events',
            'post_name'   => 'my-events',
            'post_content'=> '[ameliacustomerpanel version=2 events=1]'
        ]);
    }
});
function platform_core_get_event_organizer_id($event_id) {
    global $wpdb;
    $organizer_id = $wpdb->get_var($wpdb->prepare(
        "SELECT organizerId FROM {$wpdb->prefix}amelia_events WHERE id = %d",
        $event_id
    ));
    return $organizer_id ? (int)$organizer_id : null;
}
add_action('woocommerce_before_add_to_cart_button', function () {
    if (!empty($_GET['event'])) {
        echo '<input type="hidden" name="platform_event_id" value="' . esc_attr($_GET['event']) . '">';
    }
});

add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    error_log('ADD TO CART HOOK FIRED for product ' . $product_id);
    error_log('POST DATA: ' . print_r($_POST, true));
    error_log('GET DATA: ' . print_r($_GET, true));
    return $cart_item_data;
}, 1, 2);
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    if (!empty($values['_platform_amelia_event_id'])) {
        $item->add_meta_data('_platform_amelia_event_id', (int) $values['_platform_amelia_event_id'], true);
        error_log('Event ID saved to order item: ' . $values['_platform_amelia_event_id']);
    }
}, 10, 3);
add_action('woocommerce_checkout_order_created', function ($order) {

    if (!isset($_REQUEST['call']) || $_REQUEST['call'] !== '/payment/wc') {
        return;
    }

    // Amelia passes eventId in request body (JSON)
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!empty($data['eventId'])) {
        $order->update_meta_data('_platform_amelia_event_id', (int) $data['eventId']);
        $order->save();

        error_log('Event ID saved on order meta: ' . $data['eventId']);
    }
});

add_action('init', function () {
    if (!isset($_GET['event'])) {
        return;
    }

    $event_id = intval($_GET['event']);
    if (!$event_id) {
        return;
    }

    $organizer_id = platform_core_get_event_organizer_id($event_id);

    // Debug output
    wp_die(
        '<pre>' .
        'Event ID: ' . $event_id . PHP_EOL .
        'Organizer ID: ' . var_export($organizer_id, true) .
        '</pre>'
    );
});

/** -----------------------------
 * Settings: API keys & Calendar
 * ------------------------------
 */
function platform_core_webinar_settings() {
    add_options_page('Platform Core ï¿½ Webinars', 'Platform Webinars', 'manage_options', 'platform-webinars', function () {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['platform_webinars_save']) && check_admin_referer('platform_webinars_save')) {
            update_option('platform_amelia_api_key', sanitize_text_field($_POST['platform_amelia_api_key'] ?? ''));
            update_option('platform_google_client_id', sanitize_text_field($_POST['platform_google_client_id'] ?? ''));
            update_option('platform_google_client_secret', sanitize_text_field($_POST['platform_google_client_secret'] ?? ''));
            update_option('platform_google_refresh_token', sanitize_text_field($_POST['platform_google_refresh_token'] ?? ''));
            update_option('platform_google_calendar_id', sanitize_text_field($_POST['platform_google_calendar_id'] ?? ''));
            echo '<div class="updated"><p>Saved.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Platform Webinars</h1>
            <form method="post">
                <?php wp_nonce_field('platform_webinars_save'); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label>Amelia API Key</label></th><td><input type="text" name="platform_amelia_api_key" value="<?php echo esc_attr(get_option('platform_amelia_api_key','')); ?>" class="regular-text"></td></tr>
                    <tr><th><label>Google Client ID</label></th><td><input type="text" name="platform_google_client_id" value="<?php echo esc_attr(get_option('platform_google_client_id','')); ?>" class="regular-text"></td></tr>
                    <tr><th><label>Google Client Secret</label></th><td><input type="text" name="platform_google_client_secret" value="<?php echo esc_attr(get_option('platform_google_client_secret','')); ?>" class="regular-text"></td></tr>
                    <tr><th><label>Google Refresh Token</label></th><td><input type="text" name="platform_google_refresh_token" value="<?php echo esc_attr(get_option('platform_google_refresh_token','')); ?>" class="regular-text"></td></tr>
                    <tr><th><label>Google Calendar ID</label></th><td><input type="text" name="platform_google_calendar_id" value="<?php echo esc_attr(get_option('platform_google_calendar_id','')); ?>" class="regular-text" placeholder="primary or calendar_id@group.calendar.google.com"></td></tr>
                </table>
                <p class="submit"><button class="button button-primary" name="platform_webinars_save" value="1">Save settings</button></p>
                <p><em>Notes:</em> Amelia REST API requires an Elite plan and the API key. Google Calendar writes use the site appï¿½s refresh token to insert attendee events into a site-owned calendar and invite them as a guest.</p>
            </form>
        </div>
        <?php
    });
}
add_action('admin_menu', 'platform_core_webinar_settings');

/** ---------------------------------------------
 * Woo Product UI: attach an Amelia Event to sell
 * ----------------------------------------------
 */
add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';
    // Event ID
    woocommerce_wp_text_input([
        'id'          => '_platform_amelia_event_id',
        'label'       => 'Amelia Event ID',
        'desc_tip'    => true,
        'description' => 'Numeric Event ID from Amelia (Events).',
        'type'        => 'number',
        'custom_attributes' => ['min' => '1', 'step' => '1']
    ]);
    // Pattern
    woocommerce_wp_select([
        'id'          => '_platform_webinar_pattern',
        'label'       => 'Ticket Pattern',
        'description' => 'Choose Free (?0) or Paid (use product price).',
        'options'     => ['paid' => 'Paid', 'free' => 'Free (?0)']
    ]);
    echo '</div>';
});
add_action('woocommerce_process_product_meta', function ($post_id) {
    update_post_meta($post_id, '_platform_amelia_event_id', absint($_POST['_platform_amelia_event_id'] ?? 0));
    $pattern = ($_POST['_platform_webinar_pattern'] ?? 'paid') === 'free' ? 'free' : 'paid';
    update_post_meta($post_id, '_platform_webinar_pattern', $pattern);
});

/** -----------------------------------------------------
 * Free orders (?0) should auto-complete so we add attendee
 * ------------------------------------------------------
 */
add_filter('woocommerce_payment_complete_order_status', function($status, $order_id) {
    $order = wc_get_order($order_id);
    if ($order && floatval($order->get_total()) == 0) {
        return 'completed';
    }
    return $status;
}, 10, 2);

/** ---------------------------------------
 * On order paid -> add attendee in Amelia
 * ----------------------------------------
 */
add_action('woocommerce_order_status_processing', 'platform_core_webinar_process_order');
add_action('woocommerce_order_status_completed',  'platform_core_webinar_process_order');

/**
 * Hook into Amelia's payment creation to add organizerId
 * This runs after Amelia creates the payment record
 */

function platform_core_webinar_process_order($order_id) {
    global $wpdb;
    $order = wc_get_order($order_id);
error_log('Order ID: ' . $order_id);
error_log('Order Status: ' . $order->get_status());
error_log('Order Items Count: ' . count($order->get_items()));

    if (!$order) return;

    foreach ($order->get_items() as $item_id => $item) {
error_log('Inside order items loop');
        error_log('Item ID: ' . $item_id);

$product = $item->get_product();
if ($product) {
    error_log('Product ID: ' . $product->get_id());
    error_log('Quantity: ' . $item->get_quantity());

    $event_id = (int) $item->get_meta('_platform_amelia_event_id');
    error_log('Mapped Amelia Event ID: ' . $event_id);
} else {
    error_log('Product object is NULL');
}
        if ($event_id <= 0)
{
error_log('No event ID on order item ' . $item_id);
 continue;
}
        $email = $order->get_billing_email();
        $first = $order->get_billing_first_name();
        $last  = $order->get_billing_last_name();
        $qty   = (int) $item->get_quantity();
        $currency = get_woocommerce_currency();

        $event = platform_core_amelia_get_event($event_id);
        if (!$event) continue;

        $title   = !empty($event['name']) ? $event['name'] : 'Webinar';
        $period  = $event['periods'][0] ?? null;
        $start   = $period['periodStart'] ?? '';
        $end     = $period['periodEnd']   ?? '';
        $zoomUrl = '';
        if (!empty($period['zoomMeeting']['joinUrl'])) {
            $zoomUrl = $period['zoomMeeting']['joinUrl'];
        }
error_log('Billing Email: ' . $order->get_billing_email());
error_log('Billing Name: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

        for ($i=0; $i<$qty; $i++) {
error_log("Booking loop iteration: $i / Qty: $qty");
            $booking = platform_core_amelia_add_event_booking([
                'eventId'   => $event_id,
                'email'     => $email,
                'firstName' => $first ?: 'Learner',
                'lastName'  => $last ?: '',
                'currency'  => $currency,
                'wcOrderId' => $order_id
            ]);
            
            // organizerId is now handled directly in the API call above
            
            platform_core_calendar_insert([
                'user_id'     => (int) $order->get_user_id(),
                'email'       => $email,
                'source'      => 'webinar',
                'source_ref'  => 'amelia:event:'.$event_id,
                'starts_at'   => $start,
                'ends_at'     => $end,
                'summary'     => $title,
                'description' => ($zoomUrl ? "Zoom: $zoomUrl\n\n" : '') . 'Order #'.$order_id.' – Added via platform-core',
                'location'    => $zoomUrl ?: get_bloginfo('name'),
                'zoom_url'    => $zoomUrl
            ]);
        }
    }
}
add_action('init', function () {

    if (
        empty($_REQUEST['action']) ||
        $_REQUEST['action'] !== 'wpamelia_api' ||
        empty($_REQUEST['call']) ||
        $_REQUEST['call'] !== '/payment/wc'
    ) {
        return;
    }

    error_log('=== AMELIA WC PAYMENT REQUEST DETECTED ===');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (empty($data['eventId']) || empty($data['wcOrderId'])) {
        error_log('Missing eventId or wcOrderId');
        return;
    }

    error_log('EventId: ' . $data['eventId']);
    error_log('WC OrderId: ' . $data['wcOrderId']);

    // Store temporarily for later update
    set_transient(
        'platform_pending_organizer_' . (int)$data['wcOrderId'],
        (int)$data['eventId'],
        5 * MINUTE_IN_SECONDS
    );
});


add_action('woocommerce_order_status_processing', 'platform_finalize_organizer_mapping');
add_action('woocommerce_order_status_completed',  'platform_finalize_organizer_mapping');

function platform_finalize_organizer_mapping($order_id) {
    global $wpdb;

    $event_id = get_transient('platform_pending_organizer_' . $order_id);
    if (!$event_id) {
        return;
    }

    delete_transient('platform_pending_organizer_' . $order_id);

    $organizer_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT organizerId FROM {$wpdb->prefix}amelia_events WHERE id = %d",
            $event_id
        )
    );

    if (!$organizer_id) {
        error_log("Organizer not found for event {$event_id}");
        return;
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'amelia_payments',
        ['organizerId' => (int)$organizer_id],
        ['wcOrderId' => (int)$order_id],
        ['%d'],
        ['%d']
    );

    error_log("Organizer {$organizer_id} mapped to WC Order {$order_id} ({$updated} rows)");
}

/** ----------------------------------
 * Amelia API helpers (Elite license)
 * -----------------------------------
 */
 function platform_core_amelia_api_headers() {
    $apiKey = get_option('platform_amelia_api_key', 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm');
   return [
       'Content-Type' => 'application/json',
        'Amelia'       => $apiKey
    ];
 } 
function platform_core_amelia_add_event_booking($args) {
    global $wpdb;
    
    error_log("=== NEW BOOKING START ===");
    
    // 1. Get organizerId from event
    $organizer_id = $wpdb->get_var($wpdb->prepare(
        "SELECT organizerId FROM {$wpdb->prefix}amelia_events WHERE id = %d",
        (int)$args['eventId']
    ));
    error_log("Event #{$args['eventId']} organizerId: " . ($organizer_id ?: 'NULL'));
    
    // 2. Create booking
    $payload = [
        'type' => 'event',
        'bookings' => [[
            'customerId' => 0,
            'persons' => 1,
            'customer' => [
                'email' => $args['email'],
                'firstName' => $args['firstName'],
                'lastName' => $args['lastName'],
                'phone' => ''
            ]
        ]],
        'payment' => [
            'gateway' => 'wc',
            'currency' => $args['currency'],
            'wcOrderId' => (int)$args['wcOrderId']
        ],
        'eventId' => (int)$args['eventId']
    ];
    
    $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/bookings');
    $res = wp_remote_post($url, [
        'headers' => platform_core_amelia_api_headers(),
        'body' => wp_json_encode($payload),
        'timeout' => 20
    ]);
    
    if (is_wp_error($res)) {
        error_log("API Error: " . $res->get_error_message());
        return false;
    }
    
    $data = json_decode(wp_remote_retrieve_body($res), true);
    
    if (empty($data['data']['booking']['id'])) {
        error_log("Booking failed");
        return false;
    }
    
    $booking_id = $data['data']['booking']['id'];
    error_log("Booking created: #{$booking_id}");
    
    // 3. Wait and find payment
    sleep(2);
    
    $payment_id = $wpdb->get_var($wpdb->prepare(
        "SELECT paymentId FROM {$wpdb->prefix}amelia_customer_bookings WHERE id = %d",
        $booking_id
    ));
    
    error_log("Payment ID: " . ($payment_id ?: 'NULL'));
    
    if (!$payment_id) {
        error_log("ERROR: No paymentId found in customer_bookings!");
        return $data['data'];
    }
    
    // 4. Check payment BEFORE update
    $before = $wpdb->get_row($wpdb->prepare(
        "SELECT id, wcOrderId, organizerId FROM {$wpdb->prefix}amelia_payments WHERE id = %d",
        $payment_id
    ), ARRAY_A);
    error_log("Payment BEFORE: " . json_encode($before));
    
    // 5. Update organizerId
    if ($organizer_id) {
        $result = $wpdb->update(
            $wpdb->prefix . 'amelia_payments',
            ['organizerId' => (int)$organizer_id],
            ['id' => (int)$payment_id],
            ['%d'],
            ['%d']
        );
        
        error_log("Update result: " . ($result !== false ? "Success ({$result} rows)" : "FAILED: " . $wpdb->last_error));
        
        // 6. Check AFTER update
        $after = $wpdb->get_row($wpdb->prepare(
            "SELECT id, wcOrderId, organizerId FROM {$wpdb->prefix}amelia_payments WHERE id = %d",
            $payment_id
        ), ARRAY_A);
        error_log("Payment AFTER: " . json_encode($after));
    } else {
        error_log("SKIPPED: No organizerId to set");
    }
    
    error_log("=== BOOKING END ===\n");
    
    return $data['data'];
}
function platform_core_amelia_get_event($event_id) {
    $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/events/'.intval($event_id));
    $res = wp_remote_get($url, ['headers'=>platform_core_amelia_api_headers(),'timeout'=>20]);
    if (is_wp_error($res)) return null;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['data']['event'] ?? null;
}

/** ---------------------------------------
 * Calendar writer -> wp_platform_calendar_map
 * + Google Calendar insert (site calendar)
 * ----------------------------------------
 */
function platform_core_calendar_insert($evt) {
    global $wpdb;
    $table = $wpdb->prefix . 'platform_calendar_map';
    // Attempt to create table if missing (safety)
    $maybe_sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        email VARCHAR(190) NOT NULL,
        source VARCHAR(60) NOT NULL,
        source_ref VARCHAR(190) NOT NULL,
        provider VARCHAR(30) NOT NULL DEFAULT 'google',
        calendar_id VARCHAR(190) NULL,
        event_id VARCHAR(190) NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        summary VARCHAR(255) NOT NULL,
        description TEXT NULL,
        location VARCHAR(255) NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'pending',
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY email (email),
        KEY source_ref (source_ref)
    ) {$wpdb->get_charset_collate()};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($maybe_sql);

    $now  = current_time('mysql');
    $data = [
        'user_id'     => (int)($evt['user_id'] ?? 0),
        'email'       => sanitize_email($evt['email']),
        'source'      => sanitize_text_field($evt['source']),
        'source_ref'  => sanitize_text_field($evt['source_ref']),
        'provider'    => 'google',
        'calendar_id' => get_option('platform_google_calendar_id',''),
        'event_id'    => null,
        'starts_at'   => gmdate('Y-m-d H:i:s', strtotime(($evt['starts_at'] ?? $now) . ' UTC')),
        'ends_at'     => gmdate('Y-m-d H:i:s', strtotime(($evt['ends_at']   ?? $now) . ' UTC')),
        'summary'     => wp_strip_all_tags($evt['summary'] ?? 'Webinar'),
        'description' => wp_kses_post($evt['description'] ?? ''),
        'location'    => sanitize_text_field($evt['location'] ?? ''),
        'status'      => 'pending',
        'meta'        => wp_json_encode(['zoom_url'=>$evt['zoom_url'] ?? null]),
        'created_at'  => $now,
        'updated_at'  => $now
    ];
    $wpdb->insert($table, $data);
    $row_id = (int) $wpdb->insert_id;

    // Try to insert into Google Calendar immediately
    $g_event = platform_core_google_insert_event([
        'summary'     => $data['summary'],
        'description' => $data['description'],
        'location'    => $data['location'],
        'email'       => $data['email'],
        'start'       => $data['starts_at'],
        'end'         => $data['ends_at'],
    ]);

    if ($g_event && !empty($g_event['id'])) {
        $wpdb->update($table,
            ['status'=>'inserted','event_id'=>$g_event['id'],'updated_at'=>current_time('mysql')],
            ['id'=>$row_id]
        );
    } else {
        $wpdb->update($table, ['status'=>'queued','updated_at'=>current_time('mysql')], ['id'=>$row_id]);
    }
}

function platform_core_google_insert_event($args) {
    $calId   = get_option('platform_google_calendar_id', '');
    $client  = get_option('platform_google_client_id', '');
    $secret  = get_option('platform_google_client_secret', '');
    $refresh = get_option('platform_google_refresh_token', '');
    if (!$calId || !$client || !$secret || !$refresh) return false;

    // 1) exchange refresh_token -> access_token
    $token = wp_remote_post('https://oauth2.googleapis.com/token', [
        'body' => [
            'client_id'     => $client,
            'client_secret' => $secret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh
        ],
        'timeout' => 20
    ]);
    if (is_wp_error($token)) return false;
    $tok = json_decode(wp_remote_retrieve_body($token), true);
    if (empty($tok['access_token'])) return false;

    // 2) create event; invite attendee; include Zoom link in description (if present)
    $startRFC = gmdate('c', strtotime($args['start']));
    $endRFC   = gmdate('c', strtotime($args['end']));
    $payload  = [
        'summary'     => $args['summary'],
        'description' => $args['description'],
        'location'    => $args['location'],
        'start'       => ['dateTime'=>$startRFC, 'timeZone'=>wp_timezone_string()],
        'end'         => ['dateTime'=>$endRFC,   'timeZone'=>wp_timezone_string()],
        'attendees'   => [['email'=>$args['email']]],
        'reminders'   => ['useDefault' => true]
    ];
    $url = 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calId).'/events?sendUpdates=all';
    $res = wp_remote_post($url, [
        'headers' => ['Authorization' => 'Bearer '.$tok['access_token'], 'Content-Type'=>'application/json'],
        'body'    => wp_json_encode($payload),
        'timeout' => 20
    ]);
    if (is_wp_error($res)) return false;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data;
}
