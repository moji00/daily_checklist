<?php
require 'config.php';
if(!is_logged_in()){ header('Location:index.php'); exit; }

date_default_timezone_set('Asia/Manila');

// ✅ Get date range from modal form
$fromDate = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$toDate   = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// ✅ Set file name with range
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=it_checklist_report_'.$fromDate.'_to_'.$toDate.'.csv');

$out = fopen('php://output', 'w');

// ✅ Report Title
fputcsv($out, ['', '', 'TMC IT Daily Checklist - Completed Tasks Report']);
fputcsv($out, ['', '', 'From '.$fromDate.' to '.$toDate]);
fputcsv($out, []);

// ✅ CSV Header row
fputcsv($out, ['User', 'Task Title', 'Description', 'Category', 'Status', 'Completed At']);

// ✅ Query only completed tasks within date range
$sql = "SELECT u.name AS user_name, 
               t.title, 
               t.description, 
               t.category, 
               td.text AS sub_description,
               udc.status, 
               udc.completed_at
        FROM tasks t
        LEFT JOIN task_descriptions td 
               ON td.task_id = t.id
        INNER JOIN user_description_checks udc 
               ON udc.task_description_id = td.id 
               AND udc.status = 'completed'
        INNER JOIN users u 
               ON u.id = udc.user_id
        WHERE DATE(udc.completed_at) BETWEEN ? AND ?
        ORDER BY u.name ASC, t.category DESC, t.id ASC, td.id ASC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ss", $fromDate, $toDate);
$stmt->execute();
$res = $stmt->get_result();

// ✅ Loop results
$lastTaskKey = null;
while($r = $res->fetch_assoc()){
    $completedTime = $r['completed_at'] ? date('Y-m-d H:i:s', strtotime($r['completed_at'])) : '';

    // Unique key (user + task title)
    $taskKey = $r['user_name'] . '_' . $r['title'];

    if ($lastTaskKey !== $taskKey) {
        // Print main task row
        fputcsv($out, [
            $r['user_name'],
            $r['title'],
            $r['description'],
            $r['category'],
            $r['status'],
            $completedTime
        ]);
        $lastTaskKey = $taskKey;

        // If task has sub-description
        if ($r['sub_description']) {
            fputcsv($out, [
                '', '', 
                $r['sub_description'], 
                '', 
                $r['status'], 
                $completedTime
            ]);
        }
    } else {
        // Print only sub-description
        if ($r['sub_description']) {
            fputcsv($out, [
                '',
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
?>
