<?php
declare(strict_types=1);

/* Wspólne funkcje generatora strony. Treści leżą w katalogu tresc/, szablony w szablony.php. */

const SRC = __DIR__;
const TRESC_START = __DIR__ . '/tresc-startowa';

/** Katalog z aktualnymi treściami (edytowanymi w panelu). Bez niego używana jest treść startowa. */
function tresc_kat(): string
{
    return $GLOBALS['PSY_TRESC'] ?? TRESC_START;
}

/** Plik treści: wersja edytowana, a gdy jej nie ma, startowa. */
function plik_tresci(string $rel): string
{
    $p = tresc_kat() . '/' . $rel;
    return is_file($p) ? $p : TRESC_START . '/' . $rel;
}

const MENU = [
    ['/#badania', 'Badania'],
    ['/cennik/', 'Cennik'],
    ['/dla-firm/', 'Dla firm'],
    ['/poradnik/', 'Poradnik'],
    ['/pytania/', 'Pytania'],
    ['/kontakt/', 'Kontakt'],
];

const USLUGI = [
    'psychotesty-kierowcow-zawodowych',
    'psychotesty-po-zatrzymaniu-prawa-jazdy',
    'psychotesty-pojazdy-uprzywilejowane',
    'psychotesty-instruktorow-egzaminatorow',
    'psychotesty-taxi-przewoz-osob',
    'badania-psychotechniczne-operatorow',
    'dla-firm',
];

$GLOBALS['PODGLAD'] = false;

/* ---------- ustawienia ---------- */

/** Czyści dane wczytane w tym żądaniu (panel robi to po zapisie, przed ponownym generowaniem). */
function pamiec_wyczysc(): void
{
    $GLOBALS['PSY_PAMIEC'] = [];
}

/** Ustawienia zapisane w panelu: tylko zmienione klucze, reszta pochodzi z treści startowej. */
function ust_zywe(): array
{
    $zywe = tresc_kat() . '/ustawienia.json';
    if (tresc_kat() === TRESC_START || !is_file($zywe)) {
        return [];
    }
    $z = json_decode((string) file_get_contents($zywe), true);
    return is_array($z) ? $z : [];
}

function ust_start(): array
{
    return json_decode((string) file_get_contents(TRESC_START . '/ustawienia.json'), true, 512, JSON_THROW_ON_ERROR);
}

function ust(?string $k = null): mixed
{
    $u = &$GLOBALS['PSY_PAMIEC']['ust'];
    if ($u === null) {
        // Wartości startowe uzupełniają klucze, których nie ma jeszcze w edytowanych ustawieniach.
        $u = array_merge(ust_start(), ust_zywe());
    }
    return $k === null ? $u : ($u[$k] ?? '');
}

function cennik(): array
{
    $c = &$GLOBALS['PSY_PAMIEC']['cennik'];
    if ($c === null) {
        $c = json_decode((string) file_get_contents(plik_tresci('cennik.json')), true);
        if (!is_array($c)) {
            $c = json_decode((string) file_get_contents(TRESC_START . '/cennik.json'), true, 512, JSON_THROW_ON_ERROR);
        }
    }
    return $c;
}

/* ---------- tekst ---------- */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Czy wartość jest jeszcze polem do uzupełnienia ([[...]]). */
function brak(?string $s): bool
{
    return $s === null || $s === '' || str_contains($s, '[[');
}

function czysty(?string $s): string
{
    return trim(str_replace(['[[', ']]'], '', (string) $s));
}

