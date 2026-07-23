<?php
require_once "config/database.php";
$db = getDB();
$db->prepare("UPDATE messages SET is_read = 1 WHERE id = ?")->execute([intval($_GET["id"] ?? 0)]);
