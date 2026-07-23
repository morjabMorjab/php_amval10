<?php
function getDB() {
    static $db = null;
    if ($db !== null) return $db;
    try {
        // تنظیم دیتابیس لوکال ومپ‌سرور
        $db = new PDO("mysql:host=127.0.0.1;dbname=amval_db;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $db;
    } catch (PDOException $e) {
        return null;
    }
}
?>