import os
import sys
from ftplib import FTP

FTP_HOST = "ftp.amval10.ir"
FTP_PORT = 21
FTP_USER = "deploy@amval10.ir"
FTP_PASS = "Deploy@4#14"
LOCAL_DIR = r"C:\wamp64\www\amval"

# کدهای یکپارچه و واحد برای assets.php (بدون if/else برای فرم‌ها)
FINAL_ASSETS_CONTENT = r"""<?php
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
                // جمعدار نمیتواند پلاک را تغییر دهد، بقیه موارد ذخیره میشود
                $db->prepare("UPDATE assets SET name=?, status=?, type=?, floor=?, location=?, recipient=?, center=?, date=?, description=? WHERE id=?")
                   ->execute([$name, $status, $type, $floor, $location, $recipient, $c, $date, $description, $_POST["asset_id"]]);
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
.asset-card{background:#fff;border-radius:16px;padding:8px 12px !important;margin-bottom:6px !important;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.asset-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px !important;}
.plate-badge{background:#e2e8f0 !important;border-radius:5px !important;color:#000000 !important;padding:4px 8px !important;border:none !important;font-weight:bold !important; flex-shrink:0;}
.asset-name{margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#e2e8f0 !important;border-radius:5px !important;color:#000000 !important;padding:4px 8px !important;border:none !important;font-weight:bold !important}
.asset-meta{display:flex;flex-wrap:wrap;gap:3px !important;font-size:10px;color:#64748b;margin-top:2px !important;}
.meta-tag{background:#e2e8f0 !important;border-radius:5px !important;color:#000000 !important;padding:4px 8px !important;font-weight:bold !important;border:none !important;}
.center-grid{display:grid;grid-template-columns:repeat(3, 1fr);gap:8px;margin-bottom:16px;}
.center-btn{background:#fdfbf7 !important;border:1.5px solid #cbd5e1 !important;border-radius:10px !important;padding:6px 8px !important;display:flex;align-items:center;justify-content:space-between;gap:4px;text-decoration:none !important;height:44px !important;transition:all 0.2s ease !important;}
.center-btn:hover{background:#ffffff !important;border-color:#4f46e5 !important;transform:translateY(-1px);}
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
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:16px;">
<?php foreach($centers as $c): ?>
<a href="?center=<?=urlencode($c["center"])?>" class="center-btn" title="<?=htmlspecialchars($c["center"])?>">
<span style="font-weight:900 !important; font-size:11.5px !important; color:#1c1917 !important; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?=htmlspecialchars($c["center"])?></span>
<span style="background:#e9e4d9 !important; color:#57534e !important; padding:2px 6px !important; border-radius:6px !important; font-size:10px !important; font-weight:900 !important; flex-shrink:0; border:1px solid #d4cebe !important;"><?=number_format($c["total"])?></span>
</a>
<?php endforeach; ?>
</div>
<?php else: ?>

<?php while($a = $assets_list->fetch()): ?>
<div class="asset-card">
<div class="asset-header">
<div style="display:flex;align-items:center;gap:8px;flex:1;overflow:hidden">
<span class="plate-badge"><?=$a["plate"]?></span>
<div class="asset-name"><?=htmlspecialchars($a["name"])?></div>
</div>
<div style="display:flex;gap:4px;flex-shrink:0;margin-right:8px">
<?php if(isAdmin() || isKeeper()): ?><button type="button" onclick="editAsset(<?=$a["id"]?>)" style="background:#fef3c7;color:#92400e;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:10px">✏️</button><?php endif; ?>
<?php if(isAdmin()): ?><a href="?delete=<?=$a["id"]?>&center=<?=urlencode($center)?>" onclick="return confirm('آیا از حذف این مورد اطمینان دارید؟')" style="background:#fee2e2;color:#dc2626;padding:4px 10px;border-radius:6px;text-decoration:none;font-size:10px">🗑️</a><?php endif; ?>
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

<!-- فرم واحد و یکپارچه برای همه (ادمین و جمعدار) - بدون هیچ شرط if در HTML -->
<div class="modal-overlay" id="assetModal"><div class="modal-sheet">
    <h3 id="modalTitle">➕ ثبت اموال جدید</h3>
    <form method="POST">
        <input type="hidden" name="asset_id" id="asset_id">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
            <input name="plate" id="plate" class="input-field" placeholder="شماره اموال *" required>
            <input name="name" id="name" class="input-field" placeholder="نام اموال *" required>
            
            <select name="type" id="type" class="input-field">
                <option>ثابت</option><option>مصرفی</option><option>جاری</option>
            </select>
            <select name="status" id="status" class="input-field">
                <option>سالم</option><option>خراب</option><option>در تعمیر</option><option>اسقاط</option>
            </select>
            
            <input name="center" id="center" class="input-field" placeholder="مرکز" value="<?=htmlspecialchars($center ?? '')?>">
            <input name="floor" id="floor" class="input-field" placeholder="طبقه">
            
            <input name="location" id="location" class="input-field" placeholder="محل استقرار">
            <input name="recipient" id="recipient" class="input-field" placeholder="جمعدار" value="<?= $role === 'keeper' ? htmlspecialchars($_SESSION['fullname']) : '' ?>">
            
            <input name="date" id="date" class="input-field" value="<?=jalali_date()?>" style="grid-column:1/-1;">
            <input name="description" id="description" class="input-field" placeholder="توضیحات" style="grid-column:1/-1;">
        </div>

        <div style="display:flex;gap:6px;">
            <button type="button" onclick="closeModal()" class="btn btn-light" style="flex:1">انصراف</button>
            <button name="save" class="btn btn-primary" style="flex:1">💾 ذخیره</button>
        </div>
    </form>
</div></div>

<?php include "includes/bottom_nav.php"; ?>

<script>
// متغیر سراسری نقش کاربر برای کنترل رفتار فرم در جاوااسکریپت
const _isKeeper = <?= ($role === 'keeper') ? 'true' : 'false' ?>;

function openModal() {
    document.getElementById('modalTitle').textContent = '➕ ثبت اموال جدید';
    document.getElementById('asset_id').value = '';
    document.querySelector('form').reset();
    
    // در زمان ثبت جدید، پلاک همیشه باز است
    var p = document.getElementById('plate');
    if(p) {
        p.readOnly = false;
        p.style.pointerEvents = 'auto';
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
        
        document.getElementById('modalTitle').textContent = '✏️ ویرایش اموال';
        document.getElementById('asset_id').value = d.id;
        
        // قرار دادن مقادیر در فرم واحد
        document.getElementById('plate').value = d.plate || '';
        document.getElementById('name').value = d.name || '';
        document.getElementById('type').value = d.type || 'ثابت';
        document.getElementById('status').value = d.status || 'سالم';
        document.getElementById('floor').value = d.floor || '';
        document.getElementById('location').value = d.location || '';
        document.getElementById('recipient').value = d.recipient || '';
        document.getElementById('center').value = d.center || '';
        document.getElementById('date').value = d.date || '';
        document.getElementById('description').value = d.description || '';
        
        // اگر جمعدار باشد، در حالت ویرایش پلاک فقط‌خواندنی می‌شود
        var p = document.getElementById('plate');
        if (_isKeeper) {
            p.readOnly = true;
            p.style.pointerEvents = 'auto'; // اجازه کپی متن
        } else {
            p.readOnly = false;
            p.style.pointerEvents = 'auto';
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
</body></html>"""

