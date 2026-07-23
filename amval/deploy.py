import os
import sys
import re
from ftplib import FTP

# ==========================================
# ۱. تنظیمات اتصال و مسیرها
# ==========================================
FTP_HOST = "ftp.amval10.ir"
FTP_PORT = 21
FTP_USER = "deploy@amval10.ir"
FTP_PASS = "Deploy@4#14"
REMOTE_DIR = "/"  # ریشه سایت (public_html)
LOCAL_DIR = r"C:\wamp64\www\amval"

# قوانین فیلترینگ آپلود
IGNORE_FOLDERS = {'config', '__pycache__', '.git', 'uploads', 'backups'}
IGNORE_EXTENSIONS = {'.py', '.bak', '.old', '.sql'}

# ==========================================
# ۲. کدهای نهایی فایل ASSETS.PHP (فرم یکپارچه)
# ==========================================
ASSETS_CODE = r"""<?php
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
                $db->prepare("UPDATE assets SET name=?, status=?, type=?, floor=?, location=?, recipient=?, center=?, date=?, description=? WHERE id=?")
                   ->execute([$name, $status, $type, $floor, $location, $recipient, $c, $date, $description, $_POST["asset_id"]]);
            } else {
                $db->prepare("UPDATE assets SET plate=?, name=?, status=?, type=?, floor=?, location=?, recipient=?, center=?, date=?, description=? WHERE id=?")
                   ->execute([$plate, $name, $status, $type, $floor, $location, $recipient, $c, $date, $description, $_POST["asset_id"]]);
            }
            $msg = "✅ اموال ویرایش شد";
        } else {
            $db->prepare("INSERT INTO assets (plate, name, status, type, floor, location, recipient, center, date, description, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$plate, $name, $status, $type, $floor, $location, $recipient, $c, $date, $description, $user_id]);
            $msg = "✅ اموال جدید ثبت شد";
        }
    } catch(Exception $e) { $msg = "❌ " . $e->getMessage(); $msgType = "error"; }
}
if(isset($_GET["delete"]) && isAdmin()) {
    $db->prepare("DELETE FROM assets WHERE id=?")->execute([$_GET["delete"]]);
    redirect("assets.php?center=" . ($center ?? ""));
}
$where = "1=1";
if($role === "keeper") { $where .= " AND (a.recipient LIKE '%$user_name%' OR a.created_by = $user_id)"; $center = null; }
if($center) $where .= " AND a.center = " . $db->quote($center);
$search = $_GET["search"] ?? "";
if ($search) {
    $sE = $db->quote("%" . $search . "%");
    $where .= " AND (a.name LIKE $sE OR a.plate LIKE $sE OR a.recipient LIKE $sE)";
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
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<link rel="stylesheet" href="css/app.css"><title>اموال</title>
<style>
.asset-card{background:#fff;border-radius:16px;padding:8px 12px !important;margin-bottom:6px !important;}
.asset-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px !important;}
.plate-badge, .asset-name, .meta-tag{background:#e2e8f0 !important;border-radius:5px !important;color:#000;padding:4px 8px !important;font-weight:bold !important;border:none !important;}
.asset-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.asset-meta{display:flex;flex-wrap:wrap;gap:3px !important;font-size:10px;margin-top:2px !important;}
</style>
<script>
function toEnglishNum(str){
    var p = ["۰","۱","۲","۳","۴","۵","۶","۷","۸","۹"], a = ["٠","١","٢","٣","٤","٥","٦","٧","٨","٩"];
    for(var i=0;i<10;i++){ str = str.replace(new RegExp(p[i],"g"), i); str = str.replace(new RegExp(a[i],"g"), i); }
    return str;
}
</script></head>
<body>
<header class="top-bar" style="display:flex; justify-content:space-between; align-items:center; gap:10px; padding:12px 16px !important; background:#f4f0e6 !important; position:sticky; top:0; z-index:100;">
    <div class="search-box" style="flex:1; margin:0; display:flex; align-items:center; gap:6px; background:#faf8f5 !important; border:1.5px solid #cbd5e1 !important; padding:6px 12px !important; border-radius:10px !important; height:34px !important;">
        <input type="text" placeholder="جستجو..." value="<?=htmlspecialchars($search)?>" onchange="location='?search='+toEnglishNum(this.value)" style="border:none; background:transparent; width:100%; outline:none; font-weight:bold;">
    </div>
    <button onclick="toggleAddMenu()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer;position:relative;">＋
        <div id="addMenu" style="display:none;position:absolute;top:40px;left:0;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);padding:6px;min-width:160px;z-index:200;">
            <a href="#" onclick="openModal();toggleAddMenu();" style="display:block;padding:10px;text-decoration:none;color:#000;font-size:12px;font-weight:600;">✏️ ورود دستی</a>
        </div>
    </button>
</header>
<div class="content">
    <?php if($msg): ?><div class="toast"><?=$msg?></div><?php endif; ?>
    <?php while($a = $assets_list->fetch()): ?>
    <div class="asset-card">
        <div class="asset-header">
            <div style="display:flex;align-items:center;gap:8px;flex:1;overflow:hidden">
                <span class="plate-badge"><?=$a["plate"]?></span>
                <div class="asset-name"><?=htmlspecialchars($a["name"])?></div>
            </div>
            <button onclick="editAsset(<?=$a["id"]?>)" style="background:none;border:none;cursor:pointer;">✏️</button>
        </div>
        <div class="asset-meta">
            <span class="meta-tag"><?=htmlspecialchars($a["recipient"])?></span>
            <span class="meta-tag"><?=htmlspecialchars(formatDate($a["date"]))?></span>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<div class="modal-overlay" id="assetModal"><div class="modal-sheet">
    <h3 id="modalTitle">ثبت اموال</h3>
    <form method="POST">
        <input type="hidden" name="asset_id" id="asset_id">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
            <input name="plate" id="plate" class="input-field" placeholder="شماره اموال *" required>
            <input name="name" id="name" class="input-field" placeholder="نام اموال *" required>
            <select name="status" class="input-field"><option>سالم</option><option>خراب</option></select>
            <input name="floor" class="input-field" placeholder="طبقه">
            <input name="location" class="input-field" placeholder="مکان">
            <input name="recipient" class="input-field" placeholder="جمعدار" value="<?=htmlspecialchars($_SESSION['fullname'])?>">
            <input name="date" class="input-field" value="<?=jalali_date()?>" style="grid-column:1/-1">
        </div>
        <div style="display:flex;gap:6px;"><button type="button" onclick="closeModal()" class="btn">انصراف</button><button name="save" class="btn btn-primary">ذخیره</button></div>
    </form>
</div></div>
<?php include "includes/bottom_nav.php"; ?>
<script>
const _isKeeper = <?= ($role === 'keeper') ? 'true' : 'false' ?>;
function openModal(){ document.getElementById('modalTitle').textContent='➕ ثبت جدید'; document.querySelector('form').reset(); document.getElementById('plate').readOnly=false; document.getElementById('assetModal').classList.add('show'); }
function closeModal(){ document.getElementById('assetModal').classList.remove('show'); }
async function editAsset(id){
    let r=await fetch('get_asset.php?id='+id), d=await r.json();
    document.getElementById('asset_id').value=d.id;
    document.getElementById('plate').value=d.plate;
    document.getElementById('name').value=d.name;
    if(_isKeeper) document.getElementById('plate').readOnly=true;
    document.getElementById('assetModal').classList.add('show');
}
function toggleAddMenu(){ var m=document.getElementById('addMenu'); m.style.display=m.style.display==='none'?'block':'none'; }
</script>
</body></html>
"""

