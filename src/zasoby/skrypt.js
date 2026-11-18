(function () {
  var d = document;

  // menu na telefonie
  var btn = d.querySelector('.menu-btn');
  var nav = d.getElementById('nav');
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var otwarte = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!otwarte));
      nav.classList.toggle('nav--otwarte', !otwarte);
    });
  }

  // sprawdź swój refleks
  var s = d.querySelector('.sygnal');
  if (!s) return;
  var opis = s.querySelector('.sygnal__opis');
  var wynik = d.querySelector('.refleks__wynik');
  var stan = 'start';
  var start = 0;
  var zegar = null;
  var najlepszy = null;
  var zDotyku = false;

  function ustaw(nowy, tekst) {
    stan = nowy;
    s.setAttribute('data-stan', nowy);
    opis.textContent = tekst;
  }

  function akcja() {
    if (stan === 'start' || stan === 'wynik') {
      ustaw('czekaj', 'Czekaj na zielone');
      zegar = setTimeout(function () {
        start = performance.now();
        ustaw('teraz', 'Teraz!');
      }, 1300 + Math.random() * 2700);
    } else if (stan === 'czekaj') {
      clearTimeout(zegar);
      ustaw('wynik', 'Za wcześnie. Dotknij, żeby spróbować jeszcze raz');
    } else if (stan === 'teraz') {
      var ms = Math.round(performance.now() - start);
      najlepszy = najlepszy === null ? ms : Math.min(najlepszy, ms);
      ustaw('wynik', 'Dotknij, żeby powtórzyć');
      wynik.textContent = ms + ' ms' + (najlepszy !== ms ? ' (najlepszy wynik: ' + najlepszy + ' ms)' : '');
    }
  }

  s.addEventListener('pointerdown', function () {
    zDotyku = true;
    akcja();
  });
  s.addEventListener('click', function () {
    if (zDotyku) {
      zDotyku = false;
      return;
    }
    akcja();
  });
})();
