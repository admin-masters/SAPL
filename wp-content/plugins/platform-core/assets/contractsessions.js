(function(){
    function ready(fn){ if(document.readyState!='loading'){fn()} else {document.addEventListener('DOMContentLoaded', fn)} }
    function el(html){ var d=document.createElement('div'); d.innerHTML=html.trim(); return d.firstChild; }
    function norm(s){ return (s||'').trim().toLowerCase(); }
    function fmtDate(iso){ try{ if(!iso) return ''; var d=new Date(iso.replace(' ','T')); return d.toLocaleString(); }catch(e){ return iso||''; } }
    function money(n){ try{ return new Intl.NumberFormat(undefined,{style:'currency',currency:'INR'}).format(n||0);}catch(e){ return '₹'+(n||0);} }

    async function fetchJSON(url, opts){
        opts = opts || {}; opts.headers = opts.headers || {};
        if (window.pcoreContracts && pcoreContracts.nonce) opts.headers['X-WP-Nonce']=pcoreContracts.nonce;
        var r = await fetch(url, opts); if (!r.ok) throw new Error('HTTP '+r.status); return await r.json();
    }

    function findCardByTitle(root, title){
        var want = norm(title);
        var cards = root.querySelectorAll('.card');
        for (var i=0;i<cards.length;i++){
            var h = cards[i].querySelector('h1,h2,h3,h4,h5,h6');
            var t = norm(h ? h.textContent : '');
            if (t === want) return cards[i];
        }
        return null;
    }

    function renameActiveToAccepted(root){
        var hs = root.querySelectorAll('.card h1,.card h2,.card h3,.card h4,.card h5,.card h6');
        hs.forEach(function(h){
            var t = (h.textContent||'').trim().toLowerCase();
            if (t === 'active contracts') h.textContent = 'Accepted Contracts';
        });
    }

    // Remove all siblings after the heading so we can re-render
    function clearItems(card){
        if (!card) return;
        var heading = card.querySelector('h1,h2,h3,h4,h5,h6');
        var node = heading ? heading.nextSibling : card.firstChild;
        while (node) { var next = node.nextSibling; node.parentNode.removeChild(node); node = next; }
    }

    function renderSigned(card, list){
        if (!card) return;
        clearItems(card);
        if (!list || !list.length){
            card.appendChild(el('<div class="item"><div class="item-head"><strong>No accepted contracts yet</strong></div><small>You will see your upcoming and completed sessions here after signing & payment.</small></div>'));
            return;
        }
        list.forEach(function(c){
            var badgeText = (c.status_tag === 'completed') ? 'Completed' : 'Upcoming';
            var linkOpen  = c.pdf_url ? '<a href="'+c.pdf_url+'" target="_blank" class="item" style="text-decoration:none;color:inherit">' : '<div class="item">';
            var linkClose = c.pdf_url ? '</a>' : '</div>';
            var html = linkOpen +
                '<div class="item-head"><strong>'+ (c.topic||'Session') +'</strong><span class="badge active">'+badgeText+'</span></div>' +
                '<small>Expert: '+ (c.expert_name||'') +'</small>' +
                (c.start_iso ? '<small>When: '+ fmtDate(c.start_iso) +'</small>' : '') +
                '<small>Duration: '+ (c.duration_minutes||60) +' minutes</small>' +
                (c.total_amount ? '<small>Value: '+ money(c.total_amount) +'</small>' : '') +
            linkClose;
            card.appendChild(el(html));
        });
    }

    function renderUnsigned(card, list){
        if (!card) return;
        clearItems(card);
        (list||[]).forEach(function(c){
            // Compute sign URL if not provided (My Classes + ?pc_contract=)
            var signUrl = c.sign_url;
            if (!signUrl && c.sign_token && window.pcoreContracts && pcoreContracts.signBase){
                var sep = pcoreContracts.signBase.indexOf('?') > -1 ? '&' : '?';
                signUrl = pcoreContracts.signBase + sep + 'pc_contract=' + encodeURIComponent(c.sign_token);
            }

            // 1) MAKE PAYMENT if we have a pay_url
            if (c.pay_url) {
                var node = el('<div class="item">' +
                    '<div class="item-head"><strong>'+ (c.topic||'Contract') +'</strong><span class="badge pending">'+ (c.badge||'Awaiting Payment') +'</span></div>' +
                    '<small>Expert: '+ (c.expert_name||'') +'</small>' +
                    (c.start_iso ? '<small>Start: '+ fmtDate(c.start_iso) +'</small>' : '') +
                    (c.total_amount ? '<small>Amount: '+ money(c.total_amount) +'</small>' : '') +
                    '<div class="actions"><button class="accept pay">Make Payment</button></div>' +
                '</div>');
                node.querySelector('button.pay').addEventListener('click', function(){
                    try { sessionStorage.setItem('pcoreReloadContracts','1'); } catch(e){}
                    window.location.href = c.pay_url; // Woo/Razorpay checkout
                });
                card.appendChild(node);
                return;
            }

            // 2) SIGN if token (and computed URL) is available
            if (c.sign_token && signUrl) {
                var node2 = el('<div class="item">' +
                    '<div class="item-head"><strong>'+ (c.topic||'Contract') +'</strong><span class="badge pending">'+ (c.badge||'Awaiting Signature') +'</span></div>' +
                    '<small>Expert: '+ (c.expert_name||'') +'</small>' +
                    (c.start_iso ? '<small>Start: '+ fmtDate(c.start_iso) +'</small>' : '') +
                    (c.total_amount ? '<small>Amount: '+ money(c.total_amount) +'</small>' : '') +
                    '<div class="actions"><button class="accept sign">Sign</button></div>' +
                '</div>');
                node2.querySelector('button.sign').addEventListener('click', function(){
                    // Open inline overlay with the existing signer UI inside an iframe
                    openSignOverlay(signUrl, c.contract_id);
                });
                card.appendChild(node2);
                return;
            }

            // 3) Otherwise show as Pending
            var node3 = el('<div class="item">' +
                '<div class="item-head"><strong>'+ (c.topic||'Session Request') +'</strong><span class="badge pending">'+ (c.badge||'Pending') +'</span></div>' +
                '<small>Expert: '+ (c.expert_name||'') +'</small>' +
                (c.start_iso ? '<small>Proposed: '+ fmtDate(c.start_iso) +'</small>' : '') +
            '</div>');
            card.appendChild(node3);
        });
    }

    function renderRequests(card, list){
        if (!card) return;
        (list||[]).forEach(function(r){
            var node = el('<div class="item">' +
                '<div class="item-head"><strong>'+ (r.topic||'Session Request') +'</strong><span class="badge pending">'+ (r.status||'Pending') +'</span></div>' +
                '<small>Expert: '+ (r.expert_name||'') +'</small>' +
                (r.proposed_start_iso ? '<small>Proposed: '+ fmtDate(r.proposed_start_iso) +'</small>' : '') +
            '</div>');
            card.appendChild(node);
        });
    }

    // =============== Inline signer overlay ===============
    var overlayTimer = null; // polling timer
    function openSignOverlay(signUrl, contractId){
        try{
            // Create overlay
            var wrap = el(
                '<div id="pc-sign-overlay" style="position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;">' +
                    '<div class="frame-wrap" style="width:min(1000px,96vw);height:min(90vh,780px);background:#fff;border-radius:8px;overflow:hidden;position:relative;box-shadow:0 10px 30px rgba(0,0,0,.3);">' +
                        '<button type="button" class="close" aria-label="Close" style="position:absolute;right:10px;top:8px;font-size:24px;line-height:1;background:transparent;border:0;cursor:pointer">&times;</button>' +
                        '<iframe class="sign-frame" src="'+signUrl+'" style="width:100%;height:100%;border:0" allowtransparency="true"></iframe>' +
                    '</div>' +
                '</div>'
            );
            document.body.appendChild(wrap);

            // Hide site chrome inside iframe (same-origin only)
            var frame = wrap.querySelector('.sign-frame');
            frame.addEventListener('load', function(){
                try{
                    var doc = frame.contentDocument || frame.contentWindow.document;
                    ['header','.site-header','.wp-site-blocks > header','nav','footer','.site-footer','.site-branding'].forEach(function(sel){
                        doc.querySelectorAll(sel).forEach(function(n){ n.style.display='none'; });
                    });
                    // Optional: scroll to the signer section if the page anchors it
                    var target = doc.querySelector('#pc-contract-sign, #sign, [data-sign-form]');
                    if (target && target.scrollIntoView) target.scrollIntoView({behavior:'instant', block:'start'});
                }catch(e){}
            });

            // Close button
            wrap.querySelector('.close').addEventListener('click', function(){
                closeOverlay();
            });

            // Start polling for status while overlay is open
            overlayTimer = setInterval(async function(){
                try{
                    var data = await fetchJSON(pcoreContracts.listEndpoint, { method:'GET' });
                    var nowUnsigned = (data.unsigned || []).find(function(x){ return x.contract_id === contractId; });
                    var nowAccepted = (data.signed   || []).find(function(x){ return x.contract_id === contractId; });

                    if (nowAccepted || (nowUnsigned && nowUnsigned.needs_payment)) {
                        closeOverlay();
                        // Move to next step (payment) or accepted
                        load();
                    }
                }catch(e){
                    // ignore transient errors
                }
            }, 3000);

            // Fallback: if something blocks the iframe, navigate the page
            setTimeout(function(){
                if (!frame.contentDocument && !frame.contentWindow) {
                    window.location.href = signUrl;
                }
            }, 1500);

            function closeOverlay(){
                if (overlayTimer) { clearInterval(overlayTimer); overlayTimer = null; }
                if (wrap && wrap.parentNode) wrap.parentNode.removeChild(wrap);
                try{ sessionStorage.setItem('pcoreReloadContracts','1'); }catch(e){}
            }
        }catch(e){
            // Last-resort fallback
            window.location.href = signUrl;
        }
    }
    // ============= End inline signer overlay =============

    // Avatar + "Hi, <name>"
    function applyUserGreeting(){
        // If server already injected the correct profile block, do nothing.
        var root = document.getElementById('pc-contractsessions-root');
        if (!root) return;
        var prof = root.querySelector('.top .profile');
        if (!prof) return;
        if (prof.querySelector('.profile-name')) return; // server did it

        // Fallback (should rarely run): use localized user values
        if (!window.pcoreContracts || !pcoreContracts.user) return;
        prof.innerHTML = '';
        var img = new Image();
        img.src = pcoreContracts.user.avatar;
        img.alt = 'Profile';
        prof.appendChild(img);
        var span = document.createElement('span');
        span.className = 'profile-name';
        span.textContent = 'Hi, ' + (pcoreContracts.user.first_name || pcoreContracts.user.name || '');
        prof.appendChild(span);
    }


    async function load(){
        var root = document.getElementById('pc-contractsessions-root');
        if (!root) return;

        renameActiveToAccepted(root);

        var acceptedCard  = findCardByTitle(root, 'accepted contracts');   // left column
        var requestCard   = findCardByTitle(root, 'requested sessions');   // right column

        try{
            var data = await fetchJSON(pcoreContracts.listEndpoint, { method:'GET' });
            renderSigned(acceptedCard,  data.signed);
            renderUnsigned(requestCard, data.unsigned);
            renderRequests(requestCard, data.requests);
        } catch(e){
            console.error(e);
            if (acceptedCard) acceptedCard.appendChild(el('<div class="item"><small>Could not load contracts.</small></div>'));
        }
    }

    function autoRefreshOnReturn(){
        try{
            if (sessionStorage.getItem('pcoreReloadContracts') === '1') {
                sessionStorage.removeItem('pcoreReloadContracts');
                load();
            }
        } catch(e){}
    }

    ready(function(){
        applyUserGreeting();
        load();
    });
    window.addEventListener('focus', autoRefreshOnReturn);
    document.addEventListener('visibilitychange', function(){ if(!document.hidden) autoRefreshOnReturn(); });
})();
