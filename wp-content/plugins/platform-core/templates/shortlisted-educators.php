<?php if (!defined('ABSPATH')) exit; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@500;600&display=swap');

/* -- Kill theme gap below footer -- */
body,
#page,
.site,
.site-content,
#content,
.wp-site-blocks,
main.wp-block-group,
.is-layout-flow {
    padding-bottom: 0 !important;
    margin-bottom:  0 !important;
}

.sl-wrap {
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

/* -- Reset scoped to wrapper -- */
.sl-wrap *, .sl-wrap *::before, .sl-wrap *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.sl-wrap {
    --ink:        #0c1222;
    --ink2:       #4a5568;
    --ink3:       #94a3b8;
    --surface:    #ffffff;
    --bg:         #f4f6fb;
    --border:     #e8edf5;
    --accent:     #4338ca;
    --accent-lt:  #eef2ff;
    --green:      #059669;
    --green-lt:   #ecfdf5;
    --red:        #dc2626;
    --red-lt:     #fef2f2;
    --amber:      #d97706;
    --navy:       #0f172a;
    --gold:       #f59e0b;
    --font:       'DM Sans', sans-serif;
    --font-serif: 'Playfair Display', serif;
    --r:          14px;
    --shadow:     0 1px 3px rgba(0,0,0,.06), 0 4px 20px rgba(0,0,0,.05);
    --shadow-lg:  0 8px 30px rgba(0,0,0,.10), 0 2px 8px rgba(0,0,0,.06);

    font-family: var(--font);
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
}

/* -- Page shell -- */
.sl-page {
    max-width: 1100px;
    margin: 0 auto;
    padding: 36px 28px 64px;
}

/* -- Page header -- */
.sl-page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 32px;
    flex-wrap: wrap;
}

.sl-page-title {
    font-family: var(--font-serif);
    font-size: 30px;
    font-weight: 600;
    color: var(--ink);
    letter-spacing: -.4px;
    line-height: 1.2;
}

.sl-page-title span {
    display: block;
    font-family: var(--font);
    font-size: 13px;
    font-weight: 400;
    color: var(--ink3);
    letter-spacing: 0;
    margin-top: 5px;
}

/* -- Search bar -- */
.sl-search-row {
    display: flex;
    gap: 10px;
    align-items: center;
}

.sl-search-box {
    position: relative;
    flex: 1;
    min-width: 240px;
}

.sl-search-box svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--ink3);
    pointer-events: none;
    width: 16px;
    height: 16px;
}

.sl-search-box input {
    width: 100%;
    padding: 10px 14px 10px 40px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-family: var(--font);
    font-size: 14px;
    color: var(--ink);
    background: var(--surface);
    outline: none;
    transition: border-color .18s, box-shadow .18s;
}

.sl-search-box input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(67,56,202,.10);
}

.sl-search-box input::placeholder { color: var(--ink3); }

.sl-search-btn {
    padding: 10px 22px;
    background: var(--navy);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-family: var(--font);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s, transform .15s;
    white-space: nowrap;
}

.sl-search-btn:hover { background: #1e293b; transform: translateY(-1px); }

/* -- Count chip -- */
.sl-count-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--accent);
    background: var(--accent-lt);
    padding: 4px 12px;
    border-radius: 20px;
    margin-bottom: 16px;
}

/* -- Grid of cards -- */
.sl-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* -- Educator card -- */
.sl-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    box-shadow: var(--shadow);
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0;
    transition: box-shadow .22s, transform .22s, border-color .22s;
    overflow: hidden;
    animation: sl-fadein .35s ease both;
}

.sl-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
    border-color: #d0d9f0;
}

@keyframes sl-fadein {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.sl-card:nth-child(1) { animation-delay: .04s; }
.sl-card:nth-child(2) { animation-delay: .08s; }
.sl-card:nth-child(3) { animation-delay: .12s; }
.sl-card:nth-child(4) { animation-delay: .16s; }
.sl-card:nth-child(5) { animation-delay: .20s; }
.sl-card:nth-child(6) { animation-delay: .24s; }

/* Left accent strip */
.sl-card-strip {
    width: 4px;
    align-self: stretch;
    background: var(--border);
    transition: background .2s;
}
.sl-card.is-available .sl-card-strip { background: var(--green); }

/* Main card body */
.sl-card-body {
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
}

/* Avatar */
.sl-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.sl-avatar {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border);
    display: block;
}

.sl-avail-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    border: 2px solid #fff;
    background: var(--border);
}
.sl-card.is-available .sl-avail-dot { background: var(--green); }

