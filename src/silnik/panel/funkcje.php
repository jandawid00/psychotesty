<?php
declare(strict_types=1);

/* Panel: logowanie, sesja, zapis treści z kopią zapasową. Widoki są w widoki.php, obsługa stron w strony.php. */

if (!defined('PANEL')) {
    http_response_code(404);
    exit;
}

const P_SESJA_DNI = 30;
const P_OKNO_HASLA_DNI = 7;
const P_HASLO_MIN = 10;
const P_KOPII = 80;

/* ---------- nagłówki i sesja ---------- */

function p_naglowki(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
}

function p_lokalnie(): bool
{
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    return in_array($host, ['localhost', '127.0.0.1'], true);
}

function p_ciasteczko(): array
{
    return ['path' => '/panel/', 'secure' => !p_lokalnie(), 'httponly' => true, 'samesite' => 'Lax'];
}

function p_sesja(string $dane): void
{
    foreach (['session.use_strict_mode' => '1', 'session.use_only_cookies' => '1', 'session.use_trans_sid' => '0'] as $klucz => $wartosc) {
        if ((string) ini_get($klucz) !== $wartosc) {
            @ini_set($klucz, $wartosc);
        }
    }
    ini_set('session.gc_maxlifetime', (string) (P_SESJA_DNI * 86400));
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '50');
    session_save_path($dane . '/sesje');
    session_name('psy_panel');
    session_set_cookie_params(['lifetime' => P_SESJA_DNI * 86400] + p_ciasteczko());
    session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function p_csrf(): string
{
    return (string) $_SESSION['csrf'];
}

function p_csrf_ok(): bool
{
    return is_string($_POST['csrf'] ?? null) && hash_equals(p_csrf(), $_POST['csrf']);
}

/* ---------- hasło ---------- */

function p_haslo_hash(string $dane): ?string
{
    $h = json_decode((string) @file_get_contents($dane . '/haslo.json'), true);
    return is_array($h) && is_string($h['hash'] ?? null) && $h['hash'] !== '' ? $h['hash'] : null;
}

function p_odcisk(string $hash): string
{
    return substr(hash('sha256', $hash), 0, 24);
}

function p_zalogowana(string $dane): bool
{
    $hash = p_haslo_hash($dane);
    return $hash !== null && !empty($_SESSION['ok']) && hash_equals(p_odcisk($hash), (string) ($_SESSION['odcisk'] ?? ''));
}

