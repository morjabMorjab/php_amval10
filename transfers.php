<?php
date_default_timezone_set('Asia/Tehran');
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();

$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["fullname"];
$msg = ""; $msgType = "success";

// --- پردازش فرم جابجایی (ثبت گروهی) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_transfer"])) {
    $transfer_type = $_POST["transfer_type"];
    $transfer_date = $_POST["transfer_date"] ?: jalali_date();
    $reason = trim($_POST["reason"] ?? "");
    $asset_ids = !empty($_POST["asset_ids"]) ? array_map("intval", explode(",", $_POST["asset_ids"])) : [intval($_POST["asset_id"])];

    try {
        $db->beginTransaction();
        $maxNum = $db->query("SELECT MAX(CAST(transfer_code AS UNSIGNED)) FROM transfers")->fetchColumn();
        $common_code = str_pad(($maxNum ? $maxNum + 1 : 100), 4, "0", STR_PAD_LEFT);

        foreach ($asset_ids as $aid) {
            if ($aid <= 0) continue;
            $stmt = $db->prepare("SELECT * FROM assets WHERE id = ?");
            $stmt->execute([$aid]);
            $asset = $stmt->fetch();
            if (!$asset) continue;

            $old_place = ($asset["center"] ?? "") . " (" . ($asset["floor"] ?: "—") . " / " . ($asset["location"] ?: "—") . ")";
            
            // برای انتقال قطعی، مبدا جابجایی فقط نام مرکز باشد بدون طبقه و محل استقرار
            $from_place = ($transfer_type == "permanent") ? ($asset["center"] ?? "") : $old_place;

            if ($transfer_type == "internal") {
                $to_flr = trim($_POST["to_floor"] ?? "");
                $to_loc = trim($_POST["to_location"] ?? "");
                $new_place = ($asset["center"] ?? "") . " (" . ($to_flr ?: "—") . " / " . ($to_loc ?: "—") . ")";
                $db->prepare("INSERT INTO transfers (transfer_code, asset_id, transfer_type, transfer_date, reason, from_center, to_center, transferred_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$common_code, $aid, "internal", $transfer_date, $reason, $from_place, $new_place, $user_id]);
                $db->prepare("UPDATE assets SET floor = ?, location = ? WHERE id = ?")->execute([$to_flr, $to_loc, $aid]);
            } else {
                $to_dest = ($transfer_type == "permanent") ? trim($_POST["to_center"] ?? "") : (($transfer_type == "scrap") ? "واحد اسقاط" : "خارج از مرکز");
                $db->prepare("INSERT INTO transfers (transfer_code, asset_id, transfer_type, transfer_date, reason, from_center, to_center, transferred_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$common_code, $aid, $transfer_type, $transfer_date, $reason, $from_place, $to_dest, $user_id]);
                if($transfer_type == "permanent") $db->prepare("UPDATE assets SET center=?, floor='', location='' WHERE id=?")->execute([$to_dest, $aid]);
                if($transfer_type == "scrap") $db->prepare("UPDATE assets SET status='اسقاط' WHERE id=?")->execute([$aid]);
                if($transfer_type == "repair") $db->prepare("UPDATE assets SET status='در تعمیر' WHERE id=?")->execute([$aid]);
            }
        }
        $db->commit();
        $_SESSION["last_transfer_code"] = $common_code;
        $msg = "✅ جابجایی با موفقیت ثبت شد";
    } catch (Exception $e) { $db->rollBack(); $msg = "❌ خطا: " . $e->getMessage(); $msgType = "error"; }
}

if(isset($_GET["delete_transfer"]) && isAdmin()) {
    $db->exec("DELETE FROM transfers WHERE id = " . intval($_GET["delete_transfer"]));
    header("Location: transfers.php"); exit;
}

// --- واکشی مراکز مجاز جمعدار (Keeper) براساس لاگین و دسترسی‌ها ---
$user_allowed_centers = [];
if ($role === "keeper") {
    // ۱. مرکز متصل به اکانت کاربر
    $c_stmt = $db->prepare("SELECT name FROM centers WHERE id = ? AND is_active = 1");
    $c_stmt->execute([$_SESSION['center_id'] ?? 0]);
    $c_name = $c_stmt->fetchColumn();
    if ($c_name) {
        $user_allowed_centers[] = $c_name;
    }
    
    // ۲. مراکز اضافی مجاز کاربر از جدول دسترسی چندمرکزی user_centers
    $uc_stmt = $db->prepare("SELECT center_name FROM user_centers WHERE user_id = ?");
    $uc_stmt->execute([$user_id]);
    $extra_centers = $uc_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($extra_centers)) {
        $user_allowed_centers = array_merge($user_allowed_centers, $extra_centers);
    }
    
    // ۳. در صورتی که هیچ مرکزی تعریف نشده بود، به عنوان سوپاپ اطمینان، مراکزی که جمعدار اموالش است را بردار
    if (empty($user_allowed_centers)) {
        $my_c_stmt = $db->prepare("SELECT DISTINCT center FROM assets WHERE recipient LIKE ? OR created_by = ?");
        $my_c_stmt->execute(["%" . $user_name . "%", $user_id]);
        $my_c = $my_c_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($my_c)) {
            $user_allowed_centers = array_merge($user_allowed_centers, $my_c);
        }
    }
    
    $user_allowed_centers = array_unique(array_filter($user_allowed_centers));
}

