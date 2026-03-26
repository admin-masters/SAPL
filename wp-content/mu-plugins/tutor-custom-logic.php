<?php
/**
 * Plugin Name: Tutor LMS Ultimate Role Enforcer
 * Description: 1. Forces 'Expert' + 'Tutor Instructor'. 2. Aggressively deletes 'Student/Subscriber'. 3. Fixes 'stuck' users on page load.
 */
if ( ! defined('INDITECH_AMELIA_DEBUG') ) {
    define('INDITECH_AMELIA_DEBUG', true);
}


function inditech_resolve_amelia_endpoint() {
    $path = defined('AMELIA_API_PATH')
        ? AMELIA_API_PATH
        : '/api/v1/users/providers';

    return admin_url(
        'admin-ajax.php?action=wpamelia_api&call=' . $path
    );
}

/* ==========================================================================
   1. THE "SELF-HEALING" FIX (Runs when you visit Users page)
   ========================================================================== */
add_action( 'load-users.php', 'force_fix_roles_on_page_load' );

function force_fix_roles_on_page_load() {
    $instructors = get_users( array( 'role' => 'tutor_instructor' ) );

    foreach ( $instructors as $user ) {
        if ( in_array( 'subscriber', $user->roles ) ) {
            $user->remove_role( 'subscriber' );
        }
        if ( ! in_array( 'expert', $user->roles ) ) {
            $user->add_role( 'expert' );
        }
    }
}

/* ==========================================================================
   2. REGISTRATION APPROVAL
   ========================================================================== */
add_action( 'tutor_instructor_approval_status_changed', function( $user_id, $status ) {
    if ( $status !== 'approved' ) { return; }

    $user = new WP_User( $user_id );
    $user->remove_role( 'subscriber' );
    $user->add_role( 'expert' );
    $user->add_role( 'tutor_instructor' );
    update_user_meta( $user_id, '_is_tutor_instructor', 'yes' );

    update_option( 'inditech_last_approved_instructor', [ 'user_id' => $user_id, 'time' => time() ] );

    inditech_amelia_log('approval', ['user_id' => $user_id, 'source' => 'tutor_instructor_approval_status_changed']);
    inditech_queue_mapping( $user_id, 'approval_hook' );
}, 999, 2 );



/**
 * Build the Amelia employee payload for a given WP user.
 */
function inditech_build_amelia_payload( $user_id ) {
    $u = get_userdata( $user_id );

    $first = get_user_meta( $user_id, 'first_name', true );
    $last  = get_user_meta( $user_id, 'last_name', true );
    if ( ! $first || ! $last ) {
        $parts = preg_split( '/\s+/', trim( $u->display_name ) );
        $first = $first ?: ( $parts[0] ?? 'Instructor' );
        $last  = $last  ?: ( $parts[1] ?? 'User' );
    }

    $countryIso = strtolower( get_user_meta( $user_id, 'country_phone_iso', true ) ?: 'in' );
    $timeZone   = get_user_meta( $user_id, 'time_zone', true );
    if ( ! $timeZone && function_exists('wp_timezone_string') ) {
        $timeZone = wp_timezone_string();
    }
    $timeZone = $timeZone ?: AMELIA_DEFAULT_TZ;

    $serviceIds = (array) AMELIA_DEFAULT_SERVICE_IDS;
    $serviceList = array_map( function( $sid ) {
        return [
            'id'            => (int) $sid,
            'price'         => 0,
            'minCapacity'   => 1,
            'maxCapacity'   => 1,
            'customPricing' => '{"enabled":false,"durations":{}}',
        ];
    }, $serviceIds );

    $weekDayList = [];
    for ( $i = 1; $i <= 7; $i++ ) {
        $weekDayList[] = [
            'dayIndex'    => $i,
            'startTime'   => '09:00:00',
            'endTime'     => '17:00:00',
            'timeOutList' => [],
            'periodList'  => [[
                'startTime'          => '09:00:00',
                'endTime'            => '17:00:00',
                'locationId'         => AMELIA_DEFAULT_LOCATION_ID,
                'periodServiceList'  => [],
                'periodLocationList' => [],
            ]],
        ];
    }

    $spec = trim((string) get_user_meta($user_id, '_tutor_instructor_speciality', true));
    $exp  = get_user_meta($user_id, '_tutor_instructor_experience_years', true);
    $note = $spec ? 'Speciality: '.$spec : '';
    if ($exp !== '' && $exp !== null) {
        $note .= ($note ? ' | ' : '') . 'Experience: ' . floatval($exp) . ' yrs';
    }

    return [
        'status'                       => 'visible',
        'firstName'                    => $first,
        'lastName'                     => $last,
        'email'                        => $u->user_email,
        'externalId'                   => (string) $user_id,
        'locationId'                   => AMELIA_DEFAULT_LOCATION_ID,
        'serviceList'                  => $serviceList,
        'weekDayList'                  => $weekDayList,
        'note'                         => $note,
        'sendEmployeePanelAccessEmail' => true,
        'countryPhoneIso'              => $countryIso,
        'timeZone'                     => $timeZone,
    ];
}


/**
 * POST to Amelia to create an employee (provider) for the WP user (idempotent).
 */
