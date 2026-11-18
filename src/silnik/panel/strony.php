<?php
declare(strict_types=1);

/* Panel: obsługa poszczególnych ekranów. */

if (!defined('PANEL')) {
    http_response_code(404);
    exit;
}

/* ---------- wejście ---------- */

function p_panel(string $www): never
{
    // Ostrzeżenia PHP trafiają do dziennika serwera, nie na ekran panelu.
    ini_set('display_errors', '0');
    date_default_timezone_set('Europe/Warsaw');
    try {
        $dane = psy_przygotuj_dane($www);
        $GLOBALS['PSY_TRESC'] = $dane . '/tresc';
        if (!is_file($dane . '/ostatnie.json')) {
            generuj_strone($www);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        p_naglowki();
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Panel</title><p>Panel nie może teraz działać: ' . e($e->getMessage()) . '</p>';
        exit;
    }
    $aktywacja = p_aktywacja($www, $dane);
    p_sesja($dane);
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    if ($post && !p_csrf_ok()) {
        p_uklad('Formularz wygasł', '<h1>Formularz wygasł</h1><p>Strona była otwarta zbyt długo albo sesja się skończyła. Nic nie zostało zapisane.</p><p><a class="btn btn--kontur" href="/panel/">Wróć do panelu</a></p>', null, 400, false);
    }
    if (!p_zalogowana($dane)) {
        p_haslo_hash($dane) === null ? p_s_ustaw_haslo($dane, $aktywacja, $post) : p_s_logowanie($dane, $post);
    }
    // Każde wejście przedłuża ważność logowania na tym urządzeniu.
    setcookie((string) session_name(), (string) session_id(), ['expires' => time() + P_SESJA_DNI * 86400] + p_ciasteczko());

    $K = ['www' => $www, 'dane' => $dane, 'post' => $post];
    $s = p_linia($_GET['s'] ?? '', 30);
    match ($s) {
        '' => p_s_start($K),
        'dane' => p_s_dane($K),
        'komunikat' => p_s_komunikat($K),
        'cennik' => p_s_cennik($K),
        'pytania' => p_s_pytania($K),
        'badania' => p_s_badania($K),
        'badanie' => p_s_badanie($K),
        'poradnik' => p_s_poradnik($K),
        'artykul' => p_s_artykul($K),
        'usun-artykul' => p_s_usun_artykul($K),
        'polityka' => p_s_polityka($K),
        'google' => p_s_google($K),
        'historia' => p_s_historia($K),
        'haslo' => p_s_haslo($K),
        'wyloguj' => p_s_wyloguj($K),
        default => p_uklad('Nie ma takiej strony', '<h1>Nie ma takiej strony</h1><p><a class="btn btn--kontur" href="/panel/">Wróć do panelu</a></p>', null, 404),
    };
}

/** Wynik zapisu → komunikat i przekierowanie; przy błędzie zwraca jego treść do pokazania nad formularzem. */
function p_po_zapisie(string $wynik, string $powrot, ?string $naStronie = null, string $ok = 'Zapisano. Zmiany są już na stronie.'): string
{
    if ($wynik === 'ok') {
        p_info($ok, 'ok', $naStronie);
        p_przekieruj($powrot);
    }
    if ($wynik === 'bez-zmian') {
        p_info('Nic się nie zmieniło, więc nie było czego zapisywać.', 'uwaga');
        p_przekieruj($powrot);
    }
    return $wynik;
}

function p_blad_zapisu(string $blad): string
{
    return $blad === '' ? '' : '<div class="info info--blad" role="alert"><p>' . e($blad) . '</p></div>';
}

/* ---------- hasło i logowanie ---------- */

function p_pole_hasla(string $nazwa, string $etykieta, string $auto, string $blad = '', string $opis = ''): string
{
    return p_pole($nazwa, $etykieta, '', ['typ' => 'password', 'auto' => $auto, 'blad' => $blad, 'opis' => $opis]);
}

function p_s_ustaw_haslo(string $dane, ?array $aktywacja, bool $post): never
{
    if (!p_mozna_ustawic_haslo($dane, $aktywacja)) {
        p_uklad('Panel czeka na włączenie', '<h1>Panel czeka na włączenie</h1><p>Minął czas na ustawienie hasła. Poproś osobę, która przygotowała stronę, o ponowne włączenie panelu.</p>', null, 403, false);
    }
    $blad = '';
    if ($post) {
        $h1 = is_string($_POST['haslo'] ?? null) ? $_POST['haslo'] : '';
        $h2 = is_string($_POST['haslo2'] ?? null) ? $_POST['haslo2'] : '';
        $blad = p_haslo_blad($h1, $h2) ?? '';
        if ($blad === '') {
            // Tryb "x": hasło ustawia tylko pierwsze zgłoszenie, drugie równoczesne już go nie nadpisze.
            $f = @fopen($dane . '/haslo.json', 'x');
            if ($f === false) {
                p_przekieruj('/panel/');
            }
            $hash = password_hash($h1, PASSWORD_DEFAULT);
            fwrite($f, (string) json_encode(['hash' => $hash, 'od' => date('c')]));
            fclose($f);
            @chmod($dane . '/haslo.json', 0600);
            p_zaloguj($hash);
            p_info('Hasło ustawione. Od teraz do panelu wchodzi się tylko z tym hasłem.');
            p_przekieruj('/panel/');
        }
    }
    $h = '<h1>Ustaw hasło do panelu</h1>'
        . '<p>Tym hasłem będziesz wchodzić do panelu, w którym zmienia się treść strony. Wymyśl je samodzielnie i nikomu nie podawaj.</p>'
        . p_form_start('/panel/', 'form form--waski')
        . p_pole_hasla('haslo', 'Nowe hasło', 'new-password', $blad, 'Co najmniej ' . P_HASLO_MIN . ' znaków. Dobrze sprawdzają się trzy przypadkowe słowa.')
        . p_pole_hasla('haslo2', 'Powtórz hasło', 'new-password')
        . '<label class="ptaszek"><input type="checkbox" data-pokaz-hasla> Pokaż wpisywane hasło</label>'
        . '<p class="drobne">Gdy telefon zaproponuje zapamiętanie hasła, zgódź się. Nie trzeba go będzie wpisywać za każdym razem.</p>'
        . p_form_koniec('Ustaw hasło');
    p_uklad('Ustaw hasło', $h, null, $blad === '' ? 200 : 422, false);
}

function p_s_logowanie(string $dane, bool $post): never
{
    $blad = '';
    $czekaj = p_proby($dane);
    if ($post && $czekaj === 0) {
        $h = is_string($_POST['haslo'] ?? null) ? $_POST['haslo'] : '';
        $hash = p_haslo_hash($dane);
        if ($hash !== null && $h !== '' && strlen($h) <= 200 && password_verify($h, $hash)) {
            p_proby($dane, 'ok');
            p_zaloguj($hash);
            p_przekieruj('/panel/');
        }
        $czekaj = p_proby($dane, 'blad');
        $blad = 'To nie jest właściwe hasło.';
    }
    if ($czekaj > 0) {
        $blad = 'Za dużo nieudanych prób. Spróbuj ponownie za ' . (int) ceil($czekaj / 60) . ' min.';
    }
    $h = '<h1>Zaloguj się</h1>'
        . p_form_start('/panel/', 'form form--waski')
        . p_pole_hasla('haslo', 'Hasło do panelu', 'current-password', $blad)
        . '<label class="ptaszek"><input type="checkbox" data-pokaz-hasla> Pokaż wpisywane hasło</label>'
        . p_form_koniec('Zaloguj się')
        . '<p class="drobne">Nie pamiętasz hasła? Osoba, która przygotowała stronę, może włączyć ustawianie hasła od nowa.</p>';
    p_uklad('Logowanie', $h, null, $czekaj > 0 ? 429 : ($blad === '' ? 200 : 401), false);
}

function p_s_haslo(array $K): never
{
    $bledy = [];
    if ($K['post']) {
        $stare = is_string($_POST['stare'] ?? null) ? $_POST['stare'] : '';
        $h1 = is_string($_POST['haslo'] ?? null) ? $_POST['haslo'] : '';
        $h2 = is_string($_POST['haslo2'] ?? null) ? $_POST['haslo2'] : '';
        $hash = p_haslo_hash($K['dane']);
        if (p_proby($K['dane']) > 0) {
            $bledy['stare'] = 'Za dużo nieudanych prób. Spróbuj ponownie za kwadrans.';
        } elseif ($hash === null || !password_verify($stare, $hash)) {
            p_proby($K['dane'], 'blad');
            $bledy['stare'] = 'To nie jest obecne hasło.';
        } elseif (($b = p_haslo_blad($h1, $h2)) !== null) {
            $bledy['haslo'] = $b;
        } else {
            $nowy = p_haslo_zapisz($K['dane'], $h1);
            p_zaloguj($nowy);
            p_info('Hasło zmienione. Na innych urządzeniach trzeba zalogować się ponownie.');
            p_przekieruj('/panel/');
        }
    }
    $h = '<h1>Zmień hasło</h1>'
        . p_bledy_gora($bledy)
        . p_form_start(p_adres('haslo'), 'form form--waski')
        . p_pole_hasla('stare', 'Obecne hasło', 'current-password', $bledy['stare'] ?? '')
        . p_pole_hasla('haslo', 'Nowe hasło', 'new-password', $bledy['haslo'] ?? '', 'Co najmniej ' . P_HASLO_MIN . ' znaków.')
        . p_pole_hasla('haslo2', 'Powtórz nowe hasło', 'new-password')
        . '<label class="ptaszek"><input type="checkbox" data-pokaz-hasla> Pokaż wpisywane hasła</label>'
        . p_form_koniec('Zmień hasło');
    p_uklad('Zmień hasło', $h, ['/panel/', 'Panel'], $bledy ? 422 : 200);
}

function p_s_wyloguj(array $K): never
{
    if ($K['post']) {
        $_SESSION = [];
        session_destroy();
        setcookie((string) session_name(), '', ['expires' => 1] + p_ciasteczko());
    }
    p_przekieruj('/panel/');
}

/* ---------- start ---------- */

/** Pola „Danych pracowni”: grupa => [[klucz, etykieta, opcje]]. */
function p_pola_dane(): array
{
    return [
        'Kontakt i dojazd' => [
            ['telefon', 'Telefon', ['typ' => 'tel', 'opis' => 'Numer, pod który dzwonią klienci. Przycisk „Zadzwoń” na stronie wybiera go od razu.']],
            ['email', 'E-mail', ['typ' => 'email']],
            ['ulica', 'Ulica i numer', []],
            ['kod', 'Kod pocztowy', []],
            ['dojazd', 'Jak dojechać', ['typ' => 'textarea', 'opis' => 'Najbliższe skrzyżowanie lub przystanek, wejście do budynku, piętro.']],
            ['parking', 'Parking', ['opis' => 'Jedno zdanie, bez kropki na końcu.']],
        ],
        'Badanie' => [
            ['czas', 'Ile trwa badanie kierowcy', []],
            ['czas_operator', 'Ile trwa badanie operatora', []],
            ['orzeczenie', 'Kiedy klient dostaje orzeczenie', []],
            ['terminy', 'Najbliższy wolny termin', ['opis' => 'Ile zwykle czeka się na badanie.']],
            ['platnosci', 'Formy płatności', ['opis' => 'Po przecinku, bez kropki na końcu.']],
        ],
        'Pracownia' => [
            ['nazwa_firmy', 'Pełna nazwa pracowni', ['opis' => 'Tak jak w rejestrze i na fakturach.']],
            ['nip', 'NIP', []],
            ['wpis_pracowni', 'Numer wpisu pracowni', ['opis' => 'W rejestrze pracowni psychologicznych Marszałka Województwa Śląskiego.']],
            ['wpis_psychologa', 'Numer psychologa w ewidencji', ['opis' => 'W ewidencji psychologów transportu Marszałka Województwa Śląskiego.']],
            ['notka', 'O psychologu', ['typ' => 'textarea', 'wiersze' => 6, 'opis' => 'Kilka zdań: wykształcenie, studia podyplomowe z psychologii transportu, doświadczenie w badaniach kierowców.']],
        ],
    ];
}

/** Co jest jeszcze do uzupełnienia: ['dane' => [etykiety], 'cennik' => liczba, 'inne' => [pole => podstrona]]. */
function p_braki(array $stan): array
{
    $norm = static fn(string $s): string => mb_strtolower(trim(str_replace("\u{00A0}", ' ', czysty($s))));
    $dane = [];
    $znane = [];
    foreach (p_pola_dane() as $pola) {
        foreach ($pola as [$k, $et]) {
            if (brak((string) ust($k))) {
                $dane[] = $et;
            }
        }
    }
    foreach (ust() as $v) {
        if (is_string($v) && str_contains($v, '[[')) {
            $znane[$norm($v)] = true;
        }
    }
    $godziny = false;
    foreach ((array) ust('godziny') as $g) {
        if (brak((string) ($g[1] ?? ''))) {
            $godziny = true;
            $znane[$norm((string) ($g[1] ?? ''))] = true;
        }
    }
    if ($godziny) {
        $dane[] = 'Godziny otwarcia';
    }
    $cennik = 0;
    foreach (cennik() as $p) {
        if (brak((string) ($p['cena'] ?? ''))) {
            $cennik++;
            $znane[$norm((string) ($p['cena'] ?? ''))] = true;
        }
    }
    $inne = [];
    foreach ((array) ($stan['pola'] ?? []) as $pole => $sciezka) {
        if (!isset($znane[$norm((string) $pole)])) {
            $inne[(string) $pole] = (string) $sciezka;
        }
    }
    return ['dane' => $dane, 'cennik' => $cennik, 'inne' => $inne];
}

function p_liczba_pol(int $n): string
{
    return $n === 1 ? '1 pole' : (($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20)) ? $n . ' pola' : $n . ' pól');
}

