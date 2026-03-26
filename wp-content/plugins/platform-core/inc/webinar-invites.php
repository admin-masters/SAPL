<?php
/**
 * Platform Core ï¿½ Flow 3: Expert conducts webinar
 * Invite -> Accept/Reject/Briefing UI; Accept => create Amelia Event (+Zoom) + Woo product link;
 * Reschedule & cancellation paths update Amelia & attendee calendars.
 *
 * Requires: WooCommerce, Amelia (Elite API enabled OR Pro with manual event creation), Flow-2 Google settings.
 */
if (!defined('ABSPATH')) exit;

/* ---------------------------
   0) Utilities & Settings
----------------------------*/

// --- Pro/Elite feature switch ---
if (!function_exists('platform_core_has_amelia_api')) {
    function platform_core_has_amelia_api(): bool {
        $k = 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm';
        return is_string($k) && $k !== '';
    }
}
if (!function_exists('platform_core_amelia_api_headers')) {
    function platform_core_amelia_api_headers() {
        $apiKey = get_option('platform_amelia_api_key', 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm');
        return [
            'Content-Type' => 'application/json',
            'Amelia'       => 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm'
        ];
    }

}
if (!function_exists('platform_core_amelia_api_base')) {
    function platform_core_amelia_api_base($path) {
        return admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1' . $path);
    }
}
function platform_core_get_employee_id_for_user($user_id) {
    // Try cached mapping first
    $cached = (int) get_user_meta($user_id, 'platform_amelia_employee_id', true);
    if ($cached) return $cached;

    // Try to find by email
    $user  = get_userdata($user_id);
    if (!$user) return 0;

    $url = platform_core_amelia_api_base('/employees');  // Use GET employees and filter by email
    $res = wp_remote_get($url, ['headers'=>platform_core_amelia_api_headers(), 'timeout'=>20]);
    if (is_wp_error($res)) return 0;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (!empty($data['data']['items'])) {
        foreach ($data['data']['items'] as $emp) {
            if (!empty($emp['email']) && strtolower($emp['email']) === strtolower($user->user_email)) {
                update_user_meta($user_id, 'platform_amelia_employee_id', (int)$emp['id']);
                return (int)$emp['id'];
            }
        }
    }
    return 0;
}
/* ---------------------------------------------------
   1) CPT: Webinar Invites (admin composer + columns)
----------------------------------------------------*/
add_action('init', function () {
    register_post_type('platform_invite', [
        'label' => 'Webinar Invites',
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'editor', 'author'],
        'menu_position' => 25,
        'menu_icon' => 'dashicons-email-alt2',
        'map_meta_cap' => true
    ]);
});

add_action('add_meta_boxes', function () {
    add_meta_box('platform_invite_meta', 'Invite Details', function ($post) {
        $expert = (int) get_post_meta($post->ID, '_expert_user_id', true);
        $when   = esc_attr(get_post_meta($post->ID, '_proposed_start', true));
        $dur    = (int)  get_post_meta($post->ID, '_duration_mins', true) ?: 60;
        $cap    = (int)  get_post_meta($post->ID, '_capacity', true) ?: 100;
        $price  = (float) get_post_meta($post->ID, '_price', true) ?: 0;
        $brief  = esc_textarea(get_post_meta($post->ID, '_briefing_notes', true));
        $blink  = esc_attr(get_post_meta($post->ID, '_briefing_link', true));
        $status = esc_attr(get_post_meta($post->ID, '_invite_status', true)) ?: 'pending';
        wp_nonce_field('platform_invite_save', 'platform_invite_nonce');
        ?>
        <p><label><strong>Expert (WP user)</strong><br/>
            <?php
           wp_dropdown_users([
  'name'              => 'expert_user_id',
  'selected'          => $expert,
  'include_selected'  => true,                 // keeps value visible even if filtered out
  'role__in'          => ['expert', 'administrator'], // list experts; admins optional
  'orderby'           => 'display_name',
  'order'             => 'ASC',
  'show_option_none'  => '— Select Expert —'
]);            ?>
        </label></p>
        <p><label><strong>Proposed start (yyyy-mm-ddThh:mm)</strong><br/>
            <input type="datetime-local" name="proposed_start" value="<?php echo $when; ?>" style="width:280px">
        </label></p>
        <p><label><strong>Duration (minutes)</strong> <input type="number" min="15" step="15" name="duration_mins" value="<?php echo $dur; ?>" style="width:120px"></label></p>
        <p><label><strong>Capacity</strong> <input type="number" min="1" step="1" name="capacity" value="<?php echo $cap; ?>" style="width:120px"></label></p>
        <p><label><strong>Price (set 0 for free)</strong> <input type="number" min="0" step="0.01" name="price" value="<?php echo $price; ?>" style="width:120px"></label></p>
        <p><label><strong>Briefing notes</strong><br/>
            <textarea name="briefing_notes" rows="4" style="width:100%"><?php echo $brief; ?></textarea></label></p>
        <p><label><strong>Briefing link (doc/drive)</strong><br/>
            <input type="url" name="briefing_link" value="<?php echo $blink; ?>" style="width:100%"></label></p>
        <p><em>Status:</em> <code><?php echo esc_html($status); ?></code></p>
        <?php
    }, 'platform_invite', 'normal', 'high');
});

