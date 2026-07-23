<?php
require_once "config/database.php";
require_once "includes/functions.php";
$db = getDB();
$user_name = $_SESSION["fullname"];
$user_id = $_SESSION["user_id"];
$stmt = $db->prepare("SELECT DISTINCT center FROM assets WHERE recipient LIKE ? OR created_by = ? LIMIT 1");
$stmt->execute(["%$user_name%", $user_id]);
$center = $stmt->fetchColumn();
echo json_encode(["center" => $center ?: ""]);
