document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.checklist-item').forEach(function (item) {
    var radios = item.querySelectorAll('input[type=radio]');
    var obsBox = item.querySelector('.checklist-item-obs');
    var textarea = obsBox ? obsBox.querySelector('textarea') : null;
    var counter = obsBox ? obsBox.querySelector('.char-counter') : null;

    radios.forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (!obsBox || !textarea) return;
        if (radio.value === 'nao_conforme' && radio.checked) {
          obsBox.style.display = 'block';
          textarea.setAttribute('required', 'required');
          textarea.setAttribute('minlength', '10');
          item.classList.add('item-nao-conforme');
        } else if (radio.checked) {
          obsBox.style.display = 'none';
          textarea.removeAttribute('required');
          textarea.value = '';
          item.classList.remove('item-nao-conforme');
          if (counter) counter.textContent = '0 caracteres';
        }
      });
    });

    if (textarea) {
      textarea.addEventListener('input', function () {
        var len = textarea.value.length;
        if (counter) {
          counter.textContent = len + ' caractere' + (len === 1 ? '' : 's');
          counter.classList.toggle('char-counter-ok', len >= 10);
        }
      });
    }
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  var navToggle = document.getElementById('nav-toggle');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  if (navToggle && sidebar && overlay) {
    navToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('open');
    });
    overlay.addEventListener('click', function () {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
  }

  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.classList.add('alert-hide');
    }, 6000);
  });

  if (window.APP_SESSION) {
    var idleTimer = null;

    function resetIdleTimer() {
      if (idleTimer) clearTimeout(idleTimer);
      idleTimer = setTimeout(function () {
        window.location.href = window.APP_SESSION.logoutUrl;
      }, window.APP_SESSION.idleTimeoutMs);
    }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (evt) {
      document.addEventListener(evt, resetIdleTimer, { passive: true });
    });

    resetIdleTimer();
  }
});
