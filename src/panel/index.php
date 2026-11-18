<?php
declare(strict_types=1);

/* Panel do zmiany treści strony. Cała logika leży w katalogu silnik/panel/ (bez dostępu z internetu). */

define('PANEL', true);
$www = dirname(__DIR__);
require $www . '/silnik/generuj.php';
require $www . '/silnik/panel/funkcje.php';
require $www . '/silnik/panel/widoki.php';
require $www . '/silnik/panel/strony.php';
p_panel($www);
