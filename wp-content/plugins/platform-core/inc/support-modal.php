<?php
/**
 * Support modal + ticket escalation for Fluent Support
 * Requires: Fluent Support (core), WP Mail SMTP configured to SendGrid (F3)
 */
if (!defined('ABSPATH')) exit;

define('PLATFORM_SUPPORT_EMAIL', 'support@yourdomain.tld');     // change
define('PLATFORM_SUPPORT_MAILBOX_ID', 2);                        // change if your Support inbox has a different ID

add_action('wp_enqueue_scripts', function () {
    // Only on front-end for logged-in and logged-out users
    if (is_admin()) return;
    $ver = defined('WP_DEBUG') && WP_DEBUG ? time() : '1.0.0';
    wp_enqueue_style('platform-support-css', plugins_url('../assets/support-modal.css', __FILE__), [], $ver);
    wp_enqueue_script('platform-support-js', plugins_url('../assets/support-modal.js', __FILE__), ['jquery'], $ver, true);
    wp_localize_script('platform-support-js', 'PlatformSupport', [
        'restUrl'    => esc_url_raw(rest_url('platform-core/v1/support/ticket')),
        'nonce'      => wp_create_nonce('wp_rest'),
        'isLoggedIn' => is_user_logged_in(),
        'currentUser'=> is_user_logged_in() ? wp_get_current_user()->user_email : '',
        'site'       => get_bloginfo('name'),
        'supportEmail'=> 'admin@inditech.co.in'
    ]);
});

// Output the floating button + modal markup in footer
add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
    <div id="platform-support-launcher" aria-live="polite" role="dialog" aria-label="Support">
        <button class="psp-fab" id="psp-open">Support</button>
        <div class="psp-modal" id="psp-modal" aria-hidden="true">
            <div class="psp-modal__window" role="document">
                <button class="psp-close" id="psp-close" aria-label="Close">×</button>
                <div class="psp-steps" id="psp-steps">
                    <!-- Step 1: choose topic -->
                    <section class="psp-step" data-step="1" aria-labelledby="psp-step1-title">
                        <h3 id="psp-step1-title">How can we help?</h3>
                        <p>Select a topic below. We’ll suggest fixes and you can escalate to Support anytime.</p>
                        <div class="psp-topics">
                            <button data-topic="login">Login/technical issue</button>
                            <button data-topic="payments">Billing & payments</button>
                            <button data-topic="webinars">Webinars & /my-events</button>
                            <button data-topic="tutorials">Tutorial bookings</button>
                            <button data-topic="college">College classes</button>
                            <button data-topic="courses">Courses/certificates</button>
                            <button data-topic="other">Other</button>
                        </div>
                    </section>
                    <!-- Step 2: guidance + escalate CTA -->
                    <section class="psp-step" data-step="2" aria-labelledby="psp-step2-title" hidden>
                        <h3 id="psp-step2-title">Try this first</h3>
                        <div id="psp-suggestions"></div>
                        <button class="psp-primary" id="psp-escalate">Still need help? Contact Support</button>
                        <button class="psp-secondary" id="psp-back">Back</button>
                    </section>
                    <!-- Step 3: contact form + transcript -->
                    <section class="psp-step" data-step="3" aria-labelledby="psp-step3-title" hidden>
                        <h3 id="psp-step3-title">Contact Support</h3>
                        <form id="psp-form">
                            <label>Email <input required type="email" name="email" id="psp-email"></label>
                            <label>Subject <input required type="text" name="subject" id="psp-subject"></label>
                            <label>Details <textarea required name="message" id="psp-message" rows="6" placeholder="Tell us what’s happening..."></textarea></label>
                            <input type="file" name="attachment" id="psp-attachment" accept="image/*,.pdf,.txt,.csv">
                            <input type="hidden" name="topic" id="psp-topic">
                            <input type="hidden" name="transcript" id="psp-transcript"> <!-- filled by JS -->
                            <button class="psp-primary" type="submit">Send</button>
                            <div class="psp-status" id="psp-status" role="status" aria-live="polite"></div>
                        </form>
                    </section>
                    <!-- Step 4: success -->
                    <section class="psp-step" data-step="4" aria-labelledby="psp-step4-title" hidden>
                        <h3 id="psp-step4-title">We’ve created your ticket ??</h3>
                        <p>We emailed a confirmation. You can also track it under <a href="<?php echo esc_url(site_url('/my-support')); ?>">/my-support</a>.</p>
                        <button class="psp-secondary" id="psp-done">Close</button>
                    </section>
                </div>
            </div>
        </div>
    </div>
    <?php
});

