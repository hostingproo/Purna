<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$LOGS_FILE    = __DIR__ . '/logs.json';
$QUERIES_FILE = __DIR__ . '/queries.json';

function getRealIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '-';
}
function readJson($file){
    if(!file_exists($file)) return [];
    $d = file_get_contents($file);
    $a = json_decode($d, true);
    return is_array($a) ? $a : [];
}
function writeJson($file, $arr){
    if(count($arr) > 1000) $arr = array_slice($arr, 0, 1000);
    file_put_contents($file, json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function geoLookup($ip){
    if(empty($ip) || $ip === '-') return [];
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $raw = @file_get_contents("https://ipapi.co/{$ip}/json/", false, $ctx);
    if(!$raw) return [];
    return json_decode($raw, true) ?: [];
}

$action = $_GET['action'] ?? '';

if($action === 'log'){
    $ip = getRealIP();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '-';
    $geo = geoLookup($ip);
    $entry = [
        'action'    => $_GET['act'] ?? 'siteye_giris',
        'user'      => $_GET['user'] ?? '-',
        'ip'        => $ip,
        'isp'       => $geo['org'] ?? '-',
        'ulke'      => $geo['country_name'] ?? '-',
        'sehir'     => trim(($geo['city'] ?? '-') . (isset($geo['region']) ? ', '.$geo['region'] : '')),
        'zip'       => $geo['postal'] ?? '-',
        'lat'       => $geo['latitude'] ?? '-',
        'lon'       => $geo['longitude'] ?? '-',
        'browser'   => $_GET['browser'] ?? '-',
        'os'        => $_GET['os'] ?? '-',
        'device'    => $_GET['device'] ?? '-',
        'platform'  => $_GET['platform'] ?? '-',
        'dil'       => $_GET['dil'] ?? '-',
        'ekran'     => $_GET['ekran'] ?? '-',
        'cpu'       => $_GET['cpu'] ?? '-',
        'ram'       => $_GET['ram'] ?? '-',
        'userAgent' => $ua,
        'zaman'     => date('d.m.Y H:i:s')
    ];
    $logs = readJson($LOGS_FILE);
    array_unshift($logs, $entry);
    writeJson($LOGS_FILE, $logs);
    echo json_encode(['ok' => true]);
    exit;
}

if($action === 'qlog'){
    $entry = [
        'user'    => $_GET['user'] ?? '-',
        'type'    => $_GET['type'] ?? '-',
        'value'   => $_GET['value'] ?? '-',
        'ip'      => getRealIP(),
        'zaman'   => date('d.m.Y H:i:s')
    ];
    $q = readJson($QUERIES_FILE);
    array_unshift($q, $entry);
    writeJson($QUERIES_FILE, $q);
    echo json_encode(['ok' => true]);
    exit;
}

if($action === 'get'){ echo json_encode(readJson($LOGS_FILE), JSON_UNESCAPED_UNICODE); exit; }
if($action === 'qget'){ echo json_encode(readJson($QUERIES_FILE), JSON_UNESCAPED_UNICODE); exit; }
if($action === 'clear'){ writeJson($LOGS_FILE, []); echo json_encode(['ok'=>true]); exit; }
if($action === 'qclear'){ writeJson($QUERIES_FILE, []); echo json_encode(['ok'=>true]); exit; }

echo json_encode(['error'=>'geçersiz action']);