function p_zaloguj(string $hash): void
{
    session_regenerate_id(true);
    $_SESSION['ok'] = true;
    $_SESSION['odcisk'] = p_odcisk($hash);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/** Zwraca komunikat błędu albo null, gdy hasło jest w porządku. */
function p_haslo_blad(string $h1, string $h2): ?string
{
    if ($h1 !== $h2) {
        return 'Hasła w obu polach się różnią. Wpisz je jeszcze raz.';
    }
    if (mb_strlen($h1) < P_HASLO_MIN) {
        return 'Hasło musi mieć co najmniej ' . P_HASLO_MIN . ' znaków.';
    }
    if (strlen($h1) > 200) {
        return 'Hasło jest za długie.';
    }
    $male = mb_strtolower($h1);
    if (count(array_unique(mb_str_split($male))) < 5) {
        return 'To hasło jest zbyt proste. Użyj kilku różnych znaków.';
    }
    foreach (['psychotest', 'czestochowa', 'częstochowa', 'haslo', 'hasło', 'password', 'qwerty', '12345', 'monika'] as $slowo) {
        if (str_contains($male, $slowo)) {
            return 'To hasło łatwo zgadnąć. Nie używaj w nim słowa „' . $slowo . '”.';
        }
    }
    return null;
}

function p_haslo_zapisz(string $dane, string $haslo): string
{
    $hash = password_hash($haslo, PASSWORD_DEFAULT);
    $tmp = $dane . '/haslo.json.tmp';
    if (file_put_contents($tmp, json_encode(['hash' => $hash, 'od' => date('c')])) === false || !rename($tmp, $dane . '/haslo.json')) {
        throw new RuntimeException('Nie udało się zapisać hasła.');
    }
    @chmod($dane . '/haslo.json', 0600);
    return $hash;
}

/**
 * Okno na ustawienie pierwszego hasła. Znacznik aktywacji jest w kodzie strony (silnik/aktywacja.txt);
 * jego zmiana przy wdrożeniu kasuje hasło i na kilka dni otwiera ustawianie od nowa.
 * Wywoływane przed startem sesji, bo przy nowej aktywacji usuwa wszystkie sesje.
 */
function p_aktywacja(string $www, string $dane): ?array
{
    $znacznik = trim((string) @file_get_contents($www . '/silnik/aktywacja.txt'));
    $plik = $dane . '/aktywacja.json';
    $a = json_decode((string) @file_get_contents($plik), true);
    $a = is_array($a) && is_string($a['znacznik'] ?? null) && is_int($a['od'] ?? null) ? $a : null;
    if ($znacznik === '' || !preg_match('/^[A-Za-z0-9._-]{4,60}$/', $znacznik)) {
        return $a;
    }
    if ($a === null || $a['znacznik'] !== $znacznik) {
        @unlink($dane . '/haslo.json');
        foreach (glob($dane . '/sesje/sess_*') ?: [] as $f) {
            @unlink($f);
        }
        $a = ['znacznik' => $znacznik, 'od' => time()];
        file_put_contents($plik, json_encode($a), LOCK_EX);
    }
    return $a;
}

function p_mozna_ustawic_haslo(string $dane, ?array $aktywacja): bool
{
    return p_haslo_hash($dane) === null && $aktywacja !== null && time() - $aktywacja['od'] < P_OKNO_HASLA_DNI * 86400;
}

/* ---------- ograniczenie prób logowania ---------- */

/**
 * $zdarzenie: null = tylko sprawdź, 'blad' = zapisz nieudaną próbę, 'ok' = wyczyść próby z tego adresu.
 * Zwraca liczbę sekund blokady (0 = można próbować).
 */
function p_proby(string $dane, ?string $zdarzenie = null): int
{
    $okno = 900;
    $teraz = time();
    $ip = substr(hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 16);
    $f = fopen($dane . '/logowania.json', 'c+');
    if ($f === false) {
        return 0;
    }
    flock($f, LOCK_EX);
    $s = json_decode((string) stream_get_contents($f), true);
    $s = is_array($s) ? $s : [];
    $swieze = static fn($lista): array => array_values(array_filter(is_array($lista) ? $lista : [], static fn($t): bool => is_int($t) && $t > $teraz - $okno));
    $wszystkie = $swieze($s['wszystkie'] ?? []);
    $adresy = [];
    foreach (is_array($s['adresy'] ?? null) ? $s['adresy'] : [] as $k => $lista) {
        $l = $swieze($lista);
        if ($l) {
            $adresy[(string) $k] = $l;
        }
    }
    if ($zdarzenie === 'blad') {
        $wszystkie[] = $teraz;
        $adresy[$ip][] = $teraz;
    } elseif ($zdarzenie === 'ok') {
        unset($adresy[$ip]);
    }
    $blokada = 0;
    if (count($adresy[$ip] ?? []) >= 6) {
        $blokada = max($blokada, $adresy[$ip][0] + $okno - $teraz);
    }
    if (count($wszystkie) >= 60) {
        $blokada = max($blokada, $wszystkie[0] + $okno - $teraz);
    }
    if ($zdarzenie !== null) {
        ftruncate($f, 0);
        rewind($f);
        fwrite($f, (string) json_encode(['wszystkie' => $wszystkie, 'adresy' => $adresy]));
    }
    flock($f, LOCK_UN);
    fclose($f);
    return max(0, $blokada);
}

/* ---------- dane z formularzy ---------- */

function p_linia(mixed $v, int $maks = 400): string
{
    if (!is_string($v)) {
        return '';
    }
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '';
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    return mb_substr($v, 0, $maks);
}

function p_wiele(mixed $v, int $maks = 40000): string
{
    if (!is_string($v)) {
        return '';
    }
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $v) ?? '';
    $v = preg_replace('/[ \t]+$/mu', '', $v) ?? $v;
    $v = preg_replace('/\n{3,}/', "\n\n", $v) ?? $v;
    return mb_substr(trim($v), 0, $maks);
}

