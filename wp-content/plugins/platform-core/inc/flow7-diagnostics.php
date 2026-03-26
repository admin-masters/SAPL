<?php
/**
 * Add this temporarily to diagnose issues
 * Add shortcode: [platform_flow7_diagnostic]
 */

add_shortcode('platform_flow7_diagnostic', function() {
    if (!current_user_can('manage_options')) {
        return '<p>Admin only</p>';
    }

    global $wpdb;
    
    ob_start();
    ?>
    <div style="background: #f5f5f5; padding: 20px; border-radius: 5px; font-family: monospace;">
        <h2>Flow 7 Diagnostics</h2>
        
        <h3>1. Current User Info</h3>
        <?php 
        $user = wp_get_current_user();
        echo '<pre>';
        echo 'User ID: ' . $user->ID . "\n";
        echo 'Display Name: ' . $user->display_name . "\n";
        echo 'Email: ' . $user->user_email . "\n";
        echo 'Roles: ' . implode(', ', $user->roles) . "\n";
        echo '</pre>';
        ?>

        <h3>2. Registered Shortcodes</h3>
        <?php
        global $shortcode_tags;
        $flow7_shortcodes = [
            'platform_college_request_class',
            'platform_college_my_classes',
            'platform_expert_college_requests'
        ];
        echo '<ul>';
        foreach ($flow7_shortcodes as $sc) {
            $registered = isset($shortcode_tags[$sc]);
            echo '<li>' . $sc . ': ' . ($registered ? '<strong style="color:green;">? Registered</strong>' : '<strong style="color:red;">? NOT Registered</strong>') . '</li>';
        }
        echo '</ul>';
        ?>

        <h3>3. Database Tables</h3>
        <?php
        $tables = [
            $wpdb->prefix . 'platform_requests',
            $wpdb->prefix . 'platform_request_responses',
            $wpdb->prefix . 'platform_contracts',
            $wpdb->prefix . 'platform_calendar_map'
        ];
        echo '<ul>';
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;
            echo '<li>' . $table . ': ' . ($exists ? '<strong style="color:green;">? Exists</strong> (' . $count . ' rows)' : '<strong style="color:red;">? Missing</strong>') . '</li>';
        }
        echo '</ul>';
        ?>

        <h3>4. Requests Data (Last 5)</h3>
        <?php
        $tbl = $wpdb->prefix . 'platform_requests';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tbl}'") === $tbl) {
            $requests = $wpdb->get_results("SELECT * FROM {$tbl} ORDER BY id DESC LIMIT 5");
            if ($requests) {
                echo '<table border="1" cellpadding="5" style="background:white;">';
                echo '<tr><th>ID</th><th>College User</th><th>Expert User</th><th>Topic</th><th>Status</th><th>Created</th></tr>';
                foreach ($requests as $r) {
                    echo '<tr>';
                    echo '<td>#' . $r->id . '</td>';
                    echo '<td>' . $r->college_user_id . '</td>';
                    echo '<td>' . $r->expert_user_id . '</td>';
                    echo '<td>' . esc_html($r->topic) . '</td>';
                    echo '<td>' . $r->status . '</td>';
                    echo '<td>' . $r->created_at . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No requests found.</p>';
            }
        } else {
            echo '<p style="color:red;">Table does not exist!</p>';
        }
        ?>

        <h3>5. Users with Expert Role</h3>
        <?php
        $experts = get_users(['role' => 'expert', 'fields' => ['ID', 'display_name', 'user_email']]);
        if ($experts) {
            echo '<ul>';
            foreach ($experts as $e) {
                echo '<li>ID: ' . $e->ID . ' - ' . $e->display_name . ' (' . $e->user_email . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color:orange;">No users with "expert" role found.</p>';
        }
        ?>

        <h3>6. Users with College Admin Capability</h3>
        <?php
        $colleges = get_users(['fields' => ['ID', 'display_name', 'user_email']]);
        $college_admins = array_filter($colleges, function($u) {
            $user = new WP_User($u->ID);
            return $user->has_cap('college_admin');
        });
        
        if ($college_admins) {
            echo '<ul>';
            foreach ($college_admins as $c) {
                echo '<li>ID: ' . $c->ID . ' - ' . $c->display_name . ' (' . $c->user_email . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color:orange;">No users with "college_admin" capability found.</p>';
        }
        ?>

        <h3>7. Settings</h3>
        <?php
        $opts = get_option('platform_core_college_settings', []);
        echo '<pre>';
        print_r($opts);
        echo '</pre>';
        ?>

        <h3>8. REST API Endpoint Test</h3>
        <p>REST URL: <code><?php echo esc_url(rest_url('platform-core/v1/college/response')); ?></code></p>
        
        <h3>9. Plugin Files Check</h3>
        <?php
        $files = [
            plugin_dir_path(__FILE__) . 'flow7-college.php',
            plugin_dir_path(__FILE__) . 'flow7-college-expert-inbox.php',
            plugin_dir_path(__FILE__) . '../templates/contract-college.php'
        ];
        echo '<ul>';
        foreach ($files as $file) {
            $exists = file_exists($file);
            echo '<li>' . basename($file) . ': ' . ($exists ? '<strong style="color:green;">? Exists</strong>' : '<strong style="color:red;">? Missing</strong>') . '</li>';
        }
        echo '</ul>';
        ?>

    </div>
    <?php
    return ob_get_clean();
});