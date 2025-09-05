<?php
require 'config.php';

if (!is_logged_in()) {
    header('Location:index.php');
    exit;
}

// Allow only admins to delete tasks
if (!is_admin()) {
    die("Unauthorized action.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    $task_id = intval($_POST['task_id']);

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // Delete sub-descriptions linked to this task
        $stmt = $mysqli->prepare("DELETE FROM task_descriptions WHERE task_id=?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $stmt->close();

        // Delete user task records linked to this task
        $stmt = $mysqli->prepare("DELETE FROM user_tasks WHERE task_id=?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $stmt->close();

        // Delete the task itself
        $stmt = $mysqli->prepare("DELETE FROM tasks WHERE id=?");
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $stmt->close();

        // Commit changes
        $mysqli->commit();

        header("Location: user_task.php?msg=Task+deleted+successfully");
        exit;

    } catch (Exception $e) {
        $mysqli->rollback();
        die("Error deleting task: " . $e->getMessage());
    }
} else {
   header("Location: user_task.php?modal=delete_success");
    exit;

}
?>
