<?php
/**
 * Shortcode: [webinar_library]
 * Place [webinar_library] on any page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------
   DATA FUNCTIONS
--------------------------------------------------------------- */

if ( ! function_exists( 'wbl_featured_events' ) ) {
    function wbl_featured_events() {
        global $wpdb;
        $now = current_time( 'mysql', true );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.name, COALESCE(e.price,0) AS price,
                    COALESCE(e.maxCapacity,0) AS maxCap,
                    ep.id AS periodId, ep.periodStart, ep.periodEnd,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}amelia_customer_bookings cb2
                     INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x2
                             ON cb2.id=x2.customerBookingId
                     WHERE x2.eventPeriodId=ep.id AND cb2.status='approved') AS booked
             FROM {$wpdb->prefix}amelia_events e
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId=e.id
             WHERE e.status='approved' AND ep.periodStart>%s
             ORDER BY ep.periodStart ASC LIMIT 3", $now
        ), ARRAY_A ) ?: [];
    }
}

if ( ! function_exists( 'wbl_all_upcoming_events' ) ) {
    function wbl_all_upcoming_events() {
        global $wpdb;
        $now = current_time( 'mysql', true );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, e.name, COALESCE(e.price,0) AS price,
                    COALESCE(e.maxCapacity,0) AS maxCap,
                    ep.id AS periodId, ep.periodStart, ep.periodEnd,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}amelia_customer_bookings cb2
                     INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x2
                             ON cb2.id=x2.customerBookingId
                     WHERE x2.eventPeriodId=ep.id AND cb2.status='approved') AS booked
             FROM {$wpdb->prefix}amelia_events e
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId=e.id
             WHERE e.status='approved' AND ep.periodStart>%s
             ORDER BY ep.periodStart ASC", $now
        ), ARRAY_A ) ?: [];
    }
}

if ( ! function_exists( 'wbl_past_attended' ) ) {
    function wbl_past_attended( $uid ) {
        if(!$uid) return [];
        global $wpdb;
        $u=get_userdata($uid); if(!$u) return [];
        $now=current_time('mysql',true);
        $cid=$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_users WHERE email=%s AND type='customer' LIMIT 1",$u->user_email));
        if(!$cid) return [];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT e.id AS event_id, e.name, ep.periodStart,
                    COALESCE((SELECT p2.amount FROM {$wpdb->prefix}amelia_payments p2
                              WHERE p2.customerBookingId=cb.id AND p2.status='paid'
                              ORDER BY p2.id DESC LIMIT 1),e.price,0) AS amount_paid
             FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x ON cb.id=x.customerBookingId
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON x.eventPeriodId=ep.id
             INNER JOIN {$wpdb->prefix}amelia_events e ON ep.eventId=e.id
             INNER JOIN {$wpdb->prefix}amelia_payments p ON p.customerBookingId=cb.id
             WHERE cb.customerId=%d AND cb.status='approved' AND p.status='paid' AND ep.periodStart<%s
             ORDER BY ep.periodStart DESC",(int)$cid,$now),ARRAY_A)?:[];
    }
}

if ( ! function_exists( 'wbl_get_event_material_counts' ) ) {
    function wbl_get_event_material_counts( array $ids ) {
        if(empty($ids)) return [];
        global $wpdb;
        $t=$wpdb->prefix.'webinar_materials';
        if($wpdb->get_var("SHOW TABLES LIKE '$t'")!==$t) return [];
        $ph=implode(',',array_fill(0,count($ids),'%d'));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT event_id,COUNT(*) AS cnt FROM $t WHERE event_id IN($ph) GROUP BY event_id",...$ids),ARRAY_A);
        $map=[];
        foreach($rows as $r) $map[(int)$r['event_id']]=(int)$r['cnt'];
        return $map;
    }
}

if ( ! function_exists( 'wbl_registered_ids' ) ) {
    function wbl_registered_ids( $uid ) {
        if(!$uid) return [];
        global $wpdb;
        $u=get_userdata($uid); if(!$u) return [];
        $cid=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}amelia_users WHERE email=%s AND type='customer' LIMIT 1",$u->user_email));
        if(!$cid) return [];
        return array_map('intval',$wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT ep.eventId FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x ON cb.id=x.customerBookingId
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON x.eventPeriodId=ep.id
             INNER JOIN {$wpdb->prefix}amelia_payments p ON p.customerBookingId=cb.id
             WHERE cb.customerId=%d AND cb.status='approved' AND p.status='paid'",(int)$cid))?:[]);
    }
}

if ( ! function_exists( 'wbl_pending_ids' ) ) {
    function wbl_pending_ids( $uid ) {
        if(!$uid) return [];
        global $wpdb;
        $u=get_userdata($uid); if(!$u) return [];
        $cid=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}amelia_users WHERE email=%s AND type='customer' LIMIT 1",$u->user_email));
        if(!$cid) return [];
        return array_map('intval',$wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT ep.eventId FROM {$wpdb->prefix}amelia_customer_bookings cb
             INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x ON cb.id=x.customerBookingId
             INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON x.eventPeriodId=ep.id
             INNER JOIN {$wpdb->prefix}amelia_payments p ON p.customerBookingId=cb.id
             WHERE cb.customerId=%d AND cb.status='approved' AND p.status IN('pending','unpaid','waiting')",(int)$cid))?:[]);
    }
}

if ( ! function_exists( 'wbl_get_experts' ) ) {
    function wbl_get_experts() {
        global $wpdb;
        $rows=$wpdb->get_results("SELECT firstName,lastName FROM {$wpdb->prefix}amelia_users WHERE type='provider' AND status='visible' ORDER BY firstName ASC,lastName ASC",ARRAY_A);
        return $rows?array_map(fn($r)=>trim($r['firstName'].' '.$r['lastName']),$rows):[];
    }
}

if ( ! function_exists( 'wbl_max_price' ) ) {
    function wbl_max_price() {
        global $wpdb;
        $now=current_time('mysql',true);
        $max=$wpdb->get_var($wpdb->prepare("SELECT MAX(COALESCE(e.price,0)) FROM {$wpdb->prefix}amelia_events e INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId=e.id WHERE e.status='approved' AND ep.periodStart>%s",$now));
        return $max?(int)ceil((float)$max):5000;
    }
}

