<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();
$role = $_SESSION["role"] ?? "viewer";

$db = getDB();
$msg = ""; $msgType = "success";

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["save_profile"])) {
    $f = $_POST["fullname"];
    $old = $db->prepare("SELECT fullname FROM users WHERE id = ?");
    $old->execute([$_SESSION["user_id"]]);
    $oldName = $old->fetchColumn();
    $db->prepare("UPDATE users SET fullname = ? WHERE id = ?")->execute([$f, $_SESSION["user_id"]]);
    $_SESSION["fullname"] = $f;
    if($oldName != $f) $db->prepare("UPDATE assets SET recipient = ? WHERE recipient = ?")->execute([$f, $oldName]);
    $msg = "✅ ذخیره شد";
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_pass"])) {
    $old = $_POST["old_pass"]; $new = $_POST["new_pass"]; $rep = $_POST["rep_pass"];
    if($new !== $rep) { $msg = "❌ رمز جدید مطابقت ندارد"; $msgType = "error"; }
    elseif(strlen($new) < 6) { $msg = "❌ حداقل ۶ کاراکتر"; $msgType = "error"; }
    else {
        $u = $db->prepare("SELECT password FROM users WHERE id = ?");
        $u->execute([$_SESSION["user_id"]]);
        if(password_verify($old, $u->fetch()["password"])) {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION["user_id"]]);
            $msg = "✅ رمز تغییر کرد";
        } else { $msg = "❌ رمز فعلی اشتباه است"; $msgType = "error"; }
    }
}

$u = $db->prepare("SELECT u.*, c.name as center_name FROM users u LEFT JOIN centers c ON u.center_id = c.id WHERE u.id = ?");
$u->execute([$_SESSION["user_id"]]);
$user = $u->fetch();

?><!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>پروفایل</title><link rel="stylesheet" href="css/app.css">
<style>
body{background:#f1f5f9}
.content{padding:12px;max-width:400px;margin:0 auto}
.avatar-row{display:flex;align-items:center;gap:10px;padding:10px 0}
.avatar-icon{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;flex-shrink:0}
.avatar-name{font-size:14px;font-weight:700;color:#0f172a}
.avatar-meta{font-size:10px;color:#64748b;margin-top:2px}
.card{background:#fff;border-radius:14px;padding:10px 12px;margin-bottom:6px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.card h3{font-size:11px;font-weight:700;color:#0f172a;margin-bottom:6px}
.inp{width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:11px;font-family:inherit;background:#f8fafc;outline:none;margin-bottom:4px}
.inp:focus{border-color:#4361ee;background:#fff}
.btn-sm{padding:5px 10px;border-radius:6px;border:none;font-size:11px;font-weight:600;cursor:pointer}
.btn-blue{background:#4361ee;color:#fff}.btn-blue:active{transform:scale(.96)}
.btn-red{display:block;text-align:center;padding:10px;background:#fff;color:#dc2626;border:1.5px solid #fecaca;border-radius:12px;text-decoration:none;font-weight:600;font-size:11px;margin-top:6px}
.flex-row{display:flex;gap:4px;align-items:center}
</style>
</head>
<body>
<header class="top-bar"><a href="index.php">→</a><h1>پروفایل</h1></header>
<div class="content">
<?php if($msg):?><div class="toast <?=$msgType=="error"?"toast-error":"toast-success"?>" style="margin-bottom:8px;font-size:11px"><?=$msg?></div><?php endif?>

<div class="avatar-row">
<div class="avatar-icon"><?=mb_substr($user["fullname"],0,1)?></div>
<div>
<div class="avatar-name"><?=$user["fullname"]?></div>
<div class="avatar-meta"><?=getRoleName($user["role"])?> · @<?=$user["username"]?><?php if($user["center_name"]):?> · 🏢 <?=$user["center_name"]?><?php endif?></div>
</div>
</div>

<div class="card">
<h3>✏️ ویرایش اطلاعات</h3>
<form method="POST"><div class="flex-row">
<input name="fullname" class="inp" value="<?=htmlspecialchars($user["fullname"])?>" placeholder="نام کامل" style="flex:1;margin:0">
<button name="save_profile" class="btn-sm btn-blue">💾 ذخیره</button>
</div></form>
</div>

<div class="card">
<h3>🔒 تغییر رمز</h3>
<form method="POST">
<input type="password" name="old_pass" class="inp" placeholder="رمز فعلی">
<input type="password" name="new_pass" class="inp" placeholder="رمز جدید">
<input type="password" name="rep_pass" class="inp" placeholder="تکرار رمز">
<div class="flex-row"><button name="change_pass" class="btn-sm btn-blue">🔒 تغییر رمز</button></div>
</form>
</div>

<a href="logout.php" class="btn-red">🚪 خروج از حساب کاربری</a>

</div>
<?php include "includes/bottom_nav.php"; ?>
</body></html>