<?php
/**
 * Flow 9 — College students get recap
 * - Capture invitees into wp_platform_invitees (bulk add + token creation)
 * - Tokenized recap page /class/{request_id}/recap?token=...
 * - Post-class mailer sends recording/material links (and logs opens)
 *
 * Shortcodes:
 *   [platform_college_manage_invitees]  — College admin UI to paste CSV/list, send recap
 *   [platform_class_recap]              — Recap page renderer (on /class)
 *
 * Events/hooks:
 *   do_action('platform_core_class_session_saved', $request_id, $session_id)  // fired by Flow 8 (tiny patch)
 */

if (!defined('ABSPATH')) exit;

class PlatformCore_Flow9_CollegeRecap {

    // We reuse the existing college settings bucket in Flow 7/8
    const OPTS_KEY               = 'platform_core_college_settings';
    const OPT_RECAP_TTL_DAYS     = 'recap_token_ttl_days';
    const DEFAULT_TTL_DAYS       = 60;

    private $tbl_requests;
    private $tbl_invitees;
    private $tbl_calendar_map;

    public function __construct() {
        global $wpdb;
        $this->tbl_requests     = $wpdb->prefix . 'platform_requests';
        $this->tbl_invitees     = $wpdb->prefix . 'platform_invitees';
        $this->tbl_calendar_map = $wpdb->prefix . 'platform_calendar_map';

        // Hardening: make sure columns we use exist (no-op if already present)
        add_action('plugins_loaded', [$this, 'maybe_add_columns']);

        // Shortcodes
        add_shortcode('platform_college_manage_invitees', [$this, 'sc_manage_invitees']);
        add_shortcode('platform_class_recap',             [$this, 'sc_class_recap']);

        // REST (optional programmatic bulk add)
        add_action('rest_api_init', [$this, 'register_routes']);

        // Rewrite + query vars for /class/{rid}/recap
        add_action('init',        [$this, 'add_rewrite']);
        add_filter('query_vars',  [$this, 'add_query_vars']);
        add_action('wp_head',     [$this, 'add_noindex_on_recap']);

        // When Flow 8 posts materials, auto-send recap mails
        add_action('platform_core_class_session_saved', [$this, 'on_class_session_saved'], 10, 2);
    }

