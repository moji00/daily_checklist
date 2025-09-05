<?php
$DB_HOST='127.0.0.1'; $DB_USER='root'; $DB_PASS=''; $DB_NAME='it_checklist_db';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) { /* db may not exist until installer runs */ }
session_start();
function is_logged_in(){ return isset($_SESSION['user_id']); }
function is_admin(){ return isset($_SESSION['role']) && $_SESSION['role']==='admin'; }
?>