function inditech_validate_amelia_payload( $payload ) {
    $errors = [];

    if ( empty( $payload['email'] ) ) $errors[] = 'email missing';
    if ( empty( $payload['serviceList'] ) || ! is_array( $payload['serviceList'] ) ) {
        $errors[] = 'serviceList missing/empty';
    } else {
        foreach ( $payload['serviceList'] as $i => $svc ) {
            if ( ! isset($svc['id']) ) $errors[] = "serviceList[$i].id missing";
            if ( ! isset($svc['minCapacity']) ) $errors[] = "serviceList[$i].minCapacity missing";
            if ( ! isset($svc['maxCapacity']) ) $errors[] = "serviceList[$i].maxCapacity missing";
        }
    }
    if ( empty( $payload['weekDayList'] ) ) $errors[] = 'weekDayList missing';
    else {
        foreach ( $payload['weekDayList'] as $i => $wd ) {
            if ( empty( $wd['periodList'] ) ) $errors[] = "weekDayList[$i].periodList missing";
            else {
                foreach ( $wd['periodList'] as $j => $p ) {
                    if ( ! isset($p['locationId']) ) $errors[] = "weekDayList[$i].periodList[$j].locationId missing";
                }
            }
        }
    }
    return $errors;
}

function inditech_create_amelia_employee( $user_id ) {
    inditech_amelia_log( 'enter', [ 'user_id' => $user_id ] );

    if ( ! defined('AMELIA_API_KEY') || ! AMELIA_API_KEY ) {
        inditech_amelia_log( 'error', [ 'msg' => 'Missing AMELIA_API_KEY' ] );
        return;
    }

    if ( get_user_meta( $user_id, '_amelia_employee_id', true ) ) {
        inditech_amelia_log( 'skip', [ 'user_id' => $user_id, 'reason' => 'already_has_employee_id' ] );
        return;
    }

    $lock_key = 'amelia_create_lock_' . $user_id;
    if ( get_transient( $lock_key ) ) { return; }
    set_transient( $lock_key, 1, 60 );

    $spec = get_user_meta($user_id, '_tutor_instructor_speciality', true);
    $exp  = get_user_meta($user_id, '_tutor_instructor_experience_years', true);
    inditech_amelia_log('payload_enriched', [
        'user_id'    => $user_id,
        'speciality' => $spec,
        'experience' => $exp,
    ]);

    $payload = inditech_build_amelia_payload( $user_id );
    $validation = inditech_validate_amelia_payload( $payload );
    if ( $validation ) {
        update_user_meta( $user_id, '_amelia_last_error', implode('; ', $validation) );
        inditech_amelia_log( 'validation_error', [ 'user_id' => $user_id, 'errors' => $validation, 'payload' => $payload ] );
        delete_transient( $lock_key );
        return;
    }

    $args = [
        'timeout' => 25,
        'headers' => [
            'Content-Type' => 'application/json',
            'Amelia'       => AMELIA_API_KEY,
        ],
        'body' => wp_json_encode( $payload ),
    ];

    $endpoint = inditech_resolve_amelia_endpoint();

    inditech_amelia_log( 'post_attempt', [ 'endpoint' => $endpoint, 'payload' => $payload ] );

    $res  = wp_remote_post( $endpoint, $args );
    $code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );

    if ( $code === 0 || $code === 404 ) {
        $endpoint = inditech_resolve_amelia_endpoint();
        inditech_amelia_log( 'post_retry', [ 'endpoint' => $endpoint ] );
        $res  = wp_remote_post( $endpoint, $args );
        $code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
    }

    if ( is_wp_error( $res ) ) {
        $msg = $res->get_error_message();
        update_user_meta( $user_id, '_amelia_last_error', $msg );
        inditech_amelia_log( 'http_error', [ 'user_id' => $user_id, 'msg' => $msg ] );
        delete_transient( $lock_key );
        return;
    }

    $body_raw = wp_remote_retrieve_body( $res );
    $body     = json_decode( $body_raw, true );

    inditech_amelia_log( 'post_result', [ 'code' => $code, 'body' => $body ] );

    if ( $code >= 200 && $code < 300 && ! empty( $body['data']['user']['id'] ) ) {
        $employee_id = (int) $body['data']['user']['id'];
        update_user_meta( $user_id, '_amelia_employee_id', $employee_id );
        update_user_meta( $user_id, '_amelia_employee_endpoint', $endpoint );
        update_user_meta( $user_id, '_amelia_employee_payload', $payload );
        inditech_amelia_log( 'created', [ 'user_id' => $user_id, 'employee_id' => $employee_id ] );
    } else {
        $msg = inditech_array_get( $body, 'message', 'Unknown Amelia error' );
        update_user_meta( $user_id, '_amelia_last_error', $code . ' ' . $msg );
        inditech_amelia_log( 'amelia_error', [ 'user_id' => $user_id, 'code' => $code, 'body' => $body, 'payload' => $payload ] );
    }

    delete_transient( $lock_key );
}

// === QUEUE a single mapping and run it via WP-Cron (deduped per user) ===
add_action('inditech_amelia_run_one', function ($user_id) {
    inditech_amelia_log('queue_run', ['user_id' => $user_id]);
    inditech_create_amelia_employee($user_id);
}, 10, 1);