add_action('save_post_platform_invite', function ($post_id) {
    if (!isset($_POST['platform_invite_nonce']) || !wp_verify_nonce($_POST['platform_invite_nonce'], 'platform_invite_save')) return;
    update_post_meta($post_id, '_expert_user_id', (int) $_POST['expert_user_id']);
    update_post_meta($post_id, '_proposed_start', sanitize_text_field($_POST['proposed_start'] ?? ''));
    update_post_meta($post_id, '_duration_mins', (int) $_POST['duration_mins']);
    update_post_meta($post_id, '_capacity', (int) $_POST['capacity']);
    update_post_meta($post_id, '_price', (float) $_POST['price']);
    update_post_meta($post_id, '_briefing_notes', sanitize_textarea_field($_POST['briefing_notes'] ?? ''));
    update_post_meta($post_id, '_briefing_link', esc_url_raw($_POST['briefing_link'] ?? ''));
    if (!get_post_meta($post_id, '_invite_status', true)) update_post_meta($post_id, '_invite_status', 'pending');
}, 10);

/* ----------------------------------------------------
   2) Front-end: Expert Invite List + Actions (shortcode)
-----------------------------------------------------*/
add_shortcode('platform_expert_invites', function ($atts) {
    if (!is_user_logged_in()) return '<p>Please sign in to view your invites.</p>';
    $uid = get_current_user_id();

    // Enqueue scripts
    $ver = defined('WP_DEBUG') && WP_DEBUG ? time() : '1.0.0';
    wp_enqueue_style('platform-invites', plugins_url('../assets/invites.css', __FILE__), [], $ver);
    wp_enqueue_script('platform-invites', plugins_url('../assets/invites.js', __FILE__), [], $ver, true);
    wp_localize_script('platform-invites', 'PlatformInvites', [
        'rest'  => esc_url_raw(rest_url('platform-core/v1/invites')),
        'nonce' => wp_create_nonce('wp_rest')
    ]);

    $q = new WP_Query([
  'post_type'      => 'platform_invite',
  'post_status'    => ['publish','pending','draft','private'],
  'posts_per_page' => 50,
  'orderby'        => 'date',
  'order'          => 'DESC',
  'meta_query'     => [[
      'key'     => '_expert_user_id',
      'value'   => (string) get_current_user_id(),
      'compare' => '=',
      'type'    => 'NUMERIC'
  ]]
]);

    ob_start(); ?>
    <div class="pci-wrap">
      <h3>Your Webinar Invites</h3>
      <?php if (!$q->have_posts()): ?>
        <p>No invites yet.</p>
      <?php else: while ($q->have_posts()): $q->the_post();
        $id   = get_the_ID();
        $status = get_post_meta($id, '_invite_status', true) ?: 'pending';
        $event_id = (int) get_post_meta($id, '_amelia_event_id', true);
        $woo_id   = (int) get_post_meta($id, '_product_id', true);
        $start = esc_html(get_post_meta($id, '_proposed_start', true));
        $dur   = (int) get_post_meta($id, '_duration_mins', true);
        $cap   = (int) get_post_meta($id, '_capacity', true);
        $price = (float) get_post_meta($id, '_price', true);
        $brief = esc_html(get_post_meta($id, '_briefing_notes', true));
        $blink = esc_url(get_post_meta($id, '_briefing_link', true));
      ?>
        <div class="pci-card" data-id="<?php echo $id; ?>">
          <div class="pci-head">
            <strong><?php the_title(); ?></strong>
            <span class="pci-badge pci-<?php echo esc_attr($status); ?>"><?php echo esc_html(ucfirst($status)); ?></span>
          </div>
          <div class="pci-body">
            <p><?php echo wp_kses_post(wpautop(get_the_content())); ?></p>
            <?php if ($brief) echo '<p><em>Briefing:</em> ' . esc_html($brief) . '</p>'; ?>
            <?php if ($blink) echo '<p><a href="'.$blink.'" target="_blank" rel="noopener">Briefing document</a></p>'; ?>

            <dl class="pci-meta">
              <dt>Proposed start</dt><dd><?php echo $start ?: 'ï¿½'; ?></dd>
              <dt>Duration</dt><dd><?php echo $dur; ?> mins</dd>
              <dt>Capacity</dt><dd><?php echo $cap; ?></dd>
              <dt>Price</dt><dd><?php echo wc_price($price); ?></dd>
            </dl>

            <?php if ($status === 'pending'): ?>
              <div class="pci-actions">
                <button class="pci-accept">Accept</button>
                <button class="pci-reject">Reject</button>
              </div>
              <form class="pci-accept-form" hidden>
                <label>Title <input name="title" type="text" value="<?php echo esc_attr(get_the_title()); ?>"></label>
                <label>Start <input name="start" type="datetime-local" value="<?php echo esc_attr($start); ?>"></label>
                <label>Duration (mins) <input name="duration" type="number" min="15" step="15" value="<?php echo $dur; ?>"></label>
                <label>Capacity <input name="capacity" type="number" min="1" value="<?php echo $cap; ?>"></label>
                <label>Ticket price (0 = free) <input name="price" type="number" min="0" step="0.01" value="<?php echo $price; ?>"></label>
                <label>Public description <textarea name="description" rows="4"><?php echo esc_textarea(get_the_excerpt()); ?></textarea></label>
                <button class="pci-send-accept">Create Event</button>
              </form>
              <form class="pci-reject-form" hidden>
                <label>Reason (optional) <input name="reason" type="text"></label>
                <button class="pci-send-reject">Send</button>
              </form>

            <?php elseif ($status === 'accepted_pending_event'): ?>
              <p><strong>Next step:</strong> Create this event in the Amelia Employee Panel, then paste the <em>Event ID</em> below to link tickets to the event.</p>
              <form class="pci-attach-event">
                <label>Amelia Event ID <input name="event_id" type="number" required></label>
                <button class="pci-attach-event-btn">Attach</button>
              </form>

            <?php elseif ($status === 'accepted'): ?>
              <div class="pci-actions">
                <?php if ($event_id): ?>
                  <a class="button" href="<?php echo esc_url(site_url('/webinars')); ?>?ameliaEventId=<?php echo (int)$event_id; ?>" target="_blank" rel="noopener">View event</a>
                <?php endif; ?>
                <?php if ($woo_id): ?>
                  <a class="button" href="<?php echo esc_url(get_permalink($woo_id)); ?>" target="_blank" rel="noopener">Ticket product</a>
                <?php endif; ?>
              </div>
              <details class="pci-resched">
                <summary>Reschedule / Cancel</summary>
                <form class="pci-reschedule-form">
                  <label>New start <input name="start" type="datetime-local"></label>
                  <label>New end <input name="end" type="datetime-local"></label>
                  <button class="pci-send-reschedule">Reschedule</button>
                </form>
                <form class="pci-cancel-form">
                  <label>Reason (optional) <input name="reason" type="text"></label>
                  <button class="pci-send-cancel">Cancel event</button>
                </form>
              </details>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; wp_reset_postdata(); endif; ?>
    </div>
    <?php
    return ob_get_clean();
});

