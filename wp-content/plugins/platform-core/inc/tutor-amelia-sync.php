<?php
if (!defined('ABSPATH')) exit;
return ;
/**
 * Tutor LMS <-> Amelia auto-sync
 * - When a Tutor instructor is approved, create Amelia Employee (provider) and map to all services, 24/7 schedule, Location 1.
 * - When a Tutor student registers, create (or fetch) Amelia Customer.
 *
 * Stores persistent mappings in user meta:
 *   - amelia_employee_id   (for instructors)
 *   - amelia_customer_id   (for students)
 *
 * Requires Amelia "Elite / Developer" API endpoints enabled (Header: Amelia: <API_KEY>)
 */

// -----------------------------------------------------------------------------
// Small utilities (rely on existing helpers if present)
// -----------------------------------------------------------------------------

if (!function_exists('platform_core_amelia_api_base')) {
    function platform_core_amelia_api_base($path) {
        // All Amelia API calls go through admin-ajax + wpamelia_api
        return admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1' . $path);
    }
}

if (!function_exists('platform_core_log_amelia')) {
    function platform_core_log_amelia($title, $data) {
        // You seem to already have a logger by this name, but guard just in case.
        $out = is_string($data) ? $data : wp_json_encode($data);
        error_log("[platform-core][amelia-sync] {$title}: {$out}");
    }
}

function pcore_http_get($path, $query = []) {
    $url = platform_core_amelia_api_base($path) . ( $query ? ('&' . http_build_query($query)) : '' );
    $res = wp_remote_get($url, [
        'headers' => platform_core_amelia_api_headers(),
        'timeout' => 20,
    ]);
    if (is_wp_error($res)) {
        return $res;
    }
    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    return ($code >= 200 && $code < 300) ? $body : new WP_Error("amelia_http_{$code}", 'Amelia GET failed', ['status'=>$code, 'body'=>$body]);
}

function pcore_http_post($path, $payload = []) {
    $url = platform_core_amelia_api_base($path);
    $res = wp_remote_post($url, [
        'headers' => platform_core_amelia_api_headers(),
        'timeout' => 30,
        'body'    => wp_json_encode($payload),
    ]);
    if (is_wp_error($res)) {
        return $res;
    }
    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    return ($code >= 200 && $code < 300) ? $body : new WP_Error("amelia_http_{$code}", 'Amelia POST failed', ['status'=>$code, 'body'=>$body, 'payload'=>$payload]);
}

function pcore_http_put($path, $payload = []) {
    $url = platform_core_amelia_api_base($path);
    $res = wp_remote_request($url, [
        'method'  => 'PUT',
        'headers' => platform_core_amelia_api_headers(),
        'timeout' => 30,
        'body'    => wp_json_encode($payload),
    ]);
    if (is_wp_error($res)) {
        return $res;
    }
    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    return ($code >= 200 && $code < 300) ? $body : new WP_Error("amelia_http_{$code}", 'Amelia PUT failed', ['status'=>$code, 'body'=>$body, 'payload'=>$payload]);
}

// -----------------------------------------------------------------------------
// Lookups: services, locations, users (employee/customer)
// -----------------------------------------------------------------------------

function pcore_amelia_get_all_services() {
    // Fetch all services (iterate pages if needed)
    $page    = 1;
    $all     = [];
    do {
        $resp = pcore_http_get('/services', ['page' => $page]);
        if (is_wp_error($resp)) {
            platform_core_log_amelia('Services GET error', $resp->get_error_message());
            break;
        }
        // Common Amelia response shape: ["data"=>["services"=>[...]]] or ["data"=>[...]] (docs show "services")
        $services = [];
        if (isset($resp['data']['services']) && is_array($resp['data']['services'])) {
            $services = $resp['data']['services'];
        } elseif (isset($resp['data']) && is_array($resp['data'])) {
            // Some versions return list directly under data
            $services = $resp['data'];
        }
        $all = array_merge($all, $services);
        // Heuristic: stop if fewer than 10 returned
        $done = count($services) < 10;
        $page++;
    } while (!$done);

    return $all;
}

