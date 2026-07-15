<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();

$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_name = $_SESSION["fullname"];
$user_id = $_SESSION["user_id"];
$user_center_name = ""; if ($role !== "admin") { $u_stmt = $db->prepare("SELECT c.name FROM users u LEFT JOIN centers c ON u.center_id = c.id WHERE u.id = ?"); $u_stmt->execute([$user_id]); $user_center_name = $u_stmt->fetchColumn() ?: ""; if (!$user_center_name) { $uc_stmt = $db->prepare("SELECT center_name FROM user_centers WHERE user_id = ? LIMIT 1"); $uc_stmt->execute([$user_id]); $user_center_name = $uc_stmt->fetchColumn() ?: ""; } }
$user_center_name = ""; if ($role !== "admin") { $u_stmt = $db->prepare("SELECT c.name FROM users u LEFT JOIN centers c ON u.center_id = c.id WHERE u.id = ?"); $u_stmt->execute([$user_id]); $user_center_name = $u_stmt->fetchColumn() ?: ""; if (!$user_center_name) { $uc_stmt = $db->prepare("SELECT center_name FROM user_centers WHERE user_id = ? LIMIT 1"); $uc_stmt->execute([$user_id]); $user_center_name = $uc_stmt->fetchColumn() ?: ""; } }
$user_center_name = ""; if ($role !== "admin") { $u_stmt = $db->prepare("SELECT c.name FROM users u LEFT JOIN centers c ON u.center_id = c.id WHERE u.id = ?"); $u_stmt->execute([$user_id]); $user_center_name = $u_stmt->fetchColumn() ?: ""; if (!$user_center_name) { $uc_stmt = $db->prepare("SELECT center_name FROM user_centers WHERE user_id = ? LIMIT 1"); $uc_stmt->execute([$user_id]); $user_center_name = $uc_stmt->fetchColumn() ?: ""; } }

$filter_center = $_GET["center"] ?? "";
$filter_status = $_GET["status"] ?? "";
$filter_type   = $_GET["type"] ?? "";
$filter_floor  = $_GET["floor"] ?? "";
$filter_plate  = $_GET["plate"] ?? "";
$filter_name   = $_GET["name"] ?? "";
$filter_recipient = $_GET["recipient"] ?? "";
$filter_date_from = $_GET["date_from"] ?? "";
$filter_date_to   = $_GET["date_to"] ?? "";
$filter_location  = $_GET["location"] ?? "";
$export        = $_GET["export"] ?? "";

$where = []; $params = [];
if($role === "keeper") {
    $where[] = "(recipient LIKE ? OR created_by = ?)";
    $params[] = "%$user_name%"; $params[] = $user_id;
}
if($filter_center) { $where[] = "center = ?"; $params[] = $filter_center; }
if($filter_status) { $where[] = "status = ?"; $params[] = $filter_status; }
if($filter_type)   { $where[] = "type = ?"; $params[] = $filter_type; }
if($filter_floor)  { $where[] = "floor = ?"; $params[] = $filter_floor; }
if($filter_plate)  { $where[] = "plate LIKE ?"; $params[] = "%$filter_plate%"; }
if($filter_name)   { $where[] = "name LIKE ?"; $params[] = "%$filter_name%"; }
if($filter_recipient) { $where[] = "recipient LIKE ?"; $params[] = "%$filter_recipient%"; }
if($filter_date_from) { $where[] = "date >= ?"; $params[] = $filter_date_from; }
if($filter_date_to)   { $where[] = "date <= ?"; $params[] = $filter_date_to; }
if($filter_location)  { $where[] = "location LIKE ?"; $params[] = "%$filter_location%"; }

$whereSQL = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_ids"]) && $role === "admin") {
    $ids = array_map("intval", $_POST["delete_ids"]);
    if(count($ids) > 0) {
        $db->prepare("DELETE FROM assets WHERE id IN (" . implode(",", array_fill(0, count($ids), "?")) . ")")->execute($ids);
    }
    header("Location: report.php?" . $_SERVER["QUERY_STRING"]);
    exit;
}

$stmt = $db->prepare("SELECT * FROM assets $whereSQL ORDER BY center ASC, floor ASC, location ASC, name ASC");
$stmt->execute($params);
$assets = $stmt->fetchAll();
$total = count($assets);

if($export == "excel") {
    require_once "includes/SimpleXLSXGen.php";
    $rows = [["پلاک","نام اموال","مرکز","طبقه","محل استقرار"]];
    foreach($assets as $a) $rows[] = [$a["plate"], $a["name"], $a["center"], $a["floor"], $a["location"]];
    (new SimpleXLSXGen($rows, "گزارش"))->download("report.xlsx");
    exit;
}