/**
 * Get certificate IDs for a user across multiple event IDs in one query.
 * Returns array keyed by event_id => certificate_id
 */
if ( ! function_exists( 'wbl_get_certificate_ids' ) ) {
    function wbl_get_certificate_ids( $uid, array $event_ids ) {
        if ( ! $uid || empty( $event_ids ) ) return [];
        global $wpdb;
        $t = $wpdb->prefix . 'webinar_certificates';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) !== $t ) return [];
        $ph   = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
        $args = array_merge( [ $uid ], $event_ids );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_id, certificate_id FROM $t
                 WHERE student_user_id = %d
                   AND event_id IN($ph)
                   AND status = 'active'",
                ...$args
            ), ARRAY_A
        );
        $map = [];
        foreach ( $rows as $r ) {
            $map[ (int) $r['event_id'] ] = $r['certificate_id'];
        }
        return $map;
    }
}

/* ---------------------------------------------------------------
   SHORTCODE
--------------------------------------------------------------- */
add_shortcode( 'webinar_library', 'wbl_render' );

function wbl_render() {
    global $wpdb;

    $uid    = get_current_user_id();
    $user   = $uid ? wp_get_current_user() : null;
    $uname  = $user ? (trim($user->display_name)?:'Guest') : 'Guest';
    $avatar = $uid ? get_avatar_url($uid,['size'=>60]) : '';
    $ufirst = $user ? ($user->user_firstname ?: explode(' ',$uname)[0]) : '';
    $cur_raw = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '&#8377;';
    $cur_js  = html_entity_decode($cur_raw,ENT_QUOTES,'UTF-8');

    $url_dash = home_url('/webinar-dashboard');
    $url_lib  = home_url('/webinar-library');
    $url_my   = home_url('/my-events');

    $featured   = wbl_featured_events();
    $all_events = wbl_all_upcoming_events();
    $past       = $uid ? wbl_past_attended($uid) : [];
    $reg_arr    = $uid ? wbl_registered_ids($uid) : [];
    $reg_ids    = array_flip($reg_arr);
    $pend_arr   = $uid ? wbl_pending_ids($uid) : [];
    $pend_ids   = array_flip($pend_arr);
    $experts    = wbl_get_experts();
    $max_price  = wbl_max_price();
    $exp_count  = count($experts);

    $mat_counts = wbl_get_event_material_counts(array_map('intval',array_column($past,'event_id')));

    // Fetch certificate IDs for all past events in one query
    $past_event_ids  = array_map( fn($p) => (int)$p['event_id'], $past );
    $cert_id_map     = $uid ? wbl_get_certificate_ids( $uid, $past_event_ids ) : [];

    $specialisations = [
        'Interventional Cardiology','Cardiac Electrophysiology','Neonatology','Maternal Fetal Medicine',
        'Pediatric Neurology','Neurocritical Care','Interventional Radiology','Vascular Surgery',
        'Gynecologic Oncology','Surgical Oncology','Hematopathology','Transfusion Medicine',
        'Clinical Genetics','Reproductive Endocrinology','Movement Disorders Neurology',
        'Sleep Medicine','Pediatric Cardiac Surgery','Hand and Microsurgery','Pain Medicine','Palliative Medicine',
    ];

    $js_events = array_map(function($ev){
        $s=strtotime($ev['periodStart'].' UTC'); $e=strtotime($ev['periodEnd'].' UTC');
        return ['id'=>(int)$ev['id'],'name'=>$ev['name'],'price'=>(float)$ev['price'],
                'maxCap'=>(int)$ev['maxCap'],'booked'=>(int)$ev['booked'],
                'start_ts'=>$s,'end_ts'=>$e,
                'date_str'=>wp_date('M j, Y',$s),
                'time_str'=>wp_date('g:i A',$s).' - '.wp_date('g:i A',$e)];
    },$all_events);

    $CARD_BG = [
        'linear-gradient(135deg,#e0eaff 0%,#c7d7ff 100%)',
        'linear-gradient(135deg,#d1f5f0 0%,#a7e9e0 100%)',
        'linear-gradient(135deg,#fce7f3 0%,#fbcfe8 100%)',
        'linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)',
        'linear-gradient(135deg,#ede9fe 0%,#ddd6fe 100%)',
        'linear-gradient(135deg,#dcfce7 0%,#bbf7d0 100%)',
    ];

    ob_start();
    ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<style>
#wpadminbar{display:none!important;}
html{margin-top:0!important;}
header,#masthead,.site-header,.main-header,#header,.elementor-location-header,
.ast-main-header-wrap,#site-header,.fusion-header-wrapper,.header-wrap,
.nav-primary,.navbar,div[data-elementor-type="header"]{display:none!important;}
.site-content,.site-main,#content,#page{margin:0!important;padding:0!important;max-width:100%!important;width:100%!important;}

#wbl-root{
    font-family:'Sora',sans-serif;
    background:#f0f2f8;
    min-height:100vh;
    padding:0 0 64px;
    color:#0d1025;
}
#wbl-root *{box-sizing:border-box;font-family:'Sora',sans-serif;}

