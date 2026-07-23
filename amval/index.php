<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
checkAuth();

$db = getDB();
$role = $_SESSION['role'] ?? 'viewer';
$user_name = $_SESSION['fullname'];
$user_id = $_SESSION['user_id'];

$greeting = date('H') < 12 ? 'صبح بخیر' : (date('H') < 17 ? 'ظهر بخیر' : 'شب بخیر');

if($role === 'admin' || $role === 'viewer') {
    $total = $db->query("SELECT COUNT(*) FROM assets")->fetchColumn();
    $active = $db->query("SELECT COUNT(*) FROM assets WHERE status='سالم'")->fetchColumn();
    $damaged = $db->query("SELECT COUNT(*) FROM assets WHERE status IN ('خراب','در تعمیر')")->fetchColumn();
    $retired = $db->query("SELECT COUNT(*) FROM assets WHERE status='اسقاط'")->fetchColumn();
    
    $internal = $db->query("SELECT COUNT(*) FROM transfers WHERE transfer_type='internal'")->fetchColumn();
    $permanent = $db->query("SELECT COUNT(*) FROM transfers WHERE transfer_type='permanent'")->fetchColumn();
    $temporary = $db->query("SELECT COUNT(*) FROM transfers WHERE transfer_type='temporary'")->fetchColumn();
    $repair = $db->query("SELECT COUNT(*) FROM transfers WHERE transfer_type='repair'")->fetchColumn();
    $new_assets = $db->query("SELECT COUNT(*) FROM assets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
} elseif($role === 'keeper') {
    $where = "(recipient LIKE '%$user_name%' OR created_by = $user_id)";
    $total = $db->query("SELECT COUNT(*) FROM assets WHERE $where")->fetchColumn();
    $active = $db->query("SELECT COUNT(*) FROM assets WHERE $where AND status='سالم'")->fetchColumn();
    $damaged = $db->query("SELECT COUNT(*) FROM assets WHERE $where AND status IN ('خراب','در تعمیر')")->fetchColumn();
    $retired = $db->query("SELECT COUNT(*) FROM assets WHERE $where AND status='اسقاط'")->fetchColumn();
    
    $internal = $db->query("SELECT COUNT(*) FROM transfers t JOIN assets a ON t.asset_id=a.id WHERE t.transfer_type='internal' AND (a.recipient LIKE '%$user_name%' OR t.transferred_by=$user_id)")->fetchColumn();
    $permanent = $db->query("SELECT COUNT(*) FROM transfers t JOIN assets a ON t.asset_id=a.id WHERE t.transfer_type='permanent' AND (a.recipient LIKE '%$user_name%' OR t.transferred_by=$user_id)")->fetchColumn();
    $temporary = $db->query("SELECT COUNT(*) FROM transfers t JOIN assets a ON t.asset_id=a.id WHERE t.transfer_type='temporary' AND (a.recipient LIKE '%$user_name%' OR t.transferred_by=$user_id)")->fetchColumn();
    $repair = $db->query("SELECT COUNT(*) FROM transfers t JOIN assets a ON t.asset_id=a.id WHERE t.transfer_type='repair' AND (a.recipient LIKE '%$user_name%' OR t.transferred_by=$user_id)")->fetchColumn();
    $new_assets = $db->query("SELECT COUNT(*) FROM assets WHERE ($where) AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
} else {
    $total = $db->query("SELECT COUNT(*) FROM assets")->fetchColumn();
    $active = $db->query("SELECT COUNT(*) FROM assets WHERE status='سالم'")->fetchColumn();
    $damaged = $db->query("SELECT COUNT(*) FROM assets WHERE status IN ('خراب','در تعمیر')")->fetchColumn();
    $retired = $db->query("SELECT COUNT(*) FROM assets WHERE status='اسقاط'")->fetchColumn();
    $internal = $db->query("SELECT COUNT(*) FROM transfers WHERE transfer_type='internal'")->fetchColumn();
    $permanent = $db->query("SELECT COUNT(*) FROM transfers WHERE transfer_type='permanent'")->fetchColumn();
    $temporary = $db->query("SELECT COUNT(*) FROM transfers WHERE transfer_type='temporary'")->fetchColumn();
    $repair = $db->query("SELECT COUNT(*) FROM transfers WHERE transfer_type='repair'")->fetchColumn();
    $new_assets = $db->query("SELECT COUNT(*) FROM assets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
}

// محاسبه موجودی و انتقال مرکز اختصاصی کاربر
$my_center = "";
$center_inventory = 0;
$center_transferred = 0;

if ($db) {
    $stmt_c = $db->prepare("SELECT DISTINCT center FROM assets WHERE recipient LIKE ? OR created_by = ? LIMIT 1");
    $stmt_c->execute(["%" . $user_name . "%", $user_id]);
    $my_center = $stmt_c->fetchColumn() ?: "";

    if (!$my_center) {
        $my_center = $db->query("SELECT name FROM centers WHERE is_active=1 LIMIT 1")->fetchColumn() ?: "";
    }

    if ($my_center) {
        $stmt_inv = $db->prepare("SELECT COUNT(*) FROM assets WHERE center = ? AND status != 'اسقاط'");
        $stmt_inv->execute([$my_center]);
        $center_inventory = $stmt_inv->fetchColumn() ?: 0;

        $stmt_tr = $db->prepare("SELECT COUNT(*) FROM transfers WHERE from_center LIKE ? AND transfer_type = 'permanent'");
        $stmt_tr->execute(["%" . $my_center . "%"]);
        $center_transferred = $stmt_tr->fetchColumn() ?: 0;
    }
}

// محاسبات ریاضی درصد خطوط پیشرفت مینی‌مال جهت حفظ امنیت تقسیم بر صفر
$total_val = $total ?: 1;
$active_pct = min(100, round(($active / $total_val) * 100));
$damaged_pct = min(100, round(($damaged / $total_val) * 100));
$retired_pct = min(100, round(($retired / $total_val) * 100));

// محاسبات جابجایی
$total_transfers = ($new_assets + $permanent + $internal + $temporary) ?: 1;
$new_pct = min(100, round(($new_assets / $total_transfers) * 100));
$perm_pct = min(100, round(($permanent / $total_transfers) * 100));
$int_pct = min(100, round(($internal / $total_transfers) * 100));
$temp_pct = min(100, round(($temporary / $total_transfers) * 100));

// محاسبات مرکز
$center_total = ($center_inventory + $center_transferred) ?: 1;
$center_inv_pct = min(100, round(($center_inventory / $center_total) * 100));
$center_tr_pct = min(100, round(($center_transferred / $center_total) * 100));

$popup_message = null;
if($role === "keeper") {
    $pm = $db->prepare("SELECT m.*, u.fullname as sender_name FROM messages m LEFT JOIN users u ON m.sender_id = u.id WHERE m.is_read = 0 AND (m.receiver_id = ? OR m.receiver_center IN (SELECT DISTINCT center FROM assets WHERE recipient LIKE ?)) ORDER BY m.created_at DESC LIMIT 1");
    $pm->execute([$user_id, "%$user_name%"]);
    $popup_message = $pm->fetch();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <?php include 'includes/pwa.php'; ?>
    <title>داشبورد | اموال</title>
    <link rel="stylesheet" href="css/app.css">
    <style>
        .list-container {
            background: transparent !important;
            border: none !important;
            margin-bottom: 24px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .list-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #cbd5e1 !important; /* دیوایدر بسیار ملایم */
            gap: 16px;
        }
        .list-row:last-child {
            border-bottom: none !important;
        }
        .list-row .info-part {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 145px; /* ثابت برای تراز شدن عمودی متن‌ها */
            flex-shrink: 0;
        }
        .list-row .label {
            font-size: 13px;
            font-weight: 700;
            color: #1c1917;
        }
        .list-row .progress-part {
            flex: 1;
            height: 4px; /* خط پیشرفت بسیار باریک و ظریف مویی */
            background: #e9e4d9; /* تراک ملایم ماسی */
            border-radius: 2px;
            overflow: hidden;
            position: relative;
        }
        .list-row .progress-fill {
            height: 100%;
            border-radius: 2px;
        }
        .list-row .val-part {
            font-size: 15.5px;
            font-weight: 900;
            color: #000000;
            text-align: left;
            width: 60px; /* تراز از چپ */
            flex-shrink: 0;
        }
        .list-row .indicator-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }
        .section-header {
            font-size: 13.5px;
            font-weight: 900;
            color: #1c1917;
            margin-bottom: 6px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 6px;
        }
        .modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);z-index:300;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:.25s}
        .modal-overlay.show{opacity:1;visibility:visible}
        .modal-sheet{background:#fff;border-radius:20px;width:calc(100% - 40px);max-width:380px;max-height:70vh;overflow-y:auto;padding:20px;transform:scale(.9);transition:transform .3s ease;box-shadow:0 25px 80px rgba(0,0,0,.25);margin:16px}
    </style>
</head>
<body>

<!-- هدر یکپارچه شیک و فیکس بالا حاوی مشخصات تک‌خطی فوق‌العاده بولد، مشکی خالص و خوانا فاقد هرگونه تکرار -->
<header class="top-bar" style="display:block; padding:12px 16px !important; border-bottom:1px solid #cbd5e1 !important; background:#f4f0e6 !important; position:sticky; top:0; z-index:100; box-shadow:0 2px 10px rgba(0,0,0,0.03) !important;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <!-- تمامی اطلاعات کاملاً هم‌سطح و در یک خط واحد با فونت فوق‌العاده ضخیم و مشکی خالص -->
        <div style="display:flex; align-items:center; gap:6px; flex:1; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; font-size:13.5px; font-weight:900; color:#000000 !important;">
            <span style="color:#000000 !important; flex-shrink:0; font-weight:900 !important;"><?=$greeting?> 👋</span>
            <span style="color:#000000 !important; flex-shrink:0; font-weight:900 !important;"><?=htmlspecialchars($user_name)?></span>
            <span style="color:#000000 !important; font-size:12.5px !important; font-weight:900 !important; overflow:hidden; text-overflow:ellipsis;">(👤 <?=getRoleName($role)?><?php if($my_center): ?> | 🏢 <?= htmlspecialchars($my_center) ?><?php endif; ?>)</span>
        </div>
        
        <div class="user-menu" onclick="t()" style="cursor:pointer; position:relative; flex-shrink:0;">
            <div class="user-avatar" style="width:40px; height:40px; border-radius:50%; background:#4f46e5; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:900; box-shadow:0 4px 15px rgba(79,70,229,0.3);"><?=mb_substr($user_name,0,1)?></div>
            <div id="dd" style="display:none;position:absolute;top:46px;left:0;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);padding:8px;min-width:160px;z-index:200;">
                <a href="messages.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;font-weight:700;">📨 پیام‌ها</a>
                <a href="profile.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;font-weight:700;">👤 پروفایل</a>
                <?php if($role=='admin'):?>
                <a href="admin/users.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;font-weight:700;">👥 کاربران</a>
                <a href="admin/centers.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;font-weight:700;">🏢 مراکز</a>
                <a href="admin/sql.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;font-weight:700;">🔧 کنسول SQL</a>
                <a href="activity_log.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;font-weight:700;">📋 لاگ فعالیت</a>
                <?php endif;?>
                <hr style="border:none;border-top:1px solid #e2e8f0;margin:4px 0;">
                <a href="logout.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#dc2626;border-radius:8px;font-size:13px;font-weight:700;">🚪 خروج</a>
            </div>
        </div>
    </div>
</header>

<div class="content" style="padding-top: 12px !important;">
    <!-- بنر تکراری و کادر پایینی از بدنه کاملاً حذف شد تا مستقیماً آمارها از زیر هدر لوکس آغاز شوند -->
    
    <!-- بخش اول: وضعیت کلی اموال سیستم (طرح خطی مینی‌مال) -->
    <div class="section-header" style="margin-top: 0 !important;">📦 وضعیت اموال سیستم</div>
    <div class="list-container">
        <!-- ۱. کل اموال -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#4f46e5;"></span>
                <span class="label">کل اموال</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: 100%; background:#4f46e5;"></div>
            </div>
            <div class="val-part"><?=number_format($total)?></div>
        </div>
        <!-- ۲. اموال سالم -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#10b981;"></span>
                <span class="label">سالم</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$active_pct?>%; background:#10b981;"></div>
            </div>
            <div class="val-part" style="color:#10b981;"><?=number_format($active)?></div>
        </div>
        <!-- ۳. خراب / در تعمیر -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#f59e0b;"></span>
                <span class="label">خراب / در تعمیر</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$damaged_pct?>%; background:#f59e0b;"></div>
            </div>
            <div class="val-part" style="color:#f59e0b;"><?=number_format($damaged)?></div>
        </div>
        <!-- ۴. اسقاط -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#ef4444;"></span>
                <span class="label">اسقاط</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$retired_pct?>%; background:#ef4444;"></div>
            </div>
            <div class="val-part" style="color:#ef4444;"><?=number_format($retired)?></div>
        </div>
    </div>

    <!-- بخش دوم: وضعیت اختصاصی مرکز کاربر -->
    <?php if($my_center): ?>
    <div class="section-header">🏢 وضعیت مرکز (<?= htmlspecialchars($my_center) ?>)</div>
    <div class="list-container">
        <!-- ۱. موجودی مرکز -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#10b981;"></span>
                <span class="label">موجودی مرکز</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$center_inv_pct?>%; background:#10b981;"></div>
            </div>
            <div class="val-part" style="color:#10b981;"><?=number_format($center_inventory)?></div>
        </div>
        <!-- ۲. اموال منتقل شده -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#f97316;"></span>
                <span class="label">اموال منتقل شده</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$center_tr_pct?>%; background:#f97316;"></div>
            </div>
            <div class="val-part" style="color:#f97316;"><?=number_format($center_transferred)?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- بخش سوم: گردش و جابجایی کالاها -->
    <div class="section-header">🔄 گردش و جابجایی دارایی‌ها</div>
    <div class="list-container">
        <!-- ۱. ورود جدید -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#14b8a6;"></span>
                <span class="label">ورود جدید (۳۰ روز)</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$new_pct?>%; background:#14b8a6;"></div>
            </div>
            <div class="val-part" style="color:#14b8a6;"><?=number_format($new_assets)?></div>
        </div>
        <!-- ۲. انتقال قطعی -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#f97316;"></span>
                <span class="label">انتقال دائم</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$perm_pct?>%; background:#f97316;"></div>
            </div>
            <div class="val-part" style="color:#f97316;"><?=number_format($permanent)?></div>
        </div>
        <!-- ۳. جابجایی داخلی -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#8b5cf6;"></span>
                <span class="label">جابجایی داخلی</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$int_pct?>%; background:#8b5cf6;"></div>
            </div>
            <div class="val-part" style="color:#8b5cf6;"><?=number_format($internal)?></div>
        </div>
        <!-- ۴. اموال امانی -->
        <div class="list-row">
            <div class="info-part">
                <span class="indicator-dot" style="background:#3b82f6;"></span>
                <span class="label">اموال امانی</span>
            </div>
            <div class="progress-part">
                <div class="progress-fill" style="width: <?=$temp_pct?>%; background:#3b82f6;"></div>
            </div>
            <div class="val-part" style="color:#3b82f6;"><?=number_format($temporary)?></div>
        </div>
    </div>
    
</div>

<?php include 'includes/bottom_nav.php'; ?>

<script>
function t(){var d=document.getElementById('dd');d.style.display=d.style.display==='none'?'block':'none';}
document.addEventListener('click',function(e){if(!e.target.closest('.user-menu'))document.getElementById('dd').style.display='none';});
</script>

<?php if($popup_message): ?>
<div class="modal-overlay" id="msgPopup" style="opacity:1;visibility:visible">
<div class="modal-sheet" style="max-width:380px;text-align:center">
<div style="font-size:40px;margin-bottom:8px">📨</div>
<h3 style="margin-bottom:8px"><?=htmlspecialchars($popup_message["subject"])?></h3>
<p style="font-size:13px;color:#475569;margin-bottom:16px;line-height:1.8"><?=nl2br(htmlspecialchars($popup_message["body"]))?></p>
<div style="font-size:10px;color:#94a3b8;margin-bottom:12px">از: <?=$popup_message["sender_name"]?> | formatDate($popup_message["created_at"])?></div>
<div style="display:flex;gap:6px">
<button onclick="closeMsgPopup(<?=$popup_message["id"]?>)" class="btn btn-primary" style="flex:1">✅ متوجه شدم</button>
<a href="messages.php" class="btn btn-light" style="flex:1;text-decoration:none">📋 همه پیام‌ها</a>
</div>
</div>
</div>
<script>
function closeMsgPopup(id){
    document.getElementById("msgPopup").style.display = "none";
    fetch("mark_read.php?id=" + id);
}
</script>
<?php endif; ?>
</body>
</html>