<?php
declare(strict_types=1);

/* Panel: wspólny układ strony i klocki formularzy. */

if (!defined('PANEL')) {
    http_response_code(404);
    exit;
}

function p_info(string $tekst, string $rodzaj = 'ok', ?string $link = null): void
{
    $_SESSION['info'] = ['tekst' => $tekst, 'rodzaj' => $rodzaj, 'link' => $link];
}

/** Wysyła stronę panelu i kończy żądanie. $wstecz: adres powrotu [adres, etykieta] albo null na stronie startowej. */
function p_uklad(string $tytul, string $tresc, ?array $wstecz = null, int $kod = 200, bool $zalogowana = true): never
{
    http_response_code($kod);
    p_naglowki();
    $info = $_SESSION['info'] ?? null;
    unset($_SESSION['info']);
    $v = (string) @filemtime(dirname(__DIR__, 2) . '/panel/panel.css');
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#08703f">
<title><?= e($tytul) ?> – panel strony</title>
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="stylesheet" href="/panel/panel.css?v=<?= e($v) ?>">
</head>
<body>
<header class="gora">
  <div class="w gora__w">
    <a class="gora__nazwa" href="/panel/">Panel strony</a>
<?php if ($zalogowana): ?>
    <a class="gora__link" href="/" target="_blank" rel="noopener">Otwórz stronę</a>
<?php endif; ?>
  </div>
</header>
<main class="w">
<?php if ($wstecz !== null): ?>
  <a class="wstecz" href="<?= e($wstecz[0]) ?>"><?= e($wstecz[1]) ?></a>
<?php endif; ?>
<?php if (is_array($info)): ?>
  <div class="info info--<?= e($info['rodzaj']) ?>" role="status"><p><?= e($info['tekst']) ?><?php if (!empty($info['link'])): ?> <a href="<?= e($info['link']) ?>" target="_blank" rel="noopener">Zobacz na stronie</a><?php endif; ?></p></div>
<?php endif; ?>
<?= $tresc ?>
</main>
<script src="/panel/panel.js?v=<?= e($v) ?>" defer></script>
</body>
</html>
<?php
    exit;
}

/* ---------- klocki formularzy ---------- */

function p_form_start(string $akcja, string $klasa = 'form'): string
{
    return '<form method="post" action="' . e($akcja) . '" class="' . e($klasa) . '" novalidate><input type="hidden" name="csrf" value="' . e(p_csrf()) . '">';
}

function p_form_koniec(string $przycisk = 'Zapisz zmiany'): string
{
    return '<div class="zapisz"><div class="zapisz__w"><button class="btn btn--zolty" type="submit">' . e($przycisk) . '</button></div></div></form>';
}

/**
 * Pole formularza. Opcje: typ (text, tel, email, date, password, textarea), opis, podpowiedz, wiersze,
 * blad, licznik (zalecana liczba znaków), brak (true = pokaż znacznik „do uzupełnienia”), auto (autocomplete), klasa.
 */
function p_pole(string $nazwa, string $etykieta, string $wartosc, array $o = []): string
{
    $typ = $o['typ'] ?? 'text';
    $id = 'f-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($nazwa)) . ($o['nr'] ?? '');
    $blad = $o['blad'] ?? '';
    $opis = $o['opis'] ?? '';
    $opisane = trim(($opis !== '' ? $id . '-o ' : '') . ($blad !== '' ? $id . '-b' : ''));
    $wsp = ' id="' . e($id) . '" name="' . e($nazwa) . '"'
        . ($opisane !== '' ? ' aria-describedby="' . e($opisane) . '"' : '')
        . ($blad !== '' ? ' aria-invalid="true"' : '')
        . (!empty($o['podpowiedz']) ? ' placeholder="np. ' . e($o['podpowiedz']) . '"' : '')
        . (!empty($o['licznik']) ? ' data-licznik="' . (int) $o['licznik'] . '"' : '')
        . ' autocomplete="' . e($o['auto'] ?? 'off') . '"';
    $h = '<div class="pole' . ($blad !== '' ? ' pole--blad' : '') . (!empty($o['klasa']) ? ' ' . e($o['klasa']) : '') . '">';
    $h .= '<label for="' . e($id) . '">' . e($etykieta) . (!empty($o['brak']) ? ' <span class="znacznik">do uzupełnienia</span>' : '') . '</label>';
    if ($opis !== '') {
        $h .= '<p class="pole__opis" id="' . e($id) . '-o">' . e($opis) . '</p>';
    }
    if ($typ === 'textarea') {
        $h .= '<textarea' . $wsp . ' rows="' . (int) ($o['wiersze'] ?? 4) . '">' . e($wartosc) . '</textarea>';
    } else {
        $tryb = ['tel' => 'tel', 'email' => 'email'][$typ] ?? null;
        $h .= '<input type="' . e($typ) . '"' . $wsp . ' value="' . e($wartosc) . '"' . ($tryb ? ' inputmode="' . $tryb . '"' : '')
            . ($typ === 'password' ? ' autocapitalize="none" spellcheck="false"' : '') . '>';
    }
    if ($blad !== '') {
        $h .= '<p class="pole__blad" id="' . e($id) . '-b">' . e($blad) . '</p>';
    }
    return $h . '</div>';
}

