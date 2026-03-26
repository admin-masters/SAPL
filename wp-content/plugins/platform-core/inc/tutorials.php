<?php
/**
 * Platform Core — Flow 4: Student books tutorials (1:1 / group)
 * - Seed Amelia Services (1:1, Group)
 * - Ensure /tutorials (booking) + /my-tutorials (Customer Panel)
 * - Hook Amelia booking lifecycle to calendar insert (wp_platform_calendar_map + Google Calendar)
 *
 * Requires: Amelia (Elite API), WooCommerce + Razorpay, Flow-2 Google settings, wp_platform_calendar_map.
 */
if (!defined('ABSPATH')) exit;

/* ---------------------------
   0) Shared helpers (re-use)
----------------------------*/
// if (!function_exists('platform_core_amelia_api_headers')) {
//     function platform_core_amelia_api_headers() {
//         return [
//             'Content-Type' => 'application/json',
//             'Amelia'       => get_option('platform_amelia_api_key', '')
//         ];
//     }
// } 
if (!function_exists('platform_core_calendar_insert')) {
    // If Flow-2 not loaded, provide a minimal fallback (no-op).
    function platform_core_calendar_insert($evt) { /* Fallback — Flow-2 provides the real writer */ }
}
if (!function_exists('platform_core_google_update_event_id')) {
    function platform_core_google_update_event_id($calendarId, $eventId, $start, $end) { return false; }
}
if (!function_exists('platform_core_google_delete_event_id')) {
    function platform_core_google_delete_event_id($calendarId, $eventId) { return false; }
}
// function platform_core_amelia_api_base($path) {
//     return admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1' . $path);
// } 

/* --------------------------------------------
   1) Pages: /tutorials + /my-tutorials
---------------------------------------------*/
add_action('init', function () {
    // /tutorials — Catalog booking form
    if (!get_page_by_path('tutorials')) {
        wp_insert_post([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_title'  => 'Tutorials',
            'post_name'   => 'tutorials',
            'post_content'=> '[ameliacatalogbooking]' // Catalog 2.0 (can filter by category later)
        ]);
    }
    // /my-tutorials — Customer panel (appointments only)
    if (!get_page_by_path('my-tutorials')) {
        wp_insert_post([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_title'  => 'My Tutorials',
            'post_name'   => 'my-tutorials',
            'post_content'=> '[ameliacustomerpanel appointments=1]'
        ]);
    }
});

/* --------------------------------------------
   2) Admin: seed services (category + services)
---------------------------------------------*/
add_action('admin_menu', function () {
    add_submenu_page(
        'options-general.php',
        'Platform Tutorials',
        'Platform Tutorials',
        'manage_options',
        'platform-tutorials',
        'platform_core_tutorials_admin_page'
    );
});

