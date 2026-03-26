<?php
/**
 * Flow 5 – Expert provides tutorials (re-implemented without page creation)
 * - Employee Panel is manual (shortcode on your existing page, e.g. /expert/panel).
 * - Payout rail: insert/refresh rows based on Amelia appointment lifecycle; monthly CSV export.
 * - ZERO-DUPLICATION GUARANTEE: no wp_insert_post calls anywhere; DB writes use REPLACE with UNIQUE key.
 *
 * Requires: Amelia (with API key set in platform settings used in other flows), WooCommerce present for order lookup (optional).
 */
if (!defined('ABSPATH')) exit;

/* -----------------------------------
 * 0) Admin menu: Payout settings + CSV
 * -----------------------------------*/
add_action('admin_menu', function () {
    add_submenu_page(
        'options-general.php',
        'Platform Payouts',
        'Platform Payouts',
        'manage_options',
        'platform-payouts',
        'platform_core_flow5_render_payouts_admin'
    );
});

function platform_core_flow5_render_payouts_admin() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['platform_flow5_save']) && check_admin_referer('platform_flow5_save')) {
        update_option('platform_flow5_platform_fee_percent', (float)($_POST['platform_flow5_platform_fee_percent'] ?? 20));
        update_option('platform_flow5_platform_fee_fixed', (float)($_POST['platform_flow5_platform_fee_fixed'] ?? 0));
        update_option('platform_flow5_processor_fee_percent', (float)($_POST['platform_flow5_processor_fee_percent'] ?? 2));
        update_option('platform_flow5_processor_fee_fixed', (float)($_POST['platform_flow5_processor_fee_fixed'] ?? 3));
        echo '<div class="updated"><p>Saved.</p></div>';
    }

    if (isset($_POST['platform_flow5_backfill']) && check_admin_referer('platform_flow5_save')) {
        $n = platform_core_flow5_backfill('-48 hours');
        echo '<div class="updated"><p>Backfill done. Affected rows: '.intval($n).'.</p></div>';
    }

    ?>
    <div class="wrap">
      <h1>Platform Payouts (Tutorials)</h1>
      <p><strong>Employee Panel page is NOT created by code.</strong> Make sure you have a page (e.g. <code>/expert/panel</code>) with <code>[ameliaemployeepanel]</code>. No automatic page creation occurs here (prevents duplicates).</p>
      <form method="post">
        <?php wp_nonce_field('platform_flow5_save'); ?>
        <h2>Fee Model</h2>
        <table class="form-table" role="presentation">
          <tr><th>Platform fee (%)</th><td><input name="platform_flow5_platform_fee_percent" type="number" step="0.01" value="<?php echo esc_attr(get_option('platform_flow5_platform_fee_percent',20)); ?>"></td></tr>
          <tr><th>Platform fee (fixed)</th><td><input name="platform_flow5_platform_fee_fixed" type="number" step="0.01" value="<?php echo esc_attr(get_option('platform_flow5_platform_fee_fixed',0)); ?>"></td></tr>
          <tr><th>Processor fee (%)</th><td><input name="platform_flow5_processor_fee_percent" type="number" step="0.01" value="<?php echo esc_attr(get_option('platform_flow5_processor_fee_percent',2)); ?>"></td></tr>
          <tr><th>Processor fee (fixed)</th><td><input name="platform_flow5_processor_fee_fixed" type="number" step="0.01" value="<?php echo esc_attr(get_option('platform_flow5_processor_fee_fixed',3)); ?>"></td></tr>
        </table>
        <p class="submit">
          <button class="button button-primary" name="platform_flow5_save" value="1">Save</button>
          <button class="button" name="platform_flow5_backfill" value="1">Insert/Update last 48h</button>
        </p>
      </form>

      <hr/>
      <h2>Export CSV (monthly)</h2>
      <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="platform_core_flow5_export_csv" />
        <label>Month (YYYY-MM) <input type="text" name="month" placeholder="2025-01" required></label>
        <button class="button">Download</button>
      </form>
    </div>
    <?php
}

/* ---------------------------------------------
 * 1) DB Tables (run once, idempotent, no pages)
 *    — payouts table + certificates table merged
 *    Runs on plugins_loaded (front + admin) so the
 *    table exists before any front-end query runs.
 *    Uses a version option so dbDelta only fires
 *    when the schema actually needs creating/updating.
 * --------------------------------------------*/
function platform_core_create_db_tables() {
    if ( get_option('platform_core_db_version') === '1.1' ) return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // --- Payouts table ---
    $payouts_table = $wpdb->prefix . 'platform_payouts';
    $payouts_sql = "CREATE TABLE IF NOT EXISTS $payouts_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source VARCHAR(40) NOT NULL,
        appointment_id BIGINT UNSIGNED NOT NULL,
        booking_id BIGINT UNSIGNED NOT NULL,
        service_id BIGINT UNSIGNED NULL,
        service_name VARCHAR(190) NULL,
        expert_employee_id BIGINT UNSIGNED NULL,
        expert_user_id BIGINT UNSIGNED NULL,
        expert_email VARCHAR(190) NULL,
        student_email VARCHAR(190) NULL,
        order_id BIGINT UNSIGNED NULL,
        currency VARCHAR(10) NOT NULL DEFAULT 'INR',
        gross DECIMAL(12,2) NOT NULL DEFAULT 0,
        processor_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        platform_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        net DECIMAL(12,2) NOT NULL DEFAULT 0,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
        payout_month CHAR(7) NOT NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_booking (source, appointment_id, booking_id),
        KEY month (payout_month),
        KEY expert (expert_user_id),
        KEY order_id (order_id)
    ) {$wpdb->get_charset_collate()};";
    dbDelta($payouts_sql);

    // --- Webinar certificates table ---
    $certs_table = $wpdb->prefix . 'webinar_certificates';
    $certs_sql = "CREATE TABLE IF NOT EXISTS $certs_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        certificate_id VARCHAR(50) NOT NULL,
        event_id BIGINT UNSIGNED NOT NULL,
        period_id BIGINT UNSIGNED NOT NULL,
        student_user_id BIGINT UNSIGNED NOT NULL,
        student_email VARCHAR(190) NOT NULL,
        student_name VARCHAR(190) NOT NULL,
        webinar_title VARCHAR(500) NOT NULL,
        completion_date DATETIME NOT NULL,
        duration_minutes INT UNSIGNED NOT NULL,
        instructor_name VARCHAR(190) NULL,
        html_content LONGTEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_cert (certificate_id),
        UNIQUE KEY uniq_student_event (student_user_id, event_id, period_id),
        KEY student (student_user_id),
        KEY event (event_id),
        KEY status (status)
    ) {$wpdb->get_charset_collate()};";
    dbDelta($certs_sql);

    update_option('platform_core_db_version', '1.1');
}
// Run on every page load (front + admin) — version gate keeps it fast after first run
add_action('plugins_loaded', 'platform_core_create_db_tables');

// Certificate settings must stay in admin_init
add_action('admin_init', function() {
    register_setting('webinar_cert_settings', 'platform_director_name');
    register_setting('webinar_cert_settings', 'platform_company_address');
});

/* ---------------------------------------------
 * 2) Amelia hooks -> upsert payout entries
 * --------------------------------------------*/

