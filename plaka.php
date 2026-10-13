<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');
set_time_limit(60);

$TXT_FILE = __DIR__ . '/plaka.txt';
if (!file_exists($TXT_FILE)) {
    foreach (['plaka.sql','77k_plaka.txt','77k_plaka.sql'] as $alt) {
        if (file_exists(__DIR__ . '/' . $alt)) { $TXT_FILE = __DIR__ . '/' . $alt; break; }
    }
}
if (!file_exists($TXT_FILE)) {
    echo json_encode(['error' => 'plaka.txt bulunamadı'], JSON_UNESCAPED_UNICODE);
    exit;
}

$q = trim($_GET['q'] ?? '');
$mode = trim($_GET['mode'] ?? 'auto');
if ($q === '') { echo json_encode(['error' => 'q gerekli'], JSON_UNESCAPED_UNICODE); exit; }

$handle = fopen($TXT_FILE, 'r');
if (!$handle) { echo json_encode(['error' => 'Dosya açılamadı'], JSON_UNESCAPED_UNICODE); exit; }

$qLower = mb_strtolower($q, 'UTF-8');
$qUpper = mb_strtoupper($q, 'UTF-8');
$qClean = str_replace(' ', '', $qUpper);

$results = [];
$limit = 50;

while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if ($line === '') continue;

    if (!preg_match_all(
        "/\(\s*\d+\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'/u",
        $line, $matches, PREG_SET_ORDER
    )) continue;

    foreach ($matches as $m) {
        $plaka = trim(str_replace("\\'", "'", $m[1]));
        $isim  = trim(str_replace("\\'", "'", $m[2]));
        if ($plaka === '' || $isim === '') continue;

        $plakaClean = str_replace(' ', '', mb_strtoupper($plaka, 'UTF-8'));

        $match = false;
        if ($mode === 'plaka' || $mode === 'auto') {
            if ($qClean !== '' && mb_stripos($plakaClean, $qClean, 0, 'UTF-8') !== false) $match = true;
        }
        if (!$match && ($mode === 'adsoyad' || $mode === 'auto')) {
            if (mb_stripos($isim, $q, 0, 'UTF-8') !== false) $match = true;
            if (!$match && mb_stripos($isim, $qLower, 0, 'UTF-8') !== false) $match = true;
            if (!$match && mb_stripos($isim, $qUpper, 0, 'UTF-8') !== false) $match = true;
        }

        if ($match) {
            $results[] = ['plaka' => $plaka, 'isim' => $isim];
            if (count($results) >= $limit) break 2;
        }
    }
}
fclose($handle);

if (empty($results)) {
    echo json_encode(['success' => false, 'query' => $q, 'error' => 'Kayıt bulunamadı'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'query' => $q, 'toplam' => count($results), 'kayitlar' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);