/** Twarde spacje: po jednoliterowych spójnikach, po skrótach i między liczbą a jednostką. */
function sierotki(string $s): string
{
    $nb = "\u{00A0}";
    $s = preg_replace('/(?<![\p{L}\p{N}])([aiouwzAIOUWZ])[ \t]+(?=\S)/u', '$1' . $nb, $s) ?? $s;
    $s = preg_replace('/(?<![\p{L}])(art\.|ust\.|pkt|poz\.|nr|kat\.|ok\.|ul\.|al\.|tel\.|np\.|r\.ż\.)[ \t]+(?=\S)/u', '$1' . $nb, $s) ?? $s;
    $s = preg_replace('/(\d\.?)[ \t]+(zł|godz\.|min|lat|km|r\.|ms|miesięcy|dni|roku|punktów|stycznia|lutego|marca|kwietnia|maja|czerwca|lipca|sierpnia|września|października|listopada|grudnia)(?![\p{L}])/u', '$1' . $nb . '$2', $s) ?? $s;
    return $s;
}

/** Tekst w linii: ucieczka HTML, pola do uzupełnienia, pogrubienie, linki. */
function inline(string $s): string
{
    $s = e(sierotki($s));
    $s = preg_replace('/\[\[(.+?)\]\]/u', '<span class="brak">$1</span>', $s) ?? $s;
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace_callback('/\[([^\[\]]+)\]\(([^)\s]+)\)/u', static function (array $m): string {
        if (!preg_match('#^(/|\#|https?://|tel:|mailto:)#', $m[2])) {
            return $m[1];
        }
        $zew = preg_match('#^https?://#', $m[2]) ? ' rel="noopener"' : '';
        return '<a href="' . $m[2] . '"' . $zew . '>' . $m[1] . '</a>';
    }, $s) ?? $s;
    return $s;
}

function slug(string $s): string
{
    $s = strtr(czysty($s), [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n', 'Ó' => 'o', 'Ś' => 's', 'Ź' => 'z', 'Ż' => 'z',
    ]);
    $s = preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '';
    return trim($s, '-');
}

/** Uproszczony Markdown: akapity, ## i ###, listy, > uwaga. */
function md(string $src): string
{
    $out = [];
    $par = [];
    $lista = [];
    $typ = '';
    $zamknijPar = static function () use (&$par, &$out): void {
        if ($par) {
            $out[] = '<p>' . inline(implode(' ', $par)) . '</p>';
            $par = [];
        }
    };
    $zamknijListe = static function () use (&$lista, &$typ, &$out): void {
        if ($typ !== '') {
            $li = implode('', array_map(static fn(string $x): string => '<li>' . inline($x) . '</li>', $lista));
            $out[] = "<$typ>$li</$typ>";
            $lista = [];
            $typ = '';
        }
    };
    foreach (preg_split('/\R/u', trim($src)) ?: [] as $l) {
        $l = rtrim($l);
        if ($l === '') {
            $zamknijPar();
            $zamknijListe();
            continue;
        }
        if (preg_match('/^(#{2,3})\s+(.*)$/u', $l, $m)) {
            $zamknijPar();
            $zamknijListe();
            $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
            $out[] = "<$tag id=\"" . slug($m[2]) . '">' . inline($m[2]) . "</$tag>";
            continue;
        }
        if (preg_match('/^-\s+(.*)$/u', $l, $m)) {
            $zamknijPar();
            if ($typ !== 'ul') {
                $zamknijListe();
                $typ = 'ul';
            }
            $lista[] = $m[1];
            continue;
        }
        if (preg_match('/^\d+\.\s+(.*)$/u', $l, $m)) {
            $zamknijPar();
            if ($typ !== 'ol') {
                $zamknijListe();
                $typ = 'ol';
            }
            $lista[] = $m[1];
            continue;
        }
        if (preg_match('/^>\s+(.*)$/u', $l, $m)) {
            $zamknijPar();
            $zamknijListe();
            $out[] = '<p class="uwaga">' . inline($m[1]) . '</p>';
            continue;
        }
        $zamknijListe();
        $par[] = $l;
    }
    $zamknijPar();
    $zamknijListe();
    return implode("\n", $out);
}

