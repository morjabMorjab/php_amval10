-- Create database
CREATE DATABASE IF NOT EXISTS amval_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE amval_db;

-- Users table
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

-- Centers (سازمان‌ها/مراکز)
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

-- Categories (دسته‌بندی اموال)
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Assets (اموال)
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

-- Asset Transfers (جابجایی اموال)
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

-- Maintenance records (تعمیرات)
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

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, fullname, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدیر سیستم', 'admin');

-- Insert sample centers
INSERT INTO centers (code, name, address) VALUES 
('C001', 'مرکز اصلی', 'تهران - خیابان اصلی'),
('C002', 'شعبه شمال', 'تهران - شمال'),
('C003', 'شعبه جنوب', 'تهران - جنوب');

-- Insert sample categories
INSERT INTO categories (code, name, parent_id) VALUES 
('CAT001', 'تجهیزات کامپیوتری', NULL),
('CAT002', 'لپ تاپ', 1),
('CAT003', 'پرینتر', 1),
('CAT004', 'مبلمان اداری', NULL),
('CAT005', 'وسایل نقلیه', NULL);
