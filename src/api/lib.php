<?php
// Mapa comunitario de cobertura: utilidades comunes a upload.php y community.php.
// Los datos se guardan en ../data (fuera del alcance de la web gracias a su .htaccess).

const BUILD = 8;
const MIN_FILES_INFO = 5;        // con menos subidas en total, no se publican pasadas ni recepción por celda (privacidad)                  // versión de community.json: al cambiarla se rehace
const CELL_SIZE = 250;            // tamaño fijo de celda de los archivos exportados por la página
const MAX_BYTES = 524288;         // 512 KB por subida
const MAX_CELLS = 20000;
const MAX_NODES_PER_CELL = 60;
const RATE_MAX = 6;               // subidas por IP…
const RATE_WINDOW = 3600;         // …por hora

function data_dir(): string {
  $d = dirname(__DIR__) . '/data';
  if (!is_dir($d)) @mkdir($d, 0755, true);
  // la carpeta de datos nunca se sirve directamente
  $ht = $d . '/.htaccess';
  if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
  foreach (['uploads', 'rate'] as $sub) if (!is_dir("$d/$sub")) @mkdir("$d/$sub", 0755, true);
  return $d;
}

function reply(int $code, array $body): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function num($v, float $min, float $max): ?float {
  if (!is_int($v) && !is_float($v)) return null;
  $f = (float)$v;
  return ($f >= $min && $f <= $max && is_finite($f)) ? $f : null;
}

// Comprueba un archivo «meshcover-coverage» (o el nombre antiguo «meshtastic-coverage») y devuelve una copia limpia (solo los campos conocidos)
function clean_coverage($data): array {
  if (!is_array($data) || !in_array($data['format'] ?? null, ['meshcover-coverage', 'meshtastic-coverage'], true)) throw new Exception('format');
  if (($data['version'] ?? null) !== 1) throw new Exception('version');
  if (($data['cellSize'] ?? null) !== CELL_SIZE) throw new Exception('cellSize');
  $cells = $data['cells'] ?? null;
  if (!is_array($cells) || !count($cells) || count($cells) > MAX_CELLS) throw new Exception('cells');
  $out = [];
  foreach ($cells as $c) {
    if (!is_array($c)) throw new Exception('cell');
    $lat = num($c['lat'] ?? null, -90, 90); $lon = num($c['lon'] ?? null, -180, 180);
    $n = num($c['n'] ?? null, 0, 1000000);
    if ($lat === null || $lon === null || $n === null || floor($n) != $n) throw new Exception('cell');
    $nodes = $c['nodes'] ?? [];
    if (!is_array($nodes) || count($nodes) > MAX_NODES_PER_CELL) throw new Exception('nodes');
    $cn = [];
    foreach ($nodes as $o) {
      if (!is_array($o)) throw new Exception('node');
      $id = $o['id'] ?? '';
      if (!is_string($id) || !preg_match('/^![0-9a-f]{8}$/', $id)) throw new Exception('node id');
      $nn = num($o['n'] ?? null, 1, 1000000);
      if ($nn === null || floor($nn) != $nn) throw new Exception('node n');
      // sin nombre del nodo: el id basta para sumar y los nombres los pone la página con los registros propios
      $node = ['id' => $id, 'n' => (int)$nn];
      if (array_key_exists('snr', $o)) { $v = num($o['snr'], -40, 30); if ($v === null) throw new Exception('snr'); $node['snr'] = round($v, 1); }
      if (array_key_exists('rssi', $o)) { $v = num($o['rssi'], -200, 10); if ($v === null) throw new Exception('rssi'); $node['rssi'] = (int)round($v); }
      if (array_key_exists('sf', $o)) { $v = num($o['sf'], 5, 12); if ($v === null || floor($v) != $v) throw new Exception('sf'); $node['sf'] = (int)$v; }
      if (array_key_exists('bw', $o)) { $v = num($o['bw'], 7.8, 1625); if ($v === null) throw new Exception('bw'); $node['bw'] = $v; }
      $cn[] = $node;
    }
    $cell = ['lat' => round($lat, 6), 'lon' => round($lon, 6), 'n' => (int)$n, 'nodes' => $cn];
    // transmisión (opcional): mensajes enviados en la celda y cuántos llegaron a la red
    if (array_key_exists('tx', $c)) {
      $t = $c['tx']; $tn = is_array($t) ? num($t['n'] ?? null, 1, 100000) : null; $to = is_array($t) ? num($t['ok'] ?? null, 0, 100000) : null;
      if ($tn === null || $to === null || floor($tn) != $tn || floor($to) != $to || $to > $tn) throw new Exception('tx');
      $cell['tx'] = ['ok' => (int)$to, 'n' => (int)$tn];
    }
    $out[] = $cell;
  }
  $date = (is_string($data['date'] ?? null) && preg_match('/^\d{4}-\d{2}$/', $data['date'])) ? $data['date'] : gmdate('Y-m');
  return ['format' => 'meshcover-coverage', 'version' => 1, 'cellSize' => CELL_SIZE, 'date' => $date, 'cells' => $out];
}

