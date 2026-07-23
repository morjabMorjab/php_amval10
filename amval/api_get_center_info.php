<?php
// تبدیل اعداد فارسی
foreach($_GET as $k => $v) { $_GET[$k] = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], range(0,9), $v); }
require_once "config/database.php";
header("Content-Type: application/json; charset=utf-8");
$db = getDB();
$asset_id = intval($_GET["asset_id"] ?? 0);
$asset = $db->prepare("SELECT center, floor, location FROM assets WHERE id = ?");
$asset->execute([$asset_id]);
$data = $asset->fetch();

if($data && $data["center"]) {
    $floors = $db->prepare("SELECT DISTINCT floor FROM assets WHERE center = ? AND floor IS NOT NULL AND floor != \"\" ORDER BY floor");
    $floors->execute([$data["center"]]);
    $locs = $db->prepare("SELECT DISTINCT location FROM assets WHERE center = ? AND location IS NOT NULL AND location != \"\" ORDER BY location");
    $locs->execute([$data["center"]]);
    echo json_encode([
        "floors" => $floors->fetchAll(PDO::FETCH_COLUMN),
        "locations" => $locs->fetchAll(PDO::FETCH_COLUMN),
        "center" => $data["center"],
        "current_floor" => $data["floor"],
        "current_location" => $data["location"]
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["floors" => [], "locations" => []]);
}
