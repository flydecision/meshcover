<?php
// Devuelve el mapa comunitario (todas las subidas sumadas; ?months=3|6|12 solo las recientes)
require __DIR__ . '/lib.php';

$dir = data_dir();
$months = (int)($_GET['months'] ?? 0);
if (!in_array($months, AGES, true)) $months = 0;
$f = community_file($dir, $months);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');
readfile($f);