// Antigüedades que se pueden pedir (meses; 0 = todo)
const AGES = [0, 3, 6, 12];

// Suma las subidas en un solo archivo: por celda se suman los paquetes y, por nodo,
// SNR y RSSI se combinan con la media ponderada por nº de paquetes; el SF es el de más paquetes.
// Cada celda lleva también cuántas subidas la tocan y en cuántas se recibió algo, pero solo en franjas
// (privacidad: con pocas personas subiendo, los números exactos alrededor de un hueco señalan su casa):
// f = 1, 2 (2–4) o 5 (5 o más); rx = 0 nunca, 1 a veces, 2 siempre. Y el mes más reciente (last).
// Con $months > 0 solo cuentan las subidas de los últimos $months meses (por el mes de los datos).
function build_community(string $dir, int $months = 0): array {
  $cells = []; $files = 0;
  $since = $months > 0 ? gmdate('Y-m', strtotime('first day of -' . ($months - 1) . ' month')) : '';
  foreach (glob("$dir/uploads/*.json") ?: [] as $f) {
    $d = json_decode((string)@file_get_contents($f), true);
    if (!is_array($d) || !is_array($d['cells'] ?? null)) continue;
    $date = (string)($d['date'] ?? '');
    if ($since !== '' && strcmp($date, $since) < 0) continue;
    $files++;
    foreach ($d['cells'] as $c) {
      $k = sprintf('%.6f,%.6f', $c['lat'], $c['lon']);
      if (!isset($cells[$k])) $cells[$k] = ['lat' => $c['lat'], 'lon' => $c['lon'], 'n' => 0, 'f' => 0, 'fr' => 0, 'txo' => 0, 'txn' => 0, 'last' => '', 'nodes' => []];
      $cells[$k]['n'] += $c['n'];
      $cells[$k]['f']++;
      if ($c['n'] > 0) $cells[$k]['fr']++;
      if (isset($c['tx'])) { $cells[$k]['txo'] += $c['tx']['ok']; $cells[$k]['txn'] += $c['tx']['n']; }
      if (strcmp($date, $cells[$k]['last']) > 0) $cells[$k]['last'] = $date;
      foreach ($c['nodes'] as $o) {
        $id = $o['id'];
        if (!isset($cells[$k]['nodes'][$id])) $cells[$k]['nodes'][$id] = ['id' => $id, 'n' => 0, 'ws' => 0, 'snr' => 0, 'wr' => 0, 'rssi' => 0, 'sf' => []];
        $a = &$cells[$k]['nodes'][$id];
        $a['n'] += $o['n'];
        if (isset($o['snr'])) { $a['snr'] += $o['snr'] * $o['n']; $a['ws'] += $o['n']; }
        if (isset($o['rssi'])) { $a['rssi'] += $o['rssi'] * $o['n']; $a['wr'] += $o['n']; }
        if (isset($o['sf'])) { $key = $o['sf'] . '|' . ($o['bw'] ?? ''); $a['sf'][$key] = ($a['sf'][$key] ?? 0) + $o['n']; }
        unset($a);
      }
    }
  }
  $out = []; $info = $files >= MIN_FILES_INFO;
  foreach ($cells as $c) {
    $nodes = [];
    foreach ($c['nodes'] as $a) {
      $o = ['id' => $a['id'], 'n' => $a['n']];
      if ($a['ws']) $o['snr'] = round($a['snr'] / $a['ws'], 1);
      if ($a['wr']) $o['rssi'] = (int)round($a['rssi'] / $a['wr']);
      if ($a['sf']) { arsort($a['sf']); [$sf, $bw] = explode('|', (string)array_key_first($a['sf'])); $o['sf'] = (int)$sf; if ($bw !== '') $o['bw'] = (float)$bw; }
      $nodes[] = $o;
    }
    $o = ['lat' => $c['lat'], 'lon' => $c['lon'], 'n' => $c['n']];
    if ($info) { $o['f'] = $c['f'] >= 5 ? 5 : ($c['f'] >= 2 ? 2 : 1); $o['rx'] = $c['fr'] == 0 ? 0 : ($c['fr'] >= $c['f'] ? 2 : 1); }
    // transmisión: solo la franja (0 nunca llegó a la red, 1 a veces, 2 siempre), nunca los números
    if ($c['txn'] > 0) $o['tx'] = $c['txo'] == 0 ? 0 : ($c['txo'] >= $c['txn'] ? 2 : 1);
    $o['last'] = $c['last']; $o['nodes'] = $nodes;
    $out[] = $o;
  }
  return ['format' => 'meshcover-coverage', 'version' => 1, 'build' => BUILD, 'cellSize' => CELL_SIZE, 'date' => gmdate('Y-m'),
          'months' => $months, 'files' => $files, 'cells' => $out];
}

