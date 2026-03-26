<?php
if (!defined('ABSPATH')) exit;

/**
 * Shared Amelia + Google helper functions
 * Load this once before other flow files to avoid duplicate definitions.
 */

if (!function_exists('platform_core_amelia_api_headers')) {
    function platform_core_amelia_api_headers() {
        return [
            'Content-Type' => 'application/json',
            'Amelia'       => 'u1FWtL7dfRf0YuYN75twgMot2X4lXSuzwpJJuBajraYm'       ];
    }
}

if (!function_exists('platform_core_amelia_api_base')) {
    function platform_core_amelia_api_base($path) {
        // All Amelia API calls go through wpamelia_api proxy
        return admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1' . $path);
    }
}

// Lightweight Amelia logger (only logs when WP_DEBUG is true)
if (!function_exists('platform_core_log_amelia')) {
    function platform_core_log_amelia($label, $data) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $s = $label . ' : ' . (is_scalar($data) ? $data : wp_json_encode($data));
            error_log($s);
        }
    }
}

// Convert various WP datetime strings -> ISO8601 with timezone offset required by Amelia.
if (!function_exists('platform_core_amelia_format_datetime')) {
    function platform_core_amelia_format_datetime($wp_datetime_str) {
        if (empty($wp_datetime_str)) return null;
        $ts = strtotime($wp_datetime_str);
        if ($ts === false) return null;
        // WP timezone-aware DateTime object
        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
            $dt = new DateTime('@' . $ts);
            $dt->setTimezone($tz);
            return $dt->format('Y-m-d\TH:i:sP'); // e.g. 2025-12-10T14:30:00+05:30
        } catch (Exception $e) {
            return gmdate('Y-m-d\TH:i:s\Z', $ts);
        }
    }
}

// List all services (normalized). Returns array of services or [] on failure.
if (!function_exists('platform_core_amelia_list_services')) {
    function platform_core_amelia_list_services() {
        $url = platform_core_amelia_api_base('/services');
        $res = wp_remote_get($url, ['headers' => platform_core_amelia_api_headers(), 'timeout' => 20]);
        if (is_wp_error($res)) return [];
        $body = wp_remote_retrieve_body($res);
        platform_core_log_amelia('GET /services response', $body);
        $decoded = json_decode($body, true);
        // Support multiple shapes:
        if (isset($decoded['data']['items'])) return $decoded['data']['items'];
        if (isset($decoded['data']['services'])) return $decoded['data']['services'];
        if (isset($decoded['data'])) return $decoded['data'];
        if (is_array($decoded)) return $decoded;
        return [];
    }
}

// Get single service (normalized). Returns service array or [].
if (!function_exists('platform_core_amelia_get_service')) {
    function platform_core_amelia_get_service($id) {
        $url = platform_core_amelia_api_base('/services/' . (int)$id);
        $res = wp_remote_get($url, ['headers' => platform_core_amelia_api_headers(), 'timeout' => 20]);
        if (is_wp_error($res)) return [];
        $body = wp_remote_retrieve_body($res);
        platform_core_log_amelia('GET /services/' . (int)$id . ' response', $body);
        $decoded = json_decode($body, true);
        // Various shapes
        if (!empty($decoded['data']['service'])) return $decoded['data']['service'];
        if (!empty($decoded['data'])) return $decoded['data'];
        if (is_array($decoded)) return $decoded;
        return [];
    }
}

// Google Calendar helper: Update event ID in appointment custom fields
if (!function_exists('platform_core_google_update_event_id')) {
    function platform_core_google_update_event_id($appointment_id, $event_id) {
        if (empty($appointment_id) || empty($event_id)) return false;
        
        $url = platform_core_amelia_api_base('/appointments/' . (int)$appointment_id);
        $payload = [
            'customFields' => [
                'google_event_id' => $event_id
            ]
        ];
        
        $res = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => platform_core_amelia_api_headers(),
            'body'    => wp_json_encode($payload),
            'timeout' => 20
        ]);
        
        if (is_wp_error($res)) {
            platform_core_log_amelia('Google update event ID error', $res->get_error_message());
            return false;
        }
        
        platform_core_log_amelia('Google update event ID for appointment ' . $appointment_id, $event_id);
        return true;
    }
}

// Google Calendar helper: Delete/clear event ID from appointment custom fields
if (!function_exists('platform_core_google_delete_event_id')) {
    function platform_core_google_delete_event_id($appointment_id) {
        if (empty($appointment_id)) return false;
        
        $url = platform_core_amelia_api_base('/appointments/' . (int)$appointment_id);
        $payload = [
            'customFields' => [
                'google_event_id' => ''
            ]
        ];
        
        $res = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => platform_core_amelia_api_headers(),
            'body'    => wp_json_encode($payload),
            'timeout' => 20
        ]);
        
        if (is_wp_error($res)) {
            platform_core_log_amelia('Google delete event ID error', $res->get_error_message());
            return false;
        }
        
        platform_core_log_amelia('Google deleted event ID for appointment ' . $appointment_id, 'cleared');
        return true;
    }
}