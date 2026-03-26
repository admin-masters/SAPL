<?php
/**
 * Medical Expert Dashboard Shortcode  v5 (FIXED)
 * Shortcode: [medical_expert_dashboard]
 * 
 * KEY IMPROVEMENT v5:
 *  - Contract URL resolution now mirrors Flow 7 exactly
 *  - Handles filesystem paths, URLs, relative paths, and .html files
 *  - Proper fallback to attachment URL if pdf_path unavailable
 *  - Uses wp_upload_dir() to convert filesystem paths to URLs consistently
 *  - All view contract links now working properly
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'medical_expert_dashboard', 'med_expert_dashboard_render' );

// -----------------------------------------------------------------------
// SUPPRESS JETPACK SHARING / LIKES
// -----------------------------------------------------------------------
if ( ! function_exists( 'pcore_is_shortcode_only_page' ) ) {
    function pcore_is_shortcode_only_page( $post = null ) {
        if ( ! $post ) $post = get_post();
        if ( ! $post ) return false;
        return trim( strip_shortcodes( wp_strip_all_tags( $post->post_content ) ) ) === '';
    }
}

add_filter( 'sharing_show', function( $show, $post ) {
    return pcore_is_shortcode_only_page( $post ) ? false : $show;
}, 10, 2 );

add_filter( 'likes_are_enabled', function( $enabled ) {
    return pcore_is_shortcode_only_page() ? false : $enabled;
} );

function med_expert_dashboard_render() {

    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px;text-align:center;">Please log in.</div>';
    }

    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID;
    $user_email   = $current_user->user_email;

    /* ==================================================================
       1. AMELIA PROVIDER/EMPLOYEE ID
       ================================================================== */
    $employee_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}amelia_users
         WHERE externalId = %d AND type = 'provider' LIMIT 1",
        $user_id
    ) );
    if ( ! $employee_id ) {
        $employee_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_users
             WHERE email = %s AND type = 'provider' LIMIT 1",
            $user_email
        ) );
    }

    if ( ! $employee_id && ! current_user_can( 'manage_options' ) ) {
        return '<div style="padding:30px;text-align:center;">Expert account not linked. Please contact support.</div>';
    }

    /* ==================================================================
       2. EARNINGS
       ================================================================== */
    $total_earnings = 0.00;
    if ( $employee_id ) {
        $earn_a = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(COALESCE(NULLIF(p.amount,0), cb.price, 0)), 0)
             FROM {$wpdb->prefix}amelia_payments p
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings cb ON p.customerBookingId = cb.id
             INNER JOIN {$wpdb->prefix}amelia_appointments       a  ON cb.appointmentId   = a.id
             WHERE a.providerId = %d AND p.status IN ('paid','partiallyPaid')",
            $employee_id
        ) );
        $earn_b = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(cb.price), 0)
             FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_appointments a ON cb.appointmentId = a.id
             WHERE a.providerId = %d AND a.status = 'approved' AND cb.status = 'approved'",
            $employee_id
        ) );
        $total_earnings = max( $earn_a, $earn_b );
    }

    /* ==================================================================
       3. UPCOMING SESSIONS
       ================================================================== */
    $sessions = [];
    $now      = current_time( 'mysql' );

    if ( $employee_id ) {
        $raw_up = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                a.id            AS appt_id,
                a.bookingStart,
                a.internalNotes,
                s.name          AS service_name,
                au.email        AS cust_email,
                au.firstName    AS cust_first,
                au.lastName     AS cust_last,
                cb.status       AS booking_status,
                cb.customFields,
                pr.topic        AS req_topic
             FROM {$wpdb->prefix}amelia_appointments        a
             INNER JOIN {$wpdb->prefix}amelia_services          s  ON a.serviceId   = s.id
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings cb ON a.id          = cb.appointmentId
             INNER JOIN {$wpdb->prefix}amelia_users             au ON cb.customerId = au.id
             LEFT  JOIN {$wpdb->prefix}platform_requests        pr ON pr.appointment_id = a.id
                                                                   AND pr.expert_user_id = %d
                                                                   AND pr.topic IS NOT NULL
                                                                   AND pr.topic != ''
             WHERE a.providerId = %d
               AND a.bookingStart > %s
               AND a.status IN ('approved','pending')
             ORDER BY a.bookingStart ASC LIMIT 10",
            $user_id, $employee_id, $now
        ) );

        foreach ( $raw_up as $row ) {
            if ( count( $sessions ) >= 2 ) break;

            $title = '';

            // 1a: Direct appointment_id match
            if ( ! empty( $row->req_topic ) ) {
                $title = $row->req_topic;
            }

            // 1b: match by college WP user
            if ( empty( $title ) ) {
                $college_wp = get_user_by( 'email', $row->cust_email );
                if ( $college_wp ) {
                    $topic = $wpdb->get_var( $wpdb->prepare(
                        "SELECT pr.topic
                         FROM {$wpdb->prefix}platform_requests pr
                         INNER JOIN {$wpdb->prefix}platform_contracts pc ON pc.request_id = pr.id
                         WHERE pc.expert_user_id  = %d
                           AND pr.college_user_id = %d
                           AND pr.topic IS NOT NULL AND pr.topic != ''
                         ORDER BY pr.created_at DESC LIMIT 1",
                        $user_id, $college_wp->ID
                    ) );
                    if ( ! empty( $topic ) ) $title = $topic;
                }
            }

            // 1c: internalNotes
            if ( empty( $title ) && ! empty( trim( $row->internalNotes ) ) ) {
                $title = trim( $row->internalNotes );
            }

            // 1d: customFields JSON
            if ( empty( $title ) && ! empty( $row->customFields ) ) {
                $cf = json_decode( $row->customFields, true );
                if ( is_array( $cf ) ) {
                    foreach ( $cf as $f ) {
                        $lbl = strtolower( $f['label'] ?? '' );
                        if ( in_array( $lbl, ['topic','subject','title','session name','class name','session title'], true )
                             && ! empty( $f['value'] ) ) {
                            $title = is_array( $f['value'] ) ? implode( ', ', $f['value'] ) : $f['value'];
                            break;
                        }
                    }
                }
            }

            // 1e: service name fallback
            if ( empty( $title ) ) $title = $row->service_name;

            // Student name
            $student = trim( $row->cust_first . ' ' . $row->cust_last );
            $wp_u    = get_user_by( 'email', $row->cust_email );
            if ( $wp_u && ! empty( $wp_u->display_name ) && strpos( $wp_u->display_name, '@' ) === false ) {
                $student = $wp_u->display_name;
            }

            $sessions[] = [
                'title'      => $title,
                'student'    => $student,
                'date'       => date_i18n( 'D, M j � g:i A', strtotime( $row->bookingStart ) ),
                'status'     => ucfirst( $row->booking_status ),
                'status_key' => strtolower( $row->booking_status ),
            ];
        }

        /* ---- Priority 2: contracts awaiting signature ---- */
        if ( count( $sessions ) < 2 ) {
            $pending = $wpdb->get_results( $wpdb->prepare(
                "SELECT pr.topic, cu.display_name AS cname, cu.user_email AS cemail, pr.created_at
                 FROM {$wpdb->prefix}platform_contracts pc
                 INNER JOIN {$wpdb->prefix}platform_requests pr ON pc.request_id     = pr.id
                 LEFT  JOIN {$wpdb->users}                   cu ON pc.college_user_id = cu.ID
                 WHERE pc.expert_user_id = %d AND pc.accepted_at IS NULL
                 ORDER BY pr.created_at DESC LIMIT 5",
                $user_id
            ) );
            foreach ( $pending as $row ) {
                if ( count( $sessions ) >= 2 ) break;
                $college = ( ! empty( $row->cname ) && strpos( $row->cname, '@' ) === false )
                         ? $row->cname
                         : ( ! empty( $row->cemail ) ? explode( '@', $row->cemail )[0] : 'College' );
                $sessions[] = [
                    'title'      => ! empty( $row->topic ) ? $row->topic : 'Pending Session',
                    'student'    => $college,
                    'date'       => date_i18n( 'M j, Y', strtotime( $row->created_at ) ),
                    'status'     => 'Awaiting Signature',
                    'status_key' => 'pending',
                ];
            }
        }

        /* ---- Priority 3: requested sessions ---- */
        if ( count( $sessions ) < 2 ) {
            $requested = $wpdb->get_results( $wpdb->prepare(
                "SELECT pr.topic, cu.display_name AS cname, cu.user_email AS cemail, pr.created_at
                 FROM {$wpdb->prefix}platform_requests             pr
                 INNER JOIN {$wpdb->prefix}platform_request_responses rr ON pr.id = rr.request_id
                 LEFT  JOIN {$wpdb->users}                             cu ON pr.college_user_id = cu.ID
                 WHERE rr.expert_user_id = %d AND pr.status = 'pending'
                 ORDER BY pr.created_at DESC LIMIT 5",
                $user_id
            ) );
            foreach ( $requested as $row ) {
                if ( count( $sessions ) >= 2 ) break;
                $college = ( ! empty( $row->cname ) && strpos( $row->cname, '@' ) === false )
                         ? $row->cname
                         : ( ! empty( $row->cemail ) ? explode( '@', $row->cemail )[0] : 'College' );
                $sessions[] = [
                    'title'      => ! empty( $row->topic ) ? $row->topic : 'Requested Session',
                    'student'    => $college,
                    'date'       => date_i18n( 'M j, Y', strtotime( $row->created_at ) ),
                    'status'     => 'Requested',
                    'status_key' => 'requested',
                ];
            }
        }
    }

    /* ==================================================================
       4. RECENT TRANSACTIONS & CONTRACTS
       
       FIXED: Contract URL resolution follows Flow 7 pattern exactly:
         1. Query platform_contracts + platform_requests with INNER JOIN
         2. Try pdf_path first (convert filesystem?URL)
         3. Fallback to contract_pdf_id attachment URL
         4. Handle .html, .pdf, relative, and absolute paths
       ================================================================== */
    $all_tx    = [];
    $has_dates = false;

    if ( $employee_id ) {
        $raw_tx = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                au.email          AS cust_email,
                au.firstName      AS cust_first,
                au.lastName       AS cust_last,
                s.name            AS service_name,
                cb.price          AS booked_price,
                p.amount          AS pay_amount,
                p.dateTime        AS pay_date
             FROM {$wpdb->prefix}amelia_payments p
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings cb ON p.customerBookingId = cb.id
             INNER JOIN {$wpdb->prefix}amelia_appointments       a  ON cb.appointmentId   = a.id
             INNER JOIN {$wpdb->prefix}amelia_services           s  ON a.serviceId        = s.id
             INNER JOIN {$wpdb->prefix}amelia_users              au ON cb.customerId      = au.id
             WHERE a.providerId = %d
             ORDER BY p.dateTime DESC
             LIMIT 100",
            $employee_id
        ) );

        foreach ( $raw_tx as $tx ) {

            // Display name
            $display = trim( $tx->cust_first . ' ' . $tx->cust_last );
            $wp_cust = get_user_by( 'email', $tx->cust_email );
            if ( $wp_cust && ! empty( $wp_cust->display_name )
                          && strpos( $wp_cust->display_name, '@' ) === false ) {
                $display = $wp_cust->display_name;
            }

            // Amount
            $amount = (float) ( $tx->booked_price > 0 ? $tx->booked_price : $tx->pay_amount );

            // CONTRACT URL & TOPIC - mirrors Flow 7 resolve_contract_url()
            $contract_url = '';
            $tx_title = $tx->service_name; // default

            if ( $wp_cust ) {
                // FIXED: Query BOTH pdf_path and contract_pdf_id in one go
                $contract_data = $wpdb->get_row( $wpdb->prepare(
                    "SELECT pc.pdf_path, pc.contract_pdf_id, pr.topic
                     FROM {$wpdb->prefix}platform_contracts pc
                     INNER JOIN {$wpdb->prefix}platform_requests pr ON pc.request_id = pr.id
                     WHERE pc.expert_user_id  = %d
                       AND pc.college_user_id = %d
                     ORDER BY pc.id DESC LIMIT 1",
                    $user_id, $wp_cust->ID
                ) );

                if ( $contract_data ) {
                    // Use topic from platform_requests if available (more specific)
                    if ( ! empty( $contract_data->topic ) ) {
                        $tx_title = $contract_data->topic;
                    }

                    // FIXED: Contract URL resolution (mirrors Flow 7 exactly)
                    $contract_url = med_expert_resolve_contract_url(
                        $contract_data->pdf_path,
                        $contract_data->contract_pdf_id
                    );
                }
            }

            $has_dt = ! empty( $tx->pay_date ) && $tx->pay_date !== '0000-00-00 00:00:00';
            if ( $has_dt ) $has_dates = true;

            $all_tx[] = [
                'student'      => $display,
                'title'        => $tx_title,
                'amount'       => $amount,
                'date'         => $has_dt ? date_i18n( 'M j, Y', strtotime( $tx->pay_date ) ) : '',
                'contract_url' => $contract_url,
            ];
        }
    }

    /* ==================================================================
       5. SERVICES
       ================================================================== */
    $all_services = $wpdb->get_results(
        "SELECT id, name, price
         FROM {$wpdb->prefix}amelia_services
         WHERE status = 'visible'
         ORDER BY name ASC"
    );
    $assigned_ids = [];
    if ( $employee_id ) {
        $assigned_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT serviceId FROM {$wpdb->prefix}amelia_providers_to_services WHERE userId = %d",
            $employee_id
        ) ) );
    }

    $expert_name = $current_user->display_name;
    $avatar_url  = get_avatar_url( $user_id );

    /* ==================================================================
       6. RENDER
       ================================================================== */
    ob_start();
    ?>
    <style>
    /* === MEDICAL EXPERT DASHBOARD v5 � scoped to .med3 === */
    #wpadminbar{display:none!important;}
    html{margin-top:0!important;}
    header,#masthead,.site-header,.main-header,#header,
    .elementor-location-header,.ast-main-header-wrap,#site-header,
    .fusion-header-wrapper,.header-wrap,.nav-primary,
    div[data-elementor-type="header"]{display:none!important;}
    footer.site-footer,.site-footer,#colophon,#footer,.footer-area,
    .ast-footer-overlay,.footer-widgets-area,.footer-bar,
    div[data-elementor-type="footer"],.elementor-location-footer{display:none!important;}
    /* Social sharing / Jetpack widgets */
    .sharedaddy,.sd-sharing-enabled,.sd-block,.sd-content,
    .jp-relatedposts,.wpcnt,.post-likes-widget-placeholder,
    .wp-block-jetpack-likes,.entry-footer,.post-footer,
    .post-navigation,.post-tags,.post-categories,
    #jp-post-flair,div.sharedaddy{display:none!important;}
    .page-template-default .site-content,.site-main,#content,#page{
        margin:0!important;padding:0!important;
        max-width:100%!important;width:100%!important;}
    body,#page,.site,.site-content,#content,.wp-site-blocks,
    main.wp-block-group,.is-layout-flow{
        padding-bottom:0!important;margin-bottom:0!important;}

    .med3{
        --fn:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        --ink:#111827;--ink2:#374151;--ink3:#6b7280;
        --bd:#e5e7eb;--bg:#f3f4f6;--sf:#fff;--nv:#000;
        --gb:#dcfce7;--gt:#166534;
        --ab:#fef3c7;--at:#92400e;
        --rb:#fee2e2;--rt:#991b1b;
        font-family:var(--fn);background:var(--bg);color:var(--ink);min-height:100vh;
    }
    .med3 *,.med3 *::before,.med3 *::after{box-sizing:border-box;margin:0;padding:0;}

    /* Nav */
    .med3-nav{background:var(--sf);border-bottom:1px solid var(--bd);padding:0 28px;
              height:56px;display:flex;align-items:center;justify-content:space-between;
              position:sticky;top:0;z-index:100;}
    .med3-logo{font-size:20px;font-weight:800;letter-spacing:-1px;color:#4f46e5;text-decoration:none;}
    .med3-nav-r{display:flex;align-items:center;gap:14px;}
    .med3-bell{color:var(--ink3);cursor:pointer;display:flex;align-items:center;}
    .med3-av-w{display:flex;align-items:center;gap:8px;}
    .med3-av{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid var(--bd);}
    .med3-un{font-size:13px;font-weight:600;color:var(--ink2);}

    /* Body */
    .med3-body{padding:28px 28px 60px;max-width:1300px;margin:0 auto;}

    /* Header */
    .med3-hdr{display:flex;align-items:flex-start;justify-content:space-between;
              gap:20px;margin-bottom:24px;flex-wrap:wrap;}
    .med3-welcome h1{font-size:22px;font-weight:700;color:var(--ink);margin:0 0 4px;line-height:1.3;}
    .med3-welcome .role{font-size:13px;color:var(--ink3);}
    .med3-hdr-act{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
    .med3-vbadge{display:inline-flex;align-items:center;gap:6px;background:var(--gb);color:var(--gt);
                 padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;}
    .med3-abtn{background:var(--nv);color:#fff;padding:9px 18px;border-radius:8px;
               text-decoration:none;font-size:13px;font-weight:600;
               display:inline-flex;align-items:center;gap:7px;transition:opacity .15s;}
    .med3-abtn:hover{opacity:.82;color:#fff;}

    /* Earnings */
    .med3-earn{background:var(--sf);border:1px solid var(--bd);border-radius:12px;
               padding:20px 24px;display:inline-flex;align-items:center;gap:18px;
               margin-bottom:26px;min-width:220px;box-shadow:0 1px 3px rgba(0,0,0,.05);}
    .med3-ei{background:#f3f4f6;width:46px;height:46px;border-radius:50%;display:flex;
             align-items:center;justify-content:center;font-size:18px;font-weight:800;
             color:var(--ink2);flex-shrink:0;}
    .med3-el{font-size:11px;color:var(--ink3);text-transform:uppercase;letter-spacing:.6px;
             font-weight:600;margin-bottom:3px;}
    .med3-ea{font-size:26px;font-weight:700;color:var(--ink);display:block;line-height:1.1;}

    /* Grid */
    .med3-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;}
    @media(max-width:800px){.med3-grid{grid-template-columns:1fr;}}

    /* Card */
    .med3-card{background:var(--sf);border:1px solid var(--bd);border-radius:12px;
               overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);}
    .med3-ch{padding:16px 20px;border-bottom:1px solid #f3f4f6;
             display:flex;align-items:center;justify-content:space-between;}
    .med3-ch h2{font-size:15px;font-weight:700;color:var(--ink);}
    .med3-va{font-size:12px;color:var(--ink3);text-decoration:none;font-weight:500;
             cursor:pointer;background:none;border:none;padding:0;}
    .med3-va:hover{color:var(--ink);text-decoration:underline;}

    /* Sessions */
    .med3-sc{max-height:300px;overflow-y:auto;}
    .med3-sc::-webkit-scrollbar{width:5px;}
    .med3-sc::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px;}
    .med3-si{display:flex;align-items:center;justify-content:space-between;
             padding:14px 20px;border-bottom:1px solid #f9fafb;gap:12px;}
    .med3-si:last-child{border-bottom:none;}
    .med3-sii h4{font-size:13px;font-weight:600;color:var(--ink);margin-bottom:2px;}
    .med3-sii p{font-size:12px;color:var(--ink3);}
    .med3-sm{text-align:right;flex-shrink:0;}
    .med3-sm .dt{font-size:11px;color:var(--ink3);margin-bottom:4px;display:block;}

    /* Badges */
    .med3-b{font-size:11px;font-weight:600;padding:3px 10px;border-radius:12px;
             display:inline-block;white-space:nowrap;}
    .med3-b.approved,.med3-b.confirmed{background:var(--gb);color:var(--gt);}
    .med3-b.pending,.med3-b.requested,.med3-b.awaiting{background:var(--ab);color:var(--at);}
    .med3-b.rejected,.med3-b.canceled{background:var(--rb);color:var(--rt);}
    .med3-emp{padding:22px 20px;color:var(--ink3);font-size:13px;text-align:center;}

    /* Services */
    .med3-sv{display:flex;align-items:center;justify-content:space-between;
             padding:14px 20px;border-bottom:1px solid #f9fafb;}
    .med3-sv:last-child{border-bottom:none;}
    .med3-svi h4{font-size:13px;font-weight:600;color:var(--ink);margin-bottom:2px;}
    .med3-svi p{font-size:12px;color:var(--ink3);}
    .med3-tog{width:38px;height:22px;background:#e5e7eb;border-radius:22px;position:relative;
              cursor:default;flex-shrink:0;transition:background .2s;}
    .med3-tog.on{background:#111827;}
    .med3-tog::after{content:'';position:absolute;top:3px;left:3px;width:16px;height:16px;
                    background:#fff;border-radius:50%;transition:left .2s;}
    .med3-tog.on::after{left:19px;}

    /* Transactions */
    .med3-tw{overflow-x:auto;}
    .med3-t{width:100%;border-collapse:collapse;font-size:13px;}
    .med3-t thead th{text-align:left;padding:12px 18px;font-size:11px;font-weight:600;
                     color:var(--ink3);text-transform:uppercase;letter-spacing:.5px;
                     border-bottom:1px solid #f3f4f6;background:#fafafa;}
    .med3-t tbody td{padding:13px 18px;border-bottom:1px solid #f9fafb;color:var(--ink2);
                     vertical-align:middle;}
    .med3-t tbody tr:last-child td{border-bottom:none;}
    .med3-t tbody tr:hover td{background:#fafafa;}
    .med3-stc{display:flex;align-items:center;gap:10px;font-weight:600;color:var(--ink);}
    .med3-ini{width:30px;height:30px;border-radius:50%;background:#e0e7ff;color:#4338ca;
              display:inline-flex;align-items:center;justify-content:center;
              font-size:12px;font-weight:700;flex-shrink:0;}
    .med3-cl{display:inline-flex;align-items:center;gap:5px;color:#4f46e5;font-weight:600;
             text-decoration:none;font-size:12px;padding:5px 12px;
             border:1px solid #c7d2fe;border-radius:6px;transition:background .15s;}
    .med3-cl:hover{background:#eef2ff;}
    .med3-nc{color:var(--ink3);font-size:12px;font-style:italic;}
    .med3-txr.med3-hid{display:none;}
    .med3-bold{font-weight:700;color:var(--ink);}

    /* Footer */
    .med3-foot{text-align:center;padding:18px;font-size:11px;color:#9ca3af;
               border-top:1px solid var(--bd);margin-top:16px;}

    @media(max-width:600px){
        .med3-body{padding:18px 14px 48px;}
        .med3-nav{padding:0 14px;}
        .med3-hdr{flex-direction:column;}
    }
    </style>

    <div class="med3">
    <div class="med3-nav">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="med3-logo">
            <span style="font-style:italic">LO</span><span style="font-style:italic">GO</span>
        </a>
        <div class="med3-nav-r">
            <span class="med3-bell">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0018 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                </svg>
            </span>
            <div class="med3-av-w">
                <img src="<?php echo esc_url($avatar_url); ?>" alt="" class="med3-av">
                <span class="med3-un"><?php echo esc_html($expert_name); ?></span>
            </div>
        </div>
    </div>

    <div class="med3-body">

        <div class="med3-hdr">
            <div class="med3-welcome">
                <h1>Welcome, <?php echo esc_html($expert_name); ?></h1>
                <span class="role">Expert Medical Expert</span>
            </div>
            <div class="med3-hdr-act">
                <span class="med3-vbadge">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    Profile Verified
                </span>
                <a href="<?php echo esc_url(home_url('/expert-panel')); ?>" class="med3-abtn">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0121 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                    </svg>
                    Update Availability
                </a>
            </div>
        </div>

        <div class="med3-earn">
            <div class="med3-ei">$</div>
            <div>
                <div class="med3-el">Total Earnings</div>
                <span class="med3-ea">$<?php echo number_format($total_earnings, 2); ?></span>
            </div>
        </div>

        <div class="med3-grid">

            <!-- Upcoming Sessions -->
            <div class="med3-card">
                <div class="med3-ch">
                    <h2>Upcoming Sessions</h2>
                    <a href="<?php echo esc_url(home_url('/expert-college-requests')); ?>" class="med3-va">View All</a>
                </div>
                <div class="med3-sc">
                    <?php if (empty($sessions)): ?>
                        <div class="med3-emp">No upcoming sessions found.</div>
                    <?php else: foreach ($sessions as $sess):
                        $bk = $sess['status_key'];
                        $bc = in_array($bk, ['approved','confirmed','paid']) ? 'approved'
                           : (in_array($bk, ['rejected','canceled']) ? 'rejected' : 'pending');
                    ?>
                        <div class="med3-si">
                            <div class="med3-sii">
                                <h4><?php echo esc_html($sess['title']); ?></h4>
                                <p>with <?php echo esc_html($sess['student']); ?></p>
                            </div>
                            <div class="med3-sm">
                                <?php if (!empty($sess['date'])): ?>
                                    <span class="dt"><?php echo esc_html($sess['date']); ?></span>
                                <?php endif; ?>
                                <span class="med3-b <?php echo esc_attr($bc); ?>">
                                    <?php echo esc_html($sess['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Your Services -->
            <div class="med3-card">
                <div class="med3-ch"><h2>Your Services</h2></div>
                <div class="med3-sc">
                    <?php if (empty($all_services)): ?>
                        <div class="med3-emp">No services found.</div>
                    <?php else: foreach ($all_services as $svc):
                        $is_on = in_array((int)$svc->id, $assigned_ids, true);
                    ?>
                        <div class="med3-sv">
                            <div class="med3-svi">
                                <h4><?php echo esc_html($svc->name); ?></h4>
                                <p>$<?php echo number_format((float)$svc->price, 2); ?> / session</p>
                            </div>
                            <div class="med3-tog <?php echo $is_on ? 'on' : ''; ?>"></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div><!-- /.med3-grid -->

        <!-- Recent Transactions -->
        <div class="med3-card">
            <div class="med3-ch">
                <h2>Recent Transactions</h2>
                <?php if (count($all_tx) > 4): ?>
                    <button class="med3-va" id="med3-tx-toggle" onclick="med3ToggleTx(this)">View All</button>
                <?php endif; ?>
            </div>
            <div class="med3-tw">
                <?php if (empty($all_tx)): ?>
                    <div class="med3-emp">No transactions found.</div>
                <?php else: ?>
                    <table class="med3-t">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Title</th>
                                <th>Amount</th>
                                <?php if ($has_dates): ?><th>Date</th><?php endif; ?>
                                <th>View Contract</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_tx as $i => $tx): ?>
                                <tr class="med3-txr<?php echo $i >= 4 ? ' med3-hid' : ''; ?>">
                                    <td>
                                        <div class="med3-stc">
                                            <span class="med3-ini"><?php echo esc_html(mb_strtoupper(mb_substr($tx['student'],0,1))); ?></span>
                                            <?php echo esc_html($tx['student']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($tx['title']); ?></td>
                                    <td><span class="med3-bold">$<?php echo number_format($tx['amount'],2); ?></span></td>
                                    <?php if ($has_dates): ?>
                                        <td><?php echo esc_html($tx['date'] ?: '�'); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (!empty($tx['contract_url'])): ?>
                                            <a href="<?php echo esc_url($tx['contract_url']); ?>"
                                               target="_blank" rel="noopener noreferrer" class="med3-cl">
                                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24"
                                                     stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0013.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                                </svg>
                                                View Contract
                                            </a>
                                        <?php else: ?>
                                            <span class="med3-nc">No contract</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.med3-body -->
    <div class="med3-foot">� <?php echo date('Y'); ?> Expert Dashboard. All rights reserved.</div>
    </div><!-- /.med3 -->

    <script>
    function med3ToggleTx(btn) {
        var expanded = btn.getAttribute('data-expanded') === '1';
        if (!expanded) {
            document.querySelectorAll('.med3-txr').forEach(function(r){ r.classList.remove('med3-hid'); });
            btn.textContent = 'View Less';
            btn.setAttribute('data-expanded','1');
        } else {
            document.querySelectorAll('.med3-txr').forEach(function(r,i){ if(i>=4) r.classList.add('med3-hid'); });
            btn.textContent = 'View All';
            btn.setAttribute('data-expanded','0');
        }
    }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * CONTRACT URL RESOLUTION HELPER (mirrors Flow 7)
 * 
 * Resolves contract URLs from filesystem paths and attachment IDs
 * Handles: .pdf, .html, relative paths, absolute filesystem paths, full URLs
 */
function med_expert_resolve_contract_url( $pdf_path = '', $contract_pdf_id = 0 ) {
    
    // Try pdf_path first (filesystem or URL)
    if ( ! empty( $pdf_path ) ) {
        $uploads = wp_upload_dir();
        $base    = trailingslashit( $uploads['basedir'] );

        // CASE 1: Absolute filesystem path under uploads/
        if ( strpos( $pdf_path, $base ) === 0 ) {
            $relative = str_replace( $base, '', $pdf_path );
            return $uploads['baseurl'] . '/' . ltrim( $relative, '/' );
        }

        // CASE 2: Already a full URL (http/https)
        if ( filter_var( $pdf_path, FILTER_VALIDATE_URL ) ) {
            return $pdf_path;
        }

        // CASE 3: Relative path (e.g., "platform-contracts/contract-102.html")
        if ( ! empty( $pdf_path ) ) {
            return $uploads['baseurl'] . '/' . ltrim( $pdf_path, '/' );
        }
    }

    // Fallback: use attachment ID
    if ( ! empty( $contract_pdf_id ) ) {
        $url = wp_get_attachment_url( (int) $contract_pdf_id );
        if ( $url ) {
            return $url;
        }
    }

    // No URL available
    return '';
}