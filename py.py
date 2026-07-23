import os
import sys

# مسیرهای پیش‌فرض پروژه در ومپ‌سرور
WAMP64_BASE = r"C:\wamp64\www\amval"
WAMP_BASE = r"C:\wamp\www\amval"

# کدهای کامل و نهایی فایل assets.php مجهز به مدیریت پویای کادر ثبت جدید و ویرایش جمعدار
FINAL_ASSETS_DYNAMIC_CONTENT = r"""<?php
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
    $where .= " AND (a.recipient LIKE '%$user_name%' OR a.created_by = $user_id)";
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
    $centers = $db->query("SELECT DISTINCT center, COUNT(*) as total FROM assets WHERE center IS NOT NULL AND center != '' GROUP BY center ORDER BY center");
} else {
    $centers = $db->query("SELECT DISTINCT center, COUNT(*) as total FROM assets WHERE (recipient LIKE '%$user_name%' OR created_by=$user_id) AND center IS NOT NULL AND center != '' GROUP BY center ORDER BY center");
}
?>
<!DOCTYPE html>
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
<header class="top-bar" style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
    <?php if($center): ?>
    <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
        <a href="assets.php" style="font-size:20px;text-decoration:none">→</a>
        <h1 style="font-size:14px !important; margin:0; font-weight:800;"><?=htmlspecialchars($center)?></h1>
    </div>
    <?php endif; ?>
    
    <!-- فیلد جستجوی فشرده با بوردر باکس زیبا در هدر در کنار دکمه پلاس -->
    <div class="search-box" style="flex:1; margin:0; display:flex; align-items:center; gap:6px; background:#faf8f5 !important; border:1.5px solid #cbd5e1 !important; padding:6px 12px !important; border-radius:10px !important; box-shadow:none !important; height:34px !important;">
        <span class="search-icon" style="font-size:13px; color:#64748b;">🔍</span>
        <input type="text" placeholder="جستجو..." value="<?=htmlspecialchars($search)?>" onchange="var v=toEnglishNum(this.value); location='?<?=$center ? "center=".urlencode($center)."&" : ""?>search='+v" style="border:none !important; background:transparent !important; padding:0 !important; height:100% !important; font-size:12px !important; width:100% !important; color:#000000 !important; font-weight:bold !important; outline:none !important;">
    </div>

    <div style="display:flex; gap:8px; align-items:center; flex-shrink:0;">
        <?php if($center && isAdmin()): ?><a href="import_excel.php" style="background:#ecfdf5;color:#059669;padding:6px 12px;border-radius:8px;text-decoration:none;font-size:12px">📥 اکسل</a><?php endif; ?>
        <button onclick="toggleAddMenu()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer;font-weight:700;position:relative;">＋
            <div id="addMenu" style="display:none;position:absolute;top:40px;left:0;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);padding:6px;min-width:160px;z-index:200;white-space:nowrap;">
                <a href="#" onclick="openModal();toggleAddMenu();" style="display:flex;align-items:center;gap:8px;padding:10px 14px;text-decoration:none;color:#0f172a;border-radius:8px;font-size:12px;font-weight:600;">✏️ ورود دستی</a>
                <a href="import_excel.php" style="display:flex;align-items:center;gap:8px;padding:10px 14px;text-decoration:none;color:#0f172a;border-radius:8px;font-size:12px;font-weight:600;">📥 ورود از اکسل</a>
            </div>
        </button>
    </div>
</header>

<div class="content">
<?php if($msg): ?><div class="toast <?=$msgType=="error"?"toast-error":"toast-success"?>"><?=$msg?></div><?php endif; ?>

<?php if(!$center && $role === "admin"): ?>
<!-- دکمه‌های ۳تایی هم‌ردیف، باریک و شیک مراکز برای ادمین -->
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:16px;">
<?php foreach($centers as $c): ?>
<a href="?center=<?=urlencode($c["center"])?>" class="btn" style="background:#fdfbf7 !important; border:1.5px solid #cbd5e1 !important; border-radius:10px !important; padding:6px 8px !important; display:flex; align-items:center; justify-content:space-between; gap:4px; text-decoration:none !important; height:44px !important; box-shadow:none !important; margin:0 !important; width:100% !important;">
<span style="font-weight:900 !important; font-size:11.5px !important; color:#1c1917 !important; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?=htmlspecialchars($c["center"])?>"><?=htmlspecialchars($c["center"])?></span>
<span style="background:#e9e4d9 !important; color:#57534e !important; padding:2px 6px !important; border-radius:6px !important; font-size:10px !important; font-weight:900 !important; flex-shrink:0; border:1px solid #d4cebe !important;"><?=number_format($c["total"])?></span>
</a>
<?php endforeach; ?>
</div>

<?php else: ?>
<!-- کادر قدیمی جستجو که زیر هدر قرار داشت به طور کامل از این بخش حذف گردید -->

<?php while($a = $assets_list->fetch()): ?>
<div class="asset-card">
<div class="asset-header" style="display:flex;justify-content:space-between;align-items:center">
<div style="display:flex;align-items:center;gap:8px;flex:1;overflow:hidden">
<span class="plate-badge" style="flex-shrink:0;background:#e2e8f0 !important;border-radius:5px !important;color:#000000 !important;padding:4px 8px !important;border:none !important;font-weight:bold !important"><?=$a["plate"]?></span>
<div class="asset-name" style="margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#e2e8f0 !important;border-radius:5px !important;color:#000000 !important;padding:4px 8px !important;border:none !important;font-weight:bold !important"><?=htmlspecialchars($a["name"])?></div>
</div>
<div style="display:flex;gap:4px;flex-shrink:0;margin-right:8px">
<?php if(isAdmin() || isKeeper()): ?><button onclick="editAsset(<?=$a["id"]?>)" style="background:#fef3c7;color:#92400e;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:10px">✏️</button><?php endif; ?>
<?php if(isAdmin()): ?><a href="?delete=<?=$a["id"]?>&center=<?=urlencode($center)?>" onclick="event.preventDefault(); showConfirm('آیا از حذف این مورد اطمینان دارید؟', () => { window.location.href='?delete=<?=$a['id']?>&center=<?=urlencode($center)?>'; })" style="background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:10px">🗑️</a><?php endif; ?>
</div>
</div>
<div class="asset-meta">
<?php if($a["floor"]): ?><span class="meta-tag"><?=htmlspecialchars($a["floor"])?></span><?php endif; ?>
<?php if($a["location"]): ?><span class="meta-tag"><?=htmlspecialchars($a["location"])?></span><?php endif; ?>
<?php if($a["recipient"]): ?><span class="meta-tag"><?=htmlspecialchars($a["recipient"])?></span><?php endif; ?>
<?php if(!empty($a["date"])): ?><span class="meta-tag"><?=htmlspecialchars(formatDate($a["date"]))?></span><?php endif; ?>
</div>
</div>
<?php endwhile; ?>
<?php endif; ?>
</div>

<div class="modal-overlay" id="assetModal"><div class="modal-sheet">
    <h3 id="modalTitle">➕ ثبت اموال جدید</h3>
    <form method="POST">
    <input type="hidden" name="asset_id" id="asset_id">

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:6px;">
        <input name="plate" id="plate" class="input-field" placeholder="شماره اموال *" required>
        <input name="name" id="name" class="input-field" placeholder="نام اموال *" required>
    </div>

    <!-- فیلدهای تکمیلی که برای جمعدار در حالت ویرایش پنهان می‌شوند، اما در ساخت جدید کامل نشان داده می‌شوند -->
    <div id="extraFieldsContainer" style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
        <select name="type" id="type" class="input-field"><option>ثابت</option><option>مصرفی</option><option>جاری</option></select>
        <select name="status" id="status" class="input-field"><option>سالم</option><option>خراب</option><option>در تعمیر</option><option>اسقاط</option></select>
        <input name="center" id="center" class="input-field" placeholder="مرکز" value="<?=htmlspecialchars($center ?? '')?>">
        <input name="floor" id="floor" class="input-field" placeholder="طبقه">
        <input name="location" id="location" class="input-field" placeholder="محل استقرار">
        <input name="recipient" id="recipient" class="input-field" placeholder="جمعدار" value="<?= $role === 'keeper' ? htmlspecialchars($_SESSION['fullname']) : '' ?>">
        <input name="date" id="date" class="input-field" value="<?=jalali_date()?>">
        <input name="description" id="description" class="input-field" placeholder="توضیحات" style="grid-column:1/-1">
    </div>

    <div style="display:flex;gap:6px;margin-top:12px">
        <button name="save" class="btn btn-primary" style="flex:1">💾 ذخیره</button>
        <button type="button" onclick="closeModal()" class="btn btn-light" style="flex:1">انصراف</button>
    </div>
    </form></div></div>

<?php include "includes/bottom_nav.php"; ?>

<script>
// تعریف سراسری متغیر نقش جمعدار براساس سشن PHP پروژه شما
const _isKeeper = <?= ($role === 'keeper') ? 'true' : 'false' ?>;

function openModal() {
    if(document.getElementById('modalTitle')) document.getElementById('modalTitle').textContent = '➕ ثبت اموال جدید';
    if(document.getElementById('asset_id')) document.getElementById('asset_id').value = '';
    document.querySelector('form').reset();
    
    // در حالت ثبت اموال جدید، فیلد پلاک برای همه باز و قابل نوشتن است
    if(document.getElementById('plate')) {
        document.getElementById('plate').readOnly = false;
        document.getElementById('plate').style.pointerEvents = 'auto';
    }
    // در حالت ثبت جدید، تمام فیلدها برای جمعدار هم کامل باز هستند
    if(document.getElementById('extraFieldsContainer')) {
        document.getElementById('extraFieldsContainer').style.display = 'grid';
    }
    document.getElementById('assetModal').classList.add('show');
}

function closeModal() {
    document.getElementById('assetModal').classList.remove('show');
}

async function editAsset(id) {
    try {
        let r = await fetch('get_asset.php?id=' + id);
        let d = await r.json();
        if (!d || !d.id) {
            alert('خطا در یافتن اطلاعات کالا');
            return;
        }
        
        if(document.getElementById('modalTitle')) document.getElementById('modalTitle').textContent = '✏️ ویرایش اموال';
        if(document.getElementById('asset_id')) document.getElementById('asset_id').value = d.id;
        
        // لود کامل اطلاعات مشترک
        if(document.getElementById('plate')) document.getElementById('plate').value = d.plate || '';
        if(document.getElementById('name')) document.getElementById('name').value = d.name || '';
        if(document.getElementById('type')) document.getElementById('type').value = d.type || 'ثابت';
        if(document.getElementById('status')) document.getElementById('status').value = d.status || 'سالم';
        if(document.getElementById('floor')) document.getElementById('floor').value = d.floor || '';
        if(document.getElementById('location')) document.getElementById('location').value = d.location || '';
        if(document.getElementById('recipient')) document.getElementById('recipient').value = d.recipient || '';
        if(document.getElementById('center')) document.getElementById('center').value = d.center || '';
        if(document.getElementById('date')) document.getElementById('date').value = d.date || '';
        if(document.getElementById('description')) document.getElementById('description').value = d.description || '';
        
        // بررسی نقش کاربر به صورت کاملاً پویا و بدون خطای ران‌تایم در حالت ویرایش
        if (typeof _isKeeper === 'undefined' || !_isKeeper) {
            if(document.getElementById('plate')) {
                document.getElementById('plate').readOnly = false;
                document.getElementById('plate').style.pointerEvents = 'auto';
            }
            if(document.getElementById('extraFieldsContainer')) {
                document.getElementById('extraFieldsContainer').style.display = 'grid';
            }
        } else {
            if(document.getElementById('plate')) {
                document.getElementById('plate').value = d.plate || '';
                document.getElementById('plate').readOnly = true;
                document.getElementById('plate').style.pointerEvents = 'auto'; // فقط ریداونلی تعاملی
            }
            if(document.getElementById('extraFieldsContainer')) {
                document.getElementById('extraFieldsContainer').style.display = 'none'; // مخفی کردن فیلدهای فرعی برای جمعدار در حالت ویرایش
            }
        }
        
        document.getElementById('assetModal').classList.add('show');
    } catch (e) {
        alert('خطا در ارتباط با سرور و پردازش اطلاعات');
    }
}

document.getElementById('assetModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function toggleAddMenu() {
    var m = document.getElementById('addMenu');
    m.style.display = m.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('button')) {
        var m = document.getElementById('addMenu');
        if (m) m.style.display = 'none';
    }
});
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
        d.forEach(function(f){ html += `<a href="#" onclick="pickFloor('${f}')" style="display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px">${f}</a>`; });
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
        d.forEach(function(l){ html += `<a href="#" onclick="pickLocation('${l}')" style="display:block;padding:10px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px">${l}</a>`; });
        document.getElementById("locationPickerList").innerHTML = html || "<div style=\"padding:10px;color:#94a3b8\">موردی یافت نشد</div>";
    });
}
</script>
</body></html>"""

