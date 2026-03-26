<?php
/**
 * Shortcode: [webinar_info]
 * Usage:     /webinar-info?event-id=123
 *
 * Book Now behaviour:
 *  - Fires AJAX ? Amelia bookings API (gateway: onSite).
 *  - For PAID events  ? booking created with payment status 'pending' ? redirects to /paid-webinar-registration
 *  - For FREE events  ? booking created, payment immediately 'paid' ? redirects to /free-webinar-payment
 *
 * Expert Edit Panel (NEW):
 *  - Shown only when the logged-in user is the expert who created the webinar.
 *  - Allows editing: date/time, capacity, price.
 *  - Allows uploading new course materials.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------
   MATERIALS DATABASE FUNCTION
--------------------------------------------------------------- */
if ( ! function_exists( 'wbi_get_event_materials' ) ) {
    function wbi_get_event_materials( $event_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'webinar_materials';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) return [];
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE event_id = %d ORDER BY uploaded_at ASC", $event_id
        ), ARRAY_A ) ?: [];
    }
}

/* ---------------------------------------------------------------
   AMELIA INTERNAL CALL
--------------------------------------------------------------- */
if ( ! function_exists( 'wbi_amelia_call' ) ) {
    function wbi_amelia_call( $endpoint ) {
        $url  = home_url( '/amelia/wp-admin/admin-ajax.php' )
                . '?action=wpamelia_api&call=' . ltrim( $endpoint, '/' );
        $args = [
            'headers'     => function_exists( 'platform_core_amelia_api_headers' )
                                ? platform_core_amelia_api_headers()
                                : [ 'Content-Type' => 'application/json' ],
            'timeout'     => 45,
            'sslverify'   => false,
            'httpversion' => '1.1',
        ];
        $res = wp_remote_get( $url, $args );
        if ( is_wp_error( $res ) ) return null;
        $body = wp_remote_retrieve_body( $res );
        if ( ! $body ) return null;
        $decoded = json_decode( $body, true );
        return is_array( $decoded ) ? $decoded : null;
    }
}

/* ---------------------------------------------------------------
   GET PROVIDER ID FOR EXPERT
--------------------------------------------------------------- */
if ( ! function_exists( 'wbi_get_provider_id_for_expert' ) ) {
    function wbi_get_provider_id_for_expert( $expert_id ) {
        $expert = get_userdata( $expert_id );
        if ( ! $expert ) return 0;
        $email     = strtolower( trim( $expert->user_email ) );
        $cache_key = 'wbi_provider_' . md5( $email );
        $cached    = get_transient( $cache_key );
        if ( $cached ) return (int) $cached;
        $opts       = get_option( 'platform_core_college_settings', [] );
        $service_id = isset( $opts['remote_class_service_id'] ) ? (int) $opts['remote_class_service_id'] : 6;
        $resp = wbi_amelia_call( '/api/v1/users/providers&services[0]=' . $service_id );
        if ( empty( $resp['data']['users'] ) ) return 0;
        foreach ( $resp['data']['users'] as $p ) {
            if ( strtolower( trim( $p['email'] ?? '' ) ) === $email ) {
                $pid = (int) ( $p['id'] ?? 0 );
                if ( $pid > 0 ) { set_transient( $cache_key, $pid, DAY_IN_SECONDS ); return $pid; }
            }
        }
        return 0;
    }
}

/* ---------------------------------------------------------------
   FETCH EVENT FROM DB
--------------------------------------------------------------- */
if ( ! function_exists( 'wbi_get_event' ) ) {
    function wbi_get_event( $event_id ) {
        global $wpdb;
        $now = current_time( 'mysql', true );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT e.id, e.name, e.description,
                    COALESCE(e.price,0)       AS price,
                    COALESCE(e.maxCapacity,0) AS maxCap,
                    e.status                  AS event_status,
                    ep.id                     AS periodId,
                    ep.periodStart, ep.periodEnd,
                    (SELECT COUNT(*)
                     FROM {$wpdb->prefix}amelia_customer_bookings cb2
                     INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x2
                             ON cb2.id = x2.customerBookingId
                     WHERE x2.eventPeriodId = ep.id AND cb2.status = 'approved') AS booked
             FROM {$wpdb->prefix}amelia_events e
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId = e.id
             WHERE e.id = %d
             ORDER BY CASE WHEN ep.periodStart > %s THEN 0 ELSE 1 END ASC, ep.periodStart ASC
             LIMIT 1",
            $event_id, $now
        ), ARRAY_A ) ?: null;
    }
}

/* ---------------------------------------------------------------
   FIND EXPERT FOR EVENT
--------------------------------------------------------------- */
if ( ! function_exists( 'wbi_get_expert_for_event' ) ) {
    function wbi_get_expert_for_event( $event_id ) {
        $resp         = wbi_amelia_call( '/api/v1/events/' . (int) $event_id );
        $amelia_event = $resp['data']['event'] ?? null;
        if ( ! $amelia_event ) return null;

        foreach ( (array) ( $amelia_event['providers'] ?? [] ) as $prov ) {
            $email = strtolower( trim( $prov['email'] ?? '' ) );
            if ( ! $email ) continue;
            $u = get_user_by( 'email', $email );
            if ( $u && in_array( 'expert', (array) $u->roles, true ) ) return $u;
        }

        $org_id = (int) ( $amelia_event['organizerId'] ?? 0 );
        if ( $org_id > 0 ) {
            $pr    = wbi_amelia_call( '/api/v1/users/providers/' . $org_id );
            $email = strtolower( trim( $pr['data']['user']['email'] ?? '' ) );
            if ( $email ) {
                $u = get_user_by( 'email', $email );
                if ( $u ) return $u;
            }
        }

        $opts       = get_option( 'platform_core_college_settings', [] );
        $service_id = isset( $opts['remote_class_service_id'] ) ? (int) $opts['remote_class_service_id'] : 6;
        $all        = wbi_amelia_call( '/api/v1/users/providers&services[0]=' . $service_id );
        $map        = [];
        foreach ( (array) ( $all['data']['users'] ?? [] ) as $p ) {
            $pid = (int)($p['id']??0); $em = strtolower(trim($p['email']??''));
            if ($pid && $em) $map[$pid] = $em;
        }
        foreach ( (array) ( $amelia_event['providerIds'] ?? [] ) as $pid ) {
            $email = $map[(int)$pid] ?? '';
            if (!$email) continue;
            $u = get_user_by('email', $email);
            if ($u && in_array('expert',(array)$u->roles,true)) return $u;
        }
        return null;
    }
}


/* ---------------------------------------------------------------
   CHECK: is the given WP user the expert/provider for this event?
   Uses direct DB — does NOT depend on Amelia API call.
--------------------------------------------------------------- */
if ( ! function_exists( 'wbi_user_can_edit_event' ) ) {
    function wbi_user_can_edit_event( $wp_user, $event_id ) {
        if ( ! $wp_user || ! $event_id ) return false;
        if ( ! in_array( 'expert', (array) $wp_user->roles, true ) ) return false;

        global $wpdb;
        $email = strtolower( trim( $wp_user->user_email ) );

        // 1. Is user the organizer?
        $org_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT organizerId FROM {$wpdb->prefix}amelia_events WHERE id = %d LIMIT 1",
            $event_id
        ) );
        if ( $org_id ) {
            $org_email = $wpdb->get_var( $wpdb->prepare(
                "SELECT LOWER(TRIM(email)) FROM {$wpdb->prefix}amelia_users WHERE id = %d LIMIT 1",
                (int) $org_id
            ) );
            if ( $org_email && $org_email === $email ) return true;
        }

        // 2. Is user a provider linked to this event?
        $match = $wpdb->get_var( $wpdb->prepare(
            "SELECT au.id FROM {$wpdb->prefix}amelia_users au
             INNER JOIN {$wpdb->prefix}amelia_events_to_providers etp ON etp.userId = au.id
             WHERE etp.eventId = %d AND LOWER(TRIM(au.email)) = %s
             LIMIT 1",
            $event_id, $email
        ) );
        if ( $match ) return true;

        return false;
    }
}

/* ---------------------------------------------------------------
   BOOKING STATUS
--------------------------------------------------------------- */
if ( ! function_exists( 'wbi_booking_status' ) ) {
    function wbi_booking_status( $uid, $event_id ) {
        if ( !$uid ) return 'none';
        global $wpdb;
        $u = get_userdata($uid);
        if (!$u) return 'none';
        $cid = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_users WHERE email=%s AND type='customer' LIMIT 1",
            $u->user_email
        ));
        if (!$cid) return 'none';
        $paid = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x ON cb.id=x.customerBookingId
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON x.eventPeriodId=ep.id
             INNER JOIN {$wpdb->prefix}amelia_payments p ON p.customerBookingId=cb.id
             WHERE cb.customerId=%d AND ep.eventId=%d AND cb.status='approved' AND p.status='paid'",
            (int)$cid, (int)$event_id
        ));
        if ($paid > 0) return 'registered';
        $pend = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x ON cb.id=x.customerBookingId
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON x.eventPeriodId=ep.id
             INNER JOIN {$wpdb->prefix}amelia_payments p ON p.customerBookingId=cb.id
             WHERE cb.customerId=%d AND ep.eventId=%d AND cb.status='approved' AND p.status IN('pending','unpaid','waiting')",
            (int)$cid, (int)$event_id
        ));
        if ($pend > 0) return 'pending';
        return 'none';
    }
}

/* ---------------------------------------------------------------
   AJAX — Book event
--------------------------------------------------------------- */
add_action( 'wp_ajax_wbi_book_event', 'wbi_handle_book_event' );
function wbi_handle_book_event() {
    check_ajax_referer( 'wbi_book_event', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'You must be logged in to book.' ] );
    }

    $event_id = absint( $_POST['event_id'] ?? 0 );
    if ( ! $event_id ) {
        wp_send_json_error( [ 'message' => 'Invalid event ID.' ] );
    }

    // Check price first — free events skip Amelia API and go straight to registration page
    global $wpdb;
    $event_price = $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(price, 0) FROM {$wpdb->prefix}amelia_events WHERE id = %d",
        $event_id
    ) );
    $is_free = ( floatval( $event_price ) <= 0 );

    if ( $is_free ) {
        wp_send_json_success( [
            'message'  => 'Redirecting to registration...',
            'redirect' => add_query_arg( 'event-id', $event_id, home_url( '/free-webinar-payment' ) ),
        ] );
    }

    // Paid event — create booking via Amelia API then redirect to payment page
    $user       = wp_get_current_user();
    $amelia_key = get_option( 'platform_amelia_api_key', '' );
    $currency   = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'INR';

    $response = wp_remote_post(
        home_url( '/amelia/wp-admin/admin-ajax.php?action=wpamelia_api&call=/api/v1/bookings' ),
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'Amelia'       => $amelia_key,
            ],
            'body' => json_encode( [
                'type'     => 'event',
                'bookings' => [ [
                    'customerId' => 0,
                    'persons'    => 1,
                    'customer'   => [
                        'email'     => $user->user_email,
                        'firstName' => $user->user_firstname ?: $user->display_name,
                        'lastName'  => $user->user_lastname  ?: '',
                        'phone'     => '',
                    ],
                ] ],
                'payment' => [
                    'gateway'  => 'onSite',
                    'currency' => $currency,
                ],
                'eventId' => $event_id,
            ] ),
            'timeout' => 30,
        ]
    );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => 'Booking request failed. Please try again.' ] );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! empty( $body['data']['booking']['id'] ) ) {
        wp_send_json_success( [
            'message'    => 'Booking created successfully.',
            'booking_id' => $body['data']['booking']['id'],
            'redirect'   => add_query_arg( 'event-id', $event_id, home_url( '/paid-webinar-payment' ) ),
        ] );
    } else {
        $msg = $body['message'] ?? ( $body['data']['message'] ?? 'Booking failed. The event may be full or you are already registered.' );
        wp_send_json_error( [ 'message' => $msg ] );
    }
}
/* ---------------------------------------------------------------
   AJAX — Expert: update event details (date/time, capacity, price)
--------------------------------------------------------------- */
add_action( 'wp_ajax_wbi_expert_update_event', 'wbi_handle_expert_update_event' );