/** Tablica wartości z pól o nazwie zakończonej []. */
function p_lista(string $nazwa): array
{
    $v = $_POST[$nazwa] ?? [];
    return is_array($v) ? array_values($v) : [];
}

/* ---------- pliki treści ---------- */

function p_rel_ok(string $rel): bool
{
    if (in_array($rel, ['ustawienia.json', 'cennik.json', 'pytania.md', 'polityka-prywatnosci.md', 'poradnik/_usuniete.json'], true)) {
        return true;
    }
    if (preg_match('~^uslugi/([a-z0-9-]+)\.md$~', $rel, $m)) {
        return in_array($m[1], USLUGI, true);
    }
    return (bool) preg_match('~^poradnik/[a-z0-9][a-z0-9-]{1,90}\.md$~', $rel);
}

function p_rel_nazwa(string $rel): string
{
    $stale = ['ustawienia.json' => 'Dane pracowni i komunikat', 'cennik.json' => 'Cennik', 'pytania.md' => 'Pytania i odpowiedzi', 'polityka-prywatnosci.md' => 'Polityka prywatności'];
    if (isset($stale[$rel])) {
        return $stale[$rel];
    }
    if (preg_match('~^uslugi/(.+)\.md$~', $rel, $m)) {
        return 'Badanie: ' . (wczytaj_md(plik_tresci($rel), true)['meta']['nazwa'] ?? $m[1]);
    }
    if (preg_match('~^poradnik/(.+)\.md$~', $rel, $m)) {
        return 'Artykuł: ' . str_replace('-', ' ', $m[1]);
    }
    return $rel;
}

function p_kopia(string $dane, string $rel, string $tresc): void
{
    $nazwa = date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '__' . str_replace('/', '~', $rel);
    file_put_contents($dane . '/kopie/' . $nazwa, $tresc);
    $wszystkie = glob($dane . '/kopie/*__*') ?: [];
    sort($wszystkie);
    foreach (array_slice($wszystkie, 0, max(0, count($wszystkie) - P_KOPII)) as $stara) {
        @unlink($stara);
    }
}

/** Lista kopii od najnowszej: [plik, rel, czas]. */
function p_kopie(string $dane): array
{
    $out = [];
    $pliki = glob($dane . '/kopie/*__*') ?: [];
    rsort($pliki);
    foreach ($pliki as $p) {
        if (!preg_match('~^(\d{8})-(\d{6})-[0-9a-f]{4}__(.+)$~', basename($p), $m)) {
            continue;
        }
        $rel = str_replace('~', '/', $m[3]);
        if (!p_rel_ok($rel) || str_starts_with($rel, 'poradnik/_')) {
            continue;
        }
        $czas = DateTimeImmutable::createFromFormat('Ymd His', $m[1] . ' ' . $m[2]);
        $nazwa = p_rel_nazwa($rel);
        if (str_starts_with($rel, 'poradnik/')) {
            $h1 = rozloz_md((string) file_get_contents($p), true)['meta']['h1'] ?? '';
            $nazwa = $h1 !== '' ? 'Artykuł: ' . tekst($h1) : $nazwa;
        }
        $out[] = ['plik' => basename($p), 'rel' => $rel, 'czas' => $czas ?: null, 'nazwa' => $nazwa];
    }
    return $out;
}

function p_zapisz_plik(string $plik, ?string $tresc): void
{
    if ($tresc === null) {
        if (is_file($plik) && !unlink($plik)) {
            throw new RuntimeException('Nie udało się usunąć pliku ' . basename($plik) . '.');
        }
        return;
    }
    $kat = dirname($plik);
    if (!is_dir($kat) && !mkdir($kat, 0700, true)) {
        throw new RuntimeException('Nie udało się utworzyć katalogu treści.');
    }
    $tmp = $plik . '.tmp';
    if (file_put_contents($tmp, $tresc) === false || !rename($tmp, $plik)) {
        @unlink($tmp);
        throw new RuntimeException('Nie udało się zapisać pliku ' . basename($plik) . '.');
    }
}