function p_s_start(array $K): never
{
    $stan = p_stan($K['dane']);
    $b = p_braki($stan);
    $ile = count($b['dane']) + $b['cennik'] + count($b['inne']);
    $ukryta = (bool) ust('ukryj_przed_google');

    $h = '<h1>Co chcesz zmienić?</h1>';
    if ($ile > 0) {
        $h .= '<div class="objazd"><div class="objazd__pole"><strong>Do uzupełnienia: ' . p_liczba_pol($ile) . '</strong>'
            . '<p>Na stronie są zaznaczone na żółto. Dopóki są puste, strona jest ukryta przed Google.</p>'
            . '<p><a href="' . e(p_adres('google')) . '">Zobacz, czego brakuje</a></p></div></div>';
    } elseif ($ukryta) {
        $h .= '<div class="objazd"><div class="objazd__pole"><strong>Wszystko uzupełnione</strong>'
            . '<p>Strona jest jeszcze ukryta przed Google.</p><p><a href="' . e(p_adres('google')) . '">Pokaż stronę w Google</a></p></div></div>';
    }
    $pozycje = [
        ['dane', 'Dane pracowni', 'Telefon, adres, godziny, numery wpisów', count($b['dane'])],
        ['komunikat', 'Komunikat na stronie głównej', 'Żółta tablica z ważną informacją', 0],
        ['cennik', 'Cennik', 'Pozycje i ceny badań', $b['cennik']],
        ['pytania', 'Pytania i odpowiedzi', 'Lista najczęstszych pytań', 0],
        ['badania', 'Strony badań', 'Teksty siedmiu podstron z ofertą', 0],
        ['poradnik', 'Poradnik', 'Artykuły: dodawanie i poprawki', 0],
        ['google', 'Widoczność w Google', $ukryta ? 'Strona jest ukryta' : 'Strona jest widoczna', 0],
        ['historia', 'Cofnij zmianę', 'Poprzednie wersje treści', 0],
    ];
    $h .= '<nav aria-label="Części panelu"><ul class="menu">';
    foreach ($pozycje as [$s, $nazwa, $opis, $n]) {
        $h .= '<li><a href="' . e(p_adres($s)) . '"><span class="menu__nazwa">' . e($nazwa) . ($n > 0 ? ' <span class="znacznik">' . p_liczba_pol($n) . '</span>' : '') . '</span><span class="menu__opis">' . e($opis) . '</span></a></li>';
    }
    $h .= '</ul></nav>';
    $h .= '<div class="dolne"><a href="' . e(p_adres('polityka')) . '">Polityka prywatności</a><a href="' . e(p_adres('haslo')) . '">Zmień hasło</a>'
        . p_form_start(p_adres('wyloguj'), 'wyloguj') . '<button type="submit" class="jak-link">Wyloguj</button></form></div>';
    p_uklad('Start', $h);
}

