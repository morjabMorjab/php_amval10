<?php
if(!isLoggedIn()) return;
$r = $_SESSION['role'] ?? 'viewer';
$in_admin = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$p = $in_admin ? '../' : '';
$ap = $in_admin ? '' : 'admin/';
$links = ($r == 'admin') ? [
    ['href'=>$p.'index.php','icon'=>'🏠','label'=>'خانه'],
    ['href'=>$p.'assets.php','icon'=>'📦','label'=>'اموال'],
    ['href'=>$p.'transfers.php','icon'=>'🔄','label'=>'جابجایی'],
    ['href'=>$p.'report.php','icon'=>'📊','label'=>'گزارش'],
    ['href'=>$ap.'centers.php','icon'=>'🏢','label'=>'مراکز'],
    ['href'=>$ap.'users.php','icon'=>'👥','label'=>'کاربران'],
] : [
    ['href'=>$p.'index.php','icon'=>'🏠','label'=>'خانه'],
    ['href'=>$p.'assets.php','icon'=>'📦','label'=>'اموال'],
    ['href'=>$p.'transfers.php','icon'=>'🔄','label'=>'جابجایی'],
    ['href'=>$p.'report.php','icon'=>'📊','label'=>'گزارش'],
    ['href'=>$p.'profile.php','icon'=>'👤','label'=>'پروفایل'],
];
?>
<nav>
    <?php foreach($links as $l): $active = (basename($_SERVER['SCRIPT_NAME']) === basename($l['href'])); ?>
    <a href="<?=$l['href']?>" class="<?= $active ? 'active' : '' ?>">
        <span><?=$l['icon']?></span><span><?=$l['label']?></span>
    </a>
    <?php endforeach; ?>
</nav>