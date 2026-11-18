<?php
/*
 * Aktualizacja strony z GitHuba.
 *
 * Pobiera bieżącą wersję gałęzi "main" publicznego repozytorium i podmienia pliki
 * w katalogu, w którym leży ten skrypt. Nie przyjmuje żadnych parametrów i nie zawiera
 * haseł: adres źródła jest wpisany na stałe, więc wywołanie przez kogokolwiek może
 * najwyżej odświeżyć stronę do stanu z repozytorium.
 *
 * Usuwa wyłącznie pliki, które sam wcześniej wgrał (ich lista leży w katalogu stanu,
 * poza katalogiem strony). Plików utworzonych na serwerze w inny sposób nie rusza.
 * Po pobraniu plików uruchamia generator (silnik/generuj.php), który składa strony HTML
 * z treści edytowanych w panelu.
 */

define('AKT_REPO', 'jandawid00/psychotesty');
define('AKT_GALAZ', 'main');
define('AKT_ODSTEP', 20);            // najmniejszy odstęp między aktualizacjami, w sekundach
define('AKT_MAKS_ROZMIAR', 30000000); // górny limit rozmiaru archiwum, w bajtach

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
@set_time_limit(120);

function akt_koniec($tekst, $kod = 200)
{
    http_response_code($kod);
    echo $tekst . "\n";
    exit;
}

/** Ścieżka z archiwum jest bezpieczna: bez "..", bez znaków spoza prostego zestawu. */
function akt_bezpieczna($rel)
{
    if ($rel === '' || $rel[0] === '/' || strlen($rel) > 240) {
        return false;
    }
    if (!preg_match('~^[A-Za-z0-9._/-]+$~', $rel)) {
        return false;
    }
    foreach (explode('/', $rel) as $czesc) {
        if ($czesc === '' || $czesc === '.' || $czesc === '..') {
            return false;
        }
    }
    return true;
}

/** Pliki repozytorium, które nie należą do strony. */
function akt_pomin($rel)
{
    $pierwszy = explode('/', $rel)[0];
    if (strpos($pierwszy, '.git') === 0) {
        return true;
    }
    return in_array($rel, array('README.md', 'LICENSE'), true);
}

function akt_pobierz($url, $do)
{
    if (function_exists('curl_init')) {
        $f = fopen($do, 'wb');
        if (!$f) {
            return 'nie można zapisać pliku tymczasowego';
        }
        $c = curl_init($url);
        curl_setopt_array($c, array(
            CURLOPT_FILE => $f,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'psychotesty-aktualizacja',
        ));
        $ok = curl_exec($c);
        $kod = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
        $blad = curl_error($c);
        curl_close($c);
        fclose($f);
        return ($ok && $kod === 200) ? '' : 'połączenie z GitHubem nieudane (kod ' . $kod . ($blad !== '' ? ', ' . $blad : '') . ')';
    }
    $ctx = stream_context_create(array('http' => array('timeout' => 90, 'user_agent' => 'psychotesty-aktualizacja', 'follow_location' => 1)));
    $dane = @file_get_contents($url, false, $ctx);
    if ($dane === false) {
        return 'połączenie z GitHubem nieudane (brak curl, a allow_url_fopen nie pozwala)';
    }
    return file_put_contents($do, $dane) === false ? 'nie można zapisać pliku tymczasowego' : '';
}

$tu = __DIR__;

/* katalog stanu: obok katalogu strony, a gdy się nie da, ukryty podkatalog z blokadą dostępu */
$stan = dirname($tu) . '/psychotesty-stan';
if (!is_dir($stan) && !@mkdir($stan, 0700, true)) {
    $stan = $tu . '/.stan';
    if (!is_dir($stan) && !@mkdir($stan, 0700, true)) {
        akt_koniec('Błąd: skrypt nie może tworzyć katalogów na serwerze.', 500);
    }
    if (!is_file($stan . '/.htaccess')) {
        @file_put_contents($stan . '/.htaccess', "Require all denied\n");
    }
}
if (!is_writable($stan) || !is_writable($tu)) {
    akt_koniec('Błąd: skrypt nie może zapisywać plików na serwerze.', 500);
}

/* nie częściej niż co AKT_ODSTEP sekund */
$znacznik = $stan . '/ostatnia-proba.txt';
if (is_file($znacznik) && time() - filemtime($znacznik) < AKT_ODSTEP) {
    akt_koniec('Aktualizacja była przed chwilą. Spróbuj ponownie za ' . AKT_ODSTEP . ' sekund.', 429);
}
@touch($znacznik);

