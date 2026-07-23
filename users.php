<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
checkAuth();
if(!isAdmin()) redirect('index.php');

$db = getDB();
$msg = '';

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $role = $_POST['role'];
    $center_id = $_POST['center_id'] ?: null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        if($_POST['user_id']){
            if(!empty($_POST['password'])){
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET fullname=?, role=?, center_id=?, is_active=?, password=? WHERE id=?")->execute([$fullname, $role, $center_id, $is_active, $hash, $_POST['user_id']]);
            } else {
                $db->prepare("UPDATE users SET fullname=?, role=?, center_id=?, is_active=? WHERE id=?")->execute([$fullname, $role, $center_id, $is_active, $_POST['user_id']]);
            }
            $msg = '✅ کاربر ویرایش شد';
        } else {
            $hash = password_hash($_POST['password'] ?: '123456', PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username, password, fullname, role, center_id, is_active) VALUES (?,?,?,?,?,?)")->execute([$username, $hash, $fullname, $role, $center_id, $is_active]);
            $msg = '✅ کاربر جدید اضافه شد';
        }
    } catch(Exception $e) { $msg = '❌ ' . $e->getMessage(); }
}

if(isset($_GET['toggle']) && isset($_GET['id'])){
    $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$_GET['toggle'], $_GET['id']]);
    redirect('users.php');
}

if(isset($_GET['delete']) && isset($_GET['id'])){
    $db->prepare("DELETE FROM users WHERE id=? AND username!='admin'")->execute([$_GET['id']]);
    redirect('users.php');
}

$users = $db->query("SELECT u.*, c.name as cname FROM users u LEFT JOIN centers c ON u.center_id=c.id ORDER BY u.created_at DESC");
$centers = $db->query("SELECT id, name FROM centers WHERE is_active=1");
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"><meta name="theme-color" content="#0f172a"><title>کاربران | اموال</title><link rel="stylesheet" href="css/app.css">
    <?php include 'includes/pwa.php'; ?>
<style>
.user-row{background:#fff;border-radius:12px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;justify-content:space-between;align-items:center}
.user-info{flex:1}.user-name{font-weight:700;font-size:15px}.user-meta{font-size:11px;color:#94a3b8;margin-top:2px}
.user-actions{display:flex;gap:4px;align-items:center}
.btn-icon-action{width:30px;height:30px;border-radius:50%;border:none;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;text-decoration:none}
.btn-edit{background:#fef3c7;color:#92400e}.btn-suspend{background:#fee2e2;color:#991b1b}.btn-activate{background:#d1fae5;color:#065f46}.btn-delete{background:#fecaca;color:#7f1d1d}
.badge-active{background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:10px}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:10px;font-size:10px}
</style></head>
<body>
<header class="top-bar"><a href="index.php" style="text-decoration:none;font-size:18px">→</a><h1>👥 کاربران</h1><button onclick="openModal()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer">＋</button></header>
<div class="content">
<?php if($msg):?><div class="toast <?php echo strpos($msg,'✅')!==false?'toast-success':'toast-error';?>"><?php echo $msg;?></div><?php endif?>

<?php while($u = $users->fetch()): $isAdminUser = ($u['username'] == 'admin');?>
<div class="user-row">
<div class="user-info">
<div class="user-name"><?php echo htmlspecialchars($u['fullname']);?></div>
<div class="user-meta">@<?php echo $u['username'];?> · <?php echo getRoleName($u['role']);?> · <?php echo $u['cname']?:'همه مراکز';?> · <?php echo $u['is_active']?'<span class="badge-active">فعال</span>':'<span class="badge-inactive">تعلیق</span>';?></div>
</div>

<?php if(!$isAdminUser):?>
<div class="user-actions">
    <button onclick="editUser(<?php echo $u['id'];?>)" class="btn-icon-action btn-edit" title="ویرایش">✏️</button>
    <?php if($u['is_active']):?>
    <a href="?toggle=0&id=<?php echo $u['id'];?>" class="btn-icon-action btn-suspend" title="تعلیق" onclick="return confirm('تعلیق شود؟')">⛔</a>
    <?php else:?>
    <a href="?toggle=1&id=<?php echo $u['id'];?>" class="btn-icon-action btn-activate" title="فعال‌سازی">✅</a>
    <?php endif?>
    <a href="?delete=1&id=<?php echo $u['id'];?>" class="btn-icon-action btn-delete" title="حذف" onclick="return confirm('حذف شود؟')">🗑️</a>
</div>
<?php endif?>
</div>
<?php endwhile?>
</div>

<div class="modal-overlay" id="m"><div class="modal-sheet"><div class="modal-handle"></div><h3 id="mt">➕ کاربر جدید</h3>
<form method="POST"><input type="hidden" name="user_id" id="uid">
<div class="input-group"><label>نام کاربری *</label><input name="username" id="uname" class="input-field" required></div>
<div class="input-group"><label>رمز عبور <span id="prq">*</span></label><input type="password" name="password" id="upass" class="input-field"></div>
<div class="input-group"><label>نام کامل *</label><input name="fullname" id="fname" class="input-field" required></div>
<div class="input-group"><label>نقش *</label><select name="role" id="role" class="input-field" required><option value="admin">👑 مدیر</option><option value="keeper">📦 جمعدار</option><option value="viewer">👁️ مهمان</option></select></div>
<div class="input-group"><label>مرکز</label><select name="center_id" id="cid" class="input-field"><option value="">همه مراکز</option><?php foreach($centers as $c):?><option value="<?php echo $c['id'];?>"><?php echo $c['name'];?></option><?php endforeach?></select></div>
<div class="input-group"><label><input type="checkbox" name="is_active" id="uactive" checked> فعال</label></div>
<button name="save" class="btn btn-primary" style="width:100%">💾 ذخیره</button>
<button type="button" onclick="closeModal()" class="btn btn-light" style="width:100%;margin-top:6px">انصراف</button></form></div></div>

<?php include 'includes/bottom_nav.php';?>
<script>
function openModal(){document.getElementById('mt').textContent='➕ کاربر جدید';document.getElementById('uid').value='';document.querySelector('#m form').reset();document.getElementById('upass').required=true;document.getElementById('prq').style.display='inline';document.getElementById('uname').disabled=false;document.getElementById('m').classList.add('show')}
function closeModal(){document.getElementById('m').classList.remove('show')}
async function editUser(id){try{let r=await fetch('api_get_user.php?id='+id);let u=await r.json();document.getElementById('mt').textContent='✏️ ویرایش';document.getElementById('uid').value=u.id;document.getElementById('uname').value=u.username;document.getElementById('uname').disabled=true;document.getElementById('upass').value='';document.getElementById('upass').required=false;document.getElementById('prq').style.display='none';document.getElementById('fname').value=u.fullname;document.getElementById('role').value=u.role;document.getElementById('cid').value=u.center_id||'';document.getElementById('uactive').checked=u.is_active==1;document.getElementById('m').classList.add('show')}catch(e){alert('خطا')}}
</script>
</body></html>
