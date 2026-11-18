<?php
declare(strict_types=1);

/*
 * Generator strony.
 *
 * Działa na serwerze: po zapisie w panelu i po każdej aktualizacji z GitHuba.
 * Czyta treści z katalogu danych (poza katalogiem strony) i zapisuje gotowe pliki HTML,
 * robots.txt, sitemap.xml i llms.txt do katalogu strony.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/ikony.php';
require_once __DIR__ . '/szablony.php';

/** Katalog danych: treści, hasło panelu, kopie. Leży obok katalogu strony, więc nie jest dostępny z internetu. */
function psy_dane(string $www): string
{
    if (defined('PSY_DANE')) {
        return rtrim(PSY_DANE, '/');
    }
    $obok = dirname($www) . '/psychotesty-dane';
    if (is_dir($obok) || @mkdir($obok, 0700, true)) {
        return $obok;
    }
    // Awaryjnie: ukryty podkatalog strony z blokadą dostępu.
    $w = $www . '/.psychotesty-dane';
    if (!is_dir($w) && !@mkdir($w, 0700, true)) {
        throw new RuntimeException('Nie można utworzyć katalogu danych.');
    }
    if (!is_file($w . '/.htaccess')) {
        file_put_contents($w . '/.htaccess', "Require all denied\n");
    }
    return $w;
}

/** Tworzy podkatalogi danych. Treść startowa zostaje w kodzie; w danych leżą tylko zmiany z panelu. */
function psy_przygotuj_dane(string $www): string
{
    $dane = psy_dane($www);
    foreach ([$dane . '/tresc', $dane . '/kopie', $dane . '/sesje'] as $k) {
        if (!is_dir($k) && !@mkdir($k, 0700, true)) {
            throw new RuntimeException('Nie można utworzyć katalogu: ' . basename($k));
        }
    }
    return $dane;
}

function psy_bezpieczna_sciezka(string $rel): bool
{
    if ($rel === '' || $rel[0] === '/' || !preg_match('~^[A-Za-z0-9._/-]+$~', $rel)) {
        return false;
    }
    foreach (explode('/', $rel) as $c) {
        if ($c === '' || $c === '.' || $c === '..') {
            return false;
        }
    }
    return true;
}

/** Zapis przez plik tymczasowy, żeby odwiedzający nigdy nie dostał połowy strony. */
function psy_zapisz(string $plik, string $tresc): void
{
    $kat = dirname($plik);
    if (!is_dir($kat) && !mkdir($kat, 0755, true)) {
        throw new RuntimeException('Nie można utworzyć katalogu: ' . $kat);
    }
    if (is_file($plik) && filesize($plik) === strlen($tresc) && md5_file($plik) === md5($tresc)) {
        return;
    }
    $tmp = $plik . '.gen-tmp';
    if (file_put_contents($tmp, $tresc) === false || !rename($tmp, $plik)) {
        @unlink($tmp);
        throw new RuntimeException('Nie można zapisać pliku: ' . basename($plik));
    }
    @chmod($plik, 0644);
}

function wszystkie_strony(): array
{
    $s = [str_start()];
    foreach (USLUGI as $u) {
        $s[] = str_usluga($u);
    }
    array_push($s, str_cennik(), str_kontakt(), str_pytania(), str_poradnik());
    foreach (artykuly() as $a) {
        $s[] = str_artykul($a);
    }
    array_push($s, str_polityka(), str_404());
    return $s;
}

/**
 * Generuje całą stronę do katalogu $www.
 * Zwraca: pliki (lista wygenerowanych ścieżek), braki (liczba pól do uzupełnienia), ukryta (czy ma noindex).
 */
function generuj_strone(string $www): array
{
    // Ostrzeżenia PHP nie mogą wylądować w treści generowanych stron: zbieramy je i oddajemy w wyniku.
    $ostrzezenia = [];
    set_error_handler(static function (int $nr, string $tresc, string $plik = '', int $linia = 0) use (&$ostrzezenia): bool {
        if (!(error_reporting() & $nr)) {
            return true; // błąd świadomie wyciszony znakiem @
        }
        $ostrzezenia[] = $tresc . ' (' . basename($plik) . ':' . $linia . ')';
        return true;
    });
    try {
        $wynik = generuj_strone_wlasciwe($www);
    } finally {
        restore_error_handler();
    }
    $wynik['ostrzezenia'] = array_values(array_unique($ostrzezenia));
    return $wynik;
}