function p_bledy_gora(array $bledy): string
{
    if (!$bledy) {
        return '';
    }
    $n = count($bledy);
    $ile = $n === 1 ? 'jedno pole zaznaczone' : ($n < 5 ? $n . ' pola zaznaczone' : $n . ' pól zaznaczonych');
    return '<div class="info info--blad" role="alert"><p>Nic nie zostało zapisane. Popraw ' . $ile . ' na czerwono i zapisz ponownie.</p></div>';
}

/** Ściąga z zasad pisania tekstów, zwijana pod polami z dłuższą treścią. */
function p_sciaga(): string
{
    return '<details class="sciaga"><summary>Jak formatować tekst</summary><div class="sciaga__w">'
        . '<dl>'
        . '<div><dt><code>## Tytuł części</code></dt><dd>śródtytuł (dwa krzyżyki i spacja na początku wiersza)</dd></div>'
        . '<div><dt><code>- pierwszy punkt</code></dt><dd>lista: każdy punkt w osobnym wierszu, zaczyna się od myślnika i spacji</dd></div>'
        . '<div><dt><code>**ważne**</code></dt><dd>pogrubienie</dd></div>'
        . '<div><dt><code>[cennik](/cennik/)</code></dt><dd>link: tekst w nawiasach kwadratowych, adres w okrągłych</dd></div>'
        . '<div><dt><code>&gt; Uwaga</code></dt><dd>wyróżniona uwaga (znak większości i spacja na początku wiersza)</dd></div>'
        . '<div><dt><code>{telefon}</code></dt><dd>wstawka z „Danych pracowni”. Działają też: <code>{czas}</code>, <code>{orzeczenie}</code>, <code>{terminy}</code>, <code>{cena_kierowcy}</code>, <code>{cena_operator}</code>, <code>{email}</code>, <code>{ulica}</code>. Zostaw je, wtedy zmiana danych poprawi tekst sama.</dd></div>'
        . '</dl><p>Akapity oddziel pustym wierszem.</p></div></details>';
}

/** Lista wierszy, które można dodawać, usuwać i przesuwać. $wiersz($dane, $nr) zwraca pola jednego wiersza. */
function p_wiersze(array $wiersze, callable $wiersz, array $pusty, string $dodaj, string $nazwaWiersza): string
{
    $przyciski = '<div class="wiersz__akcje"><button type="button" class="mini" data-wyzej>W górę</button><button type="button" class="mini mini--usun" data-usun>Usuń</button></div>';
    $h = '<div class="wiersze" data-wiersze data-nazwa="' . e($nazwaWiersza) . '">';
    foreach (array_values($wiersze ?: [$pusty]) as $i => $w) {
        $h .= '<fieldset class="wiersz"><legend>' . e($nazwaWiersza) . ' <span data-nr>' . ($i + 1) . '</span></legend>' . $wiersz($w, '-' . $i) . $przyciski . '</fieldset>';
    }
    $h .= '<template><fieldset class="wiersz"><legend>' . e($nazwaWiersza) . ' <span data-nr></span></legend>' . $wiersz($pusty, '-nowy') . $przyciski . '</fieldset></template>';
    return $h . '<button type="button" class="btn btn--kontur" data-dodaj>' . e($dodaj) . '</button></div>';
}

function p_faq_pola(array $faq): string
{
    return p_wiersze(
        $faq,
        static fn(array $x, string $nr): string => p_pole('fp[]', 'Pytanie', (string) ($x['p'] ?? ''), ['nr' => $nr, 'blad' => $x['blad'] ?? ''])
            . p_pole('fo[]', 'Odpowiedź', (string) ($x['o'] ?? ''), ['nr' => $nr, 'typ' => 'textarea', 'wiersze' => 4]),
        ['p' => '', 'o' => ''],
        'Dodaj pytanie',
        'Pytanie'
    );
}

function p_data_godz(?DateTimeImmutable $d): string
{
    if ($d === null) {
        return '';
    }
    return data_pl($d->format('Y-m-d')) . ', ' . $d->format('G:i');
}
