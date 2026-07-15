
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
?>

<?php
date_default_timezone_set('Asia/Tehran');
// پاپ‌آپ پیام برای جمعدار
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
    <?php
date_default_timezone_set('Asia/Tehran'); include 'includes/pwa.php'; ?>
    <title>داشبورد | اموال</title>
    <link rel="stylesheet" href="css/app.css">
    <style>
        .stats-line {
            display: flex; align-items: center; justify-content: space-between;
            padding: 13px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px;
        }
        .stats-line:last-child { border-bottom: none; }
        .stats-line .lbl { color: #64748b; display: flex; align-items: center; gap: 8px; }
        .stats-line .val { font-weight: 700; color: #0f172a; }
        .dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .dot-blue { background: #4361ee; } .dot-green { background: #10b981; }
        .dot-red { background: #ef4444; } .dot-yellow { background: #f59e0b; }
        .dot-gray { background: #94a3b8; } .dot-purple { background: #8b5cf6; }
        .dot-orange { background: #f97316; } .dot-teal { background: #14b8a6; }
        .section-title {
            font-size: 12px; font-weight: 700; color: #94a3b8; 
            padding: 16px 16px 8px; text-transform: uppercase; letter-spacing: 1px;
        }
    
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);z-index:300;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:.25s}
.modal-overlay.show{opacity:1;visibility:visible}
.modal-sheet{background:#fff;border-radius:20px;width:calc(100% - 40px);max-width:380px;max-height:70vh;overflow-y:auto;padding:20px;transform:scale(.9);transition:transform .3s ease;box-shadow:0 25px 80px rgba(0,0,0,.25);margin:16px}
</style>
</head>
<body>

<header class="top-bar">
    <div>
        <div style="font-size:12px;color:#94a3b8;"><?=$greeting?> 👋</div>
        <div style="font-weight:700;"><?=$_SESSION['fullname']?></div>
    </div>
    <div class="user-menu" onclick="t()" style="cursor:pointer;position:relative;">
        <div class="user-avatar"><?=mb_substr($_SESSION['fullname'],0,1)?></div>
        <div id="dd" style="display:none;position:absolute;top:48px;left:0;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);padding:8px;min-width:160px;z-index:200;">
            <a href="messages.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;">📨 پیام‌ها</a>
            <a href="profile.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;">👤 پروفایل</a>
            <?php
date_default_timezone_set('Asia/Tehran'); if($role=='admin'):?>
            <a href="admin/users.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;">👥 کاربران</a>
            <a href="admin/centers.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;">🏢 مراکز</a>
            <a href="admin/sql.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;">🔧 کنسول SQL</a>
            <a href="activity_log.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#1e293b;border-radius:8px;font-size:13px;">📋 لاگ فعالیت</a>
            <?php
date_default_timezone_set('Asia/Tehran'); endif;?>
            <hr style="border:none;border-top:1px solid #e2e8f0;margin:4px 0;">
            <a href="logout.php" style="display:flex;align-items:center;gap:8px;padding:10px 12px;text-decoration:none;color:#dc2626;border-radius:8px;font-size:13px;">🚪 خروج</a>
        </div>
    </div>
</header>

<div class="content">
    
    <div style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04);">
        
        <div class="section-title">📦 وضعیت اموال</div>
        
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-blue"></span> کل اموال</span>
            <span class="val"><?=number_format($total)?></span>
        </div>
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-green"></span> سالم</span>
            <span class="val" style="color:#10b981;"><?=number_format($active)?></span>
        </div>
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-yellow"></span> خراب / در تعمیر</span>
            <span class="val" style="color:#f59e0b;"><?=number_format($damaged)?></span>
        </div>
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-gray"></span> اسقاط</span>
            <span class="val" style="color:#94a3b8;"><?=number_format($retired)?></span>
        </div>
        
        <div class="section-title">🔄 آمار جابجایی</div>
        
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-teal"></span> ورود اموال جدید (۳۰ روز)</span>
            <span class="val" style="color:#14b8a6;"><?=number_format($new_assets)?></span>
        </div>
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-purple"></span> جابجایی داخلی</span>
            <span class="val" style="color:#8b5cf6;"><?=number_format($internal)?></span>
        </div>
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-orange"></span> انتقال به مرکز دیگر</span>
            <span class="val" style="color:#f97316;"><?=number_format($permanent)?></span>
        </div>
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-blue"></span> امانی</span>
            <span class="val"><?=number_format($temporary)?></span>
        </div>
        <div class="stats-line">
            <span class="lbl"><span class="dot dot-red"></span> خروج برای تعمیر</span>
            <span class="val" style="color:#ef4444;"><?=number_format($repair)?></span>
        </div>
        
    </div>
    
</div>

<?php
date_default_timezone_set('Asia/Tehran'); include 'includes/bottom_nav.php'; ?>

<script>
function t(){var d=document.getElementById('dd');d.style.display=d.style.display==='none'?'block':'none';}
document.addEventListener('click',function(e){if(!e.target.closest('.user-menu'))document.getElementById('dd').style.display='none';});
</script>

<?php
date_default_timezone_set('Asia/Tehran'); if($popup_message): ?>
<div class="modal-overlay" id="msgPopup" style="opacity:1;visibility:visible">
<div class="modal-sheet" style="max-width:380px;text-align:center">
<div style="font-size:40px;margin-bottom:8px">📨</div>
<h3 style="margin-bottom:8px"><?=htmlspecialchars($popup_message["subject"])?></h3>
<p style="font-size:13px;color:#475569;margin-bottom:16px;line-height:1.8"><?=nl2br(htmlspecialchars($popup_message["body"]))?></p>
<div style="font-size:10px;color:#94a3b8;margin-bottom:12px">از: <?=$popup_message["sender_name"]?> | <?=formatDate($popup_message["created_at"])?></div>
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
<?php
date_default_timezone_set('Asia/Tehran'); endif; ?>
</body>
</html>