/* Info block */
.sl-info {
    flex: 1;
    min-width: 0;
}

.sl-name {
    font-size: 16px;
    font-weight: 700;
    color: var(--ink);
    letter-spacing: -.2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sl-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 4px;
    flex-wrap: wrap;
}

.sl-meta-item {
    font-size: 12px;
    color: var(--ink3);
    display: flex;
    align-items: center;
    gap: 4px;
}

.sl-meta-item svg {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
}

.sl-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.sl-tag {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    background: var(--accent-lt);
    color: var(--accent);
    letter-spacing: .1px;
}

.sl-tag-avail {
    background: var(--green-lt);
    color: var(--green);
}

.sl-tag-unavail {
    background: #f1f5f9;
    color: var(--ink3);
}

/* Right actions panel — single button layout */
.sl-actions {
    padding: 20px 24px 20px 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

/* SINGLE primary button */
.sl-btn-book {
    display: inline-block;
    padding: 9px 20px;
    background: var(--accent);
    color: #fff;
    border-radius: 9px;
    font-family: var(--font);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background .15s, transform .15s, box-shadow .15s;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(67,56,202,.25);
}

.sl-btn-book:hover {
    background: #3730a3;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(67,56,202,.35);
}

/* Remove-from-shortlist star button */
.sl-star-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform .18s;
}

.sl-star-btn:hover { transform: scale(1.2); }

.sl-star-btn svg {
    width: 20px;
    height: 20px;
}

.sl-star-btn svg path {
    fill: var(--gold);
    stroke: var(--gold);
    stroke-width: 1;
}

/* Experience badge */
.sl-exp-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    color: var(--amber);
    background: #fffbeb;
    padding: 3px 9px;
    border-radius: 20px;
    border: 1px solid #fde68a;
}

/* -- Empty state -- */
.sl-empty {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 60px 32px;
    text-align: center;
    box-shadow: var(--shadow);
}

.sl-empty-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--accent-lt);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.sl-empty-icon svg {
    width: 26px;
    height: 26px;
    color: var(--accent);
}

.sl-empty h3 {
    font-size: 17px;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 6px;
}

