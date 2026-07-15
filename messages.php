<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();

$db = getDB();
$role = $_SESSION["role"] ?? "viewer";
$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["fullname"];
$msg = ""; $msgType = "success";

// ارسال پیام (فقط ادمین)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["send"]) && $role === "admin") {
    $subject = trim($_POST["subject"]);
    $body = trim($_POST["body"]);
    $target = $_POST["target"] ?? "all";
    
    if($target == "all") {
        // به همه مراکز
        $centers = $db->query("SELECT DISTINCT center FROM assets WHERE center IS NOT NULL AND center != \"\"")->fetchAll();
        $ins = $db->prepare("INSERT INTO messages (sender_id, receiver_center, subject, body) VALUES (?, ?, ?, ?)");
        foreach($centers as $c) {
            $ins->execute([$user_id, $c["center"], $subject, $body]);
        }
        $msg = "✅ پیام به همه مراکز ارسال شد";
    } elseif($target == "center") {
        $center = $_POST["center"];
        $db->prepare("INSERT INTO messages (sender_id, receiver_center, subject, body) VALUES (?, ?, ?, ?)")
           ->execute([$user_id, $center, $subject, $body]);
        $msg = "✅ پیام به مرکز $center ارسال شد";
    } elseif($target == "keeper") {
        $keeper_id = $_POST["keeper_id"];
        $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)")
           ->execute([$user_id, $keeper_id, $subject, $body]);
        $msg = "✅ پیام به جمعدار ارسال شد";
    }
    $msgType = "success";
}

// علامت‌گذاری به عنوان خوانده شده
if(isset($_GET["read"]) && $role === "keeper") {
    $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND (receiver_id = ? OR receiver_center IN (SELECT DISTINCT center FROM assets WHERE recipient LIKE ?))")
       ->execute([intval($_GET["read"]), $user_id, "%$user_name%"]);
}

// دریافت پیام‌ها
if($role === "admin") {
    $messages = $db->prepare("SELECT m.*, u.fullname as sender_name FROM messages m LEFT JOIN users u ON m.sender_id = u.id WHERE m.sender_id = ? ORDER BY m.created_at DESC LIMIT 50");
    $messages->execute([$user_id]);
} elseif($role === "keeper") {
    $messages = $db->prepare("SELECT m.*, u.fullname as sender_name FROM messages m LEFT JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? OR m.receiver_center IN (SELECT DISTINCT center FROM assets WHERE recipient LIKE ?) ORDER BY m.created_at DESC LIMIT 50");
    $messages->execute([$user_id, "%$user_name%"]);
} else {
    $messages = $db->query("SELECT m.*, u.fullname as sender_name FROM messages m LEFT JOIN users u ON m.sender_id = u.id WHERE 1=0 ORDER BY m.created_at DESC");
}

// تعداد پیام‌های خوانده نشده
$unread = 0;
if($role === "keeper") {
    $ur = $db->prepare("SELECT COUNT(*) FROM messages WHERE is_read = 0 AND (receiver_id = ? OR receiver_center IN (SELECT DISTINCT center FROM assets WHERE recipient LIKE ?))");
    $ur->execute([$user_id, "%$user_name%"]);
    $unread = $ur->fetchColumn();
}

$centers = $db->query("SELECT DISTINCT center FROM assets WHERE center IS NOT NULL AND center != \"\" ORDER BY center")->fetchAll();
$keepers = $db->query("SELECT id, fullname, username FROM users WHERE role = \"keeper\" ORDER BY fullname")->fetchAll();

?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>پیام‌ها</title><link rel="stylesheet" href="css/app.css">
<style>
.msg-card{background:#fff;border-radius:14px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.msg-card.unread{border-right:3px solid #4361ee;background:#eff6ff}
.msg-subject{font-weight:700;font-size:13px;color:#0f172a;margin-bottom:4px}
.msg-body{font-size:11px;color:#64748b;margin-bottom:6px}
.msg-meta{font-size:9px;color:#94a3b8;display:flex;gap:10px}
.form-card{background:#fff;border-radius:16px;padding:16px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
textarea{width:100%;padding:10px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:12px;font-family:inherit;resize:vertical;min-height:80px}
</style>
</head>
<body>
<header class="top-bar"><a href="index.php">←</a><h1>📨 پیام‌ها <?php if($unread > 0): ?><span style="background:#ef4444;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px"><?=$unread?></span><?php endif?></h1></header>
<div class="content">

<?php if($role === "admin"): ?>
<div class="form-card">
<h3 style="margin-bottom:12px">✉️ ارسال پیام جدید</h3>
<form method="POST">
<select name="target" class="input-field" style="margin-bottom:8px" onchange="toggleTarget(this.value)">
<option value="all">📢 همه مراکز</option>
<option value="center">🏢 یک مرکز خاص</option>
<option value="keeper">👤 یک جمعدار خاص</option>
</select>
<div id="targetCenter" style="display:none;margin-bottom:8px">
<select name="center" class="input-field"><?php foreach($centers as $c):?><option value="<?=$c["center"]?>"><?=$c["center"]?></option><?php endforeach?></select>
</div>
<div id="targetKeeper" style="display:none;margin-bottom:8px">
<select name="keeper_id" class="input-field"><?php foreach($keepers as $k):?><option value="<?=$k["id"]?>"><?=$k["fullname"]?> (@<?=$k["username"]?>)</option><?php endforeach?></select>
</div>
<input name="subject" class="input-field" placeholder="موضوع پیام..." required style="margin-bottom:8px">
<textarea name="body" placeholder="متن پیام..." required></textarea>
<button name="send" class="btn btn-primary" style="margin-top:8px">📨 ارسال</button>
</form>
</div>
<?php endif?>

<?php while($m = $messages->fetch()): ?>
<div class="msg-card <?=$m["is_read"] ? "" : "unread"?>" onclick="location.href='?read=<?=$m["id"]?>'">
<div class="msg-subject"><?=htmlspecialchars($m["subject"])?></div>
<div class="msg-body"><?=nl2br(htmlspecialchars($m["body"]))?></div>
<div class="msg-meta">
<span>👤 <?=$m["sender_name"]?></span>
<span>📅 <?=formatDate($m["created_at"])?></span>
<?php if($m["receiver_center"]): ?><span>🏢 <?=$m["receiver_center"]?></span><?php endif?>
</div>
</div>
<?php endwhile?>

</div>
<?php include "includes/bottom_nav.php"; ?>
<script>
function toggleTarget(v){
    document.getElementById("targetCenter").style.display = v==="center" ? "block" : "none";
    document.getElementById("targetKeeper").style.display = v==="keeper" ? "block" : "none";
}
</script>
</body></html>