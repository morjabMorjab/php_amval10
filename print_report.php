<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();

$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_name = $_SESSION["fullname"];
$user_id = $_SESSION["user_id"];

$filter_center = $_GET["center"] ?? "";
$filter_status = $_GET["status"] ?? "";
$filter_type   = $_GET["type"] ?? "";
$filter_floor  = $_GET["floor"] ?? "";
$filter_plate  = $_GET["plate"] ?? "";
$filter_name   = $_GET["name"] ?? "";

$where = []; $params = [];
if($role === "keeper") { $where[] = "(recipient LIKE ? OR created_by = ?)"; $params[] = "%$user_name%"; $params[] = $user_id; }
if($filter_center) { $where[] = "center = ?"; $params[] = $filter_center; }
if($filter_status) { $where[] = "status = ?"; $params[] = $filter_status; }
if($filter_type)   { $where[] = "type = ?"; $params[] = $filter_type; }
if($filter_floor)  { $where[] = "floor = ?"; $params[] = $filter_floor; }
if($filter_plate)  { $where[] = "plate LIKE ?"; $params[] = "%$filter_plate%"; }
if($filter_name)   { $where[] = "name LIKE ?"; $params[] = "%$filter_name%"; }

$whereSQL = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$stmt = $db->prepare("SELECT * FROM assets $whereSQL ORDER BY center ASC, floor ASC, location ASC, name ASC");
$stmt->execute($params);
$assets = $stmt->fetchAll();
$total = count($assets);

// عنوان مرکز
$title_center = $filter_center ?: ($role === "keeper" ? ($assets[0]["center"] ?? "") : "");

?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>گزارش اموال</title>
<style>
@page { size: A4; margin: 18mm 15mm 18mm 15mm; }
body { font-family: Tahoma, sans-serif; direction: rtl; font-size: 10px; color: #000; margin: 0; padding: 10px; }
.print-header { text-align: center; margin-bottom: 8px; }
.print-header h1 { font-size: 14px; margin: 0 0 4px 0; }
.print-header p { font-size: 9px; color: #555; margin: 0; }
table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
th { background: #e8e8e8; font-weight: bold; padding: 4px 3px; border: 1px solid #000; font-size: 9px; text-align: center; }
td { padding: 3px; border: 1px solid #000; font-size: 8px; text-align: center; }
.print-footer { text-align: center; margin-top: 6px; font-size: 8px; color: #666; border-top: 1px solid #999; padding-top: 4px; }
@media screen {
    body { padding: 15px; background: #e2e8f0; }
    .page { background: #fff; padding: 15px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
    .btn { padding: 7px 14px; background: #4361ee; color: #fff; text-decoration: none; border-radius: 5px; font-size: 11px; border: none; cursor: pointer; margin-right: 5px; }
}
@media print { body { background: #fff; } .page { box-shadow: none; padding: 0; max-width: 100%; } .no-print { display: none; } }
</style>
</head>
<body onload="window.print()">

<div class="no-print" style="margin-bottom:10px;">
    <button onclick="window.print()" class="btn">🖨️ پرینت</button>
    <a href="report.php" class="btn" style="background:#666;">→ بازگشت</a>
</div>

<div class="page">
<div class="print-header">
    <h1>گزارش اموال</h1>
    <p>
        تاریخ: <?=jalali_date()?> | تعداد: <?=number_format($total)?> مورد
        <?php if($title_center):?> | مرکز: <?=$title_center?><?php endif; ?>
        <?php if($filter_floor):?> | طبقه: <?=$filter_floor?><?php endif; ?>
        <?php if($filter_status):?> | وضعیت: <?=$filter_status?><?php endif; ?>
        <?php if($filter_type):?> | نوع: <?=$filter_type?><?php endif; ?>
    </p>
</div>

<table>
<thead><tr><th>پلاک</th><th>نام اموال</th><th>مرکز</th><th>طبقه</th><th>محل استقرار</th></tr></thead>
<tbody>
<?php foreach($assets as $a): ?>
<tr><td><?=$a["plate"]?></td><td><?=htmlspecialchars($a["name"])?></td><td><?=$a["center"]?></td><td><?=$a["floor"]?></td><td><?=$a["location"]?></td></tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="print-footer">گزارش اموال - <?=jalali_date()?></div>
</div>

</body></html>