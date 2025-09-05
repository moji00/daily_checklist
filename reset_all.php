<?php
require 'config.php';
if (!is_logged_in()) {
    header('Location:index.php');
    exit;
}

$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

// Reset all main task statuses for today
$stmt = $mysqli->prepare("UPDATE user_tasks SET status='pending' WHERE user_id=? AND task_date=?");
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$stmt->close();

// Reset all sub-description statuses for today
$stmt = $mysqli->prepare("UPDATE user_description_checks SET status='pending' WHERE user_id=? AND check_date=?");
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$stmt->close();

// Redirect back to dashboard
header("Location: user_dashboard.php");
exit;
?>
