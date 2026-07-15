<?php
require_once "config/database.php";
header("Content-Type: application/json; charset=utf-8");
$db = getDB();
$center = $_GET["center"] ?? "";
$stmt = $db->prepare("SELECT DISTINCT floor FROM assets WHERE center = ? AND floor IS NOT NULL AND floor != \"\" ORDER BY floor");
$stmt->execute([$center]);
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN), JSON_UNESCAPED_UNICODE);
