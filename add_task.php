<?php
require 'config.php';
if(!is_logged_in()) { header('Location:index.php'); exit; }

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title = trim($_POST['title']);
    $desc  = trim($_POST['description']);
    $cat   = trim($_POST['category']);
    $subs  = $_POST['sub_descriptions'] ?? [];

    // Insert task
    $stmt = $mysqli->prepare("INSERT INTO tasks (title, description, category) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $desc, $cat);
    $stmt->execute();
    $task_id = $stmt->insert_id;
    $stmt->close();

    // Insert sub-descriptions
    if(!empty($subs)){
        $stmt = $mysqli->prepare("INSERT INTO task_descriptions (task_id, text) VALUES (?, ?)");
        foreach($subs as $s){
            $s = trim($s);
            if($s !== ""){
                $stmt->bind_param("is", $task_id, $s);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    // Seed user_tasks for today
    $today = date('Y-m-d');
    $users = $mysqli->query("SELECT id FROM users");
    while($u = $users->fetch_assoc()){
        $uid = $u['id'];
        $stmt = $mysqli->prepare("INSERT IGNORE INTO user_tasks (user_id,task_id,task_date,status) VALUES (?,?,?,'pending')");
        $stmt->bind_param("iis", $uid, $task_id, $today);
        $stmt->execute();
        $stmt->close();
    }

   header("Location: user_task.php?modal=add_success");
    exit;

}
