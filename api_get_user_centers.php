<?php
require_once "../config/database.php";
$db = getDB();
$id = intval($_GET["id"] ?? 0);
$centers = $db->prepare("SELECT center_name FROM user_centers WHERE user_id = ?");
$centers->execute([$id]);
echo json_encode($centers->fetchAll(PDO::FETCH_COLUMN));
