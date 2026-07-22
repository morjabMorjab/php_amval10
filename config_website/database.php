<?php
function getDB() {
    static $db = null;
    if ($db !== null) return $db;
    try {
        $db = new PDO("mysql:host=localhost;dbname=amval1_amval10;charset=utf8mb4", "amval1_amval1", "morjab@414#mor", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        return $db;
    } catch (PDOException $e) {
        return null;
    }
}
?>