/** Sama liczba z ceny ("200 zł" → "200"); pole nieuzupełnione albo bez liczby → null. */
function cena_liczba(?string $s): ?string
{
    if ($s === null || str_contains($s, '[[') || !preg_match('/\d+(?:[.,]\d{1,2})?/', $s, $m)) {
        return null;
    }
    return str_replace(',', '.', $m[0]);
}

/** Czysty tekst (do danych strukturalnych i llms.txt). */
function tekst(string $md): string
{
    $s = preg_replace('/\[([^\[\]]+)\]\(([^)\s]+)\)/u', '$1', $md) ?? $md;
    $s = str_replace(['**', '[[', ']]'], '', $s);
    $s = preg_replace('/^[#>\-]+\s*/mu', '', $s) ?? $s;
    return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}

/** Podstawia {klucz} wartościami z ustawień. */
function tokeny(string $s): string
{
    return preg_replace_callback('/\{([a-z_]+)\}/', static function (array $m): string {
        $v = ust($m[1]);
        return is_string($v) && $v !== '' ? $v : $m[0];
    }, $s) ?? $s;
}

/** Plik .md z nagłówkiem "klucz: wartość" i opcjonalną częścią === pytania ===. */
function wczytaj_md(string $plik, bool $surowe = false): array
{
    return rozloz_md((string) file_get_contents($plik), $surowe);
}

