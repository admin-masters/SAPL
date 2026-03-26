<?php
/**
 * Webinar Expert Dashboard Shortcode
 * Shortcode: [webinar_expert_dashboard]
 *
 * Displays the expert's upcoming webinar schedule, uploaded materials,
 * and recent transactions. Materials are sourced from wp_webinar_materials
 * (uploaded_by = current user), joined to amelia_events + amelia_events_periods.
 *
 * Includes "+ New Consultation" button that opens a slide-in form to create
 * Amelia events via the Amelia REST API (no page reload).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'webinar_expert_dashboard', 'pcore_render_webinar_expert_dashboard' );

function pcore_render_webinar_expert_dashboard() {
    if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';

    $user       = wp_get_current_user();
    $user_id    = $user->ID;
    $nav_avatar = get_avatar_url( $user_id, [ 'size' => 36, 'default' => 'mystery' ] );

    $first_name = get_user_meta( $user_id, 'first_name', true );
    if ( ! empty( trim( $first_name ) ) ) {
        $user_name = trim( $first_name );
    } elseif ( ! empty( $user->display_name ) && strpos( $user->display_name, '@' ) === false ) {
        $user_name = $user->display_name;
    } else {
        $user_name = explode( '@', $user->user_email )[0];
    }

    $url_dashboard    = get_permalink();
    $url_calendar     = home_url( '/webinar_calender' );
    $url_webinars     = home_url( '/webinar_schedule' );
    $url_transactions = home_url( '/webinar_payment' );

    global $wpdb;
    $now       = current_time( 'mysql' );
    // FIX 1: use current_time('timestamp') so month_end respects IST
    $month_end = date( 'Y-m-t 23:59:59', current_time( 'timestamp' ) );

    /* -- Resolve Amelia provider ID -- */
    $provider_id = 0;
    if ( function_exists( 'platform_get_current_provider_id' ) ) {
        $provider_id = platform_get_current_provider_id();
    }
    if ( ! $provider_id && function_exists( 'platform_core_get_current_provider_id' ) ) {
        $provider_id = platform_core_get_current_provider_id();
    }
    if ( ! $provider_id ) {
        $provider_id = (int) get_user_meta( $user_id, 'amelia_employee_id', true );
    }

    /* -- Upcoming webinars (this month) -- */
    $upcoming_webinars = [];
    if ( $provider_id ) {
        $tbl_events  = $wpdb->prefix . 'amelia_events';
        $tbl_periods = $wpdb->prefix . 'amelia_events_periods';
        $upcoming_webinars = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.name, ep.periodStart, ep.periodEnd, ep.zoomMeeting
             FROM {$tbl_events} e
             INNER JOIN {$tbl_periods} ep ON ep.eventId = e.id
             WHERE e.organizerId = %d
               AND e.status != 'rejected'
               AND ep.periodStart > %s
               AND ep.periodStart <= %s
             ORDER BY ep.periodStart ASC
             LIMIT 3",
            $provider_id, $now, $month_end
        ), ARRAY_A );
    }

    /* -- Recent transactions (last 5) -- */
    $transactions = [];
    if ( function_exists( 'platform_core_get_event_payments' ) ) {
        $txn_data     = platform_core_get_event_payments(
            date( 'Y-m-d', current_time( 'timestamp' ) - ( 2 * YEAR_IN_SECONDS ) ),
            date( 'Y-m-d', current_time( 'timestamp' ) )
        );
        $transactions = array_slice( $txn_data['payments'] ?? [], 0, 5 );
    }

    /* -- Webinar materials uploaded by this user -- */
    $tbl_webinar_mats = $wpdb->prefix . 'webinar_materials';
    $materials = $wpdb->get_results( $wpdb->prepare(
        "SELECT wm.*,
                e.name        AS event_name,
                ep.periodStart AS event_start
         FROM {$tbl_webinar_mats} wm
         LEFT JOIN {$wpdb->prefix}amelia_events e          ON e.id       = wm.event_id
         LEFT JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId = e.id
         WHERE wm.uploaded_by = %d
         GROUP BY wm.id
         ORDER BY wm.uploaded_at DESC",
        $user_id
    ) );

    /* -- Icon / colour lookup tables -- */
    /* Icon letters used instead of inline SVGs to avoid kses stripping */
    $type_icons = [
        'Presentation Slides'        => ['letter' => 'P', 'color' => '#4338ca', 'bg' => '#eef2ff'],
        'Handouts / PDF Notes'       => ['letter' => 'H', 'color' => '#ef4444', 'bg' => '#fef2f2'],
        'Assignment / Homework'      => ['letter' => 'A', 'color' => '#d97706', 'bg' => '#fffbeb'],
        'Reference Books / Articles' => ['letter' => 'R', 'color' => '#059669', 'bg' => '#ecfdf5'],
        'Session Recordings'         => ['letter' => 'V', 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
        'Practice Sheets'            => ['letter' => 'S', 'color' => '#0891b2', 'bg' => '#ecfeff'],
        'Exam Prep Materials'        => ['letter' => 'E', 'color' => '#dc2626', 'bg' => '#fef2f2'],
    ];
    $type_svgs = []; // kept for compatibility
    $ext_map = [
        'pdf'  => [ '#fef2f2', '#ef4444' ],
        'ppt'  => [ '#fff7ed', '#ea580c' ],
        'pptx' => [ '#fff7ed', '#ea580c' ],
        'doc'  => [ '#eff6ff', '#3b82f6' ],
        'docx' => [ '#eff6ff', '#3b82f6' ],
        'mp4'  => [ '#f5f3ff', '#7c3aed' ],
        'mov'  => [ '#f5f3ff', '#7c3aed' ],
        'jpg'  => [ '#f0fdf4', '#16a34a' ],
        'jpeg' => [ '#f0fdf4', '#16a34a' ],
        'png'  => [ '#f0fdf4', '#16a34a' ],
    ];

    /* -- Amelia API config -- */
    $amelia_api_base = 'https://staging-68a5-inditechsites.wpcomstaging.com/amelia/wp-admin/admin-ajax.php';
    $amelia_api_key  = 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm';
    $zoom_user_email = 'sanyam.jain@inditech.co.in';

    /* -- WP upload dir for pre-material upload via AJAX -- */
    $upload_nonce = wp_create_nonce( 'wcd_upload_prematerial' );

    ob_start();
    ?>
    <style>
    #wpadminbar{display:none!important;}
    html{margin-top:0!important;}
    header,#masthead,.site-header,.main-header,#header,
    .elementor-location-header,.ast-main-header-wrap,#site-header,
    .fusion-header-wrapper,.header-wrap,.nav-primary,
    div[data-elementor-type="header"]{display:none!important;}
    .page-template-default .site-content,.site-main,#content,#page{margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;}
    footer.site-footer,.site-footer,#colophon,#footer,
    .footer-area,.ast-footer-overlay,.footer-widgets-area,.footer-bar,
    div[data-elementor-type="footer"],.elementor-location-footer{display:none!important;}

    .pc-nav{background:rgba(255,255,255,0.94);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid #e4e7ef;position:sticky;top:0;z-index:200;box-shadow:0 1px 0 #e4e7ef,0 2px 12px rgba(0,0,0,.04);}
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
    @media(max-width:768px){.pc-nav-links{display:none;}}

    *{box-sizing:border-box;margin:0;padding:0;}
    .wd-page{font-family:'DM Sans',system-ui,sans-serif;background:#f4f6fb;min-height:100vh;padding:36px 28px 64px;max-width:1200px;margin:0 auto;}
    .wd-welcome{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:16px;}
    .wd-welcome-title{font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-.4px;}
    .wd-welcome-sub{font-size:13px;color:#94a3b8;margin-top:4px;}
    .wd-welcome-actions{display:flex;gap:10px;align-items:center;}
    .wd-btn-new{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;background:linear-gradient(135deg,#4338ca,#6366f1);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:transform .18s,box-shadow .18s,opacity .15s;box-shadow:0 4px 14px rgba(67,56,202,.30);letter-spacing:-.1px;}
    .wd-btn-new:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(67,56,202,.40);}
    .wd-btn-new svg{width:16px;height:16px;flex-shrink:0;}
    .wd-card{background:#fff;border:1px solid #e8edf5;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.05),0 4px 16px rgba(0,0,0,.04);}
    .wd-card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
    .wd-card-head h2{font-size:17px;font-weight:700;color:#0f172a;}
    .wd-view-all{font-size:13px;color:#4338ca;text-decoration:none;font-weight:600;}
    .wd-view-all:hover{text-decoration:underline;}
    .wd-schedule-list{display:flex;flex-direction:column;gap:12px;}
    .wd-sched-item{display:flex;align-items:center;gap:16px;background:#f8fafc;border:1px solid #e8edf5;border-radius:12px;padding:14px 18px;}
    a.wd-sched-item{text-decoration:none;transition:background .18s,border-color .18s,box-shadow .18s,transform .15s;cursor:pointer;}
    a.wd-sched-item:hover{background:#eef2ff;border-color:#c7d2fe;box-shadow:0 4px 16px rgba(67,56,202,.10);transform:translateY(-1px);}
    a.wd-sched-item:hover .wd-sched-info strong{color:#4338ca;}
    .wd-sched-time{min-width:80px;text-align:center;}
    .wd-sched-time strong{display:block;font-size:15px;font-weight:800;color:#0f172a;}
    .wd-sched-time span{font-size:11px;color:#94a3b8;font-weight:500;}
    .wd-sched-info{flex:1;min-width:0;}
    .wd-sched-info strong{display:block;font-size:14px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .wd-sched-info span{font-size:12px;color:#64748b;}
    .wd-btn-join{padding:8px 16px;background:#0f172a;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;transition:opacity .15s;white-space:nowrap;}
    .wd-btn-join:hover{opacity:.8;}
    .wd-empty{color:#94a3b8;font-size:13px;text-align:center;padding:30px 0;}
    .wd-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;}
    @media(max-width:760px){.wd-row{grid-template-columns:1fr;}}
    .wd-top-row{margin-bottom:20px;}
    .wd-file-list{display:flex;flex-direction:column;gap:10px;}
    .wd-file-item{display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f8fafc;border:1px solid #e8edf5;border-radius:10px;}
    .wd-file-icon{width:36px;height:36px;border-radius:8px;background:#eef2ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .wd-file-icon svg{width:18px;height:18px;color:#4338ca;}
    .wd-file-info{flex:1;min-width:0;}
    .wd-file-name{font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .wd-file-meta{font-size:11px;color:#94a3b8;margin-top:2px;}
    .wd-file-dl{width:28px;height:28px;border-radius:6px;background:#f1f5f9;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:background .15s;}
    .wd-file-dl:hover{background:#e0e7ff;}
    .wd-file-dl svg{width:14px;height:14px;color:#64748b;}
    .wd-txn-list{display:flex;flex-direction:column;gap:10px;}
    .wd-txn-item{display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f8fafc;border:1px solid #e8edf5;border-radius:10px;}
    .wd-txn-icon{width:36px;height:36px;border-radius:8px;background:#ecfdf5;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .wd-txn-icon svg{width:16px;height:16px;color:#059669;}
    .wd-txn-info{flex:1;min-width:0;}
    .wd-txn-name{font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .wd-txn-date{font-size:11px;color:#94a3b8;margin-top:2px;}
    .wd-txn-amount{font-size:14px;font-weight:800;color:#059669;white-space:nowrap;}
    .wd-txn-amount.refunded{color:#ef4444;}

    /* --------------------------------------------
       NEW CONSULTATION DRAWER / OVERLAY
       -------------------------------------------- */
    .wcd-overlay{
        position:fixed;inset:0;background:rgba(10,14,30,0.55);backdrop-filter:blur(4px);
        z-index:9000;opacity:0;pointer-events:none;transition:opacity .28s ease;
    }
    .wcd-overlay.open{opacity:1;pointer-events:all;}

    .wcd-drawer{
        position:fixed;top:0;right:0;height:100vh;width:min(540px,100vw);
        background:#fff;z-index:9001;
        box-shadow:-8px 0 40px rgba(0,0,0,.14);
        transform:translateX(100%);transition:transform .32s cubic-bezier(.4,0,.2,1);
        display:flex;flex-direction:column;overflow:hidden;
    }
    .wcd-drawer.open{transform:translateX(0);}

    .wcd-drawer-header{
        display:flex;align-items:center;justify-content:space-between;
        padding:22px 28px 18px;
        border-bottom:1px solid #e8edf5;
        background:linear-gradient(135deg,#4338ca 0%,#6366f1 100%);
        flex-shrink:0;
    }
    .wcd-drawer-title{font-size:18px;font-weight:800;color:#fff;letter-spacing:-.3px;}
    .wcd-drawer-sub{font-size:12px;color:rgba(255,255,255,.7);margin-top:2px;}
    .wcd-close-btn{
        width:34px;height:34px;border-radius:8px;
        background:rgba(255,255,255,.18);border:none;cursor:pointer;
        display:flex;align-items:center;justify-content:center;
        transition:background .15s;color:#fff;flex-shrink:0;
    }
    .wcd-close-btn:hover{background:rgba(255,255,255,.30);}
    .wcd-close-btn svg{width:18px;height:18px;}

    /* Step indicator */
    .wcd-steps{
        display:flex;align-items:center;gap:0;
        padding:18px 28px 14px;border-bottom:1px solid #f1f5f9;
        flex-shrink:0;background:#fafbff;
    }
    .wcd-step{
        display:flex;align-items:center;gap:8px;flex:1;
        font-size:11px;font-weight:600;color:#94a3b8;letter-spacing:.2px;
        text-transform:uppercase;
    }
    .wcd-step.active{color:#4338ca;}
    .wcd-step.done{color:#059669;}
    .wcd-step-num{
        width:22px;height:22px;border-radius:50%;background:#e2e8f0;
        display:flex;align-items:center;justify-content:center;
        font-size:11px;font-weight:800;color:#64748b;flex-shrink:0;transition:background .2s,color .2s;
    }
    .wcd-step.active .wcd-step-num{background:#4338ca;color:#fff;}
    .wcd-step.done .wcd-step-num{background:#059669;color:#fff;}
    .wcd-step-divider{width:24px;height:1px;background:#e2e8f0;flex-shrink:0;}

    /* Form body */
    .wcd-body{flex:1;overflow-y:auto;padding:24px 28px 10px;}

    .wcd-section{margin-bottom:24px;}
    .wcd-section-label{
        font-size:11px;font-weight:700;color:#64748b;letter-spacing:.6px;
        text-transform:uppercase;margin-bottom:12px;display:flex;align-items:center;gap:6px;
    }
    .wcd-section-label::after{content:'';flex:1;height:1px;background:#f1f5f9;}

    .wcd-field{margin-bottom:14px;}
    .wcd-label{
        display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;
    }
    .wcd-label sup{color:#ef4444;margin-left:1px;}
    .wcd-input,.wcd-select,.wcd-textarea{
        width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:9px;
        font-family:'DM Sans',system-ui,sans-serif;font-size:14px;color:#0f172a;
        background:#fff;outline:none;transition:border-color .18s,box-shadow .18s;
        appearance:none;-webkit-appearance:none;
    }
    .wcd-input:focus,.wcd-select:focus,.wcd-textarea:focus{
        border-color:#4338ca;box-shadow:0 0 0 3px rgba(67,56,202,.10);
    }
    .wcd-input::placeholder,.wcd-textarea::placeholder{color:#cbd5e1;font-size:13px;}
    .wcd-select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;}
    .wcd-textarea{resize:vertical;min-height:72px;line-height:1.55;}
    .wcd-row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .wcd-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;}

    /* Date row with + button */
    .wcd-dates-list{display:flex;flex-direction:column;gap:8px;}
    .wcd-date-row{display:flex;align-items:center;gap:8px;}
    .wcd-date-row .wcd-input{flex:1;}
    .wcd-remove-date{
        width:30px;height:30px;border:none;border-radius:7px;
        background:#fef2f2;color:#ef4444;font-size:18px;line-height:1;
        cursor:pointer;display:flex;align-items:center;justify-content:center;
        transition:background .15s;flex-shrink:0;
    }
    .wcd-remove-date:hover{background:#fee2e2;}
    .wcd-add-date{
        display:inline-flex;align-items:center;gap:6px;margin-top:8px;
        padding:7px 14px;border:1.5px dashed #c7d2fe;border-radius:8px;
        font-size:12px;font-weight:600;color:#4338ca;background:none;cursor:pointer;
        transition:background .15s,border-color .15s;
    }
    .wcd-add-date:hover{background:#eef2ff;border-color:#4338ca;}

    /* File upload zone */
    .wcd-upload-zone{
        border:2px dashed #c7d2fe;border-radius:12px;padding:24px 20px;
        text-align:center;cursor:pointer;transition:border-color .18s,background .18s;
        background:#fafbff;position:relative;
    }
    .wcd-upload-zone:hover,.wcd-upload-zone.drag-over{border-color:#4338ca;background:#eef2ff;}
    .wcd-upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
    .wcd-upload-icon{width:40px;height:40px;border-radius:10px;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}
    .wcd-upload-icon svg{width:20px;height:20px;color:#4338ca;}
    .wcd-upload-text{font-size:13px;font-weight:600;color:#374151;}
    .wcd-upload-hint{font-size:11px;color:#94a3b8;margin-top:4px;}
    .wcd-file-chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px;}
    .wcd-file-chip{
        display:inline-flex;align-items:center;gap:5px;
        background:#eef2ff;border-radius:6px;padding:4px 10px 4px 8px;
        font-size:11px;font-weight:600;color:#4338ca;
    }
    .wcd-file-chip svg{width:11px;height:11px;}
    .wcd-chip-remove{
        width:14px;height:14px;border-radius:50%;background:none;border:none;
        color:#94a3b8;cursor:pointer;display:inline-flex;align-items:center;
        justify-content:center;font-size:13px;line-height:1;transition:color .12s;padding:0;
    }
    .wcd-chip-remove:hover{color:#ef4444;}
    .wcd-upload-progress{
        height:3px;background:#e0e7ff;border-radius:2px;margin-top:10px;overflow:hidden;display:none;
    }
    .wcd-upload-progress-bar{height:100%;background:#4338ca;width:0%;transition:width .3s;}

    /* Input helpers */
    .wcd-input-prefix{
        display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;
        transition:border-color .18s,box-shadow .18s;
    }
    .wcd-input-prefix:focus-within{border-color:#4338ca;box-shadow:0 0 0 3px rgba(67,56,202,.10);}
    .wcd-prefix-label{
        padding:0 12px;background:#f8fafc;border-right:1.5px solid #e2e8f0;
        font-size:13px;font-weight:700;color:#64748b;white-space:nowrap;
        display:flex;align-items:center;align-self:stretch;
    }
    .wcd-input-prefix .wcd-input{border:none!important;box-shadow:none!important;border-radius:0!important;}

    /* Footer */
    .wcd-footer{
        padding:18px 28px;border-top:1px solid #e8edf5;
        display:flex;justify-content:space-between;align-items:center;
        flex-shrink:0;background:#fafbff;gap:12px;
    }
    .wcd-btn-cancel{
        padding:10px 20px;border:1.5px solid #e2e8f0;border-radius:9px;
        font-family:'DM Sans',system-ui,sans-serif;font-size:14px;font-weight:600;
        color:#64748b;background:#fff;cursor:pointer;transition:border-color .15s,color .15s;
    }
    .wcd-btn-cancel:hover{border-color:#94a3b8;color:#374151;}
    .wcd-btn-submit{
        display:inline-flex;align-items:center;gap:8px;
        padding:10px 26px;background:linear-gradient(135deg,#4338ca,#6366f1);
        color:#fff;border:none;border-radius:9px;
        font-family:'DM Sans',system-ui,sans-serif;font-size:14px;font-weight:700;
        cursor:pointer;transition:transform .18s,box-shadow .18s,opacity .15s;
        box-shadow:0 4px 12px rgba(67,56,202,.28);
    }
    .wcd-btn-submit:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 6px 18px rgba(67,56,202,.38);}
    .wcd-btn-submit:disabled{opacity:.6;cursor:not-allowed;}
    .wcd-btn-submit .wcd-spin{
        width:15px;height:15px;border:2px solid rgba(255,255,255,.35);
        border-top-color:#fff;border-radius:50%;
        animation:wcd-spin .7s linear infinite;display:none;
    }
    @keyframes wcd-spin{to{transform:rotate(360deg);}}

    /* Toast */
    .wcd-toast{
        position:fixed;bottom:28px;right:28px;z-index:9999;
        padding:14px 20px;border-radius:12px;font-size:13px;font-weight:600;
        color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.16);
        transform:translateY(20px);opacity:0;transition:transform .28s,opacity .28s;
        display:flex;align-items:center;gap:9px;pointer-events:none;max-width:340px;
    }
    .wcd-toast.show{transform:translateY(0);opacity:1;}
    .wcd-toast.success{background:#059669;}
    .wcd-toast.error{background:#ef4444;}
    .wcd-toast svg{width:18px;height:18px;flex-shrink:0;}

    /* Error hints */
    .wcd-error-hint{font-size:11px;color:#ef4444;margin-top:4px;display:none;}
    .wcd-field.has-error .wcd-input,
    .wcd-field.has-error .wcd-select{border-color:#ef4444;}
    .wcd-field.has-error .wcd-error-hint{display:block;}
    </style>

    <!-- --- NAVBAR --- -->
    <nav class="pc-nav">
        <div class="pc-nav-inner">
            <a href="<?php echo esc_url( home_url() ); ?>" class="pc-nav-logo">LOGO</a>
            <div class="pc-nav-links">
                <a href="<?php echo esc_url( $url_dashboard ); ?>" class="active">Dashboard</a>
                <a href="<?php echo esc_url( $url_calendar ); ?>">Calendar</a>
                <a href="<?php echo esc_url( $url_webinars ); ?>">Webinars</a>
                <a href="<?php echo esc_url( $url_transactions ); ?>">Transactions</a>
            </div>
            <div class="pc-nav-right">
                <img src="<?php echo esc_url( $nav_avatar ); ?>" alt="Profile">
                <span class="pc-nav-username">Hi, <?php echo esc_html( $user_name ); ?></span>
                <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="pc-nav-btn">Logout</a>
            </div>
        </div>
    </nav>

    <!-- --- MAIN PAGE --- -->
    <div class="wd-page">
        <div class="wd-welcome">
            <div>
                <div class="wd-welcome-title">Welcome back, <?php echo esc_html( $user_name ); ?>!</div>
                <!-- FIX 5: use current_time('timestamp') for IST date display -->
                <div class="wd-welcome-sub"><?php echo date( 'l, F j, Y', current_time( 'timestamp' ) ); ?></div>
            </div>
            <div class="wd-welcome-actions">
                <button class="wd-btn-new" id="wcd-open-btn" type="button">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Consultation
                </button>
            </div>
        </div>

        <!-- Upcoming Schedule -->
        <div class="wd-card wd-top-row">
            <div class="wd-card-head">
                <!-- FIX: month heading uses IST timestamp -->
                <h2>Upcoming Schedule &mdash; <?php echo date( 'F Y', current_time( 'timestamp' ) ); ?></h2>
                <a href="<?php echo esc_url( $url_webinars ); ?>" class="wd-view-all">View All &rarr;</a>
            </div>
            <div class="wd-schedule-list">
                <?php if ( ! empty( $upcoming_webinars ) ) : ?>
                    <?php foreach ( $upcoming_webinars as $w ) :
                        // FIX 2: Amelia stores times in UTC; add 19800s (5h30m) to convert to IST
                        $start_utc  = strtotime( $w['periodStart'] );
                        $start      = $start_utc + 19800;
                        $zoom_raw   = $w['zoomMeeting'];
                        $zoom       = is_string( $zoom_raw ) ? json_decode( $zoom_raw, true ) : (array) $zoom_raw;
                        $join_url   = $zoom['joinUrl'] ?? '#';
                        $today_ist  = date( 'Y-m-d', current_time( 'timestamp' ) );
                        $day_label  = ( date( 'Y-m-d', $start ) === $today_ist ) ? 'Today' : date( 'D, M j', $start );
                        $info_url   = home_url( '/webinar-info/?event-id=' . intval( $w['id'] ) );
                    ?>
                    <a href="<?php echo esc_url( $info_url ); ?>" class="wd-sched-item">
                        <div class="wd-sched-time">
                            <strong><?php echo date( 'h:i A', $start ); ?></strong>
                            <span><?php echo esc_html( $day_label ); ?></span>
                        </div>
                        <div class="wd-sched-info">
                            <strong><?php echo esc_html( $w['name'] ); ?></strong>
                            <span>Webinar &nbsp;&middot;&nbsp; Virtual</span>
                        </div>
                        <a href="<?php echo esc_url( $join_url ); ?>" target="_blank" class="wd-btn-join" onclick="event.stopPropagation();">Join</a>
                    </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="wd-empty">No upcoming webinars this month.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom row: Materials + Transactions -->
        <div class="wd-row">

            <!-- Materials panel -->
            <div class="wd-card">
                <div class="wd-card-head">
                    <h2>My Webinar Materials</h2>
                    <a href="<?php echo esc_url( home_url( '/upload_material' ) ); ?>" class="wd-view-all">Upload New &rarr;</a>
                </div>
                <div class="wd-file-list">
                <?php if ( ! empty( $materials ) ) : ?>
                    <?php foreach ( $materials as $mat ) :
                        $ext              = strtolower( pathinfo( $mat->title, PATHINFO_EXTENSION ) );
                        [ $ic_bg, $ic_clr ] = $ext_map[ $ext ] ?? [ '#eef2ff', '#4338ca' ];
                        $type_icon_data   = $type_icons[ $mat->material_type ] ?? ['letter'=>'F','color'=>'#64748b','bg'=>'#f1f5f9'];
                        $type_letter      = $type_icon_data['letter'];
                        $type_icon_color  = $type_icon_data['color'];
                        $type_bg          = $type_icon_data['bg'];
                        $uploaded         = human_time_diff( strtotime( $mat->uploaded_at ), current_time( 'timestamp' ) ) . ' ago';
                        $session_lbl      = ! empty( $mat->event_name )
                                            ? $mat->event_name . ' &middot; ' . date_i18n( 'M j, Y', strtotime( $mat->event_start ) + 19800 )
                                            : 'Webinar #' . $mat->event_id;
                    ?>
                    <div class="wd-file-item">
                        <div class="wd-file-icon" style="background:<?php echo $ic_bg; ?>;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?php echo $ic_clr; ?>" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div class="wd-file-info" style="flex:1;min-width:0;">
                            <div class="wd-file-name"><?php echo esc_html( $mat->title ); ?></div>
                            <div style="display:flex;align-items:center;gap:5px;margin-top:3px;flex-wrap:wrap;">
                                <span style="display:inline-flex;align-items:center;gap:5px;background:<?php echo esc_attr($type_bg); ?>;border-radius:5px;padding:2px 8px;font-size:10px;font-weight:700;color:<?php echo esc_attr($type_icon_color); ?>;">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;background:<?php echo esc_attr($type_icon_color); ?>;color:#fff;border-radius:3px;font-size:9px;font-weight:800;flex-shrink:0;"><?php echo esc_html($type_letter); ?></span>
                                    <?php echo esc_html( $mat->material_type ); ?>
                                </span>
                            </div>
                            <div class="wd-file-meta" style="margin-top:3px;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?php echo $session_lbl; ?>
                                &nbsp;&middot;&nbsp;
                                <?php echo esc_html( $uploaded ); ?>
                            </div>
                        </div>
                        <a href="<?php echo esc_url( $mat->file_url ); ?>" target="_blank" download class="wd-file-dl" title="Download">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="wd-empty">No webinar materials uploaded yet.</div>
                <?php endif; ?>
                </div>
            </div>

            <!-- Transactions panel -->
            <div class="wd-card">
                <div class="wd-card-head">
                    <h2>Recent Transactions</h2>
                    <a href="<?php echo esc_url( $url_transactions ); ?>" class="wd-view-all">View All &rarr;</a>
                </div>
                <div class="wd-txn-list">
                    <?php if ( ! empty( $transactions ) ) : ?>
                        <?php foreach ( $transactions as $t ) :
                            $amount    = (float) ( $t['amount'] ?? 0 );
                            $is_refund = strtolower( $t['status'] ?? '' ) === 'refunded';
                            $evt_name  = $t['bookableName'] ?? 'Webinar';
                            $txn_date  = ! empty( $t['dateTime'] ) ? date( 'M j, Y', strtotime( $t['dateTime'] ) + 19800 ) : '-';
                        ?>
                        <div class="wd-txn-item">
                            <div class="wd-txn-icon" style="<?php echo $is_refund ? 'background:#fef2f2;' : ''; ?>">
                                <svg fill="none" viewBox="0 0 24 24" stroke="<?php echo $is_refund ? '#ef4444' : '#059669'; ?>" stroke-width="2.5">
                                    <?php if ( $is_refund ) : ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                    <?php else : ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    <?php endif; ?>
                                </svg>
                            </div>
                            <div class="wd-txn-info">
                                <div class="wd-txn-name"><?php echo esc_html( $evt_name ); ?></div>
                                <div class="wd-txn-date"><?php echo esc_html( $txn_date ); ?></div>
                            </div>
                            <div class="wd-txn-amount <?php echo $is_refund ? 'refunded' : ''; ?>">
                                <?php echo $is_refund ? '-' : '+'; ?>Rs <?php echo number_format( $amount, 2 ); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="wd-empty">No transactions yet.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /.wd-row -->
    </div><!-- /.wd-page -->

    <!-- ---------------------------------------------------
         NEW CONSULTATION DRAWER
         --------------------------------------------------- -->
    <div class="wcd-overlay" id="wcd-overlay"></div>

    <div class="wcd-drawer" id="wcd-drawer" role="dialog" aria-modal="true" aria-labelledby="wcd-drawer-title">

        <!-- Header -->
        <div class="wcd-drawer-header">
            <div>
                <div class="wcd-drawer-title" id="wcd-drawer-title">New Consultation / Webinar</div>
                <div class="wcd-drawer-sub">Create a new event via Amelia</div>
            </div>
            <button class="wcd-close-btn" id="wcd-close-btn" type="button" aria-label="Close">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Step indicator -->
        <div class="wcd-steps">
            <div class="wcd-step active" id="wcd-step-1">
                <div class="wcd-step-num">1</div>
                <span>Details</span>
            </div>
            <div class="wcd-step-divider"></div>
            <div class="wcd-step" id="wcd-step-2">
                <div class="wcd-step-num">2</div>
                <span>Schedule</span>
            </div>
            <div class="wcd-step-divider"></div>
            <div class="wcd-step" id="wcd-step-3">
                <div class="wcd-step-num">3</div>
                <span>Materials</span>
            </div>
        </div>

        <!-- Form body -->
        <div class="wcd-body">
            <form id="wcd-form" novalidate>

                <!-- --- STEP 1: Basic details --- -->
                <div id="wcd-panel-1">
                    <div class="wcd-section">
                        <div class="wcd-section-label">Basic Information</div>

                        <div class="wcd-field" id="wcd-f-name">
                            <label class="wcd-label" for="wcd-name">Event / Webinar Name <sup>*</sup></label>
                            <input class="wcd-input" id="wcd-name" name="name" type="text"
                                   placeholder="e.g. Advanced Cardiology Masterclass" autocomplete="off">
                            <div class="wcd-error-hint">Please enter a name for this event.</div>
                        </div>

                        <div class="wcd-row-2">
                            <div class="wcd-field" id="wcd-f-price">
                                <label class="wcd-label" for="wcd-price">Price (?) <sup>*</sup></label>
                                <div class="wcd-input-prefix">
                                    <span class="wcd-prefix-label">?</span>
                                    <input class="wcd-input" id="wcd-price" name="price" type="number"
                                           min="0" step="1" placeholder="999">
                                </div>
                                <div class="wcd-error-hint">Enter a valid price.</div>
                            </div>
                            <div class="wcd-field" id="wcd-f-spots">
                                <label class="wcd-label" for="wcd-spots">Max Spots <sup>*</sup></label>
                                <input class="wcd-input" id="wcd-spots" name="spots" type="number"
                                       min="1" step="1" placeholder="50">
                                <div class="wcd-error-hint">Enter max number of attendees.</div>
                            </div>
                        </div>

                        <div class="wcd-field">
                            <label class="wcd-label" for="wcd-desc">Description</label>
                            <textarea class="wcd-textarea" id="wcd-desc" name="description"
                                      placeholder="Brief overview of this webinar / consultation…"></textarea>
                        </div>
                    </div>
                </div>

                <!-- --- STEP 2: Schedule --- -->
                <div id="wcd-panel-2" style="display:none;">
                    <div class="wcd-section">
                        <div class="wcd-section-label">Session Dates &amp; Time</div>

                        <div class="wcd-field" id="wcd-f-times">
                            <div class="wcd-row-2" style="margin-bottom:12px;">
                                <div>
                                    <label class="wcd-label" for="wcd-start-time">Start Time <sup>*</sup></label>
                                    <input class="wcd-input" id="wcd-start-time" name="startTime" type="time" value="10:00">
                                </div>
                                <div>
                                    <label class="wcd-label" for="wcd-end-time">End Time <sup>*</sup></label>
                                    <input class="wcd-input" id="wcd-end-time" name="endTime" type="time" value="11:00">
                                </div>
                            </div>
                            <div class="wcd-error-hint" id="wcd-time-err">End time must be after start time.</div>
                        </div>

                        <div class="wcd-field">
                            <label class="wcd-label">Session Dates <sup>*</sup><span style="font-weight:400;color:#94a3b8;margin-left:6px;">(add one or more)</span></label>
                            <div class="wcd-dates-list" id="wcd-dates-list">
                                <div class="wcd-date-row">
                                    <input class="wcd-input wcd-date-input" type="date" name="dates[]"
                                           min="<?php echo date( 'Y-m-d', current_time( 'timestamp' ) ); ?>">
                                    <button type="button" class="wcd-remove-date" title="Remove" style="display:none;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="wcd-add-date" id="wcd-add-date-btn">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
                                Add another date
                            </button>
                            <div class="wcd-error-hint" id="wcd-dates-err" style="display:none;margin-top:6px;">Please select at least one date.</div>
                        </div>

                        <div class="wcd-field">
                            <label class="wcd-label">Zoom Host</label>
                            <input class="wcd-input" type="text" value="<?php echo esc_attr( $zoom_user_email ); ?>" readonly
                                   style="background:#f8fafc;color:#64748b;cursor:default;">
                            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Zoom meetings will be created under this account automatically.</div>
                        </div>
                    </div>
                </div>

                <!-- --- STEP 3: Pre-webinar materials --- -->
                <div id="wcd-panel-3" style="display:none;">
                    <div class="wcd-section">
                        <div class="wcd-section-label">Pre-Webinar Materials</div>
                        <p style="font-size:12px;color:#64748b;margin-bottom:16px;line-height:1.6;">
                            Upload any materials attendees should receive before the session (slides, PDFs, reading lists, etc.).
                            These will be saved to your materials library and linked to this event.
                        </p>

                        <div class="wcd-field">
                            <label class="wcd-label" for="wcd-mat-type">Material Type</label>
                            <select class="wcd-select" id="wcd-mat-type">
                                <option value="Presentation Slides">Presentation Slides</option>
                                <option value="Handouts / PDF Notes" selected>Handouts / PDF Notes</option>
                                <option value="Assignment / Homework">Assignment / Homework</option>
                                <option value="Reference Books / Articles">Reference Books / Articles</option>
                                <option value="Practice Sheets">Practice Sheets</option>
                                <option value="Exam Prep Materials">Exam Prep Materials</option>
                            </select>
                        </div>

                        <div class="wcd-upload-zone" id="wcd-drop-zone">
                            <input type="file" id="wcd-file-input" multiple
                                   accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.mov,.jpg,.jpeg,.png">
                            <div class="wcd-upload-icon">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                </svg>
                            </div>
                            <div class="wcd-upload-text">Drop files here or click to browse</div>
                            <div class="wcd-upload-hint">PDF, PPT, DOCX, MP4, images — up to 64 MB each</div>
                        </div>
                        <div class="wcd-upload-progress" id="wcd-upload-progress">
                            <div class="wcd-upload-progress-bar" id="wcd-upload-bar"></div>
                        </div>
                        <div class="wcd-file-chips" id="wcd-file-chips"></div>
                    </div>
                </div>

            </form><!-- /#wcd-form -->
        </div><!-- /.wcd-body -->

        <!-- Footer nav -->
        <div class="wcd-footer">
            <button type="button" class="wcd-btn-cancel" id="wcd-prev-btn" style="display:none;">
                ? Back
            </button>
            <button type="button" class="wcd-btn-cancel" id="wcd-cancel-btn">Cancel</button>
            <button type="button" class="wcd-btn-submit" id="wcd-next-btn">
                <span id="wcd-next-label">Continue ?</span>
                <span class="wcd-spin" id="wcd-spin"></span>
            </button>
        </div>
    </div><!-- /.wcd-drawer -->

    <!-- Toast notification -->
    <div class="wcd-toast" id="wcd-toast" role="alert" aria-live="polite"></div>

    <?php
    // FIX 4: use current_time('timestamp') for IST today date sent to JS
    $wcd_amelia_url   = esc_js( $amelia_api_base );
    $wcd_amelia_key   = esc_js( $amelia_api_key );
    $wcd_zoom_email   = esc_js( $zoom_user_email );
    $wcd_nonce        = esc_js( $upload_nonce );
    $wcd_ajax_url     = esc_js( admin_url( 'admin-ajax.php' ) );
    $wcd_provider_id  = (int) $provider_id;
    $wcd_today        = date( 'Y-m-d', current_time( 'timestamp' ) ); // FIX 4
    ?>
    <script>
    window._wcdCfg = {
        ameliaUrl:   '<?php echo $wcd_amelia_url; ?>',
        ameliaKey:   '<?php echo $wcd_amelia_key; ?>',
        zoomEmail:   '<?php echo $wcd_zoom_email; ?>',
        uploadNonce: '<?php echo $wcd_nonce; ?>',
        ajaxUrl:     '<?php echo $wcd_ajax_url; ?>',
        providerId:  <?php echo $wcd_provider_id; ?>,
        today:       '<?php echo $wcd_today; ?>'
    };
    </script>
    <?php
    if ( ! has_action( 'wp_footer', 'wcd_output_drawer_script' ) ) {
        add_action( 'wp_footer', 'wcd_output_drawer_script', 99 );
    }
    if ( ! function_exists( 'wcd_output_drawer_script' ) ) :
    function wcd_output_drawer_script() {
        if ( ! is_user_logged_in() ) return;
        ?>
<script>
(function(){
var _cfg = window._wcdCfg||{};
if (!_cfg.ameliaUrl) return; // not on this page
var AMELIA_URL  =_cfg.ameliaUrl;
var AMELIA_KEY  =_cfg.ameliaKey;
var ZOOM_EMAIL  =_cfg.zoomEmail;
var UPLOAD_NONCE=_cfg.uploadNonce;
var AJAX_URL    =_cfg.ajaxUrl;
var PROVIDER_ID =_cfg.providerId||0;
var TODAY       =_cfg.today||new Date().toISOString().slice(0,10);

var currentStep=1, pendingFiles=[], createdEventId=null;

var overlay  =document.getElementById('wcd-overlay');
var drawer   =document.getElementById('wcd-drawer');
var openBtn  =document.getElementById('wcd-open-btn');
var closeBtn =document.getElementById('wcd-close-btn');
var cancelBtn=document.getElementById('wcd-cancel-btn');
var prevBtn  =document.getElementById('wcd-prev-btn');
var nextBtn  =document.getElementById('wcd-next-btn');
var nextLabel=document.getElementById('wcd-next-label');
var spin     =document.getElementById('wcd-spin');
var toast    =document.getElementById('wcd-toast');
var datesList=document.getElementById('wcd-dates-list');
var addDateBtn=document.getElementById('wcd-add-date-btn');
var dropZone =document.getElementById('wcd-drop-zone');
var fileInput=document.getElementById('wcd-file-input');
var fileChips=document.getElementById('wcd-file-chips');
var progWrap =document.getElementById('wcd-upload-progress');
var progBar  =document.getElementById('wcd-upload-bar');

if (!openBtn||!overlay||!drawer) return;

function openDrawer(){overlay.classList.add('open');drawer.classList.add('open');document.body.style.overflow='hidden';}
function closeDrawer(){overlay.classList.remove('open');drawer.classList.remove('open');document.body.style.overflow='';resetForm();}
openBtn.addEventListener('click',openDrawer);
closeBtn.addEventListener('click',closeDrawer);
cancelBtn.addEventListener('click',closeDrawer);
overlay.addEventListener('click',function(e){if(e.target===overlay)closeDrawer();});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&drawer.classList.contains('open'))closeDrawer();});

function goToStep(n){
    [1,2,3].forEach(function(i){
        var p=document.getElementById('wcd-panel-'+i);
        var s=document.getElementById('wcd-step-'+i);
        if(p) p.style.display=(i===n)?'block':'none';
        if(s){s.classList.remove('active','done');if(i<n)s.classList.add('done');if(i===n)s.classList.add('active');}
    });
    currentStep=n;
    prevBtn.style.display=(n>1)?'inline-flex':'none';
    cancelBtn.style.display=(n>1)?'none':'inline-flex';
    nextLabel.textContent=(n===3)?'Create Event':'Continue \u2192';
}
prevBtn.addEventListener('click',function(){goToStep(currentStep-1);});
nextBtn.addEventListener('click',function(){
    if(currentStep===1){if(validateStep1())goToStep(2);}
    else if(currentStep===2){if(validateStep2())goToStep(3);}
    else if(currentStep===3){handleSubmit();}
});

function setErr(id,on){var f=document.getElementById(id);if(f)f.classList[on?'add':'remove']('has-error');}
function validateStep1(){
    var ok=true;
    if(!document.getElementById('wcd-name').value.trim()){setErr('wcd-f-name',true);ok=false;}else setErr('wcd-f-name',false);
    var p=document.getElementById('wcd-price').value;
    if(p===''||isNaN(+p)||+p<0){setErr('wcd-f-price',true);ok=false;}else setErr('wcd-f-price',false);
    var s=document.getElementById('wcd-spots').value;
    if(!s||parseInt(s)<1){setErr('wcd-f-spots',true);ok=false;}else setErr('wcd-f-spots',false);
    return ok;
}
function validateStep2(){
    var ok=true;
    var st=document.getElementById('wcd-start-time').value;
    var et=document.getElementById('wcd-end-time').value;
    var te=document.getElementById('wcd-time-err');
    var de=document.getElementById('wcd-dates-err');
    if(!st||!et||et<=st){document.getElementById('wcd-f-times').classList.add('has-error');te.style.display='block';ok=false;}
    else{document.getElementById('wcd-f-times').classList.remove('has-error');te.style.display='none';}
    var hasDate=false;
    datesList.querySelectorAll('.wcd-date-input').forEach(function(d){if(d.value)hasDate=true;});
    de.style.display=hasDate?'none':'block';
    if(!hasDate)ok=false;
    return ok;
}

addDateBtn.addEventListener('click',function(){
    var row=document.createElement('div');row.className='wcd-date-row';
    var inp=document.createElement('input');inp.className='wcd-input wcd-date-input';inp.type='date';inp.name='dates[]';inp.min=TODAY;
    var rm=document.createElement('button');rm.type='button';rm.className='wcd-remove-date';rm.title='Remove';
    rm.innerHTML='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg>';
    rm.addEventListener('click',function(){row.remove();updateRemoveBtns();});
    row.appendChild(inp);row.appendChild(rm);
    datesList.appendChild(row);updateRemoveBtns();inp.focus();
});
function updateRemoveBtns(){
    var rows=datesList.querySelectorAll('.wcd-date-row');
    rows.forEach(function(r){var b=r.querySelector('.wcd-remove-date');if(b)b.style.display=rows.length>1?'flex':'none';});
}

dropZone.addEventListener('dragover',function(e){e.preventDefault();dropZone.classList.add('drag-over');});
dropZone.addEventListener('dragleave',function(){dropZone.classList.remove('drag-over');});
dropZone.addEventListener('drop',function(e){e.preventDefault();dropZone.classList.remove('drag-over');addFiles(Array.from(e.dataTransfer.files));});
fileInput.addEventListener('change',function(){addFiles(Array.from(fileInput.files));fileInput.value='';});

function addFiles(files){
    files.forEach(function(f){
        var dup=pendingFiles.some(function(p){return p.file.name===f.name&&p.file.size===f.size;});
        if(!dup)pendingFiles.push({file:f});
    });
    renderChips();
}
function renderChips(){
    fileChips.innerHTML='';
    pendingFiles.forEach(function(pf,idx){
        var chip=document.createElement('div');chip.className='wcd-file-chip';
        chip.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>'+escHtml(pf.file.name)+'</span><button type="button" class="wcd-chip-remove" title="Remove">\xd7</button>';
        chip.querySelector('.wcd-chip-remove').addEventListener('click',function(){pendingFiles.splice(idx,1);renderChips();});
        fileChips.appendChild(chip);
    });
}

function escHtml(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function showToast(msg,type){
    var icon=type==='success'
        ?'<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        :'<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374l7.302-12.75c.866-1.5 3.032-1.5 3.898 0L20.303 16.126z"/></svg>';
    toast.innerHTML=icon+'<span>'+escHtml(msg)+'</span>';
    toast.className='wcd-toast '+(type||'success');
    toast.classList.add('show');
    setTimeout(function(){toast.classList.remove('show');},5000);
}
function setLoading(on){nextBtn.disabled=on;spin.style.display=on?'block':'none';}

function buildPeriods(){
    var st=document.getElementById('wcd-start-time').value;
    var et=document.getElementById('wcd-end-time').value;
    var periods=[];
    datesList.querySelectorAll('.wcd-date-input').forEach(function(d){
        if(d.value)periods.push({periodStart:d.value+' '+st+':00',periodEnd:d.value+' '+et+':00'});
    });
    return periods;
}

function handleSubmit(){
    setLoading(true);
    var name   =document.getElementById('wcd-name').value.trim();
    var price  =parseFloat(document.getElementById('wcd-price').value)||0;
    var spots  =parseInt(document.getElementById('wcd-spots').value)||0;
    var desc   =document.getElementById('wcd-desc').value.trim();
    var periods=buildPeriods();
    var payload={
        name:name,status:'approved',description:desc,price:price,maxCapacity:spots,
        bookingOpens:null,bookingCloses:null,recurringCycle:null,recurringOrder:null,
        recurringUntil:null,recurringInterval:null,fullPayment:false,
        organizerId:PROVIDER_ID||null,color:'#1788FB',show:true,
        customLocation:null,locationId:null,zoomUserId:ZOOM_EMAIL,
        periods:periods,tags:[],gallery:[],customFields:[],
        providers:PROVIDER_ID?[{id:PROVIDER_ID}]:[]
    };
    ameliaPost('/api/v1/events',payload,function(err,data){
        if(err){setLoading(false);console.error('[WCD] create err:',err);showToast('Failed: '+String(err).slice(0,120),'error');return;}
        var eventId=data&&data.event&&data.event.id;
        if(!eventId){setLoading(false);console.error('[WCD] no id:',JSON.stringify(data));showToast('No event ID returned. Check console.','error');return;}
        createdEventId=eventId;
        if(pendingFiles.length===0){setLoading(false);showToast('Webinar "'+name+'" created!','success');setTimeout(closeDrawer,1800);return;}
        uploadMaterials(eventId,function(uploadErr){
            setLoading(false);
            showToast(uploadErr?'Event created, but some files failed.':'Webinar "'+name+'" created with materials!',uploadErr?'error':'success');
            setTimeout(closeDrawer,2000);
        });
    });
}

function ameliaPost(endpoint,body,cb){
    var url=AMELIA_URL+'?action=wpamelia_api&call='+endpoint;
    fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Amelia':AMELIA_KEY},body:JSON.stringify(body)})
    .then(function(r){return r.json();})
    .then(function(json){
        console.log('[WCD] Amelia response:',JSON.stringify(json));
        if(!json){cb('Empty response',null);return;}
        function findEvt(o,d){
            if(!o||typeof o!=='object'||d>8)return null;
            if(o.id>0&&(o.name!==undefined||o.periods!==undefined||o.status!==undefined))return o;
            var k=Object.keys(o);
            for(var i=0;i<k.length;i++){var f=findEvt(o[k[i]],d+1);if(f)return f;}
            return null;
        }
        function findId(o,d){
            if(!o||typeof o!=='object'||d>8)return 0;
            if(typeof o.id==='number'&&o.id>0)return o.id;
            var k=Object.keys(o);
            for(var i=0;i<k.length;i++){var f=findId(o[k[i]],d+1);if(f)return f;}
            return 0;
        }
        var evt=findEvt(json,0);
        if(evt&&evt.id){cb(null,{event:evt});return;}
        var raw=JSON.stringify(json).toLowerCase();
        var isOk=raw.indexOf('successfully')!==-1||raw.indexOf('added')!==-1||raw.indexOf('created')!==-1;
        var id=findId(json,0);
        if(isOk&&id){console.log('[WCD] fallback id:',id);cb(null,{event:{id:id}});return;}
        cb((json.message)||(json.data&&json.data.message)||('Bad response: '+JSON.stringify(json).slice(0,200)),null);
    })
    .catch(function(e){cb(e.message||'Network error',null);});
}

function uploadMaterials(eventId,done){
    progWrap.style.display='block';
    var total=pendingFiles.length,finished=0,anyErr=false;
    var matType=document.getElementById('wcd-mat-type').value;
    pendingFiles.forEach(function(pf){
        var fd=new FormData();
        fd.append('action','wcd_upload_pre_material');
        fd.append('nonce',UPLOAD_NONCE);
        fd.append('event_id',eventId);
        fd.append('material_type',matType);
        fd.append('title',pf.file.name);
        fd.append('file',pf.file);
        fetch(AJAX_URL,{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(j){
            console.log('[WCD] upload:',pf.file.name,j);
            finished++;progBar.style.width=Math.round((finished/total)*100)+'%';
            if(!j||!j.success){console.error('[WCD] upload fail:',j&&j.data);anyErr=true;}
            if(finished===total)done(anyErr);
        })
        .catch(function(e){console.error('[WCD] upload err:',e);anyErr=true;finished++;progBar.style.width=Math.round((finished/total)*100)+'%';if(finished===total)done(true);});
    });
}

function resetForm(){
    document.getElementById('wcd-form').reset();
    pendingFiles=[];renderChips();
    progBar.style.width='0%';progWrap.style.display='none';
    datesList.innerHTML='<div class="wcd-date-row"><input class="wcd-input wcd-date-input" type="date" name="dates[]" min="'+TODAY+'"><button type="button" class="wcd-remove-date" title="Remove" style="display:none;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg></button></div>';
    ['wcd-f-name','wcd-f-price','wcd-f-spots','wcd-f-times'].forEach(function(id){var f=document.getElementById(id);if(f)f.classList.remove('has-error');});
    var de=document.getElementById('wcd-dates-err');if(de)de.style.display='none';
    var te=document.getElementById('wcd-time-err');if(te)te.style.display='none';
    setLoading(false);goToStep(1);
}

goToStep(1);
})();
</script>
    <?php
    } // end wcd_output_drawer_script
    endif; // end if !function_exists
    ?>
    <?php
    return ob_get_clean();
}

/* --------------------------------------------------------
   WP AJAX HANDLER — upload pre-webinar material to DB
   -------------------------------------------------------- */
add_action( 'wp_ajax_wcd_upload_pre_material', 'wcd_ajax_upload_pre_material' );
function wcd_ajax_upload_pre_material() {

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'msg' => 'Not logged in' ], 401 );
    }

    if ( ! check_ajax_referer( 'wcd_upload_prematerial', 'nonce', false ) ) {
        wp_send_json_error( [ 'msg' => 'Invalid nonce' ], 403 );
    }

    $user_id       = get_current_user_id();
    $event_id      = absint( $_POST['event_id']      ?? 0 );
    $material_type = sanitize_text_field( $_POST['material_type'] ?? 'Handouts / PDF Notes' );
    $title         = sanitize_text_field( $_POST['title'] ?? '' );

    if ( ! $event_id ) {
        wp_send_json_error( [ 'msg' => 'Missing event_id' ] );
    }
    if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['name'] ) ) {
        wp_send_json_error( [ 'msg' => 'No file received' ] );
    }

    $upload_err = (int) ( $_FILES['file']['error'] ?? -1 );
    if ( $upload_err !== UPLOAD_ERR_OK ) {
        $php_upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the HTML form',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'Upload stopped by a PHP extension',
        ];
        wp_send_json_error( [ 'msg' => $php_upload_errors[ $upload_err ] ?? 'PHP upload error ' . $upload_err ] );
    }

    $orig_name = ! empty( $title ) ? $title : basename( $_FILES['file']['name'] );
    $orig_name = sanitize_file_name( $orig_name );
    $file_type = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );

    $udir      = wp_upload_dir();
    $target_dir = trailingslashit( $udir['basedir'] ) . 'tutorial_materials/';
    if ( ! file_exists( $target_dir ) ) {
        wp_mkdir_p( $target_dir );
    }

    $unique_name = time() . '_' . preg_replace( '/[^a-zA-Z0-9.]/', '_', $orig_name );
    $target_path = $target_dir . $unique_name;
    $file_url    = trailingslashit( $udir['baseurl'] ) . 'tutorial_materials/' . $unique_name;

    if ( ! move_uploaded_file( $_FILES['file']['tmp_name'], $target_path ) ) {
        error_log( '[wcd_upload] move_uploaded_file failed. tmp=' . $_FILES['file']['tmp_name'] . ' target=' . $target_path );
        wp_send_json_error( [ 'msg' => 'File move failed. Check server permissions on ' . $target_dir ] );
    }

    global $wpdb;
    $tbl      = $wpdb->prefix . 'webinar_materials';
    $inserted = $wpdb->insert(
        $tbl,
        [
            'event_id'      => $event_id,
            'title'         => $orig_name,
            'file_url'      => $file_url,
            'file_type'     => $file_type,
            'material_type' => $material_type,
            'description'   => '',
            'uploaded_at'   => current_time( 'mysql' ),
            'uploaded_by'   => $user_id,
        ],
        [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
    );

    if ( ! $inserted ) {
        @unlink( $target_path );
        error_log( '[wcd_upload] DB insert failed. Last error: ' . $wpdb->last_error );
        wp_send_json_error( [ 'msg' => 'DB insert failed: ' . $wpdb->last_error ] );
    }

    wp_send_json_success( [
        'material_id' => (int) $wpdb->insert_id,
        'file_url'    => $file_url,
        'title'       => $orig_name,
    ] );
}