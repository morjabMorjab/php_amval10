<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>نصب دیتابیس</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Tahoma, sans-serif; 
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1e293b;
            direction: rtl;
        }
        .container {
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }
        h1 { color: #0f172a; margin-bottom: 20px; text-align: center; font-size: 20px; }
        .step { margin: 12px 0; padding: 12px; border-radius: 8px; font-size: 13px; font-weight: 600; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .login-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #4361ee;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class='container'>
<h1>🚀 نصب و تنظیم مجدد دیتابیس</h1>";

try {
    // اتصال اولیه به MySQL بدون انتخاب دیتابیس
    $pdo = new PDO("mysql:host=127.0.0.1;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<div class='step success'>✅ اتصال به MySQL با موفقیت برقرار شد.</div>";
    
    // حذف دیتابیس قدیمی در صورت وجود و ساخت دیتابیس پاکیزه
    $pdo->exec("DROP DATABASE IF EXISTS amval_db");
    $pdo->exec("CREATE DATABASE amval_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE amval_db");
    echo "<div class='step success'>✅ دیتابیس amval_db با موفقیت ایجاد شد.</div>";
    
    // ۱. جدول کاربران
    $pdo->exec("
        CREATE TABLE users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            fullname VARCHAR(100) NOT NULL,
            role VARCHAR(50) NOT NULL,
            center_id INT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    // ۲. جدول دسترسی مراکز کاربران (چندمرکزی)
    $pdo->exec("
        CREATE TABLE user_centers (
            user_id INT NOT NULL,
            center_name VARCHAR(200) NOT NULL,
            PRIMARY KEY (user_id, center_name)
        ) ENGINE=InnoDB
    ");
    
    // ۳. جدول مراکز
    $pdo->exec("
        CREATE TABLE centers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            address TEXT,
            phone VARCHAR(20),
            manager_name VARCHAR(100),
            center_type VARCHAR(50) DEFAULT 'branch',
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB
    ");
    
    // ۴. جدول دسته‌بندی‌ها
    $pdo->exec("
        CREATE TABLE categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            parent_id INT DEFAULT NULL
        ) ENGINE=InnoDB
    ");
    
    // ۵. جدول اموال (کاملاً منطبق بر ساختار کدهای برنامه)
    $pdo->exec("
        CREATE TABLE assets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            plate VARCHAR(100) UNIQUE NOT NULL,
            name VARCHAR(300) NOT NULL,
            status VARCHAR(100) DEFAULT 'سالم',
            type VARCHAR(100) DEFAULT 'ثابت',
            floor VARCHAR(100),
            location VARCHAR(200),
            recipient VARCHAR(200),
            center VARCHAR(200),
            date VARCHAR(100),
            description TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    // ۶. جدول جابجایی‌ها (سازگار با منطق transfers.php)
    $pdo->exec("
        CREATE TABLE transfers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            transfer_code VARCHAR(50) NOT NULL,
            asset_id INT NOT NULL,
            transfer_type VARCHAR(50) DEFAULT 'internal',
            transfer_date VARCHAR(50),
            reason TEXT,
            from_center VARCHAR(200),
            to_center VARCHAR(200),
            transferred_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    
    // ۷. جدول لاگ فعالیت‌ها
    $pdo->exec("
        CREATE TABLE activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            username VARCHAR(50),
            fullname VARCHAR(100),
            action VARCHAR(50),
            entity_type VARCHAR(50),
            entity_id INT,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    // ۸. جدول پیام‌ها
    $pdo->exec("
        CREATE TABLE messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            sender_id INT NOT NULL,
            receiver_id INT DEFAULT NULL,
            receiver_center VARCHAR(200) DEFAULT NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    echo "<div class='step success'>✅ تمام جداول دیتابیس با ساختار کاملاً سازگار ساخته شدند.</div>";
    
    // ایجاد کاربر ادمین پیش‌فرض با نقش هماهنگ با سیستم
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role, is_active) VALUES (?, ?, ?, 'admin', 1)");
    $stmt->execute(['admin', $password, 'مدیر کل سیستم']);
    
    echo "<div class='step success'>✅ اکانت ادمین پیش‌فرض ایجاد شد.</div>";
    
    // ایجاد داده‌های آزمایشی برای مراکز
    $pdo->exec("INSERT INTO centers (code, name, center_type) VALUES 
        ('C001', 'مرکز اصلی', 'main'),
        ('C002', 'درمانگاه امام هادی(ع)', 'branch'),
        ('C003', 'شعبه شمال', 'branch')");
        
    echo "<div class='step success'>✅ داده‌های نمونه اولیه با موفقیت درج شدند.</div>";
    
    echo "<div class='login-box'>
        <h2>📋 اطلاعات ورود به سیستم</h2>
        <p style='font-size: 16px; margin: 8px 0;'><strong>نام کاربری:</strong> admin</p>
        <p style='font-size: 16px; margin: 8px 0;'><strong>رمز عبور:</strong> admin123</p>
        <p style='font-size: 16px; margin: 8px 0;'><strong>نقش:</strong> مدیر سیستم (admin)</p>
        <a href='../login.php' class='btn'>ورود به پنل کاربری</a>
    </div>";
    
} catch(PDOException $e) {
    echo "<div class='step error'>❌ خطا در فرآیند نصب: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>