<?php
require 'config.php';
if(!is_logged_in()){ http_response_code(403); exit('forbidden'); }

$uid = $_SESSION['user_id'] ?? 0;
$utid = intval($_POST['utid'] ?? 0);
$status = ($_POST['status'] ?? 'pending') === 'completed' ? 'completed' : 'pending';
$today = date('Y-m-d');

if(!$uid || !$utid){
    http_response_code(400);
    exit('bad request');
}

// Update main task status
if($status === 'completed'){
    $stmt = $mysqli->prepare('UPDATE user_tasks SET status=?, completed_at=NOW() WHERE id=? AND user_id=? AND task_date=?');
    $stmt->bind_param('siss', $status, $utid, $uid, $today);
} else {
    $stmt = $mysqli->prepare('UPDATE user_tasks SET status=?, completed_at=NULL WHERE id=? AND user_id=? AND task_date=?');
    $stmt->bind_param('siss', $status, $utid, $uid, $today);
}
$ok = $stmt->execute();
$stmt->close();

// Calculate new progress (main + subdescriptions)
$completed = 0;
$total = 0;

// Main descriptions
$stmt = $mysqli->prepare('SELECT ut.status, t.description
                          FROM user_tasks ut
                          JOIN tasks t ON ut.task_id = t.id
                          WHERE ut.user_id=? AND ut.task_date=?');
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    if (trim($row['description']) !== '') {
        $total++;
        if($row['status'] === 'completed') $completed++;
    }
}
$stmt->close();

// Subdescriptions
$stmt = $mysqli->prepare('SELECT td.id, COALESCE(udc.status, "pending") AS dstatus
                          FROM user_tasks ut
                          JOIN tasks t ON ut.task_id = t.id
                          JOIN task_descriptions td ON td.task_id = t.id
                          LEFT JOIN user_description_checks udc
                            ON udc.task_description_id = td.id
                            AND udc.user_id = ?
                            AND udc.check_date = ?
                          WHERE ut.user_id=? AND ut.task_date=?');
$stmt->bind_param('isis', $uid, $today, $uid, $today);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    $total++;
    if($row['dstatus'] === 'completed') $completed++;
}
$stmt->close();

$progress = $total ? round($completed/$total*100) : 0;

header('Content-Type: application/json');
echo json_encode(['ok'=>$ok, 'progress'=>$progress]);