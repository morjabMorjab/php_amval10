<?php
if(!isLoggedIn()) return;
$r = $_SESSION['role'] ?? 'viewer';
$uri = $_SERVER['REQUEST_URI'];

// تشخیص پویا: آیا صفحه در حال اجرا داخل پوشه ادمین است؟
$in_admin = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$prefix = $in_admin ? '../' : '';
$admin_prefix = $in_admin ? '' : 'admin/';

$admin = [
    ['href'=>$prefix . 'index.php','icon'=>'🏠','label'=>'خانه'],
    ['href'=>$prefix . 'assets.php','icon'=>'📦','label'=>'اموال'],
    ['href'=>$prefix . 'transfers.php','icon'=>'🔄','label'=>'جابجایی'],
    ['href'=>$prefix . 'report.php','icon'=>'📊','label'=>'گزارش'],
    ['href'=>$admin_prefix . 'centers.php','icon'=>'🏢','label'=>'مراکز'],
    ['href'=>$admin_prefix . 'users.php','icon'=>'👥','label'=>'کاربران'],
];

$keeper = [
    ['href'=>$prefix . 'index.php','icon'=>'🏠','label'=>'داشبورد'],
    ['href'=>$prefix . 'assets.php','icon'=>'📦','label'=>'اموال'],
    ['href'=>$prefix . 'transfers.php','icon'=>'🔄','label'=>'جابجایی'],
    ['href'=>$prefix . 'report.php','icon'=>'📊','label'=>'گزارش'],
    ['href'=>$prefix . 'profile.php','icon'=>'👤','label'=>'پروفایل'],
];

$viewer = [
    ['href'=>$prefix . 'index.php','icon'=>'🏠','label'=>'خانه'],
    ['href'=>$prefix . 'assets.php','icon'=>'📦','label'=>'اموال'],
    ['href'=>$prefix . 'report.php','icon'=>'📊','label'=>'گزارش'],
    ['href'=>$prefix . 'profile.php','icon'=>'👤','label'=>'پروفایل'],
];

$links = match($r) { 'admin' => $admin, 'keeper' => $keeper, default => $viewer };
?>

<nav style="position:fixed;bottom:0;left:0;right:0;background:#fff;display:flex;justify-content:space-around;padding:10px 4px;padding-bottom:max(10px,env(safe-area-inset-bottom));box-shadow:0 -2px 10px rgba(0,0,0,0.06);z-index:100;transition:none!important;animation:none!important">
    <?php foreach($links as $l): 
        $active = (basename($_SERVER['SCRIPT_NAME']) === basename($l['href']));
    ?>
    <a href="<?=$l['href']?>" style="display:flex;flex-direction:column;align-items:center;gap:2px;text-decoration:none;transition:none!important;color:<?=$active?'#4361ee':'#94a3b8'?>;font-size:11px;font-weight:500;padding:4px 8px;border-radius:8px;min-width:50px;">
        <span style="font-size:22px;"><?=$l['icon']?></span>
        <span><?=$l['label']?></span>
    </a>
    <?php endforeach; ?>
</nav>
