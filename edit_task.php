<?php
require 'config.php';
if(!is_logged_in()){ header('Location:index.php'); exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
    $task_id = (int)$_POST['task_id'];
    $title   = trim($_POST['title']);
    $desc    = trim($_POST['description']);
    $cat     = trim($_POST['category']);
    $subs    = $_POST['sub_descriptions'] ?? [];
    $sub_ids = $_POST['sub_ids'] ?? [];

    // Update main task
    $stmt = $mysqli->prepare("UPDATE tasks SET title=?, description=?, category=? WHERE id=?");
    $stmt->bind_param("sssi", $title, $desc, $cat, $task_id);
    $stmt->execute();
    $stmt->close();

    // Get existing sub-description IDs from DB
    $existing = [];
    $res = $mysqli->query("SELECT id FROM task_descriptions WHERE task_id=$task_id");
    while($r = $res->fetch_assoc()){ $existing[] = $r['id']; }

    $keep = [];
    foreach($subs as $i=>$text){
        $text = trim($text);
        $sid  = $sub_ids[$i];

        if($sid === "new" && $text !== ""){
            // Insert new subdesc
            $stmt = $mysqli->prepare("INSERT INTO task_descriptions (task_id,text) VALUES (?,?)");
            $stmt->bind_param("is", $task_id, $text);
            $stmt->execute();
            $stmt->close();
        } elseif(is_numeric($sid)){
            $keep[] = (int)$sid;
            // Update existing
            $stmt = $mysqli->prepare("UPDATE task_descriptions SET text=? WHERE id=? AND task_id=?");
            $stmt->bind_param("sii", $text, $sid, $task_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Delete sub-descriptions removed in the form
    $toDelete = array_diff($existing, $keep);
    if(!empty($toDelete)){
        $ids = implode(',', array_map('intval', $toDelete));
        $mysqli->query("DELETE FROM task_descriptions WHERE id IN ($ids)");
    }

    header("Location: user_task.php?modal=edit_success");
    exit;
}