add_action('AmeliaAppointmentBookingStatusUpdated', function($reservation, $bookings, $container) {
    try {
        $appointmentId = platform_core_flow5_extract_appt_id($reservation, $bookings);
        if (!$appointmentId) return;

        $appt = platform_core_flow5_get_appointment($appointmentId);
        if (!$appt || strtolower($appt['status'] ?? '') !== 'approved') return;

        foreach ((array)($appt['bookings'] ?? []) as $b) {
            platform_core_flow5_upsert_payout_row($appt, $b);
        }
    } catch (\Throwable $e) { /* no-op */ }
}, 10, 3);

add_action('amelia_after_booking_rescheduled', function($oldAppointment, $booking, $bookingStart) {
    try {
        $appointmentId = (int)($booking['appointmentId'] ?? ($oldAppointment['id'] ?? 0));
        if (!$appointmentId) return;

        $appt = platform_core_flow5_get_appointment($appointmentId);
        if (!$appt) return;

        foreach ((array)($appt['bookings'] ?? []) as $b) {
            platform_core_flow5_upsert_payout_row($appt, $b);
        }
    } catch (\Throwable $e) { /* no-op */ }
}, 10, 3);

add_action('amelia_after_booking_canceled', function($booking) {
    global $wpdb;
    $appointmentId = (int)($booking['appointmentId'] ?? 0);
    $bookingId     = (int)($booking['id'] ?? 0);
    if (!$appointmentId || !$bookingId) return;
    $table = $wpdb->prefix . 'platform_payouts';
    $wpdb->update($table, ['status'=>'cancelled','updated_at'=>current_time('mysql')],
        ['source'=>'tutorial','appointment_id'=>$appointmentId,'booking_id'=>$bookingId]);
}, 10, 1);

/* ---------------------------------------------
 * 3) Cron jobs (payouts + certificate generation)
 * --------------------------------------------*/
add_action('init', function () {
    // Payout hourly cron
    if (!wp_next_scheduled('platform_core_flow5_hourly')) {
        wp_schedule_event(time() + 60, 'hourly', 'platform_core_flow5_hourly');
    }
    // Certificate daily cron
    if (!wp_next_scheduled('platform_generate_webinar_certificates')) {
        wp_schedule_event(time(), 'daily', 'platform_generate_webinar_certificates');
    }
    // URL rewrite rule for certificates
    add_rewrite_rule(
        '^certificate/webinar/([^/]+)/?$',
        'index.php?webinar_certificate_id=$matches[1]',
        'top'
    );
});

add_action('platform_core_flow5_hourly', function () {
    platform_core_flow5_backfill('-24 hours');
    platform_core_flow5_finalize();
});

// Generate certificates for all completed webinars (daily cron)
add_action('platform_generate_webinar_certificates', function() {
    global $wpdb;

    $now = current_time('mysql', true);

    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT
            cb.customerId,
            ep.eventId,
            ep.id AS periodId,
            u.email
        FROM {$wpdb->prefix}amelia_customer_bookings cb
        INNER JOIN {$wpdb->prefix}amelia_customer_bookings_to_events_periods x
            ON cb.id = x.customerBookingId
        INNER JOIN {$wpdb->prefix}amelia_events_periods ep
            ON x.eventPeriodId = ep.id
        INNER JOIN {$wpdb->prefix}amelia_users u
            ON u.id = cb.customerId
        INNER JOIN {$wpdb->prefix}amelia_payments p
            ON p.customerBookingId = cb.id
        WHERE cb.status = 'approved'
          AND p.status = 'paid'
          AND ep.periodEnd < %s
          AND ep.periodEnd > DATE_SUB(%s, INTERVAL 90 DAY)",
        $now, $now
    ));

    $generated = 0;
    foreach ($bookings as $booking) {
        $wp_user = get_user_by('email', $booking->email);
        if (!$wp_user) continue;
        $cert_id = platform_generate_webinar_certificate($wp_user->ID, $booking->eventId, $booking->periodId);
        if ($cert_id) $generated++;
    }

    error_log("Generated {$generated} webinar certificates");
});

/* ---------------------------------------------
 * 4) CSV export (monthly)
 * --------------------------------------------*/
add_action('admin_post_platform_core_flow5_export_csv', function () {
    if (!is_user_logged_in()) wp_die('Unauthorized');

    global $wpdb;
    $user_id           = get_current_user_id();
    $table_payouts     = $wpdb->prefix . 'platform_payouts';
    $table_amelia_book = $wpdb->prefix . 'amelia_customer_bookings';
    $table_amelia_app  = $wpdb->prefix . 'amelia_appointments';
    $table_amelia_svc  = $wpdb->prefix . 'amelia_services';
    $table_amelia_usr  = $wpdb->prefix . 'amelia_users';

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT
            p.amelia_booking_id,
            p.amount_gross,
            p.amount_net,
            p.fee_platform,
            p.status,
            p.month_key,
            a.bookingStart,
            svc.name        AS service_name,
            svc.duration    AS service_duration,
            cu.firstName    AS customer_first,
            cu.lastName     AS customer_last
        FROM $table_payouts p
        LEFT JOIN $table_amelia_book b   ON b.id = p.amelia_booking_id
        LEFT JOIN $table_amelia_app  a   ON a.id = b.appointmentId
        LEFT JOIN $table_amelia_svc  svc ON svc.id = a.serviceId
        LEFT JOIN $table_amelia_usr  cu  ON cu.id = b.customerId AND cu.type = 'customer'
        WHERE p.expert_user_id = %d
        ORDER BY p.created_at DESC
    ", $user_id));

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions-' . date('Y-m') . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Booking ID', 'User', 'Service', 'Date', 'Time', 'Duration', 'Gross', 'Net', 'Platform Fee', 'Status', 'Month']);

    foreach ($rows as $r) {
        $name     = trim(($r->customer_first ?? '') . ' ' . ($r->customer_last ?? '')) ?: 'Unknown';
        $duration = $r->service_duration ? floor($r->service_duration / 60) . ' min' : '—';
        $date     = $r->bookingStart ? wp_date('M j, Y', strtotime($r->bookingStart)) : '—';
        $time     = $r->bookingStart ? wp_date('g:i A', strtotime($r->bookingStart)) : '—';

        fputcsv($out, [
            $r->amelia_booking_id,
            $name,
            $r->service_name ?? '—',
            $date,
            $time,
            $duration,
            $r->amount_gross,
            $r->amount_net,
            $r->fee_platform,
            $r->status,
            $r->month_key
        ]);
    }

    fclose($out);
    exit;
});

/* ---------------------------------------------
 * 5) Query vars for certificate URLs
 * --------------------------------------------*/
add_filter('query_vars', function($vars) {
    $vars[] = 'webinar_certificate_id';
    return $vars;
});

/* ---------------------------------------------
 * 6) Certificate display on template_redirect
 * --------------------------------------------*/
add_action('template_redirect', function() {
    $certificate_id = get_query_var('webinar_certificate_id');
    if (!$certificate_id) return;
    platform_display_webinar_certificate($certificate_id);
});

/* ---------------------------------------------
 * 7) Admin menus (payouts + certificates merged)
 * --------------------------------------------*/
add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'Webinar Certificates',
        'Webinar Certificates',
        'manage_options',
        'webinar-certificates',
        'platform_render_certificates_admin'
    );
});