/* ---- NAV ---- */
#wbl-root .wbl-nav{background:#fff;border-bottom:1px solid #e4e7f0;position:sticky;top:0;z-index:200;box-shadow:0 2px 12px rgba(13,16,37,.06);}
#wbl-root .wbl-nav-inner{max-width:1280px;margin:0 auto;padding:0 32px;height:60px;display:flex;align-items:center;gap:16px;}
#wbl-root .wbl-logo{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:800;color:#1a56db;text-decoration:none;flex-shrink:0;}
#wbl-root .wbl-logo-box{width:32px;height:32px;background:#1a56db;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
#wbl-root .wbl-logo-box svg{color:#fff;}
#wbl-root .wbl-nav-links{display:flex;gap:2px;margin-left:8px;}
#wbl-root .wbl-nav-links a{padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;color:#5a6180;text-decoration:none;display:inline-block;}
#wbl-root .wbl-nav-links a:hover,#wbl-root .wbl-nav-links a.active{background:#eef3ff;color:#1a56db;}
#wbl-root .wbl-nav-srch{flex:1;max-width:360px;margin-left:8px;display:flex;align-items:center;gap:8px;background:#f7f8fc;border:1.5px solid #e4e7f0;border-radius:30px;padding:0 16px;height:38px;}
#wbl-root .wbl-nav-srch input{border:none;background:transparent;outline:none;font-size:13px;color:#0d1025;width:100%;}
#wbl-root .wbl-nav-r{margin-left:auto;display:flex;align-items:center;gap:12px;}
#wbl-root .wbl-nav-bell{width:36px;height:36px;border-radius:50%;border:1.5px solid #e4e7f0;background:#f7f8fc;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;}
#wbl-root .wbl-nav-bdot{position:absolute;top:2px;right:2px;width:8px;height:8px;border-radius:50%;background:#dc2626;border:2px solid #fff;}
#wbl-root .wbl-nav-avatar{display:flex;align-items:center;gap:9px;}
#wbl-root .wbl-nav-avatar img{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #eef3ff;display:block;}
#wbl-root .wbl-nav-uname{font-size:13px;font-weight:700;color:#0d1025;}
#wbl-root .wbl-nav-logout{padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:700;background:#0d1025;color:#fff;text-decoration:none;display:inline-block;}
#wbl-root .wbl-nav-logout:hover{opacity:.82;color:#fff;}
@media(max-width:860px){#wbl-root .wbl-nav-links{display:none;}#wbl-root .wbl-nav-inner{padding:0 16px;}}

/* ---- HEADER ---- */
#wbl-root .wbl-hdr{padding:28px 32px 0;max-width:1280px;margin:0 auto;}
#wbl-root .wbl-hdr h1{font-family:'Instrument Serif',serif;font-size:28px;font-weight:400;color:#0d1025;letter-spacing:-.3px;margin:0;}
#wbl-root .wbl-hdr p{font-size:13px;color:#5a6180;margin:4px 0 0;}

/* ---- BODY LAYOUT ---- */
#wbl-root .wbl-body{display:flex;gap:20px;padding:20px 32px;align-items:flex-start;max-width:1280px;margin:0 auto;}
#wbl-root .wbl-sidebar{width:238px;flex-shrink:0;min-width:0;}
#wbl-root .wbl-content{flex:1;min-width:0;}
@media(max-width:860px){
    #wbl-root .wbl-body{flex-direction:column;padding:14px;}
    #wbl-root .wbl-sidebar{width:100%;}
    #wbl-root .wbl-hdr{padding-left:16px;padding-right:16px;}
}

/* ---- SIDEBAR CARDS ---- */
#wbl-root .wbl-scard{background:#fff;border:1px solid #e4e7f0;border-radius:14px;box-shadow:0 1px 4px rgba(13,16,37,.07),0 6px 24px rgba(13,16,37,.06);overflow:hidden;margin-bottom:14px;}
#wbl-root .wbl-scard-h{padding:11px 14px 10px;border-bottom:1px solid #e4e7f0;background:linear-gradient(135deg,#f7f8fc 0%,#fff 100%);}
#wbl-root .wbl-scard-title{font-size:13px;font-weight:700;color:#0d1025;}
#wbl-root .wbl-scard-body{padding:12px 13px;}
#wbl-root .wbl-exp-srch{display:flex;align-items:center;gap:6px;background:#f7f8fc;border:1.5px solid #e4e7f0;border-radius:20px;padding:0 10px;height:30px;margin-bottom:9px;width:100%;}
#wbl-root .wbl-exp-srch input{border:none;background:transparent;outline:none;font-size:12px;color:#0d1025;flex:1;}
#wbl-root .wbl-exp-list{display:flex;flex-direction:column;gap:4px;}
#wbl-root .wbl-exp-item{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:8px;border:1.5px solid transparent;cursor:pointer;background:#f7f8fc;}
#wbl-root .wbl-exp-item:hover,#wbl-root .wbl-exp-item.active{border-color:#1a56db;background:#eef3ff;}
#wbl-root .wbl-exp-av{width:26px;height:26px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#1a56db 0%,#0891b2 100%);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff;}
#wbl-root .wbl-exp-nm{font-size:12px;font-weight:600;color:#0d1025;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#wbl-root .wbl-view-all{display:flex;align-items:center;justify-content:center;gap:4px;margin-top:8px;padding:5px;font-size:11px;font-weight:700;color:#1a56db;cursor:pointer;border-radius:8px;border:1.5px dashed #1a56db;background:transparent;width:100%;}
#wbl-root .wbl-view-all:hover{background:#eef3ff;}
#wbl-root .wbl-flabel{font-size:10px;font-weight:700;color:#9aa0b8;letter-spacing:.6px;text-transform:uppercase;margin-bottom:7px;display:block;}
#wbl-root .wbl-fgroup{margin-bottom:13px;}
#wbl-root .wbl-date-stack{display:flex;flex-direction:column;gap:5px;}
#wbl-root .wbl-date-stack input[type="date"]{width:100%;border:1.5px solid #e4e7f0;border-radius:8px;padding:6px 8px;font-size:11.5px;color:#0d1025;background:#f7f8fc;outline:none;}
#wbl-root .wbl-price-lbl{font-size:12px;font-weight:700;color:#1a56db;margin-bottom:8px;display:block;}
#wbl-root .wbl-slider{-webkit-appearance:none;appearance:none;width:100%;height:4px;border-radius:4px;outline:none;cursor:pointer;background:#e4e7f0;}
#wbl-root .wbl-slider::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;border-radius:50%;background:#1a56db;cursor:pointer;border:2px solid #fff;box-shadow:0 0 0 3px rgba(26,86,219,.2);}
#wbl-root .wbl-spec-list{max-height:180px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;}
#wbl-root .wbl-spec-opt{display:flex;align-items:center;gap:7px;padding:5px;border-radius:6px;cursor:pointer;}
#wbl-root .wbl-spec-opt:hover{background:#f7f8fc;}
#wbl-root .wbl-spec-opt input{width:13px;height:13px;accent-color:#1a56db;cursor:pointer;flex-shrink:0;}
#wbl-root .wbl-spec-opt label{font-size:11.5px;color:#0d1025;cursor:pointer;}
#wbl-root .wbl-apply-btn{display:flex;align-items:center;justify-content:center;gap:5px;width:100%;padding:8px;border-radius:8px;border:none;background:#1a56db;color:#fff;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:6px;box-shadow:0 2px 8px rgba(26,86,219,.25);}
#wbl-root .wbl-apply-btn:hover{background:#1447c0;}
#wbl-root .wbl-clear-btn{display:flex;align-items:center;justify-content:center;gap:5px;width:100%;padding:8px;border-radius:8px;border:1.5px solid #e4e7f0;color:#5a6180;background:transparent;font-size:12px;font-weight:700;cursor:pointer;}
#wbl-root .wbl-clear-btn:hover{border-color:#dc2626;color:#dc2626;background:#fef2f2;}
#wbl-root .wbl-sec-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
#wbl-root .wbl-sec-title{font-size:17px;font-weight:700;color:#0d1025;}
#wbl-root .wbl-sec-cnt{font-size:12px;color:#9aa0b8;background:#f7f8fc;border:1px solid #e4e7f0;padding:3px 10px;border-radius:20px;font-weight:600;}
#wbl-root .wbl-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
#wbl-root .wbl-chip{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;padding:4px 10px;background:#eef3ff;color:#1a56db;border-radius:20px;border:1px solid rgba(26,86,219,.2);}
#wbl-root .wbl-chip button{background:none;border:none;cursor:pointer;color:#1a56db;font-size:14px;line-height:1;padding:0;}

/* ---- FEATURED CARDS ---- */
#wbl-root .wbl-fgrid{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:18px;
    margin-bottom:32px;
}
@media(max-width:640px){#wbl-root .wbl-fgrid{grid-template-columns:1fr;}}

#wbl-root .wbl-fcard{
    display:flex;
    flex-direction:column;
    background:#fff;
    border:1.5px solid #e4e7f0;
    border-radius:14px;
    box-shadow:0 1px 4px rgba(13,16,37,.07),0 6px 24px rgba(13,16,37,.06);
    overflow:hidden;
    cursor:pointer;
    transition:transform .2s,box-shadow .2s,border-color .2s;
}
#wbl-root .wbl-fcard:hover{transform:translateY(-3px);box-shadow:0 4px 12px rgba(13,16,37,.1),0 20px 48px rgba(26,86,219,.12);border-color:#1a56db;}