# ==========================================
# ۳. کدهای نهایی فایل REPORT.PHP (فیلتر اکسلی)
# ==========================================
REPORT_CODE = r"""<?php
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
"""

# ==========================================
# ۴. اسکریپت انفجار کش (NUKE.PHP)
# ==========================================
NUKE_CODE = r"""<?php
if (function_exists('opcache_reset')) { opcache_reset(); }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><title>تخلیه کش</title></head>
<body style="text-align:center;padding:50px;font-family:tahoma;">
    <h2>🚀 کدهای جدید جایگزین شدند. در حال تخلیه کش مرورگر...</h2>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(regs => { for(let r of regs) r.unregister(); });
    }
    if(window.caches) { caches.keys().then(names => { for (let n of names) caches.delete(n); }); }
    setTimeout(() => { window.location.href = 'assets.php?v=' + Date.now(); }, 2000);
    </script>
</body></html>
"""

# ==========================================
# ۵. منطق اصلی دپلوی (FTP)
# ==========================================
def run_deploy():
    print("🚀 شروع فرآیند دپلوی کامل...")

    # ۱. اصلاح فایل‌های لوکال
    files_to_fix = {
        "assets.php": ASSETS_CODE,
        "report.php": REPORT_CODE,
        "nuke.php": NUKE_CODE
    }
    
    for filename, code in files_to_fix.items():
        path = os.path.join(LOCAL_DIR, filename)
        with open(path, "w", encoding="utf-8") as f:
            f.write(code)
        print(f"✅ فایل {filename} در لوکال بازنویسی شد.")

    # ۲. اتصال به FTP
    ftp = FTP()
    try:
        print(f"🔌 اتصال به {FTP_HOST}...")
        ftp.connect(FTP_HOST, FTP_PORT)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.cwd(REMOTE_DIR)
        print("🔓 ورود موفقیت‌آمیز.")

        # ۳. آپلود بازگشتی (Recursive Upload)
        for root, dirs, files in os.walk(LOCAL_DIR):
            # محاسبه مسیر نسبی برای ساخت در سرور
            rel_path = os.path.relpath(root, LOCAL_DIR).replace("\\", "/")
            
            # فیلتر پوشه‌های ممنوعه
            parts = rel_path.split("/")
            if any(p in IGNORE_FOLDERS for p in parts):
                continue
            
            # ساخت پوشه در سرور
            if rel_path != ".":
                try:
                    ftp.mkd(rel_path)
                except:
                    pass
            
            # آپلود فایل‌ها
            for file in files:
                if any(file.endswith(ext) for ext in IGNORE_EXTENSIONS):
                    continue
                
                local_file_path = os.path.join(root, file)
                remote_file_path = file if rel_path == "." else f"{rel_path}/{file}"
                
                print(f"📤 {remote_file_path}")
                with open(local_file_path, 'rb') as f_obj:
                    ftp.storbinary(f"STOR {remote_file_path}", f_obj)

        print("\n🎉 دپلوی با موفقیت تمام شد!")
        print("👉 مرحله آخر: لینک زیر را برای حذف کش باز کنید:")
        print("   http://amval10.ir/nuke.php")

    except Exception as e:
        print(f"❌ خطا در دپلوی: {e}")
    finally:
        ftp.quit()

if __name__ == "__main__":
    run_deploy()