function inditech_queue_mapping($user_id, $reason = '') {
    if ( get_user_meta($user_id, '_amelia_employee_id', true) ) {
        inditech_amelia_log('queue_skip', ['user_id' => $user_id, 'reason' => 'already_has_employee_id']);
        return;
    }
    $lock = 'amelia_queue_' . $user_id;
    if ( get_transient($lock) ) {
        inditech_amelia_log('queue_dupe', ['user_id' => $user_id, 'reason' => $reason]);
        return;
    }
    set_transient($lock, 1, 300);
    if ( ! wp_next_scheduled('inditech_amelia_run_one', [$user_id]) ) {
        wp_schedule_single_event(time() + 2, 'inditech_amelia_run_one', [$user_id]);
    }
    update_user_meta($user_id, '_amelia_queue_reason', $reason);
    inditech_amelia_log('queue', ['user_id' => $user_id, 'reason' => $reason]);
}


function inditech_amelia_log( $label, $data = [] ) {
    if ( ! INDITECH_AMELIA_DEBUG ) return;
    $line = '[' . gmdate('c') . '][AmeliaSync] ' . $label . ' : ' . wp_json_encode( $data );
    error_log( $line );
}

function inditech_array_get( $arr, $key, $default = null ) {
    return is_array($arr) && array_key_exists($key, $arr) ? $arr[$key] : $default;
}

/* ==========================================================================
   3. SAVE SPECIALITY + ABOUT ME (Registration Form)
   ========================================================================== */
add_action( 'user_register', 'save_speciality_field', 10, 1 );

function save_speciality_field( $user_id ) {
    if ( isset( $_POST['user_speciality'] ) ) {
        $data = $_POST['user_speciality'];
        $saved_value = '';

        if ( is_array( $data ) ) {
            $clean_data = array_map( 'sanitize_text_field', $data );
            $saved_value = implode( ', ', $clean_data );
        } else {
            $saved_value = sanitize_text_field( $data );
        }

        // Replace the literal token "Other" with whatever the user typed
        if ( strpos( $saved_value, 'Other' ) !== false && ! empty( $_POST['user_speciality_other'] ) ) {
            $other_text  = sanitize_text_field( $_POST['user_speciality_other'] );
            $saved_value = str_replace( 'Other', $other_text, $saved_value );
        }

        update_user_meta( $user_id, '_tutor_instructor_speciality', $saved_value );
    }

    // Save About Me
    if ( isset( $_POST['user_about_me'] ) ) {
        update_user_meta( $user_id, '_tutor_instructor_about_me', sanitize_textarea_field( $_POST['user_about_me'] ) );
    }
}

