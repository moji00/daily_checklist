<?php
require 'config.php';
if (!is_logged_in() || !is_admin()) { header('Location: index.php'); exit; }

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: admin_dashboard.php'); exit; }

// prevent deleting yourself
if ($id == $_SESSION['user_id']) {
    header('Location: admin_dashboard.php?error=cant_delete_self');
    exit;
}

$del = $mysqli->prepare('DELETE FROM users WHERE id = ?');
$del->bind_param('i', $id);
$del->execute();
$del->close();

header('Location: admin_dashboard.php?action=deleted');
exit;
