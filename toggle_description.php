<?php
require 'config.php';
if(!is_logged_in()){ http_response_code(403); exit('forbidden'); }

$uid = $_SESSION['user_id'] ?? 0;
$desc_id = intval($_POST['desc_id'] ?? 0);
$status = ($_POST['status'] ?? 'pending') === 'completed' ? 'completed' : 'pending';
$today = date('Y-m-d');

if(!$uid || !$desc_id){
    http_response_code(400);
    exit('bad request');
}

if($status === 'completed'){
    $stmt = $mysqli->prepare('INSERT INTO user_description_checks (user_id, task_description_id, check_date, status, completed_at)
                              VALUES (?,?,?,?,NOW())
                              ON DUPLICATE KEY UPDATE status=VALUES(status), completed_at=NOW()');
    $stmt->bind_param('iiss', $uid, $desc_id, $today, $status);
} else {
    $stmt = $mysqli->prepare('INSERT INTO user_description_checks (user_id, task_description_id, check_date, status, completed_at)
                              VALUES (?,?,?,?,NULL)
                              ON DUPLICATE KEY UPDATE status=VALUES(status), completed_at=NULL');
    $stmt->bind_param('iiss', $uid, $desc_id, $today, $status);
}
$ok = $stmt->execute();
$stmt->close();

echo $ok ? 'ok' : 'error';