/* ----------------------------------------------------
   3) REST: Accept / Reject / Reschedule / Cancel / Attach Event
-----------------------------------------------------*/
add_action('rest_api_init', function () {
    register_rest_route('platform-core/v1', '/invites/(?P<id>\d+)/accept', [
        'methods' => 'POST',
        'permission_callback' => 'platform_invite_guard',
        'callback' => 'platform_invite_accept'
    ]);
    register_rest_route('platform-core/v1', '/invites/(?P<id>\d+)/reject', [
        'methods' => 'POST',
        'permission_callback' => 'platform_invite_guard',
        'callback' => 'platform_invite_reject'
    ]);
    register_rest_route('platform-core/v1', '/invites/(?P<id>\d+)/reschedule', [
        'methods' => 'POST',
        'permission_callback' => 'platform_invite_guard',
        'callback' => 'platform_invite_reschedule'
    ]);
    register_rest_route('platform-core/v1', '/invites/(?P<id>\d+)/cancel', [
        'methods' => 'POST',
        'permission_callback' => 'platform_invite_guard',
        'callback' => 'platform_invite_cancel'
    ]);
    register_rest_route('platform-core/v1', '/invites/(?P<id>\d+)/attach-event', [
        'methods' => 'POST',
        'permission_callback' => 'platform_invite_guard',
        'callback' => function(\WP_REST_Request $req) {
            $id = (int)$req['id'];
            $eid = (int)$req->get_param('event_id');
            if (!$eid) return new \WP_REST_Response(['error'=>'Event ID required'], 400);
            update_post_meta($id, '_amelia_event_id', $eid);
            // Link Woo product to the event so Flow-2 bridge works
            $pid = (int)get_post_meta($id, '_product_id', true);
            if ($pid) update_post_meta($pid, '_platform_amelia_event_id', $eid);
            update_post_meta($id, '_invite_status', 'accepted');
            return new \WP_REST_Response(['ok'=>true, 'event_id'=>$eid], 200);
        }
    ]);
});

