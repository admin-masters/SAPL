<?php
/*
 * Template Name: Perfect Layout Calendar (Status Fixed)
 * Description: UI Fixed + Completed/Upcoming Logic Added + Clickable cards.
 */

if (!defined('ABSPATH')) { exit; }

global $wpdb;

// --- 1. CONFIGURATION ---
// FIX: use current_time('timestamp') for IST-aware "now"
$current_timestamp = current_time('timestamp');

// --- 2. HELPER: GET CURRENT PROVIDER ID ---
function get_my_amelia_provider_id_final() {
    $user_id = get_current_user_id();
    if (!$user_id) return 0;

    $meta_id = get_user_meta($user_id, 'amelia_employee_id', true);
    if ($meta_id) return (int)$meta_id;

    global $wpdb;
    $tbl_users = $wpdb->prefix . 'amelia_users';
    
    $res = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tbl_users} WHERE externalId = %d AND type IN ('provider','admin') LIMIT 1", $user_id));
    if ($res) return (int)$res;

    $current_user = wp_get_current_user();
    $res_email = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tbl_users} WHERE email = %s AND type IN ('provider','admin') LIMIT 1", $current_user->user_email));
    
    return (int)$res_email;
}

$provider_id = get_my_amelia_provider_id_final();

// --- 3. DATES ---
// FIX: use current_time('timestamp') so month/year default to IST date
$m = isset($_GET['cal_m']) ? intval($_GET['cal_m']) : (int)date('n', current_time('timestamp'));
$y = isset($_GET['cal_y']) ? intval($_GET['cal_y']) : (int)date('Y', current_time('timestamp'));

$prev_m = ($m == 1) ? 12 : $m - 1;
$prev_y = ($m == 1) ? $y - 1 : $y;
$next_m = ($m == 12) ? 1 : $m + 1;
$next_y = ($m == 12) ? $y + 1 : $y;

$current_view_label = date('F Y', mktime(0, 0, 0, $m, 1, $y));
$start_date = "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01 00:00:00";
$end_date   = "$y-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-31 23:59:59";

$current_user = wp_get_current_user();
$avatar       = get_avatar_url($current_user->ID);
$user_id      = get_current_user_id();
$nav_avatar   = get_avatar_url($user_id, ['size' => 36, 'default' => 'mystery']);

$first_name = get_user_meta($user_id, 'first_name', true);
if (!empty(trim($first_name))) {
    $user_name = trim($first_name);
} elseif (!empty($current_user->display_name) && strpos($current_user->display_name, '@') === false) {
    $user_name = $current_user->display_name;
} else {
    $user_name = explode('@', $current_user->user_email)[0];
}

// Nav URLs
$url_dashboard    = home_url('/webinar_expert');
$url_calendar     = get_permalink();
$url_webinars     = home_url('/webinar_schedule');
$url_transactions = home_url('/webinar_payment');

// --- 4. DATA FETCHING ---
$calendar_data = [];
$raw_events    = [];