function pcore_amelia_find_or_create_location_1() {
    $name = 'Location 1';
    // Try search
    $resp = pcore_http_get('/locations', ['page' => 1 /* docs show paging; some builds support search by name with &search= */]);
    if (!is_wp_error($resp)) {
        $locs = isset($resp['data']['locations']) ? $resp['data']['locations'] : (isset($resp['data']) ? $resp['data'] : []);
        if (is_array($locs)) {
            foreach ($locs as $loc) {
                if (!empty($loc['name']) && strtolower($loc['name']) === strtolower($name)) {
                    return (int)$loc['id'];
                }
            }
        }
    }
    // Create it if not found
    $created = pcore_http_post('/locations', ['name' => $name]);
    if (is_wp_error($created)) {
        platform_core_log_amelia('Create Location 1 error', $created->get_error_data());
        return 0;
    }
    return (int)($created['data']['location']['id'] ?? 0);
}

function pcore_amelia_find_employee_by_email($email) {
    $resp = pcore_http_get('/users/providers', ['page' => 1, 'search' => $email]);
    if (is_wp_error($resp)) return 0;
    $users = isset($resp['data']['users']) ? $resp['data']['users'] : [];
    foreach ($users as $u) {
        if (!empty($u['email']) && strtolower($u['email']) === strtolower($email)) {
            return (int)$u['id'];
        }
    }
    return 0;
}

function pcore_amelia_find_customer_by_email($email) {
    $resp = pcore_http_get('/users/customers', ['page' => 1, 'search' => $email]);
    if (is_wp_error($resp)) return 0;
    $users = isset($resp['data']['users']) ? $resp['data']['users'] : [];
    foreach ($users as $u) {
        if (!empty($u['email']) && strtolower($u['email']) === strtolower($email)) {
            return (int)$u['id'];
        }
    }
    return 0;
}

// -----------------------------------------------------------------------------
// Builders: 24/7 working hours + all services mapping
// -----------------------------------------------------------------------------

function pcore_build_weekday_list_24_7($locationId) {
    $out = [];
    for ($i = 1; $i <= 7; $i++) {
        $out[] = [
            'dayIndex'   => $i,                // 1..7
            'startTime'  => '00:00:00',
            'endTime'    => '23:59:59',
            'timeOutList'=> [],
            'periodList' => [[
                'startTime'         => '00:00:00',
                'endTime'           => '23:59:59',
                'periodLocationList'=> $locationId ? [['locationId' => $locationId]] : [],
                'periodServiceList' => []
            ]],
        ];
    }
    return $out;
}


function pcore_build_service_list_all($services) {
    $list = [];
    foreach ($services as $svc) {
        if (empty($svc['id'])) continue;
        $list[] = ['id' => (int)$svc['id']]; // minimal & safest mapping
    }
    return $list;
}


// -----------------------------------------------------------------------------
// Create / update: Employee (provider) + Customer
// -----------------------------------------------------------------------------

function pcore_create_employee_for_user($user_id) {
    $user = get_userdata($user_id);
    if (!$user) return new WP_Error('no_user', 'User not found');

    // Already mapped?
    $existing = (int) get_user_meta($user_id, 'amelia_employee_id', true);
    if ($existing) return $existing;

    $email = $user->user_email;
    $first = get_user_meta($user_id, 'first_name', true) ?: $user->user_login;
    $last  = get_user_meta($user_id, 'last_name',  true) ?: '';

    // 1) Reuse if provider with same email exists
    $providerId = pcore_amelia_find_employee_by_email($email);
    if ($providerId) {
        update_user_meta($user_id, 'amelia_employee_id', $providerId);
        update_user_meta($user_id, 'platform_amelia_employee_id', $providerId);
        return $providerId;
    }

    // 2) Ensure Location 1 exists
    $locationId  = pcore_amelia_find_or_create_location_1();

    // 3) Assign ALL services
    $services    = pcore_amelia_get_all_services();
    $serviceList = pcore_build_service_list_all($services);
    $weekDayList = pcore_build_weekday_list_24_7($locationId);

    // (Optional) map "Select Speciality" into note/description
    $speciality = get_user_meta($user_id, 'speciality', true);
    if (!$speciality) $speciality = get_user_meta($user_id, 'select_speciality', true);

    $payload = [
        'status'     => 'visible',
        'firstName'  => $first,
        'lastName'   => $last,
        'email'      => $email,
        'externalId' => (int)$user_id,     // WP user mapping
        'locationId' => $locationId ?: null,
        'serviceList'=> $serviceList,
        'weekDayList'=> $weekDayList,
        'note'       => $speciality ? ('Speciality: ' . sanitize_text_field($speciality)) : '',
        'sendEmployeePanelAccessEmail' => false,
    ];

    $resp = pcore_http_post('/users/providers', $payload);
    if (is_wp_error($resp)) {
        platform_core_log_amelia('Create employee failed', $resp->get_error_data());
        return $resp;
    }

    $id = (int)($resp['data']['user']['id'] ?? 0);
    if ($id) {
        update_user_meta($user_id, 'amelia_employee_id', $id);
        update_user_meta($user_id, 'platform_amelia_employee_id', $id);
        return $id;
    }
    return new WP_Error('no_employee_id', 'Amelia did not return an employee id', $resp);
}


