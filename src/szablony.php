<?php
declare(strict_types=1);

/* Szablony: wspólny układ, powtarzalne bloki i poszczególne podstrony. */

/* ---------- drobne pomocnicze ---------- */

function attr_tel(): string
{
    $t = tel_href();
    return 'href="' . ($t ?? '/kontakt/') . '"';
}

function attr_mapa(): string
{
    $m = mapa_href();
    return $m === null ? 'href="/kontakt/"' : 'href="' . e($m) . '" target="_blank" rel="noopener"';
}

function adres_html(): string
{
    $u = ust();
    return inline($u['ulica']) . ', ' . inline($u['kod']) . ' ' . e($u['miasto']);
}

function meta_uslugi(string $slug): array
{
    static $pamiec = [];
    return $pamiec[$slug] ??= wczytaj_md(TRESC . "/uslugi/$slug.md");
}

function artykuly(): array
{
    static $lista = null;
    if ($lista === null) {
        $lista = [];
        foreach (glob(TRESC . '/poradnik/*.md') ?: [] as $plik) {
            $a = wczytaj_md($plik);
            $a['slug'] = basename($plik, '.md');
            $lista[] = $a;
        }
        usort($lista, static fn(array $a, array $b): int => [$b['meta']['data'], $a['slug']] <=> [$a['meta']['data'], $b['slug']]);
    }
    return $lista;
}

/* ---------- powtarzalne bloki ---------- */

function blok_godziny(): string
{
    $w = '';
    foreach (ust('godziny') as [$dzien, $godz]) {
        $w .= '<div><dt>' . inline($dzien) . '</dt><dd>' . inline($godz) . '</dd></div>';
    }
    return '<dl class="godziny">' . $w . '</dl>';
}

function blok_okolice(): string
{
    $w = '';
    foreach (ust('okolice') as $powiat => $miejsca) {
        $w .= '<div><dt>' . e(mb_strtoupper(mb_substr($powiat, 0, 1)) . mb_substr($powiat, 1)) . '</dt><dd>' . e(implode(', ', $miejsca)) . '</dd></div>';
    }
    return '<dl class="okolice">' . $w . '</dl>';
}

function blok_faq(array $faq, bool $pierwszeOtwarte = true): string
{
    $w = '';
    foreach ($faq as $i => $x) {
        $w .= '<details' . ($i === 0 && $pierwszeOtwarte ? ' open' : '') . '><summary>' . inline($x['p']) . '</summary><div class="odp">' . md($x['o']) . '</div></details>';
    }
    return '<div class="faq">' . $w . '</div>';
}

function tabela_cennik(): string
{
    $w = '';
    foreach (cennik() as $p) {
        $w .= '<tr><th scope="row">' . inline($p['nazwa']) . '<small>' . inline($p['opis']) . '</small></th><td class="cena">' . inline($p['cena']) . '</td></tr>';
    }
    return '<div class="przewin"><table class="cennik"><caption class="ukryj">Cennik badań</caption><thead class="ukryj"><tr><th scope="col">Badanie</th><th scope="col">Cena</th></tr></thead><tbody>' . $w . '</tbody></table></div>';
}

function tablica_faktow(string $fakty): string
{
    $w = '';
    foreach (explode('|', $fakty) as $f) {
        [$k, $v] = array_map('trim', array_pad(explode('=', $f, 2), 2, ''));
        [$v, $dop] = array_map('trim', array_pad(explode('~', $v, 2), 2, ''));
        $w .= '<div><dt>' . inline($k) . '</dt><dd>' . inline($v) . ($dop !== '' ? '<small>' . inline($dop) . '</small>' : '') . '</dd></div>';
    }
    return '<div class="mt"><dl class="mt__pole">' . $w . '</dl></div>';
}

