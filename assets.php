<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();

$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["fullname"];
$msg = ""; $msgType = "success";

$center = $_GET["center"] ?? null;

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save"])) {
    $plate = $_POST["plate"] ?: "PLT-".jalali_date("ymd")."-".rand(1000,9999);
    $name = $_POST["name"];
    $status = $_POST["status"] ?: "سالم";
    $type = $_POST["type"] ?: "ثابت";
    $floor = $_POST["floor"] ?: "";
    $location = $_POST["location"] ?: "";
    $recipient = $_POST["recipient"] ?: "";
    $c = $_POST["center"] ?: "";
    $date = $_POST["date"] ?: jalali_date();
    $description = $_POST["description"] ?: "";

    try {
        if(isset($_POST["asset_id"]) && $_POST["asset_id"]) {
            if($role === "keeper") {
                // جمعدار فقط نام رو میتونه عوض کنه
                $db->prepare("UPDATE assets SET name=? WHERE id=?")->execute([$name, $_POST["asset_id"]]);
            } else {
                $db->prepare("UPDATE assets SET plate=?, name=?, status=?, type=?, floor=?, location=?, recipient=?, center=?, date=?, description=? WHERE id=?")
                   ->execute([$plate, $name, $status, $type, $floor, $location, $recipient, $c, $date, $description, $_POST["asset_id"]]);
            }
            $msg = "✅ اموال ویرایش شد";
            $db->prepare("INSERT INTO activity_logs (user_id, username, fullname, action, entity_type, entity_id, details) VALUES (?,?,?,?,?,?,?)")->execute([$user_id, $_SESSION["username"], $_SESSION["fullname"], "update", "asset", $_POST["asset_id"], "ویرایش اموال: $plate - $name"]);
        } else {
            $db->prepare("INSERT INTO assets (plate, name, status, type, floor, location, recipient, center, date, description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$plate, $name, $status, $type, $floor, $location, $recipient, $c, $date, $description, $user_id]);
            $msg = "✅ اموال جدید ثبت شد";
            $db->prepare("INSERT INTO activity_logs (user_id, username, fullname, action, entity_type, entity_id, details) VALUES (?,?,?,?,?,?,?)")->execute([$user_id, $_SESSION["username"], $_SESSION["fullname"], "create", "asset", $db->lastInsertId(), "ثبت اموال: $plate - $name"]);
        }
    } catch(Exception $e) {
        $msg = "❌ " . $e->getMessage();
        $msgType = "error";
    }
}

if(isset($_GET["delete"]) && isAdmin()) {
    $db->prepare("DELETE FROM assets WHERE id=?")->execute([$_GET["delete"]]);
    redirect("assets.php?center=" . ($center ?: ""));
}

$where = "1=1";
if($role === "keeper") {
    $where .= " AND (a.recipient LIKE \"%$user_name%\" OR a.created_by = $user_id)";
    $center = null;
}
if($center) $where .= " AND a.center = " . $db->quote($center);

$search = $_GET["search"] ?? "";
if ($search) {
    $searchEscaped = $db->quote("%" . $search . "%");
    $where .= " AND (a.name LIKE $searchEscaped OR a.plate LIKE $searchEscaped OR a.recipient LIKE $searchEscaped)";
}

$assets_list = $db->query("SELECT * FROM assets a WHERE $where ORDER BY a.created_at DESC LIMIT 100");

