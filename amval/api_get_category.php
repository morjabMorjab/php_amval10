<?php
require_once 'config/database.php'; require_once 'includes/functions.php'; checkAuth();
header('Content-Type: application/json; charset=utf-8');
$db = getDB(); $stmt = $db->prepare("SELECT * FROM categories WHERE id=?"); $stmt->execute([$_GET['id']]);
echo json_encode($stmt->fetch(), JSON_UNESCAPED_UNICODE);
?>
