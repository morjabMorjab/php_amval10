<?php
require_once '../config/database.php'; require_once '../includes/functions.php';
checkAuth(); if(!isAdmin()) redirect('../index.php');$db = getDB(); $msg = ''; $msgType = 'success';

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    try {
        if($_POST['cid']) $db->prepare("UPDATE centers SET code=?,name=?,address=?,phone=?,manager_name=?,center_type=? WHERE id=?")->execute([$_POST['code'],$_POST['name'],$_POST['address'],$_POST['phone'],$_POST['manager'],$_POST['type'],$_POST['cid']]);
        else $db->prepare("INSERT INTO centers (code,name,address,phone,manager_name,center_type) VALUES (?,?,?,?,?,?)")->execute([$_POST['code'],$_POST['name'],$_POST['address'],$_POST['phone'],$_POST['manager'],$_POST['type']]);
        $msg='✅ ذخیره شد'; $msgType='success';
    } catch(Exception $e) { $msg='❌ '.$e->getMessage(); $msgType='error'; }
}
if(isset($_GET['toggle'])){ $db->prepare("UPDATE centers SET is_active=? WHERE id=?")->execute([$_GET['toggle'],$_GET['id']]); redirect('centers.php'); }
if(isset($_GET['delete']) && isAdmin()){
    $db->prepare("DELETE FROM centers WHERE id=?")->execute([$_GET['delete']]);
    redirect('centers.php');
}

$centers = $db->query("SELECT * FROM centers ORDER BY name");
?><!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>مراکز | اموال</title><link rel="stylesheet" href="../css/app.css"></head>
<body>
<header class="top-bar"><a href="../index.php" style="text-decoration:none;font-size:18px">←</a><h1>🏢 مراکز</h1><button onclick="o()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer">＋</button></header>
<div class="content">
<div id="toastContainer" class="toast-container"></div>
<?php foreach($centers as $c):?>
<div style="background:#fff;border-radius:12px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
<div style="font-weight:700;font-size:14px;"><?=$c['name']?></div>
<div style="display:flex;gap:4px;flex-shrink:0;">
<button onclick="e(<?=$c['id']?>)" style="background:#fef3c7;color:#92400e;border:none;padding:3px 8px;border-radius:6px;cursor:pointer;font-size:10px;">✏️</button>
<a href="?toggle=<?=$c['is_active']?0:1?>&id=<?=$c['id']?>" style="background:<?=$c['is_active']?'#fee2e2':'#d1fae5'?>;color:<?=$c['is_active']?'#991b1b':'#065f46'?>;padding:3px 8px;border-radius:6px;text-decoration:none;font-size:10px;"><?=$c['is_active']?'🚫':'✅'?></a>
<a href="#" onclick="confirmDelete(<?=$c['id']?>,'<?=$c['name']?>')" style="background:#fecaca;color:#dc2626;padding:3px 8px;border-radius:6px;text-decoration:none;font-size:10px;font-weight:700;">حذف</a>
</div>
</div>
<div style="font-size:10px;color:#94a3b8;"><?=$c['code']?> · <?=$c['center_type']?> · <?=$c['manager_name']?:'—'?></div>
</div>
<?php endforeach; ?>
</div>

<div class="modal-overlay" id="m"><div class="modal-sheet"><div class="modal-handle"></div><h3 id="mt">🏢 مرکز جدید</h3>
<form method="POST" id="centerForm"><input type="hidden" name="cid" id="cid">
<div class="input-group"><input name="code" id="code" class="input-field" placeholder="کد مرکز" required></div>
<div class="input-group"><input name="name" id="name" class="input-field" placeholder="نام مرکز" required></div>
<div class="input-group"><select name="type" id="type" class="input-field"><option value="main">🏢 اصلی</option><option value="branch">🏪 شعبه</option><option value="department">📂 بخش</option><option value="warehouse">📦 انبار</option></select></div>
<div class="input-group"><textarea name="address" id="address" class="input-field" rows="2" placeholder="آدرس"></textarea></div>
<div class="input-group"><input name="phone" id="phone" class="input-field" placeholder="تلفن"></div>
<div class="input-group"><input name="manager" id="manager" class="input-field" placeholder="مدیر مرکز"></div>
<button name="save" class="btn btn-primary">💾 ذخیره</button>
<button type="button" onclick="c()" class="btn btn-light" style="margin-top:8px;">انصراف</button></form></div></div>

<?php include '../includes/bottom_nav.php'; ?>
<script>
function showToast(msg,type){var c=document.getElementById('toastContainer');var e=document.createElement('div');e.className='toast-msg toast-'+type;e.textContent=msg;c.appendChild(e);setTimeout(function(){e.style.animation='toastOut .3s ease forwards';setTimeout(function(){e.remove()},300)},2500)}
function confirmDelete(id,name){var c=document.getElementById('toastContainer');var e=document.createElement('div');e.className='toast-msg toast-confirm';e.innerHTML='<div>🗑️ حذف «'+name+'»؟</div><div class="toast-confirm-buttons"><button class="toast-btn-yes" id="btnYes">حذف</button><button class="toast-btn-no" id="btnNo">انصراف</button></div>';c.appendChild(e);document.getElementById('btnYes').onclick=function(){window.location.href='?delete='+id};document.getElementById('btnNo').onclick=function(){e.remove()}}
function o(){document.getElementById('mt').textContent='🏢 مرکز جدید';document.getElementById('cid').value='';document.querySelector('#centerForm').reset();document.getElementById('m').classList.add('show')}
function c(){document.getElementById('m').classList.remove('show')}
async function e(id){try{let r=await fetch('../api_get_center.php?id='+id);let d=await r.json();document.getElementById('mt').textContent='✏️ ویرایش مرکز';document.getElementById('cid').value=d.id;document.getElementById('code').value=d.code;document.getElementById('name').value=d.name;document.getElementById('type').value=d.center_type;document.getElementById('address').value=d.address||'';document.getElementById('phone').value=d.phone||'';document.getElementById('manager').value=d.manager_name||'';document.getElementById('m').classList.add('show')}catch(e){showToast('خطا','error')}}
document.getElementById('m').addEventListener('click',function(e){if(e.target===this)c()});
<?php if($msg):?>showToast('<?=$msg?>','<?=$msgType?>');<?php endif?>
</script>
</body></html>