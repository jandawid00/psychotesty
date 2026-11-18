# Psychotesty Częstochowa – źródła strony

Gałąź `zrodla` to źródła. Gałąź `main` to pliki, które pobiera hosting (składa je `narzedzia/zloz.php`).

## Jak to działa

- Strony HTML powstają **na serwerze**. Generator (`src/silnik/generuj.php`) składa je z treści startowej
  (`src/silnik/tresc-startowa/`) oraz zmian zapisanych w panelu.
- Panel (`/panel/`) zapisuje zmiany w katalogu `psychotesty-dane/` obok katalogu strony, czyli poza zasięgiem
  przeglądarki i poza repozytorium. Wdrożenie nowej wersji kodu nie rusza tych danych.
- W danych leżą tylko zmiany: plik, którego nikt nie edytował w panelu, jest brany z treści startowej.
- `aktualizuj.php` pobiera gałąź `main` z GitHuba, podmienia pliki i uruchamia generator.

## Katalogi

- `src/silnik/` – generator, szablony, treść startowa; katalog zamknięty dla przeglądarki (`.htaccess`)
- `src/silnik/panel/` – logika panelu (logowanie, formularze, kopie poprzednich wersji)
- `src/panel/` – wejście do panelu oraz jego styl i skrypt
- `src/zasoby/` – styl i skrypt strony, czcionki, zdjęcia, pliki do katalogu głównego
- `narzedzia/` – składanie gałęzi `main`, testy, zrzuty ekranu

## Praca nad stroną (PHP 8.1+)

    narzedzia/test-lokalny.sh              # składa kopię, uruchamia serwer i przechodzi cały panel w przeglądarce
    php narzedzia/zloz.php <katalog>       # składa pliki gałęzi main do wskazanego katalogu

## Hasło do panelu

Hasło ustawia właściciel przy pierwszym wejściu na `/panel/`. Nie ma go w repozytorium.
Zmiana tekstu w `src/silnik/aktywacja.txt` i wdrożenie kasuje hasło i na 7 dni otwiera ustawianie od nowa.

## Widoczność

Dopóki `ukryj_przed_google` ma wartość `true`, strony mają znacznik noindex i nie powstaje mapa strony.
Przełącza się to w panelu, w części „Widoczność w Google”, po uzupełnieniu wszystkich pól.
