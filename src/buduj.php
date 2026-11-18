<?php
declare(strict_types=1);

/*
 * Generator strony.
 *   php src/buduj.php            -> public/   (gotowa strona do wgrania na serwer)
 *   php src/buduj.php podglad    -> podglad/index.html (cała strona w jednym pliku, do podglądu)
 */

require __DIR__ . '/lib.php';
require __DIR__ . '/ikony.php';
require __DIR__ . '/szablony.php';

$korzen = dirname(__DIR__);
$tryb = $argv[1] ?? 'strona';
$css = (string) file_get_contents(SRC . '/zasoby/styl.css');
$js = (string) file_get_contents(SRC . '/zasoby/skrypt.js');
define('WERSJA', substr(md5($css . $js), 0, 8));

function min_css(string $c): string
{
    $c = preg_replace('~/\*.*?\*/~s', '', $c) ?? $c;
    $c = preg_replace('/\s+/', ' ', $c) ?? $c;
    $c = preg_replace('/\s*([{};,])\s*/', '$1', $c) ?? $c;
    $c = preg_replace('/:\s+/', ':', $c) ?? $c;
    return trim(str_replace(';}', '}', $c));
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

/* ================= podgląd w jednym pliku ================= */

if ($tryb === 'podglad') {
    $GLOBALS['PODGLAD'] = true;

    $linki = static function (string $html): string {
        return preg_replace_callback('/href="(\/[^"]*)"/', static function (array $m): string {
            $h = $m[1];
            if ($h === '/') {
                return 'href="#s-start"';
            }
            if (preg_match('~^/#([a-z0-9-]+)$~', $h, $k)) {
                return 'href="#s-start" data-kotwica="' . $k[1] . '"';
            }
            if (preg_match('~^/([a-z0-9/-]+?)/?(?:#([a-z0-9-]+))?$~', $h, $k)) {
                return 'href="#s-' . str_replace('/', '-', trim($k[1], '/')) . '"' . (!empty($k[2]) ? ' data-kotwica="' . $k[2] . '"' : '');
            }
            return $m[0];
        }, $html) ?? $html;
    };

    $widoki = '';
    foreach (wszystkie_strony() as $p) {
        if ($p['sciezka'] === '/404.html') {
            continue;
        }
        $id = $p['sciezka'] === '/' ? 's-start' : 's-' . str_replace('/', '-', trim($p['sciezka'], '/'));
        $widoki .= '<div data-widok="' . $id . '"' . ($id === 's-start' ? '' : ' hidden') . ">\n" . $linki($p['glowna']) . "\n</div>\n";
    }

    $cssPodglad = preg_replace_callback('~url\(fonty/([a-z0-9-]+\.woff2)\)~', static function (array $m): string {
        return 'url(data:font/woff2;base64,' . base64_encode((string) file_get_contents(SRC . '/zasoby/fonty/' . $m[1])) . ')';
    }, min_css($css));
    $cssPodglad .= '.podglad-info{background:#191b1d;color:#fff;font-size:.93rem;line-height:1.4}.podglad-info p{margin:0;padding:.55rem 0}.podglad-info .brak{background:#fff1b8;color:#3d3000}[data-widok][hidden]{display:none}';

    $router = <<<'JS'
(function () {
  var d = document;
  var widoki = [].slice.call(d.querySelectorAll('[data-widok]'));
  var pomin = false;
  function widoczny() { return d.querySelector('[data-widok]:not([hidden])'); }
  function pokaz(id, kotwica) {
    var jest = widoki.some(function (w) { return w.getAttribute('data-widok') === id; });
    if (!jest) id = 's-start';
    widoki.forEach(function (w) { w.hidden = w.getAttribute('data-widok') !== id; });
    var btn = d.querySelector('.menu-btn'), nav = d.getElementById('nav');
    if (btn && nav) { btn.setAttribute('aria-expanded', 'false'); nav.classList.remove('nav--otwarte'); }
    var cel = kotwica ? widoczny().querySelector('#' + kotwica) : null;
    if (cel) cel.scrollIntoView(); else window.scrollTo(0, 0);
  }
  d.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a') : null;
    if (!a) return;
    var h = a.getAttribute('href') || '';
    if (h.indexOf('#s-') === 0) {
      ev.preventDefault();
      pomin = true;
      try { location.hash = h.slice(1); } catch (e) {}
      setTimeout(function () { pomin = false; }, 50);
      pokaz(h.slice(1), a.getAttribute('data-kotwica'));
    } else if (h.charAt(0) === '#' && h.length > 1) {
      ev.preventDefault();
      var el = widoczny().querySelector(h) || d.querySelector(h);
      if (el) el.scrollIntoView();
    }
  });
  window.addEventListener('hashchange', function () {
    if (pomin) return;
    var id = location.hash.slice(1);
    if (id.indexOf('s-') === 0) pokaz(id);
  });
  var start = location.hash.slice(1);
  if (start.indexOf('s-') === 0) pokaz(start);
})();
JS;

    $html = "<!DOCTYPE html>\n<html lang=\"pl\" class=\"js\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . "<title>Podgląd strony Psychotesty Częstochowa</title>\n<meta name=\"robots\" content=\"noindex\">\n<style>" . $cssPodglad . "</style>\n</head>\n<body>\n"
        . '<div class="podglad-info"><div class="w"><p>Podgląd roboczy. Pola <span class="brak">oznaczone tak</span> czekają na dane pracowni.</p></div></div>' . "\n"
        . $linki(czesc_gora()) . "<main id=\"tresc\">\n" . $widoki . "</main>\n" . $linki(czesc_dol()) . $linki(czesc_pasek())
        . "\n<script>" . $js . "</script>\n<script>" . $router . "</script>\n</body>\n</html>\n";

    zapisz($korzen . '/podglad/index.html', $html);
    echo 'podglad/index.html: ' . round(strlen($html) / 1024) . " KB\n";
    exit;
}

