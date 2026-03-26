<?php
/*
 * Template Name: Webinar Schedule – FINAL FIX (Encoding Fixed)
 * Description: Fixes the question mark symbol by using a standard hyphen.
 */

if (!defined('ABSPATH')) { exit; }

/**
 * STEP 1: GET PROVIDER ID
 */
function platform_get_current_provider_id() {
    global $wpdb;
    
    $user_id = get_current_user_id();
    if (!$user_id) return 0;

    // 1. Try User Meta
    $meta_id = get_user_meta($user_id, 'amelia_employee_id', true);
    if ($meta_id) return (int)$meta_id;

    $tbl_users = $wpdb->prefix . 'amelia_users';

    // 2. Try externalId
    $provider_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$tbl_users} WHERE externalId = %d AND type IN ('provider','admin') LIMIT 1",
            $user_id
        )
    );
    if ($provider_id) return (int)$provider_id;

    // 3. Try Email
    $current_user = wp_get_current_user();
    $provider_id_email = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$tbl_users} WHERE email = %s AND type IN ('provider','admin') LIMIT 1",
            $current_user->user_email
        )
    );

    return $provider_id_email ? (int)$provider_id_email : 0;
}

/**
 * STEP 2: FETCH WEBINARS
 */
function platform_get_provider_webinars() {
    global $wpdb;

    $provider_id = platform_get_current_provider_id();
    if (!$provider_id) return [];

    $tbl_events  = $wpdb->prefix . 'amelia_events';
    $tbl_periods = $wpdb->prefix . 'amelia_events_periods';

    // FIX: Changed ORDER BY from DESC to ASC so earliest upcoming webinars appear first
    $sql = $wpdb->prepare("
        SELECT 
            e.id,
            e.name,
            e.status,
            ep.periodStart,
            ep.periodEnd,
            ep.zoomMeeting
        FROM {$tbl_events} e
        INNER JOIN {$tbl_periods} ep
            ON ep.eventId = e.id
        WHERE
            e.status != 'rejected'
            AND e.organizerId = %d
        ORDER BY ep.periodStart ASC
    ", $provider_id);

    return $wpdb->get_results($sql, ARRAY_A);
}

/**
 * STEP 3: SHORTCODE
 */
function platform_webinar_schedule_shortcode() {

    if (!is_user_logged_in()) {
        return '<p style="padding:20px;">Please login to view webinars.</p>';
    }

    $current_user   = wp_get_current_user();
    $user_id        = get_current_user_id();
    $nav_avatar     = get_avatar_url($user_id, ['size' => 36, 'default' => 'mystery']);
    $first_name     = get_user_meta($user_id, 'first_name', true);
    if (!empty(trim($first_name))) {
        $user_name = trim($first_name);
    } elseif (!empty($current_user->display_name) && strpos($current_user->display_name, '@') === false) {
        $user_name = $current_user->display_name;
    } else {
        $user_name = explode('@', $current_user->user_email)[0];
    }

    $provider_id = platform_get_current_provider_id();
    $webinars    = platform_get_provider_webinars();

    // FIX: use current_time('timestamp') for IST-aware "now"
    $now = current_time('timestamp');

    $has_upcoming = false;
    if (!empty($webinars)) {
        foreach ($webinars as $w) {
            // FIX: Amelia stores UTC; add 19800s to compare end time in IST
            if ((strtotime($w['periodEnd']) + 19800) >= $now) {
                $has_upcoming = true;
                break;
            }
        }
    }

    // Nav URLs
    $url_dashboard    = home_url('/webinar_expert');
    $url_calendar     = home_url('/webinar_calender');
    $url_webinars     = get_permalink();
    $url_transactions = home_url('/webinar_payment');

    ob_start();
?>
<style>
/* -- Reset -- */
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',system-ui,sans-serif;background:#f4f6fb;color:#0f172a;}

/* -- NAV -- */
.pc-nav{background:rgba(255,255,255,0.94);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid #e4e7ef;position:sticky;top:0;z-index:200;box-shadow:0 1px 0 #e4e7ef,0 2px 12px rgba(0,0,0,.04);}
.pc-nav-inner{max-width:1400px;margin:auto;padding:0 36px;height:58px;display:flex;justify-content:space-between;align-items:center;}
.pc-nav-logo{font-weight:800;color:#4338ca;font-size:20px;text-decoration:none;letter-spacing:-.5px;}
.pc-nav-links{display:flex;gap:2px;}
.pc-nav-links a{padding:7px 16px;border-radius:8px;font-size:14px;font-weight:500;color:#6b7280;text-decoration:none;transition:background .18s,color .18s;}
.pc-nav-links a:hover{background:#eef2ff;color:#4338ca;}
.pc-nav-links a.active{background:#eef2ff;color:#4338ca;font-weight:600;}
.pc-nav-right{display:flex;align-items:center;gap:14px;}
.pc-nav-right img{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;box-shadow:0 0 0 2px #eef2ff;}
.pc-nav-username{font-weight:600;font-size:13px;color:#0f172a;}
.pc-nav-btn{padding:7px 18px;border-radius:8px;font-size:13px;font-weight:600;background:#0f172a;color:#fff;text-decoration:none;transition:opacity .15s,transform .15s;}
.pc-nav-btn:hover{opacity:.88;transform:translateY(-1px);}
@media(max-width:768px){.pc-nav-links{display:none;}}

/* -- Page layout -- */
.ws-page{max-width:860px;margin:40px auto;padding:0 24px 64px;}

/* -- Page header -- */
.ws-page-header{margin-bottom:28px;}
.ws-page-title{font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-.4px;}
.ws-page-sub{font-size:13px;color:#94a3b8;margin-top:4px;}

/* -- Webinar card -- */
.ws-card{
    background:#fff;
    border:1px solid #e8edf5;
    border-radius:14px;
    padding:18px 20px;
    margin-bottom:12px;
    cursor:pointer;
    display:flex;
    align-items:center;
    gap:18px;
    box-shadow:0 1px 3px rgba(0,0,0,.04);
    transition:box-shadow .2s,border-color .18s,background .18s,transform .14s;
}
.ws-card:hover{
    box-shadow:0 6px 20px rgba(67,56,202,.11);
    border-color:#c7d2fe;
    background:#fafbff;
    transform:translateY(-2px);
}
.ws-card:hover .ws-card-name{color:#4338ca;}
.ws-card:last-child{margin-bottom:0;}

/* Date badge on left */
.ws-date-badge{
    flex-shrink:0;
    width:56px;
    text-align:center;
    background:#f1f5f9;
    border-radius:10px;
    padding:8px 4px;
}
.ws-date-badge .ws-day{font-size:22px;font-weight:800;color:#0f172a;line-height:1;}
.ws-date-badge .ws-month{font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:3px;}

/* Card body */
.ws-card-body{flex:1;min-width:0;}
.ws-card-name{font-size:15px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:color .18s;margin-bottom:5px;}
.ws-card-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.ws-meta-chip{
    display:inline-flex;align-items:center;gap:4px;
    font-size:11px;font-weight:600;color:#64748b;
    background:#f8fafc;border:1px solid #e8edf5;
    border-radius:5px;padding:3px 8px;
}
.ws-meta-chip svg{width:11px;height:11px;flex-shrink:0;}

/* Status badge */
.ws-badge{font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:.3px;white-space:nowrap;flex-shrink:0;}
.ws-badge.upcoming{background:#dcfce7;color:#15803d;}
.ws-badge.completed{background:#f1f5f9;color:#475569;}

/* Right side */
.ws-card-right{display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;}
.ws-join-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 16px;background:#0f172a;color:#fff;
    border-radius:8px;font-size:12px;font-weight:700;
    text-decoration:none;white-space:nowrap;
    transition:background .15s,transform .12s;
}
.ws-join-btn:hover{background:#1e293b;transform:translateY(-1px);}
.ws-join-btn svg{width:13px;height:13px;}

/* Arrow icon on hover */
.ws-arrow{color:#cbd5e1;transition:color .18s,transform .18s;}
.ws-card:hover .ws-arrow{color:#4338ca;transform:translateX(3px);}

/* Empty state */
.ws-empty{
    text-align:center;padding:60px 20px;
    background:#fff;border:1px solid #e8edf5;border-radius:14px;
    color:#94a3b8;font-size:14px;
}
.ws-empty svg{width:40px;height:40px;margin:0 auto 12px;display:block;color:#e2e8f0;}
</style>

<nav class="pc-nav">
    <div class="pc-nav-inner">
        <a href="<?php echo esc_url(home_url()); ?>" class="pc-nav-logo">LOGO</a>
        <div class="pc-nav-links">
            <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
            <a href="<?php echo esc_url($url_calendar); ?>">Calendar</a>
            <a href="<?php echo esc_url($url_webinars); ?>" class="active">Webinars</a>
            <a href="<?php echo esc_url($url_transactions); ?>">Transactions</a>
        </div>
        <div class="pc-nav-right">
            <img src="<?php echo esc_url($nav_avatar); ?>" alt="Profile">
            <span class="pc-nav-username">Hi, <?php echo esc_html($user_name); ?></span>
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pc-nav-btn">Logout</a>
        </div>
    </div>
</nav>

<div class="ws-page">

    <div class="ws-page-header">
        <div class="ws-page-title">Upcoming Webinars</div>
        <div class="ws-page-sub"><?php echo count($webinars); ?> session<?php echo count($webinars) !== 1 ? 's' : ''; ?> scheduled</div>
    </div>

    <?php if (!empty($webinars)): ?>

        <?php if ($has_upcoming): ?>
            <?php foreach ($webinars as $w):
                // FIX: add 19800s offset for IST display of dates and times
                $start_ist = strtotime($w['periodStart']) + 19800;
                $end_ist   = strtotime($w['periodEnd'])   + 19800;
                $zoomData  = json_decode($w['zoomMeeting'], true);
                $joinUrl   = isset($zoomData['joinUrl']) ? $zoomData['joinUrl'] : '';
                $info_url  = home_url('/webinar-info/?event-id=' . intval($w['id']));
                // FIX: compare IST end time against IST now to decide upcoming vs completed
                if ($end_ist < $now) continue;
            ?>
            <div class="ws-card" onclick="window.location='<?php echo esc_js($info_url); ?>'">
                <div class="ws-date-badge">
                    <div class="ws-day"><?php echo date('d', $start_ist); ?></div>
                    <div class="ws-month"><?php echo date('M', $start_ist); ?></div>
                </div>
                <div class="ws-card-body">
                    <div class="ws-card-name"><?php echo esc_html($w['name']); ?></div>
                    <div class="ws-card-meta">
                        <span class="ws-meta-chip">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php echo date('h:i A', $start_ist); ?> &ndash; <?php echo date('h:i A', $end_ist); ?>
                        </span>
                        <span class="ws-badge upcoming">Upcoming</span>
                    </div>
                </div>
                <div class="ws-card-right">
                    <?php if (!empty($joinUrl)): ?>
                    <a href="<?php echo esc_url($joinUrl); ?>"
                       target="_blank"
                       class="ws-join-btn"
                       onclick="event.stopPropagation();">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Join
                    </a>
                    <?php endif; ?>
                    <svg class="ws-arrow" style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </div>
            <?php endforeach; ?>

        <?php else: ?>
            <?php foreach ($webinars as $w):
                // FIX: add 19800s offset for IST display
                $start_ist = strtotime($w['periodStart']) + 19800;
                $end_ist   = strtotime($w['periodEnd'])   + 19800;
                $info_url  = home_url('/webinar-info/?event-id=' . intval($w['id']));
            ?>
            <div class="ws-card" onclick="window.location='<?php echo esc_js($info_url); ?>'">
                <div class="ws-date-badge" style="background:#f8fafc;">
                    <div class="ws-day" style="color:#94a3b8;"><?php echo date('d', $start_ist); ?></div>
                    <div class="ws-month"><?php echo date('M', $start_ist); ?></div>
                </div>
                <div class="ws-card-body">
                    <div class="ws-card-name"><?php echo esc_html($w['name']); ?></div>
                    <div class="ws-card-meta">
                        <span class="ws-meta-chip">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php echo date('h:i A', $start_ist); ?> &ndash; <?php echo date('h:i A', $end_ist); ?>
                        </span>
                        <span class="ws-badge completed">Completed</span>
                    </div>
                </div>
                <div class="ws-card-right">
                    <svg class="ws-arrow" style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else: ?>
        <div class="ws-empty">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            No webinars found for this account.
        </div>
    <?php endif; ?>

</div>

<?php
    return ob_get_clean();
}
add_shortcode('webinar_schedule', 'platform_webinar_schedule_shortcode');