/* ---------- dane pracowni ---------- */

function p_nip_ok(string $nip): bool
{
    if (!preg_match('/^\d{10}$/', $nip)) {
        return false;
    }
    $wagi = [6, 5, 7, 2, 3, 4, 5, 6, 7];
    $suma = 0;
    foreach ($wagi as $i => $w) {
        $suma += $w * (int) $nip[$i];
    }
    return $suma % 11 === (int) $nip[9];
}

function p_s_dane(array $K): never
{
    $bledy = [];
    $blad = '';
    $wart = [];
    foreach (p_pola_dane() as $pola) {
        foreach ($pola as [$k]) {
            $wart[$k] = p_wartosc((string) ust($k));
        }
    }
    $godziny = array_map(static fn($g): array => [(string) ($g[0] ?? ''), (string) ($g[1] ?? '')], (array) ust('godziny'));

    if ($K['post']) {
        $zywe = ust_zywe();
        foreach (p_pola_dane() as $pola) {
            foreach ($pola as [$k, , $o]) {
                $v = ($o['typ'] ?? '') === 'textarea' ? p_wiele($_POST[$k] ?? '', 3000) : p_linia($_POST[$k] ?? '');
                $wart[$k] = $v;
                if ($v === '' && p_podpowiedz($k) !== '') {
                    unset($zywe[$k]);
                } else {
                    $zywe[$k] = $v;
                }
            }
        }
        if ($wart['telefon'] !== '') {
            $cyfry = preg_replace('/\D+/', '', $wart['telefon']) ?? '';
            if (strlen($cyfry) < 9 || strlen($cyfry) > 15 || !preg_match('/^[\d +()-]+$/', $wart['telefon'])) {
                $bledy['telefon'] = 'Wpisz numer telefonu, np. 500 600 700.';
            }
        }
        if ($wart['email'] !== '' && filter_var($wart['email'], FILTER_VALIDATE_EMAIL) === false) {
            $bledy['email'] = 'To nie wygląda na adres e-mail. Sprawdź, czy jest w nim znak @.';
        }
        if ($wart['kod'] !== '' && !preg_match('/^\d{2}-\d{3}$/', $wart['kod'])) {
            $bledy['kod'] = 'Kod pocztowy ma postać 42-200.';
        }
        if ($wart['nip'] !== '') {
            $nip = preg_replace('/[\s-]+/', '', $wart['nip']) ?? '';
            if (!p_nip_ok($nip)) {
                $bledy['nip'] = 'NIP ma 10 cyfr. Ten numer nie przechodzi kontroli, sprawdź, czy nie ma literówki.';
            } else {
                $zywe['nip'] = $wart['nip'] = $nip;
            }
        }
        $godziny = [];
        $doZapisu = [];
        $gd = p_lista('gd');
        $gg = p_lista('gg');
        foreach ($gd as $i => $dni) {
            $dni = p_linia($dni, 60);
            $godz = p_linia($gg[$i] ?? '', 60);
            if ($dni === '' && $godz === '') {
                continue;
            }
            $godziny[] = [$dni, $godz];
            if ($dni === '') {
                $bledy['godziny'] = 'W każdym wierszu godzin wpisz dni, np. „poniedziałek – piątek”.';
            }
            $doZapisu[] = [$dni, $godz === '' ? '[[godziny]]' : $godz];
        }
        if (!$doZapisu) {
            unset($zywe['godziny']);
        } else {
            $zywe['godziny'] = $doZapisu;
        }
        if (!$bledy) {
            $blad = p_po_zapisie(p_zastosuj($K['www'], $K['dane'], ['ustawienia.json' => p_json($zywe)]), p_adres('dane'), '/kontakt/');
        }
    }

    $h = '<h1>Dane pracowni</h1><p class="wstep">Te dane pojawiają się w wielu miejscach strony: w nagłówku, stopce, na stronie kontaktu i w danych dla Google.</p>'
        . p_bledy_gora($bledy) . p_blad_zapisu($blad) . p_form_start(p_adres('dane'));
    foreach (p_pola_dane() as $grupa => $pola) {
        $h .= '<h2>' . e($grupa) . '</h2>';
        foreach ($pola as [$k, $et, $o]) {
            $h .= p_pole($k, $et, $wart[$k], $o + ['podpowiedz' => p_podpowiedz($k), 'brak' => $wart[$k] === '' && p_podpowiedz($k) !== '', 'blad' => $bledy[$k] ?? '']);
        }
        if ($grupa === 'Kontakt i dojazd') {
            $h .= '<h2>Godziny otwarcia</h2><p class="pole__opis">Godziny wpisz w postaci 8:00–16:00. Możesz też napisać słowami, np. „po umówieniu” albo „nieczynne”.</p>'
                . (isset($bledy['godziny']) ? '<p class="pole__blad">' . e($bledy['godziny']) . '</p>' : '')
                . p_wiersze(
                    $godziny,
                    static fn(array $g, string $nr): string => p_pole('gd[]', 'Dni', $g[0], ['nr' => $nr, 'podpowiedz' => 'poniedziałek – piątek'])
                        . p_pole('gg[]', 'Godziny', p_wartosc($g[1]), ['nr' => $nr, 'podpowiedz' => brak($g[1]) && czysty($g[1]) !== 'godziny' && $g[1] !== '' ? czysty($g[1]) : '8:00–16:00', 'brak' => $g[0] !== '' && brak($g[1])]),
                    ['', ''],
                    'Dodaj wiersz godzin',
                    'Wiersz'
                );
        }
    }
    p_uklad('Dane pracowni', $h . p_form_koniec(), ['/panel/', 'Panel'], $bledy || $blad !== '' ? 422 : 200);
}

