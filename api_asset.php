<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();
header("Content-Type: application/json; charset=utf-8");

$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_name = $_SESSION["fullname"];
$user_id = $_SESSION["user_id"];
$id = intval($_GET["id"] ?? 0);

// بدون محدودیت - همه میتونن ببینن (برای ویرایش)
$stmt = $db->prepare("SELECT * FROM assets WHERE id = ?");
$stmt->execute([$id]);
$asset = $stmt->fetch();

if($asset) {
    echo json_encode($asset, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "پیدا نشد"]);
}
?>