add_action('wp_enqueue_scripts', function () {
    if (!is_page('instructor-registration')) return;

    // Inject styles
    $css = <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap');

.indi-section {
  margin: 0 0 28px;
  padding: 0;
}

.indi-section-header {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 14px;
}

.indi-section-label {
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #1a2535;
}

.indi-section-hint {
  font-family: 'DM Sans', sans-serif;
  font-size: 12px;
  font-weight: 400;
  color: #8a95a3;
  letter-spacing: 0;
  text-transform: none;
}

/* -- Speciality pill grid -- */
.indi-pill-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.indi-pill {
  position: relative;
  cursor: pointer;
  user-select: none;
}

.indi-pill input[type="checkbox"] {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.indi-pill-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border: 1.5px solid #d8dfe8;
  border-radius: 100px;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 400;
  color: #4a5568;
  background: #fff;
  transition: all 0.18s ease;
  line-height: 1;
}

.indi-pill-label::before {
  content: '';
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #d8dfe8;
  transition: all 0.18s ease;
  flex-shrink: 0;
}

.indi-pill input:checked + .indi-pill-label {
  border-color: #0e7490;
  background: #ecfeff;
  color: #0e7490;
  font-weight: 500;
}

.indi-pill input:checked + .indi-pill-label::before {
  background: #0e7490;
  box-shadow: 0 0 0 2px #cffafe;
}

.indi-pill-label:hover {
  border-color: #0e7490;
  color: #0e7490;
}

/* -- "Other" pill special style -- */
.indi-pill.indi-pill-other .indi-pill-label {
  border-style: dashed;
  gap: 5px;
}
.indi-pill.indi-pill-other .indi-pill-label::before {
  content: '+';
  width: auto;
  height: auto;
  border-radius: 0;
  background: none;
  font-size: 14px;
  font-weight: 700;
  color: #8a95a3;
  line-height: 1;
  box-shadow: none;
}
.indi-pill.indi-pill-other input:checked + .indi-pill-label::before {
  color: #0e7490;
  box-shadow: none;
  background: none;
}

/* -- Other text input -- */
.indi-other-input-wrap {
  display: none;
  align-items: center;
  gap: 0;
  border: 1.5px solid #0e7490;
  border-radius: 100px;
  background: #ecfeff;
  overflow: hidden;
  padding: 0 4px 0 12px;
  height: 36px;
}
.indi-other-input-wrap.visible {
  display: inline-flex;
}
.indi-other-text-input {
  border: none;
  background: transparent;
  outline: none;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 500;
  color: #0e7490;
  width: 160px;
  min-width: 80px;
  padding: 0;
  line-height: 1;
}
.indi-other-text-input::placeholder {
  color: #7ac8d8;
  font-weight: 400;
}
.indi-other-clear-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: #0e7490;
  font-size: 16px;
  line-height: 1;
  padding: 2px 4px;
  opacity: 0.6;
  transition: opacity 0.15s;
  flex-shrink: 0;
}
.indi-other-clear-btn:hover { opacity: 1; }

/* -- About Me textarea -- */
.indi-textarea-wrap {
  position: relative;
}

.indi-textarea {
  width: 100%;
  min-height: 120px;
  padding: 14px 16px;
  border: 1.5px solid #d8dfe8;
  border-radius: 10px;
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  font-weight: 300;
  color: #1a2535;
  background: #fff;
  resize: vertical;
  box-sizing: border-box;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
  outline: none;
  line-height: 1.6;
}

.indi-textarea::placeholder {
  color: #b0bac6;
  font-style: italic;
}

.indi-textarea:focus {
  border-color: #0e7490;
  box-shadow: 0 0 0 3px rgba(14,116,144,0.1);
}

.indi-char-count {
  position: absolute;
  bottom: 10px;
  right: 14px;
  font-family: 'DM Sans', sans-serif;
  font-size: 11px;
  color: #b0bac6;
  pointer-events: none;
  transition: color 0.2s;
}

.indi-char-count.warn { color: #e07b39; }

/* -- Experience stepper -- */
.indi-stepper {
  display: flex;
  align-items: center;
  gap: 0;
  width: fit-content;
  border: 1.5px solid #d8dfe8;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
}

.indi-step-btn {
  width: 44px;
  height: 48px;
  background: none;
  border: none;
  font-size: 20px;
  font-weight: 300;
  color: #4a5568;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.indi-step-btn:hover { background: #f0f9ff; color: #0e7490; }
.indi-step-btn:active { background: #e0f2fe; }

.indi-step-display {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 0 20px;
  border-left: 1.5px solid #d8dfe8;
  border-right: 1.5px solid #d8dfe8;
  min-width: 90px;
  height: 48px;
}

.indi-step-value {
  font-family: 'DM Serif Display', serif;
  font-size: 22px;
  color: #1a2535;
  line-height: 1;
}

.indi-step-unit {
  font-family: 'DM Sans', sans-serif;
  font-size: 10px;
  color: #8a95a3;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-top: 2px;
}

/* Hidden real input */
.indi-exp-hidden { display: none; }

/* Divider between sections */
.indi-divider {
  height: 1px;
  background: linear-gradient(90deg, transparent, #e8ecf0 20%, #e8ecf0 80%, transparent);
  margin: 6px 0 28px;
}
CSS;
    wp_add_inline_style('wp-block-library', $css);
    // Fallback: also inject via footer
    add_action('wp_head', function() use ($css) {
        echo '<style id="indi-form-styles">' . $css . '</style>';
    });

    $js = <<<'JS'
jQuery(function($){
  var $form = $('form').filter(function(){ return /tutor/i.test(this.className + this.id); }).first();
  if (!$form.length) return;

  var specialities = [
    'Interventional Cardiology','Cardiac Electrophysiology','Neonatology',
    'Maternal\u2013Fetal Medicine','Pediatric Neurology','Neurocritical Care',
    'Interventional Radiology','Vascular Surgery','Gynecologic Oncology',
    'Surgical Oncology','Hematopathology','Transfusion Medicine',
    'Clinical Genetics','Reproductive Endocrinology','Movement Disorders Neurology',
    'Sleep Medicine','Pediatric Cardiac Surgery','Hand and Microsurgery',
    'Pain Medicine','Palliative Medicine'
  ];

  // Build pill checkboxes for standard specialities
  var pills = specialities.map(function(o) {
    var id = 'spec_' + o.replace(/[^a-zA-Z0-9]/g, '_');
    return '<label class="indi-pill">'
      + '<input type="checkbox" id="' + id + '" name="user_speciality[]" value="' + o + '" />'
      + '<span class="indi-pill-label">' + o + '</span>'
      + '</label>';
  }).join('');

  // "Other" pill — checkbox posts the literal token "Other",
  // which save_speciality_field() then replaces with the typed text.
  var otherPill =
    '<label class="indi-pill indi-pill-other" id="indi_other_pill">'
    + '<input type="checkbox" id="spec_Other" name="user_speciality[]" value="Other" />'
    + '<span class="indi-pill-label">Other</span>'
    + '</label>'
    // Inline text input that appears when the pill is checked
    + '<span class="indi-other-input-wrap" id="indi_other_wrap">'
    + '<input type="text" id="indi_other_text" name="user_speciality_other"'
    + '       class="indi-other-text-input" maxlength="80"'
    + '       placeholder="Type your speciality\u2026" autocomplete="off" />'
    + '<button type="button" class="indi-other-clear-btn" id="indi_other_clear" title="Remove">&times;</button>'
    + '</span>';

  var block =
    // -- Speciality --------------------------------------
    '<div class="tutor-form-row"><div class="tutor-form-col-12"><div class="tutor-form-group indi-section">'
    + '<div class="indi-section-header">'
    + '<span class="indi-section-label">Medical Speciality</span>'
    + '<span class="indi-section-hint">Select all that apply</span>'
    + '</div>'
    + '<div class="indi-pill-grid">' + pills + otherPill + '</div>'
    + '</div></div></div>'

    + '<div class="tutor-form-row"><div class="tutor-form-col-12"><div class="indi-divider"></div></div></div>'

    // -- About Me -----------------------------------------
    + '<div class="tutor-form-row"><div class="tutor-form-col-12"><div class="tutor-form-group indi-section">'
    + '<div class="indi-section-header">'
    + '<span class="indi-section-label">About Me</span>'
    + '<span class="indi-section-hint">Your background &amp; expertise</span>'
    + '</div>'
    + '<div class="indi-textarea-wrap">'
    + '<textarea id="indi_about_me" name="user_about_me" class="indi-textarea" maxlength="600"'
    + ' placeholder="Share your clinical background, research interests, and what drives your teaching..."></textarea>'
    + '<span class="indi-char-count" id="indi_char_count">0 / 600</span>'
    + '</div>'
    + '</div></div></div>'

    + '<div class="tutor-form-row"><div class="tutor-form-col-12"><div class="indi-divider"></div></div></div>'

    // -- Experience stepper -------------------------------
    + '<div class="tutor-form-row"><div class="tutor-form-col-12"><div class="tutor-form-group indi-section">'
    + '<div class="indi-section-header">'
    + '<span class="indi-section-label">Years of Experience</span>'
    + '</div>'
    + '<div class="indi-stepper">'
    + '<button type="button" class="indi-step-btn" id="indi_exp_minus">&#8722;</button>'
    + '<div class="indi-step-display">'
    + '<span class="indi-step-value" id="indi_exp_val">0</span>'
    + '<span class="indi-step-unit">years</span>'
    + '</div>'
    + '<button type="button" class="indi-step-btn" id="indi_exp_plus">&#43;</button>'
    + '</div>'
    + '<input type="hidden" name="user_experience_years" id="indi_exp_hidden" value="0" class="indi-exp-hidden" />'
    + '</div></div></div>';

  $form.find('.tutor-form-row:last').before(block);

  // -- "Other" pill toggle logic -------------------------
  $('#spec_Other').on('change', function () {
    var checked = this.checked;
    $('#indi_other_wrap').toggleClass('visible', checked);
    if (checked) {
      setTimeout(function () { $('#indi_other_text').focus(); }, 50);
    } else {
      // Uncheck: clear the text so it isn't submitted
      $('#indi_other_text').val('');
    }
  });

  // Clear button inside the text input removes the "Other" selection
  $('#indi_other_clear').on('click', function () {
    $('#spec_Other').prop('checked', false).trigger('change');
  });

  // Prevent form submit if "Other" is checked but no text entered
  $form.on('submit', function (e) {
    if ($('#spec_Other').is(':checked') && $('#indi_other_text').val().trim() === '') {
      e.preventDefault();
      $('#indi_other_text').focus();
      $('#indi_other_text').css('border-bottom', '2px solid #e53e3e');
      setTimeout(function () { $('#indi_other_text').css('border-bottom', ''); }, 2000);
    }
  });

  // -- Char counter -------------------------------------
  $('#indi_about_me').on('input', function(){
    var len = $(this).val().length;
    var $c = $('#indi_char_count');
    $c.text(len + ' / 600');
    $c.toggleClass('warn', len > 500);
  });

  // -- Experience stepper logic -------------------------
  var expVal = 0;
  function setExp(v) {
    expVal = Math.max(0, Math.min(60, Math.round(v * 2) / 2)); // 0.5 steps
    $('#indi_exp_val').text(expVal % 1 === 0 ? expVal : expVal.toFixed(1));
    $('#indi_exp_hidden').val(expVal);
  }

  $('#indi_exp_minus').on('click', function(e){ e.preventDefault(); setExp(expVal - 1); });
  $('#indi_exp_plus').on('click',  function(e){ e.preventDefault(); setExp(expVal + 1); });

});
JS;

    wp_add_inline_script('jquery-core', $js);
});


function inditech_specialities_list() {
    $defaults = [
        'Interventional Cardiology', 'Cardiac Electrophysiology', 'Neonatology',
        'Maternal–Fetal Medicine', 'Pediatric Neurology', 'Neurocritical Care',
        'Interventional Radiology', 'Vascular Surgery', 'Gynecologic Oncology',
        'Surgical Oncology', 'Hematopathology', 'Transfusion Medicine',
        'Clinical Genetics', 'Reproductive Endocrinology', 'Movement Disorders Neurology',
        'Sleep Medicine', 'Pediatric Cardiac Surgery', 'Hand and Microsurgery',
        'Pain Medicine', 'Palliative Medicine',
    ];
    return apply_filters('inditech_specialities_list', $defaults);
}

/**
 * Save Experience at registration & on profile update
 */
add_action('user_register', 'inditech_save_experience_meta');
add_action('profile_update', 'inditech_save_experience_meta');
function inditech_save_experience_meta($user_id){
    if (isset($_POST['user_experience_years'])) {
        $exp = floatval($_POST['user_experience_years']);
        $exp = max(0, min($exp, 60));
        update_user_meta($user_id, '_tutor_instructor_experience_years', $exp);
    }
}

/**
 * Show Experience + About Me on the WP user profile screen (admin + self)
 */
add_action('show_user_profile', 'inditech_show_experience_field');
add_action('edit_user_profile',  'inditech_show_experience_field');
function inditech_show_experience_field($user) {
    $exp      = get_user_meta($user->ID, '_tutor_instructor_experience_years', true);
    $about_me = get_user_meta($user->ID, '_tutor_instructor_about_me', true);
    ?>
    <h3>Instructor Experience</h3>
    <table class="form-table" role="presentation">
      <tr>
        <th><label for="user_about_me">About Me</label></th>
        <td>
          <textarea name="user_about_me" id="user_about_me" rows="4" class="large-text"><?php echo esc_textarea($about_me); ?></textarea>
          <p class="description">A short bio describing the instructor's background and expertise.</p>
        </td>
      </tr>
      <tr>
        <th><label for="user_experience_years">Experience (years)</label></th>
        <td>
          <input type="number" min="0" step="0.5" name="user_experience_years"
                 id="user_experience_years" value="<?php echo esc_attr($exp); ?>" class="regular-text" />
          <p class="description">Total professional experience in years.</p>
        </td>
      </tr>
    </table>
    <?php
}

// Save About Me on admin profile update too
add_action('personal_options_update', 'inditech_save_about_me_meta');
add_action('edit_user_profile_update', 'inditech_save_about_me_meta');
function inditech_save_about_me_meta($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;
    if (isset($_POST['user_about_me'])) {
        update_user_meta($user_id, '_tutor_instructor_about_me', sanitize_textarea_field($_POST['user_about_me']));
    }
}

/* ==========================================================================
   4. ADMIN PROFILE EDIT (Safety Lock)
   ========================================================================== */
add_action( 'personal_options_update', 'save_speciality_admin_field' );
add_action( 'edit_user_profile_update', 'save_speciality_admin_field' );

function save_speciality_admin_field( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) return false;

    if ( isset( $_POST['admin_user_speciality'] ) ) {
        update_user_meta( $user_id, '_tutor_instructor_speciality', sanitize_text_field( $_POST['admin_user_speciality'] ) );
    }

    // LOCK: Ensure they don't revert to Student when you click "Update User"
    $user = new WP_User( $user_id );
    if ( in_array( 'tutor_instructor', $user->roles ) ) {
        if ( ! in_array( 'expert', $user->roles ) ) $user->add_role( 'expert' );
        if ( in_array( 'subscriber', $user->roles ) ) $user->remove_role( 'subscriber' );
    }
}

/* ==========================================================================
   5. SHOW SPECIALITY FIELD IN PROFILE & COLUMNS
   ========================================================================== */
add_action( 'show_user_profile', 'add_speciality_admin_field' );
add_action( 'edit_user_profile', 'add_speciality_admin_field' );

function add_speciality_admin_field( $user ) {
    if ( ! in_array( 'tutor_instructor', $user->roles ) && ! in_array( 'expert', $user->roles ) ) return;

    $current_speciality = get_user_meta( $user->ID, '_tutor_instructor_speciality', true );
    ?>
    <h3><?php _e( 'Medical Expert Details', 'tutor' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="admin_user_speciality"><?php _e( 'Medical Speciality', 'tutor' ); ?></label></th>
            <td>
                <input type="text" name="admin_user_speciality" value="<?php echo esc_attr( $current_speciality ); ?>" class="regular-text" /><br />
                <span class="description">Comma separated (e.g., Cardiology, Neurology)</span>
            </td>
        </tr>
    </table>
    <?php
}

add_filter( 'manage_users_columns', 'add_speciality_column_header' );
function add_speciality_column_header( $columns ) {
    $columns['medical_speciality'] = 'Speciality';
    return $columns;
}

add_filter( 'manage_users_custom_column', 'fill_speciality_column_content', 10, 3 );
function fill_speciality_column_content( $val, $column_name, $user_id ) {
    if ( 'medical_speciality' === $column_name ) {
        $val = get_user_meta( $user_id, '_tutor_instructor_speciality', true );
        return $val ? $val : '-';
    }
    return $val;
}

/* ==========================================================================
   6. VISUAL MAGIC: SHOW SPECIALITY (UNIVERSAL VERSION)
   ========================================================================== */
add_action('admin_footer', 'inject_speciality_into_tutor_table');

function inject_speciality_into_tutor_table() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'tutor-instructors' ) {
        return;
    }

    $args = array(
        'meta_key' => '_tutor_instructor_speciality',
        'fields'   => 'all_with_meta',
    );
    $instructors = get_users( $args );

    $speciality_map = array();

    foreach ( $instructors as $instructor ) {
        $spec = get_user_meta( $instructor->ID, '_tutor_instructor_speciality', true );
        if ( $spec ) {
            $email = strtolower( trim( $instructor->user_email ) );
            $speciality_map[ $email ] = $spec;
        }
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var specs = <?php echo json_encode( $speciality_map ); ?>;

        $('table tbody tr td').each(function() {
            var cell = $(this);
            var cellText = cell.text().trim().toLowerCase();
            var emailMatch = cellText.match(/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9._-]+)/);

            if (emailMatch) {
                var foundEmail = emailMatch[0];
                if (specs[foundEmail]) {
                    var badge = '<div style="margin-top:6px; padding:4px 8px; background:#e5f5fa; color:#005c87; border-left: 3px solid #00a0d2; font-size:11px; font-weight:600; border-radius: 0 3px 3px 0; display:inline-block;">' +
                                'Speciality: ' + specs[foundEmail] +
                                '</div>';
                    if (cell.find('div:contains("Speciality:")').length === 0) {
                        cell.append('<br>' + badge);
                    }
                }
            }
        });
    });
    </script>
    <?php
}