/* ---------- komunikat ---------- */

function p_linki_strony(): array
{
    $l = ['/cennik/' => 'Cennik', '/kontakt/' => 'Kontakt', '/pytania/' => 'Pytania i odpowiedzi', '/poradnik/' => 'Poradnik'];
    foreach (USLUGI as $s) {
        $l["/$s/"] = 'Badanie: ' . tekst(meta_uslugi($s)['meta']['nazwa'] ?? $s);
    }
    foreach (artykuly() as $a) {
        $l['/poradnik/' . $a['slug'] . '/'] = 'Artykuł: ' . tekst($a['meta']['h1']);
    }
    return $l;
}

function p_s_komunikat(array $K): never
{
    $bledy = [];
    $blad = '';
    $linki = p_linki_strony();
    $w = [
        'pokaz' => !empty(ust('komunikat_pokaz')),
        'tytul' => (string) ust('komunikat_tytul'),
        'tekst' => (string) ust('komunikat_tekst'),
        'link' => (string) ust('komunikat_link'),
        'link_tekst' => (string) ust('komunikat_link_tekst'),
    ];
    if ($K['post']) {
        $w = [
            'pokaz' => !empty($_POST['pokaz']),
            'tytul' => p_linia($_POST['tytul'] ?? '', 80),
            'tekst' => p_linia($_POST['tekst'] ?? '', 320),
            'link' => p_linia($_POST['link'] ?? '', 200),
            'link_tekst' => p_linia($_POST['link_tekst'] ?? '', 60),
        ];
        if ($w['link'] !== '' && !isset($linki[$w['link']])) {
            $bledy['link'] = 'Wybierz stronę z listy.';
        }
        if ($w['link'] !== '' && $w['link_tekst'] === '') {
            $bledy['link_tekst'] = 'Wpisz tekst linku, np. „Szczegóły”.';
        }
        if ($w['pokaz'] && $w['tytul'] === '' && $w['tekst'] === '') {
            $bledy['tekst'] = 'Wpisz treść komunikatu albo odznacz pokazywanie.';
        }
        if (!$bledy) {
            $zywe = ust_zywe();
            $zywe['komunikat_pokaz'] = $w['pokaz'];
            $zywe['komunikat_tytul'] = $w['tytul'];
            $zywe['komunikat_tekst'] = $w['tekst'];
            $zywe['komunikat_link'] = $w['link'];
            $zywe['komunikat_link_tekst'] = $w['link'] === '' ? '' : $w['link_tekst'];
            $blad = p_po_zapisie(p_zastosuj($K['www'], $K['dane'], ['ustawienia.json' => p_json($zywe)]), p_adres('komunikat'), '/');
        }
    }
    $opcje = '<option value="">Bez linku</option>';
    foreach ($linki as $adres => $nazwa) {
        $opcje .= '<option value="' . e($adres) . '"' . ($adres === $w['link'] ? ' selected' : '') . '>' . e($nazwa) . '</option>';
    }
    $h = '<h1>Komunikat na stronie głównej</h1><p class="wstep">Żółta tablica u góry strony głównej. Nadaje się na zmianę ceny, urlop albo nowe godziny.</p>'
        . p_bledy_gora($bledy) . p_blad_zapisu($blad) . p_form_start(p_adres('komunikat'))
        . '<label class="ptaszek"><input type="checkbox" name="pokaz" value="1"' . ($w['pokaz'] ? ' checked' : '') . '> Pokazuj komunikat na stronie</label>'
        . p_pole('tytul', 'Nagłówek', $w['tytul'], ['opis' => 'Krótko, np. „Urlop 1–14 sierpnia”.'])
        . p_pole('tekst', 'Treść', $w['tekst'], ['typ' => 'textarea', 'wiersze' => 3, 'opis' => 'Jedno lub dwa zdania.', 'blad' => $bledy['tekst'] ?? ''])
        . '<div class="pole' . (isset($bledy['link']) ? ' pole--blad' : '') . '"><label for="f-link">Link pod komunikatem</label><select id="f-link" name="link">' . $opcje . '</select>'
        . (isset($bledy['link']) ? '<p class="pole__blad">' . e($bledy['link']) . '</p>' : '') . '</div>'
        . p_pole('link_tekst', 'Tekst linku', $w['link_tekst'], ['blad' => $bledy['link_tekst'] ?? ''])
        . p_form_koniec();
    p_uklad('Komunikat', $h, ['/panel/', 'Panel'], $bledy || $blad !== '' ? 422 : 200);
}

/* ---------- cennik ---------- */

const P_CENY_W_TEKSTACH = ['cena_kierowcy', 'cena_operator'];