def get_project_dir():
    if os.path.exists(WAMP64_BASE):
        return WAMP64_BASE
    elif os.path.exists(WAMP_BASE):
        return WAMP_BASE
    else:
        print("❌ پوشه پروژه در مسیرهای پیش‌فرض ومپ‌سرور پیدا نشد.")
        user_input = input("لطفاً مسیر پوشه پروژه خود را به صورت دستی وارد کنید (مثال C:\\wamp64\\www\\amval): ")
        if os.path.exists(user_input):
            return user_input
        print("❌ مسیر نامعتبر است.")
        sys.exit(1)

def apply_dynamic_modal_fix():
    project_dir = get_project_dir()
    assets_file = os.path.join(project_dir, "assets.php")
    
    if os.path.exists(assets_file):
        try:
            # بازنویسی کامل فایل assets.php برای جداسازی کامل و داینامیک ثبت جدید و ویرایش جمعدار
            with open(assets_file, "w", encoding="utf-8") as f:
                f.write(FINAL_ASSETS_DYNAMIC_CONTENT)
            print("✅ فایل assets.php با موفقیت بازنویسی شد؛ اکنون جمعدار در ثبت جدید فرم کامل و در ویرایش فقط فیلد نام کالا را مشاهده می‌کند.")
        except Exception as e:
            print(f"❌ خطا در زمان ویرایش فایل assets.php: {e}")
    else:
        print("❌ فایل assets.php یافت نشد.")

if __name__ == "__main__":
    apply_dynamic_modal_fix()