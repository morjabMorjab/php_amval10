<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();
$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_name = $_SESSION["fullname"];
$user_id = $_SESSION["user_id"];

$filter_centers = (array)($_GET["center"] ?? []);
$filter_floors = (array)($_GET["floor"] ?? []);
$filter_plates = (array)($_GET["plate"] ?? []);
$filter_names = (array)($_GET["name"] ?? []);

$where = "1=1"; $params = [];
if($role === "keeper") { $where = "(recipient LIKE ? OR created_by = ?)"; $params = ["%$user_name%", $user_id]; }

if(!empty($filter_floors)){ 
    $p = implode(',', array_fill(0, count($filter_floors), '?')); 
    $where .= " AND floor IN ($p)"; $params = array_merge($params, $filter_floors); 
}

$stmt = $db->prepare("SELECT * FROM assets WHERE $where ORDER BY id DESC");
$stmt->execute($params);
$assets = $stmt->fetchAll();
$total = count($assets);

$unique_floors = $db->query("SELECT DISTINCT floor FROM assets WHERE floor != ''")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><link rel="stylesheet" href="css/app.css"><title>گزارش</title>
<style>
.t-header{background:#f8fafc; font-weight:bold; display:flex;}
.t-cell{flex:1; padding:10px; text-align:center; border:1px solid #ddd; cursor:pointer;}
.excel-dropdown{display:none; position:absolute; background:#fff; border:1px solid #ccc; padding:10px; z-index:1000; width:200px; box-shadow:0 4px 10px rgba(0,0,0,0.2);}
.active-filter{color:red !important;}
</style></head>
<body>
<header class="top-bar"><h1>📊 گزارش اموال</h1></header>
<div class="content">
    <div style="display:flex; gap:10px; margin-bottom:15px;">
        <div class="btn" style="flex:1; border:1px solid #555;">📦 <?=$total?> مورد</div>
        <a href="report.php" class="btn" style="flex:1; border:1px solid red; color:red;">✕ حذف فیلتر</a>
    </div>
    <div class="table-wrap" style="overflow-x:auto;">
        <div class="t-header">
            <div class="t-cell" onclick="toggleDrop('df')">طبقه ▼</div>
            <div class="t-cell">پلاک</div>
            <div class="t-cell">نام کالا</div>
        </div>
        <div id="df" class="excel-dropdown">
            <?php foreach($unique_floors as $f): ?>
            <label style="display:block;"><input type="checkbox" value="<?=$f?>" onchange="applyF('floor', this.value)"> <?=$f?></label>
            <?php endforeach; ?>
        </div>
        <?php foreach($assets as $a): ?>
        <div style="display:flex; border-bottom:1px solid #eee; padding:8px;">
            <div style="flex:1;text-align:center;"><?=$a['floor']?></div>
            <div style="flex:1;text-align:center;"><?=$a['plate']?></div>
            <div style="flex:1;text-align:center;"><?=$a['name']?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include "includes/bottom_nav.php"; ?>
<script>
function toggleDrop(id){ 
    let d = document.getElementById(id);
    d.style.display = d.style.display === 'block' ? 'none' : 'block';
}
function applyF(name, val){
    let u = new URL(window.location.href);
    u.searchParams.append(name+'[]', val);
    window.location.href = u.toString();
}
</script>
</body></html>
