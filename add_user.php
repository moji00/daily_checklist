<?php
require 'config.php';
if (!is_logged_in() || !is_admin()) { header('Location: index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin_dashboard.php'); exit; }

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = ($_POST['role'] === 'admin') ? 'admin' : 'user';

if (!$name || !$email || !$password) {
    header('Location: admin_dashboard.php?error=missing');
    exit;
}

// check unique email
$stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    header('Location: admin_dashboard.php?error=email_exists');
    exit;
}
$stmt->close();

// insert user with hashed password
$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = $mysqli->prepare('INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)');
$ins->bind_param('ssss', $name, $email, $hash, $role);
$ins->execute();
$ins->close();

// redirect back with action param so JS shows success modal
header('Location: admin_dashboard.php?action=added');
exit;
