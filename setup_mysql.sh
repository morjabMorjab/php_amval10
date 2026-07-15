#!/bin/bash

echo "🚀 راه‌اندازی MySQL و دیتابیس مدیریت اموال"
echo "=========================================="

# 1. کشتن پروسه‌های قبلی
echo "📌 توقف پروسه‌های قبلی MySQL..."
killall mysqld 2>/dev/null
sleep 2

# 2. ایجاد پوشه‌های مورد نیاز
echo "📁 ایجاد پوشه‌های MySQL..."
mkdir -p $PREFIX/var/lib/mysql
mkdir -p $PREFIX/var/run/mysqld

# 3. مقداردهی اولیه MySQL
echo "⚙️ مقداردهی اولیه MySQL..."
if [ ! -d "$PREFIX/var/lib/mysql/mysql" ]; then
    mysql_install_db --datadir=$PREFIX/var/lib/mysql
    echo "✅ MySQL initial data directory created"
else
    echo "✅ MySQL data directory already exists"
fi

# 4. اجرای MySQL
echo "▶️ اجرای MySQL..."
mysqld_safe --datadir=$PREFIX/var/lib/mysql --socket=$PREFIX/var/run/mysqld/mysqld.sock &
sleep 5

# 5. بررسی اجرا
if ps aux | grep -v grep | grep mysqld > /dev/null; then
    echo "✅ MySQL با موفقیت اجرا شد"
else
    echo "❌ خطا در اجرای MySQL"
    exit 1
fi

# 6. ایجاد دیتابیس
echo "📊 ایجاد دیتابیس..."
mysql -u root -e "CREATE DATABASE IF NOT EXISTS amval_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ دیتابیس amval_db ایجاد شد"
else
    echo "⚠️ ممکن است دیتابیس از قبل وجود داشته باشد"
fi

# 7. ایجاد جداول
echo "📋 ایجاد جداول..."

mysql -u root amval_db << 'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'manager', 'user') DEFAULT 'user',
    center_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS centers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    manager VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS assets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(300) NOT NULL,
    category_id INT,
    center_id INT,
    description TEXT,
    brand VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    purchase_date DATE,
    purchase_price DECIMAL(15,2),
    current_value DECIMAL(15,2),
    status ENUM('active', 'repair', 'damaged', 'retired') DEFAULT 'active',
    location VARCHAR(200),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS transfers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    from_center_id INT,
    to_center_id INT NOT NULL,
    transfer_date DATE NOT NULL,
    reason TEXT,
    transferred_by INT,
    approved_by INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (from_center_id) REFERENCES centers(id) ON DELETE SET NULL,
    FOREIGN KEY (to_center_id) REFERENCES centers(id) ON DELETE CASCADE,
    FOREIGN KEY (transferred_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS maintenance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    maintenance_date DATE NOT NULL,
    type ENUM('preventive', 'corrective', 'emergency') NOT NULL,
    description TEXT,
    cost DECIMAL(15,2),
    technician VARCHAR(100),
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    next_maintenance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
);

INSERT IGNORE INTO users (username, password, fullname, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'admin');

INSERT IGNORE INTO centers (code, name, address) VALUES 
('C001', 'مرکز اصلی', 'تهران - خیابان اصلی'),
('C002', 'شعبه شمال', 'تهران - شمال'),
('C003', 'شعبه جنوب', 'تهران - جنوب');

INSERT IGNORE INTO categories (code, name, parent_id) VALUES 
('CAT001', 'تجهیزات کامپیوتری', NULL),
('CAT002', 'لپ تاپ', 1),
('CAT003', 'پرینتر', 1),
('CAT004', 'مبلمان اداری', NULL),
('CAT005', 'وسایل نقلیه', NULL);
SQL

if [ $? -eq 0 ]; then
    echo "✅ جداول با موفقیت ایجاد شدند"
else
    echo "❌ خطا در ایجاد جداول"
    exit 1
fi

echo ""
echo "✅ نصب با موفقیت کامل شد!"
echo ""
echo "📋 اطلاعات ورود به سیستم:"
echo "   آدرس: http://localhost:8080"
echo "   نام کاربری: admin"
echo "   رمز عبور: admin123"
echo ""
echo "▶️ برای اجرای وب سرور:"
echo "   php -S localhost:8080"
echo ""
echo "🧪 برای تست اتصال:"
echo "   php -S localhost:8081"
echo "   سپس test_db.php را باز کنید"