function p_json(array $dane): string
{
    return json_encode($dane, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

/** Czy nowa treść pliku znaczy to samo, co obecna (zapisana w panelu albo startowa). */
function p_to_samo(string $dane, string $rel, ?string $nowa): bool
{
    $zywy = $dane . '/tresc/' . $rel;
    $start = TRESC_START . '/' . $rel;
    $obecna = is_file($zywy) ? (string) file_get_contents($zywy) : (is_file($start) && $rel !== 'ustawienia.json' ? (string) file_get_contents($start) : null);
    if ($nowa === null) {
        return !is_file($zywy);
    }
    if (!is_file($zywy) && preg_match('~^poradnik/(.+)\.md$~', $rel, $m) && !isset(artykuly_pliki()[$m[1]])) {
        // Usunięty artykuł startowy: przywrócenie go jest zmianą, choć treść jest ta sama.
        $obecna = null;
    }
    if ($rel === 'ustawienia.json') {
        $n = json_decode($nowa, true);
        $o = json_decode((string) $obecna, true);
        return is_array($n) && array_merge(ust_start(), $n) === array_merge(ust_start(), is_array($o) ? $o : []);
    }
    if ($obecna === null) {
        return false;
    }
    if (str_ends_with($rel, '.json')) {
        return json_decode($nowa, true) === json_decode($obecna, true);
    }
    return rozloz_md($nowa, true) === rozloz_md($obecna, true);
}

/**
 * Zapisuje zmiany ($zmiany: ścieżka względna => nowa treść albo null = usuń), robi kopie poprzednich wersji
 * i generuje stronę od nowa. Gdy generowanie się nie uda, przywraca poprzedni stan.
 * Zwraca: 'bez-zmian', 'ok' albo komunikat błędu do pokazania nad formularzem.
 */
function p_zastosuj(string $www, string $dane, array $zmiany): string
{
    $tresc = $dane . '/tresc';
    $blokada = fopen($dane . '/blokada', 'c');
    if ($blokada === false || !flock($blokada, LOCK_EX)) {
        return 'Nie można teraz zapisać zmian. Spróbuj za chwilę.';
    }
    $poprzednie = [];
    $bezKopii = false;
    try {
        foreach ($zmiany as $rel => $nowa) {
            if (!p_rel_ok($rel)) {
                throw new RuntimeException('Niedozwolony plik treści.');
            }
            $plik = $tresc . '/' . $rel;
            if (!p_to_samo($dane, $rel, $nowa)) {
                $poprzednie[$rel] = is_file($plik) ? (string) file_get_contents($plik) : null;
            }
        }
        if (!$poprzednie) {
            return 'bez-zmian';
        }
        // Data ostatniej aktualizacji (stopka strony i mapa strony) zmienia się razem z każdą zmianą treści.
        $ust = array_key_exists('ustawienia.json', $zmiany) ? (array) json_decode((string) $zmiany['ustawienia.json'], true) : ust_zywe();
        if (($ust['aktualizacja'] ?? '') !== date('Y-m-d')) {
            $ust['aktualizacja'] = date('Y-m-d');
            $zmiany['ustawienia.json'] = p_json($ust);
            if (!array_key_exists('ustawienia.json', $poprzednie)) {
                $poprzednie['ustawienia.json'] = is_file($tresc . '/ustawienia.json') ? (string) file_get_contents($tresc . '/ustawienia.json') : null;
                $bezKopii = true;
            }
        }
        foreach ($poprzednie as $rel => $stara) {
            $wewnetrzny = str_starts_with($rel, 'poradnik/_') || ($rel === 'ustawienia.json' && $bezKopii);
            // Pierwsza zmiana pliku: do historii trafia wersja startowa, żeby dało się do niej wrócić.
            $start = TRESC_START . '/' . $rel;
            $doKopii = $stara ?? ($rel === 'ustawienia.json' ? "{}\n" : (is_file($start) ? (string) file_get_contents($start) : null));
            if ($doKopii !== null && !$wewnetrzny) {
                p_kopia($dane, $rel, $doKopii);
            }
            p_zapisz_plik($tresc . '/' . $rel, $zmiany[$rel]);
        }
        pamiec_wyczysc();
        $wynik = generuj_strone($www);
        if (!$wynik['ukryta'] && $wynik['braki'] > 0) {
            // Strona widoczna w Google nie może dostać pustych (żółtych) pól.
            throw new DomainException('Nic nie zostało zapisane. Strona jest widoczna w Google, więc żadne pole nie może zostać puste (' . implode(', ', array_slice(array_keys($wynik['pola']), 0, 3))
                . '). Wypełnij je albo najpierw ukryj stronę w części „Widoczność w Google”.');
        }
        return 'ok';
    } catch (Throwable $e) {
        foreach ($poprzednie as $rel => $stara) {
            try {
                p_zapisz_plik($tresc . '/' . $rel, $stara);
            } catch (Throwable) {
            }
        }
        pamiec_wyczysc();
        try {
            generuj_strone($www);
        } catch (Throwable) {
        }
        if ($e instanceof DomainException) {
            return $e->getMessage();
        }
        error_log('Panel psychotesty: ' . $e->getMessage() . ' w ' . $e->getFile() . ':' . $e->getLine());
        return 'Zmiany nie zostały zapisane (' . $e->getMessage() . '). Strona została w poprzednim stanie.';
    } finally {
        flock($blokada, LOCK_UN);
        fclose($blokada);
    }
}

/* ---------- pliki .md ---------- */

function p_md_zloz(array $meta, string $tresc, array $faq): string
{
    $s = "---\n";
    foreach ($meta as $k => $v) {
        $s .= $k . ': ' . p_linia((string) $v, 2000) . "\n";
    }
    $s .= "---\n";
    // Wiersz "=== pytania ===" oddziela treść od pytań, więc nie może pojawić się w treści.
    $tresc = trim(preg_replace('/^===\s*pytania\s*===\s*$/mu', '', $tresc) ?? $tresc);
    if ($tresc !== '') {
        $s .= $tresc . "\n";
    }
    $pytania = '';
    foreach ($faq as $x) {
        $p = p_linia($x['p'] ?? '', 300);
        // Odpowiedź nie może zawierać wiersza zaczynającego się od ###, bo tak zaczyna się następne pytanie.
        $o = trim(preg_replace('/^#+\s*/mu', '', (string) ($x['o'] ?? '')) ?? '');
        if ($p !== '' && $o !== '') {
            $pytania .= '### ' . ltrim($p, '# ') . "\n" . $o . "\n\n";
        }
    }
    if ($pytania !== '') {
        $s .= ($tresc !== '' ? "\n" : '') . "=== pytania ===\n" . rtrim($pytania) . "\n";
    }
    return $s;
}

/** Pary pytanie–odpowiedź z formularza (pola fp[] i fo[]). */
function p_faq_z_formularza(): array
{
    $p = p_lista('fp');
    $o = p_lista('fo');
    $out = [];
    foreach ($p as $i => $pyt) {
        $pyt = p_linia($pyt, 300);
        $odp = p_wiele($o[$i] ?? '', 6000);
        if ($pyt !== '' || $odp !== '') {
            $out[] = ['p' => $pyt, 'o' => $odp];
        }
    }
    return $out;
}

/* ---------- pola do uzupełnienia ---------- */

/** Podpowiedź z treści startowej: tekst z [[...]] bez nawiasów; pusty, gdy pole startowe nie jest do uzupełnienia. */
function p_podpowiedz(string $klucz): string
{
    $s = ust_start()[$klucz] ?? '';
    return is_string($s) && str_contains($s, '[[') ? czysty($s) : '';
}

/** Wartość do pola formularza: pusta, gdy pole jest jeszcze nieuzupełnione. */
function p_wartosc(?string $v): string
{
    return brak($v) ? '' : (string) $v;
}

function p_stan(string $dane): array
{
    $o = json_decode((string) @file_get_contents($dane . '/ostatnie.json'), true);
    return is_array($o) ? $o + ['braki' => 0, 'pola' => [], 'ukryta' => true] : ['braki' => 0, 'pola' => [], 'ukryta' => true];
}

function p_przekieruj(string $adres): never
{
    header('Location: ' . $adres, true, 303);
    exit;
}

function p_adres(string $strona = '', array $parametry = []): string
{
    $q = array_filter(['s' => $strona] + $parametry, static fn($v): bool => $v !== '' && $v !== null);
    return '/panel/' . ($q ? '?' . http_build_query($q) : '');
}