if($role === "admin") {
    $centers = $db->query("SELECT DISTINCT center, COUNT(*) as total FROM assets WHERE center IS NOT NULL AND center != \"\" GROUP BY center ORDER BY center");
} else {
    $centers = $db->query("SELECT DISTINCT center, COUNT(*) as total FROM assets WHERE (recipient LIKE \"%$user_name%\" OR created_by=$user_id) AND center IS NOT NULL AND center != \"\" GROUP BY center ORDER BY center");
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<meta name="theme-color" content="#0f172a">
<title>اموال | مدیریت اموال</title>
<link rel="stylesheet" href="css/app.css">
<style>
.asset-card{background:#fff;border-radius:16px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.asset-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px}
.plate-badge{background:#4361ee;color:#fff;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700}
.asset-name{font-weight:700;font-size:14px;color:#0f172a;margin-bottom:4px}
.asset-meta{display:flex;flex-wrap:wrap;gap:4px;font-size:10px;color:#64748b}
.meta-tag{background:#f8fafc;padding:2px 8px;border-radius:20px;display:flex;align-items:center;gap:3px;font-size:10px}
.center-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.center-card-item{background:#fff;border-radius:16px;padding:18px 14px;text-align:center;cursor:pointer;text-decoration:none;color:#0f172a;border:2px solid transparent;box-shadow:0 2px 8px rgba(0,0,0,.03)}
.center-icon-big{font-size:32px;display:block;margin-bottom:8px}
.center-name{font-weight:700;font-size:13px}.center-count{font-size:11px;color:#64748b;background:#f1f5f9;padding:2px 12px;border-radius:10px;display:inline-block}
.empty-state{text-align:center;padding:60px 20px;color:#94a3b8}
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
<header class="top-bar">
<?php
date_default_timezone_set('Asia/Tehran'); if($center): ?><a href="assets.php" style="font-size:20px;text-decoration:none">←</a><h1>📦 <?=htmlspecialchars($center ?? '')?></h1>
<?php
date_default_timezone_set('Asia/Tehran'); else: ?><h1>🏢 اموال</h1><?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
<div style="display:flex;gap:8px">
<?php
date_default_timezone_set('Asia/Tehran'); if($center && isAdmin()): ?><a href="import_excel.php" style="background:#ecfdf5;color:#059669;padding:6px 12px;border-radius:8px;text-decoration:none;font-size:12px">📥 اکسل</a><?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
<button onclick="toggleAddMenu()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer;font-weight:700;position:relative;">＋
        <div id="addMenu" style="display:none;position:absolute;top:40px;left:0;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);padding:6px;min-width:160px;z-index:200;white-space:nowrap;">
            <a href="#" onclick="openModal();toggleAddMenu();" style="display:flex;align-items:center;gap:8px;padding:10px 14px;text-decoration:none;color:#0f172a;border-radius:8px;font-size:12px;font-weight:600;">✏️ ورود دستی</a>
            <a href="import_excel.php" style="display:flex;align-items:center;gap:8px;padding:10px 14px;text-decoration:none;color:#0f172a;border-radius:8px;font-size:12px;font-weight:600;">📥 ورود از اکسل</a>
        </div>
    </button>
</div>
</header>

<div class="content">
<?php
date_default_timezone_set('Asia/Tehran'); if($msg): ?><div class="toast <?=$msgType=="error"?"toast-error":"toast-success"?>"><?=$msg?></div><?php
date_default_timezone_set('Asia/Tehran'); endif; ?>

<?php
date_default_timezone_set('Asia/Tehran'); if(!$center && $role === "admin"): ?>
<div class="center-grid">
<?php
date_default_timezone_set('Asia/Tehran'); foreach($centers as $c): ?>
<a href="?center=<?=urlencode($c["center"])?>" class="center-card-item">
<span class="center-icon-big">🏢</span><div class="center-name"><?=htmlspecialchars($c["center"])?></div><span class="center-count"><?=$c["total"]?> مال</span>
</a>
<?php
date_default_timezone_set('Asia/Tehran'); endforeach; ?>
</div>

<?php
date_default_timezone_set('Asia/Tehran'); else: ?>
<div class="search-box" style="margin-bottom:12px;position:sticky;top:52px;z-index:40;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.05);padding:10px 14px;border-radius:12px;transform:translateZ(0);-webkit-transform:translateZ(0)">
<span class="search-icon">🔍</span>
<input type="text" placeholder="جستجو..." value="<?=htmlspecialchars($search)?>" onchange="var v=toEnglishNum(this.value); location='?<?=$center ? "center=".urlencode($center)."&" : ""?>search='+this.value">
</div>

<?php
date_default_timezone_set('Asia/Tehran'); while($a = $assets_list->fetch()): ?>
<div class="asset-card">
<div class="asset-header">
<span class="plate-badge">🔢 <?=$a["plate"]?></span>
<div style="display:flex;gap:4px">
<?php
date_default_timezone_set('Asia/Tehran'); if(isAdmin() || isKeeper()): ?><button onclick="editAsset(<?=$a["id"]?>)" style="background:#fef3c7;color:#92400e;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:10px">✏️</button><?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
<?php
date_default_timezone_set('Asia/Tehran'); if(isAdmin()): ?><a href="?delete=<?=$a["id"]?>&center=<?=urlencode($center)?>" onclick="event.preventDefault(); showConfirm('آیا از حذف این مورد اطمینان دارید؟', () => { window.location.href='?delete=<?=$a['id']?>&center=<?=urlencode($center)?>'; })" style="background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:10px">🗑️</a><?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
</div>
</div>
<div class="asset-name"><?=htmlspecialchars($a["name"])?></div>
<div class="asset-meta">
<?php
date_default_timezone_set('Asia/Tehran'); if($a["floor"]): ?><span class="meta-tag">🏗️ <?=htmlspecialchars($a["floor"])?></span><?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
<?php
date_default_timezone_set('Asia/Tehran'); if($a["location"]): ?><span class="meta-tag">📍 <?=htmlspecialchars($a["location"])?></span><?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
<?php
date_default_timezone_set('Asia/Tehran'); if($a["recipient"]): ?><span class="meta-tag">👤 <?=htmlspecialchars($a["recipient"])?></span><?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
</div>
</div>
<?php
date_default_timezone_set('Asia/Tehran'); endwhile; ?>
<?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
</div>

<div class="modal-overlay" id="assetModal"><div class="modal-sheet">
<h3 id="modalTitle">➕ ثبت اموال جدید</h3>
<form method="POST">
<input type="hidden" name="asset_id" id="asset_id">

<?php
date_default_timezone_set('Asia/Tehran'); if(isAdmin()): ?>
<!-- مودال کامل برای ادمین -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
<input name="plate" id="plate" class="input-field" placeholder="شماره اموال *" required>
<select name="type" id="type" class="input-field"><option>ثابت</option><option>مصرفی</option><option>جاری</option></select>
<input name="name" id="name" class="input-field" placeholder="نام اموال *" required style="grid-column:1/-1">
<select name="status" id="status" class="input-field"><option>سالم</option><option>خراب</option><option>در تعمیر</option><option>اسقاط</option></select>
<input name="center" id="center" class="input-field" placeholder="مرکز" value="<?=htmlspecialchars($center ?? '')?>">
<input name="floor" id="floor" class="input-field" placeholder="طبقه">
<input name="location" id="location" class="input-field" placeholder="محل استقرار">
<input name="recipient" id="recipient" class="input-field" placeholder="جمعدار">
<input name="date" id="date" class="input-field" value="<?=jalali_date()?>">
<input name="description" id="description" class="input-field" placeholder="توضیحات" style="grid-column:1/-1">
</div>
<?php
date_default_timezone_set('Asia/Tehran'); else: ?>
<!-- مودال ساده برای جمعدار -->
<input type="hidden" name="type" value="ثابت">
<input type="hidden" name="status" value="سالم">
<input type="hidden" name="recipient" value="<?=$_SESSION["fullname"]?>">
<input type="hidden" name="date" value="<?=jalali_date()?>">
<input type="hidden" name="description" value="">

<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
<input name="plate" id="plate" class="input-field" placeholder="شماره اموال *" required>
<input name="name" id="name" class="input-field" placeholder="نام اموال *" required>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px">
<input type="hidden" name="floor" id="floor_keeper">
<div class="input-field" onclick="openFloorPicker()" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between"><span id="floorText">🏗️ طبقه</span><span style="color:#94a3b8">▼</span></div>
<input type="hidden" name="location" id="location_keeper">
<div class="input-field" onclick="openLocationPicker()" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between"><span id="locationText">📍 محل استقرار</span><span style="color:#94a3b8">▼</span></div>
</div>
<input type="hidden" name="center" id="center_keeper" value="<?=htmlspecialchars($center ?? '')?>">
<?php
date_default_timezone_set('Asia/Tehran'); endif; ?>

<div style="display:flex;gap:6px;margin-top:8px">
<button name="save" class="btn btn-primary" style="flex:1">💾 ذخیره</button>
<button type="button" onclick="closeModal()" class="btn btn-light" style="flex:1">انصراف</button>
</div>
</form></div></div>

<?php
date_default_timezone_set('Asia/Tehran'); include "includes/bottom_nav.php"; ?>






<script>
var _isKeeper = <?= ($role === "keeper") ? "true" : "false" ?>;

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

function showConfirm(msg, callback){
    var o = document.createElement("div");
    o.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center";
    var b = document.createElement("div");
    b.style.cssText = "background:#fff;border-radius:20px;padding:24px;width:90%;max-width:340px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2)";
    b.innerHTML = "<div style=\"font-size:40px;margin-bottom:12px\">⚠️</div><p style=\"font-size:14px;font-weight:600;color:#0f172a;margin-bottom:20px\">"+msg+"</p><div style=\"display:flex;gap:8px\"><button id=\"cfmYes\" style=\"flex:1;padding:12px;border-radius:12px;border:none;background:#ef4444;color:#fff;font-weight:700;cursor:pointer;font-size:13px\">🗑️ حذف</button><button id=\"cfmNo\" style=\"flex:1;padding:12px;border-radius:12px;border:none;background:#f1f5f9;color:#64748b;font-weight:700;cursor:pointer;font-size:13px\">انصراف</button></div>";
    o.appendChild(b);
    document.body.appendChild(o);
    document.getElementById("cfmYes").onclick = function(){o.remove();if(callback)callback()};
    document.getElementById("cfmNo").onclick = function(){o.remove()};
    o.onclick = function(e){if(e.target===o)o.remove()};
}

function openModal(){document.getElementById("modalTitle").textContent="➕ ثبت اموال جدید";document.getElementById("asset_id").value="";document.querySelector("form").reset();document.getElementById("assetModal").classList.add("show")}
function closeModal(){document.getElementById("assetModal").classList.remove("show")}

async function editAsset(id){
    try {
        var r = await fetch("get_asset.php?id=" + id);
        var d = await r.json();
        if(!d || !d.id){ showToast("خطا", "error"); return; }
        document.getElementById("modalTitle").textContent = "✏️ ویرایش اموال";
        document.getElementById("asset_id").value = d.id;
        if(!_isKeeper){
            document.getElementById("plate").value = d.plate || "";
            document.getElementById("name").value = d.name || "";
            document.getElementById("type").value = d.type || "ثابت";
            document.getElementById("status").value = d.status || "سالم";
            document.getElementById("floor").value = d.floor || "";
            document.getElementById("location").value = d.location || "";
            document.getElementById("recipient").value = d.recipient || "";
            document.getElementById("center").value = d.center || "";
            document.getElementById("date").value = d.date || "";
            document.getElementById("description").value = d.description || "";
        } else {
            document.getElementById("plate").value = d.plate || "";
            document.getElementById("name").value = d.name || "";
            document.getElementById("floor_hidden").value = d.floor || "";
            document.getElementById("location_hidden").value = d.location || "";
            document.getElementById("recipient_hidden").value = d.recipient || "";
            document.getElementById("description_hidden").value = d.description || "";
        }
        document.getElementById("floor").value = d.floor || "";
        document.getElementById("location").value = d.location || "";
        document.getElementById("center").value = d.center || "";
        document.getElementById("assetModal").classList.add("show");
    } catch(e) { showToast("خطا", "error"); }
}

document.getElementById("assetModal").addEventListener("click", function(e){ if(e.target === this) closeModal(); });

function toggleAddMenu(){var m=document.getElementById("addMenu");m.style.display=m.style.display==="none"?"block":"none"}
document.addEventListener("click",function(e){if(!e.target.closest("button")){var m=document.getElementById("addMenu");if(m)m.style.display="none"}});
</script>

<div class="modal-overlay" id="floorPickerModal" onclick="closeFloorPicker(event)"><div class="modal-sheet">
<h3>🏗️ انتخاب طبقه</h3>
<div id="floorPickerList" style="max-height:250px;overflow-y:auto"></div>
<button onclick="document.getElementById('floorPickerModal').classList.remove('show')" class="btn btn-light" style="margin-top:8px">انصراف</button>
</div></div>

<div class="modal-overlay" id="locationPickerModal" onclick="closeLocationPicker(event)"><div class="modal-sheet">
<h3>📍 انتخاب محل استقرار</h3>
<div id="locationPickerList" style="max-height:250px;overflow-y:auto"></div>
<button onclick="document.getElementById('locationPickerModal').classList.remove('show')" class="btn btn-light" style="margin-top:8px">انصراف</button>
</div></div>

<script>
function openFloorPicker(){
    document.getElementById("floorPickerModal").classList.add("show");
    var center = document.getElementById("center_keeper") ? document.getElementById("center_keeper").value : "";
    if(!center || center === "") {
        // center رو از assets خود کاربر پیدا کن
        fetch("api_get_my_center.php")
        .then(r => r.json())
        .then(c => {
            if(c && c.center) {
                document.getElementById("center_keeper").value = c.center;
                loadFloors(c.center);
            }
        });
        return;
    }
    loadFloors(center);
}
function loadFloors(center){
    fetch("api_get_floors.php?center=" + encodeURIComponent(center))
    .then(r => r.json())
    .then(d => {
        var html = "";
        d.forEach(function(f){ html += "<a href=\"#\" onclick=\"pickFloor('"+f+"')\" style=\"display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px\">"+f+"</a>"; });
        document.getElementById("floorPickerList").innerHTML = html || "<div style=\"padding:10px;color:#94a3b8\">موردی یافت نشد</div>";
    });
}
function pickFloor(f){
    document.getElementById("floor_keeper").value = f;
    document.getElementById("floorText").textContent = f;
    document.getElementById("floorPickerModal").classList.remove("show");
}
function closeFloorPicker(e){if(!e||e.target.id==="floorPickerModal")document.getElementById("floorPickerModal").classList.remove("show")}

function openLocationPicker(){
    document.getElementById("locationPickerModal").classList.add("show");
    var center = document.getElementById("center_keeper") ? document.getElementById("center_keeper").value : "";
    if(!center || center === "") {
        fetch("api_get_my_center.php")
        .then(r => r.json())
        .then(c => {
            if(c && c.center) {
                document.getElementById("center_keeper").value = c.center;
                loadLocations(c.center);
            }
        });
        return;
    }
    loadLocations(center);
}
function loadLocations(center){
    fetch("api_get_locations.php?center=" + encodeURIComponent(center))
    .then(r => r.json())
    .then(d => {
        var html = "";
        d.forEach(function(l){ html += "<a href=\"#\" onclick=\"pickLocation('"+l+"')\" style=\"display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px\">"+l+"</a>"; });
        document.getElementById("locationPickerList").innerHTML = html || "<div style=\"padding:10px;color:#94a3b8\">موردی یافت نشد</div>";
    });
}
function loadFloors(center){
    fetch("api_get_floors.php?center=" + encodeURIComponent(center))
    .then(r => r.json())
    .then(d => {
        var html = "";
        d.forEach(function(f){ html += "<a href=\"#\" onclick=\"pickFloor('"+f+"')\" style=\"display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px\">"+f+"</a>"; });
        document.getElementById("floorPickerList").innerHTML = html || "<div style=\"padding:10px;color:#94a3b8\">موردی یافت نشد</div>";
    });
}
function pickLocation(l){
    document.getElementById("location_keeper").value = l;
    document.getElementById("locationText").textContent = l;
    document.getElementById("locationPickerModal").classList.remove("show");
}
function closeLocationPicker(e){if(!e||e.target.id==="locationPickerModal")document.getElementById("locationPickerModal").classList.remove("show")}
</script>
</body></html>