function platform_invite_guard(\WP_REST_Request $req) {
    $id = (int) $req['id'];
    $post = get_post($id);
    if (!$post || $post->post_type !== 'platform_invite') return false;
    $expert = (int) get_post_meta($id, '_expert_user_id', true);
    return current_user_can('manage_options') || (get_current_user_id() === $expert);
}

/* ACCEPT */
function platform_invite_accept(\WP_REST_Request $req) {
    $id = (int) $req['id'];
    $post = get_post($id);
    if (!$post || $post->post_type !== 'platform_invite') {
        return new \WP_REST_Response(['error'=>'Invite not found'], 404);
    }
    $expert_id = (int) get_post_meta($id, '_expert_user_id', true);
    $title = sanitize_text_field($req->get_param('title') ?: $post->post_title);
    $start = sanitize_text_field($req->get_param('start') ?: get_post_meta($id, '_proposed_start', true));
    $duration = max(15, (int) $req->get_param('duration') ?: (int) get_post_meta($id, '_duration_mins', true));
    $end = date('Y-m-d H:i:s', strtotime($start) + ($duration * 60));
    $cap = max(1, (int) $req->get_param('capacity') ?: (int) get_post_meta($id, '_capacity', true));
    $price = (float) ($req->get_param('price') ?? get_post_meta($id, '_price', true));
    $description = wp_kses_post($req->get_param('description') ?: $post->post_excerpt);

    // Always create the Woo product (so tickets can be sold)
    if (!class_exists('WC_Product_Simple')) {
        return new \WP_REST_Response(['error'=>'WooCommerce not active'], 500);
    }
    $product = new \WC_Product_Simple();
    $product->set_name($title . ' ï¿½ Webinar Ticket');
    $product->set_regular_price($price);
    $product->set_price($price);
    $product->set_virtual(true);
    $product->set_catalog_visibility('catalog');
    $product->set_status('publish');
    $product_id = $product->save();
    update_post_meta($id, '_product_id', $product_id);
    update_post_meta($product_id, '_platform_webinar_pattern', $price > 0 ? 'paid' : 'free');

    if (!platform_core_has_amelia_api()) {
        // PRO MODE: we cannot call Amelia API. Ask expert to create the event from the Employee Panel and attach the ID.
        update_post_meta($id, '_invite_status', 'accepted_pending_event');
        // Store the planned details so the expert knows what to create
        update_post_meta($id, '_proposed_start', $start);
        update_post_meta($id, '_duration_mins', $duration);
        update_post_meta($id, '_capacity', $cap);
        update_post_meta($id, '_price', $price);
        update_post_meta($id, '_public_desc', wp_kses_post($description));

        return new \WP_REST_Response([
            'ok' => true,
            'mode' => 'pro',
            'message' => 'Invite accepted. Please create the event in Amelia Employee Panel and attach the Event ID.',
            'product_id' => $product_id
        ], 200);
    }

    // ELITE MODE: create the Amelia event via API (original behavior)
    $employee_id = platform_core_get_employee_id_for_user($expert_id);
    $payload = [
        'name' => $title,
        'periods' => [['periodStart'=> date('Y-m-d H:i:s', strtotime($start)), 'periodEnd'=> $end]],
        'maxCapacity' => $cap,
        'price' => $price,
        'show' => true,
        'bookingOpens' => current_time('mysql'),
        'bookingCloses'=> date('Y-m-d H:i:s', strtotime($start) - 60),
        'description' => $description,
        'providers' => $employee_id ? [['id' => (int) $employee_id]] : [],
        'organizerId'=> $employee_id ?: null
    ];
    $url = platform_core_amelia_api_base('/events');
    $res = wp_remote_post($url, ['headers'=>platform_core_amelia_api_headers(), 'body'=>wp_json_encode($payload), 'timeout'=>25]);
    if (is_wp_error($res)) return new \WP_REST_Response(['error'=>$res->get_error_message()], 500);
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($data['data']['event']['id'])) {
        return new \WP_REST_Response(['error'=>'Could not create event'], 500);
    }
    $eventId = (int) $data['data']['event']['id'];
    update_post_meta($id, '_amelia_event_id', $eventId);
    update_post_meta($product_id, '_platform_amelia_event_id', $eventId);
    update_post_meta($id, '_invite_status', 'accepted');

    return new \WP_REST_Response(['ok'=>true, 'mode'=>'elite', 'event_id'=>$eventId, 'product_id'=>$product_id], 200);
}

