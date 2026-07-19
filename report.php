<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();

$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_name = $_SESSION["fullname"];
$user_id = $_SESSION["user_id"];
$user_center_name = ""; if ($role !== "admin") { $u_stmt = $db->prepare("SELECT c.name FROM users u LEFT JOIN centers c ON u.center_id = c.id WHERE u.id = ?"); $u_stmt->execute([$user_id]); $user_center_name = $u_stmt->fetchColumn() ?: ""; if (!$user_center_name) { $uc_stmt = $db->prepare("SELECT center_name FROM user_centers WHERE user_id = ? LIMIT 1"); $uc_stmt->execute([$user_id]); $user_center_name = $uc_stmt->fetchColumn() ?: ""; } }

// تفکیک پویا: ستون مرکز فقط برای ادمین نمایش داده شود
$show_center_col = ($role === "admin");

$filter_status = $_GET["status"] ?? "";
$filter_type   = $_GET["type"] ?? "";
$export        = $_GET["export"] ?? "";

// مدیریت فیلترهای چندانتخابی آرایه‌ای پیشرفته اکسلی برای تمام ۵ ستون
$filter_centers = (array)($_GET["center"] ?? []);
$filter_centers = array_filter($filter_centers);

$filter_floors = (array)($_GET["floor"] ?? []);
$filter_floors = array_filter($filter_floors);

$filter_plates = (array)($_GET["plate"] ?? []);
$filter_plates = array_filter($filter_plates);

$filter_names = (array)($_GET["name"] ?? []);
$filter_names = array_filter($filter_names);

$filter_locations = (array)($_GET["location"] ?? []);
$filter_locations = array_filter($filter_locations);

$where = []; $params = [];
if($role === "keeper") {
    $where[] = "(recipient LIKE ? OR created_by = ?)";
    $params[] = "%$user_name%"; $params[] = $user_id;
}

// فیلتر چندانتخابی مراکز (فقط ادمین)
if($show_center_col && !empty($filter_centers)) {
    $placeholders = implode(',', array_fill(0, count($filter_centers), '?'));
    $where[] = "center IN ($placeholders)";
    $params = array_merge($params, $filter_centers);
}

// فیلتر چندانتخابی طبقات
if(!empty($filter_floors)) {
    $placeholders = implode(',', array_fill(0, count($filter_floors), '?'));
    $where[] = "floor IN ($placeholders)";
    $params = array_merge($params, $filter_floors);
}

// فیلتر چندانتخابی پلاک‌ها
if(!empty($filter_plates)) {
    $placeholders = implode(',', array_fill(0, count($filter_plates), '?'));
    $where[] = "plate IN ($placeholders)";
    $params = array_merge($params, $filter_plates);
}

// فیلتر چندانتخابی نام‌ها
if(!empty($filter_names)) {
    $placeholders = implode(',', array_fill(0, count($filter_names), '?'));
    $where[] = "name IN ($placeholders)";
    $params = array_merge($params, $filter_names);
}

// فیلتر چندانتخابی مکان استقرار
if(!empty($filter_locations)) {
    $placeholders = implode(',', array_fill(0, count($filter_locations), '?'));
    $where[] = "location IN ($placeholders)";
    $params = array_merge($params, $filter_locations);
}

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

// --- لایه امنیتی حریم خصوصی گزینه‌های فیلتر هدر ---
$sub_where = "1=1";
$sub_params = [];
if($role === "keeper") {
    $sub_where = "(recipient LIKE ? OR created_by = ?)";
    $sub_params = ["%" . $user_name . "%", $user_id];
}

// واکشی پویای مقادیر منحصربه‌فرد دیتابیس تحت لایه محافظتی حریم خصوصی هر مرکز
$stmt_up = $db->prepare("SELECT DISTINCT plate FROM assets WHERE $sub_where AND plate IS NOT NULL AND plate != '' ORDER BY plate");
$stmt_up->execute($sub_params);
$unique_plates = $stmt_up->fetchAll(PDO::FETCH_COLUMN);