#wbl-root .wbl-fcard-thumb{
    height:120px;
    position:relative;
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    overflow:hidden;
}
#wbl-root .wbl-fcard-badge{
    position:absolute;top:10px;left:10px;
    font-size:10px;font-weight:700;padding:4px 12px;border-radius:20px;
    background:#1a56db;color:#fff;z-index:1;
}
#wbl-root .wbl-fcard-badge.free{background:#059669;}
#wbl-root .wbl-fcard-ico{
    width:44px;height:44px;background:rgba(255,255,255,.8);
    border-radius:50%;display:flex;align-items:center;justify-content:center;
}

#wbl-root .wbl-fcard-body{
    padding:16px 18px 14px;
    display:flex;
    flex-direction:column;
    flex:1;
}
#wbl-root .wbl-fcard-title{
    font-size:14px;
    font-weight:700;
    color:#0d1025;
    line-height:1.4;
    margin:0 0 10px;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
}
#wbl-root .wbl-fcard-meta{
    display:flex;flex-direction:column;gap:5px;
    margin-bottom:14px;flex:1;
}
#wbl-root .wbl-fcard-meta span{
    font-size:12px;color:#5a6180;
    display:flex;align-items:center;gap:6px;font-weight:500;
}
#wbl-root .wbl-fcard-meta svg{color:#9aa0b8;flex-shrink:0;}
#wbl-root .wbl-fcard-foot{
    display:flex;align-items:center;justify-content:space-between;
    padding-top:12px;border-top:1px solid #e4e7f0;gap:10px;
}
#wbl-root .wbl-price{font-size:17px;font-weight:800;color:#1a56db;flex-shrink:0;}
#wbl-root .wbl-price.free{color:#059669;}

/* ---- Action buttons ---- */
#wbl-root .wbl-btn{
    display:inline-flex;align-items:center;gap:5px;
    font-size:12.5px;font-weight:700;padding:8px 18px;border-radius:20px;
    background:#1a56db;color:#fff;
    text-decoration:none;border:none;cursor:pointer;
    transition:background .18s,transform .15s;
    box-shadow:0 2px 8px rgba(26,86,219,.28);
    white-space:nowrap;flex-shrink:0;
}
#wbl-root .wbl-btn:hover{background:#1447c0;transform:translateY(-1px);color:#fff;}
#wbl-root .wbl-btn.reg{background:#e5e7eb;color:#6b7280;box-shadow:none;cursor:default;pointer-events:none;}
#wbl-root .wbl-btn.full{background:#e5e7eb;color:#9ca3af;box-shadow:none;cursor:default;pointer-events:none;}
#wbl-root .wbl-btn.pay{background:#d97706;color:#fff;box-shadow:0 2px 8px rgba(217,119,6,.3);}
#wbl-root .wbl-btn.pay:hover{background:#b45309;color:#fff;}

/* ---- Results / empty ---- */
#wbl-root .wbl-results{display:none;}
#wbl-root .wbl-results.visible{display:block;}
#wbl-root .wbl-fsec.hidden,#wbl-root .wbl-psec.hidden{display:none;}
#wbl-root .wbl-empty{text-align:center;padding:44px 16px;color:#9aa0b8;background:#fff;border:1px solid #e4e7f0;border-radius:14px;}
#wbl-root .wbl-empty svg{opacity:.25;margin:0 auto 10px;display:block;}
#wbl-root .wbl-empty p{font-size:14px;font-weight:500;}

