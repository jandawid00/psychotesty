/* Panel: wiersze do dodawania i usuwania, licznik znaków, podgląd hasła, ostrzeżenie przed utratą zmian. */
(function () {
  'use strict';

  function numeruj(lista) {
    var wiersze = lista.querySelectorAll(':scope > .wiersz');
    wiersze.forEach(function (w, i) {
      var nr = w.querySelector('[data-nr]');
      if (nr) { nr.textContent = String(i + 1); }
      var wyzej = w.querySelector('[data-wyzej]');
      if (wyzej) { wyzej.hidden = i === 0; }
      var usun = w.querySelector('[data-usun]');
      // Pozycji, której cena jest wstawiana w teksty strony, nie da się usunąć.
      if (usun) { usun.hidden = !!w.querySelector('[data-stala]'); }
      w.querySelectorAll('[id]').forEach(function (el) {
        var stare = el.id;
        var nowe = stare.replace(/-(\d+|nowy)(-[ob])?$/, '-' + i + '$2');
        if (nowe === stare) { return; }
        w.querySelectorAll('[for="' + stare + '"]').forEach(function (l) { l.htmlFor = nowe; });
        w.querySelectorAll('[aria-describedby]').forEach(function (p) {
          p.setAttribute('aria-describedby', p.getAttribute('aria-describedby').split(' ').map(function (x) { return x === stare ? nowe : x; }).join(' '));
        });
        el.id = nowe;
      });
    });
  }

  document.querySelectorAll('[data-wiersze]').forEach(function (lista) {
    numeruj(lista);
    lista.addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn || !lista.contains(btn)) { return; }
      var wiersz = btn.closest('.wiersz');
      if (btn.hasAttribute('data-dodaj')) {
        var nowy = lista.querySelector(':scope > template').content.firstElementChild.cloneNode(true);
        lista.insertBefore(nowy, btn);
        numeruj(lista);
        var pole = nowy.querySelector('input:not([type=hidden]),textarea');
        if (pole) { pole.focus(); }
        zmienione = true;
      } else if (btn.hasAttribute('data-usun') && wiersz) {
        var wypelniony = Array.prototype.some.call(wiersz.querySelectorAll('input:not([type=hidden]),textarea'), function (p) { return p.value.trim() !== ''; });
        if (wypelniony && !window.confirm('Usunąć ten wiersz? Zniknie ze strony po zapisaniu.')) { return; }
        if (lista.querySelectorAll(':scope > .wiersz').length === 1) {
          wiersz.querySelectorAll('input:not([type=hidden]),textarea').forEach(function (p) { p.value = ''; });
        } else {
          wiersz.remove();
        }
        numeruj(lista);
        zmienione = true;
      } else if (btn.hasAttribute('data-wyzej') && wiersz && wiersz.previousElementSibling && wiersz.previousElementSibling.classList.contains('wiersz')) {
        lista.insertBefore(wiersz, wiersz.previousElementSibling);
        numeruj(lista);
        btn.focus();
        zmienione = true;
      }
    });
  });

  document.querySelectorAll('[data-licznik]').forEach(function (pole) {
    var maks = parseInt(pole.getAttribute('data-licznik'), 10);
    var p = document.createElement('p');
    p.className = 'licznik';
    pole.insertAdjacentElement('afterend', p);
    function licz() {
      var n = pole.value.length;
      p.textContent = n + ' z zalecanych ' + maks + ' znaków';
      p.classList.toggle('licznik--duzo', n > maks);
    }
    pole.addEventListener('input', licz);
    licz();
  });

  document.querySelectorAll('[data-pokaz-hasla]').forEach(function (ptaszek) {
    ptaszek.addEventListener('change', function () {
      var form = ptaszek.closest('form') || document;
      form.querySelectorAll('input[autocomplete$="password"]').forEach(function (p) { p.type = ptaszek.checked ? 'text' : 'password'; });
    });
  });

  var zmienione = false;
  document.querySelectorAll('form.form').forEach(function (form) {
    form.addEventListener('input', function () { zmienione = true; });
    form.addEventListener('submit', function () {
      zmienione = false;
      var btn = form.querySelector('.zapisz button');
      if (btn) { btn.textContent = 'Zapisuję…'; }
    });
  });
  window.addEventListener('beforeunload', function (e) {
    if (zmienione) { e.preventDefault(); e.returnValue = ''; }
  });
})();