function platform_render_certificates_admin() {
    if (!current_user_can('manage_options')) return;

    global $wpdb;

    if (isset($_POST['generate_certificates']) && check_admin_referer('cert_generate')) {
        do_action('platform_generate_webinar_certificates');
        echo '<div class="updated"><p>Certificate generation triggered.</p></div>';
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}webinar_certificates WHERE status = 'active'");
    $this_month = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}webinar_certificates WHERE status = 'active' AND created_at >= %s",
        date('Y-m-01 00:00:00')
    ));
    ?>
    <div class="wrap">
        <h1>Webinar Certificates</h1>

        <div class="card" style="max-width:800px;margin:20px 0;">
            <h2>Statistics</h2>
            <table class="widefat">
                <tr><td><strong>Total Certificates Issued:</strong></td><td><?php echo intval($total); ?></td></tr>
                <tr><td><strong>Issued This Month:</strong></td><td><?php echo intval($this_month); ?></td></tr>
            </table>
        </div>

        <div class="card" style="max-width:800px;margin:20px 0;">
            <h2>Manual Generation</h2>
            <p>Manually trigger certificate generation for all completed webinars.</p>
            <form method="post">
                <?php wp_nonce_field('cert_generate'); ?>
                <button type="submit" name="generate_certificates" class="button button-primary">Generate Certificates Now</button>
            </form>
        </div>

        <div class="card" style="max-width:800px;margin:20px 0;">
            <h2>Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('webinar_cert_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Director Name</th>
                        <td>
                            <input type="text" name="platform_director_name"
                                   value="<?php echo esc_attr(get_option('platform_director_name', 'Program Director')); ?>"
                                   class="regular-text">
                            <p class="description">Name to appear on certificates as Program Director</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Company Address</th>
                        <td>
                            <textarea name="platform_company_address" rows="3" class="large-text"><?php
                                echo esc_textarea(get_option('platform_company_address', ''));
                            ?></textarea>
                            <p class="description">Address to appear on certificates</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>

        <div class="card" style="max-width:800px;margin:20px 0;">
            <h2>Recent Certificates</h2>
            <?php
            $recent = $wpdb->get_results(
                "SELECT certificate_id, student_name, webinar_title, created_at
                FROM {$wpdb->prefix}webinar_certificates
                WHERE status = 'active'
                ORDER BY created_at DESC
                LIMIT 10"
            );
            if ($recent) {
                echo '<table class="widefat striped">';
                echo '<thead><tr><th>Certificate ID</th><th>Student</th><th>Webinar</th><th>Issued</th><th>Action</th></tr></thead><tbody>';
                foreach ($recent as $cert) {
                    $url = platform_get_certificate_url($cert->certificate_id);
                    echo '<tr>';
                    echo '<td><code>' . esc_html($cert->certificate_id) . '</code></td>';
                    echo '<td>' . esc_html($cert->student_name) . '</td>';
                    echo '<td>' . esc_html($cert->webinar_title) . '</td>';
                    echo '<td>' . esc_html(wp_date('M j, Y', strtotime($cert->created_at))) . '</td>';
                    echo '<td><a href="' . esc_url($url) . '" target="_blank">View</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No certificates generated yet.</p>';
            }
            ?>
        </div>
    </div>
    <?php
}

/* ---------------------------------------------
 * 8) AJAX: on-demand certificate generation
 * --------------------------------------------*/
add_action('wp_ajax_generate_webinar_certificate', function() {
    check_ajax_referer('generate_certificate', 'nonce');

    $event_id  = isset($_POST['event_id'])  ? intval($_POST['event_id'])  : 0;
    $period_id = isset($_POST['period_id']) ? intval($_POST['period_id']) : 0;

    if (!$event_id || !$period_id) {
        wp_send_json_error(['message' => 'Invalid parameters']);
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'Not logged in']);
    }

    if (!platform_is_webinar_certificate_eligible($event_id, $period_id)) {
        wp_send_json_error(['message' => 'Webinar not yet completed']);
    }

    $cert_id = platform_generate_webinar_certificate($user_id, $event_id, $period_id);

    if ($cert_id) {
        wp_send_json_success([
            'certificate_id' => $cert_id,
            'url' => platform_get_certificate_url($cert_id)
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to generate certificate']);
    }
});

/* ---------------------------------------------------------------
 * HELPERS — Amelia API + fee calc + payout upsert
 * --------------------------------------------------------------- */

function platform_core_flow5_api_headers() {
    return ['Content-Type'=>'application/json','Amelia'=> 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm'];
}
function platform_core_flow5_api($path) {
    $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1'.$path);
    $res = wp_remote_get($url, ['headers'=>platform_core_flow5_api_headers(),'timeout'=>25]);
    if (is_wp_error($res)) return null;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['data'] ?? null;
}
function platform_core_flow5_get_appointment($id) {
    $d = platform_core_flow5_api('/appointments/'.intval($id));
    return $d['appointment'] ?? null;
}
function platform_core_flow5_get_employee($id) {
    $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/users/providers/' . intval($id));
    $res = wp_remote_get($url, ['headers' => platform_core_flow5_api_headers(), 'timeout' => 25]);
    if (is_wp_error($res)) {
        error_log('Amelia Employee API Error: ' . $res->get_error_message());
        return [];
    }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['data']['user'] ?? [];
}
function platform_core_flow5_get_service($id) {
    $d = platform_core_flow5_api('/services/'.intval($id));
    return $d['service'] ?? [];
}
function platform_core_flow5_get_customer($id) {
    $d = platform_core_flow5_api('/customers/'.intval($id));
    return $d['customer'] ?? [];
}
function platform_core_flow5_extract_appt_id($reservation, $bookings) {
    if (is_array($reservation)) {
        if (!empty($reservation['appointmentId'])) return (int)$reservation['appointmentId'];
        if (!empty($reservation['appointment']['id'])) return (int)$reservation['appointment']['id'];
    }
    if (is_array($bookings) && !empty($bookings[0]['appointmentId'])) return (int)$bookings[0]['appointmentId'];
    return 0;
}
function platform_core_flow5_calc_fees($gross) {
    $pp = (float) get_option('platform_flow5_processor_fee_percent', 2);
    $pf = (float) get_option('platform_flow5_processor_fee_fixed', 3);
    $sp = (float) get_option('platform_flow5_platform_fee_percent', 20);
    $sf = (float) get_option('platform_flow5_platform_fee_fixed', 0);
    $processor = round(($pp/100.0) * $gross + $pf, 2);
    $platform  = round(($sp/100.0) * $gross + $sf, 2);
    return [$processor, $platform];
}
function platform_core_flow5_booking_gross($booking, $appt) {
    if (!empty($booking['price']) && (float)$booking['price'] > 0) return (float)$booking['price'];
    if (!empty($appt['price']) && (float)$appt['price'] > 0) return (float)$appt['price'];
    if (!empty($booking['wcOrderId']) && function_exists('wc_get_order')) {
        $order = wc_get_order((int)$booking['wcOrderId']);
        if ($order) {
            $total = (float) $order->get_total();
            if ($total > 0) {
                $qty = 0;
                foreach ($order->get_items() as $it) $qty += (int)$it->get_quantity();
                return $qty > 1 ? round($total / $qty, 2) : $total;
            }
        }
    }
    return 0.00;
}
function platform_core_flow5_find_user_by_email($email) {
    if (!$email) return 0;
    $u = get_user_by('email', $email);
    return $u ? (int)$u->ID : 0;
}
function platform_core_flow5_upsert_payout_row(array $appt, array $booking) {
    global $wpdb;
    $table = $wpdb->prefix . 'platform_payouts';

    $appointmentId = (int)($appt['id'] ?? 0);
    $bookingId     = (int)($booking['id'] ?? 0);
    if (!$appointmentId || !$bookingId) return;

    $serviceId   = (int)($appt['serviceId'] ?? 0);
    $svc         = $serviceId ? platform_core_flow5_get_service($serviceId) : [];
    $serviceName = $svc['name'] ?? '';

    $employeeId   = (int)($appt['providerId'] ?? 0);
    $employee     = $employeeId ? platform_core_flow5_get_employee($employeeId) : [];
    $expertEmail  = $employee['email'] ?? '';
    $expertUserId = platform_core_flow5_find_user_by_email($expertEmail);

    $studentEmail = '';
    if (!empty($booking['customer']['email'])) $studentEmail = sanitize_email($booking['customer']['email']);
    elseif (!empty($booking['customerId'])) {
        $cust = platform_core_flow5_get_customer((int)$booking['customerId']);
        if (!empty($cust['email'])) $studentEmail = sanitize_email($cust['email']);
    }

    $currency = $appt['currency'] ?? 'INR';
    $gross    = platform_core_flow5_booking_gross($booking, $appt);
    [$processor_fee, $platform_fee] = platform_core_flow5_calc_fees($gross);
    $net = max(0, $gross - $processor_fee - $platform_fee);

    $starts = $appt['bookingStart'] ?? '';
    $ends   = $appt['bookingEnd']   ?? $starts;

    $data = [
        'source'             => 'tutorial',
        'appointment_id'     => $appointmentId,
        'booking_id'         => $bookingId,
        'service_id'         => $serviceId ?: null,
        'service_name'       => $serviceName ?: null,
        'expert_employee_id' => $employeeId ?: null,
        'expert_user_id'     => $expertUserId ?: null,
        'expert_email'       => $expertEmail ?: null,
        'student_email'      => $studentEmail ?: null,
        'order_id'           => (int)($booking['wcOrderId'] ?? 0) ?: null,
        'currency'           => $currency,
        'gross'              => $gross,
        'processor_fee'      => $processor_fee,
        'platform_fee'       => $platform_fee,
        'net'                => $net,
        'starts_at'          => gmdate('Y-m-d H:i:s', strtotime($starts.' UTC')),
        'ends_at'            => gmdate('Y-m-d H:i:s', strtotime($ends.' UTC')),
        'status'             => (strtotime($ends.' UTC') <= time()) ? 'completed' : 'scheduled',
        'payout_month'       => gmdate('Y-m', strtotime($ends.' UTC')),
        'meta'               => wp_json_encode(['source'=>'flow5']),
        'created_at'         => current_time('mysql'),
        'updated_at'         => current_time('mysql')
    ];

    $wpdb->replace($table, $data);
}
function platform_core_flow5_backfill($since = '-24 hours') {
    return 0;
}
function platform_core_flow5_finalize() {
    global $wpdb;
    $table = $wpdb->prefix . 'platform_payouts';
    $wpdb->query("UPDATE $table SET status='completed', updated_at='".esc_sql(current_time('mysql'))."'
                  WHERE source='tutorial' AND status='scheduled' AND ends_at <= UTC_TIMESTAMP()");
}
function platform_core_flow5_get_payments($date_from = null, $date_to = null, $page = 1) {
    if (!$date_from) $date_from = date('Y-m-d', strtotime('-2 years'));
    if (!$date_to)   $date_to   = date('Y-m-d');
    $url = admin_url('admin-ajax.php');
    $url .= '?action=wpamelia_api';
    $url .= '&call=/api/v1/payments';
    $url .= '&page=' . intval($page);
    $url .= '&dates=' . $date_from . ',' . $date_to;
    $response = wp_remote_get($url, ['headers' => platform_core_flow5_api_headers(), 'timeout' => 30]);
    if (is_wp_error($response)) {
        error_log('Payments API Error: ' . $response->get_error_message());
        return null;
    }
    $code = wp_remote_retrieve_response_code($response);
    if ($code != 200) {
        error_log("Payments API status: $code");
        return null;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['data'] ?? null;
}
function platform_core_flow5_get_booking($booking_id) {
    $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/bookings/' . intval($booking_id));
    $response = wp_remote_get($url, ['headers' => platform_core_flow5_api_headers(), 'timeout' => 25]);
    if (is_wp_error($response)) return null;
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['data']['booking'] ?? null;
}
function platform_core_flow5_get_finance_data($params = []) {
    $query = http_build_query(array_merge([
        'dates' => json_encode([date('Y-m-d', strtotime('-1 year')), date('Y-m-d')])
    ], $params));
    $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/finance&' . $query);
    $res = wp_remote_get($url, ['headers' => platform_core_flow5_api_headers(), 'timeout' => 30]);
    if (is_wp_error($res)) {
        error_log('Amelia Finance API Error: ' . $res->get_error_message());
        return null;
    }
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return $data['data'] ?? null;
}
function platform_core_get_expert_earnings_from_payouts($user_id) {
    $employee_id = (int) get_user_meta($user_id, 'amelia_employee_id', true);
    if (!$employee_id) return 0.00;
    $employee = platform_core_flow5_get_employee($employee_id);
    if (!$employee) return 0.00;
    $date_from = date('Y-m-01');
    $date_to   = date('Y-m-d');
    $payments_data = platform_core_flow5_get_payments($date_from, $date_to);
    if (!$payments_data || empty($payments_data['payments'])) return 0.00;
    $total_earnings = 0.00;
    foreach ($payments_data['payments'] as $payment) {
        $status = strtolower($payment['status'] ?? '');
        if ($status !== 'paid') continue;
        $service_name = $payment['bookableName'] ?? '';
        if (stripos($service_name, 'Remote College Class') !== false) continue;
        $providers = $payment['providers'] ?? [];
        $is_expert = false;
        foreach ($providers as $provider) {
            if ((int)($provider['id'] ?? 0) === $employee_id) { $is_expert = true; break; }
        }
        if (!$is_expert) continue;
        $amount = (float)($payment['amount'] ?: $payment['bookedPrice']);
        if ($amount <= 0) continue;
        [$processor_fee, $platform_fee] = platform_core_flow5_calc_fees($amount);
        $net = $amount - $processor_fee - $platform_fee;
        $total_earnings += $net;
    }
    return round($total_earnings, 2);
}
function platform_core_get_expert_revenue_from_payouts($user_id) {
    $employee_id = (int) get_user_meta($user_id, 'amelia_employee_id', true);
    if (!$employee_id) return 0.00;
    $employee = platform_core_flow5_get_employee($employee_id);
    if (!$employee) return 0.00;
    $date_from = date('Y-m-01');
    $date_to   = date('Y-m-d');
    $payments_data = platform_core_flow5_get_payments($date_from, $date_to);
    if (!$payments_data || empty($payments_data['payments'])) return 0.00;
    $total_revenue = 0.00;
    foreach ($payments_data['payments'] as $payment) {
        $status = strtolower($payment['status'] ?? '');
        if ($status !== 'paid') continue;
        $service_name = $payment['bookableName'] ?? '';
        if (stripos($service_name, 'Remote College Class') !== false) continue;
        $providers = $payment['providers'] ?? [];
        $is_expert = false;
        foreach ($providers as $provider) {
            if ((int)($provider['id'] ?? 0) === $employee_id) { $is_expert = true; break; }
        }
        if (!$is_expert) continue;
        $amount = (float)($payment['amount'] ?: $payment['bookedPrice']);
        if ($amount > 0) $total_revenue += $amount;
    }
    return round($total_revenue, 2);
}

/* ---------------------------------------------------------------
 * CERTIFICATE FUNCTIONS
 * --------------------------------------------------------------- */

/**
 * Generate a certificate for a completed webinar.
 * Returns certificate ID string on success, false on failure.
 */
function platform_generate_webinar_certificate($student_user_id, $event_id, $period_id) {
    global $wpdb;

    // Return existing certificate ID if already generated
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT certificate_id FROM {$wpdb->prefix}webinar_certificates
        WHERE student_user_id = %d AND event_id = %d AND period_id = %d",
        $student_user_id, $event_id, $period_id
    ));
    if ($existing) return $existing;

    $student = get_userdata($student_user_id);
    if (!$student) return false;

    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT e.id, e.name, e.description, ep.periodStart, ep.periodEnd
        FROM {$wpdb->prefix}amelia_events e
        INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId = e.id
        WHERE e.id = %d AND ep.id = %d",
        $event_id, $period_id
    ));
    if (!$event) return false;

    $now = current_time('mysql', true);
    if ($event->periodEnd > $now) return false; // Webinar not yet completed

    $start_ts        = strtotime($event->periodStart);
    $end_ts          = strtotime($event->periodEnd);
    $duration_minutes = round(($end_ts - $start_ts) / 60);

    if ($duration_minutes < 60) {
        $duration_display = $duration_minutes . ' minutes';
    } else {
        $hours = floor($duration_minutes / 60);
        $mins  = $duration_minutes % 60;
        $duration_display = $hours . ' hour' . ($hours > 1 ? 's' : '');
        if ($mins > 0) $duration_display .= ' ' . $mins . ' minutes';
    }

    $year       = date('Y', $end_ts);
    $cert_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}webinar_certificates");
    $certificate_id = 'WEB-' . $year . '-' . str_pad($cert_count + 1, 6, '0', STR_PAD_LEFT);

    $instructor = $wpdb->get_var($wpdb->prepare(
        "SELECT CONCAT(u.firstName, ' ', u.lastName)
        FROM {$wpdb->prefix}amelia_providers_to_events pe
        INNER JOIN {$wpdb->prefix}amelia_users u ON u.id = pe.userId
        WHERE pe.eventId = %d LIMIT 1",
        $event_id
    ));

    $template_path = plugin_dir_path(__FILE__) . '../templates/certificate-webinar.php';
    if (!file_exists($template_path)) return false;

    $template = file_get_contents($template_path);

    $student_name   = trim($student->display_name) ?: trim($student->first_name . ' ' . $student->last_name);
    $completion_date = wp_date('F j, Y', $end_ts);
    $issue_date      = wp_date('F j, Y');
    $verify_url      = home_url('/certificate/webinar/' . $certificate_id);

    $replacements = [
        '{{COMPANY_NAME}}'    => get_option('blogname'),
        '{{STUDENT_NAME}}'    => $student_name,
        '{{WEBINAR_TITLE}}'   => $event->name,
        '{{COMPLETION_DATE}}' => $completion_date,
        '{{DURATION}}'        => $duration_display,
        '{{WEBINAR_ID}}'      => 'WBN-' . str_pad($event_id, 6, '0', STR_PAD_LEFT),
        '{{CERTIFICATE_ID}}'  => $certificate_id,
        '{{INSTRUCTOR_NAME}}' => $instructor ?: 'Program Instructor',
        '{{DIRECTOR_NAME}}'   => get_option('platform_director_name', 'Program Director'),
        '{{YEAR}}'            => $year,
        '{{VERIFY_URL}}'      => $verify_url,
        '{{COMPANY_ADDRESS}}' => get_option('platform_company_address', ''),
        '{{ISSUE_DATE}}'      => $issue_date
    ];

    $html_content = str_replace(array_keys($replacements), array_values($replacements), $template);

    $result = $wpdb->insert(
        $wpdb->prefix . 'webinar_certificates',
        [
            'certificate_id'   => $certificate_id,
            'event_id'         => $event_id,
            'period_id'        => $period_id,
            'student_user_id'  => $student_user_id,
            'student_email'    => $student->user_email,
            'student_name'     => $student_name,
            'webinar_title'    => $event->name,
            'completion_date'  => $event->periodEnd,
            'duration_minutes' => $duration_minutes,
            'instructor_name'  => $instructor,
            'html_content'     => $html_content,
            'status'           => 'active',
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql')
        ],
        ['%s','%d','%d','%d','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s']
    );

    return $result ? $certificate_id : false;
}

