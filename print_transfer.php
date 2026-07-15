<?php
require_once "config/database.php";
require_once "includes/functions.php";
checkAuth();
$db = getDB();

$code = $_GET["code"] ?? "";
if(!$code && isset($_GET["id"])) {
    $code = $db->query("SELECT transfer_code FROM transfers WHERE id=".intval($_GET["id"]))->fetchColumn();
}
if(!$code) die("رسید یافت نشد");

// واکشی به همراه استخراج نام ثبت‌کننده جابجایی (جمعدار مربوطه) به عنوان نام جایگزین
$stmt = $db->prepare("
    SELECT t.*, a.name as asset_name, a.plate, a.recipient, u.fullname as transferred_by_name 
    FROM transfers t 
    JOIN assets a ON t.asset_id=a.id 
    LEFT JOIN users u ON t.transferred_by=u.id 
    WHERE t.transfer_code = ?
");
$stmt->execute([$code]);
$items = $stmt->fetchAll();

if(count($items) === 0) die("رسید یافت نشد");

$first = $items[0];
$admin_name = $db->query("SELECT fullname FROM users WHERE role='admin' LIMIT 1")->fetchColumn();

// تعریف تابع بومی جهت فارسی‌سازی تمام اعداد چاپی
if (!function_exists('toPersianNum')) {
    function toPersianNum($str) {
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        return str_replace($en, $fa, $str);
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>رسید جابجایی اموال - <?= htmlspecialchars($code) ?></title>
<style>
    @font-face { font-family: "BTitr"; src: url("fonts/B Titr Bold_0.ttf"); }
    @font-face { font-family: "BNazanin"; src: url("fonts/B-NAZANIN.TTF"); }
    
    /* قوانین مرورگر برای انتخاب اتوماتیک کاغذ A5 در پنجره پرینت */
    @page { 
        size: A5 !important; 
        margin: 10mm !important; 
    }
    
    @media print {
        .no-print { display: none !important; }
        body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
        .page { max-width: 100% !important; padding: 0 !important; box-shadow: none !important; border: none !important; }
    }
    
    body {
        font-family: 'BNazanin', Tahoma, sans-serif;
        direction: rtl;
        background: #f1f5f9;
        margin: 0;
        padding: 20px;
    }
    
    /* در نمایش معمولی وب به شکل برگه A5 وسط‌چین نشان داده می‌شود، در چاپ کاملا تخت و فیت می‌شود */
    .page {
        background: #fff;
        max-width: 148mm;
        margin: 0 auto;
        padding: 5mm;
        box-sizing: border-box;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .header-block {
        text-align: center;
        margin-bottom: 12px;
        border-bottom: 2px solid #000;
        padding-bottom: 6px;
    }
    
    .header-block h1 {
        font-family: "BTitr", sans-serif;
        font-size: 15px;
        margin: 0 0 4px 0;
    }
    
    .header-block h2 {
        font-family: "BTitr", sans-serif;
        font-size: 11.5px;
        margin: 0;
        color: #1e293b;
    }
    
    table.meta-grid {
        width: 100%;
        margin-bottom: 10px;
        border-collapse: collapse;
    }
    
    table.meta-grid td {
        border: none !important;
        padding: 4px 0;
        font-size: 10px;
        font-weight: bold;
    }
    
    table.items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
        font-size: 10px;
    }
    
    table.items-table th, table.items-table td {
        border: 1px solid #000 !important;
        padding: 6px 4px;
        text-align: center !important;
    }
    
    table.items-table th {
        background: #f1f5f9 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        font-family: "BTitr", sans-serif;
        font-size: 10.5px;
    }
    
    .reason-section {
        margin-top: 10px;
        font-size: 10px;
        line-height: 1.6;
        border: 1px dashed #7f8c8d;
        padding: 8px;
        border-radius: 4px;
    }
    
    table.footer-block {
        margin-top: 35px;
        width: 100%;
        border-collapse: collapse;
    }
    
    table.footer-block td {
        border: none !important;
        width: 25%;
        text-align: center;
        vertical-align: top;
        font-size: 9.5px;
        padding: 0;
    }
    
    .signature-title {
        font-weight: bold;
        display: block;
        margin-bottom: 15px; /* این مقدار دقیقاً همان فاصله عمودی است که نصف شد */
    }
    
    .signature-name {
        display: block;
        color: #333;
        font-weight: bold;
    }
</style>
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom:15px; padding:10px; background:#f1f5f9; border-bottom:1px solid #e2e8f0;">
    <button onclick="window.print()" style="background:#4361ee; color:#fff; border:none; padding:8px 20px; border-radius:8px; font-weight:bold; cursor:pointer; font-family:tahoma; font-size:11px;">🖨️ چاپ رسید (A5)</button>
    <a href="transfers.php" style="background:#64748b; color:#fff; text-decoration:none; padding:8px 20px; border-radius:8px; font-weight:bold; font-family:tahoma; font-size:11px; margin-right:8px; display:inline-block;">← بازگشت</a>
</div>

<div class="page">
    <div class="header-block">
        <h1>مجتمع خیریه و درمانی حضرت امام هادی(ع)</h1>
        <h2>رسید جابجایی و تحویل اموال</h2>
    </div>
    
    <table class="meta-grid">
        <tr>
            <td>شماره جابجایی: <b><?= toPersianNum(htmlspecialchars($code)) ?></b></td>
            <td style="text-align: left;">تاریخ جابجایی: <b dir="ltr" style="font-family:sans-serif;"><?= toPersianNum(htmlspecialchars($first["transfer_date"])) ?></b></td>
        </tr>
        <tr>
            <td>نوع جابجایی: <b><?= ($first["transfer_type"]=="internal"?"داخلی":($first["transfer_type"]=="permanent"?"انتقال قطعی":"امانی/تعمیرات")) ?></b></td>
            <td style="text-align: left;">&nbsp;</td>
        </tr>
    </table>
    
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">ردیف</th>
                <th style="width: 22%;">شماره پلاک</th>
                <th>نام و مشخصات اموال</th>
                <th style="width: 25%;">مبدا جابجایی</th>
                <th style="width: 25%;">مقصد جابجایی</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $idx => $item): ?>
            <tr>
                <td><?= toPersianNum($idx+1) ?></td>
                <td style="font-family:sans-serif; font-size:9.5px;"><?= toPersianNum(htmlspecialchars($item["plate"])) ?></td>
                <td><?= htmlspecialchars($item["asset_name"]) ?></td>
                <td style="font-size:9px;"><?= htmlspecialchars($item["from_center"]) ?></td>
                <td style="font-size:9px;"><?= htmlspecialchars($item["to_center"]) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="reason-section">
        <b>علت جابجایی:</b> <?= htmlspecialchars($first["reason"] ?: "طبق دستور واحد مربوطه جهت جابجایی اموال مجتمع") ?>
    </div>
    
    <table class="footer-block">
        <tr>
            <td>
                <span class="signature-title">جمعدار مرکز:</span>
                <span class="signature-name"><?= htmlspecialchars($first["recipient"] ?: ($first["transferred_by_name"] ?: "___________")) ?></span>
            </td>
            <td>
                <span class="signature-title">تحویل گیرنده:</span>
                <span class="signature-name">___________</span>
            </td>
            <td>
                <span class="signature-title">امین اموال:</span>
                <span class="signature-name"><?= htmlspecialchars($admin_name) ?></span>
            </td>
            <td>
                <span class="signature-title">مدیر واحد:</span>
                <span class="signature-name">___________</span>
            </td>
        </tr>
    </table>
</div>

</body>
</html>