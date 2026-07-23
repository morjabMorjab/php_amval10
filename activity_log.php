<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();
if(!isAdmin()) redirect("index.php");

$db = getDB();

// اگه جدول وجود نداره، بساز
$db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    username VARCHAR(50),
    fullname VARCHAR(100),
    action VARCHAR(50),
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$page = $_GET["page"] ?? 1;
$limit = 30;
$offset = ($page - 1) * $limit;

$logs = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT $limit OFFSET $offset")->fetchAll();
$total = $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>لاگ فعالیت</title><link rel="stylesheet" href="css/app.css">
<style>
.log-card{background:#fff;border-radius:14px;padding:12px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;gap:10px;align-items:flex-start}
.log-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.icon-create{background:#d1fae5;color:#065f46}.icon-update{background:#dbeafe;color:#1e40af}.icon-delete{background:#fee2e2;color:#991b1b}
.log-info{flex:1}
.log-user{font-weight:700;font-size:13px;color:#0f172a}.log-action{font-size:10px;color:#64748b;margin-top:2px}
.log-time{font-size:9px;color:#94a3b8;text-align:left;white-space:nowrap}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:12px}
.pagination a{background:#4361ee;color:#fff;padding:6px 12px;border-radius:8px;text-decoration:none;font-size:11px}
</style>
</head>
<body>
<header class="top-bar"><a href="index.php">→</a><h1>📋 لاگ فعالیت</h1></header>
<div class="content">
<?php
foreach($logs as $l): 
    $icon = $l["action"]=="create"?"✅":($l["action"]=="update"?"✏️":($l["action"]=="delete"?"🗑️":($l["action"]=="login"?"🔵":"🔴")));
    $iconClass = $l["action"]=="create"?"icon-create":($l["action"]=="update"?"icon-update":($l["action"]=="delete"?"icon-delete":"icon-create"));
?>
<div class="log-card">
<div class="log-icon <?=$iconClass?>"><?=$icon?></div>
<div class="log-info">
<div class="log-user"><?=$l["fullname"]?> (@<?=$l["username"]?>)</div>
<div class="log-action" style="font-size:11px;color:#0f172a;font-weight:500"><?=$l["details"]?></div>
</div>
<div class="log-time"><?=jalali_date("Y/m/d", strtotime($l["created_at"]))?> - <?=gmdate("H:i", strtotime($l["created_at"]) + 12600)?></div>
</div>
<?php
endforeach; ?>

<?php
if($total > $limit): ?>
<div class="pagination">
<?php
for($i=1;$i<=ceil($total/$limit);$i++): ?>
<a href="?page=<?=$i?>" style="<?=$i==$page?"background:#0f172a":""?>"><?=$i?></a>
<?php
endfor; ?>
</div>
<?php
endif; ?>
</div>
<?php
include "includes/bottom_nav.php"; ?>
</body></html>