function p_s_cennik(array $K): never
{
    $bledy = [];
    $blad = '';
    $wiersze = array_map(static fn(array $p): array => [
        'nazwa' => (string) ($p['nazwa'] ?? ''), 'opis' => (string) ($p['opis'] ?? ''), 'cena' => (string) ($p['cena'] ?? ''), 'klucz' => (string) ($p['klucz'] ?? ''),
    ], cennik());

    if ($K['post']) {
        $wiersze = [];
        $cn = p_lista('cn');
        $co = p_lista('co');
        $cc = p_lista('cc');
        $ck = p_lista('ck');
        $klucze = [];
        foreach ($cn as $i => $nazwa) {
            $w = ['nazwa' => p_linia($nazwa, 120), 'opis' => p_linia($co[$i] ?? '', 400), 'cena' => p_linia($cc[$i] ?? '', 40), 'klucz' => p_linia($ck[$i] ?? '', 30)];
            if (!in_array($w['klucz'], P_CENY_W_TEKSTACH, true) || isset($klucze[$w['klucz']])) {
                $w['klucz'] = '';
            }
            if ($w['nazwa'] === '' && $w['opis'] === '' && $w['cena'] === '' && $w['klucz'] === '') {
                continue;
            }
            if ($w['klucz'] !== '') {
                $klucze[$w['klucz']] = true;
            }
            if (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $w['cena'])) {
                $w['cena'] .= ' zł';
            }
            if ($w['nazwa'] === '') {
                $w['blad'] = 'Wpisz nazwę badania.';
                $bledy['w' . $i] = true;
            }
            $wiersze[] = $w;
        }
        foreach (cennik() as $stary) {
            $k = (string) ($stary['klucz'] ?? '');
            if ($k !== '' && !isset($klucze[$k])) {
                // Cena tej pozycji jest wstawiana w teksty na stronie, więc pozycja musi zostać w cenniku.
                $wiersze[] = ['nazwa' => (string) $stary['nazwa'], 'opis' => (string) ($stary['opis'] ?? ''), 'cena' => (string) ($stary['cena'] ?? ''), 'klucz' => $k,
                    'blad' => 'Tej pozycji nie można usunąć, bo jej cena pojawia się w tekstach na stronie. Możesz zmienić nazwę, opis i cenę.'];
                $bledy['k' . $k] = true;
            }
        }
        if (!$bledy) {
            $zywe = ust_zywe();
            $doPliku = [];
            foreach ($wiersze as $w) {
                $cena = $w['cena'] === '' ? '[[… zł]]' : $w['cena'];
                $p = ['nazwa' => $w['nazwa'], 'opis' => $w['opis'], 'cena' => $cena];
                if ($w['klucz'] !== '') {
                    $p['klucz'] = $w['klucz'];
                    if ($w['cena'] === '' && p_podpowiedz($w['klucz']) !== '') {
                        unset($zywe[$w['klucz']]);
                    } else {
                        $zywe[$w['klucz']] = $cena;
                    }
                }
                $doPliku[] = $p;
            }
            $blad = p_po_zapisie(p_zastosuj($K['www'], $K['dane'], ['cennik.json' => p_json($doPliku), 'ustawienia.json' => p_json($zywe)]), p_adres('cennik'), '/cennik/');
        }
    }

    $h = '<h1>Cennik</h1><p class="wstep">Pozycje pokazują się na stronie głównej i na stronie „Cennik”. Cenę możesz wpisać samą liczbą, „zł” dopisze się samo.</p>'
        . p_bledy_gora($bledy) . p_blad_zapisu($blad) . p_form_start(p_adres('cennik'))
        . p_wiersze(
            $wiersze,
            static fn(array $w, string $nr): string => p_pole('cn[]', 'Nazwa badania', $w['nazwa'], ['nr' => $nr, 'blad' => $w['blad'] ?? ''])
                . p_pole('co[]', 'Opis', $w['opis'], ['nr' => $nr, 'typ' => 'textarea', 'wiersze' => 3, 'opis' => 'Dla kogo jest badanie, na jakie skierowanie.'])
                . p_pole('cc[]', 'Cena', p_wartosc($w['cena']), ['nr' => $nr, 'podpowiedz' => '200 zł', 'brak' => $w['nazwa'] !== '' && brak($w['cena']),
                    'opis' => $w['klucz'] !== '' ? 'Ta cena pojawia się też w tekstach na stronie. Zmienisz ją tutaj, a poprawi się wszędzie.' : ''])
                . '<input type="hidden" name="ck[]" value="' . e($w['klucz']) . '"' . ($w['klucz'] !== '' ? ' data-stala' : '') . '>',
            ['nazwa' => '', 'opis' => '', 'cena' => '', 'klucz' => ''],
            'Dodaj pozycję',
            'Pozycja'
        );
    p_uklad('Cennik', $h . p_form_koniec(), ['/panel/', 'Panel'], $bledy || $blad !== '' ? 422 : 200);
}

/* ---------- pytania ---------- */

function p_faq_bledy(array &$faq): bool
{
    $sa = false;
    foreach ($faq as &$x) {
        if ($x['p'] === '' || $x['o'] === '') {
            $x['blad'] = $x['p'] === '' ? 'Wpisz pytanie albo usuń odpowiedź.' : 'Dopisz odpowiedź albo usuń pytanie.';
            $sa = true;
        }
    }
    return $sa;
}

function p_s_pytania(array $K): never
{
    $bledy = [];
    $blad = '';
    $s = wczytaj_md(plik_tresci('pytania.md'), true);
    $faq = $s['faq'];
    if ($K['post']) {
        $faq = p_faq_z_formularza();
        if (p_faq_bledy($faq)) {
            $bledy['faq'] = true;
        } elseif (!$faq) {
            $bledy['faq'] = true;
            $faq = [['p' => '', 'o' => '', 'blad' => 'Strona pytań musi mieć co najmniej jedno pytanie.']];
        } else {
            $blad = p_po_zapisie(p_zastosuj($K['www'], $K['dane'], ['pytania.md' => p_md_zloz($s['meta'], $s['md'], $faq)]), p_adres('pytania'), '/pytania/');
        }
    }
    $h = '<h1>Pytania i odpowiedzi</h1><p class="wstep">Pierwsze siedem pytań pokazuje się też na stronie głównej. Odpowiadaj krótko, pierwszym zdaniem wprost na pytanie.</p>'
        . p_bledy_gora($bledy) . p_blad_zapisu($blad) . p_form_start(p_adres('pytania')) . p_faq_pola($faq) . p_sciaga();
    p_uklad('Pytania', $h . p_form_koniec(), ['/panel/', 'Panel'], $bledy || $blad !== '' ? 422 : 200);
}

/* ---------- strony badań ---------- */

function p_s_badania(array $K): never
{
    $h = '<h1>Strony badań</h1><p class="wstep">Każde badanie ma własną podstronę. Wybierz tę, którą chcesz poprawić.</p><ul class="menu">';
    foreach (USLUGI as $slug) {
        $m = wczytaj_md(plik_tresci("uslugi/$slug.md"), true)['meta'];
        $h .= '<li><a href="' . e(p_adres('badanie', ['id' => $slug])) . '"><span class="menu__nazwa">' . e($m['nazwa'] ?? $slug) . '</span><span class="menu__opis">' . e($m['h1'] ?? '') . '</span></a></li>';
    }
    p_uklad('Strony badań', $h . '</ul>', ['/panel/', 'Panel']);
}

function p_fakty_na_wiersze(string $fakty): string
{
    return implode("\n", array_filter(array_map('trim', explode('|', $fakty)), static fn(string $x): bool => $x !== ''));
}

