<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

$ip = trim($_GET['ip'] ?? '');
if ($ip === '') { echo json_encode(['success'=>false,'error'=>'IP gerekli']); exit; }
if (!filter_var($ip, FILTER_VALIDATE_IP)) { echo json_encode(['success'=>false,'error'=>'Geçersiz IP']); exit; }

$url = 'http://ip-api.com/json/' . urlencode($ip) . '?lang=tr&fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query';

$ctx = stream_context_create(['http' => ['timeout'=>15, 'user_agent'=>'Mozilla/5.0', 'ignore_errors'=>true]]);
$res = @file_get_contents($url, false, $ctx);

if ($res === false) { echo json_encode(['success'=>false,'error'=>'IP API erişilemedi']); exit; }

$data = json_decode($res, true);
if (!$data || ($data['status'] ?? '') !== 'success') {
    echo json_encode(['success'=>false,'error'=>$data['message'] ?? 'Sorgu başarısız']);
    exit;
}

echo json_encode([
    'success'   => true,
    'ip'        => $data['query'] ?? $ip,
    'ulke'      => $data['country'] ?? '-',
    'ulke_kodu' => $data['countryCode'] ?? '-',
    'bolge'     => $data['regionName'] ?? '-',
    'bolge_kod' => $data['region'] ?? '-',
    'sehir'     => $data['city'] ?? '-',
    'posta'     => $data['zip'] ?? '-',
    'enlem'     => $data['lat'] ?? '-',
    'boylam'    => $data['lon'] ?? '-',
    'timezone'  => $data['timezone'] ?? '-',
    'isp'       => $data['isp'] ?? '-',
    'org'       => $data['org'] ?? '-',
    'as'        => $data['as'] ?? '-'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);