function platform_display_webinar_certificate($certificate_id) {
    global $wpdb;
    $certificate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}webinar_certificates WHERE certificate_id = %s AND status = 'active'",
        $certificate_id
    ));
    if (!$certificate) {
        wp_die('Certificate not found or has been revoked.', 'Certificate Not Found', ['response' => 404]);
    }
    echo $certificate->html_content;
    exit;
}

function platform_get_webinar_certificate($user_id, $event_id, $period_id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}webinar_certificates
        WHERE student_user_id = %d AND event_id = %d AND period_id = %d AND status = 'active'",
        $user_id, $event_id, $period_id
    ));
}

function platform_get_user_certificates($user_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}webinar_certificates
        WHERE student_user_id = %d AND status = 'active'
        ORDER BY completion_date DESC",
        $user_id
    ));
}

function platform_get_certificate_url($certificate_id) {
    return home_url('/certificate/webinar/' . $certificate_id);
}

function platform_is_webinar_certificate_eligible($event_id, $period_id) {
    global $wpdb;
    $now    = current_time('mysql', true);
    $period = $wpdb->get_row($wpdb->prepare(
        "SELECT periodEnd FROM {$wpdb->prefix}amelia_events_periods WHERE id = %d AND eventId = %d",
        $period_id, $event_id
    ));
    if (!$period) return false;
    return $period->periodEnd < $now;
}

