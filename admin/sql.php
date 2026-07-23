<?php
require_once "../config/database.php";
require_once "../includes/functions.php";
checkAuth();
if(!isAdmin()) redirect("index.php");

$db = getDB();
$result = "";
$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["sql"])) {
    $query = trim($_POST["sql"]);
    if(!empty($query)) {
        try {
            if(stripos($query, "SELECT") === 0 || stripos($query, "SHOW") === 0 || stripos($query, "DESCRIBE") === 0 || stripos($query, "EXPLAIN") === 0) {
                $stmt = $db->query($query);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if(count($rows) > 0) {
                    $result = "<table border=\"1\" cellpadding=\"6\" style=\"border-collapse:collapse;font-size:11px;width:100%\">";
                    $result .= "<tr style=\"background:#4361ee;color:#fff\">";
                    foreach(array_keys($rows[0]) as $col) $result .= "<th>$col</th>";
                    $result .= "</tr>";
                    foreach($rows as $row) {
                        $result .= "<tr>";
                        foreach($row as $val) $result .= "<td>" . htmlspecialchars($val ?? "NULL") . "</td>";
                        $result .= "</tr>";
                    }
                    $result .= "</table>";
                } else {
                    $result = "<p style=\"color:#4361ee\">✅ پرس‌وجو اجرا شد. ۰ ردیف برگشتی.</p>";
                }
            } else {
                $affected = $db->exec($query);
                $result = "<p style=\"color:#059669\">✅ دستور اجرا شد. $affected ردیف تحت تأثیر قرار گرفت.</p>";
            }
        } catch(PDOException $e) {
            $error = "<p style=\"color:#dc2626\">❌ خطا: " . $e->getMessage() . "</p>";
        }
    }
}

?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>کنسول SQL</title><link rel="stylesheet" href="../css/app.css">
<style>
.sql-box{background:#1e293b;color:#e2e8f0;border-radius:12px;padding:16px;margin-bottom:12px}
.sql-box textarea{width:100%;height:120px;background:#0f172a;color:#10b981;border:1px solid #334155;border-radius:8px;padding:12px;font-family:monospace;font-size:13px;resize:vertical;outline:none;direction:ltr;text-align:left}
.btn-run{background:#4361ee;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;margin-top:8px}
.result-box{background:#fff;border-radius:12px;padding:16px;overflow-x:auto;max-height:400px;overflow-y:auto}
.history{margin-top:12px}
.history-item{background:#f8fafc;padding:8px 12px;border-radius:8px;margin-bottom:4px;font-family:monospace;font-size:11px;cursor:pointer;border:1px solid #e2e8f0}
.history-item:hover{background:#eff6ff}
</style>
</head>
<body>
<header class="top-bar"><a href="index.php">→</a><h1>🔧 کنسول SQL</h1></header>
<div class="content">

<div class="sql-box">
<h3 style="margin-bottom:10px;color:#f1f5f9">✍️ دستور SQL</h3>
<form method="POST">
<textarea name="sql" placeholder="SELECT * FROM assets WHERE center = 'مدرسه باقرالعلوم' LIMIT 10;"></textarea>
<button type="submit" class="btn-run">▶️ اجرا</button>
</form>
</div>

<?php if($error): ?><div class="toast toast-error"><?=$error?></div><?php endif?>
<?php if($result): ?><div class="result-box"><?=$result?></div><?php endif?>

<div class="history">
<h4 style="margin-bottom:8px">📋 دستورات پرکاربرد:</h4>
<div class="history-item" onclick="document.querySelector('textarea').value='SELECT COUNT(*) as total FROM assets'">SELECT COUNT(*) as total FROM assets</div>
<div class="history-item" onclick="document.querySelector('textarea').value='SELECT center, COUNT(*) as cnt FROM assets GROUP BY center ORDER BY cnt DESC'">SELECT center, COUNT(*) as cnt FROM assets GROUP BY center ORDER BY cnt DESC</div>
<div class="history-item" onclick="document.querySelector('textarea').value='SELECT status, COUNT(*) as cnt FROM assets GROUP BY status'">SELECT status, COUNT(*) as cnt FROM assets GROUP BY status</div>
<div class="history-item" onclick="document.querySelector('textarea').value='SELECT * FROM users'">SELECT * FROM users</div>
<div class="history-item" onclick="document.querySelector('textarea').value='SHOW TABLES'">SHOW TABLES</div>
<div class="history-item" onclick="document.querySelector('textarea').value='DESCRIBE assets'">DESCRIBE assets</div>
<div class="history-item" style="background:#fef2f2;color:#991b1b;font-weight:700" onclick="document.querySelector('textarea').value='DELETE FROM assets;\nDELETE FROM transfers;\nDELETE FROM activity_logs;\nDELETE FROM messages;\nDELETE FROM users WHERE username != \'admin\';\nDELETE FROM centers;'">🗑️ حذف همه (اموال، جابجایی، لاگ، پیام، جمعدار، مراکز) - فقط ادمین میمونه</div>
</div>

</div>
<?php include "../includes/bottom_nav.php"; ?>
</body></html>