(function(){
  function closest(el, sel){ while (el && !el.matches(sel)) el = el.parentElement; return el; }
  function formToJSON(form){ return Object.fromEntries(new FormData(form).entries()); }
  function post(url, data){
    return fetch(url, {
      method: 'POST',
      headers: {'X-WP-Nonce': PlatformInvites.nonce, 'Content-Type': 'application/json'},
      body: JSON.stringify(data||{})
    }).then(r=>r.json());
  }

  document.addEventListener('click', function(e){
    if (e.target.classList.contains('pci-accept')) {
      const card = closest(e.target, '.pci-card'); card.querySelector('.pci-accept-form').hidden = false;
    }
    if (e.target.classList.contains('pci-reject')) {
      const card = closest(e.target, '.pci-card'); card.querySelector('.pci-reject-form').hidden = false;
    }
    if (e.target.classList.contains('pci-send-accept')) {
      e.preventDefault();
      const card = closest(e.target, '.pci-card');
      const id = card.dataset.id;
      const payload = formToJSON(card.querySelector('.pci-accept-form'));
      post(PlatformInvites.rest + '/' + id + '/accept', payload).then(function(res){
        if (res && res.ok) location.reload(); else alert(res.error || 'Error');
      });
    }
    if (e.target.classList.contains('pci-send-reject')) {
      e.preventDefault();
      const card = closest(e.target, '.pci-card');
      const id = card.dataset.id;
      const payload = formToJSON(card.querySelector('.pci-reject-form'));
      post(PlatformInvites.rest + '/' + id + '/reject', payload).then(function(res){
        if (res && res.ok) location.reload(); else alert(res.error || 'Error');
      });
    }
    if (e.target.classList.contains('pci-send-reschedule')) {
      e.preventDefault();
      const card = closest(e.target, '.pci-card');
      const id = card.dataset.id;
      const payload = formToJSON(card.querySelector('.pci-reschedule-form'));
      post(PlatformInvites.rest + '/' + id + '/reschedule', payload).then(function(res){
        if (res && res.ok) location.reload(); else alert(res.error || 'Error');
      });
    }
    if (e.target.classList.contains('pci-send-cancel')) {
      e.preventDefault();
      const card = closest(e.target, '.pci-card');
      const id = card.dataset.id;
      const payload = formToJSON(card.querySelector('.pci-cancel-form'));
      if (confirm('Cancel this event for all attendees?')) {
        post(PlatformInvites.rest + '/' + id + '/cancel', payload).then(function(res){
          if (res && res.ok) location.reload(); else alert(res.error || 'Error');
        });
      }
    }
  });
})();