/* ---------------------------------------------------------------
 * DEBUG / ADMIN TOOLS (unchanged from original)
 * --------------------------------------------------------------- */

add_action('admin_init', function() {
    if (isset($_GET['debug_amelia_payments']) && current_user_can('manage_options')) {

        echo '<div style="max-width: 1400px; margin: 50px auto; background: white; padding: 30px; border: 2px solid #0073aa; border-radius: 10px;">';
        echo '<h1>?? Amelia Payments API Debug</h1>';

        echo '<h3>1. Fetch All Payments</h3>';
        $payments_url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/payments?dates=' .
            urlencode(json_encode([date('Y-m-d', strtotime('-1 year')), date('Y-m-d')])));
        echo '<p><strong>URL:</strong> <code>' . esc_html($payments_url) . '</code></p>';
        $payments_res = wp_remote_get($payments_url, ['headers' => platform_core_flow5_api_headers(), 'timeout' => 30]);
        if (is_wp_error($payments_res)) {
            echo '<p style="color: red;">? <strong>API Error:</strong> ' . $payments_res->get_error_message() . '</p>';
        } else {
            $code = wp_remote_retrieve_response_code($payments_res);
            $body = wp_remote_retrieve_body($payments_res);
            echo '<p><strong>Response Code:</strong> ' . $code . '</p>';
            if ($code == 200) {
                echo '<p style="color: green;">? Payments API is working!</p>';
                $payments_data = json_decode($body, true);
                $payments = $payments_data['data']['payments'] ?? [];
                if (!empty($payments)) {
                    echo '<h4>Sample Payments (First 10):</h4>';
                    echo '<table style="width: 100%; border-collapse: collapse; font-size: 11px;">';
                    echo '<tr style="background: #333; color: white;"><th style="padding: 8px;">ID</th><th>Booking ID</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th><th>Entity</th></tr>';
                    foreach (array_slice($payments, 0, 10) as $payment) {
                        echo '<tr style="border-bottom: 1px solid #ddd;">';
                        echo '<td style="padding: 8px;">' . ($payment['id'] ?? '?') . '</td>';
                        echo '<td>' . ($payment['customerBookingId'] ?? '?') . '</td>';
                        echo '<td>?' . number_format((float)($payment['amount'] ?? 0), 2) . '</td>';
                        echo '<td>' . esc_html($payment['gateway'] ?? '?') . '</td>';
                        echo '<td>' . esc_html($payment['status'] ?? '?') . '</td>';
                        echo '<td>' . esc_html(substr($payment['dateTime'] ?? '?', 0, 10)) . '</td>';
                        echo '<td>' . esc_html($payment['entity'] ?? '?') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '<p><strong>Total Payments Found:</strong> ' . count($payments) . '</p>';
                }
                echo '<details style="margin-top: 20px;"><summary style="cursor: pointer; font-weight: bold; color: #0073aa;">View Full API Response</summary>';
                echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto; max-height: 500px; font-size: 11px; margin-top: 10px;">';
                print_r($payments_data);
                echo '</pre></details>';
            } else {
                echo '<p style="color: red;">? API returned error code: ' . $code . '</p>';
                echo '<pre style="background: #fff3cd; padding: 15px; border-radius: 5px;">' . esc_html($body) . '</pre>';
            }
        }

        echo '<hr>';
        echo '<h3>2. Test Earnings by Employee</h3>';
        $employees_url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/employees');
        $emp_res = wp_remote_get($employees_url, ['headers' => platform_core_flow5_api_headers(), 'timeout' => 30]);
        $employees = [];
        if (!is_wp_error($emp_res)) {
            $emp_data = json_decode(wp_remote_retrieve_body($emp_res), true);
            $employees = $emp_data['data']['employees'] ?? [];
        }
        if (!empty($employees)) {
            global $wpdb;
            echo '<table style="width: 100%; border-collapse: collapse;">';
            echo '<tr style="background: #333; color: white;"><th style="padding: 8px;">Employee ID</th><th>Name</th><th>Email</th><th>WP User</th><th>Calculated Earnings</th></tr>';
            foreach ($employees as $emp) {
                $emp_id   = $emp['id'] ?? 0;
                $emp_name = ($emp['firstName'] ?? '') . ' ' . ($emp['lastName'] ?? '');
                $emp_email = $emp['email'] ?? '';
                $wp_user_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'amelia_employee_id' AND meta_value = %d LIMIT 1", $emp_id
                ));
                $earnings = $wp_user_id ? platform_core_get_expert_earnings_from_payouts($wp_user_id) : 0.00;
                echo '<tr style="border-bottom: 1px solid #ddd;">';
                echo '<td style="padding: 8px;">' . $emp_id . '</td>';
                echo '<td>' . esc_html($emp_name) . '</td>';
                echo '<td>' . esc_html($emp_email) . '</td>';
                echo '<td>' . ($wp_user_id ? "User #$wp_user_id" : '<span style="color: #999;">Not linked</span>') . '</td>';
                echo '<td style="font-weight: bold; color: #059669;">?' . number_format($earnings, 2) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        echo '<hr>';
        echo '<p><a href="' . admin_url() . '" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Back to Dashboard</a></p>';
        echo '</div>';
        exit;
    }
});

