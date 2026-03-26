<?php
/**
 * Shortcode: [student_webinar_dashboard]
 * Place [student_webinar_dashboard] on any page.
 *
 * Changes:
 *  - All "Book Now" buttons and card links navigate to /webinar-info/?event-id=X (no AJAX booking).
 *  - "Pay Now" (pending) buttons navigate to the correct payment page
 *    (free-webinar-payment or paid-webinar-payment) based on event price.
 *  - Consistent navbar with links to Dashboard, Webinar Library, My Events.
 *  - Calendar chips navigate to webinar-info (unchanged).
 *  - wbd_vars now injected via wp_localize_script (fixes certificate button not firing).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------
   DATA FUNCTIONS  (direct DB — correct Amelia schema)
--------------------------------------------------------------- */

/** All approved upcoming events with live spot counts. */
if ( ! function_exists( 'wbd_upcoming_events' ) ) {
    function wbd_upcoming_events() {
        global $wpdb;
        $now = current_time( 'mysql', true );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.name, ep.periodStart, ep.periodEnd,
                    COALESCE(e.maxCapacity,0) AS maxCap,
                    COALESCE(e.price,0)       AS price,
                    (SELECT COUNT(*)
                     FROM {$wpdb->prefix}amelia_customer_bookings cb2
                     INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x2
                             ON cb2.id = x2.customerBookingId
                     WHERE x2.eventPeriodId = ep.id AND cb2.status = 'approved') AS booked
             FROM {$wpdb->prefix}amelia_events e
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId = e.id
             WHERE e.status IN ('approved','publish') AND ep.periodStart > %s
             ORDER BY ep.periodStart ASC",
            $now
        ), ARRAY_A ) ?: [];
    }
}

/** Events within a given month — for the calendar grid. */
if ( ! function_exists( 'wbd_calendar_events' ) ) {
    function wbd_calendar_events( $year, $month ) {
        global $wpdb;
        $tz   = wp_timezone();
        $from = ( new DateTime( sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz ) )
                    ->setTimezone( new DateTimeZone('UTC') )->format('Y-m-d H:i:s');
        $to   = ( new DateTime( sprintf('%04d-%02d-%02d 23:59:59', $year, $month,
                         cal_days_in_month( CAL_GREGORIAN, $month, $year )), $tz ) )
                    ->setTimezone( new DateTimeZone('UTC') )->format('Y-m-d H:i:s');
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.name, ep.id AS periodId, ep.periodStart, ep.periodEnd,
                    COALESCE(e.price,0) AS price,
                    COALESCE(e.maxCapacity,0) AS maxCap,
                    (SELECT COUNT(*)
                     FROM {$wpdb->prefix}amelia_customer_bookings cb2
                     INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x2
                             ON cb2.id = x2.customerBookingId
                     WHERE x2.eventPeriodId = ep.id AND cb2.status = 'approved') AS booked
             FROM {$wpdb->prefix}amelia_events e
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId = e.id
             WHERE e.status IN ('approved','publish')
               AND ep.periodStart BETWEEN %s AND %s
             ORDER BY ep.periodStart ASC",
            $from, $to
        ), ARRAY_A ) ?: [];
    }
}

/** Event IDs where the user has a booking but payment is still pending. */
if ( ! function_exists( 'wbd_pending_ids' ) ) {
    function wbd_pending_ids( $uid ) {
        if ( ! $uid ) return [];
        global $wpdb;
        $u = get_userdata( $uid );
        if ( ! $u ) return [];
        $cid = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_users
             WHERE email = %s AND type = 'customer' LIMIT 1",
            $u->user_email
        ) );
        if ( ! $cid ) return [];
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT ep.eventId
             FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x
                     ON cb.id = x.customerBookingId
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep
                     ON x.eventPeriodId = ep.id
             INNER JOIN {$wpdb->prefix}amelia_payments p
                     ON p.customerBookingId = cb.id
             WHERE cb.customerId = %d
               AND cb.status = 'approved'
               AND p.status IN ('pending','unpaid','waiting')",
            (int) $cid
        ) ) ?: [] );
    }
}

/** Set of event IDs the user has a fully-paid/approved booking for. */
if ( ! function_exists( 'wbd_registered_ids' ) ) {
    function wbd_registered_ids( $uid ) {
        if ( ! $uid ) return [];
        global $wpdb;
        $u = get_userdata( $uid );
        if ( ! $u ) return [];
        $cid = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_users
             WHERE email = %s AND type = 'customer' LIMIT 1",
            $u->user_email
        ) );
        if ( ! $cid ) return [];
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT ep.eventId
             FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x
                     ON cb.id = x.customerBookingId
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep
                     ON x.eventPeriodId = ep.id
             INNER JOIN {$wpdb->prefix}amelia_payments p
                     ON p.customerBookingId = cb.id
             WHERE cb.customerId = %d
               AND cb.status    = 'approved'
               AND p.status     = 'paid'",
            (int) $cid
        ) ) ?: [] );
    }
}

/**
 * Past webinars the user attended, with amount paid AND certificate info.
 */