/* ================= właściwa strona ================= */

$out = $korzen . '/public';
usun_katalog($out);
is_dir($out) || mkdir($out, 0775, true);

kopiuj_katalog(SRC . '/zasoby/fonty', $out . '/zasoby/fonty');
kopiuj_katalog(SRC . '/zasoby/zdjecia', $out . '/zasoby/zdjecia');
if (is_dir(SRC . '/zasoby/glowny')) {
    kopiuj_katalog(SRC . '/zasoby/glowny', $out);
}
zapisz($out . '/zasoby/styl.css', min_css($css));
zapisz($out . '/zasoby/skrypt.js', $js);
zapisz($out . '/favicon.svg', '<?xml version="1.0" encoding="UTF-8"?>' . str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ', logo_svg(64)));

$u = ust();
$d = $u['domena'];
$strony = wszystkie_strony();
$doMapy = [];
$braki = 0;
foreach ($strony as $p) {
    $html = uklad($p);
    $braki += substr_count($html, 'class="brak"');
    $plik = str_ends_with($p['sciezka'], '.html') ? $out . $p['sciezka'] : $out . $p['sciezka'] . 'index.html';
    zapisz($plik, $html);
    if (empty($p['noindex'])) {
        $doMapy[] = $p['sciezka'];
    }
}

$ukryta = (bool) ust('ukryj_przed_google');

/* sitemap.xml */
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($doMapy as $s) {
    $xml .= '  <url><loc>' . e($d . $s) . '</loc><lastmod>' . $u['aktualizacja'] . '</lastmod></url>' . "\n";
}
if (!$ukryta) {
    zapisz($out . '/sitemap.xml', $xml . "</urlset>\n");
}

/* robots.txt */
if ($ukryta) {
    // Roboty mogą wejść, żeby zobaczyć znacznik noindex na każdej podstronie.
    zapisz($out . '/robots.txt', "# Strona w przygotowaniu: podstrony mają znacznik noindex.\nUser-agent: *\nAllow: /\n");
} else {
zapisz($out . '/robots.txt', <<<TXT
# Strona jest w całości dostępna dla wyszukiwarek i asystentów AI.
User-agent: *
Allow: /

User-agent: Googlebot
User-agent: bingbot
User-agent: Google-Extended
User-agent: GPTBot
User-agent: OAI-SearchBot
User-agent: ChatGPT-User
User-agent: ClaudeBot
User-agent: Claude-SearchBot
User-agent: Claude-User
User-agent: PerplexityBot
User-agent: Perplexity-User
User-agent: Applebot
User-agent: Applebot-Extended
Allow: /

Sitemap: $d/sitemap.xml

TXT);
}

/* llms.txt – skrót strony dla modeli językowych */
$l = '# ' . $u['marka'] . ' – pracownia psychologiczna (' . $u['psycholog'] . ")\n\n";
$l .= '> Pracownia psychologiczna w Częstochowie (województwo śląskie). Wykonuje badania psychologiczne kierowców w zakresie psychologii transportu (psychotesty) oraz badania psychotechniczne operatorów i pracowników. Badania prowadzi ' . $u['psycholog'] . ', ' . $u['psycholog_rola'] . ".\n\n";
$l .= "## Najważniejsze fakty\n\n";
$l .= "- Badanie psychologiczne kierowcy kosztuje 200 zł. To opłata urzędowa obowiązująca w całej Polsce od 14 listopada 2026 r. (wcześniej 150 zł).\n";
$l .= "- Kierowca zawodowy powtarza badanie co 5 lat, a po ukończeniu 60 lat co 30 miesięcy (art. 39k ustawy o transporcie drogowym).\n";
$l .= "- Na badanie trzeba zabrać dokument tożsamości, prawo jazdy, skierowanie (jeśli jest) oraz okulary lub soczewki.\n";
$l .= '- Obszar: Częstochowa oraz ' . implode(', ', array_keys($u['okolice'])) . ".\n";
if (!brak($u['ulica'])) {
    $l .= '- Adres: ' . $u['ulica'] . ', ' . czysty($u['kod']) . ' ' . $u['miasto'] . ".\n";
}
if (!brak($u['telefon'])) {
    $l .= '- Telefon: ' . $u['telefon'] . ".\n";
}
if (!brak($u['godziny'][0][1])) {
    $l .= '- Godziny: ' . implode('; ', array_map(static fn(array $g): string => $g[0] . ' ' . $g[1], $u['godziny'])) . ".\n";
}
$l .= "\n## Badania\n\n";
foreach (USLUGI as $s) {
    $m = meta_uslugi($s)['meta'];
    $l .= '- [' . $m['h1'] . "]($d/$s/): " . tekst($m['krotko']) . "\n";
}
$l .= "\n## Informacje\n\n- [Cennik]($d/cennik/)\n- [Pytania i odpowiedzi]($d/pytania/)\n- [Kontakt i dojazd]($d/kontakt/)\n\n## Poradnik\n\n";
foreach (artykuly() as $a) {
    $l .= '- [' . $a['meta']['h1'] . "]($d/poradnik/" . $a['slug'] . '/): ' . tekst($a['meta']['skrot']) . "\n";
}
if (!$ukryta) {
    zapisz($out . '/llms.txt', $l);
}

/* .htaccess (Apache, hosting OVH) */
zapisz($out . '/.htaccess', <<<'TXT'
AddDefaultCharset UTF-8
Options -Indexes
ErrorDocument 404 /404.html

<IfModule mod_rewrite.c>
RewriteEngine On
# bez www
RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
RewriteRule ^ https://%1%{REQUEST_URI} [R=301,L]
# zawsze https (przekierowanie tylko wtedy, gdy żaden z sygnałów nie wskazuje na https)
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteCond %{SERVER_PORT} !^443$
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</IfModule>

<IfModule mod_mime.c>
AddType font/woff2 .woff2
AddType image/webp .webp
AddCharset UTF-8 .html .css .js .txt .xml .svg
</IfModule>

<IfModule mod_expires.c>
ExpiresActive On
ExpiresByType text/html "access plus 10 minutes"
ExpiresByType text/css "access plus 1 year"
ExpiresByType text/javascript "access plus 1 year"
ExpiresByType application/javascript "access plus 1 year"
ExpiresByType font/woff2 "access plus 1 year"
ExpiresByType image/webp "access plus 1 month"
ExpiresByType image/jpeg "access plus 1 month"
ExpiresByType image/png "access plus 1 month"
ExpiresByType image/svg+xml "access plus 1 month"
ExpiresByType image/x-icon "access plus 1 month"
</IfModule>

<IfModule mod_deflate.c>
AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript image/svg+xml text/plain text/xml application/xml
</IfModule>

<IfModule mod_headers.c>
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Permissions-Policy "geolocation=(), camera=(), microphone=()"
</IfModule>

TXT);

echo 'public/: ' . count($strony) . ' stron, pól do uzupełnienia: ' . $braki . ', wersja zasobów ' . WERSJA . ($ukryta ? ', UKRYTA przed wyszukiwarkami (noindex)' : '') . "\n";
