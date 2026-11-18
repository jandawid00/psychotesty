#!/bin/bash
# Pełny test na kopii lokalnej: składa stronę, uruchamia serwer PHP, ustawia hasło testowe,
# wpisuje przykładowe dane i przechodzi cały panel w przeglądarce (widok telefonu).
#   narzedzia/test-lokalny.sh [katalog roboczy]      (domyślnie /tmp/psy-test)
set -euo pipefail
TU="$(cd "$(dirname "$0")/.." && pwd)"
T="${1:-/tmp/psy-test}"
PORT=8765
B="http://127.0.0.1:$PORT"
HASLO="trzy-losowe-slowa-77"   # tylko dla kopii lokalnej

pkill -f "php -S 127.0.0.1:$PORT" 2>/dev/null || true
rm -rf "$T"; mkdir -p "$T/www"
php "$TU/narzedzia/zloz.php" "$T/www"
php -r 'require $argv[1] . "/silnik/generuj.php"; $w = generuj_strone($argv[1]); echo "Wygenerowano plików: ", count($w["pliki"]), ", pól do uzupełnienia: ", $w["braki"], "\n";' "$T/www"
php -S 127.0.0.1:$PORT -t "$T/www" >"$T/serwer.log" 2>&1 </dev/null &
SERWER=$!
trap 'kill $SERWER 2>/dev/null || true' EXIT
sleep 1
J="$T/ciastka"
tok() { curl -s -c "$J" -b "$J" "$B/panel/?s=$1" | grep -o 'name="csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
wyslij() { local s="$1"; shift; curl -s -c "$J" -b "$J" -o /dev/null -w "$s: %{http_code}\n" "$@" "$B/panel/?s=$s"; }

wyslij "" --data-urlencode "csrf=$(tok "")" --data-urlencode "haslo=$HASLO" --data-urlencode "haslo2=$HASLO"
wyslij dane --data-urlencode "csrf=$(tok dane)" --data-urlencode "telefon=500 600 700" --data-urlencode "email=kontakt@example.com" \
  --data-urlencode "ulica=ul. Testowa 1" --data-urlencode "kod=42-200" --data-urlencode "dojazd=Wejście od podwórza, pierwsze piętro." \
  --data-urlencode "parking=bezpłatny parking przed budynkiem" --data-urlencode "czas=ok. 1,5 godz." --data-urlencode "czas_operator=ok. 1 godz." \
  --data-urlencode "orzeczenie=w dniu badania" --data-urlencode "terminy=1–2 dni" --data-urlencode "platnosci=gotówka, karta, BLIK" \
  --data-urlencode "nazwa_firmy=Pracownia Testowa" --data-urlencode "nip=526-000-12-46" --data-urlencode "wpis_pracowni=P/1/2020" \
  --data-urlencode "wpis_psychologa=123" --data-urlencode "notka=Psycholog transportu z doświadczeniem." \
  --data-urlencode "gd[]=poniedziałek – piątek" --data-urlencode "gg[]=8:00–16:00" --data-urlencode "gd[]=sobota" --data-urlencode "gg[]=po umówieniu" \
  --data-urlencode "gd[]=niedziela" --data-urlencode "gg[]=nieczynne"
wyslij cennik --data-urlencode "csrf=$(tok cennik)" \
  --data-urlencode "cn[]=Badanie psychologiczne kierowcy" --data-urlencode "co[]=Psychologia transportu." --data-urlencode "cc[]=200" --data-urlencode "ck[]=cena_kierowcy" \
  --data-urlencode "cn[]=Badanie psychotechniczne operatora" --data-urlencode "co[]=Wózki widłowe." --data-urlencode "cc[]=150 zł" --data-urlencode "ck[]=cena_operator" \
  --data-urlencode "cn[]=Badanie kierowcy kat. B" --data-urlencode "co[]=Na skierowanie." --data-urlencode "cc[]=140" --data-urlencode "ck[]="
rm -f "$J"
python3 "$TU/narzedzia/test-panel.py" "$B" "$HASLO" "$T/www" "$T/zrzuty" && W=0 || W=$?
echo "--- ostrzeżenia PHP w logu serwera:"; grep -i "warning\|fatal\|notice\|deprecated" "$T/serwer.log" | head -5 || true
exit $W
