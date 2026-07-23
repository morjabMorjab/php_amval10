<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
checkAuth();
if(!isAdmin()) redirect('index.php');

$db = getDB();
$msg = '';

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')){
        die('درخواست نامعتبر');
    }
    $code=trim($_POST['code']); $name=trim($_POST['name']); $type=$_POST['type'];
    $address=trim($_POST['address']); $phone=trim($_POST['phone']); $manager=trim($_POST['manager']);
    try {
        if($_POST['center_id']){
            $db->prepare("UPDATE centers SET code=?,name=?,center_type=?,address=?,phone=?,manager_name=? WHERE id=?")->execute([$code,$name,$type,$address,$phone,$manager,intval($_POST['center_id'])]);
            $msg='✅ مرکز ویرایش شد';
        } else {
            $db->prepare("INSERT INTO centers (code,name,center_type,address,phone,manager_name) VALUES (?,?,?,?,?,?)")->execute([$code,$name,$type,$address,$phone,$manager]);
            $msg='✅ مرکز جدید اضافه شد';
        }
    } catch(Exception $e) { $msg='❌ '.$e->getMessage(); }
}

if(isset($_GET['toggle'])&&isset($_GET['id'])){
    $db->prepare("UPDATE centers SET is_active=? WHERE id=?")->execute([intval($_GET['toggle']),intval($_GET['id'])]);
    redirect('centers.php');
}
if(isset($_GET['delete'])&&isset($_GET['id'])){
    $cnt=$db->prepare("SELECT COUNT(*) FROM assets WHERE center_id=?"); $cnt->execute([intval($_GET['id'])]);
    if($cnt->fetchColumn()==0){ $db->prepare("DELETE FROM centers WHERE id=?")->execute([intval($_GET['id'])]); $msg='✅ مرکز حذف شد'; }
    else $msg='❌ این مرکز اموال دارد';
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$centers=$db->query("SELECT c.*,COUNT(a.id) as cnt FROM centers c LEFT JOIN assets a ON c.id=a.center_id GROUP BY c.id ORDER BY c.name");
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"><meta name="theme-color" content="#0f172a"><title>مراکز | اموال</title><link rel="stylesheet" href="css/app.css">
    <?php include 'includes/pwa.php'; ?>
<style>
.btn-circle{width:30px;height:30px;border-radius:50%;border:none;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
.btn-add{background:#4361ee;color:#fff;font-size:18px}
.btn-edit{background:#fef3c7;color:#92400e}.btn-suspend{background:#fee2e2;color:#991b1b}.btn-activate{background:#d1fae5;color:#065f46}.btn-delete{background:#fecaca;color:#7f1d1d}
.badge-active{background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:10px}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:10px;font-size:10px}
.cnt{background:#4361ee;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
</style></head>
<body>
<header class="top-bar"><a href="index.php" style="text-decoration:none;font-size:18px">→</a><h1>🏢 مراکز</h1><button onclick="openModal()" class="btn-circle btn-add" title="افزودن مرکز جدید" aria-label="افزودن مرکز جدید">＋</button></header>
<div class="content">
<?php if($msg):?><div class="toast <?php echo strpos($msg,'✅')!==false?'toast-success':'toast-error';?>"><?php echo htmlspecialchars($msg);?></div>

<?php while($c=$centers->fetch()):?>
<div style="background:#fff;border-radius:12px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;justify-content:space-between;align-items:center">
<div>
<div style="font-weight:700;font-size:15px"><?php echo htmlspecialchars($c['name']);?></div>
<div style="font-size:11px;color:#94a3b8;margin-top:2px"><?php echo htmlspecialchars($c['code']);?> · <?php echo htmlspecialchars($c['center_type']);?> · <?php echo htmlspecialchars($c['manager_name']?:'بدون مدیر');?> · <span class="cnt"><?php echo (int)$c['cnt'];?> مال</span> <?php echo $c['is_active']?'<span class="badge-active">فعال</span>':'<span class="badge-inactive">تعلیق</span>';?></div>
</div>
<div style="display:flex;gap:10px">
    <button onclick="editCenter(<?php echo (int)$c['id'];?>)" class="btn-circle btn-edit" title="ویرایش مرکز" aria-label="ویرایش">✏️</button>
    <?php if($c['is_active']):?>
    <a href="?toggle=0&id=<?php echo (int)$c['id'];?>" class="btn-circle btn-suspend" title="تعلیق مرکز" aria-label="تعلیق" onclick="return confirm('مرکز تعلیق شود؟')">⛔</a>
    <?php else:?>
    <a href="?toggle=1&id=<?php echo (int)$c['id'];?>" class="btn-circle btn-activate" title="فعال‌سازی مرکز" aria-label="فعال‌سازی">✅</a>
    
    
    <a href="?delete=1&id=<?php echo (int)$c['id'];?>" class="btn-circle btn-delete" title="حذف مرکز" aria-label="حذف" onclick="return confirm('حذف شود؟')">🗑️</a>
    
</div>
</div>
<?php endwhile?>
</div>

<div class="modal-overlay" id="m"><div class="modal-sheet"><div class="modal-handle"></div><h3 id="mt">🏢 مرکز جدید</h3>
<form method="POST"><input type="hidden" name="center_id" id="cid"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'];?>">
<div class="input-group"><label>کد *</label><input name="code" id="code" class="input-field" required></div>
<div class="input-group"><label>نام *</label><input name="name" id="name" class="input-field" required></div>
<div class="input-group"><label>نوع</label><select name="type" id="type" class="input-field"><option value="main">اصلی</option><option value="branch">شعبه</option><option value="department">بخش</option><option value="warehouse">انبار</option></select></div>
<div class="input-group"><label>آدرس</label><textarea name="address" id="address" class="input-field" rows="2"></textarea></div>
<div class="input-group"><label>تلفن</label><input name="phone" id="phone" class="input-field"></div>
<div class="input-group"><label>مدیر</label><input name="manager" id="manager" class="input-field"></div>
<button name="save" class="btn btn-primary" style="width:100%">💾 ذخیره</button>
<button type="button" onclick="closeModal()" class="btn btn-light" style="width:100%;margin-top:6px">انصراف</button></form></div></div>

<?php include 'includes/bottom_nav.php';?>
<script>
function openModal(){document.getElementById('mt').textContent='🏢 مرکز جدید';document.getElementById('cid').value='';document.querySelector('#m form').reset();document.getElementById('m').classList.add('show')}
function closeModal(){document.getElementById('m').classList.remove('show')}
async function editCenter(id){try{let r=await fetch('api_get_center.php?id='+id);let c=await r.json();document.getElementById('mt').textContent='✏️ ویرایش';document.getElementById('cid').value=c.id;document.getElementById('code').value=c.code;document.getElementById('name').value=c.name;document.getElementById('type').value=c.center_type;document.getElementById('address').value=c.address||'';document.getElementById('phone').value=c.phone||'';document.getElementById('manager').value=c.manager_name||'';document.getElementById('m').classList.add('show')}catch(e){alert('خطا')}}
</script>
</body></html>
