<?php
/**
 * Webinar Booking Auto-Cancellation
 * Cancels unpaid webinar bookings created more than 1 hour ago.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------
   CRON REGISTRATION
--------------------------------------------------------------- */
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['wbex_every_15min'] = [
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => 'Every 15 Minutes',
    ];
    return $schedules;
} );

add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'wbex_cancel_expired_bookings' ) ) {
        wp_schedule_event( time(), 'wbex_every_15min', 'wbex_cancel_expired_bookings' );
    }
} );

register_deactivation_hook( WP_PLUGIN_DIR . '/platform-core/platform-core.php', function() {
    $ts = wp_next_scheduled( 'wbex_cancel_expired_bookings' );
    if ( $ts ) wp_unschedule_event( $ts, 'wbex_cancel_expired_bookings' );
} );

/* ---------------------------------------------------------------
   MAIN CANCELLATION LOGIC
--------------------------------------------------------------- */
add_action( 'wbex_cancel_expired_bookings', 'wbex_run_cancellation' );

function wbex_run_cancellation() {
    global $wpdb;

    // Use p.created (actual booking creation time), NOT p.dateTime (which is the event start time)
    $expiry_threshold = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

    $expired_bookings = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT
            cb.id           AS booking_id,
            ep.eventId      AS event_id,
            p.id            AS payment_id,
            p.created       AS booked_at,
            au.email        AS customer_email,
            e.name          AS event_name
         FROM {$wpdb->prefix}amelia_customer_bookings cb
         INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x
                 ON cb.id = x.customerBookingId
         INNER JOIN {$wpdb->prefix}amelia_events_periods ep
                 ON x.eventPeriodId = ep.id
         INNER JOIN {$wpdb->prefix}amelia_events e
                 ON ep.eventId = e.id
         INNER JOIN {$wpdb->prefix}amelia_payments p
                 ON p.customerBookingId = cb.id
         INNER JOIN {$wpdb->prefix}amelia_users au
                 ON cb.customerId = au.id
         WHERE cb.status   = 'approved'
           AND p.status    IN ('pending','unpaid','waiting')
           AND p.created   <= %s
           AND e.status    IN ('approved','publish')",
        $expiry_threshold
    ) );

    if ( empty( $expired_bookings ) ) {
        error_log( 'wbex: No expired bookings found.' );
        return;
    }

    $cancelled_count = 0;

    foreach ( $expired_bookings as $booking ) {
        $booking_id = (int) $booking->booking_id;
        $payment_id = (int) $booking->payment_id;

        $updated = $wpdb->update(
            $wpdb->prefix . 'amelia_customer_bookings',
            [ 'status' => 'canceled' ],
            [ 'id'     => $booking_id ],
            [ '%s' ], [ '%d' ]
        );

        if ( $updated === false ) {
            error_log( "wbex: Failed to cancel booking ID {$booking_id}" );
            continue;
        }

        $wpdb->update(
            $wpdb->prefix . 'amelia_payments',
            [ 'status' => 'rejected' ],
            [ 'id'     => $payment_id ],
            [ '%s' ], [ '%d' ]
        );

        error_log( "wbex: Cancelled booking #{$booking_id} (event \"{$booking->event_name}\", {$booking->customer_email}) — created {$booking->booked_at}, threshold {$expiry_threshold}" );

        wbex_send_cancellation_email( $booking );
        $cancelled_count++;
    }

    error_log( "wbex: Done. Cancelled {$cancelled_count} booking(s)." );
}

/* ---------------------------------------------------------------
   CANCELLATION EMAIL
--------------------------------------------------------------- */
function wbex_send_cancellation_email( $booking ) {
    $to      = sanitize_email( $booking->customer_email );
    $subject = 'Your webinar booking has been cancelled — ' . wp_strip_all_tags( $booking->event_name );
    $body    = "Hi,\n\nYour booking for \"" . wp_strip_all_tags( $booking->event_name ) . "\" was automatically cancelled because payment was not completed within 1 hour.\n\nYou can rebook here:\n" . home_url( '/webinar-library' ) . "\n\nRegards,\n" . get_bloginfo( 'name' );
    wp_mail( $to, $subject, $body, [ 'Content-Type: text/plain; charset=UTF-8' ] );
}

/* ---------------------------------------------------------------
   ADMIN: MANUAL TRIGGER PAGE (Tools menu)
--------------------------------------------------------------- */
add_action( 'admin_menu', function() {
    add_management_page( 'Webinar Booking Expiry', 'Webinar Expiry', 'manage_options', 'wbex-manual-run', 'wbex_admin_manual_page' );
} );

function wbex_admin_manual_page() {
    $ran = false;
    if ( isset( $_POST['wbex_run'] ) && check_admin_referer( 'wbex_manual_run' ) ) {
        wbex_run_cancellation();
        $ran = true;
    }
    ?>
    <div class="wrap">
        <h1>Webinar Booking Auto-Cancellation</h1>
        <?php if ( $ran ): ?>
            <div class="notice notice-success"><p>Job ran. Check <code>wp-content/debug.log</code> for <code>wbex:</code> lines.</p></div>
        <?php endif; ?>
        <p>Unpaid bookings older than <strong>1 hour</strong> (based on <code>amelia_payments.created</code>) are cancelled every 15 minutes.</p>
        <p>Next scheduled run: <strong><?php
            $next = wp_next_scheduled( 'wbex_cancel_expired_bookings' );
            echo $next ? esc_html( gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' ) : 'Not scheduled';
        ?></strong></p>
        <form method="post">
            <?php wp_nonce_field( 'wbex_manual_run' ); ?>
            <input type="hidden" name="wbex_run" value="1">
            <?php submit_button( 'Run Now (Manual)', 'secondary' ); ?>
        </form>
    </div>
    <?php
}