// Secure REST route to create the ticket + upload optional attachment
add_action('rest_api_init', function () {
    register_rest_route('platform-core/v1', '/support/ticket', [
        'methods'  => 'POST',
        'permission_callback' => function ($req) {
            return wp_verify_nonce($req->get_header('X-WP-Nonce'), 'wp_rest');
        },
        'args' => [
            'email'     => ['required' => true, 'type' => 'string', 'validate_callback' => 'is_email'],
            'subject'   => ['required' => true, 'type' => 'string'],
            'message'   => ['required' => true, 'type' => 'string'],
            'topic'     => ['required' => false, 'type' => 'string'],
            'transcript'=> ['required' => false, 'type' => 'string'],
        ],
        'callback' => function (\WP_REST_Request $req) {
            if (!function_exists('FluentSupportApi')) {
                return new \WP_REST_Response(['error' => 'Fluent Support not available'], 500);
            }

            $email      = sanitize_email($req['email']);
            $subject    = sanitize_text_field($req['subject']);
            $message    = wp_kses_post($req['message']);
            $topic      = sanitize_text_field($req['topic']);
            $transcript = sanitize_textarea_field($req['transcript']);

            // 1) Ensure customer exists (create if needed, without WP user)
            $customersApi = FluentSupportApi('customers');
            $customer     = $customersApi->getInstance()::where('email', $email)->first();
            if (!$customer) {
                $customer = $customersApi->createCustomerWithOrWithoutWpUser([
                    'first_name' => '',
                    'last_name'  => '',
                    'email'      => $email
                ], false);
            }

            // 2) Build ticket content with transcript + environment meta
            $meta = sprintf(
                "Topic: %s\nSite: %s\nURL: %s\nUser Logged In: %s\nUA: %s\nTime: %s",
                $topic ?: 'n/a',
                get_bloginfo('name'),
                esc_url_raw($_SERVER['HTTP_REFERER'] ?? home_url('/')),
                is_user_logged_in() ? 'yes' : 'no',
                sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
                current_time('mysql')
            );
            $content  = '<p>' . nl2br(wp_kses_post($message)) . "</p>\n";
            if (!empty($transcript)) {
                $content .= "<h4>Guided Help Transcript</h4>\n<pre>" . esc_html($transcript) . "</pre>\n";
            }
            $content .= "<hr/><pre>" . esc_html($meta) . '</pre>';

            // 3) Create the ticket (Web-based mailbox)
            $ticketApi = FluentSupportApi('tickets');
            $ticket    = $ticketApi->createTicket([
                'customer_id'      => is_object($customer) ? $customer->id : (int)$customer['id'],
                'mailbox_id'       => (int) PLATFORM_SUPPORT_MAILBOX_ID,
                'title'            => $subject,
                'content'          => $content,
                'client_priority'  => 'normal',
                'create_customer'  => 'no',
                'create_wp_user'   => 'no',
                'source'           => 'web'
            ]);

            if (!$ticket || (is_array($ticket) && !empty($ticket['error']))) {
                return new \WP_REST_Response(['error' => 'Could not create ticket'], 500);
            }

            // 4) Optional: add a tag matching topic
            if ($topic) {
                try {
                    $tagApi = FluentSupportApi('tags');
                    $tag    = $tagApi->getInstance()::firstOrCreate(['title' => sanitize_text_field(ucfirst($topic))]);
                    $ticketApi->getInstance()::find($ticket->id)->tags()->syncWithoutDetaching([$tag->id]);
                } catch (\Throwable $e) {}
            }

            // 5) Optional: handle uploaded file (attach to ticket by creating a response with media)
            if (!empty($_FILES['attachment']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $uploaded = wp_handle_upload($_FILES['attachment'], ['test_form' => false]);
                if (empty($uploaded['error'])) {
                    $attachment_id = wp_insert_attachment([
                        'post_mime_type' => $uploaded['type'],
                        'post_title'     => sanitize_file_name($_FILES['attachment']['name']),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    ], $uploaded['file']);
                    // add a response with a link to the file
                    $ticketApi->addResponse([
                        'content' => sprintf('Attachment uploaded: <a href="%s">%s</a>', esc_url(wp_get_attachment_url($attachment_id)), esc_html($_FILES['attachment']['name']))
                    ], 0, $ticket->id);
                }
            }

            // 6) Return success (FS will email agent + customer per notifications configured)
            return new \WP_REST_Response(['ok' => true, 'ticket_id' => $ticket->id], 200);
        }
    ]);
});

// Create /my-support page automatically if missing
add_action('admin_init', function () {
    $slug = 'my-support';
    if (!get_page_by_path($slug)) {
        $page_id = wp_insert_post([
            'post_type'   => 'page',
            'post_name'   => $slug,
            'post_title'  => 'My Support',
            'post_status' => 'publish',
            'post_content'=> '[fluent_support_portal show_logout="yes"]'
        ]);
        if (!is_wp_error($page_id)) {
            update_option('platform_core_support_portal_page', (int) $page_id);
        }
    }
});

// Optional: hook after ticket created for logging or analytics
add_action('fluent_support/ticket_created', function ($ticket, $customer) {
    // Example: write to the debug log
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('[Support] Ticket %d created for %s', $ticket->id, $customer->email));
    }
}, 10, 2);