$stmt_un = $db->prepare("SELECT DISTINCT name FROM assets WHERE $sub_where AND name IS NOT NULL AND name != '' ORDER BY name LIMIT 150");
$stmt_un->execute($sub_params);
$unique_names = $stmt_un->fetchAll(PDO::FETCH_COLUMN);

$stmt_uf = $db->prepare("SELECT DISTINCT floor FROM assets WHERE $sub_where AND floor IS NOT NULL AND floor != '' ORDER BY floor");
$stmt_uf->execute($sub_params);
$unique_floors = $stmt_uf->fetchAll(PDO::FETCH_COLUMN);

$stmt_ul = $db->prepare("SELECT DISTINCT location FROM assets WHERE $sub_where AND location IS NOT NULL AND location != '' ORDER BY location");
$stmt_ul->execute($sub_params);
$unique_locations = $stmt_ul->fetchAll(PDO::FETCH_COLUMN);

$unique_centers = $db->query("SELECT DISTINCT center FROM assets WHERE center IS NOT NULL AND center != '' ORDER BY center")->fetchAll(PDO::FETCH_COLUMN);

// ایمن‌سازی کامل متغیر با استفاده از تابع بومی کوت دیتابیس جهت ممانعت از خطای سینتکس
$centerCond = "WHERE 1=1";
if ($role === "admin" && !empty($filter_centers)) {
    $quoted_centers = array_map(function($c) use ($db) { return $db->quote($c); }, $filter_centers);
    $centerCond = "WHERE center IN (" . implode(',', $quoted_centers) . ")";
} elseif ($role !== "admin" && !empty($user_center_name)) {
    $centerCond = "WHERE center = " . $db->quote($user_center_name);
}

$centers = $db->query("SELECT DISTINCT center FROM assets WHERE center IS NOT NULL AND center != '' ORDER BY center")->fetchAll();
$statuses = $db->query("SELECT DISTINCT status FROM assets $centerCond ORDER BY status")->fetchAll();
$types = $db->query("SELECT DISTINCT type FROM assets $centerCond ORDER BY type")->fetchAll();
$floors = $db->query("SELECT DISTINCT floor FROM assets $centerCond AND floor IS NOT NULL AND floor != '' ORDER BY floor")->fetchAll();

