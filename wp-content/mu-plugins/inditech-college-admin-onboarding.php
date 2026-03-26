<?php
/**
 * Plugin Name: Inditech – College Admin Onboarding
 * Description: When a user registers via the College Admin form, assign role "college_admin" and create an Amelia Customer (no manual approval).
 * Author: Inditech
 */

if (!defined('ABSPATH')) exit;

if (!defined('INDITECH_AMELIA_DEBUG')) {
    define('INDITECH_AMELIA_DEBUG', false); // Set to true for debugging
}

/* ==========================================================================
   1. MAIN REGISTRATION HANDLER
   ========================================================================== */
add_action('user_register', function ($user_id) {
    add_action('shutdown', function () use ($user_id) {

        if (!inditech_ca_is_college_admin_context($user_id)) {
            inditech_ca_log('skip', ['user_id' => $user_id, 'reason' => 'not_college_admin_context']);
            return;
        }

        inditech_ca_log('start', ['user_id' => $user_id]);

        // 1. Persist form fields (idempotent)
        inditech_ca_save_registration_meta($user_id);

        // 2. Create or link Amelia Customer (idempotent)
        inditech_ca_ensure_amelia_customer($user_id);

        // 3. Enforce final roles: primary "college_admin", keep "wpamelia-customer"
        inditech_ca_set_primary_role($user_id);

        inditech_ca_log('done', [
            'user_id'    => $user_id,
            'roles_final'=> (new WP_User($user_id))->roles
        ]);
    });
}, 999, 1);

/* ==========================================================================
   2. CONTEXT DETECTION
   ========================================================================== */
function inditech_ca_is_college_admin_context($user_id) {
    // A. Best: explicit hidden field from the form
    if (!empty($_POST['register_source']) && $_POST['register_source'] === 'college_admin') return true;
    if (!empty($_POST['account_type'])   && $_POST['account_type']   === 'college_admin') return true;

    // B. Detect presence of your custom fields in POST
    $has_fields = isset($_POST['institution_name']) || isset($_POST['contact_name']) ||
                  isset($_POST['contact_number'])   || isset($_POST['experience_years']);
    if ($has_fields) return true;

    // C. Check referer path
    $ref = $_POST['_wp_http_referer'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref && strpos($ref, '/college_registration/') !== false) return true;

    // D. If meta already saved earlier in the request
    if (get_user_meta($user_id, 'institution_name', true)) return true;

    return false;
}

/* ==========================================================================
   3. SAVE REGISTRATION META
   ========================================================================== */
function inditech_ca_save_registration_meta($user_id) {
    $map = [
        'institution_name' => 'sanitize_text_field',
        'contact_name'     => 'sanitize_text_field',
        'contact_number'   => 'sanitize_text_field',
        'experience_years' => 'absint',
    ];
    
    foreach ($map as $key => $san) {
        if (isset($_POST[$key]) && $_POST[$key] !== '') {
            $val = call_user_func($san, wp_unslash($_POST[$key]));
            update_user_meta($user_id, $key, $val);
        }
    }

    // Convenience mapping
    $u = get_userdata($user_id);
    if ($u && $u->user_email) {
        update_user_meta($user_id, 'billing_email', $u->user_email);
    }
}

/* ==========================================================================
   4. AMELIA CUSTOMER CREATION
   ========================================================================== */
function inditech_ca_amelia_endpoint($path) {
    return admin_url('admin-ajax.php?action=wpamelia_api&call=' . ltrim($path, '/'));
}

