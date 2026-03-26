<?php
if (!defined('ABSPATH')) exit;

add_shortcode('availability_form', 'handle_availability_logic');

function handle_availability_logic() {
    if (isset($_POST['submit_availability'])) {
        return render_form_results($_POST);
    }
    return render_availability_form_ui();
}

function render_availability_form_ui() {
    $current_user = wp_get_current_user();
    $user_display_name = $current_user->exists() ? $current_user->display_name : 'User';

    $get_times = function($default = '8:00 AM') {
        $out = '';
        for ($i = 0; $i < 24; $i++) {
            $h = ($i === 0 || $i === 12) ? 12 : $i % 12;
            $p = ($i < 12) ? 'AM' : 'PM';
            $t = "$h:00 $p";
            $sel = ($t === $default) ? 'selected' : '';
            $out .= "<option value='$t' $sel>$t</option>";
        }
        return $out;
    };

    ob_start(); ?>
    <div class="p-dash-navbar-new">
        <div class="navbar-left"><div class="nav-logo">LOGO</div></div>
        <div class="navbar-right">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#a0aec0" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <div class="user-profile">
                <span class="user-name"><?php echo esc_html($user_display_name); ?></span>
                <div class="user-avatar"></div>
            </div>
        </div>
    </div>

    <div class="availability-container">
        <form method="post" id="availability-form">
            <div class="form-header">
                <h2>Update Availability</h2>
                <p>Set specific schedules or sync across multiple days.</p>
            </div>

            <div class="form-card">
                <div class="card-header-flex">
                    <h3 class="weekly-schedule-title">Weekly Schedule</h3>
                    <button type="button" class="google-sync-btn">G Connect Google Calendar</button>
                </div>
                
                <div class="day-selector">
                    <?php 
                    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    foreach ($days as $day) : ?>
                        <label class="day-item">
                            <input type="checkbox" name="active_days[]" value="<?php echo $day; ?>" class="day-toggle-input" data-day="<?php echo strtolower($day); ?>">
                            <div class="day-box">
                                <div class="custom-tick-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17L4 12" stroke="#1a202c" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <span class="day-label"><?php echo $day; ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="time-panels-container">
                    <?php foreach ($days as $day) : $slug = strtolower($day); ?>
                        <div class="day-time-panel" id="panel-<?php echo $slug; ?>" data-panel-day="<?php echo $slug; ?>" style="display: none;">
                            <div class="panel-header-sync">
                                <h4 class="panel-day-title"><?php echo $day; ?> Settings</h4>
                                <div class="sync-actions">
                                    <button type="button" class="sync-days-link" onclick="applyToAllDays('<?php echo $slug; ?>')">Apply to all active</button>
                                    <button type="button" class="sync-days-link" onclick="toggleSelectSpecific('<?php echo $slug; ?>')">Apply to selected</button>
                                </div>
                            </div>

                            <div id="select-specific-<?php echo $slug; ?>" class="specific-days-list" style="display:none;">
                                <p>Select days to copy to:</p>
                                <div class="specific-checkbox-grid">
                                    <?php foreach ($days as $d) : if($d == $day) continue; ?>
                                        <label><input type="checkbox" class="target-day-check" data-target="<?php echo strtolower($d); ?>"> <?php echo $d; ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn-apply-small" onclick="applyToSelectedDays('<?php echo $slug; ?>')">Confirm Copy</button>
                            </div>

                            <div class="input-row">
                                <div class="input-group"><label>Start Time</label><select name="schedule[<?php echo $slug; ?>][start]" class="start-time-select"><?php echo $get_times('8:00 AM'); ?></select></div>
                                <div class="input-group"><label>End Time</label><select name="schedule[<?php echo $slug; ?>][end]" class="end-time-select"><?php echo $get_times('4:00 PM'); ?></select></div>
                            </div>
                            <div class="input-row" style="margin-top:15px;">
                                <div class="input-group"><label>Break Start</label><select name="schedule[<?php echo $slug; ?>][break_start]" class="break-start-select"><?php echo $get_times('12:00 PM'); ?></select></div>
                                <div class="input-group"><label>Break Duration</label>
                                    <select name="schedule[<?php echo $slug; ?>][break_dur]" class="break-dur-select">
                                        <option value="15 minutes">15 minutes</option>
                                        <option value="30 minutes" selected>30 minutes</option>
                                        <option value="1 hour">1 hour</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-card">
                <h3 class="session-settings-title">Session Settings</h3>
                <div class="input-row">
                    <div class="input-group"><label>Session Duration</label><select name="global_duration"><option>1 hour</option><option>45 minutes</option></select></div>
                    <div class="input-group"><label>Buffer Time</label><select name="global_buffer"><option>15 minutes</option><option>10 minutes</option></select></div>
                </div>
                <div class="visibility-group" style="margin-top: 20px;">
                    <label>Calendar Visibility</label>
                    <div class="radio-row">
                        <label class="radio-item"><input type="radio" name="visibility" value="Public" checked> Public</label>
                        <label class="radio-item"><input type="radio" name="visibility" value="Private"> Private</label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="window.location.reload();">Cancel</button>
                <button type="submit" name="submit_availability" class="btn-submit">Submit</button>
            </div>
        </form>
    </div>

    <script>
    document.querySelectorAll('.day-toggle-input').forEach(input => {
        input.addEventListener('change', function() {
            document.getElementById('panel-' + this.getAttribute('data-day')).style.display = this.checked ? 'block' : 'none';
        });
    });

    function toggleSelectSpecific(slug) {
        const div = document.getElementById('select-specific-' + slug);
        div.style.display = div.style.display === 'none' ? 'block' : 'none';
    }

    function getSourceData(panel) {
        return {
            start: panel.querySelector('.start-time-select').value,
            end: panel.querySelector('.end-time-select').value,
            bStart: panel.querySelector('.break-start-select').value,
            bDur: panel.querySelector('.break-dur-select').value
        };
    }

    function copyDataToPanel(panel, data) {
        panel.querySelector('.start-time-select').value = data.start;
        panel.querySelector('.end-time-select').value = data.end;
        panel.querySelector('.break-start-select').value = data.bStart;
        panel.querySelector('.break-dur-select').value = data.bDur;
    }

    function applyToAllDays(sourceDay) {
        const data = getSourceData(document.getElementById('panel-' + sourceDay));
        document.querySelectorAll('.day-time-panel').forEach(panel => {
            if (panel.style.display === 'block') copyDataToPanel(panel, data);
        });
        alert('Applied to all active days!');
    }

    function applyToSelectedDays(sourceDay) {
        const data = getSourceData(document.getElementById('panel-' + sourceDay));
        const specificDiv = document.getElementById('select-specific-' + sourceDay);
        const selectedTargets = specificDiv.querySelectorAll('.target-day-check:checked');

        if(selectedTargets.length === 0) {
            alert('Please select at least one day.');
            return;
        }

        selectedTargets.forEach(checkbox => {
            const targetSlug = checkbox.getAttribute('data-target');
            const toggle = document.querySelector(`.day-toggle-input[data-day="${targetSlug}"]`);
            if(!toggle.checked) {
                toggle.checked = true;
                toggle.dispatchEvent(new Event('change'));
            }
            copyDataToPanel(document.getElementById('panel-' + targetSlug), data);
        });
        specificDiv.style.display = 'none';
        alert('Applied to selected days!');
    }
    </script>
    <?php
    return ob_get_clean();
}

