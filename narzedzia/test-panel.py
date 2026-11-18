"""Test panelu w prawdziwej przeglądarce (widok telefonu).
użycie: test-panel.py <adres bazowy> <hasło testowe> <katalog www> <katalog zrzutów>
Wymaga świeżej kopii testowej z ustawionym już hasłem. Nie uruchamiać na prawdziwej stronie."""
import sys, os, re
from playwright.sync_api import sync_playwright

B, HASLO, WWW, OUT = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3], sys.argv[4]
os.makedirs(OUT, exist_ok=True)
wyniki = []

def ok(nazwa, warunek, szczegoly=''):
    wyniki.append((nazwa, bool(warunek)))
    print(('  OK   ' if warunek else '  BŁĄD ') + nazwa + (' :: ' + str(szczegoly)[:200] if szczegoly and not warunek else ''))

def plik(rel):
    p = os.path.join(WWW, rel)
    return open(p, encoding='utf-8').read() if os.path.isfile(p) else None

with sync_playwright() as p:
    b = p.chromium.launch(channel="chrome", args=["--no-sandbox"])
    ctx = b.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2, is_mobile=True, has_touch=True)
    pg = ctx.new_page()
    bledy = []
    pg.on("console", lambda m: bledy.append(m.text) if m.type == "error" else None)
    pg.on("pageerror", lambda e: bledy.append(str(e)))
    pg.on("dialog", lambda d: d.accept())

    def idz(s=''):
        pg.goto(B + '/panel/' + (('?s=' + s) if s else ''), wait_until="networkidle")

    def zrzut(nazwa):
        pg.screenshot(path=os.path.join(OUT, nazwa + '.png'), full_page=True)
        szer = pg.evaluate("document.documentElement.scrollWidth > document.documentElement.clientWidth")
        ok('bez przewijania w bok: ' + nazwa, not szer)

    def zapisz():
        pg.click('.zapisz button')
        pg.wait_for_load_state('networkidle')
        el = pg.query_selector('.info')
        return el.inner_text().strip() if el else ''

    # logowanie
    idz()
    zrzut('01-logowanie')
    ok('ekran logowania', 'Zaloguj się' in pg.inner_text('h1'))
    pg.fill('input[name=haslo]', 'zle-haslo-zle-haslo')
    pg.click('.zapisz button'); pg.wait_for_load_state('networkidle')
    ok('złe hasło odrzucone', 'To nie jest właściwe hasło' in pg.content())
    pg.fill('input[name=haslo]', HASLO)
    pg.click('.zapisz button'); pg.wait_for_load_state('networkidle')
    ok('zalogowano', 'Co chcesz zmienić' in pg.inner_text('h1'))
    zrzut('02-start')

    # zapis bez zmian nie może nic zmienić (wczytanie i złożenie pliku daje to samo)
    strony = ['pytania', 'polityka', 'komunikat', 'cennik', 'dane']
    strony += ['badanie&id=' + s for s in ['psychotesty-kierowcow-zawodowych', 'psychotesty-po-zatrzymaniu-prawa-jazdy', 'psychotesty-pojazdy-uprzywilejowane',
               'psychotesty-instruktorow-egzaminatorow', 'psychotesty-taxi-przewoz-osob', 'badania-psychotechniczne-operatorow', 'dla-firm']]
    strony += ['artykul&id=' + s for s in ['psychotesty-200-zl-od-14-listopada-2026', 'jak-wygladaja-psychotesty-kierowcow', 'jak-przygotowac-sie-do-psychotestow']]
    for s in strony:
        idz(s)
        t = zapisz()
        ok('zapis bez zmian: ' + s, 'Nic się nie zmieniło' in t, t)

    for s, n in [('dane', '03-dane'), ('cennik', '04-cennik'), ('pytania', '05-pytania'), ('badania', '06-badania'), ('badanie&id=dla-firm', '07-badanie'),
                 ('poradnik', '08-poradnik'), ('artykul&nowy=1', '09-nowy-artykul'), ('komunikat', '10-komunikat'), ('google', '11-google'), ('haslo', '12-haslo')]:
        idz(s); zrzut(n)

    # nowe pytanie przez przycisk „Dodaj pytanie”
    idz('pytania')
    przed = pg.locator('.wiersz').count()
    pg.click('[data-dodaj]')
    ok('dodany wiersz pytania', pg.locator('.wiersz').count() == przed + 1)
    pg.locator('.wiersz').last.locator('input[name="fp[]"]').fill('Czy test dodawania pytań działa?')
    pg.locator('.wiersz').last.locator('textarea[name="fo[]"]').fill('Tak. To pytanie dodał **test**.\n\n- punkt pierwszy\n- punkt drugi')
    t = zapisz()
    ok('pytanie zapisane', 'Zapisano' in t, t)
    h = plik('pytania/index.html') or ''
    ok('pytanie na stronie', 'Czy test dodawania pytań działa?' in h and '<strong>test</strong>' in h and '<li>punkt pierwszy</li>' in h)
    ok('pytanie w danych dla Google', '"name":"Czy test dodawania pytań działa?"' in h)

    # przesunięcie w górę i usunięcie wiersza
    idz('pytania')
    pg.locator('.wiersz').last.locator('[data-wyzej]').click()
    t = zapisz(); ok('zmiana kolejności zapisana', 'Zapisano' in t, t)
    idz('pytania')
    n = pg.locator('.wiersz').count()
    w = pg.locator('.wiersz', has=pg.locator('input[value="Czy test dodawania pytań działa?"]'))
    w.locator('[data-usun]').click()
    ok('wiersz usunięty z formularza', pg.locator('.wiersz').count() == n - 1)
    t = zapisz(); ok('usunięcie zapisane', 'Zapisano' in t, t)
    ok('pytanie zniknęło ze strony', 'Czy test dodawania pytań działa?' not in (plik('pytania/index.html') or ''))

    # strona badania: zmiana nagłówka, próba wstrzyknięcia HTML
    idz('badanie&id=dla-firm')
    stary = pg.input_value('input[name=h1]')
    pg.fill('input[name=h1]', 'Badania dla firm <script>alert(1)</script>')
    t = zapisz(); ok('badanie zapisane', 'Zapisano' in t, t)
    h = plik('dla-firm/index.html') or ''
    ok('HTML z formularza jest unieszkodliwiony', '<script>alert(1)</script>' not in h and '&lt;script&gt;alert(1)&lt;/script&gt;' in h)
    idz('badanie&id=dla-firm'); pg.fill('input[name=h1]', stary); zapisz()

    # nowy artykuł, usunięcie, przywrócenie
    idz('artykul&nowy=1')
    pg.fill('input[name=h1]', 'Ile trwają psychotesty – test żółć')
    pg.fill('textarea[name=lead]', 'Wstęp testowy.')
    pg.fill('textarea[name=tresc]', '## Część pierwsza\n\nAkapit z [linkiem](/cennik/) i [złym linkiem](javascript:alert(1)).\n\n> Uwaga testowa')
    t = zapisz(); ok('artykuł opublikowany', 'Artykuł jest już na stronie' in t, t)
    slug = 'ile-trwaja-psychotesty-test-zolc'
    h = plik('poradnik/%s/index.html' % slug) or ''
    ok('artykuł ma własną podstronę', '<h2 id="czesc-pierwsza">' in h and '<a href="/cennik/">linkiem</a>' in h and 'class="uwaga"' in h)
    ok('niebezpieczny link odrzucony', 'javascript:' not in h)
    ok('artykuł na liście poradnika', slug in (plik('poradnik/index.html') or ''))
    idz('usun-artykul&id=' + slug); zrzut('13-usun-artykul')
    pg.click('.btn--usun'); pg.wait_for_load_state('networkidle')
    ok('artykuł usunięty', plik('poradnik/%s/index.html' % slug) is None and slug not in (plik('poradnik/index.html') or ''))
    # usunięcie artykułu startowego i przywrócenie z historii
    st = 'jak-przygotowac-sie-do-psychotestow'
    idz('usun-artykul&id=' + st); pg.click('.btn--usun'); pg.wait_for_load_state('networkidle')
    ok('artykuł startowy usunięty', plik('poradnik/%s/index.html' % st) is None)
    idz('historia'); zrzut('14-historia')
    wiersz = pg.locator('.historia li', has_text='Jak przygotować się do psychotestów').first
    wiersz.locator('button').click(); pg.wait_for_load_state('networkidle')
    ok('artykuł startowy przywrócony', plik('poradnik/%s/index.html' % st) is not None, pg.inner_text('main')[:200])

    # komunikat
    idz('komunikat'); pg.uncheck('input[name=pokaz]'); t = zapisz()
    ok('komunikat ukryty', 'Zapisano' in t and 'class="objazd"' not in (plik('index.html') or ''), t)
    idz('komunikat'); pg.check('input[name=pokaz]'); pg.fill('input[name=tytul]', 'Urlop 1–14 sierpnia'); t = zapisz()
    ok('komunikat zmieniony', 'Urlop 1–14' in (plik('index.html') or ''), t)

    # widoczność w Google
    idz('google')
    ok('można pokazać stronę (wszystko uzupełnione)', pg.locator('button:has-text("Pokaż stronę w Google")').is_enabled(), pg.inner_text('main')[:300])
    pg.click('button:has-text("Pokaż stronę w Google")'); pg.wait_for_load_state('networkidle')
    zrzut('15-google-widoczna')
    h = plik('index.html') or ''
    ok('strona ma index, follow i canonical', 'content="index, follow' in h and 'rel="canonical"' in h)
    ok('sitemap.xml i llms.txt powstały', plik('sitemap.xml') is not None and plik('llms.txt') is not None)
    ok('robots.txt wskazuje mapę strony', 'Sitemap:' in (plik('robots.txt') or ''))
    ok('llms.txt ma telefon i godziny', 'Telefon: 500 600 700' in (plik('llms.txt') or '') and 'Godziny:' in (plik('llms.txt') or ''))
    # na widocznej stronie nie da się wyczyścić wymaganego pola
    idz('dane'); pg.fill('input[name=telefon]', ''); t = zapisz()
    ok('puste pole zablokowane na widocznej stronie', 'nie może zostać puste' in pg.inner_text('main') and 'href="tel:+48500600700"' in (plik('index.html') or ''), pg.inner_text('main')[:300])
    zrzut('16-blokada-pustego-pola')
    idz('google'); pg.click('button:has-text("Ukryj stronę przed Google")'); pg.wait_for_load_state('networkidle')
    h = plik('index.html') or ''
    ok('strona znów ukryta', 'noindex, nofollow' in h and plik('sitemap.xml') is None and plik('llms.txt') is None)

    # zmiana hasła, wylogowanie, blokada po wielu próbach
    idz('haslo')
    pg.fill('input[name=stare]', HASLO); pg.fill('input[name=haslo]', HASLO + '-nowe'); pg.fill('input[name=haslo2]', HASLO + '-nowe')
    pg.click('.zapisz button'); pg.wait_for_load_state('networkidle')
    ok('hasło zmienione', 'Hasło zmienione' in pg.inner_text('main'), pg.inner_text('main')[:200])
    pg.click('.wyloguj button'); pg.wait_for_load_state('networkidle')
    ok('wylogowano', 'Zaloguj się' in pg.inner_text('h1'))
    idz('dane'); ok('po wylogowaniu formularze są zamknięte', 'Zaloguj się' in pg.inner_text('h1'))
    pg.fill('input[name=haslo]', HASLO); pg.click('.zapisz button'); pg.wait_for_load_state('networkidle')
    ok('stare hasło już nie działa', 'To nie jest właściwe hasło' in pg.content())
    for i in range(6):
        pg.fill('input[name=haslo]', 'proba-numer-%d-xx' % i); pg.click('.zapisz button'); pg.wait_for_load_state('networkidle')
    ok('blokada po serii złych haseł', 'Za dużo nieudanych prób' in pg.content())
    pg.fill('input[name=haslo]', HASLO + '-nowe'); pg.click('.zapisz button'); pg.wait_for_load_state('networkidle')
    ok('w czasie blokady nie wpuszcza nawet dobre hasło', 'Zaloguj się' in pg.inner_text('h1'))
    zrzut('17-blokada-logowania')
    # Odpowiedzi 4xx na błędnie wypełnione formularze są zamierzone; liczą się inne błędy (np. blokady CSP).
    inne = [x for x in bledy if not re.search(r'status of 4(01|22|29)', x)]
    ok('brak błędów w konsoli przeglądarki', not inne, inne)
    b.close()

zle = [n for n, w in wyniki if not w]
print('\nRazem: %d testów, błędów: %d' % (len(wyniki), len(zle)))
sys.exit(1 if zle else 0)
