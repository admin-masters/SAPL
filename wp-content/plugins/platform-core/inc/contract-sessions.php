<?php
/**
 * Contracts & Sessions screen for College Admins
 * Shortcode: [platform_contract_sessions]
 * FINAL VERSION: Clickable cards, no debug output, no Zoom buttons
 */
if (!defined('ABSPATH')) exit;

// --- Auth redirect helper ---
if (!function_exists('pcore_redirect_if_not_logged_in')) {
    function pcore_redirect_if_not_logged_in() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/college-dashboard/'));
            exit;
        }
    }
}

add_action('init', function() {
    add_shortcode('platform_contract_sessions', 'pcore_sc_contract_sessions');
});

add_action('template_redirect', function() {
    global $post;
    if (
        is_a($post, 'WP_Post') &&
        has_shortcode($post->post_content, 'platform_contract_sessions') &&
        !is_user_logged_in()
    ) {
        wp_safe_redirect(home_url('/college-dashboard/'));
        exit;
    }
});

add_action('rest_api_init', function() {
    register_rest_route('platform-core/v1', '/contracts', [
        'methods'             => 'GET',
        'callback'            => 'pcore_api_contracts_list',
        'permission_callback' => function () { return is_user_logged_in() && current_user_can('college_admin'); },
    ]);
});