function wbi_handle_expert_update_event() {
    check_ajax_referer( 'wbi_expert_edit', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Not authenticated.' ] );
    }

    $event_id = absint( $_POST['event_id'] ?? 0 );
    if ( ! $event_id ) {
        wp_send_json_error( [ 'message' => 'Invalid event ID.' ] );
    }

    // Verify the current user is the expert for this event
    if ( ! wbi_user_can_edit_event( wp_get_current_user(), $event_id ) ) {
        wp_send_json_error( [ 'message' => 'You do not have permission to edit this event.' ] );
    }

    global $wpdb;

    // -- Update wp_amelia_events ----------------------------------------------
    $event_data = [];

    $price = $_POST['price'] ?? null;
    if ( $price !== null ) {
        $event_data['price'] = max( 0, floatval( $price ) );
    }

    $max_cap = $_POST['max_capacity'] ?? null;
    if ( $max_cap !== null ) {
        $event_data['maxCapacity'] = max( 1, absint( $max_cap ) );
    }

    if ( ! empty( $event_data ) ) {
        $wpdb->update(
            $wpdb->prefix . 'amelia_events',
            $event_data,
            [ 'id' => $event_id ],
            array_fill( 0, count( $event_data ), '%s' ),
            [ '%d' ]
        );
    }

    // -- Update period start/end in wp_amelia_events_periods -----------------
    // Input arrives as IST local time; convert back to UTC for Amelia's DB
$period_start_input = sanitize_text_field( $_POST['period_start'] ?? '' );
$period_end_input   = sanitize_text_field( $_POST['period_end']   ?? '' );
$period_start = $period_start_input ? date('Y-m-d H:i:s', strtotime($period_start_input) - 19800) : '';
$period_end   = $period_end_input   ? date('Y-m-d H:i:s', strtotime($period_end_input)   - 19800) : '';    
$period_id    = absint( $_POST['period_id'] ?? 0 );

    if ( $period_id && $period_start && $period_end ) {
        // Basic sanity: end must be after start
        if ( strtotime( $period_end ) > strtotime( $period_start ) ) {
            $wpdb->update(
                $wpdb->prefix . 'amelia_events_periods',
                [
                    'periodStart' => $period_start,
                    'periodEnd'   => $period_end,
                ],
                [ 'id' => $period_id, 'eventId' => $event_id ],
                [ '%s', '%s' ],
                [ '%d', '%d' ]
            );
        } else {
            wp_send_json_error( [ 'message' => 'End time must be after start time.' ] );
        }
    }

    if ( $wpdb->last_error ) {
        wp_send_json_error( [ 'message' => 'Database error: ' . $wpdb->last_error ] );
    }

    wp_send_json_success( [ 'message' => 'Webinar updated successfully.' ] );
}

/* ---------------------------------------------------------------
   AJAX — Expert: upload material
--------------------------------------------------------------- */
add_action( 'wp_ajax_wbi_expert_upload_material', 'wbi_handle_expert_upload_material' );