function rozloz_md(string $raw, bool $surowe = false): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    if (!$surowe) {
        $raw = tokeny($raw);
    }
    $meta = [];
    $body = $raw;
    if (preg_match('/^---\R(.*?)\R---\R?(.*)$/su', $raw, $m)) {
        foreach (preg_split('/\R/u', $m[1]) ?: [] as $l) {
            if (preg_match('/^([a-z_0-9]+):\s*(.*)$/u', $l, $k)) {
                $meta[$k[1]] = trim($k[2]);
            }
        }
        $body = $m[2];
    }
    $czesci = preg_split('/^===\s*pytania\s*===\s*$/mu', $body) ?: [$body];
    $faq = [];
    if (isset($czesci[1])) {
        foreach (preg_split('/^###\s+/mu', trim($czesci[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $b) {
            [$q, $a] = array_pad(preg_split('/\R/u', $b, 2) ?: [], 2, '');
            $faq[] = ['p' => trim($q), 'o' => trim($a)];
        }
    }
    return ['meta' => $meta, 'md' => trim($czesci[0]), 'faq' => $faq];
}

function data_pl(string $iso): string
{
    $mies = ['', 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];
    [$r, $m, $d] = array_map('intval', explode('-', $iso));
    return $d . ' ' . $mies[$m] . ' ' . $r . ' r.';
}

/* ---------- kontakt ---------- */

function tel_href(): ?string
{
    $t = (string) ust('telefon');
    if (brak($t)) {
        return null;
    }
    $c = preg_replace('/\D+/', '', $t) ?? '';
    return 'tel:' . (strlen($c) === 9 ? '+48' . $c : '+' . $c);
}

function mapa_href(): ?string
{
    $u = ust();
    if (brak($u['ulica'])) {
        return null;
    }
    $adres = $u['ulica'] . ', ' . (brak($u['kod']) ? '' : $u['kod'] . ' ') . $u['miasto'];
    return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($adres);
}

/* ---------- obrazy ---------- */

/** Zdjęcie: na stronie <picture> z WebP i JPEG, w podglądzie jeden plik wklejony w treść. */
function zdjecie(string $nazwa, array $szer, string $alt, int $w, int $h, string $sizes, string $klasa = ''): string
{
    $kat = '/zasoby/zdjecia/';
    $kl = $klasa !== '' ? ' class="' . $klasa . '"' : '';
    $wym = ' width="' . $w . '" height="' . $h . '" alt="' . e($alt) . '" loading="lazy" decoding="async"';
    if ($GLOBALS['PODGLAD']) {
        $plik = SRC . $kat . $nazwa . ($szer ? '-' . $szer[0] : '') . '.webp';
        return '<img' . $kl . ' src="data:image/webp;base64,' . base64_encode((string) file_get_contents($plik)) . '"' . $wym . '>';
    }
    if (!$szer) {
        return '<picture><source type="image/webp" srcset="' . $kat . $nazwa . '.webp"><img' . $kl . ' src="' . $kat . $nazwa . '.jpg"' . $wym . '></picture>';
    }
    $zestaw = static fn(string $roz): string => implode(', ', array_map(static fn(int $s): string => $kat . $nazwa . '-' . $s . '.' . $roz . ' ' . $s . 'w', $szer));
    return '<picture><source type="image/webp" srcset="' . $zestaw('webp') . '" sizes="' . $sizes . '">'
        . '<img' . $kl . ' src="' . $kat . $nazwa . '-' . end($szer) . '.jpg" srcset="' . $zestaw('jpg') . '" sizes="' . $sizes . '"' . $wym . '></picture>';
}

/* ---------- dane strukturalne (schema.org) ---------- */

function ld_pracownia(): array
{
    $u = ust();
    $d = $u['domena'];
    $adres = ['@type' => 'PostalAddress', 'addressLocality' => $u['miasto'], 'addressRegion' => 'województwo ' . $u['wojewodztwo'], 'addressCountry' => 'PL'];
    if (!brak($u['ulica'])) {
        $adres['streetAddress'] = $u['ulica'];
    }
    if (!brak($u['kod'])) {
        $adres['postalCode'] = $u['kod'];
    }
    $oferty = [];
    foreach (USLUGI as $s) {
        $m = wczytaj_md(plik_tresci("uslugi/$s.md"))['meta'];
        $o = ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $m['h1'], 'url' => "$d/$s/"]];
        if (cena_liczba($m['cena'] ?? '') !== null) {
            $o['price'] = cena_liczba($m['cena']);
            $o['priceCurrency'] = 'PLN';
        }
        $oferty[] = $o;
    }
    $g = [
        '@type' => ['LocalBusiness', 'ProfessionalService'],
        '@id' => $d . '/#pracownia',
        'name' => brak($u['nazwa_firmy']) ? $u['marka'] . ' – ' . $u['psycholog'] : $u['nazwa_firmy'],
        'alternateName' => $u['marka'],
        'description' => 'Pracownia psychologiczna w Częstochowie. Badania psychologiczne kierowców w zakresie psychologii transportu (psychotesty) oraz badania psychotechniczne operatorów i pracowników.',
        'url' => $d . '/',
        'image' => $d . '/og.png',
        'logo' => $d . '/apple-touch-icon.png',
        'currenciesAccepted' => 'PLN',
        'address' => $adres,
        'areaServed' => array_merge(
            [['@type' => 'City', 'name' => 'Częstochowa']],
            array_map(static fn(string $p): array => ['@type' => 'AdministrativeArea', 'name' => $p], array_keys($u['okolice']))
        ),
        'knowsAbout' => ['psychologia transportu', 'badania psychologiczne kierowców', 'psychotesty', 'badania psychotechniczne operatorów'],
        'employee' => [
            '@type' => 'Person',
            '@id' => $d . '/#psycholog',
            'name' => $u['psycholog'],
            'jobTitle' => $u['psycholog_rola'],
            'image' => $d . '/zasoby/zdjecia/monika-dawid-720.jpg',
            'worksFor' => ['@id' => $d . '/#pracownia'],
        ],
        'hasOfferCatalog' => ['@type' => 'OfferCatalog', 'name' => 'Badania psychologiczne i psychotechniczne', 'itemListElement' => $oferty],
    ];
    if (($t = tel_href()) !== null) {
        $g['telephone'] = substr($t, 4);
    }
    if (!brak($u['email'])) {
        $g['email'] = $u['email'];
    }
    if (!brak($u['platnosci'])) {
        $g['paymentAccepted'] = $u['platnosci'];
    }
    if ($u['geo_lat'] !== '' && $u['geo_lng'] !== '') {
        $g['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $u['geo_lat'], 'longitude' => (float) $u['geo_lng']];
    }
    if (!$u['godziny_schema']) {
        $u['godziny_schema'] = godziny_z_tekstu($u['godziny']);
    }
    if ($u['godziny_schema']) {
        $g['openingHoursSpecification'] = array_map(static fn(array $x): array => ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $x[0], 'opens' => $x[1], 'closes' => $x[2]], $u['godziny_schema']);
    }
    if (($m = mapa_href()) !== null) {
        $g['hasMap'] = $m;
    }
    return $g;
}