/* ---- Past attended rows ---- */
#wbl-root .wbl-past-list{display:flex;flex-direction:column;gap:10px;}
#wbl-root .wbl-past-row{background:#fff;border:1px solid #e4e7f0;border-radius:14px;box-shadow:0 1px 4px rgba(13,16,37,.07),0 6px 24px rgba(13,16,37,.06);display:flex;align-items:center;gap:13px;padding:12px 15px;cursor:pointer;transition:border-color .18s,background .15s;}
#wbl-root .wbl-past-row:hover{border-color:rgba(26,86,219,.2);background:#f7f8fc;}
#wbl-root .wbl-past-dt{text-align:center;min-width:50px;flex-shrink:0;background:#f7f8fc;border:1px solid #e4e7f0;border-radius:8px;padding:6px 8px;}
#wbl-root .wbl-past-dt-m{font-size:9px;font-weight:700;color:#1a56db;text-transform:uppercase;letter-spacing:.5px;}
#wbl-root .wbl-past-dt-d{font-size:20px;font-weight:800;color:#0d1025;line-height:1;}
#wbl-root .wbl-past-info{flex:1;min-width:0;}
#wbl-root .wbl-past-title{font-size:14px;font-weight:700;color:#0d1025;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
#wbl-root .wbl-past-sub{font-size:12px;color:#5a6180;display:flex;align-items:center;gap:7px;flex-wrap:wrap;}
#wbl-root .wbl-amt{font-weight:700;color:#1a56db;}
#wbl-root .wbl-amt.free{color:#059669;}
#wbl-root .wbl-past-acts{flex-shrink:0;display:flex;align-items:center;gap:8px;}
#wbl-root .wbl-mat-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;background:#eef3ff;color:#1a56db;border:1px solid rgba(26,86,219,.15);text-decoration:none;}
#wbl-root .wbl-cert-btn{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;padding:7px 13px;border-radius:20px;background:#d1fae5;color:#059669;border:1.5px solid #059669;text-decoration:none;white-space:nowrap;}
#wbl-root .wbl-cert-btn:hover{background:#059669;color:#fff;}
#wbl-root .wbl-cert-btn.pending{background:#eef3ff;color:#1a56db;border-color:#1a56db;}
#wbl-root .wbl-cert-btn.pending:hover{background:#1a56db;color:#fff;}
</style>

<div id="wbl-root">

<!-- NAVBAR -->
<nav class="wbl-nav">
  <div class="wbl-nav-inner">
    <a href="<?php echo esc_url(home_url()); ?>" class="wbl-logo">
      <div class="wbl-logo-box">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.89L15 14"/>
          <rect x="2" y="6" width="13" height="12" rx="2"/>
        </svg>
      </div>
      <?php echo esc_html(get_bloginfo('name')); ?>
    </a>
    <div class="wbl-nav-links">
      <a href="<?php echo esc_url($url_dash); ?>">Dashboard</a>
      <a href="<?php echo esc_url($url_lib); ?>" class="active">Webinar Library</a>
    </div>
    <div class="wbl-nav-srch">
      <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#9aa0b8" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" id="wbl-nav-q" placeholder="Search webinars...">
    </div>
    <div class="wbl-nav-r">
      <div class="wbl-nav-bell">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#5a6180" stroke-width="2">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <span class="wbl-nav-bdot"></span>
      </div>
      <?php if($user): ?>
      <div class="wbl-nav-avatar">
        <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($uname); ?>">
        <span class="wbl-nav-uname">Hi, <?php echo esc_html($ufirst?:$uname); ?></span>
      </div>
      <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="wbl-nav-logout">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="wbl-hdr">
  <h1>Webinar Library</h1>
  <p>Browse and attend medical education webinars</p>
</div>