function wbi_handle_expert_upload_material() {
    check_ajax_referer( 'wbi_expert_edit', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Not authenticated.' ] );
    }

    $event_id = absint( $_POST['event_id'] ?? 0 );
    if ( ! $event_id ) {
        wp_send_json_error( [ 'message' => 'Invalid event ID.' ] );
    }

    // Verify ownership
    if ( ! wbi_user_can_edit_event( wp_get_current_user(), $event_id ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    if ( empty( $_FILES['material_file'] ) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( [ 'message' => 'No valid file uploaded.' ] );
    }

    $title         = sanitize_text_field( $_POST['material_title'] ?? '' );
    $material_type = sanitize_text_field( $_POST['material_type']  ?? 'Handouts / PDF Notes' );

    if ( empty( $title ) ) {
        wp_send_json_error( [ 'message' => 'Material title is required.' ] );
    }

    // Use WP media upload handler
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload( 'material_file', 0 );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error( [ 'message' => 'Upload failed: ' . $attachment_id->get_error_message() ] );
    }

    $file_url = wp_get_attachment_url( $attachment_id );

    // Ensure table exists
    global $wpdb;
    $table = $wpdb->prefix . 'webinar_materials';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
        $wpdb->query( "CREATE TABLE IF NOT EXISTS $table (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id     BIGINT(20) UNSIGNED NOT NULL,
            title        VARCHAR(255)        NOT NULL,
            material_type VARCHAR(100)       NOT NULL DEFAULT 'Handouts / PDF Notes',
            file_url     TEXT                NOT NULL,
            uploaded_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;" );
    }

    $inserted = $wpdb->insert(
        $table,
        [
            'event_id'      => $event_id,
            'title'         => $title,
            'material_type' => $material_type,
            'file_url'      => $file_url,
            'uploaded_at'   => current_time( 'mysql' ),
        ],
        [ '%d', '%s', '%s', '%s', '%s' ]
    );

    if ( ! $inserted ) {
        wp_send_json_error( [ 'message' => 'Failed to save material record.' ] );
    }

    wp_send_json_success( [
        'message'       => 'Material uploaded successfully.',
        'id'            => $wpdb->insert_id,
        'title'         => $title,
        'material_type' => $material_type,
        'file_url'      => $file_url,
        'uploaded_at'   => current_time( 'mysql' ),
    ] );
}

/* ---------------------------------------------------------------
   AJAX — Expert: delete material
--------------------------------------------------------------- */
add_action( 'wp_ajax_wbi_expert_delete_material', 'wbi_handle_expert_delete_material' );

function wbi_handle_expert_delete_material() {
    check_ajax_referer( 'wbi_expert_edit', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Not authenticated.' ] );
    }

    $event_id    = absint( $_POST['event_id']    ?? 0 );
    $material_id = absint( $_POST['material_id'] ?? 0 );

    if ( ! $event_id || ! $material_id ) {
        wp_send_json_error( [ 'message' => 'Invalid parameters.' ] );
    }

    if ( ! wbi_user_can_edit_event( wp_get_current_user(), $event_id ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'webinar_materials';
    $deleted = $wpdb->delete( $table, [ 'id' => $material_id, 'event_id' => $event_id ], [ '%d', '%d' ] );

    if ( $deleted ) {
        wp_send_json_success( [ 'message' => 'Material deleted.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Could not delete material.' ] );
    }
}

/* ---------------------------------------------------------------
   SHORTCODE
--------------------------------------------------------------- */
add_shortcode( 'webinar_info', 'wbi_render' );

function wbi_render() {
    $event_id = absint( $_GET['event-id'] ?? 0 );

    $cur_raw  = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '&#8377;';
    $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'INR';
    $uid      = get_current_user_id();
    $user     = $uid ? wp_get_current_user() : null;
    $uname    = $user ? (trim($user->display_name) ?: 'Guest') : 'Guest';
    $avatar   = $uid ? get_avatar_url($uid, ['size'=>36]) : '';
    $u_first  = $user ? ($user->user_firstname ?: $user->display_name) : '';
    $amelia_key = get_option('platform_amelia_api_key','');
    $my_events  = home_url('/my-events/');

    $is_current_user_expert = $user && in_array('expert', (array)$user->roles, true);

    $url_dashboard = home_url('/webinar-dashboard');
    $url_library   = home_url('/webinar-library');
    $url_myevents  = home_url('/my-events');

    $event          = $event_id ? wbi_get_event($event_id) : null;
    $amelia_resp    = $event_id ? wbi_amelia_call('/api/v1/events/' . $event_id) : null;
    $amelia_event   = $amelia_resp['data']['event'] ?? null;
    $expert_user    = $event_id ? wbi_get_expert_for_event($event_id) : null;
    $booking_status = ($event_id && $uid) ? wbi_booking_status($uid, $event_id) : 'none';

    $is_viewing_own_event = false;
    if ($expert_user && $user) {
        $is_viewing_own_event = ($expert_user->ID === $user->ID);
    }

    // -- Direct DB fallback for can_edit --------------------------------------
    // wbi_get_expert_for_event() depends on an Amelia API call that may fail.
    // As a reliable fallback, also check if the current user's email appears as
    // a provider in wp_amelia_users linked to this event via the organizer field
    // or the providers table — whichever works first.
    $can_edit = false;
    if ($is_current_user_expert && $event_id && $uid) {
        // 1. Did wbi_get_expert_for_event() already confirm it?
        if ($is_viewing_own_event) {
            $can_edit = true;
        } else {
            // 2. Direct DB: is the current user the organizer of this event?
            global $wpdb;
            $user_email   = strtolower(trim($user->user_email));
            $organizer_id = $wpdb->get_var($wpdb->prepare(
                "SELECT e.organizerId FROM {$wpdb->prefix}amelia_events e WHERE e.id = %d LIMIT 1",
                $event_id
            ));
            if ($organizer_id) {
                $organizer_email = $wpdb->get_var($wpdb->prepare(
                    "SELECT LOWER(TRIM(email)) FROM {$wpdb->prefix}amelia_users WHERE id = %d LIMIT 1",
                    (int)$organizer_id
                ));
                if ($organizer_email && $organizer_email === $user_email) {
                    $can_edit = true;
                }
            }
            // 3. Direct DB: is the current user a provider for this event's period?
            if (!$can_edit) {
                $provider_match = $wpdb->get_var($wpdb->prepare(
                    "SELECT au.id FROM {$wpdb->prefix}amelia_users au
                     INNER JOIN {$wpdb->prefix}amelia_events_to_providers etp ON etp.userId = au.id
                     WHERE etp.eventId = %d AND LOWER(TRIM(au.email)) = %s
                     LIMIT 1",
                    $event_id, $user_email
                ));
                if ($provider_match) {
                    $can_edit = true;
                }
            }
        }
    }

    $event_tags = [];
    if ($amelia_event && !empty($amelia_event['tags'])) {
        foreach ($amelia_event['tags'] as $t) {
            if (!empty($t['name'])) $event_tags[] = $t['name'];
        }
    }

    $expert_meta = null;
    if ($expert_user) {
        $eid_exp  = $expert_user->ID;
        $about    = (string) get_user_meta($eid_exp, '_tutor_instructor_about_me', true);
        if ($about === '') $about = (string) get_user_meta($eid_exp, 'description', true);
        $raw_spec = (string) get_user_meta($eid_exp, '_tutor_instructor_speciality', true);
        $specs    = array_values(array_filter(array_map('trim', explode(',', $raw_spec))));
        $expert_meta = [
            'name'        => $expert_user->display_name,
            'avatar_url'  => get_avatar_url($eid_exp, ['size' => 200]),
            'about'       => $about,
            'specs_arr'   => $specs,
            'is_verified' => get_user_meta($eid_exp, '_tutor_instructor_status', true) === 'approved',
            'profile_url' => add_query_arg('expert_id', $eid_exp, home_url('/student-educator-profile')),
        ];
    }

    $start_ts = 0; $end_ts = 0;
    $date_str = ''; $time_str = '';
    $price = 0; $free = true; $max_cap = 0; $booked_cnt = 0; $spots_left = null;
    $is_full = false; $is_reg = false; $is_pend = false;
    $is_past_webinar = false;
    $period_id = 0;
    $period_start_raw = ''; $period_end_raw = '';

    if ($event) {
        /*
         * FIX: Amelia stores dates as local time strings, NOT UTC.
         * Create DateTimeImmutable objects by treating the DB string as local time,
         * then derive timestamps correctly. This prevents the double-timezone-offset bug.
         */
        $tz  = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
 
        // Prefer the raw DB values (always local time); fall back to Amelia API if somehow missing.
        $ps = $event['periodStart'];
        $pe = $event['periodEnd'];
 
        // Parse as local time explicitly — do NOT pass $tz to createFromFormat here,
        // because Amelia already stored these strings in local time.
        $dt_s = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ps, $tz);
        $dt_e = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $pe, $tz);
 
        // Fallback
        if ( ! $dt_s ) $dt_s = new DateTimeImmutable($ps, $tz);
        if ( ! $dt_e ) $dt_e = new DateTimeImmutable($pe, $tz);
 
        $start_ts   = $dt_s->getTimestamp() + 19800;
        $end_ts     = $dt_e->getTimestamp() + 19800;
        $date_str   = wp_date('l, F j, Y', $start_ts);
        $time_str   = wp_date('g:i A', $start_ts) . ' &ndash; ' . wp_date('g:i A', $end_ts);
        $price      = (float) $event['price'];
        $free       = $price <= 0;
        $max_cap    = (int)   $event['maxCap'];
        $booked_cnt = (int)   $event['booked'];
        $spots_left = $max_cap > 0 ? max(0, $max_cap - $booked_cnt) : null;
        $is_full    = $spots_left !== null && $spots_left <= 0;
        $is_reg     = $booking_status === 'registered';
        $is_pend    = $booking_status === 'pending';
        $is_past_webinar = ( $end_ts > 0 && time() > $end_ts );
        $period_id        = (int) $event['periodId'];
        $period_start_raw = $event['periodStart'];
        $period_end_raw   = $event['periodEnd'];
    }
 
    /*
     * For datetime-local input: "YYYY-MM-DDTHH:MM"
     * Since Amelia stores local time, we just reformat the raw string directly —
     * no timestamp conversion needed, which avoids any offset drift.
     */
    // $start_ts / $end_ts already have +19800 applied (UTC?IST), so format directly from those
// Raw DB value is UTC; add 19800s (IST offset) to get correct local time for the input
$input_start = $period_start_raw ? date('Y-m-d\TH:i', strtotime($period_start_raw) + 19800) : '';
$input_end   = $period_end_raw   ? date('Y-m-d\TH:i', strtotime($period_end_raw)   + 19800) : '';    $book_nonce        = wp_create_nonce('wbi_book_event');
    $expert_edit_nonce = wp_create_nonce('wbi_expert_edit');
 
    // Plain currency symbol for JS (strip HTML entities)
    $cur_raw_plain = html_entity_decode( $cur_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    ob_start();
    ?>
<style>
/* ---- Reset ---- */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
#wpadminbar{display:none!important;}
html{margin-top:0!important;}
header,#masthead,.site-header,.main-header,#header,.elementor-location-header,
.ast-main-header-wrap,#site-header,.fusion-header-wrapper,.header-wrap,
.nav-primary,.navbar,div[data-elementor-type="header"]{display:none!important;}
.site-content,.site-main,#content,#page{margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;}

/* ---- Tokens ---- */
:root{
    --bg:#f0f2f8;--surf:#fff;--surf2:#f7f8fc;--bdr:#e4e7f0;
    --txt:#0d1025;--sub:#5a6180;--muted:#98a0b8;
    --acc:#2563eb;--acc-d:#1d4ed8;--acc-lt:#eff4ff;
    --grn:#059669;--grn-lt:#ecfdf5;
    --amber:#d97706;--amber-lt:#fffbeb;
    --r:16px;--r-sm:10px;
    --sh:0 1px 3px rgba(13,16,37,.06),0 8px 28px rgba(13,16,37,.07);
    --sh-lg:0 4px 16px rgba(13,16,37,.08),0 24px 56px rgba(37,99,235,.12);
    font-family:'DM Sans',sans-serif;
    color:var(--txt);
    background:var(--bg);
}

/* ---- Nav ---- */
.wbi-nav{
    background:rgba(255,255,255,.95);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
    border-bottom:1px solid var(--bdr);position:sticky;top:0;z-index:200;
    box-shadow:0 1px 0 var(--bdr),0 3px 14px rgba(13,16,37,.05);
}
.wbi-nav-inner{
    max-width:1240px;margin:auto;padding:0 32px;height:60px;
    display:flex;align-items:center;justify-content:space-between;gap:16px;
}
.wbi-nav-logo{
    font-family:'Playfair Display',serif;font-size:17px;font-weight:700;
    color:var(--acc);text-decoration:none;flex-shrink:0;letter-spacing:-.2px;
}
.wbi-nav-links{display:flex;gap:2px;}
.wbi-nav-links a{
    padding:7px 14px;border-radius:8px;font-size:13.5px;font-weight:500;
    color:var(--sub);text-decoration:none;transition:background .16s,color .16s;
}
.wbi-nav-links a:hover{background:var(--acc-lt);color:var(--acc);}
.wbi-nav-right{display:flex;align-items:center;gap:11px;flex-shrink:0;}
.wbi-nav-right img{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid var(--bdr);}
.wbi-nav-uname{font-size:13px;font-weight:600;color:var(--txt);}
.wbi-nav-logout{
    padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;
    background:var(--txt);color:#fff;text-decoration:none;transition:opacity .15s;
}
.wbi-nav-logout:hover{opacity:.82;}
@media(max-width:768px){.wbi-nav-links{display:none;}.wbi-nav-inner{padding:0 16px;}}

/* ---- Page ---- */
.wbi-page{max-width:1240px;margin:0 auto;padding:28px 32px 72px;}
@media(max-width:768px){.wbi-page{padding:16px 16px 48px;}}

/* ---- Back ---- */
.wbi-back{
    display:inline-flex;align-items:center;gap:7px;
    font-size:13px;font-weight:600;color:var(--sub);
    text-decoration:none;margin-bottom:24px;
    padding:7px 14px 7px 10px;border-radius:8px;
    border:1.5px solid var(--bdr);background:var(--surf);
    transition:border-color .16s,color .16s,background .16s;
    box-shadow:0 1px 3px rgba(13,16,37,.04);
}
.wbi-back:hover{border-color:var(--acc);color:var(--acc);background:var(--acc-lt);}
.wbi-back svg{transition:transform .18s;}
.wbi-back:hover svg{transform:translateX(-3px);}

/* ---- Layout ---- */
.wbi-layout{display:grid;grid-template-columns:1fr 356px;gap:24px;align-items:start;}
@media(max-width:980px){.wbi-layout{grid-template-columns:1fr;}}

/* ---- Card ---- */
.wbi-card{background:var(--surf);border:1px solid var(--bdr);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;}

/* ---- Hero ---- */
.wbi-hero{height:168px;position:relative;display:flex;align-items:flex-end;padding:22px 28px;overflow:hidden;border-radius:0;}
.wbi-hero-bg{position:absolute;inset:0;background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 55%,#0ea5e9 100%);}
.wbi-hero-dots{position:absolute;inset:0;opacity:.07;background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:28px 28px;}
.wbi-hero-glow{position:absolute;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14) 0%,transparent 70%);right:-50px;top:-70px;pointer-events:none;}
.wbi-hero-content{position:relative;z-index:1;flex:1;}
.wbi-hero-title{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#fff;line-height:1.35;text-shadow:0 2px 12px rgba(0,0,0,.2);}

/* Expert toolbar — sits above the hero outside overflow:hidden */
.wbi-expert-toolbar{
    display:flex;align-items:center;justify-content:flex-end;
    padding:14px 20px 0;gap:10px;
}
.wbi-hero-edit-badge{
    display:inline-flex;align-items:center;gap:7px;
    padding:9px 20px;border-radius:10px;
    background:linear-gradient(135deg,var(--acc),var(--acc-d));
    border:none;
    color:#fff;font-size:13px;font-weight:700;cursor:pointer;
    transition:opacity .18s,transform .18s,box-shadow .18s;white-space:nowrap;
    box-shadow:0 2px 10px rgba(37,99,235,.3);
    font-family:'DM Sans',sans-serif;
}
.wbi-hero-edit-badge:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 4px 16px rgba(37,99,235,.4);}
.wbi-hero-edit-badge svg{width:14px;height:14px;}

/* ---- Tags ---- */
.wbi-tags{display:flex;flex-wrap:wrap;gap:6px;padding:18px 28px 0;}
.wbi-tag{padding:4px 12px;border-radius:20px;font-size:11.5px;font-weight:600;background:var(--acc-lt);color:var(--acc);border:1px solid rgba(37,99,235,.15);}
.wbi-tag.free{background:var(--grn-lt);color:var(--grn);border-color:rgba(5,150,105,.2);}
.wbi-tag.reg{background:var(--grn-lt);color:var(--grn);border-color:rgba(5,150,105,.2);}
.wbi-tag.pend{background:var(--amber-lt);color:var(--amber);border-color:rgba(217,119,6,.2);}
.wbi-tag.full{background:#f1f5f9;color:var(--muted);border-color:var(--bdr);}
.wbi-tag.past{background:#f1f5f9;color:var(--muted);border-color:var(--bdr);}
.wbi-tag.owner{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;border-color:transparent;}

/* ---- Meta section ---- */
.wbi-meta-section{padding:20px 28px 24px;}
.wbi-meta-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:22px;}
@media(max-width:540px){.wbi-meta-grid{grid-template-columns:1fr;}}
.wbi-meta-item{display:flex;align-items:flex-start;gap:11px;padding:14px 15px;border-radius:12px;background:var(--surf2);border:1px solid var(--bdr);}
.wbi-meta-icon{width:34px;height:34px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:var(--acc-lt);}
.wbi-meta-icon svg{color:var(--acc);}
.wbi-meta-icon.grn{background:var(--grn-lt);}
.wbi-meta-icon.grn svg{color:var(--grn);}
.wbi-meta-icon.amber{background:var(--amber-lt);}
.wbi-meta-icon.amber svg{color:var(--amber);}
.wbi-meta-label{font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;}
.wbi-meta-value{font-size:14px;font-weight:600;color:var(--txt);line-height:1.3;}

/* ---- CTA row ---- */
.wbi-cta-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;padding:18px 28px;border-top:1px solid var(--bdr);background:var(--surf2);}
.wbi-price-wrap{display:flex;align-items:baseline;gap:6px;}
.wbi-price-big{font-family:'Playfair Display',serif;font-size:30px;font-weight:700;color:var(--acc);}
.wbi-price-big.free{color:var(--grn);}
.wbi-price-note{font-size:12px;color:var(--muted);font-weight:500;}

/* ---- Buttons ---- */
.wbi-btn{
    display:inline-flex;align-items:center;gap:8px;
    padding:12px 26px;border-radius:10px;
    font-family:'DM Sans',sans-serif;font-size:14.5px;font-weight:700;
    border:none;cursor:pointer;
    transition:transform .18s,box-shadow .18s,opacity .16s;
    text-decoration:none;white-space:nowrap;
}
.wbi-btn-book{background:linear-gradient(135deg,var(--acc) 0%,#1d4ed8 100%);color:#fff;box-shadow:0 3px 12px rgba(37,99,235,.35);}
.wbi-btn-book:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(37,99,235,.4);color:#fff;}
.wbi-btn-book:disabled{opacity:.65;cursor:wait;transform:none;}
.wbi-btn-paynow{background:linear-gradient(135deg,#d97706 0%,#b45309 100%);color:#fff;box-shadow:0 3px 12px rgba(217,119,6,.35);}
.wbi-btn-paynow:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(217,119,6,.4);color:#fff;}
.wbi-btn-registered{background:var(--grn-lt);color:var(--grn);border:2px solid rgba(5,150,105,.25);cursor:default;pointer-events:none;}
.wbi-btn-full{background:var(--surf2);color:var(--muted);border:1.5px solid var(--bdr);cursor:default;pointer-events:none;}

/* ---- Booking feedback ---- */
.wbi-book-msg{
    font-size:13px;font-weight:600;padding:10px 16px;border-radius:8px;
    display:none;margin-top:8px;width:100%;text-align:center;
}
.wbi-book-msg.error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;display:block;}
.wbi-book-msg.success{background:var(--grn-lt);color:var(--grn);border:1px solid #a7f3d0;display:block;}

@keyframes wbi-spin{to{transform:rotate(360deg);}}

/* ---- Description ---- */
.wbi-desc-section{padding:22px 28px;border-top:1px solid var(--bdr);}
.wbi-sec-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;}
.wbi-desc-text{font-size:14px;color:var(--sub);line-height:1.75;}

/* ---- Right column ---- */
.wbi-right{display:flex;flex-direction:column;gap:18px;}

/* ---- Expert card ---- */
.wbi-expert-card{overflow:visible!important;}
.wbi-expert-head{background:linear-gradient(155deg,#0f172a 0%,#1e293b 100%);padding:26px 24px 62px;border-radius:var(--r) var(--r) 0 0;position:relative;}
.wbi-expert-head-label{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.45);margin-bottom:5px;}
.wbi-expert-head-name{font-family:'Playfair Display',serif;font-size:19px;font-weight:600;color:#fff;line-height:1.3;text-transform:capitalize;}
.wbi-expert-verified{display:inline-flex;align-items:center;gap:4px;margin-top:8px;padding:3px 10px;border-radius:20px;background:rgba(5,150,105,.25);color:#6ee7b7;font-size:11px;font-weight:700;border:1px solid rgba(5,150,105,.3);}
.wbi-expert-av-wrap{position:absolute;bottom:-38px;left:24px;width:76px;height:76px;}
.wbi-expert-avatar{width:76px;height:76px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:0 4px 16px rgba(13,16,37,.2);}
.wbi-expert-body{padding:50px 24px 24px;}
.wbi-expert-specs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;}
.wbi-expert-spec{padding:4px 11px;border-radius:20px;font-size:11.5px;font-weight:600;background:var(--acc-lt);color:var(--acc);border:1px solid rgba(37,99,235,.15);}
.wbi-expert-about{font-size:13.5px;color:var(--sub);line-height:1.65;margin-bottom:18px;}
.wbi-expert-about:empty{display:none;}
.wbi-profile-link{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:9px;font-size:13px;font-weight:700;background:var(--surf2);color:var(--txt);border:1.5px solid var(--bdr);text-decoration:none;transition:border-color .16s,background .16s,color .16s;}
.wbi-profile-link:hover{border-color:var(--acc);color:var(--acc);background:var(--acc-lt);}

/* ---- Zoom card ---- */
.wbi-zoom-card{padding:20px 24px;}
.wbi-zoom-btn{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:10px;background:var(--acc);color:#fff;text-decoration:none;font-size:14px;font-weight:700;transition:background .16s,transform .16s;}
.wbi-zoom-btn:hover{background:var(--acc-d);transform:translateY(-1px);color:#fff;}
.wbi-zoom-live{margin-left:auto;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:700;background:rgba(255,255,255,.2);}
.wbi-past-notice{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:10px;background:#f8fafc;border:1.5px solid var(--bdr);font-size:13px;font-weight:600;color:var(--muted);}
.wbi-past-notice svg{flex-shrink:0;color:var(--muted);}

/* ---- Materials Panel ---- */
.wbi-materials-panel{margin-top:24px;}
.wbi-materials-header{display:flex;align-items:center;gap:12px;padding:20px 28px 18px;border-bottom:1px solid var(--bdr);background:linear-gradient(135deg,#fafbff 0%,#fff 100%);}
.wbi-materials-header svg{color:var(--acc);flex-shrink:0;}
.wbi-materials-header h3{font-family:'Playfair Display',serif;font-size:19px;font-weight:600;color:var(--txt);flex:1;}
.wbi-materials-section{padding:20px 28px;border-bottom:1px solid var(--bdr);}
.wbi-materials-section:last-child{border-bottom:none;}
.wbi-materials-section-header{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
.wbi-materials-section-header svg{color:var(--acc);flex-shrink:0;}
.wbi-materials-section-header h4{font-size:14px;font-weight:700;color:var(--txt);flex:1;}
.wbi-materials-count{display:flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 8px;border-radius:12px;background:var(--acc-lt);color:var(--acc);font-size:11px;font-weight:700;}
.wbi-materials-list{display:flex;flex-direction:column;gap:10px;}
.wbi-material-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:12px;border:1.5px solid var(--bdr);background:var(--surf2);transition:all .18s;}
.wbi-material-item:hover{border-color:var(--acc);background:#fff;box-shadow:0 2px 8px rgba(13,16,37,.06);}
.wbi-material-icon{width:40px;height:40px;border-radius:10px;background:var(--acc-lt);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.wbi-material-icon svg{color:var(--acc);}
.wbi-material-info{flex:1;min-width:0;}
.wbi-material-title{font-size:14px;font-weight:600;color:var(--txt);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.wbi-material-meta{font-size:12px;color:var(--muted);}
.wbi-material-type{display:inline-block;margin-top:4px;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;background:var(--acc-lt);color:var(--acc);}
.wbi-material-download{width:36px;height:36px;border-radius:50%;background:var(--acc);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .18s;text-decoration:none;}
.wbi-material-download:hover{background:var(--acc-d);transform:scale(1.1);}
.wbi-material-download svg{color:#fff;}
.wbi-material-delete{width:32px;height:32px;border-radius:50%;background:#fef2f2;border:1.5px solid #fecaca;display:flex;align-items:center;justify-content:center;flex-shrink:0;cursor:pointer;transition:all .18s;margin-left:6px;}
.wbi-material-delete:hover{background:#fee2e2;border-color:#fca5a5;transform:scale(1.08);}
.wbi-material-delete svg{color:#dc2626;}
.wbi-materials-empty{text-align:center;padding:48px 24px;color:var(--muted);}
.wbi-materials-empty svg{opacity:.15;margin:0 auto 16px;display:block;}
.wbi-materials-empty p{font-size:14px;font-weight:500;}

/* ============================================================
   EXPERT EDIT PANEL
   ============================================================ */

/* Slide-in drawer overlay */
.wbi-edit-overlay{
    position:fixed;inset:0;z-index:1000;
    background:rgba(13,16,37,.55);backdrop-filter:blur(4px);
    opacity:0;pointer-events:none;transition:opacity .3s ease;
}
.wbi-edit-overlay.open{opacity:1;pointer-events:all;}

/* Drawer panel */
.wbi-edit-drawer{
    position:fixed;top:0;right:0;bottom:0;z-index:1001;
    width:min(560px,100vw);
    background:var(--surf);
    box-shadow:-8px 0 40px rgba(13,16,37,.18);
    display:flex;flex-direction:column;
    transform:translateX(100%);transition:transform .35s cubic-bezier(.4,0,.2,1);
    overflow:hidden;
}
.wbi-edit-drawer.open{transform:translateX(0);}

/* Drawer header */
.wbi-edit-drawer-head{
    padding:22px 28px 20px;
    background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 60%,#2563eb 100%);
    flex-shrink:0;
}
.wbi-edit-drawer-head-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.wbi-edit-drawer-title{
    font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#fff;
}
.wbi-edit-close{
    width:36px;height:36px;border-radius:50%;
    background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.25);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:background .18s;color:#fff;flex-shrink:0;
}
.wbi-edit-close:hover{background:rgba(255,255,255,.22);}
.wbi-edit-drawer-sub{font-size:13px;color:rgba(255,255,255,.5);margin-top:2px;}

/* Drawer tabs */
.wbi-edit-tabs{
    display:flex;gap:0;border-bottom:1px solid var(--bdr);flex-shrink:0;background:var(--surf2);
}
.wbi-edit-tab{
    flex:1;padding:13px 16px;font-size:13px;font-weight:700;color:var(--muted);
    text-align:center;cursor:pointer;border-bottom:3px solid transparent;
    transition:color .18s,border-color .18s,background .18s;
    display:flex;align-items:center;justify-content:center;gap:7px;
}
.wbi-edit-tab:hover{color:var(--txt);background:var(--surf);}
.wbi-edit-tab.active{color:var(--acc);border-bottom-color:var(--acc);background:var(--surf);}
.wbi-edit-tab svg{width:14px;height:14px;}

/* Drawer body */
.wbi-edit-body{flex:1;overflow-y:auto;padding:28px;}

/* Tab panes */
.wbi-edit-pane{display:none;}
.wbi-edit-pane.active{display:block;}

/* Form rows */
.wbi-ef-group{margin-bottom:20px;}
.wbi-ef-label{
    display:block;font-size:12px;font-weight:700;color:var(--sub);
    text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;
}
.wbi-ef-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.wbi-ef-input{
    width:100%;padding:11px 14px;border-radius:10px;
    border:1.5px solid var(--bdr);background:var(--surf2);
    font-family:'DM Sans',sans-serif;font-size:14px;color:var(--txt);
    transition:border-color .18s,box-shadow .18s;outline:none;
    appearance:none;-webkit-appearance:none;
}
.wbi-ef-input:focus{border-color:var(--acc);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.wbi-ef-hint{font-size:11.5px;color:var(--muted);margin-top:6px;}

/* Info box */
.wbi-ef-info{
    padding:12px 16px;border-radius:10px;
    background:var(--acc-lt);border:1px solid rgba(37,99,235,.2);
    font-size:12.5px;color:var(--acc);margin-bottom:20px;
    display:flex;gap:9px;align-items:flex-start;
}
.wbi-ef-info svg{flex-shrink:0;margin-top:1px;}

/* Save button */
.wbi-ef-save{
    width:100%;padding:13px 24px;border-radius:10px;
    background:linear-gradient(135deg,var(--acc) 0%,var(--acc-d) 100%);
    color:#fff;font-family:'DM Sans',sans-serif;font-size:14.5px;font-weight:700;
    border:none;cursor:pointer;margin-top:8px;
    display:flex;align-items:center;justify-content:center;gap:9px;
    transition:transform .18s,box-shadow .18s,opacity .16s;
    box-shadow:0 3px 12px rgba(37,99,235,.3);
}
.wbi-ef-save:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 6px 20px rgba(37,99,235,.4);}
.wbi-ef-save:disabled{opacity:.6;cursor:wait;transform:none;}

/* Status message */
.wbi-ef-status{
    margin-top:14px;padding:11px 16px;border-radius:9px;
    font-size:13px;font-weight:600;text-align:center;display:none;
}
.wbi-ef-status.ok{background:var(--grn-lt);color:var(--grn);border:1px solid #a7f3d0;display:block;}
.wbi-ef-status.err{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;display:block;}

/* ---- Upload zone ---- */
.wbi-upload-zone{
    border:2px dashed var(--bdr);border-radius:12px;
    padding:32px 20px;text-align:center;cursor:pointer;
    transition:border-color .2s,background .2s;background:var(--surf2);
    position:relative;
}
.wbi-upload-zone:hover,.wbi-upload-zone.drag-over{
    border-color:var(--acc);background:var(--acc-lt);
}
.wbi-upload-zone input[type=file]{
    position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;
}
.wbi-upload-icon{
    width:48px;height:48px;border-radius:12px;
    background:var(--acc-lt);display:flex;align-items:center;justify-content:center;
    margin:0 auto 14px;
}
.wbi-upload-icon svg{color:var(--acc);}
.wbi-upload-zone-title{font-size:14px;font-weight:700;color:var(--txt);margin-bottom:4px;}
.wbi-upload-zone-sub{font-size:12px;color:var(--muted);}
.wbi-upload-file-name{
    margin-top:10px;padding:8px 14px;border-radius:8px;
    background:#fff;border:1.5px solid var(--acc);
    font-size:13px;font-weight:600;color:var(--acc);display:none;
}

/* Materials list in edit panel */
.wbi-edit-mat-list{margin-top:20px;display:flex;flex-direction:column;gap:8px;}
.wbi-edit-mat-item{
    display:flex;align-items:center;gap:12px;
    padding:11px 14px;border-radius:10px;
    border:1.5px solid var(--bdr);background:var(--surf2);
    transition:border-color .16s;
}
.wbi-edit-mat-item:hover{border-color:var(--bdr);}
.wbi-edit-mat-info{flex:1;min-width:0;}
.wbi-edit-mat-title{font-size:13px;font-weight:600;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.wbi-edit-mat-type{font-size:11px;color:var(--muted);margin-top:2px;}
.wbi-edit-mat-del{
    width:30px;height:30px;border-radius:8px;flex-shrink:0;
    background:#fef2f2;border:1.5px solid #fecaca;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:all .16s;
}
.wbi-edit-mat-del:hover{background:#fee2e2;transform:scale(1.1);}
.wbi-edit-mat-del svg{color:#dc2626;width:13px;height:13px;}
.wbi-edit-mat-empty{text-align:center;padding:28px 16px;color:var(--muted);font-size:13px;}

/* Section divider */
.wbi-ef-divider{height:1px;background:var(--bdr);margin:24px 0;}

/* ---- Fade-in ---- */
@keyframes wbi-fi{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
.wbi-fi{animation:wbi-fi .42s ease both;}
.wbi-fi-1{animation-delay:.05s;}
.wbi-fi-2{animation-delay:.12s;}
.wbi-fi-3{animation-delay:.20s;}
</style>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">

<!-- NAV -->
<nav class="wbi-nav">
    <div class="wbi-nav-inner">
        <a href="<?php echo esc_url(home_url()); ?>" class="wbi-nav-logo"><?php echo esc_html(get_bloginfo('name')); ?></a>
        <div class="wbi-nav-links">
            <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
            <a href="<?php echo esc_url($url_library); ?>">Webinar Library</a>
            
        </div>
        <div class="wbi-nav-right">
            <?php if ($user): ?>
                <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($uname); ?>">
                <span class="wbi-nav-uname">Hi, <?php echo esc_html($u_first ?: $uname); ?></span>
                <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="wbi-nav-logout">Logout</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="wbi-page">


    <a href="<?php echo esc_url(home_url('/webinar-library')); ?>" class="wbi-back">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Library
    </a>

    <?php if (!$event): ?>
        <div style="padding:48px;background:#fff;border-radius:var(--r);text-align:center;color:#dc2626;font-weight:600;font-size:15px;">
            <?php echo $event_id ? 'Event not found.' : 'No event ID provided.'; ?>
        </div>
    <?php else: ?>

    <div class="wbi-layout">

        <!-- LEFT: EVENT -->
        <div class="wbi-fi">
            <div class="wbi-card">

              
                <div class="wbi-hero">
                    <div class="wbi-hero-bg"></div>
                    <div class="wbi-hero-dots"></div>
                    <div class="wbi-hero-glow"></div>
                    <div class="wbi-hero-content">
                        <div class="wbi-hero-title"><?php echo esc_html($event['name']); ?></div>
                    </div>
                </div>

                <div class="wbi-tags">
                    <?php if ($can_edit): ?>
                        <span class="wbi-tag owner">Your Webinar</span>
                    <?php endif; ?>
                    <?php if ($is_past_webinar): ?>
                        <span class="wbi-tag past">Past Webinar</span>
                    <?php else: ?>
                        <span class="wbi-tag <?php echo $free ? 'free' : ''; ?>"><?php echo $free ? 'Free' : 'Paid'; ?></span>
                        <?php if ($is_reg): ?>
                            <span class="wbi-tag reg">&#10003; Registered</span>
                        <?php elseif ($is_pend): ?>
                            <span class="wbi-tag pend">&#9679; Payment Pending</span>
                        <?php elseif ($is_full): ?>
                            <span class="wbi-tag full">Fully Booked</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php foreach ($event_tags as $tag): ?>
                        <span class="wbi-tag"><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="wbi-meta-section">
                    <div class="wbi-meta-grid">
                        <div class="wbi-meta-item">
                            <div class="wbi-meta-icon">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            </div>
                            <div>
                                <div class="wbi-meta-label">Date</div>
                                <div class="wbi-meta-value" id="wbi-meta-date"><?php echo esc_html($date_str); ?></div>
                            </div>
                        </div>
                        <div class="wbi-meta-item">
                            <div class="wbi-meta-icon">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <div>
                                <div class="wbi-meta-label">Time</div>
                                <div class="wbi-meta-value" id="wbi-meta-time"><?php echo $time_str; ?></div>
                            </div>
                        </div>
                        <?php if ($max_cap > 0): ?>
                        <div class="wbi-meta-item">
                            <div class="wbi-meta-icon <?php echo $is_full ? 'amber' : 'grn'; ?>">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                                </svg>
                            </div>
                            <div>
                                <div class="wbi-meta-label">Capacity</div>
                                <div class="wbi-meta-value" id="wbi-meta-cap"><?php echo esc_html($booked_cnt . ' / ' . $max_cap . ' booked'); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($spots_left !== null && !$is_past_webinar): ?>
                        <div class="wbi-meta-item">
                            <div class="wbi-meta-icon <?php echo ($is_full || $spots_left <= 3) ? 'amber' : 'grn'; ?>">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <?php if ($is_full): ?>
                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                    <?php else: ?>
                                        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                    <?php endif; ?>
                                </svg>
                            </div>
                            <div>
                                <div class="wbi-meta-label">Availability</div>
                                <div class="wbi-meta-value">
                                    <?php echo $is_full ? 'No slots left' : esc_html($spots_left . ' slot' . ($spots_left === 1 ? '' : 's') . ' remaining'); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Price + CTA — hidden for past webinars AND for experts viewing their own event -->
                <?php if ($can_edit && !$is_past_webinar): ?>
                <!-- Expert: read-only price + earnings summary bar -->
                <div class="wbi-cta-row" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);">
                    <div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap;">
                        <div>
                            <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">Registration Price</div>
                            <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:<?php echo $free ? '#34d399' : '#60a5fa'; ?>;" id="wbi-expert-price-display">
                                <?php echo $free ? 'Free' : esc_html($cur_raw . number_format($price, 0)); ?>
                            </div>
                        </div>
                        <div style="width:1px;height:36px;background:rgba(255,255,255,.1);"></div>
                        <div>
                            <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">Registrations</div>
                            <div style="font-size:20px;font-weight:700;color:#fff;" id="wbi-expert-bookings-display">
                                <?php echo esc_html($booked_cnt); ?><?php if ($max_cap > 0): ?><span style="font-size:13px;color:rgba(255,255,255,.4);font-weight:500;"> / <?php echo esc_html($max_cap); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$free && $booked_cnt > 0): ?>
                        <div style="width:1px;height:36px;background:rgba(255,255,255,.1);"></div>
                        <div>
                            <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">Total Revenue</div>
                            <div style="font-size:20px;font-weight:700;color:#34d399;">
                                <?php echo esc_html($cur_raw . number_format($price * $booked_cnt, 0)); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button class="wbi-hero-edit-badge" onclick="(function(){var d=document.getElementById('wbi-edit-drawer'),o=document.getElementById('wbi-edit-overlay');if(d&&o){d.classList.add('open');o.classList.add('open');document.body.style.overflow='hidden';}var t=document.querySelectorAll('.wbi-edit-tab');t.forEach(function(x){x.classList.toggle('active',x.dataset.tab==='details');});var p=document.getElementById('wbi-pane-details');if(p){document.querySelectorAll('.wbi-edit-pane').forEach(function(x){x.classList.remove('active');});p.classList.add('active');}})()">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Price &amp; Schedule
                    </button>
                </div>
                <?php elseif (!$is_past_webinar && !$is_current_user_expert): ?>
                <div class="wbi-cta-row">
                    <div class="wbi-price-wrap">
                        <span class="wbi-price-big <?php echo $free ? 'free' : ''; ?>">
                            <?php echo $free ? 'Free' : esc_html($cur_raw . number_format($price, 0)); ?>
                        </span>
                        <?php if (!$free): ?><span class="wbi-price-note">per person</span><?php endif; ?>
                    </div>
                 

                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                        <?php if ($is_reg): ?>
                            <button class="wbi-btn wbi-btn-registered">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Registered
                            </button>
                        <?php elseif ($is_pend): ?>
                            <a href="<?php echo esc_url(add_query_arg('event-id', $event_id, home_url('/paid-webinar-payment'))); ?>" class="wbi-btn wbi-btn-paynow">

                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <rect x="1" y="4" width="22" height="16" rx="2"/>
                                    <line x1="1" y1="10" x2="23" y2="10"/>
                                </svg>
                                Complete Payment
                            </a>
                        <?php elseif ($is_full): ?>
                            <button class="wbi-btn wbi-btn-full">Fully Booked</button>
                        <?php else: ?>
                            <button class="wbi-btn wbi-btn-book"
                                    id="wbi-book-btn"
                                    data-event-id="<?php echo esc_attr($event_id); ?>"
                                    data-free="<?php echo $free ? '1' : '0'; ?>">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                                <?php echo $free ? 'Register Now' : 'Book Now'; ?>
                            </button>
                            <div class="wbi-book-msg" id="wbi-book-msg"></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; // elseif !is_past_webinar && !is_current_user_expert ?>

                <?php
                $desc = trim($event['description'] ?? '');
                if ($desc === '' && $amelia_event) $desc = trim($amelia_event['description'] ?? '');
                if ($desc !== ''):
                ?>
                <div class="wbi-desc-section">
                    <div class="wbi-sec-label">About this Webinar</div>
                    <div class="wbi-desc-text"><?php echo nl2br(esc_html(wp_strip_all_tags($desc))); ?></div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- RIGHT: EXPERT + ZOOM -->
        <div class="wbi-right">

            <?php if ($expert_meta): ?>
            <div class="wbi-card wbi-expert-card wbi-fi wbi-fi-2">
                <div class="wbi-expert-head">
                    <div class="wbi-expert-head-label">Speaker</div>
                    <div class="wbi-expert-head-name"><?php echo esc_html($expert_meta['name']); ?></div>
                    <?php if ($expert_meta['is_verified']): ?>
                    <div class="wbi-expert-verified">
                        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        Verified
                    </div>
                    <?php endif; ?>
                    <div class="wbi-expert-av-wrap">
                        <img class="wbi-expert-avatar"
                             src="<?php echo esc_url($expert_meta['avatar_url']); ?>"
                             alt="<?php echo esc_attr($expert_meta['name']); ?>">
                    </div>
                </div>
                <div class="wbi-expert-body">
                    <?php if (!empty($expert_meta['specs_arr'])): ?>
                    <div class="wbi-expert-specs">
                        <?php foreach (array_slice($expert_meta['specs_arr'], 0, 5) as $s): ?>
                            <span class="wbi-expert-spec"><?php echo esc_html($s); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($expert_meta['about'])): ?>
                    <p class="wbi-expert-about"><?php echo esc_html(wp_trim_words($expert_meta['about'], 45, '...')); ?></p>
                    <?php endif; ?>
                    <?php if (!$is_viewing_own_event): ?>
                    <a href="<?php echo esc_url($expert_meta['profile_url']); ?>" class="wbi-profile-link">
                        View Full Profile
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $zoom_url = $amelia_event['periods'][0]['zoomMeeting']['joinUrl'] ?? '';
            if ($is_reg && !$is_past_webinar && $zoom_url):
            ?>
            <div class="wbi-card wbi-zoom-card wbi-fi wbi-fi-3">
                <div class="wbi-sec-label" style="margin-bottom:12px;">Join Online</div>
                <a href="<?php echo esc_url($zoom_url); ?>" target="_blank" rel="noopener" class="wbi-zoom-btn">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.89L15 14"/>
                        <rect x="2" y="6" width="13" height="12" rx="2"/>
                    </svg>
                    Join Zoom Meeting
                    <span class="wbi-zoom-live">Live</span>
                </a>
            </div>
            <?php elseif ($is_reg && $is_past_webinar): ?>
            <div class="wbi-card wbi-zoom-card wbi-fi wbi-fi-3">
                <div class="wbi-past-notice">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    This webinar has ended. Check the materials below for recordings or notes.
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>

    <?php
    $all_materials = wbi_get_event_materials($event_id);
    $webinar_ts    = strtotime($event['periodStart']);
    $pre_mats = []; $post_mats = [];
    foreach ($all_materials as $mat) {
        $upload_ts = strtotime($mat['uploaded_at']);
        if ($upload_ts < $webinar_ts) $pre_mats[] = $mat;
        else $post_mats[] = $mat;
    }

    $wbi_ext_icons = [
        'pdf'  => ['#fef2f2','#ef4444'], 'ppt'  => ['#fff7ed','#ea580c'],
        'pptx' => ['#fff7ed','#ea580c'], 'doc'  => ['#eff6ff','#3b82f6'],
        'docx' => ['#eff6ff','#3b82f6'], 'mp4'  => ['#f5f3ff','#7c3aed'],
        'mov'  => ['#f5f3ff','#7c3aed'], 'jpg'  => ['#f0fdf4','#16a34a'],
        'jpeg' => ['#f0fdf4','#16a34a'], 'png'  => ['#f0fdf4','#16a34a'],
        'xlsx' => ['#f0fdf4','#16a34a'], 'csv'  => ['#ecfeff','#0891b2'],
    ];
    $mat_type_bgs = [
        'Presentation Slides' => '#eef2ff', 'Handouts / PDF Notes' => '#fef2f2',
        'Assignment / Homework' => '#fffbeb', 'Reference Books / Articles' => '#ecfdf5',
        'Session Recordings' => '#f5f3ff', 'Practice Sheets' => '#ecfeff',
        'Exam Prep Materials' => '#fef2f2',
    ];
    ?>
    <div class="wbi-materials-panel wbi-fi wbi-fi-3">
        <div class="wbi-card">
            <div class="wbi-materials-header">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3>Course Materials</h3>
                <?php if ($can_edit): ?>
                <button class="wbi-btn" id="wbi-open-edit-mat"
                    style="padding:8px 16px;font-size:13px;background:linear-gradient(135deg,var(--acc),var(--acc-d));color:#fff;box-shadow:0 2px 8px rgba(37,99,235,.25);"
                    onclick="(function(){
                        var d=document.getElementById('wbi-edit-drawer'),o=document.getElementById('wbi-edit-overlay');
                        if(d&&o){
                            d.classList.add('open');o.classList.add('open');document.body.style.overflow='hidden';
                            document.querySelectorAll('.wbi-edit-tab').forEach(function(t){t.classList.toggle('active',t.dataset.tab==='materials');});
                            document.querySelectorAll('.wbi-edit-pane').forEach(function(p){p.classList.remove('active');});
                            var mp=document.getElementById('wbi-pane-materials');if(mp)mp.classList.add('active');
                        }
                    })()">
                                      + Add Material
                </button>
                <?php endif; ?>
            </div>

            <?php foreach ([['Pre-Webinar Materials', $pre_mats, 'circle-clock'], ['Post-Webinar Materials', $post_mats, 'check-circle']] as [$section_title, $mats, $icon_type]): ?>
            <?php if (!empty($mats)): ?>
            <div class="wbi-materials-section" data-section="<?php echo esc_attr(strtolower(str_replace(' ', '-', $section_title))); ?>">
                <div class="wbi-materials-section-header">
                    <?php if ($icon_type === 'circle-clock'): ?>
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <?php else: ?>
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <?php endif; ?>
                    <h4><?php echo esc_html($section_title); ?></h4>
                    <span class="wbi-materials-count"><?php echo count($mats); ?></span>
                </div>
                <div class="wbi-materials-list">
                    <?php foreach ($mats as $mat):
                        $ext = strtolower(pathinfo($mat['title'], PATHINFO_EXTENSION));
                        [$ic_bg, $ic_clr] = $wbi_ext_icons[$ext] ?? ['#eef2ff', '#4338ca'];
                        $type_bg = $mat_type_bgs[$mat['material_type']] ?? '#f1f5f9';
                        $date_fmt = wp_date('M j, Y', strtotime($mat['uploaded_at']));
                    ?>
                    <div class="wbi-material-item" data-mat-id="<?php echo esc_attr($mat['id']); ?>">
                        <div class="wbi-material-icon" style="background:<?php echo esc_attr($ic_bg); ?>;">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="<?php echo esc_attr($ic_clr); ?>" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div class="wbi-material-info">
                            <div class="wbi-material-title"><?php echo esc_html($mat['title']); ?></div>
                            <span class="wbi-material-type" style="background:<?php echo esc_attr($type_bg); ?>;">
                                <?php echo esc_html($mat['material_type']); ?>
                            </span>
                            <div class="wbi-material-meta" style="margin-top:4px;">Uploaded <?php echo esc_html($date_fmt); ?></div>
                        </div>
                        <a href="<?php echo esc_url($mat['file_url']); ?>" class="wbi-material-download" target="_blank" download title="Download">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                        </a>
                        <?php if ($can_edit): ?>
                        <button class="wbi-material-delete wbi-mat-del-btn"
                                data-mat-id="<?php echo esc_attr($mat['id']); ?>"
                                data-event-id="<?php echo esc_attr($event_id); ?>"
                                title="Delete material">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m5 0V4a1 1 0 011-1h2a1 1 0 011 1v2"/>
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($pre_mats) && empty($post_mats)): ?>
            <div class="wbi-materials-empty" id="wbi-mats-empty">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p>No materials available yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; // event found ?>

</div><!-- /page -->

<?php if ($can_edit): ?>
<!-- =====================================================
     EXPERT EDIT DRAWER
     ===================================================== -->
<div class="wbi-edit-overlay" id="wbi-edit-overlay"
     onclick="(function(){var d=document.getElementById('wbi-edit-drawer'),o=document.getElementById('wbi-edit-overlay');if(d&&o){d.classList.remove('open');o.classList.remove('open');document.body.style.overflow='';}})()"
></div>
<div class="wbi-edit-drawer" id="wbi-edit-drawer" role="dialog" aria-modal="true" aria-label="Edit Webinar">

    <div class="wbi-edit-drawer-head">
        <div class="wbi-edit-drawer-head-top">
            <div class="wbi-edit-drawer-title">Edit Webinar</div>
            <button class="wbi-edit-close" id="wbi-edit-close" aria-label="Close"
                    onclick="(function(){var d=document.getElementById('wbi-edit-drawer'),o=document.getElementById('wbi-edit-overlay');if(d&&o){d.classList.remove('open');o.classList.remove('open');document.body.style.overflow='';}})()"
            >
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="wbi-edit-drawer-sub"><?php echo esc_html($event['name']); ?></div>
    </div>

    <!-- Tabs -->
    <div class="wbi-edit-tabs">
        <div class="wbi-edit-tab active" data-tab="details">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Schedule &amp; Pricing
        </div>
        <div class="wbi-edit-tab" data-tab="materials">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Materials
        </div>
    </div>

    <div class="wbi-edit-body">

        <!-- -- TAB: Schedule & Pricing --------------------------- -->
        <div class="wbi-edit-pane active" id="wbi-pane-details">

            <div class="wbi-ef-info">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Changes are saved directly to the database. Attendees are not automatically notified.
            </div>

            <!-- Date & Time -->
            <div class="wbi-ef-group">
                <label class="wbi-ef-label">Start Date &amp; Time</label>
                <input type="datetime-local" class="wbi-ef-input" id="wbi-ef-start"
                       value="<?php echo esc_attr($input_start); ?>">
                <div class="wbi-ef-hint">Currently: <strong><?php echo esc_html(wp_date('D, d M Y · g:i A', $start_ts)); ?></strong> &nbsp;(<?php echo esc_html(wp_timezone_string()); ?>)</div>
            </div>

            <div class="wbi-ef-group">
                <label class="wbi-ef-label">End Date &amp; Time</label>
                <input type="datetime-local" class="wbi-ef-input" id="wbi-ef-end"
                       value="<?php echo esc_attr($input_end); ?>">
                <div class="wbi-ef-hint">Currently: <strong><?php echo esc_html(wp_date('D, d M Y · g:i A', $end_ts)); ?></strong></div>
            </div>

            <div class="wbi-ef-divider"></div>

            <!-- Capacity & Price -->
            <div class="wbi-ef-row">
                <div class="wbi-ef-group">
                    <label class="wbi-ef-label">Max Capacity</label>
                    <input type="number" class="wbi-ef-input" id="wbi-ef-cap"
                           value="<?php echo esc_attr($max_cap); ?>" min="<?php echo esc_attr($booked_cnt); ?>" step="1">
                    <div class="wbi-ef-hint"><?php echo esc_html($booked_cnt); ?> already booked</div>
                </div>
                <div class="wbi-ef-group">
                    <label class="wbi-ef-label">Price (<?php echo esc_html($currency); ?>)</label>
                    <input type="number" class="wbi-ef-input" id="wbi-ef-price"
                           value="<?php echo esc_attr(number_format($price, 2, '.', '')); ?>" min="0" step="0.01">
                    <div class="wbi-ef-hint">Set 0 for a free webinar</div>
                </div>
            </div>

            <button class="wbi-ef-save" id="wbi-ef-save-details">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Save Changes
            </button>
            <div class="wbi-ef-status" id="wbi-ef-details-status"></div>
        </div>

        <!-- -- TAB: Materials ------------------------------------ -->
        <div class="wbi-edit-pane" id="wbi-pane-materials">

            <!-- Upload form -->
            <div class="wbi-ef-group">
                <label class="wbi-ef-label">Material Title</label>
                <input type="text" class="wbi-ef-input" id="wbi-ef-mat-title" placeholder="e.g. Week 1 Slides">
            </div>

            <div class="wbi-ef-group">
                <label class="wbi-ef-label">Material Type</label>
                <select class="wbi-ef-input" id="wbi-ef-mat-type">
                    <option>Presentation Slides</option>
                    <option>Handouts / PDF Notes</option>
                    <option>Assignment / Homework</option>
                    <option>Reference Books / Articles</option>
                    <option>Session Recordings</option>
                    <option>Practice Sheets</option>
                    <option>Exam Prep Materials</option>
                </select>
            </div>

            <div class="wbi-ef-group">
                <label class="wbi-ef-label">File</label>
                <div class="wbi-upload-zone" id="wbi-upload-zone">
                    <input type="file" id="wbi-ef-file" accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.mov,.jpg,.jpeg,.png,.xlsx,.csv,.zip">
                    <div class="wbi-upload-icon">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                    </div>
                    <div class="wbi-upload-zone-title">Drop a file here or click to browse</div>
                    <div class="wbi-upload-zone-sub">PDF, PPTX, DOCX, MP4, JPG, PNG, XLSX · Max 50MB</div>
                    <div class="wbi-upload-file-name" id="wbi-upload-file-name"></div>
                </div>
            </div>

            <button class="wbi-ef-save" id="wbi-ef-upload-btn">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Upload Material
            </button>
            <div class="wbi-ef-status" id="wbi-ef-mat-status"></div>

            <div class="wbi-ef-divider"></div>

            <!-- Existing materials list inside drawer -->
            <label class="wbi-ef-label">Existing Materials</label>
            <div class="wbi-edit-mat-list" id="wbi-edit-mat-list">
                <?php if (empty($all_materials)): ?>
                <div class="wbi-edit-mat-empty" id="wbi-edit-mat-empty">No materials yet.</div>
                <?php else: ?>
                    <?php foreach ($all_materials as $mat): ?>
                    <div class="wbi-edit-mat-item" data-mat-id="<?php echo esc_attr($mat['id']); ?>">
                        <div class="wbi-edit-mat-info">
                            <div class="wbi-edit-mat-title"><?php echo esc_html($mat['title']); ?></div>
                            <div class="wbi-edit-mat-type"><?php echo esc_html($mat['material_type']); ?></div>
                        </div>
                        <div class="wbi-edit-mat-del wbi-drawer-mat-del"
                             data-mat-id="<?php echo esc_attr($mat['id']); ?>"
                             data-event-id="<?php echo esc_attr($event_id); ?>"
                             title="Delete">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m5 0V4a1 1 0 011-1h2a1 1 0 011 1v2"/>
                            </svg>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div><!-- /pane-materials -->

    </div><!-- /edit-body -->
</div><!-- /edit-drawer -->
<?php endif; // can_edit ?>

<script>
(function(){

    /* -----------------------------------------
       Helpers defined FIRST — no ordering issues
    ----------------------------------------- */
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function spinnerHTML(label) {
        return '<span style="width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;animation:wbi-spin .7s linear infinite;display:inline-block;vertical-align:middle;"></span> ' + label;
    }

    function setStatus(el, type, msg) {
        if (!el) return;
        el.className  = 'wbi-ef-status ' + type;
        el.textContent = msg;
        if (type === 'ok') {
            setTimeout(function(){ el.className='wbi-ef-status'; el.textContent=''; }, 4000);
        }
    }

    function switchTab(name) {
        document.querySelectorAll('.wbi-edit-tab').forEach(function(t){
            t.classList.toggle('active', t.dataset.tab === name);
        });
        document.querySelectorAll('.wbi-edit-pane').forEach(function(p){
            p.classList.remove('active');
        });
        var pane = document.getElementById('wbi-pane-' + name);
        if (pane) pane.classList.add('active');
    }

    function openDrawer(tab) {
        var d = document.getElementById('wbi-edit-drawer');
        var o = document.getElementById('wbi-edit-overlay');
        if (!d || !o) { console.error('WBI: drawer elements not found'); return; }
        d.classList.add('open');
        o.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (tab) switchTab(tab);
    }

    function closeDrawer() {
        var d = document.getElementById('wbi-edit-drawer');
        var o = document.getElementById('wbi-edit-overlay');
        if (d) d.classList.remove('open');
        if (o) o.classList.remove('open');
        document.body.style.overflow = '';
    }

    /* -----------------------------------------
       Init — runs after DOM ready
    ----------------------------------------- */
    function wbiInit() {

        /* Book Now */
        var bookBtn = document.getElementById('wbi-book-btn');
        if (bookBtn) {
            bookBtn.addEventListener('click', function() {
                var eventId = bookBtn.dataset.eventId;
                var msg     = document.getElementById('wbi-book-msg');
                bookBtn.disabled  = true;
                bookBtn.innerHTML = spinnerHTML('Booking...');
                if (msg) { msg.className='wbi-book-msg'; msg.textContent=''; }

                fetch(<?php echo json_encode(admin_url('admin-ajax.php')); ?>, {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action:   'wbi_book_event',
                        event_id: eventId,
                        nonce:    <?php echo json_encode($book_nonce); ?>
                    })
                })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        if (msg) { msg.className='wbi-book-msg success'; msg.textContent=data.data.message+' Redirecting...'; }
                        setTimeout(function(){ window.location.href=data.data.redirect; }, 1200);
                    } else {
                        if (msg) { msg.className='wbi-book-msg error'; msg.textContent=data.data.message||'Booking failed.'; }
                        bookBtn.disabled  = false;
                        bookBtn.innerHTML = '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg> '+(bookBtn.dataset.free==='1'?'Register Now':'Book Now');
                    }
                })
                .catch(function() {
                    if (msg) { msg.className='wbi-book-msg error'; msg.textContent='Network error. Please try again.'; }
                    bookBtn.disabled  = false;
                    bookBtn.innerHTML = '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg> '+(bookBtn.dataset.free==='1'?'Register Now':'Book Now');
                });
            });
        }

        /* Drawer open/close via JS (inline onclick already handles it too) */
        var openBtn    = document.getElementById('wbi-open-edit');
        var openMatBtn = document.getElementById('wbi-open-edit-mat');
        var closeBtn   = document.getElementById('wbi-edit-close');
        var overlay    = document.getElementById('wbi-edit-overlay');

        if (openBtn)    openBtn.addEventListener('click',    function(){ openDrawer('details'); });
        if (openMatBtn) openMatBtn.addEventListener('click', function(){ openDrawer('materials'); });
        if (closeBtn)   closeBtn.addEventListener('click',   closeDrawer);
        if (overlay)    overlay.addEventListener('click',    closeDrawer);
        document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeDrawer(); });

        /* Tab clicks */
        document.querySelectorAll('.wbi-edit-tab').forEach(function(tab){
            tab.addEventListener('click', function(){ switchTab(tab.dataset.tab); });
        });

        /* File input label */
        var fileInput  = document.getElementById('wbi-ef-file');
        var fileLabel  = document.getElementById('wbi-upload-file-name');
        var uploadZone = document.getElementById('wbi-upload-zone');
        if (fileInput) {
            fileInput.addEventListener('change', function(){
                if (fileInput.files.length && fileLabel) {
                    fileLabel.textContent  = fileInput.files[0].name;
                    fileLabel.style.display = 'block';
                }
            });
        }
        if (uploadZone) {
            uploadZone.addEventListener('dragover',  function(e){ e.preventDefault(); uploadZone.classList.add('drag-over'); });
            uploadZone.addEventListener('dragleave', function(){  uploadZone.classList.remove('drag-over'); });
            uploadZone.addEventListener('drop',      function(){  uploadZone.classList.remove('drag-over'); });
        }

        /* -- Drawer AJAX (only wire if drawer exists in DOM) -- */
        var drawer = document.getElementById('wbi-edit-drawer');
        if (!drawer) return; /* not an expert / not their event */

        var AJAX_URL = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;
        var NONCE    = <?php echo json_encode($expert_edit_nonce); ?>;
        var EVENT_ID = <?php echo json_encode($event_id); ?>;
        var PERIOD_ID= <?php echo json_encode($period_id); ?>;
        var CUR_SYM  = '<?php echo esc_js($cur_raw_js); ?>';

        /* Save Schedule & Pricing */
        var saveDetailsBtn = document.getElementById('wbi-ef-save-details');
        var detailsStatus  = document.getElementById('wbi-ef-details-status');

        if (saveDetailsBtn) {
            saveDetailsBtn.addEventListener('click', function(){
                var startVal = document.getElementById('wbi-ef-start').value;
                var endVal   = document.getElementById('wbi-ef-end').value;
                var capVal   = document.getElementById('wbi-ef-cap').value;
                var priceVal = document.getElementById('wbi-ef-price').value;

                if (!startVal || !endVal) { setStatus(detailsStatus,'err','Please fill in both start and end date/time.'); return; }
                if (new Date(endVal) <= new Date(startVal)) { setStatus(detailsStatus,'err','End time must be after start time.'); return; }

                var toDbDate = function(v){ return v.replace('T',' ')+':00'; };
                saveDetailsBtn.disabled  = true;
                saveDetailsBtn.innerHTML = spinnerHTML('Saving...');

                var fd = new FormData();
                fd.append('action',       'wbi_expert_update_event');
                fd.append('nonce',        NONCE);
                fd.append('event_id',     EVENT_ID);
                fd.append('period_id',    PERIOD_ID);
                fd.append('period_start', toDbDate(startVal));
                fd.append('period_end',   toDbDate(endVal));
                fd.append('max_capacity', capVal);
                fd.append('price',        priceVal);

                fetch(AJAX_URL, {method:'POST',credentials:'same-origin',body:fd})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    saveDetailsBtn.disabled  = false;
                    saveDetailsBtn.innerHTML = '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Changes';
                    if (data.success) {
                        setStatus(detailsStatus,'ok','? '+data.data.message);
                        /* Update visible meta cards — parse "YYYY-MM-DDTHH:MM" correctly */
                        var pad = function(n){ return n < 10 ? '0'+n : ''+n; };
                        /* Parse without timezone conversion by splitting the string */
                        var parseLocal = function(v) {
                            var parts = v.split('T');
                            var d = parts[0].split('-'), t = (parts[1]||'00:00').split(':');
                            return { Y:+d[0], M:+d[1]-1, D:+d[2], h:+t[0], m:+t[1] };
                        };
                        var fmtTime = function(p) {
                            var a = p.h >= 12 ? 'PM' : 'AM';
                            var h = p.h % 12 || 12;
                            return h + ':' + pad(p.m) + ' ' + a;
                        };
                        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                        var sP = parseLocal(startVal), eP = parseLocal(endVal);var sDate = new Date(sP.Y, sP.M, sP.D);                        var dateEl = document.getElementById('wbi-meta-date');
                        var timeEl = document.getElementById('wbi-meta-time');
                        var capEl  = document.getElementById('wbi-meta-cap');
                        if (dateEl) dateEl.textContent = days[sDate.getDay()] + ', ' + months[sP.M] + ' ' + sP.D + ', ' + sP.Y;
                        if (timeEl) timeEl.innerHTML   = fmtTime(sP) + ' &ndash; ' + fmtTime(eP);
                        if (capEl)  capEl.textContent  = capEl.textContent.replace(/\/\s*\d+\s*booked/, '/ ' + capVal + ' booked');
                        /* Update expert price display */
                        var priceDisplay = document.getElementById('wbi-expert-price-display');
                        if (priceDisplay && priceVal !== undefined) {
                            var pNum = parseFloat(priceVal);
                            priceDisplay.textContent = (pNum <= 0) ? 'Free' : (CUR_SYM + Math.round(pNum).toLocaleString());
                            priceDisplay.style.color = (pNum <= 0) ? '#34d399' : '#60a5fa';
                        }
                        /* Update panel hints */
                        var hintS = document.querySelector('#wbi-ef-start + .wbi-ef-hint');
                        var hintE = document.querySelector('#wbi-ef-end + .wbi-ef-hint');
                        if (hintS) hintS.innerHTML = 'Currently: <strong>' + days[sDate.getDay()].slice(0,3) + ', ' + pad(sP.D) + ' ' + months[sP.M].slice(0,3) + ' ' + sP.Y + ' · ' + fmtTime(sP) + '</strong>';
                        if (hintE) hintE.innerHTML = 'Currently: <strong>' + days[new Date(eP.Y,eP.M,eP.D).getDay()].slice(0,3) + ', ' + pad(eP.D) + ' ' + months[eP.M].slice(0,3) + ' ' + eP.Y + ' · ' + fmtTime(eP) + '</strong>';
                    } else {
                        setStatus(detailsStatus,'err',data.data&&data.data.message?data.data.message:'Save failed.');
                    }
                })
                .catch(function(){ saveDetailsBtn.disabled=false; saveDetailsBtn.innerHTML='Save Changes'; setStatus(detailsStatus,'err','Network error.'); });
            });
        }

        /* Upload Material */
        var uploadBtn = document.getElementById('wbi-ef-upload-btn');
        var matStatus = document.getElementById('wbi-ef-mat-status');

        if (uploadBtn) {
            uploadBtn.addEventListener('click', function(){
                var titleVal = (document.getElementById('wbi-ef-mat-title')||{}).value||'';
                var typeVal  = (document.getElementById('wbi-ef-mat-type')||{}).value||'';
                var file     = fileInput && fileInput.files[0];

                titleVal = titleVal.trim();
                if (!titleVal) { setStatus(matStatus,'err','Please enter a material title.'); return; }
                if (!file)     { setStatus(matStatus,'err','Please select a file to upload.'); return; }

                uploadBtn.disabled  = true;
                uploadBtn.innerHTML = spinnerHTML('Uploading...');

                var fd = new FormData();
                fd.append('action',         'wbi_expert_upload_material');
                fd.append('nonce',          NONCE);
                fd.append('event_id',       EVENT_ID);
                fd.append('material_title', titleVal);
                fd.append('material_type',  typeVal);
                fd.append('material_file',  file);

                fetch(AJAX_URL, {method:'POST',credentials:'same-origin',body:fd})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    uploadBtn.disabled  = false;
                    uploadBtn.innerHTML = '<svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Upload Material';
                    if (data.success) {
                        setStatus(matStatus,'ok','? '+data.data.message);
                        /* Reset form */
                        var titleEl=document.getElementById('wbi-ef-mat-title'); if(titleEl) titleEl.value='';
                        if(fileInput) fileInput.value='';
                        if(fileLabel){ fileLabel.style.display='none'; fileLabel.textContent=''; }
                        /* Add to drawer list */
                        var mat=data.data;
                        var emptyEl=document.getElementById('wbi-edit-mat-empty'); if(emptyEl) emptyEl.remove();
                        var list=document.getElementById('wbi-edit-mat-list');
                        if(list){
                            var item=document.createElement('div');
                            item.className='wbi-edit-mat-item'; item.dataset.matId=mat.id;
                            item.innerHTML='<div class="wbi-edit-mat-info"><div class="wbi-edit-mat-title">'+escHtml(mat.title)+'</div><div class="wbi-edit-mat-type">'+escHtml(mat.material_type)+'</div></div>'
                                +'<div class="wbi-edit-mat-del wbi-drawer-mat-del" data-mat-id="'+mat.id+'" data-event-id="'+EVENT_ID+'" title="Delete" style="cursor:pointer;">'
                                +'<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;color:#dc2626;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m5 0V4a1 1 0 011-1h2a1 1 0 011 1v2"/></svg>'
                                +'</div>';
                            list.appendChild(item);
                            bindDelBtn(item.querySelector('.wbi-drawer-mat-del'));
                        }
                        /* Hide page-level empty state */
                        var pageEmpty=document.getElementById('wbi-mats-empty'); if(pageEmpty) pageEmpty.style.display='none';
                    } else {
                        setStatus(matStatus,'err',data.data&&data.data.message?data.data.message:'Upload failed.');
                    }
                })
                .catch(function(){ uploadBtn.disabled=false; uploadBtn.innerHTML='Upload Material'; setStatus(matStatus,'err','Network error.'); });
            });
        }

        /* Delete material */
        function deleteMaterial(matId, eventId, onSuccess) {
            if (!confirm('Delete this material? This cannot be undone.')) return;
            var fd=new FormData();
            fd.append('action','wbi_expert_delete_material');
            fd.append('nonce',NONCE);
            fd.append('event_id',eventId);
            fd.append('material_id',matId);
            fetch(AJAX_URL,{method:'POST',credentials:'same-origin',body:fd})
            .then(function(r){ return r.json(); })
            .then(function(data){ if(data.success){ onSuccess(); } else { alert(data.data&&data.data.message?data.data.message:'Delete failed.'); } })
            .catch(function(){ alert('Network error.'); });
        }

        function bindDelBtn(btn) {
            if (!btn) return;
            btn.addEventListener('click', function(){
                var matId=btn.dataset.matId, eventId=btn.dataset.eventId;
                deleteMaterial(matId, eventId, function(){
                    var dItem=document.querySelector('#wbi-edit-mat-list .wbi-edit-mat-item[data-mat-id="'+matId+'"]'); if(dItem) dItem.remove();
                    var pItem=document.querySelector('.wbi-material-item[data-mat-id="'+matId+'"]');                   if(pItem) pItem.remove();
                });
            });
        }

        document.querySelectorAll('.wbi-mat-del-btn, .wbi-drawer-mat-del').forEach(bindDelBtn);

    } /* end wbiInit */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wbiInit);
    } else {
        wbiInit();
    }

})();
</script>
<?php
    return ob_get_clean();
}