/* ==========================================================================
   7. FORENSIC AMELIA DEBUG LOGGER (SAFE VERSION)
   ========================================================================== */

add_action( 'plugins_loaded', function () {

    if ( ! defined('INDITECH_AMELIA_DEBUG') || ! INDITECH_AMELIA_DEBUG ) {
        return;
    }

    add_filter( 'http_api_debug', function ( $response, $context, $class, $args, $url ) {
        if ( strpos( $url, 'wpamelia_api' ) === false ) {
            return;
        }
        error_log( '[AmeliaSync][HTTP][' . strtoupper($context) . '] ' . wp_json_encode([
            'url'      => $url,
            'method'   => $args['method'] ?? 'POST',
            'headers'  => $args['headers'] ?? [],
            'body'     => isset($args['body']) ? json_decode($args['body'], true) : null,
            'response' => is_wp_error($response)
                ? $response->get_error_message()
                : [
                    'code' => wp_remote_retrieve_response_code($response),
                    'body' => json_decode(wp_remote_retrieve_body($response), true),
                ],
        ]) );
    }, 10, 5 );

    add_action( 'shutdown', function () {
        $error = error_get_last();
        if ( $error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) ) {
            error_log( '[AmeliaSync][FATAL] ' . wp_json_encode($error) );
        }
    });

    add_action( 'tutor_instructor_approval_status_changed', function( $user_id, $status ) {
        $u = get_userdata($user_id);
        error_log( '[AmeliaSync][HOOK] ' . wp_json_encode([
            'hook'    => 'tutor_instructor_approval_status_changed',
            'user_id' => $user_id,
            'email'   => $u ? $u->user_email : null,
            'status'  => $status,
            'roles'   => $u ? $u->roles : [],
        ]) );
    }, 1, 2 );

});

