<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$url = $_GET['url'] ?? '';
if ($url === '') { echo json_encode(['error' => 'url gerekli']); exit; }

$allowed = ['apiv2.ajaxsystems.fun', 'ipapi.co'];
$host = parse_url($url, PHP_URL_HOST);
if (!in_array($host, $allowed)) { echo json_encode(['error' => 'izin yok']); exit; }

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($res === false || $err) { http_response_code(500); echo json_encode(['error'=>$err]); exit; }
http_response_code($code ?: 200);
echo $res;