/* pobranie archiwum (w wierszu poleceń można podać gotowy plik do testów) */
$tmp = tempnam($stan, 'zip');
$lokalny = (PHP_SAPI === 'cli') ? getenv('AKT_ZIP') : false;
if ($lokalny) {
    copy($lokalny, $tmp);
} else {
    $blad = akt_pobierz('https://codeload.github.com/' . AKT_REPO . '/zip/refs/heads/' . AKT_GALAZ, $tmp);
    if ($blad !== '') {
        @unlink($tmp);
        akt_koniec('Błąd: ' . $blad . '.', 502);
    }
}
if (filesize($tmp) < 100 || filesize($tmp) > AKT_MAKS_ROZMIAR) {
    @unlink($tmp);
    akt_koniec('Błąd: pobrane archiwum ma nieprawidłowy rozmiar.', 502);
}
if (!class_exists('ZipArchive')) {
    @unlink($tmp);
    akt_koniec('Błąd: na serwerze brakuje obsługi archiwów ZIP (ZipArchive).', 500);
}
$zip = new ZipArchive();
if ($zip->open($tmp) !== true) {
    @unlink($tmp);
    akt_koniec('Błąd: pobrany plik nie jest archiwum ZIP.', 502);
}

$wersja = preg_replace('~[^0-9a-f]~', '', (string) $zip->getArchiveComment());
$nowe = array();
$zmienione = 0;
$bledy = array();

for ($i = 0; $i < $zip->numFiles; $i++) {
    $nazwa = $zip->getNameIndex($i);
    $poz = strpos($nazwa, '/');
    if ($poz === false) {
        continue;
    }
    $rel = substr($nazwa, $poz + 1);            // bez katalogu głównego archiwum
    if ($rel === '' || substr($rel, -1) === '/') {
        continue;                                // katalog
    }
    if (!akt_bezpieczna($rel) || akt_pomin($rel)) {
        continue;
    }
    $dane = $zip->getFromIndex($i);
    if ($dane === false) {
        $bledy[] = $rel;
        continue;
    }
    $cel = $tu . '/' . $rel;
    $kat = dirname($cel);
    if (!is_dir($kat) && !@mkdir($kat, 0755, true)) {
        $bledy[] = $rel;
        continue;
    }
    $nowe[] = $rel;
    if (is_file($cel) && filesize($cel) === strlen($dane) && md5_file($cel) === md5($dane)) {
        continue;                                // bez zmian
    }
    $roboczy = $cel . '.akt-tmp';
    if (@file_put_contents($roboczy, $dane) === false || !@rename($roboczy, $cel)) {
        @unlink($roboczy);
        $bledy[] = $rel;
        continue;
    }
    @chmod($cel, 0644);
    $zmienione++;
}
$zip->close();
@unlink($tmp);

if (!$nowe) {
    akt_koniec('Błąd: archiwum nie zawiera plików strony.', 502);
}
if ($bledy) {
    akt_koniec('Błąd: nie udało się zapisać ' . count($bledy) . ' plików (pierwszy: ' . $bledy[0] . '). Niczego nie usunięto.', 500);
}

/* wygenerowanie stron z aktualnych treści (silnik jest częścią pobranych plików) */
$wygenerowane = array();
$raport = '';
if (is_file($tu . '/silnik/generuj.php')) {
    try {
        require_once $tu . '/silnik/generuj.php';
        $wynik = generuj_strone($tu);
        $wygenerowane = $wynik['pliki'];
        $raport = ' Wygenerowano plików: ' . count($wygenerowane) . ', pól do uzupełnienia: ' . $wynik['braki'] . ($wynik['ukryta'] ? ', strona ukryta przed wyszukiwarkami' : '') . '. Treści z panelu: ' . ($wynik['dane_poza_strona'] ? 'poza katalogiem strony' : 'w zablokowanym podkatalogu strony') . '.'
            . ($wynik['ostrzezenia'] ? ' Ostrzeżenia PHP (' . count($wynik['ostrzezenia']) . '): ' . implode(' | ', array_slice($wynik['ostrzezenia'], 0, 4)) : ' Ostrzeżeń PHP: 0.')
            . ' PHP ' . PHP_VERSION . '.';
    } catch (Throwable $e) {
        akt_koniec('Pliki pobrane, ale generowanie strony nie powiodło się: ' . $e->getMessage() . ' Strony zostały w poprzednim stanie.', 500);
    }
}

/* usunięcie plików, które ten skrypt wgrał wcześniej, a których nie ma już w repozytorium */
$lista = $stan . '/wgrane-pliki.json';
$stare = is_file($lista) ? json_decode((string) file_get_contents($lista), true) : array();
$usuniete = 0;
if (is_array($stare)) {
    foreach (array_diff($stare, $nowe, $wygenerowane) as $rel) {
        if (!is_string($rel) || !akt_bezpieczna($rel) || !is_file($tu . '/' . $rel)) {
            continue;
        }
        if (@unlink($tu . '/' . $rel)) {
            $usuniete++;
            $kat = dirname($tu . '/' . $rel);
            while ($kat !== $tu && strlen($kat) > strlen($tu) && @rmdir($kat)) {
                $kat = dirname($kat);          // sprząta puste katalogi
            }
        }
    }
}
file_put_contents($lista, json_encode($nowe));
file_put_contents($stan . '/wersja.txt', $wersja . "\n" . date('c') . "\n");

akt_koniec('Zaktualizowano. Wersja: ' . ($wersja !== '' ? substr($wersja, 0, 7) : 'nieznana')
    . '. Plików z repozytorium: ' . count($nowe) . ', zmienionych: ' . $zmienione . ', usuniętych: ' . $usuniete . '.' . $raport);
