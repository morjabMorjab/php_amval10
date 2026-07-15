<?php
session_start();
if(isset($_SESSION["user_id"])) { try { require_once "config/database.php"; $db = getDB(); $db->prepare("INSERT INTO activity_logs (user_id, username, fullname, action, details) VALUES (?,?,?,?,?)")->execute([$_SESSION["user_id"], $_SESSION["username"] ?? "", $_SESSION["fullname"] ?? "", "logout", "خروج از سیستم"]); } catch(Exception $e) {} } session_destroy();
header('Location: login.php');
exit;
?>
