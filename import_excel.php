<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/excel_reader.php';
checkAuth();

$db = getDB();
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        $msg = 'فرمت فایل نامعتبر است. فقط XLSX و CSV مجاز می‌باشند.'; $msgType = 'error';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'خطایی در بارگذاری فایل رخ داد.'; $msgType = 'error';
    } else {
        @mkdir('uploads', 0777, true);
        $path = 'uploads/' . time() . '_' . $file['name'];
        if (move_uploaded_file($file['tmp_name'], $path)) {
            $data = ExcelReader::read($path);
            if (count($data) > 1) {
                $headers = $data[0];
                $columns = ExcelReader::mapColumns($headers);

                $required = [
                    "plate" => "پلاک",
                    "name" => "نام", 
                    "center" => "مرکز",
                    "type" => "نوع",
                    "floor" => "طبقه",
                    "location" => "محل استقرار",
                    "recipient" => "جمعدار"
                ];
                $missing = [];
                foreach($required as $k=>$v) if($columns[$k]===null) $missing[]=$v;

                if(!empty($missing)) {
                    $msg = 'فیلدهای ضروری زیر در هدر فایل شما یافت نشدند: ' . implode('، ', $missing);
                    $msgType = 'error';
                    @unlink($path);
                } else {
                    $_SESSION['import_file'] = $path;
                    $_SESSION['import_columns'] = $columns;
                    $_SESSION['import_total'] = count($data) - 1;
                    $_SESSION['import_filename'] = $file['name'];
                    $msg = (count($data)-1) . ' ردیف آماده ورود است.'; $msgType = 'warning';
                }
            } else {
                $msg = 'فایل اکسل ارسالی خالی است.'; $msgType = 'error';
                @unlink($path);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_import'])) {
    $path = $_SESSION['import_file'] ?? '';
    $cols = $_SESSION['import_columns'] ?? [];

    if($path && file_exists($path)) {
        $data = ExcelReader::read($path);
        array_shift($data);

        $imported = 0; $skipped = 0; $dupCount = 0;
        $db->beginTransaction();

        $center_cache = [];
        $keeper_cache = [];

        foreach($data as $row) {
            $plate  = ($cols['plate'] !== null)  ? trim($row[$cols['plate']] ?? '') : '';
            $name   = ($cols['name'] !== null)   ? trim($row[$cols['name']] ?? '') : '';
            $cname  = ($cols['center'] !== null) ? trim($row[$cols['center']] ?? '') : '';

            if(empty($plate) || empty($name) || empty($cname)) { $skipped++; continue; }

            $type   = ($cols['type'] !== null)      ? trim($row[$cols['type']] ?? '') : 'ثابت';
            $floor  = ($cols['floor'] !== null)     ? trim($row[$cols['floor']] ?? '') : '';
            $loc    = ($cols['location'] !== null)  ? trim($row[$cols['location']] ?? '') : '';
            $rec    = ($cols['recipient'] !== null) ? trim($row[$cols['recipient']] ?? '') : '';
            $date   = ($cols['date'] !== null)      ? trim($row[$cols['date']] ?? '') : jalali_date();
            $status = ($cols['status'] !== null)    ? trim($row[$cols['status']] ?? '') : 'سالم';

            if(empty($type)) $type = 'ثابت';
            if(empty($date)) $date = jalali_date();
            if(empty($status)) $status = 'سالم';

            $db->prepare("INSERT IGNORE INTO assets (plate, name, status, type, floor, location, recipient, center, date, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$plate, $name, $status, $type, $floor, $loc, $rec, $cname, $date, $_SESSION['user_id']]);
            
            if(!empty($cname) && !isset($center_cache[$cname])) {
                $ex = $db->prepare("SELECT id FROM centers WHERE name = ?");
                $ex->execute([$cname]);
                if(!$ex->fetch()) {
                    $db->prepare("INSERT INTO centers (code, name, center_type, is_active) VALUES (?, ?, 'branch', 1)")
                       ->execute(["C-" . date("ymd") . "-" . rand(100,999), $cname]);
                }
                $center_cache[$cname] = true;
            }
            
            if(!empty($rec) && !isset($keeper_cache[$rec])) {
                $ex2 = $db->prepare("SELECT id FROM users WHERE fullname = ?");
                $ex2->execute([$rec]);
                if(!$ex2->fetch()) {
                    $uname = "k_" . substr(md5($rec), 0, 8);
                    $db->prepare("INSERT INTO users (username, password, fullname, role, is_active) VALUES (?, ?, ?, 'keeper', 1)")
                       ->execute([$uname, password_hash("123456", PASSWORD_DEFAULT), $rec]);
                }
                $keeper_cache[$rec] = true;
            }
            if($db->lastInsertId() == 0) { $dupCount++; } else { $imported++; }
        }

        $db->commit();
        @unlink($path);
        unset($_SESSION['import_file'], $_SESSION['import_columns'], $_SESSION['import_total']);

        $msg = "<b>$imported</b> اموال جدید وارد شد.";
        if($dupCount > 0) $msg .= " | 🔁 $dupCount تکراری نادیده گرفته شد.";
        if($skipped > 0) $msg .= " | ⚠️ $skipped ردیف به دلیل نقص داده رد شد.";
        $msgType = 'success';
    }
}

if(isset($_GET['clear'])){
    $fp = $_SESSION['import_file'] ?? '';
    if($fp && file_exists($fp)) @unlink($fp);
    unset($_SESSION['import_file'], $_SESSION['import_columns'], $_SESSION['import_total']);
    redirect('import_excel.php');
}

$total = $_SESSION['import_total'] ?? 0;
$fname = $_SESSION['import_filename'] ?? '';
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>ورود اطلاعات از اکسل</title>
    <link rel="stylesheet" href="css/app.css">
    <style>
        /* استایل مینی‌مال و بسیار تمیز */
        .upload-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            margin-bottom: 16px;
        }
        
        /* دراپ‌زون ساده و تمیز */
        .dropzone {
            border: 1.5px dashed #cbd5e1;
            background: #f8fafc;
            border-radius: 10px;
            padding: 36px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .dropzone:hover {
            border-color: #4361ee;
            background: #f1f5f9;
        }
        
        .dropzone-icon {
            display: inline-block;
            margin-bottom: 12px;
            color: #4361ee;
        }
        
        .dropzone-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        
        .dropzone-subtitle {
            font-size: 11px;
            color: #64748b;
        }

        /* راهنمای فیلدهای اکسل به صورت تگ‌های فلت */
        .guide-title {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 12px;
            text-align: right;
        }
        
        .badge-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .badge-flat {
            font-size: 11px;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 6px;
        }
        
        .badge-req {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-opt {
            background: #f1f5f9;
            color: #475569;
        }

        /* پیام‌های اعلان فلت */
        .toast-msg {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 16px;
            text-align: center;
        }
        .toast-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .toast-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .toast-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

        .btn-outline {
            display: block;
            text-align: center;
            padding: 12px;
            background: #ffffff;
            color: #4361ee;
            border: 1.5px solid #4361ee;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
            transition: all 0.15s;
            margin-top: 14px;
        }
        .btn-outline:hover {
            background: #f0f4ff;
        }
        .btn-outline:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>

<header class="top-bar">
    <a href="assets.php" style="text-decoration:none;font-size:18px;color:#0f172a">→</a>
    <h1>ورود اطلاعات</h1>
    <div style="width:20px"></div>
</header>

<div class="content">

    <?php 
    if ($msg) {
        echo '<div class="toast-msg toast-' . htmlspecialchars($msgType) . '">' . $msg . '</div>';
    }
    ?>

    <?php if (!isset($_SESSION['import_file'])) { ?>
        <!-- کارت آپلود فایل مینیمال -->
        <div class="upload-card">
            <form method="POST" enctype="multipart/form-data" id="excelForm">
                <input type="file" name="excel_file" id="fileSelector" accept=".xlsx,.xls,.csv" style="display:none" onchange="document.getElementById('excelForm').submit()">
                <div class="dropzone" onclick="document.getElementById('fileSelector').click()">
                    <div class="dropzone-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                    </div>
                    <div class="dropzone-title">بارگذاری فایل اکسل</div>
                    <div class="dropzone-subtitle">انتخاب فایل با فرمت XLSX یا CSV</div>
                </div>
            </form>
        </div>

        <!-- کارت راهنمای فیلدهای مورد نیاز به صورت ساده و حرفه‌ای -->
        <div class="upload-card">
            <h3 class="guide-title">ساختار ستون‌های فایل اکسل</h3>
            <div class="badge-grid">
                <span class="badge-flat badge-req">پلاک *</span>
                <span class="badge-flat badge-req">نام اموال *</span>
                <span class="badge-flat badge-req">مرکز *</span>
                <span class="badge-flat badge-req">نوع *</span>
                <span class="badge-flat badge-req">طبقه *</span>
                <span class="badge-flat badge-req">محل استقرار *</span>
                <span class="badge-flat badge-req">جمعدار *</span>
                <span class="badge-flat badge-opt">تاریخ ثبت</span>
                <span class="badge-flat badge-opt">وضعیت اموال</span>
            </div>
            <a href="download_sample.php" class="btn-outline">دانلود قالب نمونه اکسل</a>
        </div>
    <?php } else { ?>
        <!-- کارت تایید نهایی داده‌های خوانده شده از اکسل -->
        <div class="upload-card" style="text-align: center; padding: 32px 24px;">
            <div style="color: #4361ee; margin-bottom: 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
            </div>
            <h4 style="font-size: 14px; color: #0f172a; margin-bottom: 4px; font-weight: 700;"><?php echo htmlspecialchars($fname); ?></h4>
            <p style="color: #64748b; font-size: 11px; margin-bottom: 24px;">تعداد <b><?php echo htmlspecialchars($total); ?></b> ردیف اموال آماده ثبت در دیتابیس است.</p>
            
            <form method="POST">
                <button type="submit" name="confirm_import" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 13px; border-radius: 10px; font-weight: 700;">
                    تایید و ثبت اطلاعات
                </button>
                <a href="?clear=1" class="btn-outline" style="border-color: #fee2e2; color: #991b1b; margin-top: 10px;">
                    انصراف
                </a>
            </form>
        </div>
    <?php } ?>

</div>

<?php include 'includes/bottom_nav.php'; ?>

</body>
</html>