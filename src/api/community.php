<?php
// Devuelve el mapa comunitario (todas las subidas sumadas)
require __DIR__ . '/lib.php';

$dir = data_dir();
$f = community_file($dir);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');
readfile($f);
