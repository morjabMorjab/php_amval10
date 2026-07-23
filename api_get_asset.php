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

$stmt = $db->prepare("SELECT * FROM assets WHERE id = ?");
$stmt->execute([$id]);
$asset = $stmt->fetch();

if($asset) {
    if($role === "keeper") {
        // جمعدار فقط نام رو میتونه ببینه و ویرایش کنه
        echo json_encode(["id" => $asset["id"], "name" => $asset["name"]], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($asset, JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([]);
}