add_action('admin_init', function() {
    if (isset($_GET['do_payout_backfill']) && current_user_can('manage_options')) {
        echo '<div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border: 3px solid #0073aa; border-radius: 10px; z-index: 99999; box-shadow: 0 0 50px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">';
        echo '<h2>? Running Backfill...</h2>';
        $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/appointments');
        $res = wp_remote_get($url, ['headers' => platform_core_flow5_api_headers(), 'timeout' => 60]);
        if (is_wp_error($res)) {
            echo '<p style="color: red;">? Error: ' . $res->get_error_message() . '</p><p><a href="' . admin_url() . '">Go Back</a></p></div>';
            exit;
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        $appointments_by_date = $data['data']['appointments'] ?? [];
        $total_appointments = 0;
        foreach ($appointments_by_date as $date_data) $total_appointments += count($date_data['appointments'] ?? []);
        echo '<p>Found <strong>' . $total_appointments . '</strong> appointments in Amelia.</p><hr>';
        $processed = $skipped = $errors = 0;
        foreach ($appointments_by_date as $date => $date_data) {
            foreach ($date_data['appointments'] ?? [] as $appt) {
                $apptId = $appt['id'] ?? 0;
                $status = strtolower($appt['status'] ?? '');
                if ($status !== 'approved') { $skipped++; continue; }
                $serviceId   = (int)($appt['serviceId'] ?? 0);
                $serviceName = '';
                if ($serviceId) {
                    $svc = platform_core_flow5_get_service($serviceId);
                    $serviceName = $svc['name'] ?? '';
                    if (stripos($serviceName, 'Remote College Class') !== false) { $skipped++; continue; }
                }
                foreach ((array)($appt['bookings'] ?? []) as $booking) {
                    try {
                        platform_core_flow5_upsert_payout_row($appt, $booking);
                        $processed++;
                    } catch (Exception $e) { $errors++; }
                }
            }
        }
        echo '<hr><div style="background: #d4edda; padding: 15px; border-radius: 5px;">';
        echo '<h3>? Backfill Complete!</h3>';
        echo '<p><strong>Processed:</strong> ' . $processed . '</p>';
        echo '<p><strong>Skipped:</strong> ' . $skipped . '</p>';
        if ($errors > 0) echo '<p style="color: red;"><strong>Errors:</strong> ' . $errors . '</p>';
        echo '</div>';
        echo '<p><a href="' . admin_url() . '" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;">Go to Dashboard</a></p>';
        echo '</div>';
        exit;
    }
});

add_action('admin_init', function() {
    if (isset($_GET['debug_amelia_api']) && current_user_can('manage_options')) {
        echo '<div style="max-width: 900px; margin: 50px auto; background: white; padding: 30px; border: 2px solid #0073aa; border-radius: 10px;">';
        echo '<h1>?? Amelia API Debug</h1>';
        $api_key = get_option('platform_amelia_api_key', 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm');
        echo '<h3>1. API Key Check</h3>';
        if (empty($api_key)) echo '<p style="color: red;">? <strong>API Key is EMPTY!</strong></p>';
        else echo '<p style="color: green;">? API Key exists: <code>' . substr($api_key, 0, 10) . '...</code></p>';
        echo '<hr>';
        echo '<h3>2. Test API Call</h3>';
        $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/appointments');
        $res = wp_remote_get($url, ['headers' => platform_core_flow5_api_headers(), 'timeout' => 30]);
        if (is_wp_error($res)) {
            echo '<p style="color: red;">? <strong>API Error:</strong> ' . $res->get_error_message() . '</p>';
        } else {
            $code = wp_remote_retrieve_response_code($res);
            $body = wp_remote_retrieve_body($res);
            echo '<p><strong>Response Code:</strong> ' . $code . '</p>';
            if ($code == 200) {
                echo '<p style="color: green;">? API call successful!</p>';
                $data = json_decode($body, true);
                echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto; max-height: 400px;">';
                print_r($data);
                echo '</pre>';
            } else {
                echo '<p style="color: red;">? API returned error code: ' . $code . '</p>';
                echo '<pre style="background: #fff3cd; padding: 15px; border-radius: 5px;">' . esc_html($body) . '</pre>';
            }
        }
        echo '<hr>';
        echo '<h3>3. Direct Database Check</h3>';
        global $wpdb;
        $appt_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}amelia_appointments");
        echo '<p><strong>Appointments in DB:</strong> ' . intval($appt_count) . '</p>';
        if ($appt_count > 0) {
            $sample = $wpdb->get_results("SELECT id, bookingStart, status, serviceId FROM {$wpdb->prefix}amelia_appointments LIMIT 5", ARRAY_A);
            echo '<table style="width: 100%; border-collapse: collapse;"><tr style="background: #333; color: white;"><th style="padding: 8px;">ID</th><th>Start</th><th>Status</th><th>Service ID</th></tr>';
            foreach ($sample as $s) {
                echo '<tr style="border-bottom: 1px solid #ddd;"><td style="padding: 8px;">' . $s['id'] . '</td><td>' . $s['bookingStart'] . '</td><td>' . $s['status'] . '</td><td>' . $s['serviceId'] . '</td></tr>';
            }
            echo '</table>';
        }
        echo '<p><strong>Services in DB:</strong> ' . intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}amelia_services")) . '</p>';
        echo '<p><strong>Employees in DB:</strong> ' . intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}amelia_users WHERE type='provider'")) . '</p>';
        echo '<hr><p><a href="' . admin_url() . '" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Back to Dashboard</a></p>';
        echo '</div>';
        exit;
    }
});