function platform_core_tutorials_admin_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['platform_tutorials_save']) && check_admin_referer('platform_tutorials_save')) {
        update_option('platform_tutorials_group_cap', max(2, (int)($_POST['platform_tutorials_group_cap'] ?? 5)));
        update_option('platform_tutorials_duration_mins', max(15, (int)($_POST['platform_tutorials_duration_mins'] ?? 60)));
        update_option('platform_tutorials_price_1to1', (float)($_POST['platform_tutorials_price_1to1'] ?? 0));
        update_option('platform_tutorials_price_group', (float)($_POST['platform_tutorials_price_group'] ?? 0));
        echo '<div class="updated"><p>Saved.</p></div>';
    }
    if (isset($_POST['platform_tutorials_seed']) && check_admin_referer('platform_tutorials_save')) {
        $result = platform_core_seed_tutorial_services();
        if (is_wp_error($result)) {
            echo '<div class="error"><p>Error: '.esc_html($result->get_error_message()).'</p></div>';
        } else {
            echo '<div class="updated"><p>Services seeded/updated.</p></div>';
        }
    }

    $cap  = (int) get_option('platform_tutorials_group_cap', 5);
    $dur  = (int) get_option('platform_tutorials_duration_mins', 60);
    $p1   = (float) get_option('platform_tutorials_price_1to1', 0);
    $pg   = (float) get_option('platform_tutorials_price_group', 0);
    ?>
    <div class="wrap">
      <h1>Platform Tutorials</h1>
      <form method="post">
        <?php wp_nonce_field('platform_tutorials_save'); ?>
        <table class="form-table" role="presentation">
          <tr><th><label>Group max capacity</label></th><td><input type="number" name="platform_tutorials_group_cap" min="2" step="1" value="<?php echo esc_attr($cap); ?>"></td></tr>
          <tr><th><label>Service duration (minutes)</label></th><td><input type="number" name="platform_tutorials_duration_mins" min="15" step="15" value="<?php echo esc_attr($dur); ?>"></td></tr>
          <tr><th><label>Price — 1:1 Tutorial</label></th><td><input type="number" name="platform_tutorials_price_1to1" min="0" step="0.01" value="<?php echo esc_attr($p1); ?>"></td></tr>
          <tr><th><label>Price — Group Tutorial</label></th><td><input type="number" name="platform_tutorials_price_group" min="0" step="0.01" value="<?php echo esc_attr($pg); ?>"></td></tr>
        </table>
        <p class="submit">
          <button class="button button-primary" name="platform_tutorials_save" value="1">Save settings</button>
          <button class="button" name="platform_tutorials_seed" value="1">Create/Update Tutorial Services</button>
        </p>
        <p><em>Notes:</em> Services are created via the official Amelia **Services API** with required fields:
           name, categoryId, providers, duration, minCapacity, maxCapacity, price. We set <code>show=true</code>
           so they appear on the booking form.</p>
      </form>
    </div>
    <?php
}

/* Seed helpers */
function platform_core_seed_tutorial_services() {
    // Ensure there's at least one employee to assign services to
    $employees = platform_core_amelia_get_employees();
    if (is_wp_error($employees)) return $employees;
    if (empty($employees)) return new WP_Error('no_employees', 'No Amelia employees found. Create at least one expert employee first.');

    // Get or create "Tutorials" category
    $categoryId = platform_core_amelia_get_or_create_category('Tutorials');
    if (!$categoryId) return new WP_Error('category', 'Could not create/find "Tutorials" category.');

    $durationSec = ((int)get_option('platform_tutorials_duration_mins', 60)) * 60;
    $capGroup    = (int) get_option('platform_tutorials_group_cap', 5);
    $price1      = (float) get_option('platform_tutorials_price_1to1', 0);
    $priceG      = (float) get_option('platform_tutorials_price_group', 0);

    // 1) 1:1 Tutorial
    $svc1 = platform_core_amelia_upsert_service([
        'name'        => '1:1 Tutorial',
        'categoryId'  => $categoryId,
        'providers'   => [],   // DO NOT auto-assign providers. Assign manually in Amelia.
        'duration'    => $durationSec,
        'minCapacity' => 1,
        'maxCapacity' => 1,
        'price'       => $price1,
        'bringingAnyone' => false,
        'aggregatedPrice'=> true,
        'show'        => true,
        'settings'    => json_encode(['zoom'=>['enabled'=>true]])
    ]);
    if (is_wp_error($svc1)) return $svc1;
    update_option('platform_tutorials_service_1to1_id', (int)$svc1['id']);

    // 2) Group Tutorial
    $svc2 = platform_core_amelia_upsert_service([
        'name'        => 'Group Tutorial',
        'categoryId'  => $categoryId,
        'providers'   => [],   // DO NOT auto-assign providers. Assign manually in Amelia.
        'duration'    => $durationSec,
        'minCapacity' => 1,
        'maxCapacity' => max(2, $capGroup),
        'price'       => $priceG,
        'bringingAnyone' => false,  // each student books their own seat
        'aggregatedPrice'=> true,
        'show'        => true,
        'settings'    => json_encode(['zoom'=>['enabled'=>true]])
    ]);
    if (is_wp_error($svc2)) return $svc2;
    update_option('platform_tutorials_service_group_id', (int)$svc2['id']);

    return true;
}

