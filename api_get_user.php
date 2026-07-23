<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();
header("Content-Type: application/json; charset=utf-8");
$db = getDB();
$id = intval($_GET["id"] ?? 0);
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
echo json_encode($stmt->fetch(), JSON_UNESCAPED_UNICODE);