<div class="wbl-body">

  <!-- SIDEBAR -->
  <aside class="wbl-sidebar">
    <div class="wbl-scard">
      <div class="wbl-scard-h"><span class="wbl-scard-title">Experts</span></div>
      <div class="wbl-scard-body">
        <div class="wbl-exp-srch">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#9aa0b8" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input type="text" id="wbl-exp-q" placeholder="Search experts...">
        </div>
        <div class="wbl-exp-list" id="wbl-exp-list">
          <?php foreach($experts as $idx=>$name):
              $parts=array_filter(explode(' ',$name));
              $ini=implode('',array_map(fn($p)=>strtoupper(substr($p,0,1)),array_slice($parts,0,2)));
          ?>
          <div class="wbl-exp-item<?php echo $idx>=4?' wbl-exp-extra':''; ?>"
               <?php echo $idx>=4?'style="display:none"':''; ?>
               data-name="<?php echo esc_attr(strtolower($name)); ?>"
               data-full="<?php echo esc_attr($name); ?>"
               onclick="wblToggleExpert(this)">
            <div class="wbl-exp-av"><?php echo esc_html($ini); ?></div>
            <span class="wbl-exp-nm"><?php echo esc_html($name); ?></span>
          </div>
          <?php endforeach; ?>
          <?php if(empty($experts)): ?><p style="font-size:12px;color:#9aa0b8;text-align:center;padding:8px 0;">No experts found.</p><?php endif; ?>
        </div>
        <?php if($exp_count>4): ?>
        <button class="wbl-view-all" id="wbl-va-btn" data-expanded="0" onclick="wblToggleExperts(this)">
          <svg id="wbl-vaicon" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          <span id="wbl-va-label">View all (<?php echo $exp_count; ?> experts)</span>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="wbl-scard">
      <div class="wbl-scard-h"><span class="wbl-scard-title">Filters</span></div>
      <div class="wbl-scard-body">
        <div class="wbl-fgroup">
          <span class="wbl-flabel">Date Range</span>
          <div class="wbl-date-stack">
            <input type="date" id="wbl-date-from">
            <span style="font-size:10px;color:#9aa0b8;font-weight:600;">to</span>
            <input type="date" id="wbl-date-to">
          </div>
        </div>
        <div class="wbl-fgroup">
          <span class="wbl-flabel">Max Price</span>
          <span class="wbl-price-lbl" id="wbl-price-lbl">Loading...</span>
          <input type="range" class="wbl-slider" id="wbl-price-slider" min="0" max="<?php echo esc_attr($max_price); ?>" value="<?php echo esc_attr($max_price); ?>" oninput="wblPriceSlide(this)">
        </div>
        <div class="wbl-fgroup">
          <span class="wbl-flabel">Specialisation</span>
          <div class="wbl-spec-list">
            <?php foreach($specialisations as $spec): ?>
            <label class="wbl-spec-opt">
              <input type="checkbox" name="wbl_spec" value="<?php echo esc_attr($spec); ?>">
              <label><?php echo esc_html($spec); ?></label>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <button class="wbl-apply-btn" onclick="wblApplyFilters()">
          <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M3 4h18M7 8h10M10 12h4"/></svg>
          Apply Filters
        </button>
        <button class="wbl-clear-btn" onclick="wblClearAll()">
          <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Clear Filters
        </button>
      </div>
    </div>
  </aside>

  <!-- CONTENT -->
  <div class="wbl-content">
    <div class="wbl-chips" id="wbl-chips"></div>

    <!-- FILTERED RESULTS -->
    <div class="wbl-results" id="wbl-results">
      <div class="wbl-sec-h">
        <span class="wbl-sec-title" id="wbl-res-title">Results</span>
        <span class="wbl-sec-cnt" id="wbl-res-cnt">0 events</span>
      </div>
      <div class="wbl-fgrid" id="wbl-res-grid"></div>
    </div>

    <!-- FEATURED WEBINARS -->
    <div class="wbl-fsec" id="wbl-fsec">
      <div class="wbl-sec-h">
        <span class="wbl-sec-title">Featured Webinars</span>
        <span class="wbl-sec-cnt"><?php echo count($featured); ?> upcoming</span>
      </div>
      <div class="wbl-fgrid">
        <?php if(empty($featured)): ?>
          <div class="wbl-empty" style="grid-column:1/-1">
            <svg width="38" height="38" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <p>No upcoming webinars at the moment.</p>
          </div>
        <?php else: ?>
          <?php foreach($featured as $i=>$ev):
              $eid=(int)$ev['id']; $price=(float)$ev['price']; $free=$price<=0;
              $sts=strtotime($ev['periodStart'].' UTC'); $ets=strtotime($ev['periodEnd'].' UTC');
              $dstr=wp_date('M j, Y',$sts); $tstr=wp_date('g:i A',$sts).' - '.wp_date('g:i A',$ets);
              $cap=(int)$ev['maxCap']; $bkd=(int)$ev['booked'];
              $spots=$cap>0?max(0,$cap-$bkd):null;
              $is_reg=isset($reg_ids[$eid]); $is_pend=isset($pend_ids[$eid]); $is_full=$spots!==null&&$spots<=0;
              $bg=$CARD_BG[$i%count($CARD_BG)];
              $info=esc_url(home_url('/webinar-info?event-id='.$eid));
              $pay_pg=$free?'free-webinar-payment':'paid-webinar-payment';
              $pay_url=esc_url(add_query_arg('event-id',$eid,home_url('/'.$pay_pg)));
          ?>
          <div class="wbl-fcard" onclick="window.location.href='<?php echo $info; ?>'">
            <div class="wbl-fcard-thumb" style="background:<?php echo esc_attr($bg); ?>">
              <div class="wbl-fcard-badge <?php echo $free?'free':''; ?>"><?php echo $free?'Free':'Paid'; ?></div>
              <div class="wbl-fcard-ico">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#1a56db" stroke-width="2">
                  <path d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.89L15 14"/>
                  <rect x="2" y="6" width="13" height="12" rx="2"/>
                </svg>
              </div>
            </div>
            <div class="wbl-fcard-body">
              <div class="wbl-fcard-title"><?php echo esc_html($ev['name']); ?></div>
              <div class="wbl-fcard-meta">
                <span>
                  <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  <?php echo esc_html($dstr); ?>
                </span>
                <span>
                  <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?php echo esc_html($tstr); ?>
                </span>
                <?php if($spots!==null): ?>
                <span>
                  <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                  <?php echo esc_html($bkd.'/'.$cap.' booked'); ?>
                </span>
                <?php endif; ?>
              </div>
              <div class="wbl-fcard-foot">
                <span class="wbl-price <?php echo $free?'free':''; ?>">
                  <?php echo $free?'Free':esc_html($cur_raw.number_format($price,0)); ?>
                </span>
                <?php if($is_reg): ?>
                  <span class="wbl-btn reg" onclick="event.stopPropagation()">&#10003; Registered</span>
                <?php elseif($is_pend): ?>
                  <a class="wbl-btn pay" href="<?php echo $pay_url; ?>" onclick="event.stopPropagation()">Pay Now</a>
                <?php elseif($is_full): ?>
                  <span class="wbl-btn full" onclick="event.stopPropagation()">Full</span>
                <?php else: ?>
                  <a class="wbl-btn" href="<?php echo $info; ?>" onclick="event.stopPropagation()">Book Now</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div><!-- /featured -->

    <!-- PAST ATTENDED -->
    <div class="wbl-psec" id="wbl-psec">
      <div class="wbl-sec-h">
        <span class="wbl-sec-title">Past Attended Webinars</span>
        <span class="wbl-sec-cnt"><?php echo count($past); ?> attended</span>
      </div>
      <?php if(empty($past)): ?>
        <div class="wbl-empty">
          <svg width="38" height="38" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <p><?php echo $uid?"You haven't attended any webinars yet.":"Please log in to view your attended webinars."; ?></p>
        </div>
      <?php else: ?>
        <div class="wbl-past-list">
          <?php foreach($past as $p):
              $eid_p   = (int)$p['event_id'];
              $ts      = strtotime($p['periodStart'].' UTC');
              $amt     = (float)$p['amount_paid'];
              $fp      = $amt <= 0;
              $mc      = $mat_counts[$eid_p] ?? 0;

              // Certificate lookup
              $cert_id  = $cert_id_map[$eid_p] ?? '';
              $cert_url = $cert_id
                          ? home_url('/certificate/webinar/' . $cert_id)
                          : '';
          ?>
          <div class="wbl-past-row" onclick="window.location.href='<?php echo esc_url(home_url('/webinar-info?event-id='.$eid_p)); ?>'">
            <div class="wbl-past-dt">
              <div class="wbl-past-dt-m"><?php echo esc_html(wp_date('M',$ts)); ?></div>
              <div class="wbl-past-dt-d"><?php echo esc_html(wp_date('j',$ts)); ?></div>
            </div>
            <div class="wbl-past-info">
              <div class="wbl-past-title"><?php echo esc_html($p['name']); ?></div>
              <div class="wbl-past-sub">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php echo esc_html(wp_date('g:i A',$ts)); ?>
                <span class="wbl-amt <?php echo $fp?'free':''; ?>"><?php echo $fp?'Free':esc_html($cur_raw.number_format($amt,0)); ?></span>
              </div>
            </div>
            <div class="wbl-past-acts" onclick="event.stopPropagation()">
              <?php if($mc>0): ?>
              <a href="<?php echo esc_url(home_url('/webinar-info?event-id='.$eid_p)); ?>" class="wbl-mat-badge" onclick="event.stopPropagation()">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <?php echo $mc; ?> Material<?php echo $mc!==1?'s':''; ?>
              </a>
              <?php endif; ?>

              <?php if($cert_url): ?>
                <a class="wbl-cert-btn"
                   href="<?php echo esc_url($cert_url); ?>"
                   target="_blank"
                   title="View Certificate"
                   onclick="event.stopPropagation()">
                  <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  Certificate
                </a>
              <?php else: ?>
                <a class="wbl-cert-btn pending"
                   href="<?php echo esc_url(home_url('/webinar-dashboard')); ?>"
                   title="Generate certificate from your dashboard"
                   onclick="event.stopPropagation()">
                  <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
                  Get Certificate
                </a>
              <?php endif; ?>

            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div><!-- /past -->

  </div><!-- /content -->