if ($provider_id > 0) {
    $tbl_events  = $wpdb->prefix . 'amelia_events';
    $tbl_periods = $wpdb->prefix . 'amelia_events_periods';

    if ($wpdb->get_var("SHOW TABLES LIKE '$tbl_events'") == $tbl_events) {
        $sql = $wpdb->prepare("
            SELECT 
                e.id, 
                e.name as title, 
                e.color,
                ep.periodStart as start_time,
                ep.periodEnd as end_time,
                'Webinar' as type
            FROM {$tbl_events} e
            INNER JOIN {$tbl_periods} ep ON e.id = ep.eventId
            WHERE ep.periodStart BETWEEN %s AND %s
            AND e.status != 'rejected'
            AND e.organizerId = %d
            ORDER BY ep.periodStart ASC
        ", $start_date, $end_date, $provider_id);
        
        $raw_events = $wpdb->get_results($sql, ARRAY_A);
        
        foreach ($raw_events as $ev) {
            // FIX: Amelia stores UTC; add 19800s (5h30m) offset for IST before grouping by day
            $day = (int)date('j', strtotime($ev['start_time']) + 19800);
            $calendar_data[$day][] = $ev;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Calendar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #F8FAFC; color: #0F172A; }

    /* NAV */
    .pc-nav{background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid #e4e7ef;position:sticky;top:0;z-index:200;box-shadow:0 1px 0 #e4e7ef,0 2px 12px rgba(0,0,0,.04);}
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

    /* LAYOUT */
    .cal-main { width: 100%; display: flex; flex-direction: column; min-height: calc(100vh - 58px); background: #F8FAFC; }
    .content-area { padding: 40px; width: 100%; max-width: 1600px; margin: 0 auto; }

    /* CONTROLS */
    .controls-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; width: 100%; }
    .month-label { font-size: 32px; font-weight: 800; color: #1E293B; line-height: 1.2; }
    .nav-btns { display: flex; background: #fff; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; }
    .nav-btns a { padding: 10px 20px; text-decoration: none; color: #64748B; font-weight: 600; font-size: 14px; transition: 0.2s; background: #fff; }
    .nav-btns a:hover { background: #F1F5F9; color: #0F172A; }
    .nav-btns a:first-child { border-right: 1px solid #E2E8F0; }

    /* GRID */
    .cal-card { background: #fff; border: 1px solid #E2E8F0; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); overflow: hidden; width: 100%; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); width: 100%; }
    .day-head { background: #fff; padding: 15px; text-align: center; font-size: 12px; font-weight: 700; color: #94A3B8; border-right: 1px solid #F1F5F9; border-bottom: 1px solid #E2E8F0; }
    .day-cell { background: #fff; min-height: 140px; border-right: 1px solid #F1F5F9; border-bottom: 1px solid #F1F5F9; padding: 12px; position: relative; }
    .day-cell:nth-child(7n) { border-right: none; }
    .date-num { font-size: 14px; font-weight: 600; color: #334155; margin-bottom: 10px; display: block; }

    /* Calendar event pill — clickable */
    a.event-pill {
        color: #fff;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11px;
        margin-bottom: 6px;
        border-left: 3px solid rgba(255,255,255,0.3);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
        width: 100%;
        font-weight: 500;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        text-decoration: none;
        transition: filter .15s, transform .12s, box-shadow .15s;
    }
    a.event-pill:hover {
        filter: brightness(112%);
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0,0,0,.15);
    }

    /* LIST VIEW */
    .session-section { margin-top: 40px; padding-bottom: 40px; }
    .session-title { font-size: 20px; font-weight: 700; color: #1E293B; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .count-badge { background: #F1F5F9; color: #64748B; padding: 4px 10px; border-radius: 20px; font-size: 13px; }
    .session-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }

    /* Session card — clickable */
    a.session-card {
        display: block;
        background: #fff;
        border: 1px solid #E2E8F0;
        padding: 18px;
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        transition: transform .2s, box-shadow .2s, border-color .18s, background .18s;
    }
    a.session-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(67,56,202,.10);
        border-color: #c7d2fe;
        background: #fafbff;
    }
    a.session-card:hover .session-name { color: #4338ca; }
    .session-name { font-weight: 700; color: #0F172A; font-size: 15px; margin-bottom: 5px; transition: color .18s; }
    .session-time { font-size: 13px; color: #64748B; display: flex; align-items: center; gap: 5px; }

    .badge { font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 4px 8px; border-radius: 4px; letter-spacing: 0.5px; }
    .b-up { background: #EFF6FF; color: #1D4ED8; }
    .b-comp { background: #F1F5F9; color: #64748B; }
</style>
</head>
<body>

<!-- UNIFIED NAVBAR -->
<nav class="pc-nav">
    <div class="pc-nav-inner">
        <a href="<?php echo esc_url(home_url()); ?>" class="pc-nav-logo">LOGO</a>
        <div class="pc-nav-links">
            <a href="<?php echo esc_url($url_dashboard); ?>">Dashboard</a>
            <a href="<?php echo esc_url($url_calendar); ?>" class="active">Calendar</a>
            <a href="<?php echo esc_url($url_webinars); ?>">Webinars</a>
            <a href="<?php echo esc_url($url_transactions); ?>">Transactions</a>
        </div>
        <div class="pc-nav-right">
            <img src="<?php echo esc_url($nav_avatar); ?>" alt="Profile">
            <span class="pc-nav-username">Hi, <?php echo esc_html($user_name); ?></span>
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="pc-nav-btn">Logout</a>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="cal-main">
    <div class="content-area">
        
        <div class="controls-bar">
            <div class="month-label"><?php echo esc_html($current_view_label); ?></div>
            <div class="nav-btns">
                <a href="?cal_m=<?php echo $prev_m; ?>&cal_y=<?php echo $prev_y; ?>">&lt; Prev</a>
                <a href="?cal_m=<?php echo $next_m; ?>&cal_y=<?php echo $next_y; ?>">Next &gt;</a>
            </div>
        </div>

        <div class="cal-card">
            <div class="cal-grid">
                <div class="day-head">SUN</div><div class="day-head">MON</div><div class="day-head">TUE</div>
                <div class="day-head">WED</div><div class="day-head">THU</div><div class="day-head">FRI</div>
                <div class="day-head">SAT</div>

                <?php
                $start_offset  = date('w', mktime(0, 0, 0, $m, 1, $y));
                $days_in_month = date('t', mktime(0, 0, 0, $m, 1, $y));

                for ($k = 0; $k < $start_offset; $k++) {
                    echo '<div class="day-cell" style="background:#F8FAFC;"></div>';
                }

                for ($day = 1; $day <= $days_in_month; $day++) {
                    echo '<div class="day-cell">';
                    echo '<span class="date-num">' . $day . '</span>';
                    
                    if (isset($calendar_data[$day])) {
                        foreach ($calendar_data[$day] as $ev) {
                            // FIX: add 19800s offset to display correct IST time in pill
                            $time     = date('h:i A', strtotime($ev['start_time']) + 19800);
                            $color    = !empty($ev['color']) ? $ev['color'] : '#2563EB';
                            $pill_url = home_url('/webinar-info/?event-id=' . intval($ev['id']));
                            echo "<a class='event-pill' style='background:{$color};' href='" . esc_url($pill_url) . "' title='" . esc_attr($ev['title']) . "'>";
                            echo "<span>{$time}&nbsp;</span>";
                            echo "<span>" . esc_html($ev['title']) . "</span>";
                            echo "</a>";
                        }
                    }
                    echo '</div>';
                }
                
                $total_cells = $start_offset + $days_in_month;
                while ($total_cells % 7 != 0) {
                    echo '<div class="day-cell" style="background:#F8FAFC;"></div>';
                    $total_cells++;
                }
                ?>
            </div>
        </div>
        
        <!-- SESSION LIST -->
        <div class="session-section">
            <div class="session-title">
                All Sessions <span class="count-badge"><?php echo count($raw_events); ?></span>
            </div>

            <?php if (empty($raw_events)): ?>
                <div style="text-align:center;padding:40px;border:1px dashed #CBD5E1;border-radius:12px;color:#64748B;">
                    No webinars found for <?php echo esc_html($current_view_label); ?>.
                </div>
            <?php else: ?>
                <div class="session-grid">
                    <?php foreach ($raw_events as $ev):
                        // FIX: add 19800s offset so completed/upcoming comparison is IST-aware
                        $end_ts_ist  = strtotime($ev['end_time']) + 19800;
                        $is_past     = $end_ts_ist < $current_timestamp;
                        $badge_text  = $is_past ? 'COMPLETED' : 'UPCOMING';
                        $badge_class = $is_past ? 'b-comp' : 'b-up';
                        $card_url    = home_url('/webinar-info/?event-id=' . intval($ev['id']));
                        // FIX: display date/time in IST
                        $start_ist   = strtotime($ev['start_time']) + 19800;
                    ?>
                        <a href="<?php echo esc_url($card_url); ?>" class="session-card">
                            <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                            </div>
                            <div class="session-name">
                                <?php echo esc_html($ev['title']); ?>
                            </div>
                            <div class="session-time">
                                <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <?php echo date('M d, Y', $start_ist); ?> @ <?php echo date('h:i A', $start_ist); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

</body>
</html>