<?php
declare(strict_types=1);

/* Wspólne funkcje generatora strony. Treści leżą w katalogu tresc/, szablony w szablony.php. */

const SRC = __DIR__;
const TRESC = __DIR__ . '/tresc';

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

function ust(?string $k = null): mixed
{
    static $u = null;
    if ($u === null) {
        $u = json_decode((string) file_get_contents(TRESC . '/ustawienia.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    return $k === null ? $u : ($u[$k] ?? '');
}

function cennik(): array
{
    static $c = null;
    if ($c === null) {
        $c = json_decode((string) file_get_contents(TRESC . '/cennik.json'), true, 512, JSON_THROW_ON_ERROR);
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
function wczytaj_md(string $plik): array
{
    $raw = tokeny((string) file_get_contents($plik));
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
        $m = wczytaj_md(TRESC . "/uslugi/$s.md")['meta'];
        $o = ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $m['h1'], 'url' => "$d/$s/"]];
        if (!empty($m['cena'])) {
            $o['price'] = $m['cena'];
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
    if ($u['godziny_schema']) {
        $g['openingHoursSpecification'] = array_map(static fn(array $x): array => ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $x[0], 'opens' => $x[1], 'closes' => $x[2]], $u['godziny_schema']);
    }
    if (($m = mapa_href()) !== null) {
        $g['hasMap'] = $m;
    }
    return $g;
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
