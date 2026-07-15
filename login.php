<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
if(isLoggedIn()) redirect('index.php');
$error = '';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $db = getDB();
    if($db){
        $stmt = $db->prepare("SELECT * FROM users WHERE username=? AND is_active=1");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();
        if($user && password_verify($_POST['password'], $user['password'])){
            $_SESSION['user_id']=$user['id']; $_SESSION['username']=$user['username'];
$_SESSION['fullname']=$user['fullname']; $_SESSION['role']=$user['role']; $_SESSION['username']=$user['username'];
            try {
                $db->prepare('INSERT INTO activity_logs (user_id, username, fullname, action, details) VALUES (?,?,?,?,?)')->execute([$user['id'], $user['username'], $user['fullname'], 'login', 'ورود به سیستم']);
            } catch(Exception $e) {}
            $_SESSION['center_id']=$user['center_id'];
            redirect('index.php');
        } else $error = 'نام کاربری یا رمز عبور اشتباه است';
    } else $error = 'خطا در اتصال به دیتابیس';
}
?><!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"><meta name="theme-color" content="#0f172a"><title>ورود | اموال</title>
    <?php
date_default_timezone_set('Asia/Tehran'); include 'includes/pwa.php'; ?>
<style>
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'BNazanin',Tahoma,sans-serif;background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;direction:rtl}.c{width:100%;max-width:380px}.h{text-align:center;margin-bottom:28px}.h .icon{width:68px;height:68px;background:linear-gradient(135deg,#4361ee,#7209b7);border-radius:20px;display:inline-flex;align-items:center;justify-content:center;font-size:34px;margin-bottom:12px;box-shadow:0 8px 32px rgba(67,97,238,.3)}.h h1{color:#f1f5f9;font-size:22px;font-weight:900}.card{background:#1e293b;border-radius:20px;padding:28px 22px;border:1px solid #334155}.inp{margin-bottom:16px}.inp label{display:block;color:#94a3b8;font-size:12px;font-weight:500;margin-bottom:6px}.inp input{width:100%;padding:13px 14px;background:#0f172a;border:2px solid #334155;border-radius:12px;color:#f1f5f9;font-size:15px;font-family:inherit;outline:none;transition:.2s}.inp input:focus{border-color:#4361ee;box-shadow:0 0 0 3px rgba(67,97,238,.1)}.err{background:#451a1a;color:#fca5a5;padding:10px 14px;border-radius:10px;font-size:12px;margin-bottom:14px;text-align:center;border:1px solid #7f1d1d}.btn{width:100%;padding:14px;background:linear-gradient(135deg,#4361ee,#3b82f6);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 4px 16px rgba(67,97,238,.3)}.btn:active{transform:scale(.97)}.ft{text-align:center;margin-top:16px;color:#475569;font-size:12px}</style></head>
<body><div class="c"><div class="h"><div class="icon">🏢</div><h1>مدیریت اموال</h1></div><div class="card"><?php
date_default_timezone_set('Asia/Tehran'); if($error):?><div class="err">⚠️ <?=$error?></div><?php
date_default_timezone_set('Asia/Tehran'); endif?><form method="POST"><div class="inp"><label>نام کاربری</label><input name="username" placeholder="admin" required autofocus></div><div class="inp"><label>رمز عبور</label><input type="password" name="password" placeholder="••••••••" required></div><button class="btn">🚀 ورود</button></form></div></div></body></html>