// Recalcula community.json si falta o si ha cambiado la carpeta de subidas (también al borrar una a mano)
function community_file(string $dir, int $months = 0): string {
  $f = $months > 0 ? "$dir/community_$months.json" : "$dir/community.json";
  clearstatcache();
  $newest = filemtime("$dir/uploads") ?: 0;
  foreach (glob("$dir/uploads/*.json") ?: [] as $u) $newest = max($newest, filemtime($u) ?: 0);
  // también se rehace si se hizo con otra versión de este archivo (por si cambia el formato)
  $old = is_file($f) && strpos((string)@file_get_contents($f, false, null, 0, 300), '"build":' . BUILD . ',') === false;
  // con antigüedad, además una vez al día (al cambiar de mes salen subidas)
  $day = $months > 0 && is_file($f) && filemtime($f) < time() - 86400;
  if (!is_file($f) || $old || $day || filemtime($f) <= $newest) {
    $lock = fopen("$dir/.lock", 'c');
    if ($lock) flock($lock, LOCK_EX);
    $tmp = "$f." . bin2hex(random_bytes(4));
    file_put_contents($tmp, json_encode(build_community($dir, $months), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    rename($tmp, $f);
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
  }
  return $f;
}

// Límite de subidas por IP; la IP no se guarda, solo un resumen con una sal propia del servidor
function rate_ok(string $dir): bool {
  $saltf = "$dir/salt.txt";
  if (!is_file($saltf)) @file_put_contents($saltf, bin2hex(random_bytes(16)));
  $salt = (string)@file_get_contents($saltf);
  $h = substr(hash('sha256', $salt . ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 24);
  $f = "$dir/rate/$h";
  $now = time();
  // borrar huellas de más de una hora, para no guardar nada más tiempo del necesario
  foreach (glob("$dir/rate/*") ?: [] as $old) if (@filemtime($old) < $now - RATE_WINDOW) @unlink($old);
  $times = array_filter(array_map('intval', explode(',', (string)@file_get_contents($f))), fn($t) => $t > $now - RATE_WINDOW);
  if (count($times) >= RATE_MAX) return false;
  $times[] = $now;
  @file_put_contents($f, implode(',', $times));
  return true;
}
