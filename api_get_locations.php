<?php
require_once "config/database.php";
header("Content-Type: application/json; charset=utf-8");
$db = getDB();
$center = $_GET["center"] ?? "";
$stmt = $db->prepare("SELECT DISTINCT location FROM assets WHERE center = ? AND location IS NOT NULL AND location != \"\" ORDER BY location");
$stmt->execute([$center]);
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN), JSON_UNESCAPED_UNICODE);
