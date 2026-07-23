<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth(); 
if(!isAdmin()) redirect('../index.php');

$db = getDB(); 
$msg = ''; 
$msgType = 'success';

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save'])){
    $u = trim($_POST['username']); 
    $f = trim($_POST['fullname']); 
    $r = $_POST['role'];
    $cid = $_POST['center_id'] ?: null; 
    $active = isset($_POST['is_active']) ? 1 : 0;
    try {
        if($_POST['user_id']){
            // گرفتن نام قدیمی قبل از آپدیت
            $oldStmt = $db->prepare("SELECT fullname FROM users WHERE id = ?");
            $oldStmt->execute([$_POST['user_id']]);
            $oldRow = $oldStmt->fetch();
            $oldFullname = $oldRow ? $oldRow['fullname'] : "";
            
            // آپدیت کاربر
            $db->prepare("UPDATE users SET username=?, fullname=?, role=?, center_id=?, is_active=? WHERE id=?")->execute([$u, $f, $r, $cid, $active, $_POST['user_id']]);
            
            // ذخیره مراکز انتخاب شده
            if(isset($_POST["center_names"]) && is_array($_POST["center_names"])) {
                $db->prepare("DELETE FROM user_centers WHERE user_id = ?")->execute([$_POST['user_id']]);
                $ins = $db->prepare("INSERT IGNORE INTO user_centers (user_id, center_name) VALUES (?, ?)");
                foreach($_POST["center_names"] as $cn) {
                    $ins->execute([$_POST['user_id'], $cn]);
                }
            }
            
            // همگام‌سازی کامل assets
            if(!empty($oldFullname) && $oldFullname != $f) {
                $db->prepare("UPDATE assets SET recipient = ? WHERE recipient = ?")->execute([$f, $oldFullname]);
            }
            $db->prepare("UPDATE assets SET created_by = ? WHERE recipient = ? AND (created_by IS NULL OR created_by = 0)")->execute([$_POST['user_id'], $f]);
            if($_POST['password']) $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$_POST['user_id']]);
            
            $msg = '✅ کاربر ویرایش شد'; $msgType = 'success';
        } else {
            $db->prepare("INSERT INTO users (username,password,fullname,role,center_id,is_active) VALUES (?,?,?,?,?,?)")->execute([$u,password_hash($_POST['password']?:'123456',PASSWORD_DEFAULT),$f,$r,$cid,$active]);
            $msg = '✅ کاربر جدید اضافه شد'; $msgType = 'success';
        }
    } catch(Exception $e) { $msg = '❌ '.$e->getMessage(); $msgType = 'error'; }
}

if(isset($_GET['toggle'])){ 
    $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([intval($_GET['toggle']), intval($_GET['id'])]); 
    redirect('users.php'); 
}

if(isset($_GET['delete']) && isAdmin()){
    $delId = intval($_GET['delete']);
    if($delId == $_SESSION['user_id']){
        $msg = '❌ نمیتوانید خودتان را حذف کنید!'; $msgType = 'error';
    } else {
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$delId]);
        redirect('users.php');
    }
}