add_action( 'set_user_role', function( $user_id, $role, $old_roles ) {
    if ( $role !== 'tutor_instructor' ) {
        return;
    }
    if ( get_user_meta( $user_id, '_amelia_employee_id', true ) ) {
        error_log('[AmeliaSync][FALLBACK] already synced, skipping');
        return;
    }
    error_log('[AmeliaSync][FALLBACK] tutor_instructor role assigned');
    inditech_create_amelia_employee( $user_id );
}, 10, 3 );

add_action( 'updated_user_meta', function ( $meta_id, $user_id, $meta_key, $meta_value ) {
    if ( $meta_key !== '_is_tutor_instructor' || $meta_value !== 'yes' ) { return; }
    if ( get_user_meta( $user_id, '_amelia_employee_id', true ) ) { return; }
    inditech_amelia_log('meta_trigger', ['user_id' => $user_id]);
    inditech_queue_mapping( $user_id, 'meta_is_tutor_instructor_yes' );
}, 10, 4 );


// Manual backfill (admin-only, on-demand).
add_action('admin_post_inditech_amelia_backfill', function () {
    if ( ! current_user_can('manage_options') ) { wp_die('Forbidden'); }
    check_admin_referer('inditech_amelia_backfill');

    $user_ids = get_users([
        'meta_key'     => '_is_tutor_instructor',
        'meta_compare' => 'EXISTS',
        'fields'       => 'ids',
    ]);

    foreach ($user_ids as $uid) {
        if ( get_user_meta($uid, '_amelia_employee_id', true) ) { continue; }
        inditech_create_amelia_employee($uid);
    }

    wp_safe_redirect( admin_url('users.php?inditech_backfill=done') );
    exit;
});

