<?php
// Test hostingu: czy działa PHP i czy skrypt może zapisywać pliki. Po sprawdzeniu usuń ten plik z serwera.
header('Content-Type: text/plain; charset=utf-8');
echo 'PHP działa, wersja ' . PHP_VERSION . "\n";
$plik = __DIR__ . '/test-zapis.txt';
$ok = @file_put_contents($plik, 'ok') !== false;
echo 'Zapis plików: ' . ($ok ? 'działa' : 'NIE działa') . "\n";
if ($ok) {
    @unlink($plik);
}