function inditech_ca_ensure_amelia_customer($user_id) {
    if (!defined('AMELIA_API_KEY') || !AMELIA_API_KEY) {
        inditech_ca_log('amelia_error', ['user_id' => $user_id, 'reason' => 'missing_api_key']);
        return;
    }

    // Skip if already linked
    if (get_user_meta($user_id, '_amelia_customer_id', true)) {
        inditech_ca_log('amelia_skip', ['user_id' => $user_id, 'reason' => 'already_has_customer_id']);
        return;
    }

    $u = get_userdata($user_id);
    if (!$u) return;

    // Names
    $first = get_user_meta($user_id, 'first_name', true);
    $last  = get_user_meta($user_id,  'last_name', true);
    if (!$first || !$last) {
        $parts = preg_split('/\s+/', trim($u->display_name));
        $first = $first ?: ($parts[0] ?? 'College');
        $last  = $last  ?: ($parts[1] ?? 'Admin');
    }

    $payload = [
        'firstName'       => $first,
        'lastName'        => $last,
        'externalId'      => (int) $user_id,
        'email'           => $u->user_email ?: null,
        'phone'           => (string) (get_user_meta($user_id, 'contact_number', true) ?: ''),
        'countryPhoneIso' => strtolower((string) get_user_meta($user_id, 'country_phone_iso', true) ?: 'in'),
        'note'            => sprintf(
            'Institution: %s; Contact: %s',
            (string) get_user_meta($user_id, 'institution_name', true),
            (string) get_user_meta($user_id, 'contact_name', true)
        ),
    ];
    // Remove empty/null values
    $payload = array_filter($payload, static function ($v) { return $v !== '' && $v !== null; });

    $args = [
        'timeout' => 25,
        'headers' => [
            'Content-Type' => 'application/json',
            'Amelia'       => AMELIA_API_KEY,
        ],
        'body' => wp_json_encode($payload),
    ];

    $endpoint = inditech_ca_amelia_endpoint('/api/v1/users/customers');
    $res = wp_remote_post($endpoint, $args);
    $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
    $raw  = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
    $body = $raw ? json_decode($raw, true) : null;

    inditech_ca_log('amelia_post', ['endpoint' => $endpoint, 'code' => $code, 'body' => $body]);

    if (!is_wp_error($res) && $code >= 200 && $code < 300 && !empty($body['data']['user']['id'])) {
        $cid = (int) $body['data']['user']['id'];
        update_user_meta($user_id, '_amelia_customer_id', $cid);
        inditech_ca_log('amelia_created', ['user_id' => $user_id, 'customer_id' => $cid]);
        return;
    }

    // If email already exists (unique constraint), lookup and link
    $message = is_array($body) ? ($body['message'] ?? '') : (is_wp_error($res) ? $res->get_error_message() : 'unknown');
    if ($code === 409 || stripos($message, 'email') !== false) {
        $found = inditech_ca_find_amelia_customer_by_email($u->user_email);
        if ($found && !empty($found['id'])) {
            update_user_meta($user_id, '_amelia_customer_id', (int) $found['id']);
            inditech_ca_log('amelia_linked_existing', ['user_id' => $user_id, 'customer_id' => (int) $found['id']]);
            return;
        }
    }

    update_user_meta($user_id, '_amelia_last_error', trim($code . ' ' . $message));
    inditech_ca_log('amelia_error', ['user_id' => $user_id, 'code' => $code, 'message' => $message]);
}

function inditech_ca_find_amelia_customer_by_email($email) {
    if (!$email) return null;

    $endpoint = inditech_ca_amelia_endpoint('/api/v1/users/customers?page=1&search=' . rawurlencode($email));
    $args = [
        'timeout' => 15,
        'headers' => [
            'Amelia' => AMELIA_API_KEY,
        ],
    ];
    $res  = wp_remote_get($endpoint, $args);
    $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
    $raw  = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
    $body = $raw ? json_decode($raw, true) : null;

    inditech_ca_log('amelia_get', ['endpoint' => $endpoint, 'code' => $code]);

    if ($code >= 200 && $code < 300 && !empty($body['data']['users'])) {
        foreach ($body['data']['users'] as $row) {
            if (isset($row['email']) && strtolower($row['email']) === strtolower($email)) {
                return $row;
            }
        }
    }
    return null;
}

/* ==========================================================================
   5. ROLE MANAGEMENT
   ========================================================================== */
function inditech_ca_set_primary_role($user_id) {
    $u = new WP_User($user_id);

    // Ensure the custom role exists
    if (!get_role('college_admin')) {
        add_role('college_admin', 'College Admin', ['read' => true]);
        inditech_ca_log('role_created', []);
    }

    // Store current Amelia role status
    $had_amelia = in_array('wpamelia-customer', $u->roles, true);

    // Remove subscriber role
    if (in_array('subscriber', $u->roles, true)) {
        $u->remove_role('subscriber');
    }

    // Set college_admin as primary role
    if (!in_array('college_admin', $u->roles, true)) {
        $u->set_role('college_admin');
    }

    // Restore Amelia role if it was present or should be present
    if ($had_amelia || get_role('wpamelia-customer')) {
        if (!in_array('wpamelia-customer', $u->roles, true)) {
            $u->add_role('wpamelia-customer');
        }
    }

    inditech_ca_log('role_set', ['user_id' => $user_id, 'roles' => $u->roles]);
}

/* ==========================================================================
   6. SAFETY HOOK: Restore college_admin if Amelia overwrites it
   ========================================================================== */
add_action('set_user_role', function ($user_id, $role, $old_roles) {
    // Only react when Amelia sets wpamelia-customer role
    if (
        $role === 'wpamelia-customer' &&
        isset($_GET['action']) && $_GET['action'] === 'wpamelia_api' &&
        get_user_meta($user_id, 'institution_name', true) // Only for College Admin users
    ) {
        $u = new WP_User($user_id);
        if (!in_array('college_admin', $u->roles, true)) {
            $u->add_role('college_admin');
            inditech_ca_log('role_restored', [
                'user_id'    => $user_id,
                'roles'      => $u->roles
            ]);
        }
    }
}, 20, 3);

/* ==========================================================================
   7. LOGGING
   ========================================================================== */
function inditech_ca_log($label, $data = []) {
    if (!INDITECH_AMELIA_DEBUG) return;
    error_log('[' . gmdate('c') . '][CollegeAdmin] ' . $label . ' : ' . wp_json_encode($data));
}