/** Zamienia wiersze godzin ("poniedziałek – piątek" / "8:00–16:00") na format schema.org. */
function godziny_z_tekstu(array $wiersze): array
{
    $dni = ['pon' => 'Monday', 'wt' => 'Tuesday', 'śr' => 'Wednesday', 'sr' => 'Wednesday', 'czw' => 'Thursday', 'pi' => 'Friday', 'pt' => 'Friday', 'sob' => 'Saturday', 'nie' => 'Sunday', 'ndz' => 'Sunday'];
    $kolej = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $dzien = static function (string $t) use ($dni): ?string {
        $t = mb_strtolower(trim($t));
        foreach ($dni as $pocz => $ang) {
            if (str_starts_with($t, $pocz)) {
                return $ang;
            }
        }
        return null;
    };
    $out = [];
    foreach ($wiersze as $w) {
        if (!is_array($w) || count($w) < 2 || brak($w[0]) || brak($w[1])) {
            continue;
        }
        if (!preg_match('/^\s*(\d{1,2})[:.](\d{2})\s*[–—-]\s*(\d{1,2})[:.](\d{2})\s*$/u', (string) $w[1], $g)) {
            continue;
        }
        $czesci = preg_split('/\s*[–—-]\s*/u', (string) $w[0]) ?: [];
        $od = $dzien($czesci[0] ?? '');
        $do = isset($czesci[1]) ? $dzien($czesci[1]) : $od;
        if ($od === null || $do === null) {
            continue;
        }
        $i = array_search($od, $kolej, true);
        $j = array_search($do, $kolej, true);
        if ($j < $i) {
            continue;
        }
        $out[] = [array_slice($kolej, $i, $j - $i + 1), sprintf('%02d:%s', $g[1], $g[2]), sprintf('%02d:%s', $g[3], $g[4])];
    }
    return $out;
}

function ld_okruszki(array $sciezka): array
{
    $d = ust('domena');
    $el = [];
    foreach ($sciezka as $i => [$href, $nazwa]) {
        $el[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $nazwa, 'item' => $d . $href];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $el];
}

function ld_faq(array $faq): array
{
    return [
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn(array $x): array => [
            '@type' => 'Question',
            'name' => tekst($x['p']),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => tekst($x['o'])],
        ], $faq),
    ];
}

function ld_skrypt(array $wezly): string
{
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => $wezly], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return '<script type="application/ld+json">' . str_replace('</', '<\/', $json) . '</script>';
}

/* ---------- pliki ---------- */

function zapisz(string $plik, string $tresc): void
{
    $kat = dirname($plik);
    if (!is_dir($kat)) {
        mkdir($kat, 0775, true);
    }
    file_put_contents($plik, $tresc);
}

function usun_katalog(string $kat): void
{
    if (!is_dir($kat)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kat, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
}

function kopiuj_katalog(string $z, string $do): void
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($z, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $cel = $do . substr($f->getPathname(), strlen($z));
        if ($f->isDir()) {
            is_dir($cel) || mkdir($cel, 0775, true);
        } else {
            is_dir(dirname($cel)) || mkdir(dirname($cel), 0775, true);
            copy($f->getPathname(), $cel);
        }
    }
}