if ( ! function_exists( 'wbd_past_bookings' ) ) {
    function wbd_past_bookings( $uid ) {
        if ( ! $uid ) return [];
        global $wpdb;
        $u = get_userdata( $uid );
        if ( ! $u ) return [];
        $now = current_time( 'mysql', true );
        $cid = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_users
             WHERE email = %s AND type = 'customer' LIMIT 1",
            $u->user_email
        ) );
        if ( ! $cid ) return [];
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT
                    e.id        AS event_id,
                    ep.id       AS period_id,
                    e.name,
                    ep.periodStart,
                    ep.periodEnd,
                    COALESCE(e.price,0) AS price,
                    COALESCE(
                        (SELECT p2.amount FROM {$wpdb->prefix}amelia_payments p2
                         WHERE p2.customerBookingId = cb.id
                           AND p2.status = 'paid'
                         ORDER BY p2.id DESC LIMIT 1),
                        e.price,
                        0
                    ) AS amount_paid,
                    (SELECT wc.certificate_id
                     FROM {$wpdb->prefix}webinar_certificates wc
                     WHERE wc.student_user_id = %d
                       AND wc.event_id = e.id
                       AND wc.period_id = ep.id
                       AND wc.status = 'active'
                     LIMIT 1) AS certificate_id
             FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x
                     ON cb.id = x.customerBookingId
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep
                     ON x.eventPeriodId = ep.id
             INNER JOIN {$wpdb->prefix}amelia_events e
                     ON ep.eventId = e.id
             INNER JOIN {$wpdb->prefix}amelia_payments p
                     ON p.customerBookingId = cb.id
             WHERE cb.customerId = %d
               AND cb.status  = 'approved'
               AND p.status   = 'paid'
               AND ep.periodStart < %s
             ORDER BY ep.periodStart DESC",
            (int) $uid,
            (int) $cid,
            $now
        ), ARRAY_A ) ?: [];
    }
}

/* ---------------------------------------------------------------
   STYLES + SCRIPTS ENQUEUE
   KEY FIX: wbd_vars is now registered via wp_localize_script so it
   lands in the <head> before ANY inline JS runs. The old inline
   <script>var wbd_vars = {...}</script> inside ob_start() was racing
   against the click-handler script and sometimes losing.
--------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', function () {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'student_webinar_dashboard' ) ) return;

    wp_enqueue_style( 'wbd-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap',
        [], null );
    wp_add_inline_style( 'wbd-fonts', wbd_css() );

    // Dummy script handle — no src, just a carrier for wp_localize_script.
    // in_footer = false ensures wbd_vars is in <head>, available globally
    // before the shortcode's inline <script> at the bottom of the page runs.
    wp_register_script( 'wbd-main', false, [], null, false );
    wp_enqueue_script( 'wbd-main' );
    wp_localize_script( 'wbd-main', 'wbd_vars', [
        'ajax_url'       => admin_url( 'admin-ajax.php' ),
        'nonce_generate' => wp_create_nonce( 'generate_certificate' ),
    ] );
} );

function wbd_css() { return '
/* -- Reset -------------------------------------------------- */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
#wpadminbar{display:none!important;}
html{margin-top:0!important;}
header,#masthead,.site-header,.main-header,#header,.elementor-location-header,
.ast-main-header-wrap,#site-header,.fusion-header-wrapper,.header-wrap,
.nav-primary,.navbar,div[data-elementor-type="header"]{display:none!important;}
.site-content,.site-main,#content,#page{margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;}

