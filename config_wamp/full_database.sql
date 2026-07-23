-- ایجاد دیتابیس
CREATE DATABASE IF NOT EXISTS amval_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE amval_db;

-- جدول کاربران با نقش‌های مختلف
CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB;

-- جدول سازمان‌ها/مراکز
CREATE TABLE IF NOT EXISTS centers (
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
    FOREIGN KEY (parent_center_id) REFERENCES centers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- جدول طبقات/دسته‌بندی اموال
CREATE TABLE IF NOT EXISTS categories (
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
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- جدول اموال
CREATE TABLE IF NOT EXISTS assets (
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
    national_id VARCHAR(50) COMMENT 'شماره اموال ملی',
    purchase_date DATE,
    purchase_price DECIMAL(15,2),
    current_value DECIMAL(15,2),
    depreciation_rate DECIMAL(5,2) DEFAULT 0,
    useful_life INT COMMENT 'عمر مفید به ماه',
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_asset_code (asset_code),
    INDEX idx_barcode (barcode),
    INDEX idx_status (status),
    INDEX idx_center (center_id),
    INDEX idx_category (category_id)
) ENGINE=InnoDB;

-- جدول جابجایی اموال
CREATE TABLE IF NOT EXISTS transfers (
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (from_center_id) REFERENCES centers(id) ON DELETE SET NULL,
    FOREIGN KEY (to_center_id) REFERENCES centers(id) ON DELETE SET NULL,
    FOREIGN KEY (from_keeper_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (to_keeper_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (transferred_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- جدول تعمیرات
CREATE TABLE IF NOT EXISTS maintenances (
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- جدول اسناد و مدارک اموال
CREATE TABLE IF NOT EXISTS documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    document_type ENUM('invoice', 'warranty', 'insurance', 'manual', 'other') NOT NULL,
    title VARCHAR(200) NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size BIGINT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    notes TEXT,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- جدول لاگ فعالیت‌ها
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