function pcore_create_or_get_customer_for_user($user_id) {
    $user = get_userdata($user_id);
    if (!$user) return new WP_Error('no_user', 'User not found');

    // Prevent duplicates
    $existing = (int) get_user_meta($user_id, 'amelia_customer_id', true);
    if ($existing) return (int)$existing;

    $email = $user->user_email;
    $first = get_user_meta($user_id, 'first_name', true) ?: $user->user_login;
    $last  = get_user_meta($user_id, 'last_name',  true) ?: '';

    // Reuse by email if present
    $byEmail = pcore_amelia_find_customer_by_email($email);
    if ($byEmail) {
        // Also ensure externalId = WP user id
        pcore_http_post('/users/customers/' . (int)$byEmail, ['externalId' => (int)$user_id]);
        update_user_meta($user_id, 'amelia_customer_id', $byEmail);
        return (int)$byEmail;
    }

    $payload = [
        'firstName'  => $first,
        'lastName'   => $last,
        'email'      => $email,
        'externalId' => (int)$user_id,
    ];

    $resp = pcore_http_post('/users/customers', $payload);
    if (is_wp_error($resp)) {
        platform_core_log_amelia('Create customer failed', $resp->get_error_data());
        return $resp;
    }

    $id = (int)($resp['data']['user']['id'] ?? 0);
    if ($id) {
        update_user_meta($user_id, 'amelia_customer_id', $id);
        return $id;
    }
    return new WP_Error('no_customer_id', 'Amelia did not return a customer id', $resp);
}

// -----------------------------------------------------------------------------
// HOOKS
// -----------------------------------------------------------------------------

/**
 * 1) INSTRUCTOR: On Tutor approval, create Amelia Employee.
 * We listen for meta key _tutor_instructor_status changing to 'approved'
 * and then create + map in Amelia, storing amelia_employee_id
 */

// add_action('updated_user_meta', function($meta_id, $user_id, $meta_key, $_meta_value) {
//     if ($meta_key !== '_tutor_instructor_status') return;

//     $new = get_user_meta($user_id, '_tutor_instructor_status', true);
//     if ($new !== 'approved') return; // run only when transitioned to "approved"

//     // Prevent duplicates
//     if ((int) get_user_meta($user_id, 'amelia_employee_id', true)) return;

//     $result = pcore_create_employee_for_user((int)$user_id);
//     // log and handle $result (already in your file)
// }, 10, 4);


/**
 * 2) STUDENT: Immediately after Tutor student registration, create Amelia Customer.
 */
add_action('tutor_after_student_register', function($user_id, $data) {
    $result = pcore_create_or_get_customer_for_user((int)$user_id);
    if (is_wp_error($result)) {
        platform_core_log_amelia('Student register sync error', $result->get_error_data());
    } else {
        platform_core_log_amelia('Student registered -> Amelia customer ensured', ['user_id' => $user_id, 'amelia_customer_id' => $result]);
    }
}, 10, 2);