$users = $db->query("SELECT u.*,c.name as cname FROM users u LEFT JOIN centers c ON u.center_id=c.id ORDER BY u.created_at DESC")->fetchAll();
$centers = $db->query("SELECT id,name FROM centers WHERE is_active=1")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>کاربران | اموال</title>
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
        .badge-type {
            background: #faf8f5 !important;
            border: 1px solid #cbd5e1 !important;
            color: #57534e !important;
            padding: 2px 6px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-active {
            background: #d1fae5 !important;
            color: #065f46 !important;
            padding: 2px 6px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-inactive {
            background: #fee2e2 !important;
            color: #991b1b !important;
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
        <h1 style="font-size:16px !important; margin:0; font-weight:800; color:#000000;">👥 مدیریت کاربران</h1>
    </div>
    <button onclick="o()" style="width:34px;height:34px;border-radius:50%;border:none;background:#4361ee;color:#fff;font-size:18px;cursor:pointer;font-weight:700;display:flex;align-items:center;justify-content:center;">＋</button>
</header>

<div class="content" style="padding-top: 12px !important;">
    <div id="toastContainer" class="toast-container"></div>
    
    <?php if(count($users) > 0): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>نام کامل</th>
                    <th>نام کاربری</th>
                    <th>نقش</th>
                    <th>مرکز متصل</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): 
                    $status_badge = $u['is_active'] ? '<span class="badge-active">فعال</span>' : '<span class="badge-inactive">غیرفعال</span>';
                    $status_btn_color = $u['is_active'] ? '#dc2626' : '#059669';
                    $status_btn_text = $u['is_active'] ? '🚫' : '✅';
                ?>
                <tr>
                    <td style="text-align:right !important; font-size:13px;"><?=htmlspecialchars($u['fullname'])?></td>
                    <td style="font-family:sans-serif; font-size:12px;">@<?=htmlspecialchars($u['username'])?></td>
                    <td><span class="badge-type"><?=htmlspecialchars(getRoleName($u['role']))?></span></td>
                    <td style="font-size:12px;"><?=htmlspecialchars($u['cname'] ?: '—')?></td>
                    <td><?=$status_badge?></td>
                    <td>
                        <div style="display:flex; gap:4px; justify-content:center;">
                            <button onclick="e(<?=$u['id']?>)" class="action-btn" style="border:1.5px solid #d97706 !important; color:#d97706 !important;" title="ویرایش">✏️</button>
                            <a href="?toggle=<?=$u['is_active']?0:1?>&id=<?=$u['id']?>" class="action-btn" style="border:1.5px solid <?=$status_btn_color?> !important; color:<?=$status_btn_color?> !important;" title="تغییر وضعیت"><?=$status_btn_text?></a>
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <a href="#" onclick="confirmDelete(<?=$u['id']?>,'<?=$u['fullname']?>')" class="action-btn" style="border:1.5px solid #dc2626 !important; color:#dc2626 !important;" title="حذف">🗑️</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:60px;color:#94a3b8">📭 هیچ کاربری تعریف نشده است</div>
    <?php endif; ?>
</div>

<!-- مودال ساخت/ویرایش کاربر با چیدمان گرید متقارن -->
<div class="modal-overlay" id="m"><div class="modal-sheet" style="max-width:500px !important; padding:22px 18px;">
    <h3 id="mt">➕ کاربر جدید</h3>
    <form method="POST" id="userForm">
        <input type="hidden" name="user_id" id="uid">
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
            <div class="input-group" style="margin:0;">
                <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">نام کاربری <span style="color:#ef4444">*</span></label>
                <input name="username" id="uname" class="input-field" placeholder="username" required style="width:100% !important;">
            </div>
            
            <div class="input-group" style="margin:0;">
                <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">رمز عبور <span id="prq" style="color:#ef4444">*</span></label>
                <input type="password" name="password" id="upass" class="input-field" placeholder="حداقل ۶ کاراکتر" style="width:100% !important;">
            </div>
        </div>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
            <div class="input-group" style="margin:0;">
                <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">نام کامل <span style="color:#ef4444">*</span></label>
                <input name="fullname" id="fname" class="input-field" placeholder="نام و نام خانوادگی" required style="width:100% !important;">
            </div>
            
            <div class="input-group" style="margin:0;">
                <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">نقش کاربری <span style="color:#ef4444">*</span></label>
                <select name="role" id="urole" class="input-field" required style="width:100% !important;">
                    <option value="admin">👑 مدیر</option>
                    <option value="keeper">📦 جمعدار</option>
                    <option value="viewer">👁️ مهمان</option>
                </select>
            </div>
        </div>
        
        <div class="input-group" style="margin-bottom:10px;">
            <label style="display:block;font-size:11px;font-weight:700;color:#57534e;margin-bottom:4px">مراکز مجاز چندگانه</label>
            <div style="max-height:100px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:12px; padding:8px; background:#faf8f5;">
                <?php foreach($centers as $c):?>
                <label style="display:flex; align-items:center; gap:6px; padding:4px 0; font-size:11.5px; color:#1c1917; cursor:pointer; font-weight:bold; margin-bottom:0 !important;">
                    <input type="checkbox" name="center_names[]" value="<?=$c['name']?>" class="center-check" style="width:15px; height:15px; accent-color:#4f46e5; cursor:pointer;"> <?=$c['name']?>
                </label>
                <?php endforeach?>
            </div>
        </div>
        
        <input type="hidden" name="center_id" id="ucid" value="">
        <div class="checkbox-group" style="display:flex; align-items:center; gap:8px; margin:12px 0 16px 0;">
            <input type="checkbox" name="is_active" id="uactive" checked style="width:16px; height:16px; accent-color:#4f46e5; cursor:pointer;">
            <label style="font-size:12px; font-weight:700; color:#1c1917; cursor:pointer;">کاربر فعال باشد</label>
        </div>
        
        <div style="display:flex; gap:6px;">
            <button name="save" class="btn btn-primary" style="flex:1">💾 ذخیره</button>
            <button type="button" onclick="c()" class="btn btn-light" style="flex:1;">انصراف</button>
        </div>
    </form>
</div></div>

<?php include '../includes/bottom_nav.php'; ?>

<script>
function showToast(msg,type){var c=document.getElementById('toastContainer');var e=document.createElement('div');e.className='toast-msg toast-'+type;e.textContent=msg;c.appendChild(e);setTimeout(function(){e.style.animation='toastOut .3s ease forwards';setTimeout(function(){e.remove()},300)},2500)}
function confirmDelete(id,name){var c=document.getElementById('toastContainer');var e=document.createElement('div');e.className='toast-msg toast-confirm';e.innerHTML='<div>🗑️ حذف «'+name+'»؟</div><div class="toast-confirm-buttons"><button class="toast-btn-yes" id="btnYes">حذف</button><button class="toast-btn-no" id="btnNo">انصراف</button></div>';c.appendChild(e);document.getElementById('btnYes').onclick=function(){window.location.href='?delete='+id};document.getElementById('btnNo').onclick=function(){e.remove()}}
function o(){document.getElementById('mt').textContent='➕ کاربر جدید';document.getElementById('uid').value='';document.querySelector('#userForm').reset();document.getElementById('upass').required=true;document.getElementById('prq').style.display='inline';document.getElementById('m').classList.add('show')}
function c(){document.getElementById('m').classList.remove('show')}
async function e(id){
    try {
        let r = await fetch('../api_get_user.php?id=' + id);
        let u = await r.json();
        document.getElementById('mt').textContent = '✏️ ویرایش کاربر';
        document.getElementById('uid').value = u.id;
        document.getElementById('uname').value = u.username || '';
        document.getElementById('upass').value = '';
        document.getElementById('upass').required = false;
        document.getElementById('prq').style.display = 'none';
        document.getElementById('fname').value = u.fullname || '';
        document.getElementById('urole').value = u.role || '';
        document.getElementById('ucid').value = u.center_id || '';
        
        // بارگذاری مراکز چندگانه مجاز کاربر
        fetch('../api_get_user_centers.php?id=' + u.id).then(r=>r.json()).then(centers => {
            document.querySelectorAll('.center-check').forEach(cb => {
                cb.checked = centers.includes(cb.value);
            });
        });
        document.getElementById('uactive').checked = u.is_active == 1;
        document.getElementById('m').classList.add('show');
    } catch(e) { showToast('خطا در دریافت اطلاعات','error') }
}
document.getElementById('m').addEventListener('click',function(e){if(e.target===this)c()});
<?php if($msg):?>showToast('<?=$msg?>','<?=$msgType?>');<?php endif?>
</script>
</body></html>