function platform_core_amelia_get_employees() {
    $url = platform_core_amelia_api_base('/employees');
    $res = wp_remote_get($url, ['headers'=>platform_core_amelia_api_headers(), 'timeout'=>20]);
    if (is_wp_error($res)) return $res;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    $ids  = [];
    if (!empty($data['data']['items'])) {
        foreach ($data['data']['items'] as $e) $ids[] = (int)$e['id'];
    }
    return $ids;
}
function platform_core_amelia_get_or_create_category($name) {
    // Try to find
    $url = platform_core_amelia_api_base('/categories');
    $res = wp_remote_get($url, ['headers'=>platform_core_amelia_api_headers(), 'timeout'=>20]);
    if (!is_wp_error($res)) {
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($data['data']['items'])) {
            foreach ($data['data']['items'] as $cat) {
                if (strcasecmp($cat['name'] ?? '', $name) === 0) return (int)$cat['id'];
            }
        }
    }
    // Create
    $res2 = wp_remote_post(platform_core_amelia_api_base('/categories'), [
        'headers'=>platform_core_amelia_api_headers(),
        'body'   => wp_json_encode(['name'=>$name]),
        'timeout'=>20
    ]);
    if (is_wp_error($res2)) return 0;
    $data2 = json_decode(wp_remote_retrieve_body($res2), true);
    return (int)($data2['data']['category']['id'] ?? 0);
}
function platform_core_amelia_upsert_service($svc) {
    // Look up by name
    $existing = platform_core_amelia_find_service_by_name($svc['name']);
    $payload  = [
        'name'        => $svc['name'],
        'categoryId'  => (int)$svc['categoryId'],
        'providers'   => array_map('intval', $svc['providers']),
        'duration'    => (int)$svc['duration'],
        'minCapacity' => (int)$svc['minCapacity'],
        'maxCapacity' => (int)$svc['maxCapacity'],
        'price'       => (float)$svc['price'],
        'bringingAnyone' => !empty($svc['bringingAnyone']),
        'aggregatedPrice'=> !empty($svc['aggregatedPrice']),
        'show'        => true,
        // Ensure settings is an array/object — Amelia expects structured data.
        'settings'    => (is_string($svc['settings']) ? json_decode($svc['settings'], true) : ($svc['settings'] ?? (object)[]))
    ];
    if ($existing) {
        $url = platform_core_amelia_api_base('/services/'.(int)$existing['id']);
        platform_core_log_amelia('Updating service ' . $existing['id'], $payload);
        $res = wp_remote_post($url, [
            'headers'=> array_merge(['Content-Type' => 'application/json'], platform_core_amelia_api_headers()),
            'body'   => wp_json_encode($payload),
            'timeout'=>25
        ]);
        if (is_wp_error($res)) return $res;
        $d = json_decode(wp_remote_retrieve_body($res), true);
        return $d['data']['service'] ?? $existing;
    } else {
        $url = platform_core_amelia_api_base('/services');
        platform_core_log_amelia('Creating service ' . $svc['name'], $payload);
        $res = wp_remote_post($url, [
            'headers'=> array_merge(['Content-Type' => 'application/json'], platform_core_amelia_api_headers()),
            'body'   => wp_json_encode($payload),
            'timeout'=>25
        ]);
        if (is_wp_error($res)) return $res;
        $d = json_decode(wp_remote_retrieve_body($res), true);
        return $d['data']['service'] ?? new WP_Error('service', 'Service not created');
    }
}
function platform_core_amelia_find_service_by_name($name) {
    $res = wp_remote_get(platform_core_amelia_api_base('/services'), [
        'headers'=>platform_core_amelia_api_headers(),
        'timeout'=>20
    ]);
    if (is_wp_error($res)) return null;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!empty($data['data']['items'])) {
        foreach ($data['data']['items'] as $s) {
            if (strcasecmp($s['name'] ?? '', $name) === 0) return $s;
        }
    }
    return null;
}

