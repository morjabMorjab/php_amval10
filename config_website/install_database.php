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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .step { margin: 15px 0; padding: 10px; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .login-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
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
<h1>🚀 نصب سیستم مدیریت اموال</h1>";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<div class='step success'>✅ اتصال به MySQL برقرار شد</div>";
    
    // ایجاد دیتابیس
    $pdo->exec("DROP DATABASE IF EXISTS amval_db");
    $pdo->exec("CREATE DATABASE amval_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE amval_db");
    echo "<div class='step success'>✅ دیتابیس ایجاد شد</div>";
    
    // ایجاد جداول
    $pdo->exec("
        CREATE TABLE users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            fullname VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE,
            phone VARCHAR(20),
            role ENUM('super_admin', 'admin', 'center_manager', 'keeper', 'viewer') DEFAULT 'viewer',
            center_id INT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    $pdo->exec("
        CREATE TABLE centers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            address TEXT,
            phone VARCHAR(20),
            email VARCHAR(100),
            manager_name VARCHAR(100),
            center_type ENUM('main', 'branch', 'department', 'warehouse') DEFAULT 'branch',
            parent_center_id INT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            description TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_center_id) REFERENCES centers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    
    $pdo->exec("
        CREATE TABLE categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            parent_id INT DEFAULT NULL,
            level INT DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    
    $pdo->exec("
        CREATE TABLE assets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            asset_code VARCHAR(50) UNIQUE NOT NULL,
            barcode VARCHAR(100) UNIQUE,
            name VARCHAR(300) NOT NULL,
            category_id INT,
            center_id INT,
            keeper_id INT,
            description TEXT,
            brand VARCHAR(100),
            model VARCHAR(100),
            serial_number VARCHAR(100),
            national_id VARCHAR(50),
            purchase_date DATE,
            purchase_price DECIMAL(15,2),
            current_value DECIMAL(15,2),
            depreciation_rate DECIMAL(5,2) DEFAULT 0,
            useful_life INT,
            status ENUM('active', 'inactive', 'repair', 'damaged', 'retired', 'transferred') DEFAULT 'active',
            location VARCHAR(200),
            room VARCHAR(100),
            floor VARCHAR(50),
            warranty_expire DATE,
            insurance_number VARCHAR(100),
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE SET NULL,
            FOREIGN KEY (keeper_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    
    $pdo->exec("
        CREATE TABLE transfers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            transfer_code VARCHAR(50) UNIQUE NOT NULL,
            asset_id INT NOT NULL,
            from_center_id INT,
            to_center_id INT NOT NULL,
            from_keeper_id INT,
            to_keeper_id INT,
            transfer_date DATE NOT NULL,
            transfer_type ENUM('permanent', 'temporary', 'repair', 'return') DEFAULT 'permanent',
            reason TEXT,
            status ENUM('draft', 'pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
            transferred_by INT,
            approved_by INT,
            approval_date TIMESTAMP NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
            FOREIGN KEY (from_center_id) REFERENCES centers(id) ON DELETE SET NULL,
            FOREIGN KEY (to_center_id) REFERENCES centers(id) ON DELETE SET NULL,
            FOREIGN KEY (from_keeper_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (to_keeper_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (transferred_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
    
    $pdo->exec("
        CREATE TABLE maintenances (
            id INT PRIMARY KEY AUTO_INCREMENT,
            maintenance_code VARCHAR(50) UNIQUE NOT NULL,
            asset_id INT NOT NULL,
            maintenance_date DATE NOT NULL,
            type ENUM('preventive', 'corrective', 'emergency', 'inspection') NOT NULL,
            description TEXT,
            cost DECIMAL(15,2),
            technician_name VARCHAR(100),
            technician_phone VARCHAR(20),
            company_name VARCHAR(200),
            status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
            start_date DATE,
            end_date DATE,
            next_maintenance_date DATE,
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    
    $pdo->exec("
        CREATE TABLE activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    echo "<div class='step success'>✅ تمام جداول ایجاد شدند</div>";
    
    // ایجاد کاربر ادمین
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, role, is_active) VALUES (?, ?, ?, ?, 'super_admin', 1)");
    $stmt->execute(['admin', $password, 'مدیر کل سیستم', 'admin@example.com']);
    
    echo "<div class='step success'>✅ کاربر مدیر کل ایجاد شد</div>";
    
    // ایجاد چند داده نمونه
    $pdo->exec("INSERT INTO centers (code, name, center_type, address) VALUES 
        ('C001', 'مرکز اصلی', 'main', 'تهران - خیابان اصلی'),
        ('C002', 'شعبه شمال', 'branch', 'تهران - شمال'),
        ('C003', 'انبار مرکزی', 'warehouse', 'تهران - جنوب')");
    
    $pdo->exec("INSERT INTO categories (code, name, level) VALUES 
        ('CAT001', 'تجهیزات کامپیوتری', 1),
        ('CAT002', 'مبلمان اداری', 1),
        ('CAT003', 'وسایل نقلیه', 1)");
    
    $pdo->exec("INSERT INTO categories (code, name, parent_id, level) VALUES 
        ('CAT001-1', 'لپ تاپ', 1, 2),
        ('CAT001-2', 'پرینتر', 1, 2),
        ('CAT001-3', 'مانیتور', 1, 2)");
    
    echo "<div class='step success'>✅ داده‌های نمونه ایجاد شدند</div>";
    
    echo "<div class='login-box'>
        <h2>📋 اطلاعات ورود به سیستم</h2>
        <p style='font-size: 18px; margin: 10px 0;'><strong>نام کاربری:</strong> admin</p>
        <p style='font-size: 18px; margin: 10px 0;'><strong>رمز عبور:</strong> admin123</p>
        <p style='font-size: 18px; margin: 10px 0;'><strong>نقش:</strong> مدیر کل سیستم</p>
        <a href='login.php' class='btn'>ورود به سیستم</a>
    </div>";
    
} catch(PDOException $e) {
    echo "<div class='step error'>❌ خطا: " . $e->getMessage() . "</div>";
    echo "<div class='step info'>
        <strong>راه حل:</strong><br>
        1. مطمئن شوید MySQL نصب و در حال اجراست<br>
        2. دستور زیر را در ترموکس اجرا کنید:<br>
        <code>mysqld --port=3306 --bind-address=127.0.0.1 &</code><br>
        3. سپس صفحه را رفرش کنید
    </div>";
}

echo "</div></body></html>";
?>