function p_s_badanie(array $K): never
{
    $slug = p_linia($_GET['id'] ?? '', 100);
    if (!in_array($slug, USLUGI, true)) {
        p_przekieruj(p_adres('badania'));
    }
    $rel = "uslugi/$slug.md";
    $s = wczytaj_md(plik_tresci($rel), true);
    $m = $s['meta'] + ['nazwa' => '', 'krotko' => '', 'ikona' => '', 'tytul' => '', 'opis' => '', 'h1' => '', 'lead' => '', 'fakty' => '', 'cena' => ''];
    $tresc = $s['md'];
    $faq = $s['faq'];
    $fakty = p_fakty_na_wiersze($m['fakty']);
    $bledy = [];
    $blad = '';

    if ($K['post']) {
        $m['h1'] = p_linia($_POST['h1'] ?? '', 120);
        $m['lead'] = p_linia($_POST['lead'] ?? '', 700);
        $m['nazwa'] = p_linia($_POST['nazwa'] ?? '', 60);
        $m['krotko'] = p_linia($_POST['krotko'] ?? '', 260);
        $m['tytul'] = p_linia($_POST['tytul'] ?? '', 120);
        $m['opis'] = p_linia($_POST['opis'] ?? '', 320);
        $m['cena'] = p_linia($_POST['cena'] ?? '', 30);
        $tresc = p_wiele($_POST['tresc'] ?? '');
        $faq = p_faq_z_formularza();
        $fakty = p_wiele($_POST['fakty'] ?? '', 1500);
        $linie = [];
        foreach (explode("\n", $fakty) as $l) {
            $l = p_linia(str_replace('|', '/', $l), 200);
            if ($l === '') {
                continue;
            }
            if (!preg_match('/^[^=]+=.+$/u', $l)) {
                $bledy['fakty'] = 'Każdy wiersz ma mieć postać: nazwa = wartość, np. „Cena = 200 zł”.';
            }
            $linie[] = $l;
        }
        $m['fakty'] = implode(' | ', $linie);
        foreach (['h1' => 'Wpisz nagłówek strony.', 'lead' => 'Wpisz wstęp.', 'nazwa' => 'Wpisz krótką nazwę.', 'krotko' => 'Wpisz opis na kafelku.'] as $k => $kom) {
            if ($m[$k] === '') {
                $bledy[$k] = $kom;
            }
        }
        if ($tresc === '') {
            $bledy['tresc'] = 'Strona musi mieć treść.';
        }
        if ($m['tytul'] === '') {
            $m['tytul'] = $m['h1'] . ' – Częstochowa';
        }
        if ($m['opis'] === '') {
            $m['opis'] = mb_substr($m['lead'], 0, 155);
        }
        if (p_faq_bledy($faq)) {
            $bledy['faq'] = true;
        }
        if (!$bledy) {
            $doPliku = $m;
            if ($doPliku['cena'] === '') {
                unset($doPliku['cena']);
            }
            $blad = p_po_zapisie(p_zastosuj($K['www'], $K['dane'], [$rel => p_md_zloz($doPliku, $tresc, $faq)]), p_adres('badanie', ['id' => $slug]), "/$slug/");
        }
    }

    $h = '<h1>' . e($m['nazwa'] !== '' ? $m['nazwa'] : $slug) . '</h1><p class="wstep">Podstrona <a href="/' . e($slug) . '/" target="_blank" rel="noopener">/' . e($slug) . '/</a></p>'
        . p_bledy_gora($bledy) . p_blad_zapisu($blad) . p_form_start(p_adres('badanie', ['id' => $slug]))
        . p_pole('h1', 'Nagłówek strony', $m['h1'], ['blad' => $bledy['h1'] ?? ''])
        . p_pole('lead', 'Wstęp pod nagłówkiem', $m['lead'], ['typ' => 'textarea', 'wiersze' => 4, 'blad' => $bledy['lead'] ?? '', 'opis' => 'Dwa lub trzy zdania: dla kogo jest badanie i co klient dostaje.'])
        . p_pole('tresc', 'Treść strony', $tresc, ['typ' => 'textarea', 'wiersze' => 18, 'blad' => $bledy['tresc'] ?? '', 'klasa' => 'pole--dlugie'])
        . p_sciaga()
        . '<h2>Pytania na tej stronie</h2>' . p_faq_pola($faq)
        . '<h2>Kafelek na stronie głównej</h2>'
        . p_pole('nazwa', 'Krótka nazwa', $m['nazwa'], ['blad' => $bledy['nazwa'] ?? ''])
        . p_pole('krotko', 'Opis na kafelku', $m['krotko'], ['typ' => 'textarea', 'wiersze' => 3, 'blad' => $bledy['krotko'] ?? ''])
        . '<details class="wiecej"' . (isset($bledy['fakty']) ? ' open' : '') . '><summary>Ustawienia dla Google i tabliczka z faktami</summary>'
        . p_pole('tytul', 'Tytuł w wynikach Google', $m['tytul'], ['licznik' => 60, 'opis' => 'Najważniejsze słowa na początku, na końcu „Częstochowa”.'])
        . p_pole('opis', 'Opis w wynikach Google', $m['opis'], ['typ' => 'textarea', 'wiersze' => 3, 'licznik' => 155])
        . p_pole('fakty', 'Tabliczka z faktami pod nagłówkiem', $fakty, ['typ' => 'textarea', 'wiersze' => 4, 'blad' => $bledy['fakty'] ?? '', 'opis' => 'Jeden fakt w wierszu: nazwa = wartość. Drobny dopisek po znaku ~, np. „Ważność = 5 lat ~ po 60. roku życia 30 miesięcy”.'])
        . p_pole('cena', 'Cena w danych dla Google', $m['cena'], ['opis' => 'Sama liczba, np. 200. Zostaw puste, jeśli ceny nie podajesz.'])
        . '</details>';
    p_uklad((string) $m['nazwa'], $h . p_form_koniec(), [p_adres('badania'), 'Strony badań'], $bledy || $blad !== '' ? 422 : 200);
}

/* ---------- poradnik ---------- */

function p_s_poradnik(array $K): never
{
    $h = '<h1>Poradnik</h1><p class="wstep">Artykuły odpowiadają na pytania, które kierowcy wpisują w Google. Nowy artykuł co kilka tygodni pomaga stronie.</p>'
        . '<p><a class="btn btn--zolty" href="' . e(p_adres('artykul', ['nowy' => '1'])) . '">Dodaj artykuł</a></p><ul class="menu">';
    foreach (artykuly() as $a) {
        $h .= '<li><a href="' . e(p_adres('artykul', ['id' => $a['slug']])) . '"><span class="menu__nazwa">' . e(tekst($a['meta']['h1'])) . '</span><span class="menu__opis">' . e(data_pl($a['meta']['data'])) . '</span></a></li>';
    }
    if (!artykuly()) {
        $h .= '<li class="menu__pusto">Nie ma jeszcze żadnego artykułu.</li>';
    }
    p_uklad('Poradnik', $h . '</ul>', ['/panel/', 'Panel']);
}

function p_data_ok(string $d): bool
{
    return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 2020 && (int) $m[1] <= 2100;
}