/* -----------------------------------------------------
   3) Calendar writer for tutorial bookings (hooks)
   We hook Amelia's Booking status + reschedule + cancel.
   (Official hook names and timing documented by Amelia.)
------------------------------------------------------*/
add_action('AmeliaAppointmentBookingStatusUpdated', function($reservation, $bookings, $container) {
    // When Woo order completes & Amelia approves, insert calendar row for each student.
    try {
        $appointmentId = platform_core_extract_appointment_id($reservation, $bookings);
        if (!$appointmentId) return;

        $appt = platform_core_amelia_get_appointment($appointmentId);
        if (!$appt || !isset($appt['status']) || strtolower($appt['status']) !== 'approved') return; // only for approved

        foreach ((array)$appt['bookings'] as $b) {
            // Fetch student email (via Customers API if not embedded)
            $email = platform_core_booking_email($b);
            if (!$email) continue;

            $start = $appt['bookingStart'] ?? '';
            $end   = $appt['bookingEnd']   ?? '';
            $zoom  = '';
            if (!empty($appt['zoomMeeting']['joinUrl'])) $zoom = $appt['zoomMeeting']['joinUrl'];
            elseif (!empty($appt['googleMeetUrl']))      $zoom = $appt['googleMeetUrl'];

            $summary = 'Tutorial Session';
            // Try to enrich with service/employee names
            $summary = platform_core_tutorial_summary($appt, $summary);

            platform_core_calendar_insert([
                'user_id'     => 0, // unknown/not required
                'email'       => $email,
                'source'      => 'tutorial',
                'source_ref'  => 'amelia:appointment:'.$appointmentId.':booking:'.($b['id'] ?? '0'),
                'starts_at'   => $start,
                'ends_at'     => $end,
                'summary'     => $summary,
                'description' => ($zoom ? "Join: $zoom\n\n" : '') . 'Added via platform-core',
                'location'    => $zoom ?: get_bloginfo('name'),
                'zoom_url'    => $zoom
            ]);
        }
    } catch (\Throwable $e) { /* swallow */ }
}, 10, 3);
add_action('amelia_after_booking_rescheduled', function($oldAppointment, $booking, $bookingStart) {
    global $wpdb;
    $appointmentId = $booking['appointmentId'] ?? ($oldAppointment['id'] ?? 0);
    if (!$appointmentId) return;

    $appt = platform_core_amelia_get_appointment($appointmentId);
    if (!$appt) return;
    $start = $appt['bookingStart'] ?? $bookingStart;
    $end   = $appt['bookingEnd']   ?? $bookingStart;

    $table = $wpdb->prefix.'platform_calendar_map';
    $rows  = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE source=%s AND source_ref LIKE %s",
             'tutorial', $wpdb->esc_like('amelia:appointment:'.$appointmentId.':booking:').'%'), ARRAY_A);

    if ($rows) {
        foreach ($rows as $row) {
            if (!empty($row['event_id'])) {
                platform_core_google_update_event_id($row['calendar_id'] ?: get_option('platform_google_calendar_id',''), $row['event_id'], $start, $end);
                $wpdb->update($table, [
                    'starts_at'=>gmdate('Y-m-d H:i:s', strtotime($start.' UTC')),
                    'ends_at'  =>gmdate('Y-m-d H:i:s', strtotime($end.' UTC')),
                    'updated_at'=>current_time('mysql')
                ], ['id'=>$row['id']]);
            }
        }
    }
}, 10, 3);
add_action('amelia_after_booking_canceled', function($booking) {
    global $wpdb;
    $appointmentId = $booking['appointmentId'] ?? 0;
    $bookingId     = $booking['id'] ?? 0;
    if (!$appointmentId || !$bookingId) return;

    $table = $wpdb->prefix.'platform_calendar_map';
    $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE source=%s AND source_ref=%s LIMIT 1",
            'tutorial', 'amelia:appointment:'.$appointmentId.':booking:'.$bookingId), ARRAY_A);
    if ($row && !empty($row['event_id'])) {
        platform_core_google_delete_event_id($row['calendar_id'] ?: get_option('platform_google_calendar_id',''), $row['event_id']);
        $wpdb->update($table, ['status'=>'cancelled','updated_at'=>current_time('mysql')], ['id'=>$row['id']]);
    }
}, 10, 1);

