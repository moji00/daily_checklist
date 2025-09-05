<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
if (!is_logged_in()) { header('Location: index.php'); exit; }
$user_id = intval($_POST['user_id'] ?? 0);
if ($user_id !== $_SESSION['user_id'] && !is_admin()) { die('Forbidden'); }
$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';
if (!$current || !$new || !$confirm) { die('Missing fields'); }
if ($new !== $confirm) { die('New passwords do not match'); }
$stmt = $mysqli->prepare('SELECT password FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$row = $res->fetch_assoc()) { die('User not found'); }
$hash = $row['password'];
if (!password_verify($current, $hash)) { die('Current password incorrect'); }
$new_hash = password_hash($new, PASSWORD_DEFAULT);
$u = $mysqli->prepare('UPDATE users SET password = ? WHERE id = ?');
$u->bind_param('si', $new_hash, $user_id);
$u->execute();
echo '<!doctype html><html><body><div style="padding:20px">Password updated. <a href="index.php">Return to login</a></div></body></html>';
?>