add_action( 'add_user_role', function( $user_id, $role ) {
    if ( $role === 'tutor_instructor' ) {
        inditech_amelia_log('fallback_add_role', ['user_id' => $user_id]);
        inditech_queue_mapping( $user_id, 'add_user_role' );
    }
}, 10, 2 );



/**
 * ================================
 * STUDENT -> AMELIA CUSTOMER (idempotent, single-user, no scan)
 * ================================
 */

add_action('inditech_amelia_customer_run_one', function ($user_id) {
    inditech_create_amelia_customer((int) $user_id);
}, 10, 1);

function inditech_queue_customer($user_id, $reason = '') {
    if (get_user_meta($user_id, '_amelia_customer_id', true)) {
        inditech_amelia_log('customer_queue_skip', ['user_id' => $user_id, 'reason' => 'already_has_customer_id']);
        return;
    }
    $lock = 'amelia_customer_queue_' . $user_id;
    if (get_transient($lock)) {
        inditech_amelia_log('customer_queue_dupe', ['user_id' => $user_id, 'reason' => $reason]);
        return;
    }
    set_transient($lock, 1, 300);
    if (!wp_next_scheduled('inditech_amelia_customer_run_one', [$user_id])) {
        wp_schedule_single_event(time() + 2, 'inditech_amelia_customer_run_one', [$user_id]);
    }
    inditech_amelia_log('customer_queue', ['user_id' => $user_id, 'reason' => $reason]);
}

function inditech_student_assign_roles($user_id) {
    $u = new WP_User($user_id);
    if (!in_array('student', (array) $u->roles, true)) {
        $u->add_role('student');
    }
    foreach (['amelia_customer', 'wpamelia-customer', 'customer'] as $slug) {
        if (get_role($slug) && !in_array($slug, (array) $u->roles, true)) {
            $u->add_role($slug);
            break;
        }
    }
}

