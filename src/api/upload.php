<?php
// Recibe un archivo de cobertura anónima (el .json que exporta la página) y lo suma al mapa comunitario
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(405, ['ok' => false, 'error' => 'method']);
$raw = file_get_contents('php://input', false, null, 0, MAX_BYTES + 1);
if ($raw === false || $raw === '') reply(400, ['ok' => false, 'error' => 'empty']);
if (strlen($raw) > MAX_BYTES) reply(413, ['ok' => false, 'error' => 'too_big']);
try { $clean = clean_coverage(json_decode($raw, true, 32)); }
catch (Exception $e) { reply(400, ['ok' => false, 'error' => 'invalid', 'detail' => $e->getMessage()]); }

$dir = data_dir();
// el mismo contenido subido dos veces no se suma otra vez (la huella va en el nombre del archivo:
// si se borra una subida, se puede volver a subir)
$hash = substr(hash('sha256', json_encode($clean['cells'])), 0, 16);
if (glob("$dir/uploads/*_$hash.json")) reply(409, ['ok' => false, 'error' => 'duplicate']);
if (!rate_ok($dir)) reply(429, ['ok' => false, 'error' => 'rate']);
$name = gmdate('Ymd_His') . '_' . $hash . '.json';
if (@file_put_contents("$dir/uploads/$name", json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false)
  reply(500, ['ok' => false, 'error' => 'write']);
community_file($dir);
reply(200, ['ok' => true, 'cells' => count($clean['cells'])]);
