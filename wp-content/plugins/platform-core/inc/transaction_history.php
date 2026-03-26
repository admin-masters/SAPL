<?php
/**
 * Transaction History Dashboard — Polished UI
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
$user         = wp_get_current_user();
$user_id      = $user->ID;
$display_name = $user->display_name;
$user_avatar  = get_avatar_url($user_id);

$table_amelia_app  = $wpdb->prefix . 'amelia_appointments';
$table_amelia_book = $wpdb->prefix . 'amelia_customer_bookings';
$table_amelia_svc  = $wpdb->prefix . 'amelia_services';
$table_amelia_usr  = $wpdb->prefix . 'amelia_users';
$table_payouts     = $wpdb->prefix . 'platform_payouts';
$table_contracts   = $wpdb->prefix . 'platform_contracts';
$table_requests    = $wpdb->prefix . 'platform_requests';

$employee_id = (int) get_user_meta($user_id, 'amelia_employee_id', true);

$total_revenue = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(amount_gross) FROM $table_payouts WHERE expert_user_id = %d", $user_id)) ?: 0;
$net_earnings  = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(amount_net) FROM $table_payouts WHERE expert_user_id = %d AND status IN ('approved','paid','completed')", $user_id)) ?: 0;
$pending_amt   = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(amount_gross) FROM $table_payouts WHERE expert_user_id = %d AND status = 'pending'", $user_id)) ?: 0;

$transactions = $wpdb->get_results($wpdb->prepare("
    SELECT
        p.*,
        p.amelia_booking_id AS appointment_id,
        p.amount_gross      AS gross,
        p.amount_net        AS net,
        p.month_key         AS payout_month,
        a.bookingStart      AS booking_start,
        a.bookingEnd        AS booking_end,
        a.serviceId         AS service_id,
        svc.name            AS service_name,
        svc.duration        AS service_duration,
        cu.firstName        AS customer_first,
        cu.lastName         AS customer_last,
        cu.email            AS customer_email,
        b.customerId        AS amelia_customer_id,
        b.info              AS booking_info
    FROM $table_payouts p
    LEFT JOIN $table_amelia_book b   ON b.id = p.amelia_booking_id
    LEFT JOIN $table_amelia_app  a   ON a.id = b.appointmentId
    LEFT JOIN $table_amelia_svc  svc ON svc.id = a.serviceId
    LEFT JOIN $table_amelia_usr  cu  ON cu.id = b.customerId AND cu.type = 'customer'
    WHERE p.expert_user_id = %d
    ORDER BY p.created_at DESC
", $user_id));
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
.txn-root *, .txn-root *::before, .txn-root *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
.txn-root {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #f0f2f5;
    min-height: 100vh;
    width: 100%;
    display: block;
}

/* -- Navbar -- */
.txn-nav {
    background: #ffffff;
    border-bottom: 1px solid #e4e7ec;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 40px;
    height: 64px;
    position: sticky;
    top: 0;
    z-index: 200;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.txn-logo {
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 800;
    font-size: 18px;
    color: #4338ca;
    letter-spacing: -0.4px;
    text-decoration: none;
}
.txn-logo-icon {
    width: 34px; height: 34px;
    background: #4338ca;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
}
.txn-logo-icon svg { width: 18px; height: 18px; stroke: #fff; stroke-width: 2.5; }
.txn-nav-right { display: flex; align-items: center; gap: 20px; }
.txn-nav-bell {
    width: 36px; height: 36px;
    border-radius: 9px;
    border: 1px solid #e4e7ec;
    background: #fafafa;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.txn-nav-bell:hover { background: #f0f2f5; border-color: #d0d5dd; }
.txn-nav-bell svg { width: 16px; height: 16px; stroke: #667085; stroke-width: 2; }
.txn-nav-divider { width: 1px; height: 28px; background: #e4e7ec; }
.txn-nav-profile { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.txn-nav-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e4e7ec;
}
.txn-nav-name { font-size: 14px; font-weight: 600; color: #101828; }
.txn-nav-role { font-size: 11.5px; color: #667085; font-weight: 500; }

/* -- Page -- */
.txn-page {
    max-width: 100%;
    padding: 36px 24px 60px;
}

/* -- Page header -- */
.txn-page-header { margin-bottom: 28px; }
.txn-page-title {
    font-size: 22px;
    font-weight: 800;
    color: #101828;
    letter-spacing: -0.4px;
    margin-bottom: 4px;
}
.txn-page-sub { font-size: 13.5px; color: #667085; font-weight: 500; }

/* -- Stat cards -- */
.txn-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 32px;
}
.txn-stat {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e4e7ec;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    transition: box-shadow .2s, transform .2s;
}
.txn-stat:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    transform: translateY(-1px);
}
.txn-stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.txn-stat-icon svg { width: 22px; height: 22px; stroke-width: 2; }
.si-blue  { background: #eff8ff; color: #1570ef; }
.si-green { background: #ecfdf3; color: #079455; }
.si-amber { background: #fffaeb; color: #b54708; }
.txn-stat-body { min-width: 0; }
.txn-stat-label {
    font-size: 12px;
    font-weight: 600;
    color: #667085;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 5px;
}
.txn-stat-value {
    font-size: 24px;
    font-weight: 800;
    color: #101828;
    letter-spacing: -0.6px;
    font-family: 'JetBrains Mono', monospace;
}
.txn-stat-value span {
    font-size: 14px;
    font-weight: 600;
    color: #667085;
    font-family: 'Plus Jakarta Sans', sans-serif;
    margin-right: 3px;
}

/* -- Table header -- */
.txn-tbl-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.txn-tbl-title { font-size: 15px; font-weight: 700; color: #101828; letter-spacing: -0.2px; }
.txn-tbl-count {
    display: inline-block;
    background: #f2f4f7;
    color: #344054;
    font-size: 11.5px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    margin-left: 8px;
}
.txn-export {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #fff;
    border: 1px solid #d0d5dd;
    border-radius: 9px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #344054;
    text-decoration: none;
    box-shadow: 0 1px 2px rgba(0,0,0,.05);
    transition: background .15s, border-color .15s, box-shadow .15s;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.txn-export:hover { background: #f9fafb; border-color: #b2b9c4; box-shadow: 0 2px 6px rgba(0,0,0,.08); }
.txn-export svg { width: 14px; height: 14px; stroke-width: 2.5; flex-shrink: 0; }

/* -- Table -- */
.txn-tbl-wrap {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e4e7ec;
    overflow-x: auto;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
}
.txn-tbl {
    width: 100%;
    border-collapse: collapse;
    min-width: 780px;
}
.txn-tbl thead th {
    padding: 13px 20px;
    background: #f9fafb;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    color: #667085;
    text-transform: uppercase;
    letter-spacing: .6px;
    border-bottom: 1px solid #f2f4f7;
    white-space: nowrap;
}
/* User column stays left */
.txn-tbl thead th:first-child { text-align: left; }

.txn-tbl tbody tr {
    border-bottom: 1px solid #f2f4f7;
    transition: background .1s;
}
.txn-tbl tbody tr:last-child { border-bottom: none; }
.txn-tbl tbody tr:hover { background: #fafbfc; }
.txn-tbl td {
    padding: 15px 20px;
    font-size: 13.5px;
    color: #344054;
    vertical-align: middle;
    text-align: center;
}
/* User column stays left */
.txn-tbl td:first-child { text-align: left; }

/* -- User cell -- */
.txn-user { display: flex; align-items: center; gap: 11px; }
.txn-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: .5px;
}
.txn-avatar[data-letter="A"],.txn-avatar[data-letter="B"],.txn-avatar[data-letter="C"] { background: linear-gradient(135deg,#f093fb,#f5576c); }
.txn-avatar[data-letter="D"],.txn-avatar[data-letter="E"],.txn-avatar[data-letter="F"] { background: linear-gradient(135deg,#4facfe,#00f2fe); color:#0c4a6e; }
.txn-avatar[data-letter="G"],.txn-avatar[data-letter="H"],.txn-avatar[data-letter="I"] { background: linear-gradient(135deg,#43e97b,#38f9d7); color:#064e3b; }
.txn-avatar[data-letter="J"],.txn-avatar[data-letter="K"],.txn-avatar[data-letter="L"] { background: linear-gradient(135deg,#fa709a,#fee140); color:#7c2d12; }
.txn-avatar[data-letter="M"],.txn-avatar[data-letter="N"],.txn-avatar[data-letter="O"] { background: linear-gradient(135deg,#a18cd1,#fbc2eb); }
.txn-avatar[data-letter="P"],.txn-avatar[data-letter="Q"],.txn-avatar[data-letter="R"] { background: linear-gradient(135deg,#ffecd2,#fcb69f); color:#b25f3a; }
.txn-avatar[data-letter="S"],.txn-avatar[data-letter="T"],.txn-avatar[data-letter="U"] { background: linear-gradient(135deg,#a1c4fd,#c2e9fb); color:#1e3a8a; }
.txn-avatar[data-letter="V"],.txn-avatar[data-letter="W"],.txn-avatar[data-letter="X"] { background: linear-gradient(135deg,#d4fc79,#96e6a1); color:#166534; }
.txn-avatar[data-letter="Y"],.txn-avatar[data-letter="Z"] { background: linear-gradient(135deg,#f6d365,#fda085); color:#7c2d12; }

.txn-user-name { font-weight: 600; color: #101828; font-size: 13.5px; line-height: 1.3; }
.txn-user-email { font-size: 11.5px; color: #98a2b3; margin-top: 2px; }

/* -- Service badge -- */
.txn-service-badge {
    display: inline-flex;
    align-items: center;
    background: #f2f4f7;
    color: #344054;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 11px;
    border-radius: 6px;
    white-space: nowrap;
}

/* -- Date -- */
.txn-date-main { font-size: 13px; font-weight: 600; color: #344054; }
.txn-date-time { font-size: 11.5px; color: #98a2b3; margin-top: 2px; }

/* -- Duration -- */
.txn-dur { font-size: 13px; color: #667085; white-space: nowrap; }

/* -- Amount -- */
.txn-amount {
    font-weight: 700;
    color: #101828;
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    white-space: nowrap;
}
.txn-amount-currency {
    font-size: 11px;
    color: #98a2b3;
    font-weight: 600;
    margin-right: 2px;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

/* -- Status pills -- */
.txn-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    letter-spacing: .1px;
}
.txn-pill-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.pill-completed,.pill-approved,.pill-paid { background: #ecfdf3; color: #027a48; }
.pill-completed .txn-pill-dot,.pill-approved .txn-pill-dot,.pill-paid .txn-pill-dot { background: #12b76a; }
.pill-pending   { background: #fffaeb; color: #b54708; }
.pill-pending   .txn-pill-dot { background: #f79009; }
.pill-scheduled { background: #eff8ff; color: #1570ef; }
.pill-scheduled .txn-pill-dot { background: #2e90fa; }
.pill-canceled,.pill-rejected { background: #fff1f3; color: #c01048; }
.pill-canceled .txn-pill-dot,.pill-rejected .txn-pill-dot { background: #f63d68; }
.pill-open { background: #f2f4f7; color: #344054; }
.pill-open .txn-pill-dot { background: #667085; }

/* -- Contract download -- */
.txn-dl {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px; height: 32px;
    border-radius: 8px;
    border: 1px solid #e4e7ec;
    background: #fff;
    color: #667085;
    text-decoration: none;
    transition: all .15s;
    box-shadow: 0 1px 2px rgba(0,0,0,.05);
}
.txn-dl:hover {
    background: #4338ca;
    border-color: #4338ca;
    color: #fff;
    box-shadow: 0 3px 8px rgba(67,56,202,.3);
    transform: translateY(-1px);
}
.txn-dl svg { width: 14px; height: 14px; stroke-width: 2.5; }
.txn-no-dl { color: #d0d5dd; display: block; text-align: center; }

/* -- Empty state -- */
.txn-empty { padding: 64px 20px; text-align: center; }
.txn-empty-icon {
    width: 52px; height: 52px;
    background: #f2f4f7;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.txn-empty-icon svg { width: 26px; height: 26px; stroke: #98a2b3; stroke-width: 1.5; }
.txn-empty-title { font-size: 15px; font-weight: 700; color: #344054; margin-bottom: 5px; }
.txn-empty-sub   { font-size: 13px; color: #98a2b3; }

/* -- Responsive -- */
@media (max-width: 768px) {
    .txn-stats { grid-template-columns: 1fr; }
    .txn-page  { padding: 20px 16px 40px; }
    .txn-nav   { padding: 0 16px; }
}
</style>

<div class="txn-root">

    <!-- Navbar -->
    <nav class="txn-nav">
        <a class="txn-logo" href="#">
            <div class="txn-logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
            </div>
            LOGO
        </a>
        <div class="txn-nav-right">
            <div class="txn-nav-bell">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </div>
            <div class="txn-nav-divider"></div>
            <div class="txn-nav-profile">
                <img src="<?php echo esc_url($user_avatar); ?>" alt="" class="txn-nav-avatar">
                <div>
                    <div class="txn-nav-name"><?php echo esc_html($display_name); ?></div>
                    <div class="txn-nav-role">Expert</div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page -->
    <div class="txn-page">

        <!-- Page header -->
        <div class="txn-page-header">
            <div class="txn-page-title">Transaction History</div>
            <div class="txn-page-sub">Track your earnings, sessions, and contracts</div>
        </div>

        <!-- Stat Cards -->
        <div class="txn-stats">
            <div class="txn-stat">
                <div class="txn-stat-icon si-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                    </svg>
                </div>
                <div class="txn-stat-body">
                    <div class="txn-stat-label">Total Revenue</div>
                    <div class="txn-stat-value"><span>Rs</span><?php echo number_format($total_revenue, 2); ?></div>
                </div>
            </div>
            <div class="txn-stat">
                <div class="txn-stat-icon si-green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M23 6l-9.5 9.5-5-5L1 18M17 6h6v6"/>
                    </svg>
                </div>
                <div class="txn-stat-body">
                    <div class="txn-stat-label">Net Earnings</div>
                    <div class="txn-stat-value"><span>Rs</span><?php echo number_format($net_earnings, 2); ?></div>
                </div>
            </div>
            <div class="txn-stat">
                <div class="txn-stat-icon si-amber">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                    </svg>
                </div>
                <div class="txn-stat-body">
                    <div class="txn-stat-label">Pending Payout</div>
                    <div class="txn-stat-value"><span>Rs</span><?php echo number_format($pending_amt, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Table header -->
        <div class="txn-tbl-header">
            <div>
                <span class="txn-tbl-title">All Transactions</span>
                <span class="txn-tbl-count"><?php echo count($transactions); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin-post.php?action=platform_core_flow5_export_csv&month=' . date('Y-m'))); ?>" class="txn-export">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="12" y1="4" x2="12" y2="16"/>
                    <polyline points="8 12 12 16 16 12"/>
                    <line x1="4" y1="20" x2="20" y2="20"/>
                </svg>
                Export CSV
            </a>
        </div>

        <!-- Table -->
        <div class="txn-tbl-wrap">
            <table class="txn-tbl">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Service</th>
                        <th>Date &amp; Time</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Contract</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($transactions):
                    foreach ($transactions as $t):

                        $first = trim($t->customer_first ?? '');
                        $last  = trim($t->customer_last  ?? '');
                        if (!$first && !empty($t->booking_info)) {
                            $info = json_decode($t->booking_info, true);
                            if (!empty($info['firstName'])) $first = $info['firstName'];
                            if (!empty($info['lastName']))  $last  = $info['lastName'];
                        }
                        $student_name = trim($first . ' ' . $last) ?: 'Unknown';
                        $initials     = strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) ?: '?';
                        $first_letter = strtoupper(substr($first, 0, 1)) ?: '?';

                        $service_name = $t->service_name ?: '—';

                        $date_main = '—';
                        $date_time = '';
                        if (!empty($t->booking_start)) {
                            $date_main = wp_date('M j, Y', strtotime($t->booking_start));
                            $date_time = wp_date('g:i A', strtotime($t->booking_start));
                        } elseif (!empty($t->created_at)) {
                            $date_main = wp_date('M j, Y', strtotime($t->created_at));
                        }

                        $duration_display = '—';
                        if (!empty($t->service_duration)) {
                            $mins = (int)($t->service_duration / 60);
                            if ($mins >= 60) {
                                $hrs = floor($mins / 60);
                                $rem = $mins % 60;
                                $duration_display = $hrs . 'h' . ($rem ? " {$rem}m" : '');
                            } else {
                                $duration_display = $mins . ' min';
                            }
                        }

                        $status_raw   = strtolower($t->status ?? 'open');
                        $pill_class   = 'pill-' . $status_raw;
                        $status_label = ucfirst($status_raw);

                        $contract_url = '';
                        $uploads = wp_get_upload_dir();
                        $contract = $wpdb->get_row($wpdb->prepare(
                            "SELECT c.pdf_path FROM $table_contracts c
                             INNER JOIN $table_requests r ON r.id = c.request_id
                             WHERE c.status = 'signed'
                               AND (
                                   r.appointment_id = (SELECT appointmentId FROM $table_amelia_book WHERE id = %d LIMIT 1)
                                   OR r.order_id = %d
                               )
                             LIMIT 1",
                            (int)$t->amelia_booking_id,
                            (int)$t->order_item_id
                        ));
                        if ($contract && !empty($contract->pdf_path) && file_exists($contract->pdf_path)) {
                            $contract_url = str_replace($uploads['basedir'], $uploads['baseurl'], $contract->pdf_path);
                        }
                    ?>
                    <tr>
                        <!-- User -->
                        <td>
                            <div class="txn-user">
                                <div class="txn-avatar" data-letter="<?php echo esc_attr($first_letter); ?>">
                                    <?php echo esc_html($initials); ?>
                                </div>
                                <div>
                                    <div class="txn-user-name"><?php echo esc_html($student_name); ?></div>
                                    <?php if (!empty($t->customer_email)): ?>
                                    <div class="txn-user-email"><?php echo esc_html($t->customer_email); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Service -->
                        <td>
                            <span class="txn-service-badge"><?php echo esc_html($service_name); ?></span>
                        </td>

                        <!-- Date & Time -->
                        <td>
                            <div class="txn-date-main"><?php echo esc_html($date_main); ?></div>
                            <?php if ($date_time): ?>
                            <div class="txn-date-time"><?php echo esc_html($date_time); ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Duration -->
                        <td><span class="txn-dur"><?php echo esc_html($duration_display); ?></span></td>

                        <!-- Amount -->
                        <td>
                            <span class="txn-amount">
                                <span class="txn-amount-currency">Rs</span><?php echo number_format((float)$t->gross, 2); ?>
                            </span>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="txn-pill <?php echo esc_attr($pill_class); ?>">
                                <span class="txn-pill-dot"></span>
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>

                        <!-- Contract -->
                        <td>
                            <?php if ($contract_url): ?>
                                <a href="<?php echo esc_url($contract_url); ?>" target="_blank" class="txn-dl" title="Download Contract">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <line x1="12" y1="4" x2="12" y2="16"/>
                                        <polyline points="8 12 12 16 16 12"/>
                                        <line x1="4" y1="20" x2="20" y2="20"/>
                                    </svg>
                                </a>
                            <?php else: ?>
                                <span class="txn-no-dl">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach;
                else: ?>
                    <tr><td colspan="7">
                        <div class="txn-empty">
                            <div class="txn-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <rect x="2" y="3" width="20" height="14" rx="2"/>
                                    <path d="M8 21h8M12 17v4"/>
                                </svg>
                            </div>
                            <div class="txn-empty-title">No transactions yet</div>
                            <div class="txn-empty-sub">Your completed sessions will appear here</div>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>