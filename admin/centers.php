<?php
require_once '../config/database.php'; 
require_once '../includes/functions.php';
checkAuth(); 
if(!isAdmin()) redirect('../index.php');

$db = getDB(); 
$msg = ''; 
$msgType = 'success';

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    try {
        if($_POST['cid']) {
            $db->prepare("UPDATE centers SET code=?,name=?,address=?,phone=?,manager_name=?,center_type=? WHERE id=?")->execute([$_POST['code'],$_POST['name'],$_POST['address'],$_POST['phone'],$_POST['manager'],$_POST['type'],$_POST['cid']]);
        } else {
            $db->prepare("INSERT INTO centers (code,name,address,phone,manager_name,center_type) VALUES (?,?,?,?,?,?)")->execute([$_POST['code'],$_POST['name'],$_POST['address'],$_POST['phone'],$_POST['manager'],$_POST['type']]);
        }
        $msg='✅ ذخیره شد'; $msgType='success';
    } catch(Exception $e) { $msg='❌ '.$e->getMessage(); $msgType='error'; }
}

if(isset($_GET['toggle'])){ 
    $db->prepare("UPDATE centers SET is_active=? WHERE id=?")->execute([intval($_GET['toggle']), intval($_GET['id'])]); 
    redirect('centers.php'); 
}

if(isset($_GET['delete']) && isAdmin()){
    $db->prepare("DELETE FROM centers WHERE id=?")->execute([intval($_GET['delete'])]);
    redirect('centers.php');
}

