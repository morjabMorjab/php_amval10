<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
checkAuth();

$db = getDB();

// آمار کلی
$stats = [];
if ($db) {
    $stats['users'] = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1")->fetch()['total'];
    $stats['centers'] = $db->query("SELECT COUNT(*) as total FROM centers WHERE is_active = 1")->fetch()['total'];
    $stats['categories'] = $db->query("SELECT COUNT(*) as total FROM categories WHERE is_active = 1")->fetch()['total'];
    $stats['assets'] = $db->query("SELECT COUNT(*) as total FROM assets WHERE status != 'retired'")->fetch()['total'];
    $stats['total_value'] = $db->query("SELECT SUM(current_value) as total FROM assets WHERE status != 'retired'")->fetch()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="admin-layout">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>داشبورد مدیریت</h1>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>کاربران</h3>
                    <div class="value"><?php echo number_format($stats['users']); ?></div>
                    <div class="icon">👥</div>
                </div>
                
                <div class="stat-card">
                    <h3>مراکز</h3>
                    <div class="value"><?php echo number_format($stats['centers']); ?></div>
                    <div class="icon">🏢</div>
                </div>
                
                <div class="stat-card">
                    <h3>طبقات</h3>
                    <div class="value"><?php echo number_format($stats['categories']); ?></div>
                    <div class="icon">📂</div>
                </div>
                
                <div class="stat-card">
                    <h3>اموال</h3>
                    <div class="value"><?php echo number_format($stats['assets']); ?></div>
                    <div class="icon">📦</div>
                </div>
                
                <div class="stat-card">
                    <h3>ارزش کل</h3>
                    <div class="value"><?php echo formatCurrency($stats['total_value']); ?></div>
                    <div class="icon">💰</div>
                </div>
            </div>
            
            <!-- آخرین فعالیت‌ها -->
            <div class="card">
                <div class="card-header">
                    <h3>آخرین فعالیت‌ها</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>فعالیت</th>
                            <th>توضیحات</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $logs = $db->query("
                            SELECT al.*, u.fullname 
                            FROM activity_logs al 
                            LEFT JOIN users u ON al.user_id = u.id 
                            ORDER BY al.created_at DESC 
                            LIMIT 20
                        ");
                        while ($log = $logs->fetch()) {
                            echo "<tr>";
                            echo "<td>{$log['fullname']}</td>";
                            echo "<td>{$log['action']}</td>";
                            echo "<td>{$log['description']}</td>";
                            echo "<td>" . formatDate($log['created_at'], 'Y/m/d H:i') . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