/* REJECT */
function platform_invite_reject(\WP_REST_Request $req) {
    $id = (int) $req['id'];
    update_post_meta($id, '_invite_status', 'rejected');
    update_post_meta($id, '_reject_reason', sanitize_text_field($req->get_param('reason')));
    return new \WP_REST_Response(['ok'=>true], 200);
}

/* RESCHEDULE (updates Amelia + attendee Google events) */
function platform_invite_reschedule(\WP_REST_Request $req) {
    global $wpdb;
    $id = (int) $req['id'];
    $eventId = (int) get_post_meta($id, '_amelia_event_id', true);
    if (!$eventId) return new \WP_REST_Response(['error'=>'No event attached'], 400);

    $start = sanitize_text_field($req->get_param('start'));
    $end   = sanitize_text_field($req->get_param('end'));
    if (!$start || !$end) return new \WP_REST_Response(['error'=>'Start and End required'], 400);

    if (platform_core_has_amelia_api()) {
        // ELITE MODE: Update Amelia Event periods and notify participants
        $url = platform_core_amelia_api_base('/events/' . $eventId);
        $payload = [
            'periods' => [['periodStart'=> date('Y-m-d H:i:s', strtotime($start)), 'periodEnd'=> date('Y-m-d H:i:s', strtotime($end))]],
            'notifyParticipants' => 1
        ];
        $res = wp_remote_post($url, [
            'headers' => platform_core_amelia_api_headers(),
            'body'    => wp_json_encode($payload),
            'timeout' => 25
        ]);
        if (is_wp_error($res)) return new \WP_REST_Response(['error'=>$res->get_error_message()], 500);
    }

    // Update Google Calendar rows for this event (created in Flow-2) - works in both Pro and Elite modes
    $table = $wpdb->prefix . 'platform_calendar_map';
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE source=%s AND source_ref=%s",
        'webinar', 'amelia:event:'.$eventId
    ), ARRAY_A);
    if ($rows) {
        foreach ($rows as $row) {
            if (!empty($row['event_id'])) {
                platform_core_google_update_event_id(
                    $row['calendar_id'] ?: get_option('platform_google_calendar_id',''),
                    $row['event_id'],
                    $start,
                    $end
                );
                $wpdb->update($table, [
                    'starts_at'=>gmdate('Y-m-d H:i:s', strtotime($start.' UTC')),
                    'ends_at'=>gmdate('Y-m-d H:i:s', strtotime($end.' UTC')),
                    'updated_at'=>current_time('mysql')
                ], ['id'=>$row['id']]);
            }
        }
    }
    return new \WP_REST_Response(['ok'=>true, 'mode'=> platform_core_has_amelia_api() ? 'elite' : 'pro'], 200);
}