function generuj_strone_wlasciwe(string $www): array
{
    $www = rtrim($www, '/');
    $dane = psy_przygotuj_dane($www);
    $GLOBALS['PSY_TRESC'] = $dane . '/tresc';
    $GLOBALS['PODGLAD'] = false;
    pamiec_wyczysc();
    date_default_timezone_set('Europe/Warsaw');
    if (!defined('WERSJA')) {
        define('WERSJA', substr(md5((string) @file_get_contents($www . '/zasoby/styl.css') . (string) @file_get_contents($www . '/zasoby/skrypt.js')), 0, 8));
    }

    $u = ust();
    $d = $u['domena'];
    $ukryta = (bool) $u['ukryj_przed_google'];
    $pliki = [];
    $doMapy = [];
    $braki = 0;
    $pola = [];

    foreach (wszystkie_strony() as $p) {
        $html = uklad($p);
        $braki += substr_count($html, 'class="brak"');
        if (preg_match_all('~<span class="brak">(.*?)</span>~su', $html, $mm)) {
            foreach ($mm[1] as $pole) {
                // Dla każdego pola do uzupełnienia zapamiętujemy pierwszą podstronę, na której widać je.
                $pola[html_entity_decode(strip_tags($pole), ENT_QUOTES, 'UTF-8')] ??= $p['sciezka'];
            }
        }
        $rel = ltrim(str_ends_with($p['sciezka'], '.html') ? $p['sciezka'] : $p['sciezka'] . 'index.html', '/');
        psy_zapisz($www . '/' . $rel, $html);
        $pliki[] = $rel;
        if (empty($p['noindex'])) {
            $doMapy[] = $p['sciezka'];
        }
    }

    /* robots.txt */
    if ($ukryta) {
        // Roboty mogą wejść, żeby zobaczyć znacznik noindex na każdej podstronie.
        $robots = "# Strona w przygotowaniu: podstrony mają znacznik noindex.\nUser-agent: *\nAllow: /\n";
    } else {
        $boty = ['Googlebot', 'bingbot', 'Google-Extended', 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Applebot', 'Applebot-Extended'];
        $robots = "# Strona jest dostępna dla wyszukiwarek i asystentów AI.\nUser-agent: *\nAllow: /\n\n";
        foreach ($boty as $b) {
            $robots .= 'User-agent: ' . $b . "\n";
        }
        $robots .= "Allow: /\n\nSitemap: $d/sitemap.xml\n";
    }
    psy_zapisz($www . '/robots.txt', $robots);
    $pliki[] = 'robots.txt';

    if (!$ukryta) {
        /* sitemap.xml */
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($doMapy as $s) {
            $xml .= '  <url><loc>' . e($d . $s) . '</loc><lastmod>' . e((string) $u['aktualizacja']) . '</lastmod></url>' . "\n";
        }
        psy_zapisz($www . '/sitemap.xml', $xml . "</urlset>\n");
        $pliki[] = 'sitemap.xml';

        /* llms.txt – skrót strony dla modeli językowych */
        $l = '# ' . $u['marka'] . ' – pracownia psychologiczna (' . $u['psycholog'] . ")\n\n";
        $l .= '> Pracownia psychologiczna w Częstochowie (województwo śląskie). Wykonuje badania psychologiczne kierowców w zakresie psychologii transportu (psychotesty) oraz badania psychotechniczne operatorów i pracowników. Badania prowadzi ' . $u['psycholog'] . ', ' . $u['psycholog_rola'] . ".\n\n";
        $l .= "## Najważniejsze fakty\n\n";
        $l .= '- Badanie psychologiczne kierowcy kosztuje ' . czysty($u['cena_kierowcy']) . ". To opłata urzędowa obowiązująca w całej Polsce od 14 listopada 2026 r. (wcześniej 150 zł).\n";
        $l .= "- Kierowca zawodowy powtarza badanie co 5 lat, a po ukończeniu 60 lat co 30 miesięcy (art. 39k ustawy o transporcie drogowym).\n";
        $l .= "- Na badanie trzeba zabrać dokument tożsamości, prawo jazdy, skierowanie (jeśli jest) oraz okulary lub soczewki.\n";
        $l .= '- Obszar: Częstochowa oraz ' . implode(', ', array_keys($u['okolice'])) . ".\n";
        if (!brak($u['ulica'])) {
            $l .= '- Adres: ' . $u['ulica'] . ', ' . czysty($u['kod']) . ' ' . $u['miasto'] . ".\n";
        }
        if (!brak($u['telefon'])) {
            $l .= '- Telefon: ' . $u['telefon'] . ".\n";
        }
        $godz = array_filter($u['godziny'], static fn($g): bool => is_array($g) && !brak($g[1] ?? ''));
        if ($godz) {
            $l .= '- Godziny: ' . implode('; ', array_map(static fn(array $g): string => $g[0] . ' ' . $g[1], $godz)) . ".\n";
        }
        $l .= "\n## Badania\n\n";
        foreach (USLUGI as $s) {
            $m = meta_uslugi($s)['meta'];
            $l .= '- [' . tekst($m['h1']) . "]($d/$s/): " . tekst($m['krotko']) . "\n";
        }
        $l .= "\n## Informacje\n\n- [Cennik]($d/cennik/)\n- [Pytania i odpowiedzi]($d/pytania/)\n- [Kontakt i dojazd]($d/kontakt/)\n\n## Poradnik\n\n";
        foreach (artykuly() as $a) {
            $l .= '- [' . tekst($a['meta']['h1']) . "]($d/poradnik/" . $a['slug'] . '/): ' . tekst($a['meta']['skrot'] ?? '') . "\n";
        }
        psy_zapisz($www . '/llms.txt', $l);
        $pliki[] = 'llms.txt';
    }

    /* usunięcie plików z poprzedniego generowania, których już nie ma (np. skasowany artykuł) */
    $lista = $dane . '/wygenerowane.json';
    $stare = is_file($lista) ? json_decode((string) file_get_contents($lista), true) : [];
    if (is_array($stare)) {
        foreach (array_diff($stare, $pliki) as $rel) {
            if (!is_string($rel) || !psy_bezpieczna_sciezka($rel) || !is_file($www . '/' . $rel)) {
                continue;
            }
            @unlink($www . '/' . $rel);
            $kat = dirname($www . '/' . $rel);
            while ($kat !== $www && strlen($kat) > strlen($www) && @rmdir($kat)) {
                $kat = dirname($kat);
            }
        }
    }
    file_put_contents($lista, json_encode($pliki));
    file_put_contents($dane . '/ostatnie.json', json_encode(['czas' => date('c'), 'braki' => $braki, 'pola' => $pola, 'stron' => count($doMapy), 'ukryta' => $ukryta], JSON_UNESCAPED_UNICODE));

    return ['pliki' => $pliki, 'braki' => $braki, 'pola' => $pola, 'ukryta' => $ukryta, 'dane_poza_strona' => !str_starts_with($dane, $www . '/')];
}
