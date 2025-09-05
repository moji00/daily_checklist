<?php
require 'config.php';
if (!is_logged_in() || !is_admin()) { header('Location: index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin_dashboard.php'); exit; }

$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? null; // may be empty
$role = ($_POST['role'] === 'admin') ? 'admin' : 'user';

if (!$id || !$name || !$email) { header('Location: admin_dashboard.php?error=missing'); exit; }

// ensure email not used by others
$stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
$stmt->bind_param('si', $email, $id);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    header('Location: admin_dashboard.php?error=email_in_use');
    exit;
}
$stmt->close();

if ($password !== null && $password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $upd = $mysqli->prepare('UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?');
    $upd->bind_param('ssssi', $name, $email, $hash, $role, $id);
} else {
    $upd = $mysqli->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?');
    $upd->bind_param('sssi', $name, $email, $role, $id);
}
$upd->execute();
$upd->close();

header('Location: admin_dashboard.php?action=edited');
exit;