function render_form_results($data) {
    ob_start(); ?>
    <div class="availability-container">
        <div class="form-card results-view">
            <h2>Schedule Summary</h2>
            <table class="results-table" style="width:100%; border-collapse: collapse; margin-top:20px;">
                <thead>
                    <tr style="background:#f8fafc; text-align:left;">
                        <th style="padding:12px; border:1px solid #e2e8f0;">Day</th>
                        <th style="padding:12px; border:1px solid #e2e8f0;">Hours</th>
                        <th style="padding:12px; border:1px solid #e2e8f0;">Break Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if(!empty($data['active_days'])) {
                        foreach($data['active_days'] as $day) {
                            $slug = strtolower($day);
                            $s = $data['schedule'][$slug];
                            echo "<tr>
                                <td style='padding:12px; border:1px solid #e2e8f0;'><strong>$day</strong></td>
                                <td style='padding:12px; border:1px solid #e2e8f0;'>{$s['start']} - {$s['end']}</td>
                                <td style='padding:12px; border:1px solid #e2e8f0;'>Start: {$s['break_start']} ({$s['break_dur']})</td>
                            </tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
            <div style="margin-top:20px;">
                <p><strong>Session Duration:</strong> <?php echo esc_html($data['global_duration']); ?></p>
                <p><strong>Buffer Time:</strong> <?php echo esc_html($data['global_buffer']); ?></p>
                <p><strong>Visibility:</strong> <?php echo esc_html($data['visibility']); ?></p>
            </div>
            <div style="text-align:center; margin-top:30px;"><a href="<?php echo get_permalink(); ?>" class="btn-submit" style="text-decoration:none;">Go Back</a></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}