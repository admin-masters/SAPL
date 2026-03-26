(function(){
  function show(tabbar, name){
    tabbar.querySelectorAll('.peh-tab').forEach(b=>b.classList.toggle('active', b.dataset.tab===name));
    const root = tabbar.closest('.peh');
    root.querySelectorAll('.peh-panel').forEach(p=>p.toggleAttribute('hidden', p.dataset.tab!==name));
    sessionStorage.setItem('peh-last', name);
  }
  document.addEventListener('click', function(e){
    if (e.target.classList && e.target.classList.contains('peh-tab')) {
      show(e.target.parentElement, e.target.dataset.tab);
    }
  });
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.peh-tabs').forEach(tabbar=>{
      const def = sessionStorage.getItem('peh-last') || tabbar.dataset.default || 'invites';
      const first = tabbar.querySelector('.peh-tab')?.dataset.tab || 'invites';
      show(tabbar, tabbar.querySelector('.peh-tab[data-tab="'+def+'"]') ? def : first);
    });
  });
})();