/* Data access helpers */
function platform_core_extract_appointment_id($reservation, $bookings) {
    // Best-effort extraction across possible shapes.
    if (is_array($reservation)) {
        if (!empty($reservation['appointmentId'])) return (int)$reservation['appointmentId'];
        if (!empty($reservation['appointment']['id'])) return (int)$reservation['appointment']['id'];
    }
    if (is_array($bookings) && !empty($bookings[0]['appointmentId'])) return (int)$bookings[0]['appointmentId'];
    return 0;
}
function platform_core_booking_email($booking) {
    // Amelia sometimes includes customer object; otherwise fetch via Customers API
    if (!empty($booking['customer']['email'])) return sanitize_email($booking['customer']['email']);
    if (!empty($booking['customerId'])) {
        $cust = platform_core_amelia_get_customer((int)$booking['customerId']);
        if (!empty($cust['email'])) return sanitize_email($cust['email']);
    }
    return '';
}
function platform_core_tutorial_summary($appt, $fallback='Tutorial Session') {
    $name = $fallback;
    try {
        $serviceName  = '';
        $employeeName = '';
        if (!empty($appt['serviceId'])) {
            $svc = platform_core_amelia_get_service((int)$appt['serviceId']);
            $serviceName = $svc['name'] ?? '';
        }
        if (!empty($appt['providerId'])) {
            $emp = platform_core_amelia_get_employee((int)$appt['providerId']);
            $employeeName = trim(($emp['firstName'] ?? '').' '.($emp['lastName'] ?? ''));
        }
        if ($serviceName && $employeeName) $name = "$serviceName with $employeeName";
        elseif ($serviceName) $name = $serviceName;
    } catch (\Throwable $e) {}
    return $name;
}
function platform_core_amelia_get_appointment($id) {
    $res = wp_remote_get(platform_core_amelia_api_base('/appointments/'.(int)$id), [
        'headers'=>platform_core_amelia_api_headers(),'timeout'=>20
    ]);
    if (is_wp_error($res)) return null;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['data']['appointment'] ?? null; // fields include bookingStart, bookingEnd, status, zoomMeeting, bookings[], serviceId, providerId
}
function platform_core_amelia_get_customer($id) {
    $res = wp_remote_get(platform_core_amelia_api_base('/customers/'.(int)$id), [
        'headers'=>platform_core_amelia_api_headers(),'timeout'=>20
    ]);
    if (is_wp_error($res)) return [];
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['data']['customer'] ?? [];
}
function platform_core_amelia_get_service($id) {
    $res = wp_remote_get(platform_core_amelia_api_base('/services/'.(int)$id), [
        'headers'=>platform_core_amelia_api_headers(),'timeout'=>20
    ]);
    if (is_wp_error($res)) return [];
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['data']['service'] ?? [];
}
function platform_core_amelia_get_employee($id) {
    $res = wp_remote_get(platform_core_amelia_api_base('/employees/'.(int)$id), [
        'headers'=>platform_core_amelia_api_headers(),'timeout'=>20
    ]);
    if (is_wp_error($res)) return [];
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['data']['employee'] ?? [];
}