<?php
declare(strict_types=1);

/*
 * Składa drzewo plików do wdrożenia (gałąź main repozytorium) z katalogu src/.
 *   php narzedzia/zloz.php <katalog docelowy>
 * W katalogu docelowym zostaje nietknięty tylko .git.
 */

$src = dirname(__DIR__) . '/src';
$cel = rtrim($argv[1] ?? '', '/');
if ($cel === '' || !is_dir($cel)) {
    fwrite(STDERR, "Podaj istniejący katalog docelowy.\n");
    exit(1);
}
require $src . '/silnik/lib.php';

function min_css(string $c): string
{
    $c = preg_replace('~/\*.*?\*/~s', '', $c) ?? $c;
    $c = preg_replace('/\s+/', ' ', $c) ?? $c;
    $c = preg_replace('/\s*([{};,])\s*/', '$1', $c) ?? $c;
    $c = preg_replace('/:\s+/', ':', $c) ?? $c;
    return trim(str_replace(';}', '}', $c));
}

foreach (scandir($cel) ?: [] as $n) {
    if (in_array($n, ['.', '..', '.git'], true)) {
        continue;
    }
    is_dir("$cel/$n") ? (usun_katalog("$cel/$n") || true) && rmdir("$cel/$n") : unlink("$cel/$n");
}
kopiuj_katalog($src . '/silnik', $cel . '/silnik');
kopiuj_katalog($src . '/panel', $cel . '/panel');
kopiuj_katalog($src . '/zasoby/fonty', $cel . '/zasoby/fonty');
kopiuj_katalog($src . '/zasoby/zdjecia', $cel . '/zasoby/zdjecia');
kopiuj_katalog($src . '/zasoby/glowny', $cel);
copy($src . '/zasoby/skrypt.js', $cel . '/zasoby/skrypt.js');
file_put_contents($cel . '/zasoby/styl.css', min_css((string) file_get_contents($src . '/zasoby/styl.css')));
echo "Złożono: $cel\n";