add_action('admin_init', function() {
    if (isset($_GET['test_payments_working']) && current_user_can('manage_options')) {
        echo '<div style="max-width: 1400px; margin: 50px auto; background: white; padding: 30px; border: 2px solid #0073aa; border-radius: 10px;">';
        echo '<h1>?? Payments API Test (Working Version)</h1>';
        $api_key = get_option('platform_amelia_api_key', 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm');
        echo '<h3>1. Test Payments API</h3>';
        $url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/payments&page=1&dates=2023-10-01,2025-12-31');
        echo '<p><strong>URL:</strong><br><code style="word-break: break-all;">' . esc_html($url) . '</code></p>';
        $response = wp_remote_get($url, ['headers' => ['Amelia' => $api_key], 'timeout' => 30]);
        if (is_wp_error($response)) {
            echo '<p style="color: red;">? Error: ' . $response->get_error_message() . '</p>';
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            echo '<p><strong>Status:</strong> <span style="font-size: 24px; color: ' . ($code == 200 ? 'green' : 'red') . ';">' . $code . '</span></p>';
            if ($code == 200) {
                $data     = json_decode($body, true);
                $payments = $data['data']['payments'] ?? [];
                echo '<p style="color: green; font-weight: bold;">? API is working!</p>';
                echo '<p><strong>Total:</strong> ' . ($data['data']['totalCount'] ?? 0) . '</p>';
                echo '<p><strong>Returned:</strong> ' . count($payments) . '</p>';
                if (!empty($payments)) {
                    echo '<table style="width: 100%; border-collapse: collapse; font-size: 11px;">';
                    echo '<tr style="background: #333; color: white;"><th style="padding: 8px;">ID</th><th>Name</th><th>Provider</th><th>Amount</th><th>Status</th><th>Date</th></tr>';
                    foreach (array_slice($payments, 0, 5) as $p) {
                        $provider_name = $p['providers'][0]['fullName'] ?? '';
                        echo '<tr style="border-bottom: 1px solid #ddd;">';
                        echo '<td style="padding: 8px;">' . ($p['id'] ?? '?') . '</td>';
                        echo '<td>' . esc_html($p['bookableName'] ?? '?') . '</td>';
                        echo '<td>' . esc_html($provider_name) . '</td>';
                        echo '<td>?' . number_format((float)($p['amount'] ?: $p['bookedPrice'] ?? 0), 2) . '</td>';
                        echo '<td>' . esc_html($p['status'] ?? '?') . '</td>';
                        echo '<td>' . esc_html(substr($p['bookingStart'] ?? '?', 0, 10)) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            } else {
                echo '<p style="color: red;">? API Error</p>';
                echo '<pre style="background: #fff3cd; padding: 15px;">' . esc_html($body) . '</pre>';
            }
        }
        echo '<hr>';
        echo '<p><a href="' . admin_url() . '" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">? Back to Dashboard</a></p>';
        echo '</div>';
        exit;
    }
});

/* ---------------------------------------------------------------
 * SHORTCODE: [tutorial_expert_dashboard]
 * --------------------------------------------------------------- */
add_shortcode('tutorial_expert_dashboard', function () {

    if (!is_user_logged_in() || !platform_core_user_is_expert()) {
        return '<div class="ted-access-denied"><p>Access denied. This page is only available to tutorial experts.</p></div>';
    }

    global $wpdb;
    $user_id     = get_current_user_id();
    $user        = wp_get_current_user();
    $employee_id = (int) get_user_meta($user_id, 'amelia_employee_id', true);
    if (!$employee_id) {
        return '<div class="ted-no-profile"><p>Expert profile not linked. Please contact support to link your Amelia employee account.</p></div>';
    }

    $employee       = platform_core_flow5_get_employee($employee_id);
    $profile_status = (!empty($employee['status']) && $employee['status'] === 'visible') ? 'Published' : 'Draft';
    $profile_class  = ($profile_status === 'Published') ? 'status-published' : 'status-draft';

    $revenue  = platform_core_get_expert_revenue_from_payouts($user_id);
    $earnings = platform_core_get_expert_earnings_from_payouts($user_id);

    $now = gmdate('Y-m-d H:i:s');
    $sessions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT
                a.id as appointment_id,
                a.bookingStart,
                a.bookingEnd,
                a.status as appointment_status,
                s.name AS service_name,
                c.firstName,
                c.lastName,
                c.email as customer_email,
                cb.status as booking_status
            FROM {$wpdb->prefix}amelia_appointments a
            INNER JOIN {$wpdb->prefix}amelia_services s ON s.id = a.serviceId
            INNER JOIN {$wpdb->prefix}amelia_customer_bookings cb ON cb.appointmentId = a.id
            INNER JOIN {$wpdb->prefix}amelia_users c ON c.id = cb.customerId
            WHERE a.providerId = %d
              AND a.bookingStart > %s
              AND s.name NOT LIKE %s
              AND cb.status != 'canceled'
              AND cb.status != 'rejected'
            ORDER BY a.bookingStart ASC",
            $employee_id, $now, '%Remote College Class%'
        )
    );

    $panel_base  = home_url('/expert-panel-internal');
    $profile_url = $panel_base . '#/profile';

    ob_start();
    ?>
    <div class="tutorial-expert-dashboard">

        <div class="ted-header">
            <div class="ted-header-left">
                <h1>Tutorial Expert Dashboard</h1>
                <p class="ted-subtitle">Manage your tutorials and expert services</p>
            </div>
            <div class="ted-header-right">
                <a href="javascript:void(0)" class="ted-btn ted-btn-outline" data-open-availability="1">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M5 1v2M11 1v2M2 5h12M3 3h10a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Manage Availability
                </a>
                <a href="<?php echo esc_url($profile_url); ?>" class="ted-btn ted-btn-dark">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM4 14v-1a3 3 0 0 1 3-3h2a3 3 0 0 1 3 3v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Update Profile
                </a>
            </div>
        </div>

        <div class="ted-metrics">
            <div class="ted-metric-card">
                <div class="ted-metric-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="ted-metric-content">
                    <span class="ted-metric-label">Profile Status</span>
                    <span class="ted-metric-value <?php echo esc_attr($profile_class); ?>"><?php echo esc_html($profile_status); ?></span>
                </div>
            </div>
            <div class="ted-metric-card">
                <div class="ted-metric-icon ted-metric-icon-revenue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M3 3h18v18H3zM8 12h8M8 8h8M8 16h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="ted-metric-content">
                    <span class="ted-metric-label">Total Revenue (Gross)</span>
                    <span class="ted-metric-value"><?php echo number_format($revenue, 2); ?>Rs</span>
                </div>
            </div>
            <div class="ted-metric-card">
                <div class="ted-metric-icon ted-metric-icon-earnings">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="ted-metric-content">
                    <span class="ted-metric-label">Total Earnings (Net)</span>
                    <span class="ted-metric-value"><?php echo number_format($earnings, 2); ?>Rs</span>
                </div>
            </div>
        </div>

        <div class="ted-grid">
            <div class="ted-card">
                <div class="ted-card-header">
                    <h3>Upcoming Sessions</h3>
                    <a href="javascript:void(0)" class="ted-link" data-open-availability="1">View all ?</a>
                </div>
                <div class="ted-card-body">
                    <?php if ($sessions): ?>
                        <ul class="ted-session-list">
                            <?php foreach ($sessions as $s):
                                $start = strtotime($s->bookingStart);
                                $end   = strtotime($s->bookingEnd);
                                $duration_mins = round(($end - $start) / 60);
                                $initials = strtoupper(substr($s->firstName, 0, 1) . substr($s->lastName, 0, 1));
                            ?>
                            <li class="ted-session-item">
                                <div class="ted-session-avatar"><?php echo esc_html($initials); ?></div>
                                <div class="ted-session-info">
                                    <div class="ted-session-student"><?php echo esc_html($s->firstName . ' ' . $s->lastName); ?></div>
                                    <div class="ted-session-service"><?php echo esc_html($s->service_name); ?></div>
                                </div>
                                <div class="ted-session-time">
                                    <div class="ted-session-date"><?php echo date_i18n('M j, g:i A', $start); ?></div>
                                    <div class="ted-session-duration"><?php echo intval($duration_mins); ?> minutes</div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="ted-empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                                <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p>No upcoming sessions</p>
                            <small>Your scheduled tutorials will appear here</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ted-card">
                <div class="ted-card-header"><h3>Quick Actions</h3></div>
                <div class="ted-card-body">
                    <ul class="ted-action-list">
                        <li>
                            <a href="javascript:void(0)" class="ted-action-item" data-open-availability="1">
                                <div class="ted-action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div class="ted-action-content">
                                    <div class="ted-action-title">Manage Calendar</div>
                                    <div class="ted-action-desc">Set your availability and working hours</div>
                                </div>
                                <svg class="ted-action-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo home_url('/expert/materials'); ?>" class="ted-action-item">
                                <div class="ted-action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div class="ted-action-content">
                                    <div class="ted-action-title">Upload Materials</div>
                                    <div class="ted-action-desc">Share resources with your students</div>
                                </div>
                                <svg class="ted-action-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo home_url('/expert/earnings'); ?>" class="ted-action-item">
                                <div class="ted-action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div class="ted-action-content">
                                    <div class="ted-action-title">Transaction History</div>
                                    <div class="ted-action-desc">View your earnings and payouts</div>
                                </div>
                                <svg class="ted-action-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/* ---------------------------------------------------------------
 * SHORTCODE: [expert_update_availability]
 * --------------------------------------------------------------- */
add_shortcode('expert_update_availability', function() {
    if (!is_user_logged_in() || !platform_core_user_is_expert()) {
        return '<div class="access-denied"><p>Access denied.</p></div>';
    }
    $user_id     = get_current_user_id();
    $employee_id = (int) get_user_meta($user_id, 'amelia_employee_id', true);
    if (!$employee_id) {
        return '<div class="no-profile"><p>Expert profile not linked.</p></div>';
    }
    $employee = platform_core_flow5_get_employee($employee_id);
    ob_start();
    ?>
    <div class="expert-availability-container">
        <style>
            .expert-availability-container{padding:2rem;max-width:900px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
            .availability-header{margin-bottom:2rem;}
            .availability-header h1{font-size:1.875rem;font-weight:700;color:#111827;margin:0 0 .5rem 0;}
            .availability-header p{color:#6b7280;margin:0;}
            .availability-section{background:white;border:1px solid #e5e7eb;border-radius:.75rem;padding:1.5rem;margin-bottom:1.5rem;}
            .section-title{font-size:1.125rem;font-weight:600;color:#111827;margin:0 0 1rem 0;display:flex;align-items:center;gap:.5rem;}
            .section-title svg{width:20px;height:20px;color:#3b82f6;}
            .amelia-embed{min-height:500px;}
            .loading-state{display:flex;align-items:center;justify-content:center;padding:3rem;color:#6b7280;}
            .spinner{width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:spin .8s linear infinite;margin-right:1rem;}
            @keyframes spin{to{transform:rotate(360deg);}}
            .help-text{background:#eff6ff;border-left:3px solid #3b82f6;padding:1rem;margin-top:1rem;border-radius:.375rem;font-size:.875rem;color:#1e40af;}
        </style>
        <div class="availability-header">
            <h1>Update Availability</h1>
            <p>Manage your weekly schedule and available hours</p>
        </div>
        <div class="availability-section">
            <h2 class="section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Weekly Schedule
            </h2>
            <div class="amelia-embed">
                <div id="amelia-calendar-container">
                    <div class="loading-state"><div class="spinner"></div><span>Loading calendar...</span></div>
                </div>
            </div>
            <div class="help-text"><strong>Tip:</strong> Set your regular working hours here. Students can only book appointments during your available time slots.</div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            setTimeout(function() {
                var iframe = $('<iframe>', {
                    src: '<?php echo esc_js(home_url('/expert-panel-internal')); ?>#/appointments',
                    frameborder: '0', width: '100%', height: '600px',
                    style: 'border: none; background: white;'
                });
                $('#amelia-calendar-container').html(iframe);
            }, 500);
        });
        </script>
    </div>
    <?php
    return ob_get_clean();
});