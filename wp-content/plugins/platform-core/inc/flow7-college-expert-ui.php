<?php
if (!defined('ABSPATH')) exit;

add_shortcode('platform_expert_college_requests', 'sc_final_modern_inbox');

function sc_final_modern_inbox() {
    if (!is_user_logged_in()) {
        return '<div style="padding:20px; color:red;">Please sign in to view this page.</div>';
    }
    
    if (!platform_core_user_is_expert() && !current_user_can('manage_options')) {
        return '<div style="padding:20px; color:red;">You need an Expert account to view this page.</div>';
    }
    
    global $wpdb;
    $current_user_id = get_current_user_id();
    
    $tbl_requests = $wpdb->prefix . 'platform_requests';
    $tbl_contracts = $wpdb->prefix . 'platform_contracts';

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT r.*, c.status AS contract_status, c.total_amount
        FROM $tbl_requests r
        LEFT JOIN $tbl_contracts c ON c.request_id = r.id
        WHERE r.expert_user_id = %d
        ORDER BY r.id DESC
    ", $current_user_id));

    $nonce = wp_create_nonce('wp_rest');
    $rest  = esc_url_raw(rest_url('platform-core/v1/college/response'));

    ob_start(); 
    ?>
    
    <style>
        #sc-modern-wrapper { max-width: 1200px !important; margin: 30px auto !important; font-family: 'Inter', sans-serif !important; }
        .sc-header-row { display: flex !important; justify-content: space-between !important; align-items: center !important; margin-bottom: 25px !important; }
        .sc-header-row h2 { font-size: 24px !important; font-weight: 700 !important; margin: 0 !important; }
        .sc-filter-bar { display: flex !important; gap: 12px !important; margin-bottom: 20px !important; align-items: center !important; flex-wrap: wrap !important; }
        .sc-search-input { flex: 1 !important; min-width: 250px !important; padding: 10px 16px !important; border: 1px solid #ddd !important; border-radius: 8px !important; font-size: 14px !important; }
        .sc-filter-select { padding: 10px 16px !important; border: 1px solid #ddd !important; border-radius: 8px !important; font-size: 14px !important; background: #fff !important; }
        .btn-calendar-toggle { background: #000 !important; color: #fff !important; padding: 10px 20px !important; border-radius: 8px !important; border: none !important; cursor: pointer !important; font-weight: 600 !important; }
        .sc-custom-tabs { display: flex !important; gap: 25px !important; border-bottom: 1px solid #eee !important; margin-bottom: 25px !important; }
        .sc-custom-tab { padding-bottom: 12px !important; font-size: 14px !important; color: #888 !important; cursor: pointer !important; font-weight: 500; position: relative; }
        .sc-custom-tab.active { color: #000 !important; font-weight: 700 !important; }
        .sc-custom-tab.active::after { content: ''; position: absolute; bottom: -1px; left: 0; width: 100%; height: 2px; background: #000; }
        .sc-session-card { display: flex !important; justify-content: space-between !important; align-items: center !important; background: #fff !important; border: 1px solid #eef0f2 !important; border-radius: 12px !important; padding: 20px 25px !important; margin-bottom: 15px !important; box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important; }
        .sc-card-left { display: flex !important; gap: 18px !important; align-items: center !important; }
        .sc-user-avatar { width: 52px !important; height: 52px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: bold !important; color: #fff !important; font-size: 18px !important; }
        .sc-user-info h4 { margin: 0 0 2px 0 !important; font-size: 17px !important; font-weight: 600 !important; }
        .sc-user-email { font-size: 12px !important; color: #2563eb !important; margin: 0 0 6px 0 !important; display: block !important; }
        .sc-user-info p { margin: 0 0 8px 0 !important; font-size: 13px !important; color: #777 !important; font-weight: 500 !important; }
        .sc-meta-info { display: flex !important; gap: 0 !important; font-size: 12px !important; color: #999 !important; align-items: center !important; }
        .sc-meta-divider { margin: 0 10px !important; color: #e2e8f0 !important; }
        .sc-card-right { text-align: right !important; }
        .sc-status-price-row { display: flex !important; align-items: center !important; gap: 15px !important; margin-bottom: 15px !important; justify-content: flex-end !important; }
        .sc-badge { padding: 5px 12px !important; border-radius: 20px !important; font-size: 11px !important; font-weight: 600 !important; text-transform: uppercase !important; }
        .badge-pending { background: #fef3c7 !important; color: #92400e !important; }
        .badge-pending-contract { background: #dbeafe !important; color: #1e40af !important; }
        .badge-confirmed { background: #dcfce7 !important; color: #166534 !important; }
        .badge-rejected { background: #fee2e2 !important; color: #991b1b !important; }
        .sc-amount { font-size: 19px !important; font-weight: 700 !important; }
        .sc-btn { padding: 9px 22px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 600 !important; cursor: pointer !important; border: 1px solid transparent !important; }
        .btn-decline-alt { background: #fff !important; color: #e11d48 !important; border-color: #fee2e2 !important; }
        .btn-accept-alt { background: #000 !important; color: #fff !important; }
        .btn-counter-alt { background: #fff !important; color: #2563eb !important; border-color: #dbeafe !important; }
        #sc-calendar-container { display: none; background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 20px; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #eee; border: 1px solid #eee; }
        .calendar-day-head { background: #f8f9fa; padding: 10px; text-align: center; font-weight: 600; font-size: 13px; }
        .calendar-day { background: #fff; min-height: 100px; padding: 8px; position: relative; }
        .calendar-event { font-size: 10px !important; padding: 4px; border-radius: 4px; margin-bottom: 4px; cursor: pointer; line-height: 1.2; }
        .sc-counter-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .sc-counter-modal.active { display: flex !important; }
        .sc-counter-form { background: white; padding: 30px; border-radius: 12px; width: 400px; }
    </style>

    <div id="sc-modern-wrapper">
        <div class="sc-header-row">
            <h2>Session Requests</h2>
            <button class="btn-calendar-toggle" id="js-toggle-calendar">
                <span id="toggle-text">Calendar View</span>
            </button>
        </div>

        <div class="sc-filter-bar">
            <input type="text" id="sc-search" class="sc-search-input" placeholder="Search by name, email, or topic...">
            <select id="sc-type-filter" class="sc-filter-select"><option value="all">All Types</option></select>
            <input type="date" id="sc-date-filter" class="sc-filter-select">
        </div>

        <div id="sc-tabs-wrapper">
            <div class="sc-custom-tabs" id="sc-tabs-container">
                <div class="sc-custom-tab active" data-filter="all">All (0)</div>
                <div class="sc-custom-tab" data-filter="pending">Pending (0)</div>
                <div class="sc-custom-tab" data-filter="confirmed">Confirmed (0)</div>
                <div class="sc-custom-tab" data-filter="declined">Declined (0)</div>
            </div>

            <div id="sc-list-view">
                <?php if (!$rows): ?>
                    <div style="padding:50px; text-align:center; background:#fff; border-radius:12px; color:#999;">No requests found.</div>
                <?php else: 
                    $avatar_colors = ['#FF5722', '#E91E63', '#9C27B0', '#673AB7', '#3F51B5', '#2196F3', '#009688', '#4CAF50', '#FF9800', '#795548'];
                    foreach ($rows as $r): 
                        $c_user = get_userdata($r->college_user_id);
                        $c_name = $c_user ? $c_user->display_name : 'Student';
                        $c_email = $c_user ? $c_user->user_email : '';
                        $status = $r->status; 
                        $color_idx = ord(substr($c_name, 0, 1)) % count($avatar_colors);
                        $date_val = date('Y-m-d', strtotime($r->proposed_start_iso));
                ?>
                    <div class="sc-session-card" 
                         data-id="<?php echo $r->id; ?>"
                         data-status="<?php echo $status; ?>"
                         data-price="<?php echo esc_attr($r->price_offer); ?>"
                         data-start-iso="<?php echo esc_attr($r->proposed_start_iso); ?>"
                         data-date-only="<?php echo $date_val; ?>" 
                         data-duration="<?php echo (int)$r->duration_minutes; ?>"
                         data-name="<?php echo esc_attr(strtolower($c_name)); ?>"
                         data-email="<?php echo esc_attr(strtolower($c_email)); ?>"
                         data-topic="<?php echo esc_attr(strtolower($r->topic)); ?>"
                         data-admin="<?php echo esc_attr($c_name); ?>">
                        
                        <div class="sc-card-left">
                            <div class="sc-user-avatar" style="background-color: <?php echo $avatar_colors[$color_idx]; ?> !important;">
                                <?php echo strtoupper(substr($c_name, 0, 1)); ?>
                            </div>
                            <div class="sc-user-info">
                                <h4><?php echo esc_html($c_name); ?></h4>
                                <span class="sc-user-email"><?php echo esc_html($c_email); ?></span>
                                <p><?php echo esc_html($r->topic); ?></p>
                                <div class="sc-meta-info">
                                    <span><?php echo date('M d, g:i A', strtotime($r->proposed_start_iso)); ?></span>
                                    <span class="sc-meta-divider">|</span>
                                    <span><?php echo $r->duration_minutes; ?> min</span>
                                </div>
                            </div>
                        </div>

                        <div class="sc-card-right">
                            <div class="sc-status-price-row">
                                <span class="sc-badge <?php 
                                    if($status == 'requested') echo 'badge-pending';
                                    elseif($status == 'pending_contract') echo 'badge-pending-contract';
                                    elseif($status == 'booked') echo 'badge-confirmed';
                                    elseif($status == 'rejected') echo 'badge-rejected';
                                ?>"><?php 
                                    if($status == 'requested') echo 'Pending';
                                    elseif($status == 'pending_contract') echo 'Pending Contract';
                                    elseif($status == 'booked') echo 'Confirmed';
                                    elseif($status == 'rejected') echo 'Declined';
                                ?></span>
                                <span class="sc-amount"><?php echo number_format($r->price_offer, 2); ?>Rs</span>
                            </div>
                            <div class="sc-btn-group">
                                <?php if ($status === 'requested'): ?>
                                    <button class="sc-btn btn-decline-alt js-sc-decline">Decline</button>
                                    <button class="sc-btn btn-counter-alt js-sc-counter">Counter Offer</button>
                                    <button class="sc-btn btn-accept-alt js-sc-accept">Accept</button>
                                <?php elseif ($status === 'pending_contract'): ?>
                                    <button class="sc-btn btn-decline-alt js-sc-decline">Decline</button>
                                    <button class="sc-btn btn-counter-alt js-sc-counter">Update Offer</button>
                                <?php elseif ($status === 'booked'): ?>
                                    <button class="sc-btn btn-view-alt" style="background:#fff; border:1px solid #ddd;">View Details</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div id="sc-calendar-container">
            <div class="calendar-header">
                <button id="cal-prev" style="border:1px solid #ddd; padding:5px 10px; border-radius:5px; background:#fff; cursor:pointer;">&lt;</button>
                <h3 id="cal-month-year" style="margin:0;"></h3>
                <button id="cal-next" style="border:1px solid #ddd; padding:5px 10px; border-radius:5px; background:#fff; cursor:pointer;">&gt;</button>
            </div>
            <div class="calendar-grid" id="cal-grid"></div>
        </div>
    </div>

    <div class="sc-counter-modal" id="sc-counter-modal">
        <div class="sc-counter-form">
            <h3 id="modal-title">Make Offer</h3>
            <form id="sc-counter-form-element">
                <input type="hidden" id="modal-rid">
                <div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;font-weight:600;">Price (Rs)*</label><input type="number" step="0.01" id="counter-price" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px;" required></div>
                <div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;font-weight:600;">Start Time*</label><input type="datetime-local" id="counter-start" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px;" required></div>
                <div style="margin-bottom:15px;"><label style="display:block;margin-bottom:5px;font-weight:600;">Duration (min)*</label><input type="number" id="counter-duration" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:5px;" required></div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" id="btn-cancel-counter" style="flex:1; padding:10px; border:none; border-radius:5px; cursor:pointer;">Cancel</button>
                    <button type="submit" style="flex:1; padding:10px; border:none; border-radius:5px; background:#000; color:#fff; cursor:pointer;">Send</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function(){
        const api_url = <?php echo wp_json_encode($rest); ?>;
        const api_nonce = <?php echo wp_json_encode($nonce); ?>;
        const cards = document.querySelectorAll('.sc-session-card');
        const tabs = document.querySelectorAll('.sc-custom-tab');
        const listView = document.getElementById('sc-list-view');
        const calendarView = document.getElementById('sc-calendar-container');
        const toggleBtn = document.getElementById('js-toggle-calendar');
        const dateFilterInput = document.getElementById('sc-date-filter');
        const searchInput = document.getElementById('sc-search');
        
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();

        toggleBtn.onclick = () => {
            if (calendarView.style.display === 'none' || !calendarView.style.display) {
                calendarView.style.display = 'block';
                document.getElementById('sc-tabs-wrapper').style.display = 'none';
                document.getElementById('toggle-text').innerText = 'List View';
                renderCalendar();
            } else {
                calendarView.style.display = 'none';
                document.getElementById('sc-tabs-wrapper').style.display = 'block';
                document.getElementById('toggle-text').innerText = 'Calendar View';
            }
        };

        function renderCalendar() {
            const grid = document.getElementById('cal-grid');
            const date = new Date(currentYear, currentMonth, 1);
            document.getElementById('cal-month-year').innerText = date.toLocaleString('default', { month: 'long', year: 'numeric' });
            grid.innerHTML = '';
            ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(d => grid.innerHTML += `<div class="calendar-day-head">${d}</div>`);
            for(let i=0; i<date.getDay(); i++) grid.innerHTML += `<div class="calendar-day"></div>`;
            const lastDate = new Date(currentYear, currentMonth + 1, 0).getDate();
            for(let d=1; d<=lastDate; d++) {
                const dayStr = `${currentYear}-${String(currentMonth + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                let eventsHtml = '';
                cards.forEach(card => {
                    if (card.dataset.dateOnly === dayStr) {
                        const status = card.dataset.status;
                        let bgColor = (status === 'booked') ? '#dcfce7' : (status === 'pending_contract' ? '#dbeafe' : '#fef3c7');
                        eventsHtml += `<div class="calendar-event" style="background:${bgColor}">${card.dataset.admin.split(' ')[0]}: ${card.dataset.topic}</div>`;
                    }
                });
                grid.innerHTML += `<div class="calendar-day"><span style="font-weight:600;">${d}</span>${eventsHtml}</div>`;
            }
        }

        document.getElementById('cal-prev').onclick = () => { currentMonth--; if(currentMonth < 0){ currentMonth=11; currentYear--; } renderCalendar(); };
        document.getElementById('cal-next').onclick = () => { currentMonth++; if(currentMonth > 11){ currentMonth=0; currentYear++; } renderCalendar(); };

        function filterRequests() {
            const activeTab = document.querySelector('.sc-custom-tab.active');
            const filterValue = activeTab ? activeTab.getAttribute('data-filter') : 'all';
            const term = searchInput.value.toLowerCase();
            const selDate = dateFilterInput.value;

            cards.forEach(card => {
                const s = card.dataset.status;
                const n = card.dataset.name;
                const e = card.dataset.email;
                const t = card.dataset.topic;
                const cd = card.dataset.dateOnly;

                let matchesStatus = (filterValue === 'all') || (filterValue === 'pending' && (s === 'requested' || s === 'pending_contract')) || (filterValue === 'confirmed' && s === 'booked') || (filterValue === 'declined' && s === 'rejected');
                const matchesSearch = n.includes(term) || e.includes(term) || t.includes(term);
                let matchesDate = !selDate || cd === selDate;

                if (matchesStatus && matchesSearch && matchesDate) {
                    card.style.setProperty('display', 'flex', 'important');
                } else {
                    card.style.setProperty('display', 'none', 'important');
                }
            });
            updateCounts();
        }

        function updateCounts() {
            const selDate = dateFilterInput.value;
            const term = searchInput.value.toLowerCase();
            const counts = { all: 0, pending: 0, confirmed: 0, declined: 0 };
            
            cards.forEach(card => {
                const s = card.dataset.status;
                const n = card.dataset.name;
                const e = card.dataset.email;
                const t = card.dataset.topic;
                const cd = card.dataset.dateOnly;
                
                const matchesSearch = n.includes(term) || e.includes(term) || t.includes(term);
                const matchesDate = !selDate || cd === selDate;

                if (matchesSearch && matchesDate) {
                    counts.all++;
                    if (s === 'requested' || s === 'pending_contract') counts.pending++;
                    else if (s === 'booked') counts.confirmed++;
                    else if (s === 'rejected') counts.declined++;
                }
            });
            
            tabs.forEach(tab => {
                const f = tab.getAttribute('data-filter');
                tab.innerText = `${f.charAt(0).toUpperCase() + f.slice(1)} (${counts[f]})`;
            });
        }

        tabs.forEach(tab => tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            filterRequests();
        }));

        searchInput.addEventListener('input', filterRequests);
        dateFilterInput.addEventListener('change', filterRequests);
        filterRequests();

        document.querySelectorAll('.js-sc-accept').forEach(btn => {
            btn.onclick = async () => {
                const card = btn.closest('.sc-session-card');
                if(!confirm('Accept this request?')) return;
                await fetch(api_url, { method: 'POST', headers: {'Content-Type': 'application/json', 'X-WP-Nonce': api_nonce}, body: JSON.stringify({request_id: card.dataset.id, action: 'accept', price: card.dataset.price, start_iso: card.dataset.startIso, duration_minutes: card.dataset.duration})});
                location.reload();
            };
        });
        
        const modal = document.getElementById('sc-counter-modal');
        document.querySelectorAll('.js-sc-counter').forEach(btn => {
            btn.onclick = () => {
                const card = btn.closest('.sc-session-card');
                document.getElementById('modal-rid').value = card.dataset.id;
                document.getElementById('counter-price').value = card.dataset.price;
                document.getElementById('counter-start').value = card.dataset.startIso.replace(' ', 'T');
                document.getElementById('counter-duration').value = card.dataset.duration;
                document.getElementById('modal-title').innerText = (card.dataset.status === 'pending_contract') ? 'Update Offer' : 'Counter Offer';
                modal.classList.add('active');
            };
        });
        document.getElementById('btn-cancel-counter').onclick = () => modal.classList.remove('active');
        document.getElementById('sc-counter-form-element').onsubmit = async (e) => {
            e.preventDefault();
            await fetch(api_url, { method: 'POST', headers: {'Content-Type': 'application/json', 'X-WP-Nonce': api_nonce}, body: JSON.stringify({request_id: document.getElementById('modal-rid').value, action: 'counter', price: document.getElementById('counter-price').value, start_iso: document.getElementById('counter-start').value.replace('T', ' '), duration_minutes: document.getElementById('counter-duration').value})});
            location.reload();
        };

        document.querySelectorAll('.js-sc-decline').forEach(btn => {
            btn.onclick = async () => {
                const card = btn.closest('.sc-session-card');
                if(!confirm('Decline this request?')) return;
                await fetch(api_url, { method: 'POST', headers: {'Content-Type': 'application/json', 'X-WP-Nonce': api_nonce}, body: JSON.stringify({request_id: card.dataset.id, action: 'reject'})});
                location.reload();
            };
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}