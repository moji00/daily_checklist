<?php
require 'config.php';
if(!is_logged_in()){ header('Location:index.php'); exit; }

date_default_timezone_set('Asia/Manila');
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');
$nowName = date('Y-m-d');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=it_checklist_'.$nowName.'.csv');

$out = fopen('php://output', 'w');

// Add centered title row (will appear at the top when printed)
$title = 'IT Daily Checklist';
fputcsv($out, ['', 'TMC IT Daily Checklist']);

// Add an empty row for spacing
fputcsv($out, []);

// Header
fputcsv($out, ['Task Title','Description','Category','Status','Completed At']);

// Fetch tasks and their sub-descriptions with today's status
$sql = "SELECT t.title, t.description, t.category, td.text AS sub_description,
               COALESCE(udc.status,'pending') AS status,
               udc.completed_at
        FROM tasks t
        LEFT JOIN task_descriptions td ON td.task_id = t.id
        LEFT JOIN user_description_checks udc ON udc.task_description_id = td.id
             AND udc.user_id = ? AND udc.check_date = ?
        ORDER BY t.category DESC, t.id ASC, td.id ASC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$res = $stmt->get_result();

$lastTaskId = null;
while($r = $res->fetch_assoc()){
    $completedTime = $r['completed_at'] ? date('Y-m-d H:i:s', strtotime($r['completed_at'])) : '';
    // Print main description only once per task (first row), then sub-descriptions in next rows
    if ($lastTaskId !== $r['title']) {
        // First row for this task: print main description
        fputcsv($out, [
            $r['title'],
            $r['description'],
            $r['category'],
            $r['status'],
            $completedTime
        ]);
        $lastTaskId = $r['title'];
        // If there is a sub-description, print it in a new row
        if ($r['sub_description']) {
            fputcsv($out, [
                '', // No task title
                $r['sub_description'],
                '', // No category
                $r['status'],
                $completedTime
            ]);
        }
    } else {
        // Only print sub-description row
        if ($r['sub_description']) {
            fputcsv($out, [
                '',
                $r['sub_description'],
                '',
                $r['status'],
                $completedTime
            ]);
        }
    }
}


fclose($out);
exit;