/* -- Navbar -------------------------------------------------- */
.wbd-nav{
    background:rgba(255,255,255,.97);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
    border-bottom:1px solid #e4e7f0;position:sticky;top:0;z-index:200;
    box-shadow:0 1px 0 #e4e7f0,0 3px 14px rgba(13,16,37,.05);
}
.wbd-nav-inner{
    max-width:1240px;margin:auto;padding:0 32px;height:60px;
    display:flex;align-items:center;gap:16px;
}
.wbd-nav-logo{
    display:flex;align-items:center;gap:8px;font-weight:800;font-size:15px;
    color:#6c47ff;text-decoration:none;flex-shrink:0;letter-spacing:-.3px;
}
.wbd-nav-logo-box{
    width:32px;height:32px;background:#6c47ff;border-radius:9px;
    display:flex;align-items:center;justify-content:center;
}
.wbd-nav-logo-box svg{color:#fff;}
.wbd-nav-links{display:flex;gap:2px;margin-left:8px;}
.wbd-nav-links a{
    padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;
    color:#5a6180;text-decoration:none;transition:background .16s,color .16s;
}
.wbd-nav-links a:hover,.wbd-nav-links a.active{background:#edeaff;color:#6c47ff;}
.wbd-nav-r{margin-left:auto;display:flex;align-items:center;gap:12px;}
.wbd-nav-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #edeaff;}
.wbd-nav-uname{font-size:13px;font-weight:700;color:#0f1120;}
.wbd-nav-logout{
    padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:700;
    background:#0f1120;color:#fff;text-decoration:none;transition:opacity .15s;
}
.wbd-nav-logout:hover{opacity:.82;color:#fff;}
.wbd-nav-bell{
    position:relative;width:36px;height:36px;border-radius:50%;
    border:1.5px solid #e4e7f0;background:#f5f6fb;
    display:flex;align-items:center;justify-content:center;transition:all .18s;cursor:pointer;
}
.wbd-nav-bell:hover{background:#edeaff;border-color:#6c47ff;}
.wbd-nav-bell svg{color:#5a6180;}
.wbd-nav-bdot{position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#ef4444;border:2px solid #fff;}
@media(max-width:768px){.wbd-nav-links{display:none;}.wbd-nav-inner{padding:0 16px;}}

/* -- Base --------------------------------------------------- */
.wbd-wrap{
    --bg:#f4f5fb;--surf:#ffffff;--bdr:#e8eaf2;--txt:#0f1120;--muted:#8a90aa;
    --acc:#6c47ff;--acc-h:#5535e0;--alt:#edeaff;--acc2:#1e2235;
    --grn:#16a34a;--glt:#dcfce7;--grn-b:#22c55e;
    --red:#ef4444;
    --r:16px;--r-sm:10px;
    --sh:0 1px 3px rgba(15,17,32,.06),0 4px 20px rgba(15,17,32,.06);
    --sh-hover:0 4px 8px rgba(15,17,32,.08),0 12px 32px rgba(108,71,255,.12);
    font-family:"DM Sans",sans-serif;color:var(--txt);font-size:14px;
    background:var(--bg);border-radius:0;padding:24px 20px 56px;
    min-height:calc(100vh - 60px);
}
.wbd-wrap *{box-sizing:border-box;margin:0;padding:0;}

/* -- Certificate buttons ------------------------------------ */
.wbd-cert-btn{
    display:inline-flex;align-items:center;gap:5px;padding:5px 14px;
    background:#16a34a;color:#fff;border:none;border-radius:20px;
    font-size:11.5px;font-weight:700;text-decoration:none;cursor:pointer;
    transition:background .18s,transform .15s;font-family:inherit;
    box-shadow:0 2px 6px rgba(22,163,74,.25);
}
.wbd-cert-btn:hover{background:#15803d;color:#fff;transform:translateY(-1px);box-shadow:0 3px 10px rgba(22,163,74,.35);}
.wbd-cert-btn svg{width:13px;height:13px;}
.wbd-cert-gen{background:#6c47ff;box-shadow:0 2px 6px rgba(108,71,255,.25);}
.wbd-cert-gen:hover{background:#5535e0;box-shadow:0 3px 10px rgba(108,71,255,.35);}
.wbd-cert-gen.loading{opacity:0.7;cursor:wait;}
.wbd-hactions{display:flex;align-items:center;gap:8px;}

/* -- Layout --------------------------------------------------- */
.wbd-layout{display:flex;justify-content:center;gap:20px;align-items:start;max-width:1280px;margin:0 auto;}
.wbd-main{flex:1;min-width:0;}
.wbd-side{width:310px;flex-shrink:0;}
@media(max-width:1000px){
    .wbd-layout{flex-direction:column;align-items:stretch;}
    .wbd-main{width:100%;}
    .wbd-side{width:100%;order:-1;}
}

/* -- Card --------------------------------------------------- */
.wbd-card{background:var(--surf);border-radius:var(--r);border:1px solid var(--bdr);box-shadow:var(--sh);overflow:hidden;margin-bottom:20px;}
.wbd-card:last-child{margin-bottom:0;}
.wbd-ch{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px 14px;border-bottom:1px solid var(--bdr);
    background:linear-gradient(135deg,#fafbff 0%,#fff 100%);
}
.wbd-ct{font-size:15px;font-weight:800;letter-spacing:-.3px;color:var(--txt);}

/* -- Calendar nav ------------------------------------------- */
.wbd-cnav{display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--bdr);background:#fafbff;}
.wbd-cnav h3{font-size:14px;font-weight:800;flex:1;letter-spacing:-.2px;}
.wbd-carr{
    display:flex;align-items:center;justify-content:center;
    width:26px;height:26px;border-radius:50%;
    border:1.5px solid var(--bdr);color:var(--muted);
    text-decoration:none;font-size:14px;line-height:1;transition:all .18s;
}
.wbd-carr:hover{background:var(--acc);color:#fff;border-color:var(--acc);}
.wbd-ctod{
    font-size:11.5px;font-weight:700;padding:4px 12px;
    border-radius:20px;background:var(--alt);color:var(--acc);
    text-decoration:none;white-space:nowrap;transition:background .18s,color .18s;
}
.wbd-ctod:hover{background:var(--acc);color:#fff;}

/* -- Calendar grid ------------------------------------------ */
.wbd-cgrid{display:grid;grid-template-columns:repeat(7,1fr);}
.wbd-clbl{
    text-align:center;font-size:11px;font-weight:700;color:var(--muted);
    padding:10px 0;letter-spacing:.5px;text-transform:uppercase;
    border-bottom:1px solid var(--bdr);background:#fafbff;
}
.wbd-cc{
    min-height:140px;
    position:relative;
    border-top:1px solid var(--bdr);border-right:1px solid var(--bdr);
    min-width:0;transition:background .15s;
    overflow:visible;
}
.wbd-cc:hover{background:#fafbff;}
.wbd-cc:nth-child(7n){border-right:none;}
.wbd-cc.wbd-emp{background:#f9fafb;}
.wbd-cc.wbd-tod{background:linear-gradient(135deg,#f0ecff 0%,#fafbff 100%);}
.wbd-cc.wbd-tod .wbd-dn{background:var(--acc);color:#fff;border-radius:50%;box-shadow:0 2px 8px rgba(108,71,255,.35);}
.wbd-cci{
    padding:6px 5px 6px;
    display:flex;flex-direction:column;gap:4px;
    width:100%;
}
.wbd-dn{
    font-weight:700;font-size:12px;flex-shrink:0;
    display:inline-flex;align-items:center;justify-content:center;
    width:22px;height:22px;margin-bottom:2px;
}

/* -- Calendar event chip ------------------------------------ */
.wbd-ce{
    border-radius:5px;
    border-left:3px solid var(--acc);
    cursor:pointer;
    transition:all .15s;
    width:100%;
    background:var(--alt);
    color:var(--acc);
    text-decoration:none;
    box-sizing:border-box;
    display:block;
    padding:4px 6px 4px 6px;
}
.wbd-ce:hover{background:var(--acc);color:#fff;box-shadow:0 2px 6px rgba(108,71,255,.3);}
.wbd-ce.wbd-bkd{background:var(--glt);color:var(--grn);border-left-color:var(--grn-b);}
.wbd-ce.wbd-bkd:hover{background:var(--grn);color:#fff;}
.wbd-ce.wbd-pend{background:#fef3c7;color:#92400e;border-left-color:#f59e0b;}
.wbd-ce.wbd-pend:hover{background:#f59e0b;color:#fff;}
.wbd-cpill-time{
    display:block;
    font-size:9px;font-weight:700;opacity:.9;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    line-height:1.4;
}
.wbd-cpill-name{
    display:block;
    font-size:10px;font-weight:600;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    line-height:1.4;
    margin-top:1px;
}
.wbd-cbdg{display:block;margin-top:3px;}
.wbd-cbdg-inner{
    display:inline-block;background:var(--acc);color:#fff;
    border-radius:3px;padding:2px 7px;font-size:8.5px;font-weight:700;line-height:1.5;
    white-space:nowrap;text-decoration:none;border:none;cursor:pointer;font-family:inherit;
}
.wbd-cbdg-inner.wbd-bkd{background:var(--grn-b);}
.wbd-cbdg-inner.wbd-pay{background:#f59e0b;}
.wbd-cbdg-inner.wbd-full{background:#9ca3af;}
.wbd-cmore{font-size:9px;color:var(--muted);font-weight:600;padding:2px 3px;line-height:1.3;display:block;}

/* -- History ------------------------------------------------ */
.wbd-hl{padding:4px 20px 12px;}
.wbd-hr{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 10px;border-bottom:1px solid var(--bdr);gap:12px;
    cursor:pointer;transition:background .15s;margin:0 -10px;border-radius:8px;
}
.wbd-hr:hover{background:var(--alt);}
.wbd-hr:last-child{border-bottom:none;}
.wbd-hico{width:36px;height:36px;border-radius:10px;background:var(--alt);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.wbd-hico svg{color:var(--acc);}
.wbd-hinfo{flex:1;min-width:0;}
.wbd-htt{font-size:14px;font-weight:700;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.wbd-hdt{font-size:11.5px;color:var(--muted);display:flex;align-items:center;gap:4px;}
.wbd-pp{font-size:13px;font-weight:700;border-radius:8px;padding:5px 14px;white-space:nowrap;flex-shrink:0;}
.wbd-pp.wbd-paid{background:var(--alt);color:var(--acc);}
.wbd-pp.wbd-free{background:var(--glt);color:var(--grn);}

/* -- Sidebar ------------------------------------------------ */
.wbd-side .wbd-ch{padding:14px 16px 12px;}
.wbd-upsrch{
    display:flex;align-items:center;gap:6px;
    background:#f5f6fb;border:1.5px solid var(--bdr);
    border-radius:20px;padding:0 10px;height:30px;flex:1;transition:border-color .18s;
}
.wbd-upsrch:focus-within{border-color:var(--acc);}
.wbd-upsrch input{border:none;background:transparent;outline:none;font-size:12px;font-family:inherit;color:var(--txt);width:100%;}
.wbd-upsrch svg{color:var(--muted);flex-shrink:0;}
.wbd-ul{padding:8px 12px 14px;}
.wbd-ui{
    border-radius:12px;padding:13px 13px 11px;margin-top:8px;
    border:1.5px solid var(--bdr);transition:border-color .18s,box-shadow .18s,transform .18s;
    background:var(--surf);cursor:pointer;
}
.wbd-ui:hover{border-color:var(--acc);box-shadow:var(--sh-hover);transform:translateY(-1px);}
.wbd-ui h4{font-size:13.5px;font-weight:800;line-height:1.35;margin-bottom:6px;letter-spacing:-.2px;}
.wbd-um span{font-size:11.5px;color:var(--muted);display:flex;align-items:center;gap:4px;margin-bottom:3px;font-weight:500;}
.wbd-um svg{flex-shrink:0;opacity:.7;}
.wbd-uf{display:flex;align-items:center;justify-content:flex-end;margin-top:10px;}

/* buttons */
.wbd-btn{
    display:inline-flex;align-items:center;gap:5px;font-size:12.5px;font-weight:700;
    padding:7px 18px;border-radius:20px;background:var(--acc);color:#fff;
    text-decoration:none;border:none;cursor:pointer;font-family:inherit;
    transition:background .18s,transform .15s,box-shadow .18s;
    box-shadow:0 2px 8px rgba(108,71,255,.25);
}
.wbd-btn:hover{background:var(--acc-h);color:#fff;transform:translateY(-1px);box-shadow:0 4px 14px rgba(108,71,255,.35);}
.wbd-btn:active{transform:translateY(0);}
.wbd-btn.wbd-bkd{background:#e5e7eb;color:#6b7280;box-shadow:none;cursor:default;pointer-events:none;}
.wbd-btn-full{background:#e5e7eb;color:#9ca3af;box-shadow:none;cursor:default;pointer-events:none;display:inline-flex;align-items:center;}
.wbd-pay-now{background:#f59e0b;color:#fff;box-shadow:0 2px 8px rgba(245,158,11,.3);}
.wbd-pay-now:hover{background:#d97706;color:#fff;box-shadow:0 4px 14px rgba(245,158,11,.4);}

/* empty state */
.wbd-mt{text-align:center;padding:36px 16px;color:var(--muted);font-size:13px;display:flex;flex-direction:column;align-items:center;gap:10px;}
.wbd-mt svg{opacity:.35;}
'; }

/* ---------------------------------------------------------------
   SHORTCODE
--------------------------------------------------------------- */

add_shortcode( 'student_webinar_dashboard', 'wbd_render' );

function wbd_render() {

    if ( ! is_user_logged_in() ) {
        return '<p style="font-family:sans-serif;color:#555;padding:16px 0">Please '
             . '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a>'
             . ' to view your dashboard.</p>';
    }

    $user    = wp_get_current_user();
    $uid     = $user->ID;
    $uname   = trim( $user->display_name ) ?: 'Student';
    $ufirst  = $user->user_firstname ?: explode(' ', $uname)[0];
    $uavatar = get_avatar_url( $uid, [ 'size' => 60 ] );
    $cur     = function_exists('get_woocommerce_currency_symbol')
               ? get_woocommerce_currency_symbol() : '&#8377;';

    $url_dashboard = home_url('/webinar-dashboard');
    $url_library   = home_url('/webinar-library');
    $url_myevents  = home_url('/my-events');

    /* -- Data -- */
    $upcoming    = wbd_upcoming_events();
    $reg_arr     = wbd_registered_ids( $uid );
    $reg_ids     = array_flip( $reg_arr );
    $pending_arr = wbd_pending_ids( $uid );
    $pending_ids = array_flip( $pending_arr );
    $past        = wbd_past_bookings( $uid );

    /* -- Calendar state -- */
    $cy = isset($_GET['wbd_y']) ? (int)$_GET['wbd_y'] : (int)date('Y');
    $cm = isset($_GET['wbd_m']) ? (int)$_GET['wbd_m'] : (int)date('n');
    $cm = max(1, min(12, $cm));
    $cal_evs = wbd_calendar_events( $cy, $cm );

    $by_day = [];
    foreach ( $cal_evs as $ev ) {
        $d = (int) wp_date('j', strtotime( $ev['periodStart'] . ' UTC' ));
        $by_day[$d][] = $ev;
    }

    $base    = get_permalink();
    $pm=$cm-1;$py=$cy; if($pm<1){$pm=12;$py--;}
    $nm=$cm+1;$ny=$cy; if($nm>12){$nm=1;$ny++;}
    $prev_u  = add_query_arg(['wbd_y'=>$py,'wbd_m'=>$pm],$base);
    $next_u  = add_query_arg(['wbd_y'=>$ny,'wbd_m'=>$nm],$base);
    $today_u = add_query_arg(['wbd_y'=>date('Y'),'wbd_m'=>date('n')],$base);
    $td=(int)wp_date('j');$tm=(int)wp_date('n');$ty=(int)wp_date('Y');
    $now_ts = current_time('timestamp');

    $max_chips = 10;

    ob_start();
    ?>
<style><?php echo wbd_css(); ?></style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- wbd_vars is injected via wp_localize_script in wp_enqueue_scripts — no inline script needed here -->

<!-- ===== NAVBAR ===== -->
<nav class="wbd-nav">
  <div class="wbd-nav-inner">
    <a href="<?php echo esc_url(home_url()); ?>" class="wbd-nav-logo">
      <div class="wbd-nav-logo-box">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.89L15 14"/>
          <rect x="2" y="6" width="13" height="12" rx="2"/>
        </svg>
      </div>
      <?php echo esc_html(get_bloginfo('name')); ?>
    </a>
    <div class="wbd-nav-links">
      <a href="<?php echo esc_url($url_dashboard); ?>" class="active">Dashboard</a>
      <a href="<?php echo esc_url($url_library); ?>">Webinar Library</a>
      
    </div>
    <div class="wbd-nav-r">
      <div class="wbd-nav-bell">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="wbd-nav-bdot"></span>
      </div>
      <img class="wbd-nav-avatar" src="<?php echo esc_url($uavatar); ?>" alt="<?php echo esc_attr($uname); ?>">
      <span class="wbd-nav-uname">Hi, <?php echo esc_html($ufirst ?: $uname); ?></span>
      <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="wbd-nav-logout">Logout</a>
    </div>
  </div>
</nav>

<div class="wbd-wrap">

  <div class="wbd-layout">

    <!-- LEFT: Calendar + History -->
    <div class="wbd-main">

      <!-- Calendar -->
      <div class="wbd-card">
        <div class="wbd-cnav">
          <a href="<?php echo esc_url($prev_u); ?>" class="wbd-carr">&#8249;</a>
          <h3><?php echo esc_html(date('F Y', mktime(0,0,0,$cm,1,$cy))); ?></h3>
          <a href="<?php echo esc_url($today_u); ?>" class="wbd-ctod">Today</a>
          <a href="<?php echo esc_url($next_u); ?>" class="wbd-carr">&#8250;</a>
        </div>

        <div class="wbd-cgrid">
          <?php foreach(['S','M','T','W','T','F','S'] as $l): ?>
            <div class="wbd-clbl"><?php echo $l; ?></div>
          <?php endforeach; ?>

          <?php
          $first_ts    = mktime(0,0,0,$cm,1,$cy);
          $start_dow   = (int)date('w',$first_ts);
          $days_in_mon = (int)date('t',$first_ts);

          for($e=0;$e<$start_dow;$e++):
              echo '<div class="wbd-cc wbd-emp"><div class="wbd-cci"></div></div>';
          endfor;

          for($d=1;$d<=$days_in_mon;$d++):
              $is_today = ($d===$td && $cm===$tm && $cy===$ty);
              $cell_ts  = mktime(23,59,59,$cm,$d,$cy);
              $is_past  = $cell_ts < $now_ts;
              $day_evs  = $by_day[$d] ?? [];
              $overflow = max(0, count($day_evs) - $max_chips);
          ?>
            <div class="wbd-cc<?php echo $is_today?' wbd-tod':''; ?>">
              <div class="wbd-cci">
                <span class="wbd-dn"><?php echo $d; ?></span>

                <?php foreach(array_slice($day_evs, 0, $max_chips) as $cev):
                    $eid_cal    = (int)$cev['id'];
                    $ev_price   = (float)$cev['price'];
                    $is_reg     = isset($reg_ids[$eid_cal]);
                    $is_pending = isset($pending_ids[$eid_cal]);
                    $ev_ts      = strtotime( $cev['periodStart'] . ' UTC' );
                    $tstr       = wp_date('g:i A', $ev_ts);
                    $ename      = html_entity_decode( wp_strip_all_tags( $cev['name'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                    $ename      = trim( $ename );
                    $cal_spots  = (int)$cev['maxCap'] > 0 ? max(0, (int)$cev['maxCap'] - (int)$cev['booked']) : null;
                    $no_spots   = $cal_spots !== null && $cal_spots <= 0;
                    $chip_cls   = 'wbd-ce';
                    if     ($is_reg)     $chip_cls .= ' wbd-bkd';
                    elseif ($is_pending) $chip_cls .= ' wbd-pend';
                    $info_url   = esc_url( home_url('/webinar-info/?event-id=' . $eid_cal) );
                    $pay_page   = $ev_price <= 0 ? 'free-webinar-payment' : 'paid-webinar-payment';
                    $pay_url    = esc_url( add_query_arg('event-id', $eid_cal, home_url('/' . $pay_page)) );
                ?>
                  <div class="<?php echo $chip_cls; ?>"
                       title="<?php echo esc_attr($ename.' - '.$tstr); ?>"
                       onclick="window.location.href='<?php echo $info_url; ?>'">
                    <span class="wbd-cpill-time"><?php echo esc_html($tstr); ?></span>
                    <span class="wbd-cpill-name"><?php echo esc_html($ename); ?></span>
                    <span class="wbd-cbdg">
                      <?php if($is_reg): ?>
                        <span class="wbd-cbdg-inner wbd-bkd">&#10003; Booked</span>
                      <?php elseif($is_pending): ?>
                        <a class="wbd-cbdg-inner wbd-pay" href="<?php echo $pay_url; ?>" onclick="event.stopPropagation()">Pay Now</a>
                      <?php elseif($no_spots): ?>
                        <span class="wbd-cbdg-inner wbd-full">Full</span>
                      <?php elseif(!$is_past): ?>
                        <a class="wbd-cbdg-inner" href="<?php echo $info_url; ?>" onclick="event.stopPropagation()">Book</a>
                      <?php endif; ?>
                    </span>
                  </div>
                <?php endforeach; ?>

                <?php if($overflow>0): ?>
                  <span class="wbd-cmore">+<?php echo $overflow; ?> more</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endfor; ?>
        </div><!-- /grid -->
      </div><!-- /calendar card -->

      <!-- WEBINAR HISTORY -->
      <div class="wbd-card">
        <div class="wbd-ch"><span class="wbd-ct">Webinar History</span></div>
        <div class="wbd-hl" id="wbd-hlist">
          <?php if(empty($past)): ?>
            <div class="wbd-mt">
              <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="10"/></svg>
              No past webinars yet.
            </div>
          <?php else: ?>
            <?php foreach($past as $p):
                $amt      = (float)$p['amount_paid'];
                $free     = $amt <= 0;
                $lbl      = $free ? 'Free' : $cur.number_format($amt,2);
                $cls      = $free ? 'wbd-pp wbd-free' : 'wbd-pp wbd-paid';
                $has_cert = ! empty( $p['certificate_id'] );
                $cert_url = $has_cert && function_exists('platform_get_certificate_url')
                            ? platform_get_certificate_url( $p['certificate_id'] )
                            : '';
            ?>
            <div class="wbd-hr"
                 data-name="<?php echo esc_attr(strtolower($p['name'])); ?>"
                 onclick="window.location.href='<?php echo esc_url(home_url('/webinar-info/?event-id='.$p['event_id'])); ?>'">
              <div class="wbd-hico">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path d="M15 10l4.553-2.069A1 1 0 0 1 21 8.82v6.36a1 1 0 0 1-1.447.89L15 14"/>
                  <rect x="2" y="6" width="13" height="12" rx="2"/>
                </svg>
              </div>
              <div class="wbd-hinfo">
                <div class="wbd-htt"><?php echo esc_html($p['name']); ?></div>
                <div class="wbd-hdt">
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  Completed <?php echo esc_html(wp_date('M j, Y', strtotime($p['periodStart'] . ' UTC'))); ?>
                </div>
              </div>
              <div class="wbd-hactions" onclick="event.stopPropagation()">
                <span class="<?php echo esc_attr($cls); ?>"><?php echo esc_html($lbl); ?></span>
                <?php if( $has_cert && $cert_url ): ?>
                  <a href="<?php echo esc_url($cert_url); ?>" target="_blank" class="wbd-cert-btn" title="View Certificate" onclick="event.stopPropagation()">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Certificate
                  </a>
                <?php else: ?>
                  <button class="wbd-cert-btn wbd-cert-gen"
    data-event-id="<?php echo esc_attr($p['event_id']); ?>"
    data-period-id="<?php echo esc_attr($p['period_id']); ?>"
    title="Generate Certificate">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
                    Get Certificate
                  </button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div><!-- /history card -->

    </div><!-- /left -->

    <!-- RIGHT: Upcoming Events -->
    <aside class="wbd-side">
      <div class="wbd-card">
        <div class="wbd-ch">
          <span class="wbd-ct">Upcoming Events</span>
          <div class="wbd-upsrch">
            <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="wbd-upsrch" placeholder="Search...">
          </div>
        </div>
        <div class="wbd-ul" id="wbd-ulist">
          <?php if(empty($upcoming)): ?>
            <div class="wbd-mt">
              <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              No upcoming events.
            </div>
          <?php else: ?>
            <?php foreach($upcoming as $ev):
                $eid        = (int)$ev['id'];
                $ev_price   = (float)$ev['price'];
                $is_reg     = isset($reg_ids[$eid]);
                $is_pending = isset($pending_ids[$eid]);
                $start_ts   = strtotime( $ev['periodStart'] . ' UTC' );
                $end_ts     = strtotime( $ev['periodEnd']   . ' UTC' );
                $max_cap    = (int)$ev['maxCap'];
                $booked_cnt = (int)$ev['booked'];
                $spots      = $max_cap > 0 ? max(0, $max_cap - $booked_cnt) : null;
                $date_str   = wp_date('M j, Y', $start_ts);
                $start_str  = wp_date('g:i A',  $start_ts);
                $end_str    = wp_date('g:i A',  $end_ts);
                $info_url   = esc_url( home_url('/webinar-info/?event-id=' . $eid) );
                $pay_page   = $ev_price <= 0 ? 'free-webinar-payment' : 'paid-webinar-payment';
                $pay_url    = esc_url( add_query_arg('event-id', $eid, home_url('/' . $pay_page)) );
            ?>
            <div class="wbd-ui"
                 data-name="<?php echo esc_attr(strtolower($ev['name'])); ?>"
                 onclick="window.location.href='<?php echo $info_url; ?>'">
              <h4><?php echo esc_html($ev['name']); ?></h4>
              <div class="wbd-um">
                <span>
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  <?php echo esc_html( $date_str ); ?>
                </span>
                <span>
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?php echo esc_html( $start_str . ' - ' . $end_str ); ?>
                </span>
                <?php if($spots!==null): ?>
                <span>
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  <?php echo esc_html( $booked_cnt . '/' . $max_cap . ' slots booked' ); ?>
                </span>
                <?php endif; ?>
              </div>
              <div class="wbd-uf" onclick="event.stopPropagation()">
                <?php if($is_reg): ?>
                  <span class="wbd-btn wbd-bkd">Booked</span>
                <?php elseif($is_pending): ?>
                  <a class="wbd-btn wbd-pay-now" href="<?php echo $pay_url; ?>">Pay Now</a>
                <?php elseif($spots !== null && $spots <= 0): ?>
                  <span class="wbd-btn wbd-btn-full">Full</span>
                <?php else: ?>
                  <a class="wbd-btn" href="<?php echo $info_url; ?>">Book Now</a>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </aside>

  </div><!-- /.wbd-layout -->
</div><!-- /.wbd-wrap -->

<script>
(function(){
    'use strict';

    /* -- Live search -- */
    function liveSearch(inputId, listId, itemSel){
        var inp = document.getElementById(inputId);
        if(!inp) return;
        inp.addEventListener('input', function(){
            var q = this.value.toLowerCase();
            document.querySelectorAll('#'+listId+' '+itemSel).forEach(function(el){
                el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
            });
        });
    }
    liveSearch('wbd-upsrch', 'wbd-ulist', '.wbd-ui');
    liveSearch('wbd-hsrch',  'wbd-hlist', '.wbd-hr');

    /* -- Certificate Generation -- */
    function attachCertHandlers(){
        document.querySelectorAll('.wbd-cert-gen').forEach(function(btn){
            btn.onclick = function(e){
                e.stopPropagation();
                if(btn.classList.contains('loading')) return;

                if(typeof wbd_vars === 'undefined' || !wbd_vars.ajax_url){
                    alert('Page configuration error. Please refresh and try again.');
                    return;
                }

                var eventId  = parseInt(btn.dataset.eventId,  10);
                var periodId = parseInt(btn.dataset.periodId, 10);
                if(!eventId || !periodId){
                    alert('Missing event data. Please refresh the page.');
                    return;
                }

                btn.classList.add('loading');
                btn.disabled = true;
                btn.innerHTML =
                    '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                    '<circle cx="12" cy="12" r="10"/></svg> Generating...';

                fetch(wbd_vars.ajax_url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action:    'generate_webinar_certificate',
                        nonce:     wbd_vars.nonce_generate,
                        event_id:  eventId,
                        period_id: periodId
                    })
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if(data.success && data.data && data.data.url){
                        btn.outerHTML =
                            '<a href="' + data.data.url + '" target="_blank" class="wbd-cert-btn" title="View Certificate">' +
                            '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                            '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                            'Certificate</a>';
                    } else {
                        var msg = (data.data && data.data.message) ? data.data.message : 'Failed to generate certificate.';
                        alert(msg);
                        btn.classList.remove('loading');
                        btn.disabled = false;
                        btn.innerHTML =
                            '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                            '<path d="M12 4v16m8-8H4"/></svg> Get Certificate';
                    }
                })
                .catch(function(err){
                    console.error('Certificate fetch error:', err);
                    alert('Network error. Please try again.');
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    btn.innerHTML =
                        '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                        '<path d="M12 4v16m8-8H4"/></svg> Get Certificate';
                });
            };
        });
    }

    // Attach on DOM ready
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', attachCertHandlers);
    } else {
        attachCertHandlers();
    }

})();
</script>
    <?php
    return ob_get_clean();
}

/* ---------------------------------------------------------------
   AJAX: Generate Certificate
   Handles: wp_ajax_generate_webinar_certificate
--------------------------------------------------------------- */
add_action( 'wp_ajax_generate_webinar_certificate', 'wbd_ajax_generate_certificate' );

function wbd_ajax_generate_certificate() {

    // 1. Verify nonce
    if ( ! check_ajax_referer( 'generate_certificate', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }

    // 2. Must be logged in
    $uid = get_current_user_id();
    if ( ! $uid ) {
        wp_send_json_error( [ 'message' => 'Not logged in.' ] );
    }

    // 3. Sanitize inputs
    $event_id  = absint( $_POST['event_id']  ?? 0 );
    $period_id = absint( $_POST['period_id'] ?? 0 );

    if ( ! $event_id || ! $period_id ) {
        wp_send_json_error( [ 'message' => 'Invalid event or period.' ] );
    }

    // 4. Confirm the user actually attended this event (paid + approved)
    global $wpdb;
    $u   = get_userdata( $uid );
    $cid = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}amelia_users
         WHERE email = %s AND type = 'customer' LIMIT 1",
        $u->user_email
    ) );

    if ( ! $cid ) {
        wp_send_json_error( [ 'message' => 'No booking record found.' ] );
    }

    $attended = $wpdb->get_var( $wpdb->prepare(
        "SELECT cb.id
         FROM {$wpdb->prefix}amelia_customer_bookings cb
         INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x
                 ON cb.id = x.customerBookingId
         INNER JOIN {$wpdb->prefix}amelia_payments p
                 ON p.customerBookingId = cb.id
         WHERE cb.customerId = %d
           AND x.eventPeriodId = %d
           AND cb.status = 'approved'
           AND p.status  = 'paid'
         LIMIT 1",
        (int) $cid,
        $period_id
    ) );

    if ( ! $attended ) {
        wp_send_json_error( [ 'message' => 'No paid booking found for this event.' ] );
    }

    // 5. Check if certificate already exists — return it immediately if so
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT certificate_id
         FROM {$wpdb->prefix}webinar_certificates
         WHERE student_user_id = %d
           AND event_id  = %d
           AND period_id = %d
           AND status    = 'active'
         LIMIT 1",
        $uid, $event_id, $period_id
    ) );

    if ( $existing ) {
        $url = function_exists( 'platform_get_certificate_url' )
               ? platform_get_certificate_url( $existing )
               : home_url( '/certificate/?id=' . $existing );
        wp_send_json_success( [ 'url' => $url ] );
    }

    // 6. Generate a new certificate.
    //    Prefers platform_generate_certificate() if available.
    //    Falls back to inserting a row in wp_webinar_certificates.
    if ( function_exists( 'platform_generate_certificate' ) ) {
        $cert_id = platform_generate_certificate( $uid, $event_id, $period_id );
    } else {
        $token    = wp_generate_uuid4();
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'webinar_certificates',
            [
                'student_user_id' => $uid,
                'event_id'        => $event_id,
                'period_id'       => $period_id,
                'certificate_id'  => $token,
                'status'          => 'active',
                'created_at'      => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%s', '%s' ]
        );
        $cert_id = $inserted ? $token : false;
    }

    if ( ! $cert_id ) {
        wp_send_json_error( [ 'message' => 'Certificate generation failed. Please contact support.' ] );
    }

    $url = function_exists( 'platform_get_certificate_url' )
           ? platform_get_certificate_url( $cert_id )
           : home_url( '/certificate/?id=' . $cert_id );

    wp_send_json_success( [ 'url' => $url ] );
}