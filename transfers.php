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

// واکشی لیست برای تاریخچه
if($role === "keeper") {
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
        WHERE a.center IN (SELECT name FROM centers WHERE is_active=1) 
        GROUP BY t.transfer_code, t.transfer_type, t.transfer_date, t.from_center, t.to_center
        ORDER BY t.id DESC LIMIT 50
    ");
    $transfers->execute();
} else {
    $transfers = $db->query("
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
        GROUP BY t.transfer_code, t.transfer_type, t.transfer_date, t.from_center, t.to_center
        ORDER BY t.id DESC LIMIT 50
    ");
}
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
.pick-item{display:block;padding:11px 12px;text-decoration:none;color:#0f172a;border-bottom:1px solid #f1f5f9;font-size:12px;font-weight:600}
.pick-item:hover{background:#f8fafc}
.btn-light{background:#f1f5f9;color:#64748b; border:none; padding:10px; border-radius:10px; cursor:pointer;}
</style>
</head>
<body>
<header class="top-bar"><a href="index.php">←</a><h1>🔄 جابجایی</h1><button onclick="openTransferModal()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer">＋</button></header>

<div class="content">
    <?php if($msg):?><div class="toast <?=$msgType=="error"?"toast-error":"toast-success"?>"><?=$msg?></div><?php endif?>
    
    <?php if(isset($_SESSION["last_transfer_code"])):?>
    <div style="background:#dbeafe;padding:12px;border-radius:10px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:12px; font-weight:bold;">✅ رسید واحد صادر شد (<?=$_SESSION["last_transfer_code"]?>)</span>
        <a href="print_transfer.php?code=<?=$_SESSION["last_transfer_code"]?>" style="background:#4361ee;color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none;font-size:12px">🖨️ پرینت لیست</a>
    </div>
    <?php unset($_SESSION["last_transfer_code"]); endif?>

    <div style="font-weight:700;color:#64748b;margin-bottom:8px">📋 تاریخچه اخیر</div>
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
</div>

<!-- مودال اصلی جابجایی -->
<div class="modal-overlay" id="transferModal"><div class="modal-sheet">
    <h3>🔄 ثبت جابجایی جدید</h3>
    <form method="POST">
        <input type="hidden" name="transfer_type" id="tt" value="internal">
        <input type="hidden" name="asset_id" id="asset_id">
        <input type="hidden" name="asset_ids" id="asset_ids">

        <div class="type-select">
            <div class="type-opt sel" onclick="selectType('internal',this)"><span class="ticon">🏢</span>داخلی</div>
            <div class="type-opt" onclick="selectType('permanent',this)"><span class="ticon">📦</span>انتقال</div>
            <div class="type-opt" onclick="selectType('temporary',this)"><span class="ticon">🤝</span>امانی</div>
            <div class="type-opt" onclick="selectType('repair',this)"><span class="ticon">🔧</span>تعمیر</div>
        </div>

        <div class="input-group" style="position:relative">
            <input type="text" id="assetSearch" class="input-field" placeholder="🔍 جستجوی نام یا پلاک اموال..." oninput="searchAssets()" autocomplete="off">
            <div id="selectedCount" style="background:#eff6ff;color:#4361ee;padding:6px 10px;border-radius:8px;font-size:12px;margin-top:6px;display:none;align-items:center;justify-content:space-between;"></div>
            <div id="searchResults" style="display:none;max-height:180px;overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:10px;position:absolute;top:45px;left:0;right:0;z-index:50;box-shadow:0 10px 20px rgba(0,0,0,0.1)"></div>
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
    <button onclick="pickManual()" class="pick-item" style="color:#4361ee;width:100%;text-align:center;border:none;background:none;">➕ تعریف مقدار جدید</button>
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
            html += `<label style="display:flex;align-items:center;gap:10px;padding:10px;border-bottom:1px solid #eee;cursor:pointer">
                <input type="checkbox" onchange="toggleAsset(${a.id}, this)" ${checked}> 
                <span style="font-size:12px">${a.plate} - ${a.name} (${a.center})</span>
            </label>`;
        }
    });
    res.innerHTML = html || '<div style="padding:10px;color:#999">یافت نشد</div>';
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

function selectType(t, el){
    document.getElementById('tt').value = t;
    document.querySelectorAll('.type-opt').forEach(o=>o.classList.remove('sel'));
    el.classList.add('sel');
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
    document.getElementById('pickList').innerHTML = html || '<div style="padding:15px;color:#999;font-size:11px">داده‌ای یافت نشد</div>';
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