function karta_umow(string $h = 'h2', bool $pelna = false): string
{
    $u = ust();
    ob_start(); ?>
<div class="karta umow">
  <<?= $h ?> class="umow__h">Umów badanie</<?= $h ?>>
  <a class="umow__tel" <?= attr_tel() ?>><?= inline($u['telefon']) ?></a>
  <div class="umow__btn">
    <a class="btn btn--zolty" <?= attr_tel() ?>><?= ikona('telefon', 20) ?>Zadzwoń</a>
    <a class="btn btn--kontur" <?= attr_mapa() ?>><?= ikona('trasa', 20) ?>Dojazd</a>
  </div>
  <ul class="fakty">
    <li><?= ikona('pin') ?><span><?= adres_html() ?></span></li>
    <li><?= ikona('zegar') ?><?= blok_godziny() ?></li>
    <li><?= ikona('kalendarz') ?><span>Najbliższy termin: <?= inline($u['terminy']) ?></span></li>
<?php if ($pelna): ?>
    <li><?= ikona('parking') ?><span><?= inline(ucfirst_pl($u['parking'])) ?></span></li>
    <li><?= ikona('karta') ?><span>Płatność: <?= inline($u['platnosci']) ?></span></li>
<?php endif; ?>
  </ul>
  <p class="umow__kto"><?= zdjecie('monika-dawid-awatar', [], '', 96, 96, '48px') ?><span>Badania prowadzi <strong><?= e($u['psycholog']) ?></strong>, <?= e($u['psycholog_rola']) ?></span></p>
</div>
<?php
    return (string) ob_get_clean();
}