// Fallback: if for any reason Tutor hook didn’t fire, catch all user registrations
// Helpers
function pcore_user_is_explicit_student($user) {
    // Adjust these slugs to match your site. On your install students have a distinct "student" role.
    $student_roles = ['student', 'tutor_student'];
    return (bool) array_intersect($student_roles, (array) $user->roles);
}
function pcore_user_is_instructor_applicant($user_id) {
    // Tutor LMS sets this meta when a user applied to become instructor.
    $status = get_user_meta($user_id, '_tutor_instructor_status', true);
    return !empty($status); // 'pending' before approval, 'approved' after approval
}

// ✅ Strict fallback (optional)
// Better: delete this whole fallback and rely on the Tutor student hook only.
// If you must keep a fallback, add strict guards:
add_action('user_register', function($user_id) {
    if (!isset($_POST) || empty($_POST)) return;

    // Only run if this is the Student registration form submission
    // (Tutor forms usually include an action or nonce specific to student form)
    $is_student_form = isset($_POST['tutor_action']) && $_POST['tutor_action'] === 'student_register';
    if (!$is_student_form) return;

    pcore_create_or_get_customer_for_user((int)$user_id);
}, 20);


// Fires on student registration – maps to Amelia Customer
add_action('tutor_after_student_register', 'pcore__student_to_amelia_customer', 10, 2);
add_action('tutor_after_student_signup',   'pcore__student_to_amelia_customer', 10, 2);

function pcore__student_to_amelia_customer($user_id, $data = []) {
    $res = pcore_create_or_get_customer_for_user((int)$user_id);
    if (is_wp_error($res)) {
        platform_core_log_amelia('Student register sync error', $res->get_error_data());
    } else {
        platform_core_log_amelia('Student registered -> Amelia customer ensured', ['user_id' => $user_id, 'amelia_customer_id' => $res]);
    }
}

// (i) Tutor LMS meta gate – support both keys people use across versions:
// add_action('updated_user_meta', function($meta_id, $user_id, $meta_key, $_meta_value) {
//     if ($meta_key !== '_tutor_instructor_status' && $meta_key !== 'tutor_instructor_status') return;
//     $new = get_user_meta($user_id, $meta_key, true);
//     if ($new !== 'approved') return;
//     pcore__ensure_employee_after_approval((int)$user_id, 'meta:' . $meta_key);
// }, 10, 4);

// (ii) Role added (covers your platform-core role assignment on approval)
// add_action('add_user_role', function($user_id, $role) {
//     // If either Tutor's instructor role or your Expert role is added, create employee
//     if (in_array($role, ['tutor_instructor', 'instructor', 'expert'], true)) {
//         pcore__ensure_employee_after_approval((int)$user_id, 'add_user_role:' . $role);
//     }
// }, 10, 2);

// (iii) Role set (covers cases where roles are set in one shot)
// add_action('set_user_role', function($user_id, $role, $old_roles) {
//     if (in_array($role, ['tutor_instructor', 'instructor', 'expert'], true)) {
//         pcore__ensure_employee_after_approval((int)$user_id, 'set_user_role:' . $role);
//     }
// }, 10, 3);

// Helper that actually creates the Amelia Employee once
// function pcore__ensure_employee_after_approval($user_id, $source) {
//     // Avoid duplicates
//     if ((int) get_user_meta($user_id, 'amelia_employee_id', true)) {
//         platform_core_log_amelia('Instructor already mapped as employee (skip)', compact('user_id','source'));
//         return;
//     }

//     // Make sure this user really is an instructor now (role check)
//     $u = get_userdata($user_id);
//     if ($u && array_intersect(['tutor_instructor', 'instructor', 'expert'], (array) $u->roles)) {
//         $res = pcore_create_employee_for_user((int)$user_id);
//         if (is_wp_error($res)) {
//             platform_core_log_amelia('Instructor approval sync error', ['src' => $source, 'err' => $res->get_error_data()]);
//         } else {
//             platform_core_log_amelia('Instructor approved -> Amelia employee created', ['src' => $source, 'user_id' => $user_id, 'amelia_employee_id' => $res]);
//         }
//     } else {
//         platform_core_log_amelia('Instructor approval signal but roles not set yet', ['src' => $source, 'user_id' => $user_id, 'roles' => $u ? $u->roles : []]);
//     }
// }


