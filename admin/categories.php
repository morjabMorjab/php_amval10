<?php
require_once '../config/database.php'; require_once '../includes/functions.php';
checkAuth(); if(!isAdmin()) redirect('../index.php');
$db = getDB(); $msg = '';

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    try {
        if($_POST['cat_id']) $db->prepare("UPDATE categories SET code=?,name=?,parent_id=? WHERE id=?")->execute([$_POST['code'],$_POST['name'],$_POST['parent_id']?:null,$_POST['cat_id']]);
        else $db->prepare("INSERT INTO categories (code,name,parent_id) VALUES (?,?,?)")->execute([$_POST['code'],$_POST['name'],$_POST['parent_id']?:null]);
        $msg='✅ ذخیره شد';
    } catch(Exception $e) { $msg='❌ '.$e->getMessage(); }
}

$cats = $db->query("SELECT c.*, p.name as parent_name, COUNT(a.id) as cnt FROM categories c LEFT JOIN categories p ON c.parent_id=p.id LEFT JOIN assets a ON c.id=a.center_id GROUP BY c.id ORDER BY c.name");
?><!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>دسته‌بندی | اموال</title><link rel="stylesheet" href="../css/app.css"></head>
<body>
<header class="top-bar"><a href="../index.php" style="text-decoration:none;font-size:18px">←</a><h1>📂 دسته‌بندی</h1><button onclick="o()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer">＋</button></header>
<div class="content">
<?php if($msg):?><div class="toast <?=strpos($msg,'✅')!==false?'toast-success':'toast-error'?>"><?=$msg?></div><?php endif?>
<?php foreach($cats as $c):?>
<div style="background:#fff;border-radius:12px;padding:14px;margin-bottom:8px;box-shadow:0 1px 3px rgba(0,0,0,.04);display:flex;justify-content:space-between;align-items:center">
<div><div style="font-weight:700"><?=$c['name']?></div><div style="font-size:11px;color:#94a3b8"><?=$c['code']?> · <?=$c['parent_name']?:'اصلی'?></div></div>
<button onclick="e(<?=$c['id']?>)" style="background:#fef3c7;color:#92400e;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px">✏️</button>
</div>
<?php endforeach; ?>
</div>

<div class="modal-overlay" id="m"><div class="modal-sheet"><div class="modal-handle"></div><h3 id="mt">📂 دسته جدید</h3>
<form method="POST"><input type="hidden" name="cat_id" id="cid">
<div class="input-group"><label>کد *</label><input name="code" id="code" class="input-field" required></div>
<div class="input-group"><label>نام *</label><input name="name" id="name" class="input-field" required></div>
<div class="input-group"><label>والد</label><select name="parent_id" id="pid" class="input-field"><option value="">—</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>"><?=$c['name']?></option><?php endforeach?></select></div>
<button name="save" class="btn btn-primary" style="width:100%">💾 ذخیره</button>
<button type="button" onclick="c()" class="btn btn-light" style="width:100%;margin-top:6px">انصراف</button></form></div></div>

<?php include '../includes/bottom_nav.php'; ?>
<script>
function o(){document.getElementById('mt').textContent='📂 دسته جدید';document.getElementById('cid').value='';document.querySelector('#m form').reset();document.getElementById('m').classList.add('show')}
function c(){document.getElementById('m').classList.remove('show')}
async function e(id){try{let r=await fetch('../api_get_category.php?id='+id);let d=await r.json();document.getElementById('mt').textContent='✏️ ویرایش';document.getElementById('cid').value=d.id;document.getElementById('code').value=d.code;document.getElementById('name').value=d.name;document.getElementById('pid').value=d.parent_id||'';document.getElementById('m').classList.add('show')}catch(e){alert('خطا')}}
</script>
</body></html>