.sl-empty p {
    font-size: 13px;
    color: var(--ink3);
    margin-bottom: 20px;
    max-width: 340px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

.sl-btn-browse {
    display: inline-block;
    padding: 10px 24px;
    background: var(--navy);
    color: #fff;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: background .15s, transform .15s;
}

.sl-btn-browse:hover { background: #1e293b; transform: translateY(-1px); }

/* -- Footer -- */
.sl-footer {
    background: var(--navy);
    color: #64748b;
    padding: 36px 36px 18px;
    margin-top: 100px !important;
    margin-bottom: 0 !important;
}

.sl-footer-inner {
    max-width: 1100px;
    margin: 0 auto;
}

.sl-footer-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 32px;
    margin-bottom: 28px;
}

.sl-footer-logo {
    font-size: 18px;
    font-weight: 800;
    color: #818cf8;
    margin-bottom: 8px;
    letter-spacing: -.3px;
}

.sl-footer-brand p {
    font-size: 12px;
    line-height: 1.7;
}

.sl-footer h4 {
    font-size: 10px;
    font-weight: 700;
    color: #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.sl-footer-links {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.sl-footer-links a {
    font-size: 12px;
    color: #64748b;
    text-decoration: none;
    transition: color .15s;
}

.sl-footer-links a:hover { color: #e2e8f0; }

.sl-footer-bottom {
    border-top: 1px solid rgba(255,255,255,.06);
    padding-top: 14px;
    font-size: 11px;
    text-align: center;
    color: #334155;
}

.sl-social-icons { display: flex; gap: 8px; }

.sl-social-icons a {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.08);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 11px;
    text-decoration: none;
    transition: background .15s, color .15s;
}

.sl-social-icons a:hover { background: rgba(255,255,255,.12); color: #e2e8f0; }

/* -- No-results message -- */
.sl-no-results {
    text-align: center;
    padding: 40px;
    color: var(--ink3);
    font-size: 14px;
    display: none;
}

/* -- Responsive -- */
@media (max-width: 700px) {
    .sl-card {
        grid-template-columns: auto 1fr;
        grid-template-rows: auto auto;
    }
    .sl-card-strip { grid-row: 1 / 3; }
    .sl-actions {
        grid-column: 2;
        flex-direction: row;
        flex-wrap: wrap;
        padding: 0 16px 16px;
        justify-content: flex-start;
    }
    .sl-card-body { padding: 16px; }
    .sl-page-header { flex-direction: column; align-items: flex-start; }
    .sl-search-row { width: 100%; }
    .sl-footer-grid { grid-template-columns: 1fr 1fr; gap: 20px; }
}
</style>

<div class="sl-wrap">
    <div class="sl-page">

        <!-- -- Page header -- -->
        <div class="sl-page-header">
            <div>
                <h1 class="sl-page-title">
                    Shortlisted Educators
                    <span>Your saved educators, ready to book</span>
                </h1>
            </div>

            <div class="sl-search-row">
                <div class="sl-search-box">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           id="search-input"
                           placeholder="Search by name or specialization…"
                           autocomplete="off">
                </div>
                <button type="button" class="sl-search-btn" onclick="performSearch()">Search</button>
            </div>
        </div>

        <!-- -- Card grid -- -->
        <div class="sl-grid" id="experts-grid">

            <?php if (empty($experts)) : ?>

                <div class="sl-empty">
                    <div class="sl-empty-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                        </svg>
                    </div>
                    <h3>No shortlisted educators yet</h3>
                    <p>Use the star icon on the Find Educators page to save experts you'd like to revisit.</p>
                    <a class="sl-btn-browse" href="<?php echo esc_url(site_url('/find_educators/')); ?>">Browse All Educators</a>
                </div>

            <?php else : ?>

                <div class="sl-count-chip">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                    <?php echo count($experts); ?> educator<?php echo count($experts) !== 1 ? 's' : ''; ?> shortlisted
                </div>

                <?php foreach ($experts as $e) :
                    $avail_class  = $e['available'] ? 'is-available' : '';
                    $avail_label  = $e['available'] ? 'Available now' : 'Unavailable';
                    $avail_tag    = $e['available'] ? 'sl-tag-avail' : 'sl-tag-unavail';
                    $specs_show   = array_slice($e['specialties'], 0, 3);
                    $exp          = (int)$e['experience'];
                ?>

                <div class="sl-card <?php echo esc_attr($avail_class); ?> expert-card educator-card"
                     data-name="<?php echo esc_attr(strtolower($e['name'])); ?>"
                     data-specialization="<?php echo esc_attr(strtolower(implode(' ', $e['specialties']))); ?>"
                     data-search="<?php echo esc_attr(strtolower($e['name'] . ' ' . implode(' ', $e['specialties']))); ?>"
                     data-expert-id="<?php echo (int)$e['ID']; ?>">

                    <!-- colour strip -->
                    <div class="sl-card-strip"></div>

                    <!-- main info -->
                    <div class="sl-card-body">
                        <div class="sl-avatar-wrap">
                            <img class="sl-avatar"
                                 src="<?php echo esc_url($e['avatar']); ?>"
                                 alt="<?php echo esc_attr($e['name']); ?>">
                            <span class="sl-avail-dot" title="<?php echo esc_attr($avail_label); ?>"></span>
                        </div>

                        <div class="sl-info">
                            <div class="sl-name"><?php echo esc_html($e['name']); ?></div>

                            <div class="sl-meta">
                                <?php if ($exp > 0) : ?>
                                <span class="sl-exp-badge">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?php echo $exp; ?> yr exp
                                </span>
                                <?php endif; ?>

                                <span class="sl-meta-item">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                                    </svg>
                                    Remote
                                </span>
                            </div>

                            <div class="sl-tags">
                                <?php foreach ($specs_show as $spec) : ?>
                                    <span class="sl-tag"><?php echo esc_html($spec); ?></span>
                                <?php endforeach; ?>
                                <span class="sl-tag <?php echo $avail_tag; ?>"><?php echo esc_html($avail_label); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- SINGLE action button -->
                    <div class="sl-actions">
                        <a class="sl-btn-book"
                           href="<?php echo esc_url(site_url('/college/educator-profile?expert_id=' . (int)$e['ID'])); ?>">
                            View Profile & Book Session
                        </a>
                        <button class="sl-star-btn saved"
                                onclick="pcToggleSave(this, <?php echo (int)$e['ID']; ?>)"
                                title="Remove from shortlist"
                                aria-pressed="true">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                            </svg>
                        </button>
                    </div>

                </div>

                <?php endforeach; ?>

                <!-- shown when search finds nothing -->
                <div class="sl-no-results" id="sl-no-results">
                    No educators match your search.
                </div>

            <?php endif; ?>

        </div><!-- /.sl-grid -->

    </div><!-- /.sl-page -->

    <!-- -- Footer -- -->
    <footer class="sl-footer">
        <div class="sl-footer-inner">
            <div class="sl-footer-grid">
                <div class="sl-footer-brand">
                    <div class="sl-footer-logo">LOGO</div>
                    <p>Connecting educators and learners worldwide.</p>
                </div>
                <div>
                    <h4>Company</h4>
                    <div class="sl-footer-links">
                        <a href="#">About</a>
                        <a href="#">Careers</a>
                        <a href="#">Contact</a>
                    </div>
                </div>
                <div>
                    <h4>Resources</h4>
                    <div class="sl-footer-links">
                        <a href="#">Blog</a>
                        <a href="#">Help Center</a>
                        <a href="#">Terms</a>
                    </div>
                </div>
                <div>
                    <h4>Follow Us</h4>
                    <div class="sl-social-icons">
                        <a href="#">Tw</a>
                        <a href="#">Li</a>
                        <a href="#">Fb</a>
                    </div>
                </div>
            </div>
            <div class="sl-footer-bottom">&copy; <?php echo date('Y'); ?> All rights reserved.</div>
        </div>
    </footer>

</div><!-- /.sl-wrap -->

<script>
(function () {

    /* -- helpers -- */
    function getInput() {
        return (
            document.getElementById('search-input') ||
            document.querySelector('.sl-search-box input[type="text"]')
        );
    }

    function getCards() {
        return document.querySelectorAll('.expert-card, .educator-card');
    }

    function setHidden(el, hidden) {
        if (hidden) {
            el.classList.add('hidden');
            el.style.setProperty('display', 'none', 'important');
            el.setAttribute('aria-hidden', 'true');
        } else {
            el.classList.remove('hidden');
            el.style.removeProperty('display');
            el.removeAttribute('aria-hidden');
        }
    }

    function filter() {
        var inp   = getInput();
        var q     = (inp && inp.value || '').toLowerCase().trim();
        var cards = getCards();
        var noRes = document.getElementById('sl-no-results');
        if (!cards.length) return;

        var visible = 0;
        (Array.prototype.slice.call(cards)).forEach(function(c) {
            var name = (c.getAttribute('data-name') || '').toLowerCase();
            var spec = (c.getAttribute('data-search') ||
                        c.getAttribute('data-specialization') || '').toLowerCase();
            var match = !q || (name + ' ' + spec).indexOf(q) !== -1;
            setHidden(c, !match);
            if (match) visible++;
        });

        if (noRes) noRes.style.display = (visible === 0 && q) ? 'block' : 'none';
    }

    window.performSearch = filter;

    /* -- star / shortlist toggle -- */
    window.pcToggleSave = function(btn, expertId) {
        var wasSaved = btn.classList.contains('saved');
        btn.classList.toggle('saved');
        btn.setAttribute('aria-pressed', btn.classList.contains('saved') ? 'true' : 'false');

        /* On shortlisted page, removing = animate card out */
        if (wasSaved) {
            var card = btn.closest('.sl-card');
            if (card) {
                card.style.transition = 'opacity .3s, transform .3s';
                card.style.opacity = '0';
                card.style.transform = 'translateX(20px)';
                setTimeout(function() { card.style.display = 'none'; }, 320);
            }
        }

        var data = new FormData();
        data.append('action',    'pc_toggle_shortlist');
        data.append('expert_id', expertId);
        data.append('nonce',     (typeof pcoreContracts !== 'undefined' && pcoreContracts.nonce)
                                     ? pcoreContracts.nonce : '');

        fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: data,
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            if (!j || j.success !== true) throw new Error(j && j.data && j.data.msg ? j.data.msg : 'Failed');
        })
        .catch(function(err) {
            /* Rollback on error */
            if (wasSaved) {
                btn.classList.add('saved');
                btn.setAttribute('aria-pressed', 'true');
                var card = btn.closest('.sl-card');
                if (card) { card.style.opacity = '1'; card.style.transform = ''; card.style.display = ''; }
            } else {
                btn.classList.remove('saved');
                btn.setAttribute('aria-pressed', 'false');
            }
            alert(err.message || 'Unable to update shortlist. Please try again.');
        });
    };

    /* -- init search -- */
    document.addEventListener('DOMContentLoaded', function () {
        var inp = getInput();
        if (!inp) return;
        inp.addEventListener('input', filter);
        inp.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); filter(); }
        });
    });

})();
</script>