</div><!-- /body -->
</div><!-- /#wbl-root -->

<script>
(function(){
'use strict';
var WBL_EVENTS=<?php echo json_encode($js_events); ?>;
var WBL_REG=<?php echo json_encode(array_values($reg_arr)); ?>;
var WBL_PEND=<?php echo json_encode(array_values($pend_arr)); ?>;
var CUR=<?php echo json_encode($cur_js); ?>;
var MAX_P=<?php echo (int)$max_price; ?>;
var EXP_COUNT=<?php echo (int)$exp_count; ?>;
var BASE=<?php echo json_encode(rtrim(home_url('/'),'/').'/'); ?>;

var staged={expert:null,specs:[],dateFrom:'',dateTo:'',maxPrice:MAX_P};
var active={expert:null,specs:[],dateFrom:'',dateTo:'',maxPrice:MAX_P,q:''};
var inRes=false;

function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function setSlider(v){
    var el=document.getElementById('wbl-price-slider'),max=parseInt(el.max);
    var pct=(v/max*100).toFixed(1)+'%';
    el.style.background='linear-gradient(to right,#1a56db 0%,#1a56db '+pct+',#e4e7f0 '+pct+',#e4e7f0 100%)';
    document.getElementById('wbl-price-lbl').textContent=CUR+'0 \u2013 '+CUR+v;
    staged.maxPrice=v;
}
window.wblPriceSlide=function(el){setSlider(parseInt(el.value));};
setSlider(MAX_P);

window.wblToggleExpert=function(el){
    var n=el.dataset.full;
    if(staged.expert===n){staged.expert=null;el.classList.remove('active');}
    else{document.querySelectorAll('#wbl-root .wbl-exp-item').forEach(function(e){e.classList.remove('active');});staged.expert=n;el.classList.add('active');}
};
window.wblToggleExperts=function(btn){
    var ex=btn.getAttribute('data-expanded')==='1';
    document.querySelectorAll('#wbl-root .wbl-exp-extra').forEach(function(el){el.style.display=ex?'none':'flex';});
    btn.setAttribute('data-expanded',ex?'0':'1');
    document.getElementById('wbl-vaicon').style.transform=ex?'':'rotate(180deg)';
    document.getElementById('wbl-va-label').textContent=ex?'View all ('+EXP_COUNT+' experts)':'Show less';
};
document.getElementById('wbl-exp-q').addEventListener('input',function(){
    var q=this.value.toLowerCase();
    document.querySelectorAll('#wbl-exp-list .wbl-exp-item').forEach(function(el){
        el.style.display=(!q||el.dataset.name.indexOf(q)!==-1)?'flex':'none';
    });
});
document.querySelectorAll('input[name="wbl_spec"]').forEach(function(cb){
    cb.addEventListener('change',function(){
        staged.specs=Array.from(document.querySelectorAll('input[name="wbl_spec"]:checked')).map(function(c){return c.value;});
    });
});

function matches(ev){
    if(active.q&&ev.name.toLowerCase().indexOf(active.q)===-1)return false;
    if(ev.price>active.maxPrice)return false;
    if(active.dateFrom){var df=new Date(active.dateFrom+'T00:00:00').getTime()/1000;if(ev.start_ts<df)return false;}
    if(active.dateTo){var dt=new Date(active.dateTo+'T23:59:59').getTime()/1000;if(ev.start_ts>dt)return false;}
    if(active.expert){
        var hay=ev.name.toLowerCase(),exp=active.expert.toLowerCase();
        if(hay.indexOf(exp)===-1){var last=exp.split(' ').pop();if(last.length>2&&hay.indexOf(last)===-1)return false;}
    }
    return true;
}

var COLS=['#e0eaff,#c7d7ff','#d1f5f0,#a7e9e0','#fce7f3,#fbcfe8','#fef3c7,#fde68a','#ede9fe,#ddd6fe','#dcfce7,#bbf7d0'];
function buildCard(ev,idx){
    var c=COLS[idx%COLS.length].split(',');
    var free=ev.price<=0;
    var spots=ev.maxCap>0?Math.max(0,ev.maxCap-ev.booked):null;
    var isReg=WBL_REG.indexOf(ev.id)!==-1;
    var isPnd=WBL_PEND.indexOf(ev.id)!==-1;
    var full=spots!==null&&spots<=0;
    var infoUrl=BASE+'webinar-info?event-id='+ev.id;
    var payPage=free?'free-webinar-payment':'paid-webinar-payment';
    var payUrl=BASE+payPage+'?event-id='+ev.id;
    var spotsHtml=spots!==null?'<span><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>'+ev.booked+'/'+ev.maxCap+' booked</span>':'';
    var btn;
    if(isReg)btn='<span class="wbl-btn reg" onclick="event.stopPropagation()">&#10003; Registered</span>';
    else if(isPnd)btn='<a class="wbl-btn pay" href="'+esc(payUrl)+'" onclick="event.stopPropagation()">Pay Now</a>';
    else if(full)btn='<span class="wbl-btn full" onclick="event.stopPropagation()">Full</span>';
    else btn='<a class="wbl-btn" href="'+esc(infoUrl)+'" onclick="event.stopPropagation()">Book Now</a>';
    return '<div class="wbl-fcard" onclick="window.location.href=\''+infoUrl+'\'">'
        +'<div class="wbl-fcard-thumb" style="background:linear-gradient(135deg,'+c[0]+' 0%,'+c[1]+' 100%)">'
        +'<div class="wbl-fcard-badge'+(free?' free':'')+'">'+( free?'Free':'Paid')+'</div>'
        +'<div class="wbl-fcard-ico"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#1a56db" stroke-width="2"><path d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.89L15 14"/><rect x="2" y="6" width="13" height="12" rx="2"/></svg></div>'
        +'</div>'
        +'<div class="wbl-fcard-body">'
        +'<div class="wbl-fcard-title">'+esc(ev.name)+'</div>'
        +'<div class="wbl-fcard-meta">'
        +'<span><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'+esc(ev.date_str)+'</span>'
        +'<span><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'+esc(ev.time_str)+'</span>'
        +spotsHtml+'</div>'
        +'<div class="wbl-fcard-foot">'
        +'<span class="wbl-price'+(free?' free':'')+'">'+( free?'Free':CUR+Math.round(ev.price))+'</span>'
        +btn+'</div></div></div>';
}

function renderResults(evs,title){
    document.getElementById('wbl-res-title').textContent=title;
    document.getElementById('wbl-res-cnt').textContent=evs.length+(evs.length===1?' event':' events');
    document.getElementById('wbl-res-grid').innerHTML=evs.length?evs.map(buildCard).join(''):'<div class="wbl-empty" style="grid-column:1/-1"><p>No webinars match your filters.</p></div>';
    document.getElementById('wbl-results').classList.add('visible');
    document.getElementById('wbl-fsec').classList.add('hidden');
    document.getElementById('wbl-psec').classList.add('hidden');
    inRes=true;
}
function showDefault(){
    document.getElementById('wbl-results').classList.remove('visible');
    document.getElementById('wbl-fsec').classList.remove('hidden');
    document.getElementById('wbl-psec').classList.remove('hidden');
    inRes=false;
}
function hasFilters(){return active.expert||active.specs.length||active.dateFrom||active.dateTo||active.maxPrice<MAX_P||active.q;}

window.wblApplyFilters=function(){
    active.expert=staged.expert;active.specs=staged.specs.slice();
    active.dateFrom=document.getElementById('wbl-date-from').value;
    active.dateTo=document.getElementById('wbl-date-to').value;
    active.maxPrice=staged.maxPrice;
    if(!hasFilters()){showDefault();renderChips();return;}
    renderResults(WBL_EVENTS.filter(matches),'Filtered Results');renderChips();
};
window.wblClearAll=function(){
    staged={expert:null,specs:[],dateFrom:'',dateTo:'',maxPrice:MAX_P};
    active={expert:null,specs:[],dateFrom:'',dateTo:'',maxPrice:MAX_P,q:''};
    document.getElementById('wbl-nav-q').value='';
    document.getElementById('wbl-date-from').value='';
    document.getElementById('wbl-date-to').value='';
    document.getElementById('wbl-price-slider').value=MAX_P;setSlider(MAX_P);
    document.querySelectorAll('input[name="wbl_spec"]').forEach(function(cb){cb.checked=false;});
    document.querySelectorAll('#wbl-root .wbl-exp-item').forEach(function(el){el.classList.remove('active');});
    showDefault();renderChips();
};
document.getElementById('wbl-nav-q').addEventListener('input',function(){
    active.q=this.value.trim().toLowerCase();
    if(!hasFilters()){showDefault();renderChips();return;}
    renderResults(WBL_EVENTS.filter(matches),active.q?'Search Results':'Filtered Results');renderChips();
});

function renderChips(){
    var h='';
    if(active.expert)h+='<span class="wbl-chip">Expert: '+esc(active.expert)+'<button onclick="wblChipRm(\'expert\',\'\')">&#215;</button></span>';
    if(active.dateFrom||active.dateTo)h+='<span class="wbl-chip">Date: '+esc((active.dateFrom||'…')+' to '+(active.dateTo||'…'))+'<button onclick="wblChipRm(\'date\',\'\')">&#215;</button></span>';
    if(active.maxPrice<MAX_P)h+='<span class="wbl-chip">Price &#8804; '+CUR+active.maxPrice+'<button onclick="wblChipRm(\'price\',\'\')">&#215;</button></span>';
    active.specs.forEach(function(s){h+='<span class="wbl-chip">'+esc(s)+'<button onclick="wblChipRm(\'spec\',\''+s.replace(/'/g,"\\'")+'\')" >&#215;</button></span>';});
    if(active.q)h+='<span class="wbl-chip">Search: '+esc(active.q)+'<button onclick="wblChipRm(\'q\',\'\')">&#215;</button></span>';
    document.getElementById('wbl-chips').innerHTML=h;
}
window.wblChipRm=function(type,val){
    if(type==='expert'){active.expert=null;staged.expert=null;document.querySelectorAll('#wbl-root .wbl-exp-item').forEach(function(e){e.classList.remove('active');});}
    else if(type==='date'){active.dateFrom='';active.dateTo='';document.getElementById('wbl-date-from').value='';document.getElementById('wbl-date-to').value='';}
    else if(type==='price'){active.maxPrice=MAX_P;document.getElementById('wbl-price-slider').value=MAX_P;setSlider(MAX_P);}
    else if(type==='spec'){active.specs=active.specs.filter(function(s){return s!==val;});document.querySelectorAll('input[name="wbl_spec"]').forEach(function(cb){if(cb.value===val)cb.checked=false;});}
    else if(type==='q'){active.q='';document.getElementById('wbl-nav-q').value='';}
    if(!hasFilters()){showDefault();renderChips();return;}
    renderResults(WBL_EVENTS.filter(matches),inRes?'Filtered Results':'Search Results');renderChips();
};
})();
</script>
<?php
    return ob_get_clean();
}