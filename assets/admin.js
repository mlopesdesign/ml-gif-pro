(function(){
  function ready(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function(){
    var wrap = document.querySelector('.mlpb-admin-wrap');
    if(!wrap){ return; }

    var toastArea = document.getElementById('mlpb-toast-area');

    // Toast
    function showToast(message, type){
      if(!toastArea || !message){ return; }
      var toast = document.createElement('div');
      toast.className = 'mlpb-toast mlpb-toast-' + (type || 'success');
      toast.textContent = message;
      toastArea.appendChild(toast);
      requestAnimationFrame(function(){ toast.classList.add('is-visible'); });
      setTimeout(function(){
        toast.classList.remove('is-visible');
        setTimeout(function(){ if(toast.parentNode){ toast.parentNode.removeChild(toast); } }, 220);
      }, 4200);
    }
    window.mlgifShowToast = showToast;

    // On-load toast
    var initialToast = wrap.getAttribute('data-toast-message');
    var initialType  = wrap.getAttribute('data-toast-type') || 'success';
    if(initialToast){ showToast(decodeURIComponent(initialToast), initialType); }

    // Tab navigation
    document.querySelectorAll('.mlpb-tab-button').forEach(function(button){
      button.addEventListener('click', function(){
        var target = button.getAttribute('data-tab-target');
        if(!target){ return; }
        document.querySelectorAll('.mlpb-tab-button').forEach(function(btn){ btn.classList.remove('is-active'); });
        document.querySelectorAll('.mlpb-tab-panel').forEach(function(panel){ panel.hidden = true; panel.classList.remove('is-active'); });
        button.classList.add('is-active');
        var panel = document.getElementById(target);
        if(panel){ panel.hidden = false; panel.classList.add('is-active'); }
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('tab', target.replace('mlgif-tab-', ''));
          window.history.replaceState({}, '', url.toString());
        } catch(e){}
      });
    });

    // Copy buttons (generic)
    document.querySelectorAll('[data-copy-target]').forEach(function(button){
      button.addEventListener('click', function(){
        var selector = button.getAttribute('data-copy-target');
        var field    = document.querySelector(selector);
        if(!field){ return; }
        var text = field.value || field.textContent || '';
        navigator.clipboard.writeText(text)
          .then(function(){ showToast('Copiado com sucesso.', 'success'); })
          .catch(function(){ showToast('Falha ao copiar.', 'error'); });
      });
    });
  });

  // Project list helpers (global scope for inline onclick)
  window.mlgifOpenNew = function(){
    var url = new URL(window.location.href);
    url.searchParams.set('tab', 'projects');
    url.searchParams.set('action', 'new');
    url.searchParams.delete('edit');
    window.location.href = url.toString();
  };

  window.mlgifEditProject = function(id){
    var url = new URL(window.location.href);
    url.searchParams.set('tab', 'projects');
    url.searchParams.set('edit', id);
    url.searchParams.delete('action');
    window.location.href = url.toString();
  };

  window.mlgifBackToList = function(){
    var url = new URL(window.location.href);
    url.searchParams.set('tab', 'projects');
    url.searchParams.delete('edit');
    url.searchParams.delete('action');
    window.location.href = url.toString();
  };

  window.mlgifCopyUrl = function(urlStr){
    navigator.clipboard.writeText(urlStr)
      .then(function(){ if(window.mlgifShowToast){ window.mlgifShowToast('URL copiada!', 'success'); } })
      .catch(function(){ if(window.mlgifShowToast){ window.mlgifShowToast('Falha ao copiar URL.', 'error'); } });
  };

  window.mlgifDeleteProject = function(id, title){
    if(!confirm('Excluir o projeto "' + title + '" e o GIF gerado? Esta ação não pode ser desfeita.')){ return; }
    var nonce   = document.querySelector('[data-mlgif-nonce]');
    var nonceVal = nonce ? nonce.dataset.mlgifNonce : '';
    var fd = new FormData();
    fd.append('action', 'mlgif_delete_project');
    fd.append('nonce',  nonceVal);
    fd.append('id',     id);
    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(data.success){
          if(window.mlgifShowToast){ window.mlgifShowToast('Projeto excluído.', 'success'); }
          setTimeout(function(){ window.location.reload(); }, 700);
        } else {
          if(window.mlgifShowToast){ window.mlgifShowToast((data.data && data.data.error) || 'Erro ao excluir.', 'error'); }
        }
      }).catch(function(){
        if(window.mlgifShowToast){ window.mlgifShowToast('Erro de comunicação.', 'error'); }
      });
  };
})();