function p_s_artykul(array $K): never
{
    $nowy = !empty($_GET['nowy']);
    $slug = p_linia($_GET['id'] ?? '', 100);
    $pliki = artykuly_pliki();
    if (!$nowy && !isset($pliki[$slug])) {
        p_przekieruj(p_adres('poradnik'));
    }
    $puste = ['tytul' => '', 'opis' => '', 'h1' => '', 'lead' => '', 'skrot' => '', 'data' => date('Y-m-d')];
    $s = $nowy ? ['meta' => [], 'md' => '', 'faq' => []] : wczytaj_md($pliki[$slug], true);
    $m = $s['meta'] + $puste;
    $tresc = $s['md'];
    $faq = $s['faq'];
    $bledy = [];
    $blad = '';
    $akcja = $nowy ? p_adres('artykul', ['nowy' => '1']) : p_adres('artykul', ['id' => $slug]);

    if ($K['post']) {
        $m['h1'] = p_linia($_POST['h1'] ?? '', 120);
        $m['lead'] = p_linia($_POST['lead'] ?? '', 700);
        $m['skrot'] = p_linia($_POST['skrot'] ?? '', 260);
        $m['data'] = p_linia($_POST['data'] ?? '', 10);
        $m['tytul'] = p_linia($_POST['tytul'] ?? '', 120);
        $m['opis'] = p_linia($_POST['opis'] ?? '', 320);
        $tresc = p_wiele($_POST['tresc'] ?? '');
        $faq = p_faq_z_formularza();
        foreach (['h1' => 'Wpisz tytuł artykułu.', 'lead' => 'Wpisz wstęp.'] as $k => $kom) {
            if ($m[$k] === '') {
                $bledy[$k] = $kom;
            }
        }
        if ($tresc === '') {
            $bledy['tresc'] = 'Artykuł musi mieć treść.';
        }
        if (!p_data_ok($m['data'])) {
            $bledy['data'] = 'Wybierz datę publikacji.';
        }
        if (p_faq_bledy($faq)) {
            $bledy['faq'] = true;
        }
        if ($m['skrot'] === '') {
            $m['skrot'] = mb_substr($m['lead'], 0, 200);
        }
        if ($m['tytul'] === '') {
            $m['tytul'] = $m['h1'];
        }
        if ($m['opis'] === '') {
            $m['opis'] = mb_substr($m['skrot'], 0, 155);
        }
        if ($nowy && !isset($bledy['h1'])) {
            $slug = substr(slug($m['h1']), 0, 80);
            $slug = trim($slug, '-');
            if (strlen($slug) < 3) {
                $bledy['h1'] = 'Tytuł musi zawierać słowa, bo powstaje z niego adres artykułu.';
            } else {
                $zajete = $pliki + array_flip(array_map(static fn(string $p): string => basename($p, '.md'), glob(TRESC_START . '/poradnik/*.md') ?: []));
                $baza = $slug;
                for ($i = 2; isset($zajete[$slug]); $i++) {
                    $slug = $baza . '-' . $i;
                }
            }
        }
        if (!$bledy) {
            $wynik = p_zastosuj($K['www'], $K['dane'], ["poradnik/$slug.md" => p_md_zloz($m, $tresc, $faq)]);
            $blad = p_po_zapisie($wynik, p_adres('artykul', ['id' => $slug]), "/poradnik/$slug/", $nowy ? 'Artykuł jest już na stronie.' : 'Zapisano. Zmiany są już na stronie.');
        }
    }

    $h = '<h1>' . ($nowy ? 'Nowy artykuł' : 'Artykuł') . '</h1>'
        . ($nowy ? '<p class="wstep">Adres artykułu powstanie z tytułu i potem się nie zmienia.</p>' : '<p class="wstep">Adres: <a href="/poradnik/' . e($slug) . '/" target="_blank" rel="noopener">/poradnik/' . e($slug) . '/</a></p>')
        . p_bledy_gora($bledy) . p_blad_zapisu($blad) . p_form_start($akcja)
        . p_pole('h1', 'Tytuł artykułu', $m['h1'], ['blad' => $bledy['h1'] ?? '', 'opis' => 'Najlepiej pytanie albo temat, którego ludzie szukają, np. „Ile trwają psychotesty kierowcy”.'])
        . p_pole('lead', 'Wstęp', $m['lead'], ['typ' => 'textarea', 'wiersze' => 4, 'blad' => $bledy['lead'] ?? '', 'opis' => 'Dwa lub trzy zdania z odpowiedzią na pytanie z tytułu.'])
        . p_pole('tresc', 'Treść', $tresc, ['typ' => 'textarea', 'wiersze' => 18, 'blad' => $bledy['tresc'] ?? '', 'klasa' => 'pole--dlugie'])
        . p_sciaga()
        . p_pole('skrot', 'Zapowiedź na liście artykułów', $m['skrot'], ['typ' => 'textarea', 'wiersze' => 2, 'opis' => 'Jedno zdanie. Puste pole wypełni się początkiem wstępu.'])
        . p_pole('data', 'Data publikacji', $m['data'], ['typ' => 'date', 'blad' => $bledy['data'] ?? ''])
        . '<h2>Pytania pod artykułem</h2><p class="pole__opis">Nieobowiązkowe.</p>' . p_faq_pola($faq)
        . '<details class="wiecej"><summary>Ustawienia dla Google</summary>'
        . p_pole('tytul', 'Tytuł w wynikach Google', $m['tytul'], ['licznik' => 60, 'opis' => 'Puste pole wypełni się tytułem artykułu.'])
        . p_pole('opis', 'Opis w wynikach Google', $m['opis'], ['typ' => 'textarea', 'wiersze' => 3, 'licznik' => 155, 'opis' => 'Puste pole wypełni się zapowiedzią.'])
        . '</details>' . p_form_koniec($nowy ? 'Opublikuj artykuł' : 'Zapisz zmiany');
    if (!$nowy) {
        $h .= '<p class="usun"><a href="' . e(p_adres('usun-artykul', ['id' => $slug])) . '">Usuń ten artykuł</a></p>';
    }
    p_uklad($nowy ? 'Nowy artykuł' : 'Artykuł', $h, [p_adres('poradnik'), 'Poradnik'], $bledy || $blad !== '' ? 422 : 200);
}

function p_s_usun_artykul(array $K): never
{
    $slug = p_linia($_GET['id'] ?? '', 100);
    $pliki = artykuly_pliki();
    if (!isset($pliki[$slug]) || !preg_match('/^[a-z0-9][a-z0-9-]{1,90}$/', $slug)) {
        p_przekieruj(p_adres('poradnik'));
    }
    $tytul = tekst(wczytaj_md($pliki[$slug], true)['meta']['h1'] ?? $slug);
    $blad = '';
    if ($K['post']) {
        $zmiany = ["poradnik/$slug.md" => null];
        if (is_file(TRESC_START . "/poradnik/$slug.md")) {
            $usuniete = json_decode((string) @file_get_contents($K['dane'] . '/tresc/poradnik/_usuniete.json'), true);
            $usuniete = array_values(array_unique(array_merge(is_array($usuniete) ? $usuniete : [], [$slug])));
            $zmiany['poradnik/_usuniete.json'] = p_json($usuniete);
            if (!is_file($K['dane'] . "/tresc/poradnik/$slug.md")) {
                // Artykuł startowy bez zmian z panelu: kopia jego treści trafia do historii, żeby dało się go przywrócić.
                p_kopia($K['dane'], "poradnik/$slug.md", (string) file_get_contents($pliki[$slug]));
            }
        }
        if (ust('komunikat_link') === "/poradnik/$slug/") {
            $zywe = ust_zywe();
            $zywe['komunikat_link'] = '';
            $zywe['komunikat_link_tekst'] = '';
            $zmiany['ustawienia.json'] = p_json($zywe);
        }
        $wynik = p_zastosuj($K['www'], $K['dane'], $zmiany);
        if ($wynik === 'ok' || $wynik === 'bez-zmian') {
            p_info('Artykuł usunięty. Można go przywrócić w części „Cofnij zmianę”.');
            p_przekieruj(p_adres('poradnik'));
        }
        $blad = $wynik;
    }
    $h = '<h1>Usunąć artykuł?</h1>' . p_blad_zapisu($blad) . '<p>„' . e($tytul) . '” zniknie ze strony. Jeśli prowadzą do niego linki z innych tekstów, przestaną działać.</p>'
        . p_form_start(p_adres('usun-artykul', ['id' => $slug]), 'form form--waski')
        . '<div class="przyciski"><button class="btn btn--usun" type="submit">Tak, usuń</button><a class="btn btn--kontur" href="' . e(p_adres('artykul', ['id' => $slug])) . '">Nie, zostaw</a></div></form>';
    p_uklad('Usuń artykuł', $h, [p_adres('artykul', ['id' => $slug]), 'Artykuł'], $blad !== '' ? 422 : 200);
}

/* ---------- polityka prywatności ---------- */