function ucfirst_pl(string $s): string
{
    if (str_starts_with($s, '[[')) {
        return '[[' . ucfirst_pl(substr($s, 2));
    }
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

function okruszki(array $sciezka): string
{
    $w = '';
    $ost = count($sciezka) - 1;
    foreach ($sciezka as $i => [$href, $nazwa]) {
        $w .= $i === $ost ? '<li aria-current="page">' . e($nazwa) . '</li>' : '<li><a href="' . $href . '">' . e($nazwa) . '</a></li>';
    }
    return '<nav aria-label="Ścieżka nawigacji"><ol class="okruszki">' . $w . '</ol></nav>';
}

function naglowek_strony(array $sciezka, string $h1, string $lead, string $znak = '', string $dodatek = ''): string
{
    return '<div class="naglowek"><div class="w">' . okruszki($sciezka)
        . '<div class="naglowek__w">' . $znak . '<h1>' . inline($h1) . '</h1></div>' . ($lead !== '' ? '<p class="lead">' . inline($lead) . '</p>' : '')
        . $dodatek . '</div></div>';
}

function lista_uslug(string $bez = ''): string
{
    $w = '';
    foreach (USLUGI as $s) {
        if ($s === $bez) {
            continue;
        }
        $w .= '<li><a href="/' . $s . '/">' . inline(meta_uslugi($s)['meta']['nazwa']) . '</a></li>';
    }
    return '<ul class="linki">' . $w . '</ul>';
}

/* ---------- układ strony ---------- */

function czesc_gora(string $aktywna = ''): string
{
    $u = ust();
    ob_start(); ?>
<a class="skok" href="#tresc">Przejdź do treści</a>
<header class="gora">
  <div class="w gora__w">
    <a class="logo" href="/" aria-label="<?= e($u['marka']) ?> – strona główna">
      <?= logo_svg() ?>
      <span class="logo__t"><?= e($u['marka']) ?><small><?= e($u['psycholog']) ?>, <?= e($u['psycholog_rola']) ?></small></span>
    </a>
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="nav"><?= ikona('menu') ?><span>Menu</span></button>
    <nav id="nav" class="nav" aria-label="Menu główne">
      <ul>
<?php foreach (MENU as [$href, $etyk]): ?>
        <li><a href="<?= $href ?>"<?= $href === $aktywna ? ' aria-current="page"' : '' ?>><?= e($etyk) ?></a></li>
<?php endforeach; ?>
      </ul>
    </nav>
    <a class="btn btn--zolty gora__tel" <?= attr_tel() ?>><?= ikona('telefon', 20) ?><span><?= inline($u['telefon']) ?></span></a>
  </div>
</header>
<?php
    return (string) ob_get_clean();
}

function czesc_dol(): string
{
    $u = ust();
    ob_start(); ?>
<footer class="dol">
  <div class="dol__pas" aria-hidden="true"></div>
  <div class="w dol__w">
    <div>
      <p class="dol__marka"><?= logo_svg(36) ?><span><?= e($u['marka']) ?></span></p>
      <address>
        <?= inline($u['nazwa_firmy']) ?><br>
        <?= adres_html() ?><br>
        tel. <a <?= attr_tel() ?>><?= inline($u['telefon']) ?></a><br>
        <?= inline($u['email']) ?>
      </address>
    </div>
    <div>
      <h2>Godziny</h2>
      <?= blok_godziny() ?>
    </div>
    <div>
      <h2>Badania</h2>
      <?= lista_uslug() ?>
    </div>
    <div>
      <h2>Informacje</h2>
      <ul class="linki">
        <li><a href="/cennik/">Cennik</a></li>
        <li><a href="/poradnik/">Poradnik</a></li>
        <li><a href="/pytania/">Pytania i odpowiedzi</a></li>
        <li><a href="/kontakt/">Kontakt i dojazd</a></li>
        <li><a href="/polityka-prywatnosci/">Polityka prywatności</a></li>
      </ul>
    </div>
  </div>
  <div class="w dol__stopka">
    <p>Pracownia wpisana do rejestru przedsiębiorców prowadzących pracownię psychologiczną Marszałka Województwa Śląskiego, nr <?= inline($u['wpis_pracowni']) ?>. NIP <?= inline($u['nip']) ?>.</p>
    <p>Stan prawny treści: <?= e(data_pl($u['aktualizacja'])) ?></p>
  </div>
</footer>
<?php
    return (string) ob_get_clean();
}

function czesc_pasek(): string
{
    return '<div class="pasek"><a class="btn btn--zolty" ' . attr_tel() . '>' . ikona('telefon', 20) . 'Zadzwoń</a>'
        . '<a class="btn btn--kontur" ' . attr_mapa() . '>' . ikona('trasa', 20) . 'Dojazd</a></div>';
}

function uklad(array $p): string
{
    $u = ust();
    $d = $u['domena'];
    $kan = $d . $p['sciezka'];
    ob_start(); ?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($p['tytul']) ?></title>
<meta name="description" content="<?= e($p['opis']) ?>">
<?php if (ust('ukryj_przed_google')): ?>
<meta name="robots" content="noindex, nofollow">
<?php elseif (!empty($p['noindex'])): ?>
<meta name="robots" content="noindex, follow">
<?php else: ?>
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
<link rel="canonical" href="<?= e($kan) ?>">
<?php endif; ?>
<meta property="og:locale" content="pl_PL">
<meta property="og:type" content="<?= e($p['og'] ?? 'website') ?>">
<meta property="og:site_name" content="<?= e($u['marka']) ?>">
<meta property="og:title" content="<?= e($p['tytul']) ?>">
<meta property="og:description" content="<?= e($p['opis']) ?>">
<meta property="og:url" content="<?= e($kan) ?>">
<meta property="og:image" content="<?= e($d) ?>/og.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="geo.region" content="PL-SL">
<meta name="geo.placename" content="Częstochowa">
<meta name="theme-color" content="#08703f">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preload" href="/zasoby/fonty/barlow-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/zasoby/fonty/barlow-condensed-600.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/zasoby/styl.css?v=<?= WERSJA ?>">
<script>document.documentElement.className='js'</script>
<?= ld_skrypt($p['ld']) . "\n" ?>
</head>
<body>
<?= czesc_gora($p['aktywna'] ?? '') ?>
<main id="tresc">
<?= $p['glowna'] ?>
</main>
<?= czesc_dol() ?>
<?= czesc_pasek() ?>
<script src="/zasoby/skrypt.js?v=<?= WERSJA ?>" defer></script>
</body>
</html>
<?php
    return (string) ob_get_clean();
}

/* ---------- strona główna ---------- */

function str_start(): array
{
    $u = ust();
    $d = $u['domena'];
    $faq = array_slice(wczytaj_md(TRESC . '/pytania.md')['faq'], 0, 7);
    ob_start(); ?>
<section class="hero">
  <div class="w hero__w">
    <div class="tablica">
      <div class="tablica__pole">
        <div class="tablica__kier">
          <?= strzalka_svg() ?>
          <h1>Psychotesty w&nbsp;Częstochowie <span>Badania psychologiczne kierowców i&nbsp;operatorów</span></h1>
        </div>
        <ul class="wiersze">
          <li><span>Badanie kierowcy</span><b><?= inline($u['cena_kierowcy']) ?></b></li>
          <li><span>Czas badania</span><b><?= inline($u['czas']) ?></b></li>
          <li><span>Orzeczenie</span><b><?= inline($u['orzeczenie']) ?></b></li>
          <li><span>Najbliższy termin</span><b><?= inline($u['terminy']) ?></b></li>
        </ul>
      </div>
    </div>
    <?= karta_umow('h2', true) ?>
  </div>
</section>

<section class="wstep">
  <div class="w wstep__w">
    <p class="wstep__t"><?= inline('Pracownia psychologiczna w Częstochowie. Badamy kierowców zawodowych, kandydatów na prawo jazdy kat. C i D, taksówkarzy, kierowców pojazdów uprzywilejowanych, osoby skierowane przez starostę oraz operatorów maszyn. Badania prowadzi ' . $u['psycholog'] . ', ' . $u['psycholog_rola'] . '.') ?></p>
    <div class="objazd">
      <p class="objazd__pole"><strong><?= inline('Zmiana od 14 listopada 2026 r.') ?></strong> <?= inline('Badanie psychologiczne kierowcy kosztuje 200 zł, wcześniej 150 zł. To opłata urzędowa, taka sama w każdej pracowni.') ?> <a href="/poradnik/psychotesty-200-zl-od-14-listopada-2026/">Kogo dotyczy zmiana</a></p>
    </div>
  </div>
</section>

<section class="sek" id="badania">
  <div class="w">
    <h2>Kogo badamy</h2>
    <ul class="uslugi">
<?php foreach (USLUGI as $s): $m = meta_uslugi($s)['meta']; ?>
      <li class="usluga"><?= znak($m['ikona']) ?><div><h3><a href="/<?= $s ?>/"><?= inline($m['nazwa']) ?></a></h3><p><?= inline($m['krotko']) ?></p></div></li>
<?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="sek sek--biala" id="przebieg">
  <div class="w">
    <h2>Jak wygląda badanie</h2>
    <ol class="kroki">
      <li><h3>Rejestracja</h3><p><?= inline('Pokazujesz dokument tożsamości i mówisz, do czego potrzebne jest orzeczenie.') ?></p></li>
      <li><h3>Rozmowa z psychologiem</h3><p><?= inline('Kilka pytań o doświadczenie za kierownicą, pracę i zdrowie.') ?></p></li>
      <li><h3>Testy</h3><p><?= inline('Zadania na papierze lub komputerze oraz pomiar czasu reakcji i koordynacji na aparatach. Przed każdym testem jest próba.') ?></p></li>
      <li><h3>Orzeczenie</h3><p><?= inline('Psycholog omawia wyniki i wydaje orzeczenie z datą kolejnego badania.') ?></p></li>
    </ol>
    <p class="dalej"><a href="/poradnik/jak-wygladaja-psychotesty-kierowcow/">Przebieg badania krok po kroku</a></p>
  </div>
</section>

<section class="sek" id="co-zabrac">
  <div class="w dwa">
    <div>
      <h2>Co zabrać na badanie</h2>
      <ul class="lista-tak">
        <li><?= ikona('tak') ?><span><?= inline('dokument tożsamości ze zdjęciem') ?></span></li>
        <li><?= ikona('tak') ?><span><?= inline('prawo jazdy, jeśli je masz') ?></span></li>
        <li><?= ikona('tak') ?><span><?= inline('skierowanie, jeśli dostałeś je od pracodawcy, lekarza albo starosty') ?></span></li>
        <li><?= ikona('tak') ?><span><?= inline('okulary lub soczewki, jeśli ich używasz') ?></span></li>
      </ul>
    </div>
    <div>
      <h2>Jak się przygotować</h2>
      <p><?= inline('Wyśpij się i zjedz posiłek. Dzień przed badaniem nie pij alkoholu. Leki przyjmowane na stałe weź jak zwykle i powiedz o nich psychologowi.') ?></p>
      <p class="dalej"><a href="/poradnik/jak-przygotowac-sie-do-psychotestow/">Jak przygotować się do psychotestów</a></p>
    </div>
  </div>
</section>

<section class="sek sek--biala" id="cennik">
  <div class="w">
    <h2>Cennik</h2>
    <?= tabela_cennik() ?>
    <p class="drobne">Płatność: <?= inline($u['platnosci']) ?>. Wystawiamy faktury. <a href="/cennik/">Cennik i zasady płatności</a></p>
  </div>
</section>

<section class="sek" id="psycholog">
  <div class="w osoba">
    <div class="osoba__foto"><?= zdjecie('monika-dawid', [480, 720], $u['psycholog'] . ', ' . $u['psycholog_rola'] . ' z Częstochowy', 720, 900, '(min-width: 800px) 340px, 70vw') ?></div>
    <div class="osoba__t">
      <h2>Badania prowadzi <?= e($u['psycholog']) ?></h2>
      <p class="osoba__rola"><?= e(ucfirst_pl($u['psycholog_rola'])) ?></p>
      <p><?= inline('[[Krótka notka o psychologu: wykształcenie, studia podyplomowe z psychologii transportu, doświadczenie w badaniach kierowców.]]') ?></p>
      <p><?= inline('Przed każdym testem jest instrukcja i próba. Na pytania zawsze jest czas.') ?></p>
      <dl class="wpisy">
        <div><dt>Ewidencja psychologów transportu</dt><dd>Marszałek Województwa Śląskiego, nr <?= inline($u['wpis_psychologa']) ?></dd></div>
        <div><dt>Rejestr pracowni psychologicznych</dt><dd>Marszałek Województwa Śląskiego, nr <?= inline($u['wpis_pracowni']) ?></dd></div>
      </dl>
    </div>
  </div>
</section>

<section class="sek sek--biala" id="refleks">
  <div class="w refleks">
    <div>
      <h2>Sprawdź swój refleks</h2>
      <p><?= inline('Dotknij sygnalizatora i poczekaj na zielone światło. Gdy się zapali, dotknij jak najszybciej.') ?></p>
      <p class="drobne"><?= inline('To zabawa, a nie część badania. Wynik zależy także od telefonu. W pracowni czas reakcji mierzy się na aparaturze przeznaczonej do badań kierowców.') ?></p>
      <p class="refleks__wynik" role="status"></p>
    </div>
    <button class="sygnal" type="button" data-stan="start">
      <span class="sygnal__lampa"></span>
      <span class="sygnal__opis">Dotknij, żeby zacząć</span>
    </button>
  </div>
</section>

<section class="sek" id="dojazd">
  <div class="w dwa">
    <div>
      <h2>Dojazd i godziny</h2>
      <address class="adres"><strong><?= inline($u['nazwa_firmy']) ?></strong><br><?= adres_html() ?></address>
      <?= blok_godziny() ?>
      <ul class="fakty">
        <li><?= ikona('parking') ?><span><?= inline(ucfirst_pl($u['parking'])) ?></span></li>
        <li><?= ikona('karta') ?><span>Płatność: <?= inline($u['platnosci']) ?></span></li>
      </ul>
      <p class="przyciski"><a class="btn btn--kontur" <?= attr_mapa() ?>><?= ikona('trasa', 20) ?>Wyznacz trasę</a> <a class="btn btn--kontur" href="/kontakt/">Kontakt i dojazd</a></p>
    </div>
    <div>
      <h2>Psychotesty dla Częstochowy i okolic</h2>
      <p><?= inline('Badanie można wykonać w dowolnej uprawnionej pracowni, niezależnie od miejsca zamieszkania. Przyjmujemy kierowców z Częstochowy i sąsiednich powiatów.') ?></p>
      <?= blok_okolice() ?>
    </div>
  </div>
</section>

<section class="sek sek--biala" id="pytania">
  <div class="w waska">
    <h2>Częste pytania</h2>
    <?= blok_faq($faq) ?>
    <p class="dalej"><a href="/pytania/">Wszystkie pytania i odpowiedzi</a></p>
  </div>
</section>
<?php
    $glowna = (string) ob_get_clean();
    return [
        'sciezka' => '/',
        'tytul' => 'Psychotesty Częstochowa – badania psychologiczne kierowców',
        'opis' => 'Psychotesty w Częstochowie: kierowcy kat. C, C+E i D, taxi, pojazdy uprzywilejowane, skierowania starosty, operatorzy. Badanie kierowcy 200 zł. Umów termin.',
        'glowna' => $glowna,
        'aktywna' => '',
        'ld' => [
            ['@type' => 'WebSite', '@id' => $d . '/#witryna', 'url' => $d . '/', 'name' => $u['marka'], 'inLanguage' => 'pl-PL', 'publisher' => ['@id' => $d . '/#pracownia']],
            ld_pracownia(),
            ld_faq($faq),
        ],
    ];
}

/* ---------- podstrona badania ---------- */

function str_usluga(string $slug): array
{
    $u = ust();
    $d = $u['domena'];
    $s = meta_uslugi($slug);
    $m = $s['meta'];
    $sciezka = [['/', 'Strona główna'], ["/$slug/", $m['nazwa']]];
    ob_start(); ?>
<?= naglowek_strony($sciezka, $m['h1'], $m['lead'], znak($m['ikona']), tablica_faktow($m['fakty'])) ?>
<div class="sek">
  <div class="w uklad2">
    <article class="proza">
      <?= md($s['md']) ?>
<?php if ($s['faq']): ?>
      <h2 id="pytania">Częste pytania</h2>
      <?= blok_faq($s['faq']) ?>
<?php endif; ?>
    </article>
    <aside class="bok">
      <?= karta_umow('h2') ?>
      <div class="karta">
        <h2 class="karta__h">Inne badania</h2>
        <?= lista_uslug($slug) ?>
      </div>
    </aside>
  </div>
</div>
<?php
    $glowna = (string) ob_get_clean();
    $usluga = [
        '@type' => 'Service',
        '@id' => "$d/$slug/#usluga",
        'name' => $m['h1'],
        'description' => $m['opis'],
        'serviceType' => 'Badanie psychologiczne',
        'url' => "$d/$slug/",
        'provider' => ['@id' => $d . '/#pracownia'],
        'areaServed' => ['@type' => 'City', 'name' => 'Częstochowa'],
    ];
    if (!empty($m['cena'])) {
        $usluga['offers'] = ['@type' => 'Offer', 'price' => $m['cena'], 'priceCurrency' => 'PLN', 'url' => "$d/$slug/"];
    }
    $ld = [ld_pracownia(), $usluga, ld_okruszki($sciezka)];
    if ($s['faq']) {
        $ld[] = ld_faq($s['faq']);
    }
    return [
        'sciezka' => "/$slug/",
        'tytul' => $m['tytul'],
        'opis' => $m['opis'],
        'glowna' => $glowna,
        'aktywna' => $slug === 'dla-firm' ? '/dla-firm/' : '/#badania',
        'ld' => $ld,
    ];
}

/* ---------- cennik ---------- */

function str_cennik(): array
{
    $u = ust();
    $sciezka = [['/', 'Strona główna'], ['/cennik/', 'Cennik']];
    $faq = [
        ['p' => 'Dlaczego badanie kierowcy kosztuje wszędzie tyle samo?', 'o' => 'Opłatę za badanie psychologiczne w zakresie psychologii transportu określa rozporządzenie Ministra Zdrowia. Od 14 listopada 2026 r. wynosi ona 200 zł.'],
        ['p' => 'Czy cena obejmuje orzeczenie?', 'o' => 'Tak. W cenie jest badanie, omówienie wyników i orzeczenie psychologiczne.'],
        ['p' => 'Kto płaci za badanie pracownika?', 'o' => 'Badania okresowe kierowców zatrudnionych na umowę o pracę oraz badania profilaktyczne pracowników opłaca pracodawca. Wystawiamy faktury.'],
    ];
    ob_start(); ?>
<?= naglowek_strony($sciezka, 'Cennik psychotestów', 'Badanie psychologiczne kierowcy kosztuje 200 zł. To opłata urzędowa, taka sama w każdej pracowni w Polsce.') ?>
<div class="sek">
  <div class="w uklad2">
    <article class="proza">
      <?= tabela_cennik() ?>
      <h2 id="platnosc">Płatność</h2>
      <p>Formy płatności: <?= inline($u['platnosci']) ?>. <?= inline('Wystawiamy faktury, także na firmę, która kieruje pracownika na badanie.') ?></p>
      <h2 id="oplata-urzedowa">Opłata urzędowa od 14 listopada 2026 r.</h2>
      <p><?= inline('Od 14 listopada 2026 r. badanie psychologiczne w zakresie psychologii transportu kosztuje 200 zł, wcześniej 150 zł. Dotyczy to kierowców zawodowych, kandydatów na kat. C i D, taksówkarzy, kierowców pojazdów uprzywilejowanych, instruktorów, egzaminatorów oraz osób skierowanych przez starostę.') ?> <a href="/poradnik/psychotesty-200-zl-od-14-listopada-2026/">Szczegóły zmiany</a></p>
      <p><?= inline('Ceny badań psychotechnicznych wykonywanych na potrzeby medycyny pracy ustala pracownia.') ?></p>
      <h2 id="pytania">Pytania o ceny</h2>
      <?= blok_faq($faq) ?>
    </article>
    <aside class="bok">
      <?= karta_umow('h2') ?>
    </aside>
  </div>
</div>
<?php
    return [
        'sciezka' => '/cennik/',
        'tytul' => 'Cennik psychotestów – Częstochowa | badanie kierowcy 200 zł',
        'opis' => 'Cennik pracowni psychologicznej w Częstochowie. Badanie psychologiczne kierowcy 200 zł (opłata urzędowa od 14.11.2026). Badania operatorów i medycyna pracy.',
        'glowna' => (string) ob_get_clean(),
        'aktywna' => '/cennik/',
        'ld' => [ld_pracownia(), ld_okruszki($sciezka), ld_faq($faq)],
    ];
}

/* ---------- kontakt ---------- */

function str_kontakt(): array
{
    $u = ust();
    $sciezka = [['/', 'Strona główna'], ['/kontakt/', 'Kontakt']];
    ob_start(); ?>
<?= naglowek_strony($sciezka, 'Kontakt i dojazd', 'Zadzwoń i powiedz, jakie badanie jest potrzebne. Zaproponujemy termin.') ?>
<div class="sek">
  <div class="w dwa">
    <div>
      <?= karta_umow('h2', true) ?>
    </div>
    <div class="proza">
      <h2 id="adres">Adres</h2>
      <address class="adres"><strong><?= inline($u['nazwa_firmy']) ?></strong><br><?= adres_html() ?><br><?= inline($u['email']) ?></address>
      <h2 id="dojazd">Jak dojechać</h2>
      <p><?= inline('[[Opis dojazdu: najbliższe skrzyżowanie lub przystanek, wejście do budynku, piętro.]]') ?></p>
      <p><?= inline(ucfirst_pl($u['parking'])) ?>.</p>
      <h2 id="jak-sie-umowic">Jak się umówić</h2>
      <ol>
        <li><?= inline('Zadzwoń pod numer ' . $u['telefon'] . '.') ?></li>
        <li><?= inline('Powiedz, do czego potrzebne jest orzeczenie: kurs, kod 95, taxi, skierowanie starosty albo medycyna pracy.') ?></li>
        <li><?= inline('Przyjdź w umówionym terminie z dokumentem tożsamości. Zabierz prawo jazdy, skierowanie i okulary, jeśli ich używasz.') ?></li>
      </ol>
    </div>
  </div>
</div>
<div class="sek sek--biala">
  <div class="w waska">
    <h2>Psychotesty dla Częstochowy i okolic</h2>
    <p><?= inline('Badanie można wykonać w dowolnej uprawnionej pracowni, niezależnie od miejsca zamieszkania. Przyjmujemy kierowców z Częstochowy i sąsiednich powiatów.') ?></p>
    <?= blok_okolice() ?>
    <h2>Dane pracowni</h2>
    <dl class="wpisy">
      <div><dt>Nazwa</dt><dd><?= inline($u['nazwa_firmy']) ?></dd></div>
      <div><dt>NIP</dt><dd><?= inline($u['nip']) ?></dd></div>
      <div><dt>Rejestr pracowni psychologicznych</dt><dd>Marszałek Województwa Śląskiego, nr <?= inline($u['wpis_pracowni']) ?></dd></div>
      <div><dt>Ewidencja psychologów transportu</dt><dd><?= e($u['psycholog']) ?>, nr <?= inline($u['wpis_psychologa']) ?></dd></div>
    </dl>
  </div>
</div>
<?php
    return [
        'sciezka' => '/kontakt/',
        'tytul' => 'Kontakt i dojazd – Psychotesty Częstochowa',
        'opis' => 'Adres, telefon, godziny otwarcia i dojazd do pracowni psychologicznej w Częstochowie. Psychotesty dla kierowców z Częstochowy, Kłobucka, Myszkowa i Lublińca.',
        'glowna' => (string) ob_get_clean(),
        'aktywna' => '/kontakt/',
        'ld' => [ld_pracownia(), ld_okruszki($sciezka)],
    ];
}

/* ---------- pytania ---------- */

function str_pytania(): array
{
    $s = wczytaj_md(TRESC . '/pytania.md');
    $m = $s['meta'];
    $sciezka = [['/', 'Strona główna'], ['/pytania/', 'Pytania']];
    ob_start(); ?>
<?= naglowek_strony($sciezka, $m['h1'], $m['lead']) ?>
<div class="sek">
  <div class="w uklad2">
    <div class="proza">
      <?= blok_faq($s['faq']) ?>
    </div>
    <aside class="bok">
      <?= karta_umow('h2') ?>
    </aside>
  </div>
</div>
<?php
    return [
        'sciezka' => '/pytania/',
        'tytul' => $m['tytul'],
        'opis' => $m['opis'],
        'glowna' => (string) ob_get_clean(),
        'aktywna' => '/pytania/',
        'ld' => [ld_pracownia(), ld_okruszki($sciezka), ld_faq($s['faq'])],
    ];
}

/* ---------- poradnik ---------- */

function str_poradnik(): array
{
    $sciezka = [['/', 'Strona główna'], ['/poradnik/', 'Poradnik']];
    ob_start(); ?>
<?= naglowek_strony($sciezka, 'Poradnik kierowcy', 'Krótkie wyjaśnienia: ile kosztują psychotesty, jak wygląda badanie i jak się do niego przygotować.') ?>
<div class="sek">
  <div class="w uklad2">
    <ul class="wpisy-lista">
<?php foreach (artykuly() as $a): ?>
      <li>
        <h2><a href="/poradnik/<?= $a['slug'] ?>/"><?= inline($a['meta']['h1']) ?></a></h2>
        <p><?= inline($a['meta']['skrot']) ?></p>
        <p class="meta"><time datetime="<?= e($a['meta']['data']) ?>"><?= e(data_pl($a['meta']['data'])) ?></time></p>
      </li>
<?php endforeach; ?>
    </ul>
    <aside class="bok">
      <?= karta_umow('h2') ?>
    </aside>
  </div>
</div>
<?php
    return [
        'sciezka' => '/poradnik/',
        'tytul' => 'Poradnik: psychotesty kierowców – Częstochowa',
        'opis' => 'Poradnik pracowni psychologicznej z Częstochowy: ceny psychotestów w 2026 r., przebieg badania kierowcy krok po kroku i przygotowanie do badania.',
        'glowna' => (string) ob_get_clean(),
        'aktywna' => '/poradnik/',
        'ld' => [ld_pracownia(), ld_okruszki($sciezka)],
    ];
}

function str_artykul(array $a): array
{
    $u = ust();
    $d = $u['domena'];
    $m = $a['meta'];
    $slug = $a['slug'];
    $sciezka = [['/', 'Strona główna'], ['/poradnik/', 'Poradnik'], ["/poradnik/$slug/", $m['h1']]];
    $inne = array_filter(artykuly(), static fn(array $x): bool => $x['slug'] !== $slug);
    ob_start(); ?>
<?= naglowek_strony($sciezka, $m['h1'], $m['lead'], '', '<p class="meta">Aktualizacja: <time datetime="' . e($m['data']) . '">' . e(data_pl($m['data'])) . '</time></p>') ?>
<div class="sek">
  <div class="w uklad2">
    <article class="proza">
      <?= md($a['md']) ?>
    </article>
    <aside class="bok">
      <?= karta_umow('h2') ?>
      <div class="karta">
        <h2 class="karta__h">Czytaj też</h2>
        <ul class="linki">
<?php foreach ($inne as $x): ?>
          <li><a href="/poradnik/<?= $x['slug'] ?>/"><?= inline($x['meta']['h1']) ?></a></li>
<?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</div>
<?php
    return [
        'sciezka' => "/poradnik/$slug/",
        'tytul' => $m['tytul'],
        'opis' => $m['opis'],
        'og' => 'article',
        'glowna' => (string) ob_get_clean(),
        'aktywna' => '/poradnik/',
        'ld' => [
            ld_pracownia(),
            [
                '@type' => 'Article',
                'headline' => $m['h1'],
                'description' => $m['opis'],
                'inLanguage' => 'pl-PL',
                'datePublished' => $m['data'],
                'dateModified' => $m['data'],
                'mainEntityOfPage' => "$d/poradnik/$slug/",
                'image' => $d . '/og.png',
                'author' => ['@id' => $d . '/#pracownia'],
                'publisher' => ['@id' => $d . '/#pracownia'],
            ],
            ld_okruszki($sciezka),
        ],
    ];
}

/* ---------- polityka prywatności i 404 ---------- */

function str_polityka(): array
{
    $s = wczytaj_md(TRESC . '/polityka-prywatnosci.md');
    $m = $s['meta'];
    $sciezka = [['/', 'Strona główna'], ['/polityka-prywatnosci/', 'Polityka prywatności']];
    return [
        'sciezka' => '/polityka-prywatnosci/',
        'tytul' => $m['tytul'],
        'opis' => $m['opis'],
        'glowna' => naglowek_strony($sciezka, $m['h1'], $m['lead']) . '<div class="sek"><div class="w"><article class="proza">' . md($s['md']) . '</article></div></div>',
        'ld' => [ld_okruszki($sciezka)],
    ];
}

function str_404(): array
{
    $sciezka = [['/', 'Strona główna'], ['/404.html', 'Nie ma takiej strony']];
    return [
        'sciezka' => '/404.html',
        'tytul' => 'Nie ma takiej strony – Psychotesty Częstochowa',
        'opis' => 'Strona o podanym adresie nie istnieje.',
        'noindex' => true,
        'glowna' => naglowek_strony($sciezka, 'Nie ma takiej strony', 'Adres jest błędny albo strona została przeniesiona. Wybierz badanie z listy lub wróć na stronę główną.')
            . '<div class="sek"><div class="w uklad2"><div class="proza"><h2>Badania</h2>' . lista_uslug() . '<p class="przyciski"><a class="btn btn--kontur" href="/">Strona główna</a></p></div><aside class="bok">' . karta_umow('h2') . '</aside></div></div>',
        'ld' => [],
    ];
}
