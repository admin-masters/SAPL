<?php
/**
 * Seed Saved Replies for Fluent Support (runs once)
 */
if (!defined('ABSPATH')) exit;

// Use a later hook to ensure Fluent Support is fully loaded
add_action('plugins_loaded', 'pcore_seed_support_replies', 999);

function pcore_seed_support_replies() {
    // Only run once
    if (get_option('platform_core_saved_replies_seeded')) {
        return;
    }
    
    // Verify Fluent Support is active
    if (!function_exists('FluentSupportApi')) {
        return;
    }
    
    // Check if table exists
    global $wpdb;
    $table = $wpdb->prefix . 'fs_saved_replies';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return; // Table doesn't exist yet, Fluent Support may not be fully activated
    }
    
    $saved_replies = [
        ['Login/Password reset', 'Please reset your password from /my-account using "Lost your password?". If you see "link expired", log out and retry in a private/incognito window. Reply with browser+device if it still fails.'],
        ['Webinar join link', 'Your webinar Join link is in /my-events and in your email confirmation. The .ics calendar also includes it. If the schedule changes, /my-events updates automatically.'],
        ['Webinar recording availability', 'Recordings (when allowed) appear in Webinar Archives within 48–72h. We\'ll notify you once published.'],
        ['How to book a tutorial', 'Pick a slot on the expert calendar, complete checkout, and manage from /my-tutorials. You will receive an email + .ics file.'],
        ['Reschedule/cancellation (tutorials)', 'You can reschedule from /my-tutorials. If an expert cancels you get a full refund. Learner-initiated cancellations follow the policy shown at checkout.'],
        ['College class access', 'College sessions are restricted to invited student lists. Please confirm you\'re listed with your coordinator; the access link/time comes from them. Missed it? We\'ll send the recap if enabled.'],
        ['Payments & invoices', 'Invoices/receipts live under /my-account ? Orders. If a payment failed, retry once. For duplicate charges, reply and we\'ll verify/refund.'],
        ['Certificates', 'Certificates generate on course completion and are emailed to you. If missing, confirm the course shows as Completed and reply here.'],
        ['Recorded content access', 'If access is missing, your subscription may have expired or the item is excluded. Renew or purchase individually, then refresh your page.'],
        ['Data deletion/privacy', 'We can delete/export your data upon request. Please confirm from the account email address and we\'ll proceed.']
    ];
    
    foreach ($saved_replies as $row) {
        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE title = %s LIMIT 1",
            $row[0]
        ));
        
        if (!$exists) {
            $wpdb->insert($table, [
                'title'      => $row[0],
                'content'    => wp_kses_post($row[1]),
                'product_id' => null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ], ['%s', '%s', '%d', '%s', '%s']);
        }
    }
    
    // Mark as seeded
    update_option('platform_core_saved_replies_seeded', 1, false);
}