function inditech_create_amelia_customer($user_id) {
    inditech_amelia_log('customer_enter', ['user_id' => $user_id]);

    if (!defined('AMELIA_API_KEY') || !AMELIA_API_KEY) {
        inditech_amelia_log('customer_error', ['user_id' => $user_id, 'reason' => 'Missing AMELIA_API_KEY']);
        return;
    }

    if (get_user_meta($user_id, '_amelia_customer_id', true)) {
        inditech_amelia_log('customer_skip', ['user_id' => $user_id, 'reason' => 'already_has_customer_id']);
        return;
    }

    $lock_key = 'amelia_customer_lock_' . $user_id;
    if (get_transient($lock_key)) { return; }
    set_transient($lock_key, 1, 60);

    $u = get_userdata($user_id);
    if (!$u) { delete_transient($lock_key); return; }

    $first = get_user_meta($user_id, 'first_name', true);
    $last  = get_user_meta($user_id, 'last_name', true);
    if (!$first || !$last) {
        $parts = preg_split('/\s+/', trim($u->display_name));
        $first = $first ?: ($parts[0] ?? 'Student');
        $last  = $last  ?: ($parts[1] ?? '-');
    }

    $phone      = (string) get_user_meta($user_id, 'billing_phone', true);
    $countryIso = strtolower((string) (get_user_meta($user_id, 'country_phone_iso', true) ?: 'in'));

    $payload = array_filter([
        'firstName'       => $first,
        'lastName'        => $last,
        'email'           => $u->user_email ?: null,
        'phone'           => $phone,
        'countryPhoneIso' => $countryIso,
        'externalId'      => (string) $user_id,
        'note'            => 'Student registration',
    ], static function($v) { return $v !== '' && $v !== null; });

    $endpoint = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/users/customers');
    $args = [
        'timeout' => 25,
        'headers' => [
            'Content-Type' => 'application/json',
            'Amelia'       => AMELIA_API_KEY,
        ],
        'body' => wp_json_encode($payload),
    ];

    inditech_amelia_log('customer_post_attempt', ['endpoint' => $endpoint, 'payload' => $payload]);

    $res  = wp_remote_post($endpoint, $args);
    $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
    $raw  = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
    $body = $raw ? json_decode($raw, true) : null;

    if (!is_wp_error($res) && $code >= 200 && $code < 300 && !empty($body['data']['user']['id'])) {
        $cid = (int) $body['data']['user']['id'];
        update_user_meta($user_id, '_amelia_customer_id', $cid);
        inditech_student_assign_roles($user_id);
        inditech_amelia_log('customer_created', ['user_id' => $user_id, 'customer_id' => $cid]);
        delete_transient($lock_key);
        return;
    }

    $message = is_array($body) ? ($body['message'] ?? '') :
               (is_wp_error($res) ? $res->get_error_message() : 'unknown');

    if ($code === 409 || stripos($message, 'email') !== false) {
        $found = inditech_find_amelia_customer_by_email($u->user_email);
        if ($found && !empty($found['id'])) {
            update_user_meta($user_id, '_amelia_customer_id', (int) $found['id']);
            inditech_student_assign_roles($user_id);
            inditech_amelia_log('customer_linked_existing', ['user_id' => $user_id, 'customer_id' => (int) $found['id']]);
            delete_transient($lock_key);
            return;
        }
    }

    inditech_amelia_log('customer_error', [
        'user_id' => $user_id,
        'code'    => $code,
        'message' => $message,
        'body'    => $body,
    ]);
    delete_transient($lock_key);
}

function inditech_find_amelia_customer_by_email($email) {
    $endpoint = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/users/customers&page=1&search=' . rawurlencode($email));
    $args = [
        'timeout' => 20,
        'headers' => [
            'Amelia' => AMELIA_API_KEY,
        ],
    ];
    $res  = wp_remote_get($endpoint, $args);
    $code = is_wp_error($res) ? 0 : (int) wp_remote_retrieve_response_code($res);
    $raw  = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
    $body = $raw ? json_decode($raw, true) : null;

    inditech_amelia_log('customer_get', ['endpoint' => $endpoint, 'code' => $code]);

    if ($code >= 200 && $code < 300 && !empty($body['data']['users'])) {
        foreach ($body['data']['users'] as $row) {
            if (isset($row['email']) && strtolower($row['email']) === strtolower($email)) {
                return $row;
            }
        }
    }
    return null;
}

add_action('tutor_after_student_register', function($user_id, $data = []) {
    inditech_student_assign_roles((int) $user_id);
    inditech_queue_customer((int) $user_id, 'tutor_after_student_register');
}, 10, 2);

add_action('tutor_after_student_signup', function($user_id, $data = []) {
    inditech_student_assign_roles((int) $user_id);
    inditech_queue_customer((int) $user_id, 'tutor_after_student_signup');
}, 10, 2);

add_action('add_user_role', function($user_id, $role) {
    if ($role === 'student') {
        inditech_student_assign_roles((int) $user_id);
        inditech_queue_customer((int) $user_id, 'add_user_role:student');
    }
}, 10, 2);

add_action('set_user_role', function($user_id, $role, $old_roles) {
    if ($role === 'student') {
        inditech_student_assign_roles((int) $user_id);
        inditech_queue_customer((int) $user_id, 'set_user_role:student');
    }
}, 10, 3);

add_action('user_register', function($user_id) {
    $ref = $_POST['_wp_http_referer'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $is_student_form = (isset($_POST['tutor_action']) && $_POST['tutor_action'] === 'student_register')
                    || ($ref && strpos($ref, '/student-registration/') !== false);

    if ($is_student_form) {
        inditech_student_assign_roles((int) $user_id);
        inditech_queue_customer((int) $user_id, 'user_register:student_registration_page');
    }
}, 999);