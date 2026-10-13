<?php
/* ============================================================
   users.php — Merkezi Kullanıcı + Rol + Süre + Ban + IP Ban
   ============================================================ */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$USERS_FILE = __DIR__ . '/users.json';
$BAN_FILE   = __DIR__ . '/banlist.json';
$IPBAN_FILE = __DIR__ . '/ipban.json';

/* ---------- YARDIMCILAR ---------- */
function readJson($f){
    if (!file_exists($f)) return [];
    $d = file_get_contents($f);
    $a = json_decode($d, true);
    return is_array($a) ? $a : [];
}
function writeJson($f, $a){
    file_put_contents($f, json_encode($a, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function getRealIP(){
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '-';
}

/* ---------- KURUCU OTOMATİK OLUŞTUR ---------- */
$users = readJson($USERS_FILE);
if (!isset($users['baba3101'])) {
    $users['baba3101'] = [
        'pass'    => password_hash('vantoriumreisi01', PASSWORD_BCRYPT),
        'role'    => 'kurucu',
        'created' => time(),
        'ip'      => '-'
    ];
    writeJson($USERS_FILE, $users);
}

/* ---------- SÜRESİ DOLAN ROLLERİ TEMİZLE ---------- */
$users = readJson($USERS_FILE);
$changed = false;
$now = time();
foreach ($users as $u => &$data) {
    if (isset($data['expires']) && $data['expires'] > 0 && $data['expires'] < $now) {
        // Kurucu hariç herkes düşer
        if ($data['role'] !== 'kurucu') {
            $data['role'] = 'user';
        }
        unset($data['expires']);
        unset($data['duration_text']);
        $changed = true;
    }
}
unset($data);
if ($changed) writeJson($USERS_FILE, $users);

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$ip     = getRealIP();

/* ============================================================
   IP BAN KONTROLÜ — her istekte önce buraya bak
============================================================ */
$ipbans = readJson($IPBAN_FILE);
if (in_array($ip, $ipbans)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'IP_BANNED',
        'message' => "You've been IP banned!",
        'by'      => 'vantorium team'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============================================================
   KAYIT
============================================================ */
if ($action === 'register') {
    $u = trim($input['user'] ?? '');
    $p = $input['pass'] ?? '';

    if (!$u || !$p) { echo json_encode(['success'=>false,'error'=>'Boş alan bıraktın']); exit; }
    if (strlen($u) < 3) { echo json_encode(['success'=>false,'error'=>'Kullanıcı adı en az 3 karakter']); exit; }
    if (strlen($p) < 4) { echo json_encode(['success'=>false,'error'=>'Şifre en az 4 karakter']); exit; }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $u)) { echo json_encode(['success'=>false,'error'=>'Sadece harf, rakam ve _ kullan']); exit; }

    $bans = readJson($BAN_FILE);
    if (in_array($u, $bans)) {
        echo json_encode(['success'=>false,'error'=>'Bu kullanıcı adı yasaklı!']);
        exit;
    }

    $users = readJson($USERS_FILE);
    if (isset($users[$u])) {
        echo json_encode(['success'=>false,'error'=>'Bu kullanıcı adı alınmış!']);
        exit;
    }

    $users[$u] = [
        'pass'    => password_hash($p, PASSWORD_BCRYPT),
        'role'    => 'user',
        'created' => time(),
        'ip'      => $ip
    ];
    writeJson($USERS_FILE, $users);

    echo json_encode(['success'=>true, 'user'=>$u, 'role'=>'user']);
    exit;
}

/* ============================================================
   GİRİŞ
============================================================ */
if ($action === 'login') {
    $u = trim($input['user'] ?? '');
    $p = $input['pass'] ?? '';

    if (!$u || !$p) { echo json_encode(['success'=>false,'error'=>'Boş alan bıraktın']); exit; }

    $bans = readJson($BAN_FILE);
    if (in_array($u, $bans)) {
        echo json_encode(['success'=>false,'error'=>'Hesabın yasaklanmış!']);
        exit;
    }

    $users = readJson($USERS_FILE);
    if (!isset($users[$u]) || !password_verify($p, $users[$u]['pass'])) {
        echo json_encode(['success'=>false,'error'=>'Hatalı kullanıcı adı veya şifre!']);
        exit;
    }

    $users[$u]['ip'] = $ip;
    $users[$u]['last_login'] = time();
    writeJson($USERS_FILE, $users);

    echo json_encode([
        'success' => true,
        'user'    => $u,
        'role'    => $users[$u]['role'],
        'expires' => $users[$u]['expires'] ?? null
    ]);
    exit;
}

/* ============================================================
   KULLANICI LİSTESİ
============================================================ */
if ($action === 'list') {
    $users = readJson($USERS_FILE);
    $bans  = readJson($BAN_FILE);
    $ipbans= readJson($IPBAN_FILE);

    $out = [];
    foreach ($users as $k => $v) {
        $out[] = [
            'user'    => $k,
            'role'    => $v['role'] ?? 'user',
            'created' => $v['created'] ?? 0,
            'ip'      => $v['ip'] ?? '-',
            'banned'  => in_array($k, $bans),
            'expires' => $v['expires'] ?? null,
            'duration_text' => $v['duration_text'] ?? null
        ];
    }
    echo json_encode([
        'success'  => true,
        'users'    => $out,
        'bans'     => $bans,
        'ipbans'   => $ipbans,
        'total'    => count($out)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============================================================
   ROL — süreli / süresiz
============================================================ */
if ($action === 'role') {
    $u = $input['user'] ?? '';
    $r = $input['role'] ?? '';
    $duration = trim($input['duration'] ?? '');
    $valid = ['user','premium','vip','admin','kurucu'];
    if (!in_array($r, $valid)) { echo json_encode(['success'=>false,'error'=>'Geçersiz rol']); exit; }

    $users = readJson($USERS_FILE);
    if (!isset($users[$u])) { echo json_encode(['success'=>false,'error'=>'Kullanıcı bulunamadı']); exit; }

    $users[$u]['role'] = $r;

    if ($duration !== '') {
        if (!preg_match('/^(\d+)\s*(y|a|h|g)$/i', $duration, $m)) {
            echo json_encode(['success'=>false,'error'=>'Geçersiz süre. Örn: 30g, 6a, 1y, 4h']);
            exit;
        }
        $num = intval($m[1]);
        $unit = strtolower($m[2]);
        $multipliers = ['g' => 86400, 'h' => 604800, 'a' => 2592000, 'y' => 31536000];
        $seconds = $num * $multipliers[$unit];
        $expires = time() + $seconds;

        $users[$u]['expires'] = $expires;
        $users[$u]['duration_text'] = $duration;
    } else {
        unset($users[$u]['expires']);
        unset($users[$u]['duration_text']);
    }

    writeJson($USERS_FILE, $users);
    echo json_encode([
        'success'  => true,
        'user'     => $u,
        'role'     => $r,
        'expires'  => $users[$u]['expires'] ?? null,
        'duration' => $duration ?: 'süresiz'
    ]);
    exit;
}

/* ============================================================
   BAN
============================================================ */
if ($action === 'ban') {
    $u = $input['user'] ?? '';
    if (!$u) { echo json_encode(['success'=>false,'error'=>'Kullanıcı adı gerekli']); exit; }
    if ($u === 'baba3101') { echo json_encode(['success'=>false,'error'=>'Kurucu banlanamaz!']); exit; }

    $bans = readJson($BAN_FILE);
    if (!in_array($u, $bans)) $bans[] = $u;
    writeJson($BAN_FILE, $bans);

    $users = readJson($USERS_FILE);
    if (isset($users[$u])) {
        unset($users[$u]);
        writeJson($USERS_FILE, $users);
    }

    echo json_encode(['success'=>true, 'user'=>$u, 'action'=>'banned']);
    exit;
}

/* ============================================================
   UNBAN
============================================================ */
if ($action === 'unban') {
    $u = $input['user'] ?? '';
    if (!$u) { echo json_encode(['success'=>false,'error'=>'Kullanıcı adı gerekli']); exit; }
    $bans = readJson($BAN_FILE);
    $bans = array_values(array_filter($bans, fn($x) => $x !== $u));
    writeJson($BAN_FILE, $bans);
    echo json_encode(['success'=>true, 'user'=>$u, 'action'=>'unbanned']);
    exit;
}

/* ============================================================
   IP BAN
============================================================ */
if ($action === 'ipban') {
    $targetIP = $input['ip'] ?? '';
    if (!$targetIP) { echo json_encode(['success'=>false,'error'=>'IP gerekli']); exit; }
    if (!filter_var($targetIP, FILTER_VALIDATE_IP)) { echo json_encode(['success'=>false,'error'=>'Geçersiz IP']); exit; }

    $ipbans = readJson($IPBAN_FILE);
    if (!in_array($targetIP, $ipbans)) $ipbans[] = $targetIP;
    writeJson($IPBAN_FILE, $ipbans);

    echo json_encode(['success'=>true, 'ip'=>$targetIP, 'action'=>'ipbanned']);
    exit;
}

/* ============================================================
   UNIPBAN
============================================================ */
if ($action === 'unipban') {
    $targetIP = $input['ip'] ?? '';
    if (!$targetIP) { echo json_encode(['success'=>false,'error'=>'IP gerekli']); exit; }
    $ipbans = readJson($IPBAN_FILE);
    $ipbans = array_values(array_filter($ipbans, fn($x) => $x !== $targetIP));
    writeJson($IPBAN_FILE, $ipbans);
    echo json_encode(['success'=>true, 'ip'=>$targetIP, 'action'=>'unipbanned']);
    exit;
}

/* ============================================================
   ME
============================================================ */
if ($action === 'me') {
    echo json_encode(['success'=>true, 'ip'=>$ip]);
    exit;
}

echo json_encode(['error' => 'geçersiz action']);