// --- پارامترهای جستجو و فیلترینگ شدید امنیتی بر اساس مراکز مجاز ---
$search = $_GET["search"] ?? "";
$where = "1=1";
$params = [];

if($role === "keeper") {
    if (!empty($user_allowed_centers)) {
        $placeholders = implode(',', array_fill(0, count($user_allowed_centers), '?'));
        // بررسی مطابقت مبدا، مقصد یا مرکز فعلی دارایی با مراکز مجاز جمعدار
        $where = "(a.center IN ($placeholders) OR t.from_center IN ($placeholders) OR t.to_center IN ($placeholders))";
        $params = array_merge($user_allowed_centers, $user_allowed_centers, $user_allowed_centers);
    } else {
        // در صورتی که جمعدار هیچ مرکز مجازی ندارد، به جهت امنیت اطلاعات هیچ هسیتوری نشان داده نمی‌شود
        $where = "1=0";
    }
}

if($search) {
    $where .= " AND (t.transfer_code LIKE ? OR a.plate LIKE ?)";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
}

$transfers = $db->prepare("
    SELECT 
        t.transfer_code, 
        t.transfer_type, 
        t.transfer_date, 
        t.from_center, 
        t.to_center, 
        MAX(t.id) as id,
        COUNT(t.id) as total_assets,
        GROUP_CONCAT(CONCAT(a.name, ' (', a.plate, ')') SEPARATOR '، ') as asset_names
    FROM transfers t 
    JOIN assets a ON t.asset_id=a.id 
    WHERE $where
    GROUP BY t.transfer_code, t.transfer_type, t.transfer_date, t.from_center, t.to_center
    ORDER BY t.id DESC LIMIT 50
");
$transfers->execute($params);

$centers = $db->query("SELECT name as center FROM centers WHERE is_active = 1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>جابجایی اموال</title>
<link rel="stylesheet" href="css/app.css">
<style>
.type-select{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px}
.type-opt{background:#fff;border:2px solid #e2e8f0;border-radius:10px;padding:10px 8px;text-align:center;cursor:pointer;font-size:11px;font-weight:600}
.type-opt.sel{border-color:#4361ee;background:#eff6ff;color:#4361ee}
.type-opt .ticon{font-size:18px;display:block;margin-bottom:2px}
.tr-card{background:#fff;border-radius:12px;padding:12px;margin-bottom:6px;cursor:pointer}
.tr-type{display:inline-block;padding:2px 8px;border-radius:8px;font-size:10px;font-weight:600}
.t-internal{background:#d1fae5;color:#065f46}.t-permanent{background:#fee2e2;color:#991b1b}.t-temporary{background:#fef3c7;color:#92400e}.t-repair{background:#dbeafe;color:#1e40af}.t-scrap{background:#e2e8f0;color:#475569}
.hidden{display:none}
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);z-index:300;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:.25s}
.modal-overlay.show{opacity:1;visibility:visible}
.modal-sheet{background:#fff;border-radius:20px;width:calc(100% - 40px);max-width:380px;max-height:85vh;overflow-y:auto;padding:20px;transform:scale(.9);transition:transform .3s ease;box-shadow:0 25px 80px rgba(0,0,0,.25);margin:16px}
.modal-overlay.show .modal-sheet{transform:scale(1)}

/* پدینگ مینی‌مال عمودی ۵پیکسلی جهت ایجاد دقیق ۱۰پیکسل فاصله در لیست تبلت/مکان */
.pick-item{display:block;padding:5px 12px !important;text-decoration:none;color:#1c1917;border-bottom:1px solid #e9e4d9;font-size:12.5px;font-weight:700}
.pick-item:hover{background:#f8fafc}
.btn-light{background:#f1f5f9;color:#64748b; border:none; padding:10px; border-radius:10px; cursor:pointer;}

/* بهینه‌سازی کادر دراپ‌داون نتایج جستجو به صورت نسبی جهت هل دادن عناصر پایینی به جای همپوشانی */
#searchResults {
    display: none;
    max-height: 150px;
    overflow-y: auto;
    background: #fdfbf7 !important; /* رنگ هماهنگ با کاغذ گرم پوستی */
    border: 1px solid #cbd5e1 !important;
    border-radius: 12px !important;
    position: relative !important;   /* تغییر موقعیت به نسبی جهت هل دادن محتوای زیرین به پایین و جلوگیری از همپوشانی */
    margin-top: 8px !important;
    z-index: 50 !important;
    box-shadow: none !important;
}
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
</script>
</head>
<body>
<header class="top-bar"><a href="index.php">→</a><h1>🔄 جابجایی</h1><button onclick="openTransferModal()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer">＋</button></header>

<div class="content">
    <?php if($msg):?><div class="toast <?=$msgType=="error"?"toast-error":"toast-success"?>"><?=$msg?></div><?php endif?>
    
    <?php if(isset($_SESSION["last_transfer_code"])):?>
    <div style="background:#dbeafe;padding:12px;border-radius:10px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:12px; font-weight:bold;">✅ رسید واحد صادر شد (<?=$_SESSION["last_transfer_code"]?>)</span>
        <a href="print_transfer.php?code=<?=$_SESSION["last_transfer_code"]?>" style="background:#4361ee;color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none;font-size:12px">🖨️ پرینت لیست</a>
    </div>
    <?php unset($_SESSION["last_transfer_code"]); endif?>

    <!-- فیلد جستجوی شماره جابجایی یا پلاک اموال در بالای تاریخچه -->
    <div class="search-box" style="margin-bottom:12px;position:sticky;top:52px;z-index:40;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.05);padding:10px 14px;border-radius:12px;transform:translateZ(0);-webkit-transform:translateZ(0)">
        <span class="search-icon">🔍</span>
        <input type="text" placeholder="جستجوی شماره جابجایی یا پلاک..." value="<?=htmlspecialchars($search)?>" onchange="var v=toEnglishNum(this.value); location='?search='+v">
    </div>

    <div style="font-weight:700;color:#64748b;margin-bottom:8px">📋 تاریخچه اخیر</div>
    <?php if($transfers->rowCount() > 0): ?>
        <?php while($t=$transfers->fetch()): ?>
        <div class="tr-card" onclick="window.location='print_transfer.php?id=' + <?=$t['id']?>">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:11px;color:#94a3b8;font-weight:bold;">کد: <?=$t["transfer_code"]?> (<?=$t["total_assets"]?> کالا)</span>
                <?php 
                $typeLabels = [
                    "internal" => "داخلی",
                    "permanent" => "انتقال دائم",
                    "temporary" => "امانی",
                    "repair" => "تعمیرات",
                    "scrap" => "اسقاط",
                    "return" => "برگشت"
                ];
                $currentLabel = $typeLabels[$t["transfer_type"]] ?? $t["transfer_type"];
                ?>
                <span class="tr-type t-<?=$t["transfer_type"]?>"><?=$currentLabel?></span>
            </div>
            <div style="font-weight:600;font-size:13px;line-height:1.5;color:#1e293b;margin-bottom:4px;">
                📦 <?=htmlspecialchars($t["asset_names"])?>
            </div>
            <div style="font-size:10px;color:#94a3b8"><?=$t["from_center"]?> → <?=$t["to_center"]?></div>
        </div>
        <?php endwhile?>
    <?php else: ?>
        <div style="text-align:center;padding:40px;color:#94a3b8">📭 موردی یافت نشد</div>
    <?php endif; ?>
</div>

<!-- مودال اصلی جابجایی -->
<div class="modal-overlay" id="transferModal"><div class="modal-sheet">
    <h3>🔄 ثبت جابجایی جدید</h3>
    <form method="POST">
        <input type="hidden" name="transfer_type" id="tt" value="internal">
        <input type="hidden" name="asset_id" id="asset_id">
        <input type="hidden" name="asset_ids" id="asset_ids">

        <!-- سطر افقی شیک دکمه‌های رادیویی هم‌ردیف و مینی‌مال فاقد اسکرول -->
        <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; margin-bottom:15px; font-size:11.5px; white-space:nowrap; overflow:hidden; background:rgba(255,255,255,0.02); padding:10px 8px; border-radius:10px; border:1px solid rgba(0,0,0,0.05);">
            <label style="display:flex; align-items:center; gap:4px; cursor:pointer; font-weight:bold;">
                <input type="radio" name="transfer_type_radio" value="internal" checked onclick="selectType('internal')" style="width:15px; height:15px; accent-color:#4f46e5; margin:0; cursor:pointer;"> داخلی
            </label>
            <label style="display:flex; align-items:center; gap:4px; cursor:pointer; font-weight:bold;">
                <input type="radio" name="transfer_type_radio" value="permanent" onclick="selectType('permanent')" style="width:15px; height:15px; accent-color:#4f46e5; margin:0; cursor:pointer;"> انتقال
            </label>
            <label style="display:flex; align-items:center; gap:4px; cursor:pointer; font-weight:bold;">
                <input type="radio" name="transfer_type_radio" value="temporary" onclick="selectType('temporary')" style="width:15px; height:15px; accent-color:#4f46e5; margin:0; cursor:pointer;"> امانی
            </label>
            <label style="display:flex; align-items:center; gap:4px; cursor:pointer; font-weight:bold;">
                <input type="radio" name="transfer_type_radio" value="repair" onclick="selectType('repair')" style="width:15px; height:15px; accent-color:#4f46e5; margin:0; cursor:pointer;"> تعمیر
            </label>
            <label style="display:flex; align-items:center; gap:4px; cursor:pointer; font-weight:bold;">
                <input type="radio" name="transfer_type_radio" value="scrap" onclick="selectType('scrap')" style="width:15px; height:15px; accent-color:#4f46e5; margin:0; cursor:pointer;"> اسقاط
            </label>
        </div>

        <div class="input-group" style="position:relative">
            <input type="text" id="assetSearch" class="input-field" placeholder="🔍 جستجوی نام یا پلاک اموال..." oninput="searchAssets()" autocomplete="off">
            <div id="selectedCount" style="background:#eff6ff;color:#4361ee;padding:6px 10px;border-radius:8px;font-size:12px;margin-top:6px;display:none;align-items:center;justify-content:space-between;"></div>
            <div id="searchResults"></div>
        </div>

        <div id="internalFields">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px">
                <div style="position:relative">
                    <input type="hidden" name="to_floor" id="to_floor">
                    <div class="input-field" onclick="openPickModal('floor')" style="cursor:pointer;font-size:11px;display:flex;justify-content:space-between;"><span id="floorText">🏢 طبقه جدید...</span><span>▼</span></div>
                </div>
                <div style="position:relative">
                    <input type="hidden" name="to_location" id="to_location">
                    <div class="input-field" onclick="openPickModal('location')" style="cursor:pointer;font-size:11px;display:flex;justify-content:space-between;"><span id="locationText">📍 مکان جدید...</span><span>▼</span></div>
                </div>
            </div>
        </div>

        <div id="permanentFields" class="hidden">
            <select name="to_center" class="input-field" style="margin-top:10px">
                <option value="">🏠 انتخاب مرکز مقصد...</option>
                <?php foreach($centers as $c):?><option value="<?=$c["center"]?>"><?=$c["center"]?></option><?php endforeach?>
            </select>
        </div>

        <div class="input-group" style="margin-top:10px"><textarea name="reason" class="input-field" rows="2" placeholder="دلیل جابجایی (اختیاری)"></textarea></div>
        <input type="hidden" name="transfer_date" value="<?=jalali_date()?>">

        <div style="display:flex;gap:6px;margin-top:15px">
            <button name="save_transfer" class="btn btn-primary" style="flex:1">💾 ثبت رسید</button>
            <button type="button" onclick="closeTransferModal()" class="btn-light" style="flex:1">انصراف</button>
        </div>
    </form>
</div></div>

<!-- مودال انتخاب طبقه/مکان -->
<div class="modal-overlay" id="pickModal" onclick="if(event.target==this) this.classList.remove('show')"><div class="modal-sheet">
    <h3 id="pickTitle">انتخاب</h3>
    <div id="pickList" style="max-height:250px;overflow-y:auto;"></div>
    <button onclick="pickManual()" class="pick-item" style="color:#4361ee;width:100%;text-align:center;border:none;background:none;font-weight:800;">➕ تعریف مقدار جدید</button>
</div></div>

<!-- مودال تایید حذف انتخاب -->
<div class="modal-overlay" id="confirmClearModal"><div class="modal-sheet" style="max-width:300px;text-align:center;">
    <div style="font-size:30px">⚠️</div>
    <h4>لغو انتخاب‌ها؟</h4>
    <p style="font-size:12px;color:#64748b">آیا می‌خواهید لیست کالاهای انتخاب شده را پاک کنید?</p>
    <div style="display:flex;gap:8px;margin-top:15px">
        <button onclick="doClearSelection()" class="btn" style="background:#ef4444;color:#fff;flex:1;border:none;padding:8px;border-radius:8px">بله</button>
        <button onclick="document.getElementById('confirmClearModal').classList.remove('show')" class="btn-light" style="flex:1">خیر</button>
    </div>
</div></div>

<?php include "includes/bottom_nav.php"; ?>

<script>
var allAssets = [];
var selectedAssets = [];
var currentPickType = '';

fetch("api_get_assets.php").then(r=>r.json()).then(d=>allAssets=d);

function openTransferModal(){ document.getElementById('transferModal').classList.add('show'); }
function closeTransferModal(){ document.getElementById('transferModal').classList.remove('show'); }

function searchAssets(){
    let q = document.getElementById('assetSearch').value.toLowerCase();
    let res = document.getElementById('searchResults');
    if(q.length<1){ res.style.display='none'; return; }
    let html = '';
    allAssets.forEach(a => {
        if(a.name.toLowerCase().includes(q) || a.plate.toLowerCase().includes(q)){
            let checked = selectedAssets.includes(a.id) ? 'checked' : '';
            // تنظیم دقیق پدینگ ۵پیکسلی (فاصله عمودی ۱۰پیکسلی مجموع) و کدهای استایل ماسی شیک
            html += `<label style="display:flex;align-items:center;gap:10px;padding:5px 10px;border-bottom:1px solid #e9e4d9;cursor:pointer;margin:0;">
                <input type="checkbox" onchange="toggleAsset(${a.id}, this)" ${checked} style="width:14px;height:14px;accent-color:#4f46e5;margin:0;cursor:pointer;"> 
                <span style="font-size:12.5px;font-weight:700;color:#1c1917;">${a.plate} - ${a.name} (${a.center})</span>
            </label>`;
        }
    });
    res.innerHTML = html || '<div style="padding:10px;color:#94a3b8;font-size:11px;">موردی یافت نشد</div>';
    res.style.display = 'block';
}

function toggleAsset(id, el){
    if(el.checked) { if(!selectedAssets.includes(id)) selectedAssets.push(id); }
    else { selectedAssets = selectedAssets.filter(i => i !== id); }
    updateSelectedCount();
}

function updateSelectedCount(){
    let div = document.getElementById('selectedCount');
    if(selectedAssets.length > 0){
        div.innerHTML = `<span>📦 ${selectedAssets.length} مورد انتخاب شده</span> <span onclick="document.getElementById('confirmClearModal').classList.add('show')" style="color:red;cursor:pointer;font-weight:bold;font-size:16px">✕</span>`;
        div.style.display = 'flex';
    } else { div.style.display = 'none'; }
    document.getElementById('asset_ids').value = selectedAssets.join(',');
    document.getElementById('asset_id').value = selectedAssets[0] || '';
}

function doClearSelection(){
    selectedAssets = [];
    document.querySelectorAll('#searchResults input').forEach(i=>i.checked=false);
    updateSelectedCount();
    document.getElementById('confirmClearModal').classList.remove('show');
    document.getElementById('assetSearch').value = '';
    document.getElementById('searchResults').style.display='none';
}

function selectType(t){
    document.getElementById('tt').value = t;
    document.getElementById('internalFields').classList.toggle('hidden', t!=='internal');
    document.getElementById('permanentFields').classList.toggle('hidden', t!=='permanent');
}

function openPickModal(type){
    if(selectedAssets.length===0){ alert('اول یک کالا انتخاب کنید'); return; }
    currentPickType = type;
    let asset = allAssets.find(a=>a.id == selectedAssets[0]);
    let center = asset.center;
    let title = type === 'floor' ? 'انتخاب طبقه' : 'انتخاب مکان';
    document.getElementById('pickTitle').textContent = title + ' (' + center + ')';
    
    let list = [...new Set(allAssets.filter(a=>a.center === center && a[type]).map(a=>a[type]))].sort();
    let html = '';
    list.forEach(item => {
        html += `<div class="pick-item" onclick="setPick('${item}')">${item}</div>`;
    });
    document.getElementById('pickList').innerHTML = html || '<div style="padding:15px;color:#94a3b8;font-size:11px">داده‌ای یافت نشد</div>';
    document.getElementById('pickModal').classList.add('show');
}

function setPick(val){
    document.getElementById(currentPickType + 'Text').textContent = val;
    document.getElementById('to_' + currentPickType).value = val;
    document.getElementById('pickModal').classList.remove('show');
}

function pickManual(){
    let val = prompt('مقدار جدید را وارد کنید:');
    if(val) setPick(val);
}
</script>
</body></html>