// پچ شناسایی ستون‌های فیلتر شده جهت تغییر رنگ به قرمز
$is_plate_filtered = !empty($filter_plates);
$is_name_filtered = !empty($filter_names);
$is_center_filtered = !empty($filter_centers);
$is_floor_filtered = !empty($filter_floors);
$is_location_filtered = !empty($filter_locations);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>گزارش</title><link rel="stylesheet" href="css/app.css">
<style>
.table-wrap{background:#fff;border-radius:16px;overflow:hidden}
.t-header,.t-row{display:flex;border-bottom:1px solid #f1f5f9}
.t-header{background:#f8fafc;font-weight:900;font-size:10px;position:sticky;top:0;z-index:5}
.t-cell{padding:8px 5px;font-size:10px;text-align:center;flex:1;font-weight:600}
.floating-delete{display:none;position:fixed;bottom:100px;left:50%;transform:translateX(-50%);z-index:100;background:#ef4444;color:#fff;padding:12px 24px;border-radius:30px;font-size:13px;font-weight:700;border:none;cursor:pointer}
.floating-delete.show{display:block}
@keyframes toastIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}

/* استایل مینی‌مال فشرده شده دراپ‌داون‌های فیلتر به سبک اکسل */
.excel-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background: #fdfbf7 !important; /* رنگ هماهنگ با کاغذ گرم پوستی */
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
    border-radius: 12px !important;
    z-index: 300 !important;
    width: 190px !important; /* عرض مینی‌مال‌تر */
    text-align: right !important;
    direction: rlt !important;
}
.excel-dropdown label {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px !important; /* پدینگ عمودی ۴ پیکسل (بسیار مینی‌مال) */
    color: #1c1917 !important;
    font-size: 11px !important;
    border-bottom: 1px solid #e9e4d9;
    text-align: right !important;
    cursor: pointer;
    margin: 0 !important;
}
.excel-dropdown label:hover {
    background: #f4f0e6 !important;
    color: #000000 !important;
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
</script></head>
<body>
    <div class="content">

    <!-- ردیف دوم: ۴ المان کاملاً هم‌اندازه و موازی در کنار یکدیگر (هر کدام ۲۵٪ از عرض کل سطر) -->
    <div style="display:flex; gap:8px; margin-bottom:12px; align-items:stretch; margin-top:8px;">
        <!-- ۱. خلاصه تعداد نتایج -->
        <div class="summary-card" style="flex:1; margin:0; display:flex; align-items:center; justify-content:center; gap:4px; background:transparent !important; border:1.5px solid #57534e !important; color:#57534e !important; border-radius:12px !important; font-weight:bold !important; box-shadow:none !important; padding:10px 2px !important; font-size:12px !important; height:44px !important;">
            <span>📦 <?=number_format($total)?> مورد</span>
        </div>
        
        <!-- ۲. خروجی اکسل -->
        <a href="?<?=http_build_query(array_merge($_GET,["export"=>"excel"]))?>" class="btn" style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; margin:0; background:transparent !important; border:1.5px solid #059669 !important; color:#059669 !important; border-radius:12px !important; font-weight:bold !important; text-decoration:none !important; box-shadow:none !important; padding:10px 2px !important; font-size:12px !important; height:44px !important;">Excel</a>
        
        <!-- ۳. خروجی پی‌دی‌اف -->
        <a href="print_report.php?<?=http_build_query($_GET)?>" target="_blank" class="btn" style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; margin:0; background:transparent !important; border:1.5px solid #d97706 !important; color:#d97706 !important; border-radius:12px !important; font-weight:bold !important; text-decoration:none !important; box-shadow:none !important; padding:10px 2px !important; font-size:12px !important; height:44px !important;">PDF</a>
        
        <!-- ۴. دکمه بومی ارسال گزارش -->
        <button type="button" onclick="shareReport()" class="btn" style="flex:1; display:flex; align-items:center; justify-content:center; gap:4px; margin:0; background:transparent !important; border:1.5px solid #0ea5e9 !important; color:#0ea5e9 !important; border-radius:12px !important; font-weight:bold !important; box-shadow:none !important; padding:10px 2px !important; font-size:12px !important; height:44px !important;">ارسال</button>
    </div>

    <?php if($total > 0): ?>
    <div class="table-wrap" style="max-height:calc(100vh - 185px); max-height:calc(100dvh - 185px); overflow-y:auto; margin-bottom:20px !important;">
    <div class="t-header" style="border-top-left-radius: 16px !important; border-top-right-radius: 16px !important;">
    <?php if($role==="admin"):?><div class="t-cell" style="flex:0.3"><input type="checkbox" id="selectAll" onclick="toggleAll()" style="width:14px;height:14px;cursor:pointer"></div><?php endif?>
    
    <!-- طراحی اکسلی هدرها به همراه فلش‌های کوچک و دکمه لغو فیلتر مستقیم ضربدر قرمز در هدرهای فعال -->
    <div class="t-cell" onclick="toggleExcelDropdown(event, 'dropdown-plate')" style="position:relative; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px; <?= $is_plate_filtered ? 'color:#dc2626 !important; font-weight:900 !important;' : '' ?>">
        <span>پلاک</span>
        <span style="font-size:9px; color:<?= $is_plate_filtered ? '#dc2626' : '#57534e' ?>;">▼</span>
        <?php if ($is_plate_filtered): ?>
        <span onclick="event.stopPropagation(); clearColumnFilter('plate')" style="font-size:11px; color:#dc2626; margin-right:2px; font-weight:bold;" title="لغو فیلتر ستون">✕</span>
        <?php endif; ?>
        
        <!-- چندانتخابی پلاک -->
        <div class="excel-dropdown" id="dropdown-plate">
            <div style="padding:12px; text-align:right;" onclick="event.stopPropagation()">
                <input type="text" placeholder="🔍 جستجو پلاک..." oninput="filterDropdownItems(this, 'list-plate')" style="width:100%; padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:6px; background:#faf8f5; color:#1c1917; margin-bottom:8px;">
                <label style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:bold; margin-bottom:6px; color:#1c1917; cursor:pointer;">
                    <input type="checkbox" onclick="toggleSelectAllDropdown(this, 'list-plate', 'plate')" style="width:14px; height:14px; accent-color:#4f46e5;"> (انتخاب همه)
                </label>
                <div id="list-plate" style="max-height:110px; overflow-y:auto; border-top:1px solid #e9e4d9; padding-top:6px; display:flex; flex-direction:column; gap:3px;">
                    <?php foreach($unique_plates as $p): 
                        $checked = in_array($p, $filter_plates) ? 'checked' : '';
                    ?>
                    <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#1c1917; cursor:pointer;">
                        <input type="checkbox" value="<?=htmlspecialchars($p)?>" <?=$checked?> onchange="applyExcelMultiFilter('list-plate', 'plate')" style="width:14px; height:14px; accent-color:#4f46e5;"> <?=htmlspecialchars($p)?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="t-cell" onclick="toggleExcelDropdown(event, 'dropdown-name')" style="position:relative; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px; <?= $is_name_filtered ? 'color:#dc2626 !important; font-weight:900 !important;' : '' ?>">
        <span>نام</span>
        <span style="font-size:9px; color:<?= $is_name_filtered ? '#dc2626' : '#57534e' ?>;">▼</span>
        <?php if ($is_name_filtered): ?>
        <span onclick="event.stopPropagation(); clearColumnFilter('name')" style="font-size:11px; color:#dc2626; margin-right:2px; font-weight:bold;" title="لغو فیلتر ستون">✕</span>
        <?php endif; ?>
        
        <!-- چندانتخابی نام کالا -->
        <div class="excel-dropdown" id="dropdown-name">
            <div style="padding:12px; text-align:right;" onclick="event.stopPropagation()">
                <input type="text" placeholder="🔍 جستجو کالا..." oninput="filterDropdownItems(this, 'list-name')" style="width:100%; padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:6px; background:#faf8f5; color:#1c1917; margin-bottom:8px;">
                <label style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:bold; margin-bottom:6px; color:#1c1917; cursor:pointer;">
                    <input type="checkbox" onclick="toggleSelectAllDropdown(this, 'list-name', 'name')" style="width:14px; height:14px; accent-color:#4f46e5;"> (انتخاب همه)
                </label>
                <div id="list-name" style="max-height:110px; overflow-y:auto; border-top:1px solid #e9e4d9; padding-top:6px; display:flex; flex-direction:column; gap:3px;">
                    <?php foreach($unique_names as $n): 
                        $checked = in_array($n, $filter_names) ? 'checked' : '';
                    ?>
                    <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#1c1917; cursor:pointer;">
                        <input type="checkbox" value="<?=htmlspecialchars($n)?>" <?=$checked?> onchange="applyExcelMultiFilter('list-name', 'name')" style="width:14px; height:14px; accent-color:#4f46e5;"> <?=htmlspecialchars($n)?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if($show_center_col): ?>
    <div class="t-cell" onclick="toggleExcelDropdown(event, 'dropdown-center')" style="position:relative; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px; <?= $is_center_filtered ? 'color:#dc2626 !important; font-weight:900 !important;' : '' ?>">
        <span>مرکز</span>
        <span style="font-size:9px; color:<?= $is_center_filtered ? '#dc2626' : '#57534e' ?>;">▼</span>
        <?php if ($is_center_filtered): ?>
        <span onclick="event.stopPropagation(); clearColumnFilter('center')" style="font-size:11px; color:#dc2626; margin-right:2px; font-weight:bold;" title="لغو فیلتر ستون">✕</span>
        <?php endif; ?>
        
        <!-- چندانتخابی مرکز (مخصوص ادمین) -->
        <div class="excel-dropdown" id="dropdown-center">
            <div style="padding:12px; text-align:right;" onclick="event.stopPropagation()">
                <input type="text" placeholder="🔍 جستجو مرکز..." oninput="filterDropdownItems(this, 'list-center')" style="width:100%; padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:6px; background:#faf8f5; color:#1c1917; margin-bottom:8px;">
                <label style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:bold; margin-bottom:6px; color:#1c1917; cursor:pointer;">
                    <input type="checkbox" onclick="toggleSelectAllDropdown(this, 'list-center', 'center')" style="width:14px; height:14px; accent-color:#4f46e5;"> (انتخاب همه)
                </label>
                <div id="list-center" style="max-height:110px; overflow-y:auto; border-top:1px solid #e9e4d9; padding-top:6px; display:flex; flex-direction:column; gap:3px;">
                    <?php foreach($unique_centers as $uc): 
                        $checked = in_array($uc, $filter_centers) ? 'checked' : '';
                    ?>
                    <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#1c1917; cursor:pointer;">
                        <input type="checkbox" value="<?=htmlspecialchars($uc)?>" <?=$checked?> onchange="applyExcelMultiFilter('list-center', 'center')" style="width:14px; height:14px; accent-color:#4f46e5;"> <?=htmlspecialchars($uc)?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="t-cell" onclick="toggleExcelDropdown(event, 'dropdown-floor')" style="position:relative; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px; <?= $is_floor_filtered ? 'color:#dc2626 !important; font-weight:900 !important;' : '' ?>">
        <span>طبقه</span>
        <span style="font-size:9px; color:<?= $is_floor_filtered ? '#dc2626' : '#57534e' ?>;">▼</span>
        <?php if ($is_floor_filtered): ?>
        <span onclick="event.stopPropagation(); clearColumnFilter('floor')" style="font-size:11px; color:#dc2626; margin-right:2px; font-weight:bold;" title="لغو فیلتر ستون">✕</span>
        <?php endif; ?>
        
        <!-- چندانتخابی طبقه -->
        <div class="excel-dropdown" id="dropdown-floor">
            <div style="padding:12px; text-align:right;" onclick="event.stopPropagation()">
                <input type="text" placeholder="🔍 جستجو طبقه..." oninput="filterDropdownItems(this, 'list-floor')" style="width:100%; padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:6px; background:#faf8f5; color:#1c1917; margin-bottom:8px;">
                <label style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:bold; margin-bottom:6px; color:#1c1917; cursor:pointer;">
                    <input type="checkbox" onclick="toggleSelectAllDropdown(this, 'list-floor', 'floor')" style="width:14px; height:14px; accent-color:#4f46e5;"> (انتخاب همه)
                </label>
                <div id="list-floor" style="max-height:110px; overflow-y:auto; border-top:1px solid #e9e4d9; padding-top:6px; display:flex; flex-direction:column; gap:3px;">
                    <?php foreach($unique_floors as $uf): 
                        $checked = in_array($uf, $filter_floors) ? 'checked' : '';
                    ?>
                    <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#1c1917; cursor:pointer;">
                        <input type="checkbox" value="<?=htmlspecialchars($uf)?>" <?=$checked?> onchange="applyExcelMultiFilter('list-floor', 'floor')" style="width:14px; height:14px; accent-color:#4f46e5;"> <?=htmlspecialchars($uf)?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="t-cell" onclick="toggleExcelDropdown(event, 'dropdown-location')" style="position:relative; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:4px; <?= $is_location_filtered ? 'color:#dc2626 !important; font-weight:900 !important;' : '' ?>">
        <span>مکان</span>
        <span style="font-size:9px; color:<?= $is_location_filtered ? '#dc2626' : '#57534e' ?>;">▼</span>
        <?php if ($is_location_filtered): ?>
        <span onclick="event.stopPropagation(); clearColumnFilter('location')" style="font-size:11px; color:#dc2626; margin-right:2px; font-weight:bold;" title="لغو فیلتر ستون">✕</span>
        <?php endif; ?>
        
        <!-- چندانتخابی مکان استقرار -->
        <div class="excel-dropdown" id="dropdown-location">
            <div style="padding:12px; text-align:right;" onclick="event.stopPropagation()">
                <input type="text" placeholder="🔍 جستجو مکان..." oninput="filterDropdownItems(this, 'list-location')" style="width:100%; padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:6px; background:#faf8f5; color:#1c1917; margin-bottom:8px;">
                <label style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:bold; margin-bottom:6px; color:#1c1917; cursor:pointer;">
                    <input type="checkbox" onclick="toggleSelectAllDropdown(this, 'list-location', 'location')" style="width:14px; height:14px; accent-color:#4f46e5;"> (انتخاب همه)
                </label>
                <div id="list-location" style="max-height:110px; overflow-y:auto; border-top:1px solid #e9e4d9; padding-top:6px; display:flex; flex-direction:column; gap:3px;">
                    <?php foreach($unique_locations as $ul): 
                        $checked = in_array($ul, $filter_locations) ? 'checked' : '';
                    ?>
                    <label style="display:flex; align-items:center; gap:6px; font-size:11px; color:#1c1917; cursor:pointer;">
                        <input type="checkbox" value="<?=htmlspecialchars($ul)?>" <?=$checked?> onchange="applyExcelMultiFilter('list-location', 'location')" style="width:14px; height:14px; accent-color:#4f46e5;"> <?=htmlspecialchars($ul)?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    <form method="POST" id="delForm">
    <?php foreach($assets as $a):?>
    <div class="t-row">
    <?php if($role==="admin"):?><div class="t-cell" style="flex:0.3"><input type="checkbox" name="delete_ids[]" value="<?=$a["id"]?>" class="del-check" style="width:14px;height:14px"></div><?php endif?>
    <div class="t-cell"><?=htmlspecialchars($a["plate"])?></div>
    <div class="t-cell"><?=htmlspecialchars($a["name"])?></div>
    <?php if($show_center_col): ?><div class="t-cell"><?=htmlspecialchars($a["center"])?></div><?php endif; ?>
    <div class="t-cell"><?=htmlspecialchars($a["floor"])?></div>
    <div class="t-cell"><?=htmlspecialchars($a["location"])?></div>
    </div>
    <?php endforeach?>
    </form>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:60px;color:#94a3b8">📭 موردی یافت نشد</div>
    <?php endif?>
    </div>

    <?php if($role==="admin"):?>
    <button class="floating-delete" id="floatingDelete" onclick="submitDelete()">🗑️ حذف انتخاب‌شده‌ها</button>
    <?php endif?>

    <?php include "includes/bottom_nav.php"; ?>
    <script>
    function toggleAll(){var s=document.getElementById("selectAll");if(!s)return;var all=document.querySelectorAll(".del-check");for(var i=0;i<all.length;i++){all[i].checked=s.checked}updateCount()}
    function updateCount(){var c=document.querySelectorAll(".del-check:checked").length;var b=document.getElementById("floatingDelete");if(b){if(c>0){b.classList.add("show");b.textContent="🗑️ حذف "+c+" مورد"}else{b.classList.remove("show")}}}
    function submitDelete(){var cbs=document.querySelectorAll(".del-check:checked").length;var f=document.getElementById("delForm");cbs.forEach(function(cb){var i=document.createElement("input");i.type="hidden";i.name="delete_ids[]";i.value=cb.value;f.appendChild(i)});f.submit()}
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

    function shareReport() {
        var shareUrl = window.location.protocol + '//' + window.location.host + window.location.pathname.replace('report.php', 'print_report.php') + window.location.search;
        
        if (navigator.share) {
            navigator.share({
                title: 'گزارش اموال',
                text: 'مشاهده گزارش اموال مجتمع:',
                url: shareUrl
            }).catch(function() {
                copyToClipboard(shareUrl);
            });
        } else {
            copyToClipboard(shareUrl);
        }
    }

    // بستن خودکار دراپ‌داون‌ها هنگام کلیک بر روی سایر نقاط صفحه
    document.addEventListener("click", function(e) {
        if(!e.target.closest('.t-cell')) {
            document.querySelectorAll('.excel-dropdown').forEach(function(d) {
                d.style.display = "none";
            });
        }
    });

    // بازنویسی پویا و شیک توابع کنترل دراپ‌داون‌های فیلتر اکسلی بدون تداخل
    function toggleExcelDropdown(e, id) {
        if(e) e.stopPropagation();
        var current = document.getElementById(id);
        var isOpen = current.style.display === "block";
        
        // بستن تمام دراپ‌داون‌های باز در صفحه جهت تقارن
        document.querySelectorAll('.excel-dropdown').forEach(function(d) {
            d.style.display = "none";
        });
        
        // باز کردن دراپ‌داون فعلی
        if(!isOpen) {
            current.style.display = "block";
        }
    }

    // تصفیه دراپ‌داون در زمان واقعی هنگام تایپ کاربر در فیلد سرچ درون دراپ‌داون
    function filterDropdownItems(inputEl, listId) {
        var q = inputEl.value.toLowerCase();
        var items = document.querySelectorAll('#' + listId + ' label');
        items.forEach(function(item) {
            var text = item.textContent.toLowerCase();
            item.style.display = text.includes(q) ? 'flex' : 'none';
        });
    }

    // کنترل چک‌باکس انتخاب همه (Select All) و اعمال فوری فیلترها
    function toggleSelectAllDropdown(selectAllCb, listId, paramName) {
        var checkboxes = document.querySelectorAll('#' + listId + ' input[type="checkbox"]');
        checkboxes.forEach(function(cb) {
            if (cb.parentNode.style.display !== 'none') {
                cb.checked = selectAllCb.checked;
            }
        });
        // فیلترینگ درجا بعد از تغییر حالت انتخاب همه
        applyExcelMultiFilter(listId, paramName);
    }

    // اعمال فیلتر چندانتخابی پیشرفته اکسلی به صورت آرایه مستقیم در آدرس بار URL (بسیار سریع و واکنشی)
    function applyExcelMultiFilter(listId, paramName) {
        var url = new URL(window.location.href);
        // پاک کردن فیلتر قدیمی این ستون
        url.searchParams.delete(paramName + '[]');
        
        // دریافت چک‌باکس‌های تیک‌خورده ستون مربوطه
        var checked = document.querySelectorAll('#' + listId + ' input[type="checkbox"]:checked');
        
        // بهینه‌سازی حیاتی و هوشمند: اگر همه گزینه‌ها انتخاب شده باشند، فیلتر را برای جلوگیری از خطای طول آدرس حذف می‌کنیم
        var allCheckboxes = document.querySelectorAll('#' + listId + ' input[type="checkbox"]');
        
        if (checked.length === allCheckboxes.length || checked.length === 0) {
            url.searchParams.delete(paramName + '[]');
        } else {
            checked.forEach(function(cb) {
                if (cb.value) {
                    url.searchParams.append(paramName + '[]', cb.value);
                }
            });
        }
        
        window.location.href = url.toString();
    }

    // ثبت مقادیر متنی و ارسال مستقیم فرم جستجو
    function applyTextFilter(name, value) {
        var url = new URL(window.location.href);
        url.searchParams.set(name, value);
        window.location.href = url.toString();
    }

    // حذف فیلتر مربوط به هر ستون به صورت مستقل (آرایه یا تک متغیره)
    function clearColumnFilter(name) {
        var url = new URL(window.location.href);
        url.searchParams.delete(name + '[]');
        url.searchParams.delete(name);
        window.location.href = url.toString();
    }

    function copyToClipboard(text) {
        var temp = document.createElement("input");
        document.body.appendChild(temp);
        temp.value = text;
        temp.select();
        document.execCommand("copy");
        document.body.removeChild(temp);
        showToast("🔗 لینک گزارش در حافظه کپی شد", "success");
    }
    </script>
    </body></html>