// Seed Saved Replies (runs once)
add_action('admin_init', function () {
    if (get_option('platform_core_saved_replies_seeded')) return;
    if (!function_exists('FluentSupportApi')) return;

    $saved = [
        ['Login/Password reset', "Please reset your password from /my-account using “Lost your password?”. If you see “link expired”, log out and retry in a private/incognito window. Reply with browser+device if it still fails."],
        ['Webinar join link', "Your webinar Join link is in /my-events and in your email confirmation. The .ics calendar also includes it. If the schedule changes, /my-events updates automatically."],
        ['Webinar recording availability', "Recordings (when allowed) appear in Webinar Archives within 48–72h. We’ll notify you once published."],
        ['How to book a tutorial', "Pick a slot on the expert calendar, complete checkout, and manage from /my-tutorials. You will receive an email + .ics file."],
        ['Reschedule/cancellation (tutorials)', "You can reschedule from /my-tutorials. If an expert cancels you get a full refund. Learner-initiated cancellations follow the policy shown at checkout."],
        ['College class access', "College sessions are restricted to invited student lists. Please confirm you’re listed with your coordinator; the access link/time comes from them. Missed it? We’ll send the recap if enabled."],
        ['Payments & invoices', "Invoices/receipts live under /my-account ? Orders. If a payment failed, retry once. For duplicate charges, reply and we’ll verify/refund."],
        ['Certificates', "Certificates generate on course completion and are emailed to you. If missing, confirm the course shows as Completed and reply here."],
        ['Recorded content access', "If access is missing, your subscription may have expired or the item is excluded. Renew or purchase individually, then refresh your page."],
        ['Data deletion/privacy', "We can delete/export your data upon request. Please confirm from the account email address and we’ll proceed."]
    ];

    try {
        $api = FluentSupportApi('tickets'); // get container; Saved Replies live under REST but we can use model directly
        // Fallback to REST if needed:
        $rest_insert = function($title, $content) {
            // Use WP internal REST with application passwords if desired; here we rely on core model if available.
            // If the SavedReply model is not exposed, skip silently and seed manually from UI.
        };

        // Try model class if present
        if (class_exists('\FluentSupport\App\Models\SavedReply')) {
            foreach ($saved as $row) {
                $exists = \FluentSupport\App\Models\SavedReply::where('title', $row[0])->first();
                if (!$exists) {
                    \FluentSupport\App\Models\SavedReply::create([
                        'title'   => $row[0],
                        'content' => wp_kses_post($row[1]),
                        'product_id' => null
                    ]);
                }
            }
        } else {
            foreach ($saved as $row) { $rest_insert($row[0], $row[1]); }
        }
        update_option('platform_core_saved_replies_seeded', 1);
    } catch (\Throwable $e) {
        // Non-fatal; you can always create from the UI (Fluent Support ? Saved Replies)
    }
});
