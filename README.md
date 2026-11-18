# Psychotesty Częstochowa – źródła strony

Gałąź `zrodla` to kopia zapasowa źródeł. Gałąź `main` zawiera gotową stronę, którą pobiera hosting.

- `src/tresc/` – treści: `ustawienia.json` (dane pracowni), `cennik.json`, podstrony badań i artykuły w plikach `.md`
- `src/szablony.php`, `src/lib.php`, `src/ikony.php` – szablony i funkcje
- `src/zasoby/` – styl, skrypt, czcionki, zdjęcia, ikony
- `src/buduj.php` – generator

Budowanie (PHP 8.1+):

    php src/buduj.php            # gotowa strona w katalogu public/
    php src/buduj.php podglad    # podgląd w jednym pliku: podglad/index.html

Pola w podwójnych nawiasach kwadratowych w `ustawienia.json` czekają na dane pracowni.
Dopóki `ukryj_przed_google` ma wartość `true`, strona ma znacznik noindex i nie tworzy mapy strony.