function pcore_sc_contract_sessions() {
    $tpl_path = plugin_dir_path(__FILE__) . '../templates/contractsessions.php';
    if (!file_exists($tpl_path)) {
        return '<div class="notice notice-error"><p>Template not found: templates/contractsessions.php</p></div>';
    }

    ob_start();
    include $tpl_path;
    $raw = ob_get_clean();

    // --- CSS extraction ---
    $css = '';
    if (preg_match_all('~<style[^>]*>(.*?)</style>~is', $raw, $m_styles)) {
        $css = trim(implode("\n\n", array_map('trim', $m_styles[1])));
        if (!function_exists('pcore_rewrite_css_urls_to_base')) {
            function pcore_rewrite_css_urls_to_base($css, $base){
                return preg_replace_callback('~url\\(([^)]+)\\)~i', function($m) use ($base){
                    $u = trim($m[1], '\'" ');
                    if (preg_match('~^https?://|^data:~i', $u)) return "url($u)";
                    return 'url(' . rtrim($base, '/') . '/' . ltrim($u, '/') . ')';
                }, $css);
            }
        }
        $tpl_base_url = trailingslashit( plugins_url('../templates', __FILE__) );
        $css = pcore_rewrite_css_urls_to_base($css, $tpl_base_url);
    }

    $html = $raw;
    if (preg_match('~<body[^>]*>(.*)</body>~is', $raw, $m_body)) {
        $html = $m_body[1];
    }

    $html = preg_replace('~<div\s+class=["\']top["\'][^>]*>.*?</div>\s*</div>~is', '', $html, 1);

    $html = preg_replace(
        '~(<div\s[^>]*class=["\']banner["\'][^>]*>.*?<small>)[^<]*(</small>)~is',
        '$1<span id="pcs-contracts-subtitle">Loading...</span>$2',
        $html,
        1
    );

    $card_count = 0;
    $html = preg_replace_callback(
        '~<div\s+class=["\']card["\']~i',
        function($m) use (&$card_count) {
            $card_count++;
            if ($card_count === 1) return '<div class="card" id="pcs-card-contracts"';
            if ($card_count === 2) return '<div class="card" id="pcs-card-sessions"';
            return $m[0];
        },
        $html
    );

    $handle = 'pcore-contractsessions';
    wp_register_script(
        $handle,
        plugins_url('../assets/contractsessions.js', __FILE__),
        array('jquery'),
        '1.2.0',
        true
    );

    $u                = wp_get_current_user();
    $avatar_url_small = get_avatar_url($u->ID, array('size' => 48, 'default' => 'mystery'));
    $sign_page_base   = apply_filters('pcore/contract/sign_page_base', site_url('/college-classes/my-classes/'));

    wp_localize_script($handle, 'pcoreContracts', array(
        'listEndpoint' => esc_url_raw( rest_url('platform-core/v1/contracts') ),
        'nonce'        => wp_create_nonce('wp_rest'),
        'uploadsBase'  => wp_upload_dir()['baseurl'],
        'now'          => current_time('mysql'),
        'tz'           => wp_timezone_string(),
        'signBase'     => esc_url_raw($sign_page_base),
        'user'         => array(
            'name'       => $u->display_name,
            'first_name' => $u->user_firstname ? $u->user_firstname : $u->display_name,
            'avatar'     => esc_url_raw($avatar_url_small),
        ),
    ));

    wp_enqueue_script($handle);

    wp_add_inline_script($handle, '
(function(){

    /* ---- inject CSS once ---- */
    if (!document.getElementById("pcs-patch-css")) {
        var s = document.createElement("style");
        s.id = "pcs-patch-css";
        s.textContent =
            "#pc-contractsessions-root{max-width:1400px;margin:0 auto;padding:24px 28px;}" +
            ".pcs-cards-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;}" +
            "@media(max-width:900px){.pcs-cards-grid{grid-template-columns:1fr;}}" +

            "#pcs-card-contracts .item{" +
                "border:1px solid #e5e7eb;" +
                "border-radius:12px;" +
                "padding:14px 16px;" +
                "margin-bottom:10px;" +
                "box-shadow:0 1px 3px rgba(0,0,0,.05);" +
                "transition:box-shadow .2s,transform .2s;" +
                "border-bottom:none;" +
                "cursor:pointer;" +
            "}" +
            "#pcs-card-contracts .item:last-child{border-bottom:none;margin-bottom:0;}" +
            "#pcs-card-contracts .item:hover{box-shadow:0 4px 10px rgba(0,0,0,.08);transform:translateY(-2px);}" +

            ".pcs-group-label{" +
                "font-size:11px;" +
                "font-weight:700;" +
                "text-transform:uppercase;" +
                "letter-spacing:.05em;" +
                "color:#94a3b8;" +
                "margin:14px 0 6px;" +
            "}" +
            ".pcs-group-label:first-child{margin-top:0;}" +

            ".pcs-view-btn{" +
                "display:inline-block;" +
                "margin-top:10px;" +
                "padding:6px 13px;" +
                "background:#fff;" +
                "color:#2563eb;" +
                "border:1px solid #2563eb;" +
                "border-radius:8px;" +
                "font-size:12px;" +
                "font-weight:600;" +
                "text-decoration:none;" +
                "cursor:pointer;" +
                "transition:background .15s;" +
            "}" +
            ".pcs-view-btn:hover{background:#eff6ff;}" +
            ".pcs-view-btn.disabled{opacity:.4;pointer-events:none;cursor:default;}" +

            ".pcs-pay-btn{" +
                "display:inline-block;" +
                "margin-top:10px;" +
                "margin-left:8px;" +
                "padding:6px 13px;" +
                "background:#0f172a;" +
                "color:#fff;" +
                "border:none;" +
                "border-radius:8px;" +
                "font-size:12px;" +
                "font-weight:600;" +
                "text-decoration:none;" +
                "cursor:pointer;" +
                "transition:background .15s;" +
            "}" +
            ".pcs-pay-btn:hover{background:#1e293b;}" +

            ".pcs-sign-btn{" +
                "display:inline-block;" +
                "margin-top:10px;" +
                "margin-left:0px;" +
                "padding:6px 13px;" +
                "background:#000000;" +
                "color:#fff;" +
                "border:none;" +
                "border-radius:8px;" +
                "font-size:12px;" +
                "font-weight:600;" +
                "text-decoration:none;" +
                "cursor:pointer;" +
                "transition:background .15s;" +
            "}" +
            ".pcs-sign-btn:hover{background:#6d28d9;}" +

            ".pcs-paid-badge{" +
                "display:inline-flex;" +
                "align-items:center;" +
                "gap:5px;" +
                "margin-top:10px;" +
                "padding:5px 12px;" +
                "background:#ecfdf5;" +
                "color:#059669;" +
                "border:1px solid #6ee7b7;" +
                "border-radius:8px;" +
                "font-size:12px;" +
                "font-weight:600;" +
            "}" +

            "#pcs-card-sessions .item{" +
                "border:1px solid #e5e7eb;" +
                "border-radius:12px;" +
                "padding:14px 16px;" +
                "margin-bottom:10px;" +
                "box-shadow:0 1px 3px rgba(0,0,0,.05);" +
                "border-bottom:none;" +
                "cursor:pointer;" +
                "transition:box-shadow .2s,transform .2s;" +
            "}" +
            "#pcs-card-sessions .item:last-child{border-bottom:none;margin-bottom:0;}" +
            "#pcs-card-sessions .item:hover{box-shadow:0 4px 10px rgba(0,0,0,.08);transform:translateY(-2px);}" +

            ".badge.pcs-waiting{background:#fef9c3;color:#854d0e;}" +
            ".badge.pcs-not-responded{background:#f1f5f9;color:#475569;}" +
            ".badge.pcs-rejected{background:#fee2e2;color:#991b1b;}" +
            ".badge.pcs-awaiting-payment{background:#fef3c7;color:#92400e;}" +
            ".badge.pcs-awaiting-sig{background:#ede9fe;color:#5b21b6;}" +
            ".badge.pcs-booked{background:#ecfdf5;color:#065f46;}" +

            ".pcs-empty{text-align:center;padding:24px;color:#94a3b8;font-size:14px;}";

        document.head.appendChild(s);

        document.addEventListener("DOMContentLoaded", function() {
            var root = document.getElementById("pc-contractsessions-root");
            var c1   = document.getElementById("pcs-card-contracts");
            var c2   = document.getElementById("pcs-card-sessions");
            if (root && c1 && c2) {
                var grid = document.createElement("div");
                grid.className = "pcs-cards-grid";
                root.insertBefore(grid, c1);
                grid.appendChild(c1);
                grid.appendChild(c2);
            }
        });
    }

    /* ---- helpers ---- */
    function esc(v) {
        return String(v || "")
            .replace(/&/g,"&amp;").replace(/</g,"&lt;")
            .replace(/>/g,"&gt;").replace(/"/g,"&quot;");
    }

    function fmtDate(iso) {
        if (!iso) return "";
        var d = new Date(iso);
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleDateString("en-GB", {weekday:"short", day:"numeric", month:"short", year:"numeric"}) +
               " at " + d.toLocaleTimeString("en-GB", {hour:"2-digit", minute:"2-digit"});
    }

    /* ---- one accepted-contract .item ---- */
    function contractItem(item) {
        var now     = new Date();
        var startTs = item.start_iso ? new Date(item.start_iso) : null;
        var endTs   = startTs
            ? new Date(startTs.getTime() + (item.duration_minutes || 60) * 60000)
            : null;
        var isDone = endTs && endTs < now;

        var badgeTxt = isDone ? "Completed" : "Upcoming";

        var viewBtn = item.pdf_url
            ? "<a class=\"pcs-view-btn\" href=\"" + esc(item.pdf_url) + "\" target=\"_blank\" rel=\"noopener noreferrer\" onclick=\"event.stopPropagation();\">View Contract</a>"
            : "<span class=\"pcs-view-btn disabled\" title=\"PDF not yet available\">View Contract</span>";

        var paySection = "";
        if (item.is_booked) {
            paySection = "<span class=\"pcs-paid-badge\">Payment Completed</span>";
        }

        return "<div class=\"item\" onclick=\"window.location.href=\'/session-info/?session_id=" + item.request_id + "\'\">" +
            "<div class=\"item-head\">" +
                "<strong>" + esc(item.topic) + "</strong>" +
                "<span class=\"badge active\">" + badgeTxt + "</span>" +
            "</div>" +
            "<small>Expert: " + esc(item.expert_name) + "</small>" +
            (item.start_iso ? "<small>" + esc(fmtDate(item.start_iso)) + "</small>" : "") +
            "<small>" + (item.duration_minutes || 0) + " minutes</small>" +
            "<div style=\"display:flex;flex-wrap:wrap;gap:0;align-items:center;\" onclick=\"event.stopPropagation();\">" +
                viewBtn + paySection +
            "</div>" +
        "</div>";
    }

    function sessionBadge(item) {
        if (item.is_booked) return {cls: "pcs-booked", txt: "Booked"};

        var status  = (item.status || item.badge || "").toLowerCase().trim();
        var dateStr = item.proposed_start_iso || item.start_iso || "";
        var isPast  = dateStr ? (new Date(dateStr) < new Date()) : false;

        if (isPast) return {cls: "pcs-not-responded", txt: "Not Responded"};
        if (status === "rejected") return {cls: "pcs-rejected", txt: "Rejected"};
        if (item.needs_payment || status === "needs_payment" || status === "awaiting payment")
            return {cls: "pcs-awaiting-payment", txt: "Awaiting Payment"};
        if (item.sign_token || status === "awaiting_signature" || status === "awaiting signature")
            return {cls: "pcs-awaiting-sig", txt: "Awaiting Signature"};
        return {cls: "pcs-waiting", txt: "Waiting Response"};
    }

    /* ---- one pending-session .item ---- */
    function sessionItem(item) {
        var bdg     = sessionBadge(item);
        var dateStr = item.proposed_start_iso || item.start_iso || "";
        var actionBtns = "";

        if (item.is_booked) {
            actionBtns = "<span class=\"pcs-paid-badge\">Payment Completed</span>";
        } else {
            if (item.sign_url && bdg.cls === "pcs-awaiting-sig") {
                actionBtns += "<a class=\"pcs-sign-btn\" href=\"" + esc(item.sign_url) + "\" onclick=\"event.stopPropagation();\">Review &amp; Sign Contract</a>";
            }
            if (item.needs_payment && item.pay_url && bdg.cls === "pcs-awaiting-payment") {
                actionBtns += "<a class=\"pcs-pay-btn\" href=\"" + esc(item.pay_url) + "\" onclick=\"event.stopPropagation();\">Pay Now</a>";
            }
        }

        return "<div class=\"item\" onclick=\"window.location.href=\'/session-info/?session_id=" + item.request_id + "\'\">" +
            "<div class=\"item-head\">" +
                "<strong>" + esc(item.topic) + "</strong>" +
                "<span class=\"badge " + bdg.cls + "\">" + bdg.txt + "</span>" +
            "</div>" +
            "<small>Expert: " + esc(item.expert_name) + "</small>" +
            (dateStr ? "<small>" + esc(fmtDate(dateStr)) + "</small>" : "") +
            (item.duration_minutes ? "<small>" + item.duration_minutes + " minutes</small>" : "") +
            (actionBtns ? "<div style=\"display:flex;flex-wrap:wrap;gap:0;align-items:center;margin-top:4px;\" onclick=\"event.stopPropagation();\">" + actionBtns + "</div>" : "") +
        "</div>";
    }

    /* ---- fetch ---- */
    document.addEventListener("DOMContentLoaded", function() {
        var subtitle      = document.getElementById("pcs-contracts-subtitle");
        var contractsCard = document.getElementById("pcs-card-contracts");
        var sessionsCard  = document.getElementById("pcs-card-sessions");

        fetch(pcoreContracts.listEndpoint, {
            headers: {"X-WP-Nonce": pcoreContracts.nonce},
            credentials: "same-origin"
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            var signed   = data.signed   || [];
            var unsigned = data.unsigned || [];
            var requests = data.requests || [];

            if (subtitle) {
                var ac = signed.length;
                var pn = requests.length + unsigned.length;
                subtitle.textContent =
                    "You have " +
                    ac + " accepted contract" + (ac !== 1 ? "s" : "") +
                    " and " +
                    pn + " pending session request" + (pn !== 1 ? "s" : "");
            }

            if (contractsCard) {
                contractsCard.querySelectorAll(".item, .pcs-group-label, .pcs-empty").forEach(function(el){ el.remove(); });

                var now = new Date();
                var upcoming  = [];
                var completed = [];

                signed.forEach(function(item) {
                    var startTs = item.start_iso ? new Date(item.start_iso) : null;
                    var endTs   = startTs ? new Date(startTs.getTime() + (item.duration_minutes || 60) * 60000) : null;
                    if (endTs && endTs < now) { completed.push(item); } else { upcoming.push(item); }
                });

                upcoming.sort(function(a,b){
                    var ta = a.start_iso ? new Date(a.start_iso).getTime() : Infinity;
                    var tb = b.start_iso ? new Date(b.start_iso).getTime() : Infinity;
                    return ta - tb;
                });
                completed.sort(function(a,b){
                    var ta = a.start_iso ? new Date(a.start_iso).getTime() : -Infinity;
                    var tb = b.start_iso ? new Date(b.start_iso).getTime() : -Infinity;
                    return tb - ta;
                });

                if (!signed.length) {
                    contractsCard.insertAdjacentHTML("beforeend", "<div class=\"pcs-empty\">No accepted contracts yet.</div>");
                } else {
                    var html = "";
                    if (upcoming.length)  html += "<div class=\"pcs-group-label\">Upcoming</div>"  + upcoming.map(contractItem).join("");
                    if (completed.length) html += "<div class=\"pcs-group-label\">Completed</div>" + completed.map(contractItem).join("");
                    contractsCard.insertAdjacentHTML("beforeend", html);
                }
            }

            if (sessionsCard) {
                sessionsCard.querySelectorAll(".item, .pcs-empty").forEach(function(el){ el.remove(); });
                var allPending = unsigned.concat(requests);

                function sessionPriority(item) {
                    if (item.is_booked) return 4;
                    var status  = (item.status || item.badge || "").toLowerCase().trim();
                    var dateStr = item.proposed_start_iso || item.start_iso || "";
                    var isPast  = dateStr ? (new Date(dateStr) < new Date()) : false;
                    if (isPast || status === "rejected") return 3;
                    if (item.needs_payment) return 2;
                    if (item.sign_token)    return 1;
                    return 2;
                }

                allPending.sort(function(a, b) {
                    var pa = sessionPriority(a), pb = sessionPriority(b);
                    if (pa !== pb) return pa - pb;
                    var da = a.proposed_start_iso || a.start_iso || "";
                    var db = b.proposed_start_iso || b.start_iso || "";
                    return (da ? new Date(da).getTime() : Infinity) - (db ? new Date(db).getTime() : Infinity);
                });

                sessionsCard.insertAdjacentHTML("beforeend",
                    allPending.length
                        ? allPending.map(sessionItem).join("")
                        : "<div class=\"pcs-empty\">No pending session requests.</div>"
                );
            }
        })
        .catch(function(err){
            if (subtitle)      subtitle.textContent = "Unable to load data.";
            if (contractsCard) contractsCard.insertAdjacentHTML("beforeend", "<div class=\"pcs-empty\">Unable to load contracts.</div>");
            if (sessionsCard)  sessionsCard.insertAdjacentHTML("beforeend",  "<div class=\"pcs-empty\">Unable to load sessions.</div>");
        });
    });

})();
', 'after');

    // --- Nav URLs ---
    $url_dashboard   = home_url('/platform-dashboard');
    $url_find        = home_url('/find_educators');
    $url_sessions    = home_url('/college-sessions');
    $url_contracts   = get_permalink();
    $url_shortlisted = home_url('/shortlisted-educators');

    $u          = wp_get_current_user();
    $nav_avatar = get_avatar_url($u->ID, array('size' => 36, 'default' => 'mystery'));
    $first_name = $u->user_firstname ? $u->user_firstname : $u->display_name;

    $navbar_css = '<style id="pc-contractsessions-navbar-css">
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
</style>';

    $navbar_html = sprintf(
        '<nav class="pc-nav"><div class="pc-nav-inner">
            <a href="%s" class="pc-nav-logo">LOGO</a>
            <div class="pc-nav-links">
                <a href="%s">Dashboard</a>
                <a href="%s">Find Educators</a>
                <a href="%s">Sessions</a>
                <a href="%s" class="active">Contracts</a>
                <a href="%s">Shortlisted</a>
            </div>
            <div class="pc-nav-right">
                <img src="%s" alt="Profile">
                <span class="pc-nav-username">Hi, %s</span>
                <a href="%s" class="pc-nav-btn">Logout</a>
            </div>
        </div></nav>',
        esc_url(home_url()),
        esc_url($url_dashboard),
        esc_url($url_find),
        esc_url($url_sessions),
        esc_url($url_contracts),
        esc_url($url_shortlisted),
        esc_url($nav_avatar),
        esc_html($first_name),
        esc_url(wp_logout_url(home_url()))
    );

    $inline_css_tag = $css ? '<style id="pc-contractsessions-inline">' . $css . '</style>' : '';

    return $navbar_css . $navbar_html . $inline_css_tag . '<div id="pc-contractsessions-root">' . $html . '</div>';
}


/**
 * REST: List contracts/sessions for the current college admin.
 * UPDATED: Now includes zoom_url from platform_calendar_map AND Amelia appointments
 */
function pcore_api_contracts_list(WP_REST_Request $req) {
    global $wpdb;

    $tbl_requests  = $wpdb->prefix . 'platform_requests';
    $tbl_contracts = $wpdb->prefix . 'platform_contracts';
    $tbl_calendar  = $wpdb->prefix . 'platform_calendar_map';
    $uid           = get_current_user_id();

    // Helper function to extract zoom URL from various formats
    $extract_zoom_url = function($raw_zoom_data) {
        if (empty($raw_zoom_data)) return '';
        
        // If it's already a URL, return it
        if (filter_var($raw_zoom_data, FILTER_VALIDATE_URL)) {
            return $raw_zoom_data;
        }
        
        // If it's JSON, try to extract join_url
        $json_data = json_decode($raw_zoom_data, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
            // Check common JSON structures
            if (isset($json_data['join_url'])) return $json_data['join_url'];
            if (isset($json_data['joinUrl'])) return $json_data['joinUrl'];
            if (isset($json_data['url'])) return $json_data['url'];
            if (isset($json_data['meeting_url'])) return $json_data['meeting_url'];
        }
        
        // If it contains a URL within the text, extract it
        if (preg_match('#https://[^\s]+zoom[^\s]+#i', $raw_zoom_data, $matches)) {
            return $matches[0];
        }
        
        return '';
    };

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT
            c.id                  AS contract_id,
            c.status              AS contract_status,
            c.total_amount        AS total_amount,
            c.class_start_iso     AS class_start_iso,
            c.duration_minutes    AS c_duration,
            c.sign_token          AS sign_token,
            c.sign_token_expires  AS sign_token_expires,
            c.signed_at           AS signed_at,
            c.pdf_path            AS pdf_path,
            c.order_id            AS order_id,
            r.id                  AS request_id,
            r.topic               AS topic,
            r.proposed_start_iso  AS proposed_start_iso,
            r.duration_minutes    AS r_duration,
            r.status              AS request_status,
            r.expert_user_id      AS expert_user_id,
            r.appointment_id      AS appointment_id,
            COALESCE(
                cal1.zoom_url,
                cal2.zoom_url,
                cal3.zoom_url,
                a.zoomMeeting
            )                     AS zoom_url
        FROM {$tbl_contracts} c
        INNER JOIN {$tbl_requests} r ON r.id = c.request_id
        LEFT JOIN {$tbl_calendar} cal1 ON cal1.object_id = r.id AND cal1.source = 'platform_request'
        LEFT JOIN {$tbl_calendar} cal2 ON cal2.object_id = r.appointment_id AND cal2.source = 'amelia_appointment'
        LEFT JOIN {$tbl_calendar} cal3 ON cal3.object_id = c.id AND cal3.source = 'platform_contract'
        LEFT JOIN {$wpdb->prefix}amelia_appointments a ON a.id = r.appointment_id
        WHERE r.college_user_id = %d
        ORDER BY c.id DESC
    ", $uid));

    $req_only = $wpdb->get_results($wpdb->prepare("
        SELECT r.*, 
            COALESCE(
                cal1.zoom_url,
                cal2.zoom_url,
                a.zoomMeeting
            ) AS zoom_url
        FROM {$tbl_requests} r
        LEFT JOIN {$tbl_contracts} c ON c.request_id = r.id
        LEFT JOIN {$tbl_calendar} cal1 ON cal1.object_id = r.id AND cal1.source = 'platform_request'
        LEFT JOIN {$tbl_calendar} cal2 ON cal2.object_id = r.appointment_id AND cal2.source = 'amelia_appointment'
        LEFT JOIN {$wpdb->prefix}amelia_appointments a ON a.id = r.appointment_id
        WHERE r.college_user_id = %d
          AND c.id IS NULL
        ORDER BY r.id DESC
    ", $uid));

    $accepted = [];
    $unsigned = [];
    $now_ts   = time();

    foreach ($rows as $r) {
        $expert      = get_userdata((int)$r->expert_user_id);
        $expert_name = $expert ? $expert->display_name : 'Expert';

        $start_iso = $r->class_start_iso ?: $r->proposed_start_iso;
        $dur_min   = (int)($r->c_duration ?: $r->r_duration);
        if ($dur_min <= 0) $dur_min = 60;

        $is_signed = (
            !empty($r->signed_at)
            || in_array($r->contract_status, ['signed','accepted','agreed','approved'], true)
        );

        $is_booked = ($r->request_status === 'booked');

        $total   = (float)$r->total_amount;
        $pay_url = '';

        if (!$is_booked && $total > 0 && !empty($r->order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order((int)$r->order_id);
            if ($order && $order->has_status(['pending', 'failed', 'on-hold'])) {
                $pay_url = $order->get_checkout_payment_url(false);
            }
        }

        $pdf_url = '';
        if (!empty($r->pdf_path)) {
            $uploads = wp_upload_dir();
            $base    = trailingslashit($uploads['basedir']);
            if (strpos($r->pdf_path, $base) === 0) {
                $pdf_url = $uploads['baseurl'] . '/' . ltrim(str_replace($base, '', $r->pdf_path), '/');
            } elseif (filter_var($r->pdf_path, FILTER_VALIDATE_URL)) {
                $pdf_url = $r->pdf_path;
            }
        }

        $end_ts     = $start_iso ? strtotime($start_iso) + ($dur_min * 60) : 0;
        $status_tag = ($start_iso && $end_ts < $now_ts) ? 'completed' : 'upcoming';

        $item = [
            'contract_id'      => (int)$r->contract_id,
            'request_id'       => (int)$r->request_id,
            'topic'            => $r->topic,
            'expert_name'      => $expert_name,
            'start_iso'        => $start_iso,
            'duration_minutes' => $dur_min,
            'status_tag'       => $status_tag,
            'pdf_url'          => $pdf_url,
            'total_amount'     => $total,
            'appointment_id'   => (int)$r->appointment_id,
            'is_booked'        => $is_booked,
            'zoom_url'         => $extract_zoom_url($r->zoom_url),
        ];

        if ($is_signed && ($is_booked || $total <= 0)) {
            $accepted[] = $item;

        } elseif ($is_signed && !$is_booked && !empty($pay_url)) {
            $item['needs_payment'] = true;
            $item['pay_url']       = $pay_url;
            $item['badge']         = 'Awaiting Payment';
            $unsigned[] = $item;

        } else {
            $valid_token = !empty($r->sign_token) && pcore_token_valid($r->sign_token_expires);

            if (!$valid_token && !$is_signed) {
                if (apply_filters('pcore/contracts/auto_refresh_sign_token', true)) {
                    $new_token = wp_generate_password(32, false);
                    $new_exp   = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS * 7);
                    $wpdb->update(
                        $tbl_contracts,
                        ['sign_token' => $new_token, 'sign_token_expires' => $new_exp],
                        ['id' => (int)$r->contract_id],
                        ['%s', '%s'], ['%d']
                    );
                    $r->sign_token         = $new_token;
                    $r->sign_token_expires = $new_exp;
                    $valid_token           = true;
                }
            }

            if ($valid_token) {
                $item['sign_token'] = $r->sign_token;
                $item['sign_url']   = pcore_contract_sign_url($r->sign_token);
                $item['expires_at'] = $r->sign_token_expires;
                $item['badge']      = 'Awaiting Signature';
            } else {
                $item['badge'] = 'Pending';
            }

            $unsigned[] = $item;
        }
    }

    $requests = [];
    foreach ($req_only as $r) {
        $expert = get_userdata((int)$r->expert_user_id);
        $requests[] = [
            'request_id'         => (int)$r->id,
            'topic'              => $r->topic,
            'expert_name'        => $expert ? $expert->display_name : 'Expert',
            'status'             => $r->status,
            'proposed_start_iso' => $r->proposed_start_iso,
            'duration_minutes'   => (int)$r->duration_minutes,
            'is_booked'          => ($r->status === 'booked'),
            'zoom_url'           => $extract_zoom_url($r->zoom_url),
        ];
    }
    
    return ['signed' => $accepted, 'unsigned' => $unsigned, 'requests' => $requests];
}


function pcore_contract_sign_url($token) {
    return esc_url(add_query_arg('pc_contract', rawurlencode($token), site_url('/sign-contract/')));
}

function pcore_token_valid($expires_mysql) {
    if (empty($expires_mysql)) return false;
    return (strtotime($expires_mysql) > time());
}