// واکشی مراکز با محاسبه پویای تعداد اموال بر اساس نام متنی مرکز
$centers = $db->query("
    SELECT c.*, (SELECT COUNT(*) FROM assets a WHERE a.center = c.name) as cnt 
    FROM centers c 
    ORDER BY c.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>مراکز | اموال</title>
    <link rel="stylesheet" href="../css/app.css">
    <style>
        /* جدول مینی‌مال شیک به سبک اسپردشیت اکسل/نوشن */
        .table-container {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        th {
            background: #e9e4d9 !important;
            color: #1c1917 !important;
            font-weight: 800 !important;
            padding: 10px 8px !important;
            text-align: center !important;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        td {
            padding: 10px 8px !important;
            text-align: center !important;
            border-bottom: 1px solid #cbd5e1 !important;
            color: #000000 !important;
            font-weight: 700 !important;
            vertical-align: middle;
        }
        tr:hover {
            background: rgba(255, 255, 255, 0.02) !important;
        }
        .badge-cnt {
            background: #e9e4d9 !important;
            color: #1c1917 !important;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            display: inline-block;
        }
        .badge-type {
            background: #faf8f5 !important;
            border: 1px solid #cbd5e1 !important;
            color: #57534e !important;
            padding: 2px 6px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
        }
        .action-btn {
            background: transparent !important;
            border-radius: 8px !important;
            padding: 4px 8px !important;
            font-size: 11px !important;
            font-weight: bold !important;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<header class="top-bar" style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px !important; border-bottom:1px solid #cbd5e1 !important; background:#f4f0e6 !important;">
    <div style="display:flex; align-items:center; gap:6px;">
        <a href="../index.php" style="font-size:20px;text-decoration:none; color:#000000;">→</a>
        <h1 style="font-size:16px !important; margin:0; font-weight:800; color:#000000;">🏢 مدیریت مراکز</h1>
    </div>
    <button onclick="o()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer;font-weight:700;display:flex;align-items:center;justify-content:center;">＋</button>
</header>

<div class="content" style="padding-top: 12px !important;">
    <div id="toastContainer" class="toast-container"></div>
    
    <?php if(count($centers) > 0): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>کد</th>
                    <th>نام مرکز</th>
                    <th>نوع</th>
                    <th>تعداد اموال</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($centers as $c): 
                    $status_btn_color = $c['is_active'] ? '#dc2626' : '#059669';
                    $status_btn_text = $c['is_active'] ? '🚫' : '✅';
                ?>
                <tr>
                    <td style="font-family:sans-serif; font-size:10px; white-space:nowrap !important;"><?=htmlspecialchars($c['code'])?></td>
                    <td style="text-align:right !important; font-size:13px;"><?=htmlspecialchars($c['name'])?></td>
                    <td><span class="badge-type"><?=htmlspecialchars($c['center_type'] == 'main' ? 'اصلی' : ($c['center_type'] == 'branch' ? 'شعبه' : ($c['center_type'] == 'department' ? 'بخش' : 'انبار')))?></span></td>
                    <td><span class="badge-cnt"><?=number_format($c['cnt'])?> مال</span></td>
                    <td>
                        <div style="display:flex; gap:4px; justify-content:center;">
                            <button onclick="e(<?=$c['id']?>)" class="action-btn" style="border:1.5px solid #d97706 !important; color:#d97706 !important;" title="ویرایش">✏️</button>
                            <a href="?toggle=<?=$c['is_active']?0:1?>&id=<?=$c['id']?>" class="action-btn" style="border:1.5px solid <?=$status_btn_color?> !important; color:<?=$status_btn_color?> !important;" title="تغییر وضعیت"><?=$status_btn_text?></a>
                            <a href="#" onclick="confirmDelete(<?=$c['id']?>,'<?=$c['name']?>')" class="action-btn" style="border:1.5px solid #dc2626 !important; color:#dc2626 !important;" title="حذف">🗑️</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:60px;color:#94a3b8">📭 هیچ مرکزی تعریف نشده است</div>
    <?php endif; ?>
</div>

<!-- مودال ساخت/ویرایش مرکز با چیدمان گرید متقارن -->
<div class="modal-overlay" id="m"><div class="modal-sheet" style="max-width:500px !important; padding:22px 18px;">
    <h3 id="mt">🏢 مرکز جدید</h3>
    <form method="POST" id="centerForm">
        <input type="hidden" name="cid" id="cid">
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
            <div class="input-group" style="margin:0;">
                <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">کد مرکز <span style="color:#ef4444">*</span></label>
                <input name="code" id="code" class="input-field" placeholder="کد مرکز" required style="width:100% !important;">
            </div>
            <div class="input-group" style="margin:0;">
                <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">نام مرکز <span style="color:#ef4444">*</span></label>
                <input name="name" id="name" class="input-field" placeholder="نام مرکز" required style="width:100% !important;">
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
            <div class="input-group" style="margin:0;">
                <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">نوع مرکز *</label>
                <select name="type" id="type" class="input-field" style="width:100% !important;">
                    <option value="main">🏢 اصلی</option>
                    <option value="branch">🏪 شعبه</option>
                    <option value="department">📂 بخش</option>
                    <option value="warehouse">📦 انبار</option>
                </select>
            </div>
            <div class="input-group" style="margin:0;">
                <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">تلفن تماس</label>
                <input name="phone" id="phone" class="input-field" placeholder="تلفن" style="width:100% !important;">
            </div>
        </div>

        <div class="input-group" style="margin-bottom:10px;">
            <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">آدرس مرکز</label>
            <textarea name="address" id="address" class="input-field" rows="2" placeholder="آدرس فیزیکی" style="width:100% !important; resize:none !important;"></textarea>
        </div>

        <div class="input-group" style="margin-bottom:16px;">
            <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">مدیر مسئول مرکز</label>
            <input name="manager" id="manager" class="input-field" placeholder="مدیر مسئول" style="width:100% !important;">
        </div>
        
        <div style="display:flex;gap:6px;">
            <button name="save" class="btn btn-primary" style="flex:1">💾 ذخیره</button>
            <button type="button" onclick="c()" class="btn btn-light" style="flex:1;">انصراف</button>
        </div>
    </form>
</div></div>

<?php include '../includes/bottom_nav.php'; ?>

<script>
function showToast(msg,type){var c=document.getElementById('toastContainer');var e=document.createElement('div');e.className='toast-msg toast-'+type;e.textContent=msg;c.appendChild(e);setTimeout(function(){e.style.animation='toastOut .3s ease forwards';setTimeout(function(){e.remove()},300)},2500)}
function confirmDelete(id,name){var c=document.getElementById('toastContainer');var e=document.createElement('div');e.className='toast-msg toast-confirm';e.innerHTML='<div>🗑️ حذف «'+name+'»؟</div><div class="toast-confirm-buttons"><button class="toast-btn-yes" id="btnYes">حذف</button><button class="toast-btn-no" id="btnNo">انصراف</button></div>';c.appendChild(e);document.getElementById('btnYes').onclick=function(){window.location.href='?delete='+id};document.getElementById('btnNo').onclick=function(){e.remove()}}
function o(){document.getElementById('mt').textContent='🏢 مرکز جدید';document.getElementById('cid').value='';document.querySelector('#centerForm').reset();document.getElementById('m').classList.add('show')}
function c(){document.getElementById('m').classList.remove('show')}
async function e(id){try{let r=await fetch('../api_get_center.php?id='+id);let d=await r.json();document.getElementById('mt').textContent='✏️ ویرایش مرکز';document.getElementById('cid').value=d.id;document.getElementById('code').value=d.code;document.getElementById('name').value=d.name;document.getElementById('type').value=d.center_type;document.getElementById('address').value=d.address||'';document.getElementById('phone').value=d.phone||'';document.getElementById('manager').value=d.manager_name||'';document.getElementById('m').classList.add('show')}catch(e){showToast('خطا در دریافت اطلاعات','error')}}
document.getElementById('m').addEventListener('click',function(e){if(e.target===this)c()});
<?php if($msg):?>showToast('<?=$msg?>','<?=$msgType?>');<?php endif?>
</script>
</body></html>