# فایل بمب کش (نابودگر PWA و OPcache)
NUKE_PHP_CONTENT = """<?php
if (function_exists('opcache_reset')) { opcache_reset(); }
clearstatcache();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>در حال پاکسازی سیستم...</title>
<style>body{font-family:Tahoma;text-align:center;padding:50px;background:#f4f0e6;color:#1c1917;}</style>
</head>
<body>
    <h2>🚀 در حال نصب اجباری کدهای جدید روی مرورگر شما...</h2>
    <p>لطفاً چند ثانیه صبر کنید.</p>
    <script>
    // کشتن PWA
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            for(let registration of registrations) {
                registration.unregister();
            }
        });
    }
    // پاک کردن حافظه کش مرورگر
    if(window.caches) {
        caches.keys().then(function(names) {
            for (let name of names) caches.delete(name);
        });
    }
    // ریدایرکت با پارامتر تصادفی برای شکستن قطعی کش
    setTimeout(function(){
        window.location.href = 'assets.php?nocache=' + new Date().getTime();
    }, 2000);
    </script>
</body>
</html>"""

def deploy():
    print("🚀 شروع فرآیند دپلوی قطعی (دور زدن کش مرورگر و سرور)...")
    
    if not os.path.exists(LOCAL_DIR):
        print("❌ پوشه لوکال پیدا نشد.")
        return

    # ۱. بازنویسی قطعی لوکال
    assets_file = os.path.join(LOCAL_DIR, "assets.php")
    with open(assets_file, "w", encoding="utf-8") as f:
        f.write(FINAL_ASSETS_CONTENT)
        
    nuke_file = os.path.join(LOCAL_DIR, "nuke.php")
    with open(nuke_file, "w", encoding="utf-8") as f:
        f.write(NUKE_PHP_CONTENT)

    # ۲. آپلود FTP
    ftp = FTP()
    try:
        print("🔌 اتصال به سرور...")
        ftp.connect(FTP_HOST, FTP_PORT, timeout=30)
        ftp.login(FTP_USER, FTP_PASS)
        
        # آپلود در ریشه اصلی سایت (همان محلی که اکانت شما لندینگ می‌کند)
        print("📤 آپلود assets.php")
        with open(assets_file, 'rb') as f:
            ftp.storbinary("STOR assets.php", f)
            
        print("📤 آپلود nuke.php")
        with open(nuke_file, 'rb') as f:
            ftp.storbinary("STOR nuke.php", f)
            
        print("🎉 آپلود تمام شد!")
        
    except Exception as e:
        print(f"❌ خطا در FTP: {e}")
    finally:
        ftp.quit()
        if os.path.exists(nuke_file):
            os.remove(nuke_file)

    print("\n=============================================")
    print("🔥 مرحله طلایی نهایی:")
    print("برای اینکه مرورگر شما مجبور شود کدهای جدید را دانلود کند، لینک زیر را باز کنید:")
    print("👉  http://amval10.ir/nuke.php  👈")
    print("این صفحه کش مرورگر را پاک کرده و شما را به صفحه اموال هدایت می‌کند.")
    print("=============================================")

if __name__ == "__main__":
    deploy()