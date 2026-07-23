<?php
// تبدیل اعداد فارسی
foreach($_GET as $k => $v) { $_GET[$k] = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], range(0,9), $v); }
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();
header("Content-Type: application/json; charset=utf-8");
$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_name = $_SESSION["fullname"];
if($role === "keeper"){
    $stmt = $db->prepare("SELECT id, plate, name, recipient, floor, location, center FROM assets WHERE (recipient LIKE ? OR created_by=?) AND status!=\"اسقاط\" ORDER BY plate ASC");
    $stmt->execute(["%".$user_name."%", $_SESSION["user_id"]]);
} else {
    $stmt = $db->query("SELECT id, plate, name, recipient, floor, location, center FROM assets WHERE status!=\"اسقاط\" ORDER BY plate ASC");
}
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
