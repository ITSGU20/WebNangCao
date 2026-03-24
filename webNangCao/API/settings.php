<?php require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = fetchAll("SELECT * FROM settings");
    $data = [];
    foreach ($rows as $r) $data[$r['key']] = $r['value'];
    respond(['ok'=>true,'data'=>$data]);
}

if ($method === 'PUT') {
    requireAdmin();
    $b = getBody();
    foreach ($b as $k => $v) {
        $k = esc($k); $v = esc($v);
        q("INSERT INTO settings (`key`,`value`) VALUES ('$k','$v') ON DUPLICATE KEY UPDATE `value`='$v'");
    }
    respond(['ok'=>true,'msg'=>'Đã lưu cài đặt']);
}