    /* -------------------------------
     * DB columns (non-destructive add)
     * ----------------------------- */
    public function maybe_add_columns() {
        global $wpdb;

        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$this->tbl_invitees}", 0);
        $need = [
            'id' ,'request_id', 'email', 'name', 'token_hash', 'token_expires',
            'mailed_at', 'opened_at', 'open_count', 'last_open_ip', 'last_open_ua',
            'created_at', 'updated_at'
        ];
        foreach ($need as $c) {
            if (!in_array($c, $cols, true)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query("ALTER TABLE {$this->tbl_invitees} ADD COLUMN {$c} " . $this->column_ddl($c));
            }
        }
    }

    private function column_ddl($c) {
        switch ($c) {
            case 'id': return "BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
            case 'request_id': return "BIGINT UNSIGNED NOT NULL DEFAULT 0";
            case 'email': return "VARCHAR(190) NOT NULL DEFAULT ''";
            case 'name': return "VARCHAR(190) NOT NULL DEFAULT ''";
            case 'token_hash': return "VARCHAR(190) NOT NULL DEFAULT ''";
            case 'token_expires': return "DATETIME NULL";
            case 'mailed_at':
            case 'opened_at': return "DATETIME NULL";
            case 'open_count': return "INT UNSIGNED NOT NULL DEFAULT 0";
            case 'last_open_ip': return "VARCHAR(64) NULL";
            case 'last_open_ua': return "VARCHAR(255) NULL";
            case 'created_at':
            case 'updated_at': return "DATETIME NULL";
        }
        return "TEXT NULL";
    }

    /* -------------------------------
     * Rewrite: /class/{rid}/recap?token=...
     * ----------------------------- */
    public function add_rewrite() {
        add_rewrite_rule('^class/([0-9]+)/recap/?$', 'index.php?pagename=class&rid=$matches[1]', 'top');
    }
    public function add_query_vars($vars) {
        $vars[] = 'rid';
        return $vars;
    }
    public function add_noindex_on_recap() {
        // Add <meta name="robots" content="noindex"> per cutover C3 for token pages
        if (is_page('class') && get_query_var('rid') && isset($_GET['token'])) {
            echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
        }
    }

    private function get_opt($key, $default = '') {
        $opts = get_option(self::OPTS_KEY, []);
        return isset($opts[$key]) ? $opts[$key] : $default;
    }

    /* -------------------------------
     * Shortcode: College admin — manage invitees
     * ----------------------------- */
    public function sc_manage_invitees($atts = []) {
        if (!is_user_logged_in() || !current_user_can('college_admin')) {
            return '<div class="notice notice-error">You need a College Admin account.</div>';
        }

        $rid = isset($_GET['rid']) ? absint($_GET['rid']) : 0;
        if (!$rid) return '<div class="notice notice-warning"><p>Missing request id (?rid=).</p></div>';

        // Handle POSTs
        $notice = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_pc_nonce']) && wp_verify_nonce($_POST['_pc_nonce'], 'pc_invitees_'.$rid)) {
            $action = sanitize_text_field($_POST['pc_action'] ?? '');
            if ($action === 'bulk_add')    $notice = $this->handle_bulk_add($rid);
            if ($action === 'send_recap')  $notice = $this->handle_send_recap($rid, 'all');
            if ($action === 'resend_unopened') $notice = $this->handle_send_recap($rid, 'unopened');
        }

        global $wpdb;
        $cl = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_requests} WHERE id=%d", $rid));
        if (!$cl) return '<div class="notice notice-error"><p>Class request not found.</p></div>';

        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tbl_invitees} WHERE request_id=%d ORDER BY id DESC", $rid));

        ob_start(); ?>
        <h3>Invitees for Class #<?php echo (int)$rid; ?> — “<?php echo esc_html($cl->topic); ?>”</h3>
        <?php if ($notice) echo $notice; ?>

        <form method="post">
            <?php wp_nonce_field('pc_invitees_'.$rid, '_pc_nonce'); ?>
            <input type="hidden" name="pc_action" value="bulk_add" />
            <p><label>Paste invitees (one per line). Formats accepted:
                <code>email@example.com</code> or <code>Full Name, email@example.com</code>
                <br/><textarea name="pc_invitees" rows="6" class="large-text" placeholder="Jane Doe, jane@college.edu&#10;john@college.edu"></textarea>
            </label></p>
            <p>
                Token TTL (days): <input type="number" name="pc_ttl" min="1" step="1"
                value="<?php echo esc_attr( (int)$this->get_opt(self::OPT_RECAP_TTL_DAYS, self::DEFAULT_TTL_DAYS) ); ?>" />
                <button class="button">Add / Update invitees</button>
            </p>
        </form>

        <form method="post" style="margin-top:12px;">
            <?php wp_nonce_field('pc_invitees_'.$rid, '_pc_nonce'); ?>
            <input type="hidden" name="pc_action" value="send_recap" />
            <button class="button button-primary">Send recap to all</button>
            <button class="button" name="pc_action" value="resend_unopened">Resend to unopened</button>
        </form>

        <h4 style="margin-top:18px;">Current list</h4>
        <table class="widefat striped">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Mailed</th><th>Opened</th><th>Opens</th><th>Token expires</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7">No invitees yet.</td></tr>
            <?php else:
                foreach ($rows as $r): ?>
                <tr>
                    <td>#<?php echo (int)$r->id; ?></td>
                    <td><?php echo esc_html($r->name); ?></td>
                    <td><?php echo esc_html($r->email); ?></td>
                    <td><?php echo $r->mailed_at ? esc_html($r->mailed_at) : '—'; ?></td>
                    <td><?php echo $r->opened_at ? esc_html($r->opened_at) : '—'; ?></td>
                    <td><?php echo (int)$r->open_count; ?></td>
                    <td><?php echo $r->token_expires ? esc_html($r->token_expires) : '—'; ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    private function handle_bulk_add($rid) {
        global $wpdb;
        $txt = trim(str_replace(["\r\n", "\r"], "\n", wp_unslash($_POST['pc_invitees'] ?? '')));
        $ttl = max(1, (int)($_POST['pc_ttl'] ?? self::DEFAULT_TTL_DAYS));
        if (!$txt) return '<div class="notice notice-warning"><p>Nothing to add.</p></div>';

        $lines = array_filter(array_map('trim', explode("\n", $txt)));
        $added = 0; $updated = 0;
        foreach ($lines as $line) {
            $name = ''; $email = '';
            if (strpos($line, ',') !== false) {
                [$name, $email] = array_map('trim', explode(',', $line, 2));
            } else {
                $email = trim($line);
            }
            if (!is_email($email)) continue;

            $token  = $this->gen_token();
            $hash   = $this->hash_token($token);
            $exp    = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS * $ttl);

            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->tbl_invitees} WHERE request_id=%d AND email=%s", $rid, $email));
            if ($id) {
                $wpdb->update($this->tbl_invitees, [
                    'name'          => $name,
                    'token_hash'    => $hash,
                    'token_expires' => $exp,
                    'updated_at'    => current_time('mysql')
                ], ['id' => $id]);
                $updated++;
            } else {
                $wpdb->insert($this->tbl_invitees, [
                    'request_id'    => $rid,
                    'name'          => $name,
                    'email'         => $email,
                    'token_hash'    => $hash,
                    'token_expires' => $exp,
                    'created_at'    => current_time('mysql')
                ]);
                $added++;
            }
            // Store the raw token transiently so we can show it once if needed (not required for production UX)
        }

        return '<div class="notice notice-success"><p>Invitees added: '.$added.'; updated: '.$updated.'.</p></div>';
    }

    /* -------------------------------
     * Shortcode: tokenized recap page (/class/{rid}/recap?token=...)
     * ----------------------------- */
    public function sc_class_recap() {
        $rid   = absint(get_query_var('rid'));
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        if (!$rid || !$token) {
            return '<div class="notice notice-error"><p>Missing or invalid recap link.</p></div>';
        }
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tbl_invitees} WHERE request_id=%d AND token_hash=%s",
            $rid, $this->hash_token($token)
        ));
        if (!$row) {
            return '<div class="notice notice-error"><p>Invalid token.</p></div>';
        }
        if ($row->token_expires && strtotime($row->token_expires) < time()) {
            return '<div class="notice notice-error"><p>This recap link has expired.</p></div>';
        }

        // Log access
        $wpdb->update($this->tbl_invitees, [
            'opened_at'    => $row->opened_at ?: current_time('mysql'),
            'open_count'   => (int)$row->open_count + 1,
            'last_open_ip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'last_open_ua' => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'updated_at'   => current_time('mysql')
        ], ['id' => $row->id]);

        // Load class session (from Flow 8) and attachments
        $session = $this->find_class_session_for_request($rid);
        if (!$session) {
            return '<div class="notice notice-warning"><p>Materials are not yet available. Please check back later.</p></div>';
        }

        $materials = (array) get_post_meta($session->ID, '_pc_material_ids', true);
        $recording = trim((string) get_post_meta($session->ID, '_pc_recording_url', true)); // optional
        $notes     = wpautop(esc_html(get_post_field('post_content', $session->ID)));

        ob_start(); ?>
        <div class="pc-recap">
            <h3>Class Recap</h3>
            <p><strong>Topic:</strong> <?php echo esc_html(get_the_title($session->ID)); ?></p>
            <?php if ($recording): ?>
                <p><strong>Recording:</strong> <a href="<?php echo esc_url($recording); ?>" target="_blank" rel="noopener">Watch recording</a></p>
            <?php endif; ?>
            <?php if ($materials): ?>
                <h4>Materials</h4>
                <ul>
                <?php foreach ($materials as $aid):
                    $url = wp_get_attachment_url($aid);
                    $title = get_the_title($aid);
                    if ($url): ?>
                        <li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($title ?: basename($url)); ?></a></li>
                    <?php endif;
                endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($notes): ?>
                <h4>Notes</h4>
                <div class="pc-recap-notes"><?php echo $notes; ?></div>
            <?php endif; ?>
            <?php if (!$materials && !$recording && !$notes): ?>
                <p>No materials have been posted yet.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function find_class_session_for_request($rid) {
        $q = new WP_Query([
            'post_type'      => 'class_session',
            'posts_per_page' => 1,
            'meta_key'       => '_pc_request_id',
            'meta_value'     => (int)$rid,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'no_found_rows'  => true
        ]);
        return $q->have_posts() ? $q->posts[0] : null;
    }

    /* -------------------------------
     * Send recap emails (all / unopened)
     * ----------------------------- */
    private function handle_send_recap($rid, $mode = 'all') {
        $sent = $this->send_recap_emails($rid, $mode);
        return '<div class="notice notice-success"><p>Recap emails queued: ' . (int)$sent . '.</p></div>';
    }

    public function on_class_session_saved($rid, $session_id) {
        // Auto-send recap to all invitees when materials posted
        $this->send_recap_emails($rid, 'all');
    }

    private function send_recap_emails($rid, $mode = 'all') {
        global $wpdb;

        $class = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->tbl_requests} WHERE id=%d", $rid));
        if (!$class) return 0;

        $session = $this->find_class_session_for_request($rid);
        if (!$session) return 0;

        $subject = sprintf('[%s] Recap: %s', get_bloginfo('name'), $class->topic);

        $where = "request_id=%d";
        $args  = [$rid];
        if ($mode === 'unopened') {
            $where .= " AND (mailed_at IS NOT NULL AND (opened_at IS NULL OR open_count = 0))";
        }

        $list = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->tbl_invitees} WHERE {$where}", $args));
        $count = 0;

        foreach ($list as $row) {
            // Reissue token if expired or missing
            if (!$row->token_hash || ($row->token_expires && strtotime($row->token_expires) < time())) {
                $token = $this->gen_token();
                $hash  = $this->hash_token($token);
                $exp   = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS * (int)$this->get_opt(self::OPT_RECAP_TTL_DAYS, self::DEFAULT_TTL_DAYS));
                $wpdb->update($this->tbl_invitees, [
                    'token_hash'    => $hash,
                    'token_expires' => $exp,
                    'updated_at'    => current_time('mysql')
                ], ['id' => $row->id]);
            }

            // Fetch the latest hashed token back (we can't email the hash; we need a raw token ? regenerate one time)
            $token = $this->gen_token();
            $wpdb->update($this->tbl_invitees, [
                'token_hash'  => $this->hash_token($token),
                'updated_at'  => current_time('mysql')
            ], ['id' => $row->id]);

            $link = add_query_arg([
                'token' => rawurlencode($token),
            ], site_url('/class/' . (int)$rid . '/recap'));

            $body  = "Hello";
            $body .= $row->name ? " {$row->name}" : "";
            $body .= ",\n\nThe recap for your recent class is now available:\n";
            $body .= "Topic: {$class->topic}\n";
            $body .= "Recap link: {$link}\n\n";
            $body .= "This link is private to you. Please do not share it widely.\n\n";
            $body .= "Regards,\n" . get_bloginfo('name');

            // Send via wp_mail (WP Mail SMTP?SendGrid from Foundation F3)
            $ok = wp_mail($row->email, $subject, $body);
            if ($ok) {
                $wpdb->update($this->tbl_invitees, [
                    'mailed_at'  => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ], ['id' => $row->id]);
                $count++;
            }
        }

        return $count;
    }

    /* -------------------------------
     * REST (optional): programmatic bulk add
     * ----------------------------- */
    public function register_routes() {
        register_rest_route('platform-core/v1', '/college/invitees/bulk', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_bulk'],
            'permission_callback' => function () { return current_user_can('college_admin'); }
        ]);
    }
    public function api_bulk(WP_REST_Request $req) {
        $rid = absint($req['request_id']);
        $txt = trim((string)$req['body']);
        if (!$rid || !$txt) return new WP_REST_Response(['error'=>'bad_request'], 400);
        $_POST['_pc_nonce'] = wp_create_nonce('pc_invitees_'.$rid);
        $_POST['pc_invitees']= $txt;
        $_POST['pc_ttl']     = (int)$this->get_opt(self::OPT_RECAP_TTL_DAYS, self::DEFAULT_TTL_DAYS);
        return ['html' => $this->handle_bulk_add($rid)];
    }

    /* -------------------------------
     * Token helpers
     * ----------------------------- */
    private function gen_token() {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }
    private function hash_token($token) {
        return hash_hmac('sha256', $token, wp_salt('auth'));
    }
}

new PlatformCore_Flow9_CollegeRecap();