function p_s_polityka(array $K): never
{
    $s = wczytaj_md(plik_tresci('polityka-prywatnosci.md'), true);
    $m = $s['meta'] + ['tytul' => '', 'opis' => '', 'h1' => '', 'lead' => ''];
    $tresc = $s['md'];
    $bledy = [];
    $blad = '';
    if ($K['post']) {
        $m['lead'] = p_linia($_POST['lead'] ?? '', 700);
        $tresc = p_wiele($_POST['tresc'] ?? '');
        if ($tresc === '') {
            $bledy['tresc'] = 'Polityka prywatności musi mieć treść.';
        }
        if (!$bledy) {
            $blad = p_po_zapisie(p_zastosuj($K['www'], $K['dane'], ['polityka-prywatnosci.md' => p_md_zloz($m, $tresc, [])]), p_adres('polityka'), '/polityka-prywatnosci/');
        }
    }
    $h = '<h1>Polityka prywatności</h1><p class="wstep">Nazwa pracowni, adres i NIP wstawiają się tu same z „Danych pracowni”.</p>'
        . p_bledy_gora($bledy) . p_blad_zapisu($blad) . p_form_start(p_adres('polityka'))
        . p_pole('lead', 'Wstęp', $m['lead'], ['typ' => 'textarea', 'wiersze' => 3])
        . p_pole('tresc', 'Treść', $tresc, ['typ' => 'textarea', 'wiersze' => 20, 'blad' => $bledy['tresc'] ?? '', 'klasa' => 'pole--dlugie'])
        . p_sciaga();
    p_uklad('Polityka prywatności', $h . p_form_koniec(), ['/panel/', 'Panel'], $bledy || $blad !== '' ? 422 : 200);
}

/* ---------- widoczność w Google ---------- */

function p_s_google(array $K): never
{
    $blad = '';
    $stan = p_stan($K['dane']);
    $b = p_braki($stan);
    $ile = count($b['dane']) + $b['cennik'] + count($b['inne']);
    $ukryta = (bool) ust('ukryj_przed_google');

    if ($K['post']) {
        $pokaz = ($_POST['widocznosc'] ?? '') === 'pokaz';
        if ($pokaz && $ile > 0) {
            $blad = 'Najpierw uzupełnij brakujące pola z listy poniżej.';
        } else {
            $zywe = ust_zywe();
            $zywe['ukryj_przed_google'] = !$pokaz;
            $wynik = p_zastosuj($K['www'], $K['dane'], ['ustawienia.json' => p_json($zywe)]);
            $blad = p_po_zapisie($wynik, p_adres('google'), null, $pokaz
                ? 'Strona jest teraz widoczna dla Google i asystentów AI. Pierwsze wizyty robotów zajmują zwykle kilka dni.'
                : 'Strona jest teraz ukryta przed Google.');
        }
    }

    $h = '<h1>Widoczność w Google</h1>' . p_blad_zapisu($blad);
    $h .= $ukryta
        ? '<p class="stan stan--ukryta"><strong>Strona jest ukryta.</strong> Działa pod swoim adresem, ale Google i asystenci AI jej nie pokazują.</p>'
        : '<p class="stan stan--widoczna"><strong>Strona jest widoczna.</strong> Google i asystenci AI mogą ją czytać i pokazywać w odpowiedziach.</p>';
    if ($ile > 0) {
        $h .= '<h2>Do uzupełnienia: ' . p_liczba_pol($ile) . '</h2><p>Puste pola widać na stronie jako żółte ramki. Strony z takimi polami nie warto pokazywać klientom ani Google.</p><ul class="braki">';
        if ($b['dane']) {
            $h .= '<li><a href="' . e(p_adres('dane')) . '">Dane pracowni</a>: ' . e(implode(', ', $b['dane'])) . '</li>';
        }
        if ($b['cennik'] > 0) {
            $h .= '<li><a href="' . e(p_adres('cennik')) . '">Cennik</a>: ' . ($b['cennik'] === 1 ? 'brakuje jednej ceny' : 'brakuje cen w ' . $b['cennik'] . ' pozycjach') . '</li>';
        }
        foreach ($b['inne'] as $pole => $sciezka) {
            $h .= '<li>W tekście strony <a href="' . e($sciezka) . '" target="_blank" rel="noopener">' . e($sciezka) . '</a> zostało pole w podwójnych nawiasach: „' . e($pole) . '”</li>';
        }
        $h .= '</ul>';
    }
    $h .= p_form_start(p_adres('google'), 'form form--waski');
    if ($ukryta) {
        $h .= '<input type="hidden" name="widocznosc" value="pokaz">'
            . ($ile > 0
                ? '<p class="drobne">Przycisk zadziała, gdy lista powyżej będzie pusta.</p><div class="przyciski"><button class="btn btn--zolty" type="submit" disabled>Pokaż stronę w Google</button></div>'
                : '<p>Wszystko jest uzupełnione. Przeczytaj jeszcze stronę główną i kontakt, a potem pokaż stronę światu.</p><div class="przyciski"><button class="btn btn--zolty" type="submit">Pokaż stronę w Google</button></div>');
    } else {
        $h .= '<input type="hidden" name="widocznosc" value="ukryj"><div class="przyciski"><button class="btn btn--kontur" type="submit">Ukryj stronę przed Google</button></div>'
            . '<p class="drobne">Ukrycie przydaje się rzadko, np. przy dłuższej przerwie w pracy pracowni. Strona znika z wyników po kilku dniach lub tygodniach.</p>';
    }
    p_uklad('Widoczność w Google', $h . '</form>', ['/panel/', 'Panel'], $blad !== '' ? 422 : 200);
}

/* ---------- historia ---------- */

function p_s_historia(array $K): never
{
    $blad = '';
    $kopie = array_slice(p_kopie($K['dane']), 0, 40);
    if ($K['post']) {
        $plik = p_linia($_POST['plik'] ?? '', 200);
        $kopia = null;
        foreach ($kopie as $k) {
            if ($k['plik'] === $plik) {
                $kopia = $k;
            }
        }
        if ($kopia === null) {
            p_przekieruj(p_adres('historia'));
        }
        $tresc = (string) file_get_contents($K['dane'] . '/kopie/' . $kopia['plik']);
        if ($kopia['rel'] === 'ustawienia.json') {
            // Przywrócenie starszych danych nie zmienia tego, czy strona jest widoczna w Google.
            $stare = json_decode($tresc, true);
            $stare = is_array($stare) ? $stare : [];
            $teraz = ust_zywe();
            if (array_key_exists('ukryj_przed_google', $teraz)) {
                $stare['ukryj_przed_google'] = $teraz['ukryj_przed_google'];
            } else {
                unset($stare['ukryj_przed_google']);
            }
            $tresc = p_json($stare);
        }
        $blad = p_po_zapisie(p_zastosuj($K['www'], $K['dane'], [$kopia['rel'] => $tresc]), p_adres('historia'), null, 'Przywrócono poprzednią wersję: ' . $kopia['nazwa'] . '.');
    }
    $h = '<h1>Cofnij zmianę</h1><p class="wstep">Przed każdym zapisem panel odkłada poprzednią wersję. Tu można do niej wrócić.</p>' . p_blad_zapisu($blad);
    if (!$kopie) {
        $h .= '<p>Nie ma jeszcze żadnych zmian do cofnięcia.</p>';
    } else {
        $h .= '<ul class="historia">';
        foreach ($kopie as $k) {
            $h .= '<li><div><strong>' . e($k['nazwa']) . '</strong><span>wersja sprzed zmiany z ' . e(p_data_godz($k['czas'])) . '</span></div>'
                . p_form_start(p_adres('historia'), 'w-linii') . '<input type="hidden" name="plik" value="' . e($k['plik']) . '"><button class="mini" type="submit">Przywróć</button></form></li>';
        }
        $h .= '</ul>';
    }
    p_uklad('Cofnij zmianę', $h, ['/panel/', 'Panel'], $blad !== '' ? 422 : 200);
}