$centerCond = "WHERE 1=1";
if ($role === "admin" && $filter_center) {
    $centerCond = "WHERE center = " . $db->quote($filter_center);
} elseif ($role !== "admin" && !empty($user_center_name)) {
    $centerCond = "WHERE center = " . $db->quote($user_center_name);
}
$centers = $db->query("SELECT DISTINCT center FROM assets WHERE center IS NOT NULL AND center != \"\" ORDER BY center")->fetchAll();
$statuses = $db->query("SELECT DISTINCT status FROM assets $centerCond ORDER BY status")->fetchAll();
$types = $db->query("SELECT DISTINCT type FROM assets $centerCond ORDER BY type")->fetchAll();
$floors = $db->query("SELECT DISTINCT floor FROM assets $centerCond AND floor IS NOT NULL AND floor != \"\" ORDER BY floor")->fetchAll();

?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>گزارش</title><link rel="stylesheet" href="css/app.css">
<style>
.filter-card{background:#fff;border-radius:16px;padding:14px;margin-bottom:10px;margin-top:8px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.filter-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.filter-grid *{box-sizing:border-box;max-width:100%}
.filter-grid input,.filter-grid select{padding:10px 10px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:11px;font-family:inherit;background:#fff;outline:none;width:100%;box-sizing:border-box;font-weight:600;color:#0f172a;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:left 10px center;padding-left:30px}
.filter-grid input:focus,.filter-grid select:focus{border-color:#4361ee;background:#fff;box-shadow:0 0 0 3px rgba(67,97,238,.08)}
option{padding:10px;font-size:12px;font-weight:600}
.action-bar{display:flex;gap:6px;margin:8px 0}
.btn-action{flex:1;padding:6px 8px;border-radius:10px;font-size:11px;font-weight:800;text-align:center;text-decoration:none;border:none;cursor:pointer}
.btn-excel{background:#ecfdf5;color:#059669}.btn-pdf{background:#fff;color:#0f172a;border:1px solid #e2e8f0}
.btn-reset{background:#fef2f2;color:#991b1b}
.summary-card{background:#4361ee;color:#fff;border-radius:12px;padding:8px 12px;margin:0;display:flex;justify-content:space-between;align-items:center;font-size:14px}
.table-wrap{background:#fff;border-radius:16px;overflow:hidden}
.t-header,.t-row{display:flex;border-bottom:1px solid #f1f5f9}
.t-header{background:#f8fafc;font-weight:900;font-size:10px;position:sticky;top:0;z-index:5}
.t-cell{padding:8px 5px;font-size:10px;text-align:center;flex:1;font-weight:600}
.floating-delete{display:none;position:fixed;bottom:100px;left:50%;transform:translateX(-50%);z-index:100;background:#ef4444;color:#fff;padding:12px 24px;border-radius:30px;font-size:13px;font-weight:700;border:none;cursor:pointer}
.floating-delete.show{display:block}
@keyframes toastIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}

.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:.25s}
.modal-overlay.show{opacity:1;visibility:visible}
.modal-sheet{background:#fff;border-radius:20px;width:calc(100% - 40px);max-width:400px;max-height:70vh;overflow-y:auto;padding:20px;transform:scale(.9);transition:transform .3s cubic-bezier(.34,1.56,.64,1);box-shadow:0 25px 80px rgba(0,0,0,.25);margin:16px}
.modal-overlay.show .modal-sheet{transform:scale(1)}
.modal-sheet h3{font-size:16px;font-weight:800;text-align:center;margin-bottom:16px}
.modal-sheet .input-field{width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:12px;background:#f8fafc}
.modal-sheet .btn{width:100%;padding:11px;border-radius:10px;border:none;font-size:13px;font-weight:700;cursor:pointer}
.modal-sheet .btn-light{background:#f1f5f9;color:#64748b}
</style>

<script>
function toEnglishNum(str){
    var persian = ["۰","۱","۲","۳","۴","۵","۶","۷","۸","۹"];
    var arabic = ["٠","١","٢","٣","٤","٥","٦","٧","٨","٩"];
    for(var i=0;i<10;i++){
        str = str.replace(new RegExp(persian[i],"g"), i);
        str = str.replace(new RegExp(arabic[i],"g"), i);
    }
    return str;
}
</script></head>
<body>
<header class="top-bar"><a href="index.php">←</a><h1>📊 گزارش</h1></header>
<div class="content">

<div class="filter-card"><h3>🔍 فیلترها</h3><form method="GET"><div class="filter-grid">
<?php
date_default_timezone_set('Asia/Tehran'); if($role === "admin" || $role === "viewer"): ?>
<input type="hidden" name="center" id="centerInput" value="<?=htmlspecialchars($filter_center)?>">
<div class="input-field" onclick="openModal('centerModal')" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;padding:10px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:11px;font-weight:600;background:#fff"><span id="centerText"><?=$filter_center ?: "🏢 همه مراکز"?></span><span style="color:#94a3b8">▼</span></div>
<input type="hidden" name="status" id="statusInput" value="<?=htmlspecialchars($filter_status)?>">
<div class="input-field" onclick="openModal('statusModal')" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;padding:10px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:11px;font-weight:600;background:#fff"><span id="statusText"><?=$filter_status ?: "📊 همه وضعیت‌ها"?></span><span style="color:#94a3b8">▼</span></div>
<input type="hidden" name="type" id="typeInput" value="<?=htmlspecialchars($filter_type)?>">
<div class="input-field" onclick="openModal('typeModal')" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;padding:10px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:11px;font-weight:600;background:#fff"><span id="typeText"><?=$filter_type ?: "📂 همه انواع"?></span><span style="color:#94a3b8">▼</span></div>
<?php
date_default_timezone_set('Asia/Tehran'); else: ?>
<select name="center" disabled><option value="">🏢 <?= htmlspecialchars($user_center_name ?: "مرکز شما") ?></option></select>
<?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
<input type="hidden" name="floor" id="floorInput" value="<?=htmlspecialchars($filter_floor)?>">
<div class="input-field" onclick="openFloorModal()" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;padding:10px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:11px;font-weight:600;background:#fff" id="floorButton">
<span id="floorText"><?=$filter_floor ?: "🏗️ همه طبقات"?></span><span style="color:#94a3b8">▼</span>
</div>
<input name="plate" onchange="this.form.submit()" placeholder="🔢 پلاک..." value="<?=htmlspecialchars($filter_plate)?>">
<input name="name" onchange="this.form.submit()" placeholder="📝 نام..." value="<?=htmlspecialchars($filter_name)?>">
<?php
date_default_timezone_set('Asia/Tehran'); if($role === "admin" || $role === "viewer"): ?>
<input name="recipient" onchange="this.form.submit()" placeholder="👤 جمعدار..." value="<?=htmlspecialchars($filter_recipient)?>">
<input name="date_from" onchange="this.form.submit()" placeholder="📅 از تاریخ..." value="<?=htmlspecialchars($filter_date_from)?>">
<input name="date_to" onchange="this.form.submit()" placeholder="📅 تا تاریخ..." value="<?=htmlspecialchars($filter_date_to)?>">
<?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
<input name="location" onchange="this.form.submit()" placeholder="📍 محل استقرار..." value="<?=htmlspecialchars($filter_location)?>">
</div><div class="action-bar"><a href="report.php" class="btn-action btn-reset">✕ حذف فیلتر</a></div></form></div>



<div style="display:flex;gap:8px;margin-bottom:10px;align-items:stretch">
<div class="summary-card" style="flex:1;margin:0"><div><?=number_format($total)?> مورد</div><div>📦</div></div>
<a href="?<?=http_build_query(array_merge($_GET,["export"=>"excel"]))?>" class="btn-action btn-excel" style="flex:1;display:flex;align-items:center;justify-content:center;margin:0">📥 Excel</a>
<a href="print_report.php?<?=http_build_query($_GET)?>" target="_blank" class="btn-action btn-pdf" style="flex:1;display:flex;align-items:center;justify-content:center;margin:0">🖨️ PDF</a>
</div>

<?php
date_default_timezone_set('Asia/Tehran'); if($total > 0): ?>
<div class="table-wrap" style="max-height:55vh;overflow-y:auto">
<div class="t-header">
<?php
date_default_timezone_set('Asia/Tehran'); if($role==="admin"):?><div class="t-cell" style="flex:0.3"><input type="checkbox" id="selectAll" onclick="toggleAll()" style="width:14px;height:14px;cursor:pointer"></div><?php
date_default_timezone_set('Asia/Tehran'); endif?>
<div class="t-cell">پلاک</div><div class="t-cell">نام</div><div class="t-cell">مرکز</div><div class="t-cell">طبقه</div><div class="t-cell">محل استقرار</div>
</div>
<form method="POST" id="delForm">
<?php
date_default_timezone_set('Asia/Tehran'); foreach($assets as $a):?>
<div class="t-row">
<?php
date_default_timezone_set('Asia/Tehran'); if($role==="admin"):?><div class="t-cell" style="flex:0.3"><input type="checkbox" name="delete_ids[]" value="<?=$a["id"]?>" class="del-check" style="width:14px;height:14px"></div><?php
date_default_timezone_set('Asia/Tehran'); endif?>
<div class="t-cell"><?=htmlspecialchars($a["plate"])?></div>
<div class="t-cell"><?=htmlspecialchars($a["name"])?></div>
<div class="t-cell"><?=htmlspecialchars($a["center"])?></div>
<div class="t-cell"><?=htmlspecialchars($a["floor"])?></div>
<div class="t-cell"><?=htmlspecialchars($a["location"])?></div>
</div>
<?php
date_default_timezone_set('Asia/Tehran'); endforeach?>
</form>
</div>
<?php
date_default_timezone_set('Asia/Tehran'); else: ?>
<div style="text-align:center;padding:60px;color:#94a3b8">📭 موردی یافت نشد</div>
<?php
date_default_timezone_set('Asia/Tehran'); endif?>
</div>

<?php
date_default_timezone_set('Asia/Tehran'); if($role==="admin"):?>
<button class="floating-delete" id="floatingDelete" onclick="submitDelete()">🗑️ حذف انتخاب‌شده‌ها</button>
<?php
date_default_timezone_set('Asia/Tehran'); endif?>

<?php
date_default_timezone_set('Asia/Tehran'); include "includes/bottom_nav.php"; ?>
<script>
function toggleAll(){var s=document.getElementById("selectAll");if(!s)return;var all=document.querySelectorAll(".del-check");for(var i=0;i<all.length;i++){all[i].checked=s.checked}updateCount()}
function updateCount(){var c=document.querySelectorAll(".del-check:checked").length;var b=document.getElementById("floatingDelete");if(b){if(c>0){b.classList.add("show");b.textContent="🗑️ حذف "+c+" مورد"}else{b.classList.remove("show")}}}
function submitDelete(){var cbs=document.querySelectorAll(".del-check:checked");if(cbs.length==0){showToast("هیچ موردی انتخاب نشده!", "error");return}if(!confirm("حذف "+cbs.length+" مورد؟"))return;var f=document.getElementById("delForm");cbs.forEach(function(cb){var i=document.createElement("input");i.type="hidden";i.name="delete_ids[]";i.value=cb.value;f.appendChild(i)});f.submit()}
document.addEventListener("change",function(e){if(e.target.classList.contains("del-check"))updateCount()});

function showToast(msg, type){
    type = type || "success";
    var colors = {success:{bg:"#ecfdf5",color:"#065f46",border:"#a7f3d0",icon:"✅"},error:{bg:"#fef2f2",color:"#991b1b",border:"#fecaca",icon:"❌"}};
    var c = colors[type] || colors.success;
    var d = document.createElement("div");
    d.style.cssText = "position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;background:"+c.bg+";color:"+c.color+";border:1px solid "+c.border+";padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,.15);max-width:90%;text-align:center";
    d.textContent = c.icon + " " + msg;
    document.body.appendChild(d);
    setTimeout(function(){d.remove()}, 3000);
}
</script>

<div class="modal-overlay" id="floorModal" onclick="closeFloorModal(event)">
<div class="modal-sheet" style="max-height:70vh;overflow-y:auto">
<h3>🏗️ انتخاب طبقه</h3>
<div class="input-group" style="margin-bottom:8px">
<input type="text" class="input-field" id="floorSearch" placeholder="🔍 جستجوی طبقه..." oninput="filterFloors()" style="margin-bottom:8px">
</div>
<div id="floorList" style="max-height:300px;overflow-y:auto">
<a href="#" onclick="selectFloor('')" style="display:block;padding:12px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-weight:600;font-size:13px;<?=!$filter_floor?"background:#eff6ff;color:#4361ee":""?>">🏗️ همه طبقات</a>
<?php
date_default_timezone_set('Asia/Tehran'); foreach($floors as $f):?>
<a href="#" onclick="selectFloor('<?=$f["floor"]?>')" style="display:block;padding:12px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-weight:600;font-size:13px;<?=$filter_floor==$f["floor"]?"background:#eff6ff;color:#4361ee":""?>"><?=$f["floor"]?></a>
<?php
date_default_timezone_set('Asia/Tehran'); endforeach?>
</div>
<button onclick="closeFloorModal()" class="btn btn-light" style="margin-top:8px">انصراف</button>
</div>
</div>

<script>
function openFloorModal(){document.getElementById("floorModal").classList.add("show")}
function closeFloorModal(e){if(!e || e.target.id==="floorModal"||!e){document.getElementById("floorModal").classList.remove("show")}}
function selectFloor(f){
    document.getElementById("floorInput").value = f;
    document.getElementById("floorText").textContent = f || "🏗️ همه طبقات";
    document.getElementById("floorModal").classList.remove("show");
    document.forms[0].submit();
}
function filterFloors(){
    var q = document.getElementById("floorSearch").value.toLowerCase();
    var links = document.querySelectorAll("#floorList a");
    links.forEach(function(l){
        l.style.display = l.textContent.toLowerCase().includes(q) ? "" : "none";
    });
}
</script>

<!-- مودال مراکز -->
<div class="modal-overlay" id="centerModal" onclick="closeModal(event,'centerModal')"><div class="modal-sheet">
<h3>🏢 انتخاب مرکز</h3>
<input type="text" class="input-field" placeholder="🔍 جستجو..." oninput="filterList('centerList',this.value)" style="margin-bottom:8px">
<div id="centerList" style="max-height:250px;overflow-y:auto">
<a href="#" onclick="selectOption('center','','🏢 همه مراکز')" style="display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px">🏢 همه مراکز</a>
<?php
date_default_timezone_set('Asia/Tehran'); foreach($centers as $c):?>
<a href="#" onclick="selectOption('center','<?=$c["center"]?>','<?=$c["center"]?>')" style="display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px"><?=$c["center"]?></a>
<?php
date_default_timezone_set('Asia/Tehran'); endforeach?>
</div>
<button onclick="document.getElementById('centerModal').classList.remove('show')" class="btn btn-light" style="margin-top:8px">انصراف</button>
</div></div>

<!-- مودال وضعیت -->
<div class="modal-overlay" id="statusModal" onclick="closeModal(event,'statusModal')"><div class="modal-sheet">
<h3>📊 انتخاب وضعیت</h3>
<div id="statusList" style="max-height:250px;overflow-y:auto">
<a href="#" onclick="selectOption('status','','📊 همه وضعیت‌ها')" style="display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px">📊 همه وضعیت‌ها</a>
<?php
date_default_timezone_set('Asia/Tehran'); foreach($statuses as $s):?>
<a href="#" onclick="selectOption('status','<?=$s["status"]?>','<?=$s["status"]?>')" style="display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px"><?=$s["status"]?></a>
<?php
date_default_timezone_set('Asia/Tehran'); endforeach?>
</div>
<button onclick="document.getElementById('statusModal').classList.remove('show')" class="btn btn-light" style="margin-top:8px">انصراف</button>
</div></div>

<!-- مودال انواع -->
<div class="modal-overlay" id="typeModal" onclick="closeModal(event,'typeModal')"><div class="modal-sheet">
<h3>📂 انتخاب نوع</h3>
<div id="typeList" style="max-height:250px;overflow-y:auto">
<a href="#" onclick="selectOption('type','','📂 همه انواع')" style="display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px">📂 همه انواع</a>
<?php
date_default_timezone_set('Asia/Tehran'); foreach($types as $t):?>
<a href="#" onclick="selectOption('type','<?=$t["type"]?>','<?=$t["type"]?>')" style="display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px"><?=$t["type"]?></a>
<?php
date_default_timezone_set('Asia/Tehran'); endforeach?>
</div>
<button onclick="document.getElementById('typeModal').classList.remove('show')" class="btn btn-light" style="margin-top:8px">انصراف</button>
</div></div>

<script>
function openModal(id){document.getElementById(id).classList.add("show")}
function closeModal(e,id){if(!e || e.target.classList.contains("modal-overlay")){document.getElementById(id).classList.remove("show")}}
function selectOption(type, value, text){
    document.getElementById(type+"Input").value = value;
    document.getElementById(type+"Text").textContent = text;
    document.querySelectorAll(".modal-overlay").forEach(function(m){m.classList.remove("show")});
    document.forms[0].submit();
}
function filterList(id, q){
    var links = document.querySelectorAll("#"+id+" a");
    links.forEach(function(l){l.style.display = l.textContent.toLowerCase().includes(q.toLowerCase()) ? "" : "none"});
}
</script>

</body></html>