<?php
/**
 * File Name: upload_material.php
 * Tutorial uploads  -> wp_tutorial_uploads  (appointment_id)
 * Webinar uploads   -> wp_webinar_materials (event_id)
 *
 * Access restricted to users with the 'expert' role (or admins).
 */
if (!defined('ABSPATH')) exit;

/* -- icon helpers ---------------------------------------------------------- */
function _um_type_svg($type, $size = 16) {
    $s = (int)$size;
    $icons = [
        'Presentation Slides'        => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="#4338ca" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
        'Handouts / PDF Notes'       => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'Assignment / Homework'      => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'Reference Books / Articles' => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>',
        'Session Recordings'         => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
        'Practice Sheets'            => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'Exam Prep Materials'        => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
    ];
    return $icons[$type] ?? '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}
function _um_type_bg($type) {
    $map = [
        'Presentation Slides'=>'#eef2ff','Handouts / PDF Notes'=>'#fef2f2',
        'Assignment / Homework'=>'#fffbeb','Reference Books / Articles'=>'#ecfdf5',
        'Session Recordings'=>'#f5f3ff','Practice Sheets'=>'#ecfeff','Exam Prep Materials'=>'#fef2f2',
    ];
    return $map[$type] ?? '#f1f5f9';
}
function _um_ext_icon($ext, $size = 18) {
    $map = [
        'pdf'=>['#fef2f2','#ef4444'],'ppt'=>['#fff7ed','#ea580c'],'pptx'=>['#fff7ed','#ea580c'],
        'doc'=>['#eff6ff','#3b82f6'],'docx'=>['#eff6ff','#3b82f6'],'mp4'=>['#f5f3ff','#7c3aed'],
        'mov'=>['#f5f3ff','#7c3aed'],'jpg'=>['#f0fdf4','#16a34a'],'jpeg'=>['#f0fdf4','#16a34a'],
        'png'=>['#f0fdf4','#16a34a'],'xlsx'=>['#f0fdf4','#16a34a'],'csv'=>['#ecfeff','#0891b2'],
    ];
    [$bg,$clr] = $map[$ext] ?? ['#f1f5f9','#64748b'];
    $s = (int)$size;
    return [$bg, '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="'.$clr.'" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'];
}

/* -- main shortcode -------------------------------------------------------- */
function render_upload_material_ui() {
    global $wpdb;

    /* ------------------------------------------------------------------ */
    /* ACCESS CONTROL: experts and admins only                            */
    /* ------------------------------------------------------------------ */
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(get_permalink()));
        exit;
    }

    $current_user = wp_get_current_user();
    $is_expert    = in_array('expert', (array) $current_user->roles, true);
    $is_admin     = current_user_can('manage_options');

    if (!$is_expert && !$is_admin) {
        return '<div style="font-family:\'DM Sans\',system-ui,sans-serif;max-width:540px;margin:60px auto;background:#fff;'
             . 'border:1px solid #fee2e2;border-radius:14px;padding:40px 36px;text-align:center;">'
             . '<div style="width:56px;height:56px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;'
             . 'justify-content:center;margin:0 auto 16px;">'
             . '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#ef4444" stroke-width="2">'
             . '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>'
             . '</svg></div>'
             . '<h2 style="font-size:18px;font-weight:800;color:#0f172a;margin:0 0 8px;">Access Restricted</h2>'
             . '<p style="font-size:14px;color:#6b7280;margin:0 0 24px;line-height:1.6;">'
             . 'This page is only accessible to verified educators. If you are an expert, please log in with your educator account.</p>'
             . '<a href="' . esc_url(home_url()) . '" style="display:inline-block;padding:10px 24px;background:#0f172a;color:#fff;'
             . 'border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;">Go to Homepage</a>'
             . '</div>';
    }
    /* ------------------------------------------------------------------ */

    /* ensure tables */
    $tbl_tutorial = $wpdb->prefix . 'tutorial_uploads';
    $tbl_webinar  = $wpdb->prefix . 'webinar_materials';
    $cc = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE IF NOT EXISTS $tbl_tutorial (
        id mediumint(9) NOT NULL AUTO_INCREMENT, appointment_id int(11) NOT NULL,
        user_id bigint(20) NOT NULL, file_name varchar(255) NOT NULL,
        file_url varchar(255) NOT NULL, material_type varchar(100) NOT NULL,
        description text, upload_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)) $cc;");

    /* upload handler */
    $success = false;
    $error   = '';
    if (!empty($_POST['submit_upload_materials'])) {
        if (empty($_FILES['file']['name'])) {
            $error = 'Please select a file.';
        } else {
            $mtype  = sanitize_text_field($_POST['type'] ?? '');
            $desc   = sanitize_textarea_field($_POST['desc'] ?? '');
            $source = sanitize_text_field($_POST['source'] ?? 'tutorial');
            $udir   = wp_upload_dir();
            $tdir   = $udir['basedir'] . '/tutorial_materials/';
            if (!file_exists($tdir)) wp_mkdir_p($tdir);
            $orig   = basename($_FILES['file']['name']);
            $uname  = time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $orig);
            $furl   = $udir['baseurl'] . '/tutorial_materials/' . $uname;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $tdir . $uname)) {
                if ($source === 'webinar') {
                    $eid = intval($_POST['webinar_event'] ?? 0);
                    $wpdb->insert($tbl_webinar, [
                        'event_id'=>$eid,'title'=>$orig,'file_url'=>$furl,
                        'file_type'=>strtolower(pathinfo($orig,PATHINFO_EXTENSION)),
                        'material_type'=>$mtype,'description'=>$desc,
                        'uploaded_at'=>current_time('mysql'),'uploaded_by'=>get_current_user_id(),
                    ]);
                } else {
                    $aid = intval($_POST['session'] ?? 0);
                    $wpdb->insert($tbl_tutorial, [
                        'appointment_id'=>$aid,'user_id'=>get_current_user_id(),
                        'file_name'=>$orig,'file_url'=>$furl,
                        'material_type'=>$mtype,'description'=>$desc,
                        'upload_date'=>current_time('mysql'),
                    ]);
                }
                $success = true;
            } else { $error = 'File upload failed.'; }
        }
    }

    /* fetch data */
    $all_sessions = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.bookingStart, s.name as service_name
         FROM {$wpdb->prefix}amelia_appointments a
         INNER JOIN {$wpdb->prefix}amelia_services s ON a.serviceId = s.id
         WHERE a.status IN (%s,%s) AND s.name NOT LIKE %s
         ORDER BY a.bookingStart DESC",
        'approved','completed','%Remote College Class%'
    ));

    $all_webinars = $wpdb->get_results(
        "SELECT e.id, e.name, MIN(ep.periodStart) as periodStart
         FROM {$wpdb->prefix}amelia_events e
         INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId = e.id
         WHERE e.status NOT IN ('canceled','rejected','draft')
         GROUP BY e.id, e.name
         ORDER BY MIN(ep.periodStart) DESC"
    ) ?: [];

    $tut_uploads = $wpdb->get_results(
        "SELECT tu.appointment_id, COUNT(*) as cnt, s.name as service_name, a.bookingStart
         FROM {$tbl_tutorial} tu
         LEFT JOIN {$wpdb->prefix}amelia_appointments a ON tu.appointment_id = a.id
         LEFT JOIN {$wpdb->prefix}amelia_services s ON a.serviceId = s.id
         GROUP BY tu.appointment_id ORDER BY a.bookingStart DESC"
    );

    $web_uploads = $wpdb->get_results(
        "SELECT wm.event_id, COUNT(*) as cnt, e.name, MIN(ep.periodStart) as periodStart
         FROM {$tbl_webinar} wm
         LEFT JOIN {$wpdb->prefix}amelia_events e ON wm.event_id = e.id
         LEFT JOIN {$wpdb->prefix}amelia_events_periods ep ON ep.eventId = e.id
         GROUP BY wm.event_id, e.name
         ORDER BY MIN(ep.periodStart) DESC"
    ) ?: [];

    $material_types = ['Presentation Slides','Handouts / PDF Notes','Assignment / Homework',
        'Reference Books / Articles','Session Recordings','Practice Sheets','Exam Prep Materials'];

    /* -- HTML OUTPUT -------------------------------------------------------- */
    $out = '';

    /* success banner */
    if ($success) {
        $out .= '<div style="background:#ecfdf5;border:1px solid #6ee7b7;border-radius:10px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:10px;">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span style="font-weight:600;color:#065f46;">Material uploaded successfully!</span>
        </div>';
    }
    if ($error) {
        $out .= '<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:16px 20px;margin-bottom:24px;color:#991b1b;font-weight:600;">' . esc_html($error) . '</div>';
    }

    /* page wrapper */
    $out .= '<div id="um-wrap" style="font-family:\'DM Sans\',system-ui,sans-serif;color:#111827;max-width:1100px;margin:0 auto;padding:32px 20px;">';

    /* header */
    $out .= '<h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 6px;">Upload Tutorial Materials</h1>
             <p style="font-size:14px;color:#6b7280;margin:0 0 28px;">Upload files for your tutorial sessions or webinars.</p>';

    /* tab buttons */
    $out .= '<div style="display:flex;gap:8px;margin-bottom:28px;">
        <button onclick="umTab(\'tutorials\')" id="tab-btn-tutorials"
            style="padding:9px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:2px solid #4338ca;background:#4338ca;color:#fff;font-family:inherit;">
            Tutorials (' . count($all_sessions) . ')
        </button>
        <button onclick="umTab(\'webinars\')" id="tab-btn-webinars"
            style="padding:9px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:2px solid #e5e7eb;background:#fff;color:#6b7280;font-family:inherit;">
            Webinars (' . count($all_webinars) . ')
        </button>
    </div>';

    /* grid */
    $out .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">';

    /* == LEFT: upload form ================================================= */
    $out .= '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;">';
    $out .= '<h2 style="font-size:16px;font-weight:700;margin:0 0 20px;color:#0f172a;">Upload New File</h2>';
    $out .= '<form method="POST" enctype="multipart/form-data">';
    $out .= '<input type="hidden" name="source" id="um-source" value="tutorial">';

    /* tutorial session select */
    $out .= '<div id="um-field-session" style="margin-bottom:16px;">';
    $out .= '<label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#374151;">Select Tutorial Session</label>';
    $out .= '<select name="session" id="um-sel-session" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;color:#111827;font-family:inherit;">';
    $out .= '<option value="">&mdash; Choose Session &mdash;</option>';
    foreach ($all_sessions as $s) {
        $out .= '<option value="' . (int)$s->id . '">' . esc_html($s->service_name . ' (' . date_i18n('M j, Y', strtotime($s->bookingStart)) . ')') . '</option>';
    }
    $out .= '</select></div>';

    /* webinar select */
    $out .= '<div id="um-field-webinar" style="margin-bottom:16px;display:none;">';
    $out .= '<label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#374151;">Select Webinar</label>';
    $out .= '<select name="webinar_event" id="um-sel-webinar" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;color:#111827;font-family:inherit;">';
    $out .= '<option value="">&mdash; Choose Webinar &mdash;</option>';
    foreach ($all_webinars as $w) {
        $lbl = (strtotime($w->periodStart) < time() ? '(Past) ' : '(Upcoming) ')
             . $w->name . ' &mdash; ' . date_i18n('M j, Y', strtotime($w->periodStart));
        $out .= '<option value="' . (int)$w->id . '">' . esc_html(html_entity_decode($lbl)) . '</option>';
    }
    $out .= '</select></div>';

    /* material type */
    $out .= '<div style="margin-bottom:16px;">';
    $out .= '<label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#374151;">Material Type</label>';
    $out .= '<select name="type" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;color:#111827;font-family:inherit;">';
    foreach ($material_types as $mt) {
        $out .= '<option value="' . esc_attr($mt) . '">' . esc_html($mt) . '</option>';
    }
    $out .= '</select></div>';

    /* file drop */
    $out .= '<div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#374151;">File</label>
        <label style="display:block;border:2px dashed #d1d5db;border-radius:10px;padding:32px 20px;text-align:center;background:#f9fafb;cursor:pointer;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="display:block;margin:0 auto 8px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <span id="um-file-label" style="font-size:14px;font-weight:600;color:#6b7280;">Click to choose file</span>
            <input type="file" name="file" id="um-file-input" style="display:none;" onchange="document.getElementById(\'um-file-label\').textContent=this.files[0]?this.files[0].name:\'Click to choose file\'">
        </label>
    </div>';

    /* description */
    $out .= '<div style="margin-bottom:20px;">
        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#374151;">Notes (optional)</label>
        <textarea name="desc" rows="3" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;" placeholder="Add notes about this material..."></textarea>
    </div>';

    $out .= '<button type="submit" name="submit_upload_materials" value="1"
        style="width:100%;background:#111827;color:#fff;padding:13px;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;">
        Upload Material
    </button>';

    $out .= '</form></div>'; /* end left card */

    /* == RIGHT: viewer ===================================================== */
    $out .= '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;">';

    /* tutorial viewer */
    $out .= '<div id="um-view-tutorials">';
    $out .= '<h2 style="font-size:16px;font-weight:700;margin:0 0 16px;color:#0f172a;">View Materials by Session</h2>';
    $out .= '<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;">';
    $out .= '<input type="hidden" name="tab" value="tutorials">';
    $out .= '<select name="view_session" style="flex:1;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;color:#111827;font-family:inherit;">';
    $out .= '<option value="">&mdash; Select session &mdash;</option>';
    $view_session = isset($_GET['view_session']) ? intval($_GET['view_session']) : 0;
    foreach ($all_sessions as $s) {
        $sel = $view_session === (int)$s->id ? ' selected' : '';
        $out .= '<option value="' . (int)$s->id . '"' . $sel . '>' . esc_html($s->service_name . ' (' . date_i18n('M j, Y', strtotime($s->bookingStart)) . ')') . '</option>';
    }
    $out .= '</select>';
    $out .= '<button type="submit" style="padding:10px 18px;background:#4338ca;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">View</button>';
    $out .= '</form>';

    if ($view_session) {
        $mats = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tbl_tutorial WHERE appointment_id = %d ORDER BY upload_date DESC", $view_session));
        if (empty($mats)) {
            $out .= '<p style="text-align:center;color:#9ca3af;padding:24px 0;">No materials for this session yet.</p>';
        } else {
            $out .= '<p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px;">' . count($mats) . ' file' . (count($mats)!==1?'s':'') . ' found</p>';
            foreach ($mats as $m) {
                $ext = strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION));
                [$ibg,$isvg] = _um_ext_icon($ext);
                $tbg = _um_type_bg($m->material_type);
                $out .= '<div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;">';
                $out .= '<div style="width:38px;height:38px;border-radius:8px;background:'.$ibg.';display:flex;align-items:center;justify-content:center;flex-shrink:0;">'.$isvg.'</div>';
                $out .= '<div style="flex:1;min-width:0;">';
                $out .= '<div style="font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'.esc_html($m->file_name).'</div>';
                $out .= '<div style="margin-top:3px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
                $out .= '<span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:4px;background:'.$tbg.';color:#374151;">'.esc_html($m->material_type).'</span>';
                $out .= '<span style="font-size:11px;color:#9ca3af;">'.date_i18n('M j, Y', strtotime($m->upload_date)).'</span>';
                $out .= '</div></div>';
                $out .= '<a href="'.esc_url($m->file_url).'" target="_blank" style="width:32px;height:32px;border-radius:7px;background:#f1f5f9;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;text-decoration:none;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.5"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>';
                $out .= '</div>';
            }
        }
    }

    /* sessions with uploads list */
    if (!empty($tut_uploads)) {
        $out .= '<div style="margin-top:20px;border-top:1px solid #f1f5f9;padding-top:16px;">';
        $out .= '<p style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.5px;margin-bottom:10px;">SESSIONS WITH UPLOADS</p>';
        foreach ($tut_uploads as $r) {
            $lbl = ($r->service_name ?: 'Session') . ' &mdash; ' . date_i18n('M j, Y', strtotime($r->bookingStart));
            $out .= '<a href="?tab=tutorials&view_session='.(int)$r->appointment_id.'" style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:6px;text-decoration:none;">';
            $out .= '<span style="font-size:13px;font-weight:600;color:#374151;">'.esc_html(html_entity_decode($lbl)).'</span>';
            $out .= '<span style="font-size:11px;font-weight:700;color:#4338ca;background:#eef2ff;padding:2px 9px;border-radius:20px;">'.(int)$r->cnt.'</span>';
            $out .= '</a>';
        }
        $out .= '</div>';
    }
    $out .= '</div>'; /* end tutorial viewer */

    /* webinar viewer */
    $out .= '<div id="um-view-webinars" style="display:none;">';
    $out .= '<h2 style="font-size:16px;font-weight:700;margin:0 0 16px;color:#0f172a;">View Materials by Webinar</h2>';
    $out .= '<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;">';
    $out .= '<input type="hidden" name="tab" value="webinars">';
    $out .= '<select name="view_event" style="flex:1;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;color:#111827;font-family:inherit;">';
    $out .= '<option value="">&mdash; Select webinar &mdash;</option>';
    $view_event = isset($_GET['view_event']) ? intval($_GET['view_event']) : 0;
    foreach ($all_webinars as $w) {
        $lbl = (strtotime($w->periodStart) < time() ? '(Past) ' : '(Upcoming) ') . $w->name . ' - ' . date_i18n('M j, Y', strtotime($w->periodStart));
        $sel = $view_event === (int)$w->id ? ' selected' : '';
        $out .= '<option value="' . (int)$w->id . '"' . $sel . '>' . esc_html($lbl) . '</option>';
    }
    $out .= '</select>';
    $out .= '<button type="submit" style="padding:10px 18px;background:#4338ca;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">View</button>';
    $out .= '</form>';

    if ($view_event) {
        $mats = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tbl_webinar WHERE event_id = %d ORDER BY uploaded_at DESC", $view_event));
        if (empty($mats)) {
            $out .= '<p style="text-align:center;color:#9ca3af;padding:24px 0;">No materials for this webinar yet.</p>';
        } else {
            $out .= '<p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px;">' . count($mats) . ' file' . (count($mats)!==1?'s':'') . ' found</p>';
            foreach ($mats as $m) {
                $ext = strtolower(pathinfo($m->title, PATHINFO_EXTENSION));
                [$ibg,$isvg] = _um_ext_icon($ext);
                $tbg = _um_type_bg($m->material_type);
                $out .= '<div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;">';
                $out .= '<div style="width:38px;height:38px;border-radius:8px;background:'.$ibg.';display:flex;align-items:center;justify-content:center;flex-shrink:0;">'.$isvg.'</div>';
                $out .= '<div style="flex:1;min-width:0;">';
                $out .= '<div style="font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'.esc_html($m->title).'</div>';
                $out .= '<div style="margin-top:3px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
                $out .= '<span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:4px;background:'.$tbg.';color:#374151;">'.esc_html($m->material_type).'</span>';
                $out .= '<span style="font-size:11px;color:#9ca3af;">'.date_i18n('M j, Y g:i A', strtotime($m->uploaded_at)).'</span>';
                $out .= '</div></div>';
                $out .= '<a href="'.esc_url($m->file_url).'" target="_blank" style="width:32px;height:32px;border-radius:7px;background:#f1f5f9;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;text-decoration:none;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2.5"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>';
                $out .= '</div>';
            }
        }
    }

    if (!empty($web_uploads)) {
        $out .= '<div style="margin-top:20px;border-top:1px solid #f1f5f9;padding-top:16px;">';
        $out .= '<p style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.5px;margin-bottom:10px;">WEBINARS WITH UPLOADS</p>';
        foreach ($web_uploads as $r) {
            $lbl = ($r->name ?: 'Webinar #'.$r->event_id) . ($r->periodStart ? ' - ' . date_i18n('M j, Y', strtotime($r->periodStart)) : '');
            $out .= '<a href="?tab=webinars&view_event='.(int)$r->event_id.'" style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:6px;text-decoration:none;">';
            $out .= '<span style="font-size:13px;font-weight:600;color:#374151;">'.esc_html($lbl).'</span>';
            $out .= '<span style="font-size:11px;font-weight:700;color:#4338ca;background:#eef2ff;padding:2px 9px;border-radius:20px;">'.(int)$r->cnt.'</span>';
            $out .= '</a>';
        }
        $out .= '</div>';
    }
    $out .= '</div>'; /* end webinar viewer */

    $out .= '</div>'; /* end right card */
    $out .= '</div>'; /* end grid */
    $out .= '</div>'; /* end um-wrap */

    /* read active tab from GET (for initial state) */
    $init_tab = (isset($_GET['tab']) && $_GET['tab'] === 'webinars') ? 'webinars' : 'tutorials';

    $out .= '<script>
function umTab(tab) {
    var isTut = tab === "tutorials";
    document.getElementById("um-field-session").style.display  = isTut ? "block" : "none";
    document.getElementById("um-field-webinar").style.display  = isTut ? "none"  : "block";
    document.getElementById("um-source").value = isTut ? "tutorial" : "webinar";
    document.getElementById("um-view-tutorials").style.display = isTut ? "block" : "none";
    document.getElementById("um-view-webinars").style.display  = isTut ? "none"  : "block";
    var active   = {background:"#4338ca",color:"#fff",border:"2px solid #4338ca"};
    var inactive = {background:"#fff",color:"#6b7280",border:"2px solid #e5e7eb"};
    var btnTut = document.getElementById("tab-btn-tutorials");
    var btnWeb = document.getElementById("tab-btn-webinars");
    Object.assign(btnTut.style, isTut ? active : inactive);
    Object.assign(btnWeb.style, isTut ? inactive : active);
}
document.addEventListener("DOMContentLoaded", function(){ umTab("' . $init_tab . '"); });
</script>';

    return $out;
}