/* CANCEL */
function platform_invite_cancel(\WP_REST_Request $req) {
    global $wpdb;
    $id      = (int) $req['id'];
    $eventId = (int) get_post_meta($id, '_amelia_event_id', true);
    
    if (platform_core_has_amelia_api() && $eventId) {
        // ELITE MODE: Delete/cancel in Amelia
        $url = platform_core_amelia_api_base('/events/delete/'.$eventId);
        wp_remote_post($url, ['headers'=>platform_core_amelia_api_headers(), 'timeout'=>20, 'body'=>wp_json_encode(['applyGlobally'=>false])]);
    }
    
    update_post_meta($id, '_invite_status', 'cancelled');
    update_post_meta($id, '_cancel_reason', sanitize_text_field($req->get_param('reason')));

    // Cancel Google calendar events - works in both Pro and Elite modes
    $table = $wpdb->prefix . 'platform_calendar_map';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE source=%s AND source_ref=%s", 'webinar', 'amelia:event:'.$eventId), ARRAY_A);
    if ($rows) {
        foreach ($rows as $row) {
            if (!empty($row['event_id'])) {
                platform_core_google_delete_event_id($row['calendar_id'] ?: get_option('platform_google_calendar_id',''), $row['event_id']);
                $wpdb->update($table, ['status'=>'cancelled','updated_at'=>current_time('mysql')], ['id'=>$row['id']]);
            }
        }
    }
    return new \WP_REST_Response(['ok'=>true, 'mode'=> platform_core_has_amelia_api() ? 'elite' : 'pro'], 200);
}

/* ----------------------------------------------------
   4) Google Calendar PATCH/DELETE (Flow-2 compatible)
-----------------------------------------------------*/
if (!function_exists('platform_core_google_token')) {
    function platform_core_google_token() {
        $client  = get_option('platform_google_client_id', '');
        $secret  = get_option('platform_google_client_secret', '');
        $refresh = get_option('platform_google_refresh_token', '');
        if (!$client || !$secret || !$refresh) return false;
        $r = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $client,
                'client_secret' => $secret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh
            ],
            'timeout' => 20
        ]);
        if (is_wp_error($r)) return false;
        $tok = json_decode(wp_remote_retrieve_body($r), true);
        return $tok['access_token'] ?? false;
    }
}

function platform_core_google_update_event_id($calendarId, $eventId, $start, $end) {
    $access = platform_core_google_token();
    if (!$access || !$calendarId || !$eventId) return false;
    $payload = [
        'start' => ['dateTime'=>gmdate('c', strtotime($start)), 'timeZone'=>wp_timezone_string()],
        'end'   => ['dateTime'=>gmdate('c', strtotime($end)),   'timeZone'=>wp_timezone_string()]
    ];
    $url = 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId).'?sendUpdates=all';
    $res = wp_remote_request($url, [
        'method'  => 'PATCH',
        'headers' => ['Authorization'=>'Bearer '.$access, 'Content-Type'=>'application/json'],
        'body'    => wp_json_encode($payload),
        'timeout' => 20
    ]);
    return !is_wp_error($res);
}

function platform_core_google_delete_event_id($calendarId, $eventId) {
    $access = platform_core_google_token();
    if (!$access || !$calendarId || !$eventId) return false;
    $url = 'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId).'?sendUpdates=all';
    $res = wp_remote_request($url, [
        'method'  => 'DELETE',
        'headers' => ['Authorization'=>'Bearer '.$access],
        'timeout' => 20
    ]);
    return !is_wp_error($res);
}