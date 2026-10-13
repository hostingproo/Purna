<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

$iban = strtoupper(str_replace(' ', '', trim($_GET['iban'] ?? '')));

if ($iban === '') { echo json_encode(['success'=>false,'error'=>'IBAN gerekli']); exit; }
if (strlen($iban) !== 26 || substr($iban, 0, 2) !== 'TR') {
    echo json_encode(['success'=>false,'error'=>'Geçersiz TR IBAN (26 karakter olmalı)']);
    exit;
}

function ibanValidate($iban) {
    $iban = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    for ($i = 0; $i < strlen($iban); $i++) {
        $c = $iban[$i];
        $numeric .= ctype_alpha($c) ? (ord($c) - 55) : $c;
    }
    $mod = 0;
    for ($i = 0; $i < strlen($numeric); $i++) {
        $mod = ($mod * 10 + intval($numeric[$i])) % 97;
    }
    return $mod === 1;
}

if (!ibanValidate($iban)) { echo json_encode(['success'=>false,'error'=>'IBAN geçersiz (checksum hatası)']); exit; }

$bankCode = substr($iban, 4, 5);
$banks = [
    '00001'=>'T.C. Ziraat Bankası','00010'=>'Türkiye Halk Bankası','00012'=>'Türkiye Vakıflar Bankası',
    '00015'=>'Türkiye Eximbank','00032'=>'Türk Ekonomi Bankası','00034'=>'Akbank','00046'=>'Akbank',
    '00059'=>'Şekerbank','00062'=>'Türkiye Garanti Bankası','00064'=>'Türkiye İş Bankası','00067'=>'Yapı ve Kredi Bankası',
    '00092'=>'Citibank','00096'=>'Turkish Bank','00098'=>'JPMorgan Chase','00103'=>'ING Bank',
    '00111'=>'QNB Finansbank','00115'=>'Denizbank','00123'=>'HSBC Bank','00124'=>'Alternatifbank',
    '00125'=>'Burgan Bank','00129'=>'Türkiye Finans Katılım','00135'=>'Anadolubank','00138'=>'QNB Finansbank',
    '00139'=>'Şekerbank','00142'=>'Garanti BBVA','00143'=>'Aktif Yatırım Bankası','00146'=>'Odeabank',
    '00147'=>'Kuveyt Türk Katılım','00148'=>'Türkiye Finans Katılım','00149'=>'Albaraka Türk Katılım',
    '00151'=>'Ziraat Katılım','00152'=>'Vakıf Katılım','00153'=>'Türkiye Emlak Katılım',
    '00155'=>'QNB Finansbank','00156'=>'İller Bankası','00157'=>'Türkiye Kalkınma Bankası',
    '00158'=>'Türkiye Sınai Kalkınma Bankası'
];
$banka = $banks[$bankCode] ?? 'Bilinmiyor ('.$bankCode.')';

echo json_encode([
    'success'   => true,
    'iban'      => $iban,
    'gecerli'   => true,
    'banka_kodu'=> $bankCode,
    'banka'     => $banka,
    'ulke'      => 'Türkiye (TR)',
    'hesap_no'  => substr($iban, 9, 17) ?: '-',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);