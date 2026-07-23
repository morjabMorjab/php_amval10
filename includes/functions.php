<?php
date_default_timezone_set('Asia/Tehran');
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    }
    echo "<script>window.location.href='$url';</script>";
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isKeeper() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'keeper']);
}

function checkAuth() {
    if (!isLoggedIn()) redirect('login.php');
}

function getRoleName($role) {
    return ['admin' => 'مدیر', 'keeper' => 'جمعدار', 'viewer' => 'مهمان'][$role] ?? $role;
}

function gregorian_to_jalali($g_y, $g_m, $g_d) {
    $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 29, 30, 29, 30, 29);
    $gy = $g_y - 1600;
    $gm = $g_m - 1;
    $gd = $g_d - 1;
    $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
    for ($i = 0; $i < $gm; ++$i) $g_day_no += $g_days_in_month[$i];
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) $g_day_no++;
    $g_day_no += $gd;
    $j_day_no = $g_day_no - 79;
    $j_np = floor($j_day_no / 12053);
    $j_day_no %= 12053;
    $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
    $j_day_no %= 1461;
    if ($j_day_no >= 366) { $jy += floor(($j_day_no - 1) / 365); $j_day_no = ($j_day_no - 1) % 365; }
    $jm = 0;
    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; $i++) { $j_day_no -= $j_days_in_month[$i]; $jm++; }
    $jd = $j_day_no + 1;
    return array($jy, $jm + 1, $jd);
}

function jalali_date($format = "Y/m/d", $timestamp = null) {
    if ($timestamp === null) $timestamp = time();
    $timestamp += 12600; // +3.5 ساعت تهران
    $date = gmdate("Y-m-d", $timestamp);
    $time = gmdate("H:i", $timestamp);
    list($gy, $gm, $gd) = explode("-", $date);
    list($jy, $jm, $jd) = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd);
    $short_y = substr($jy, -2);
    $out = $format;
    $out = str_replace("Y", $jy, $out);
    $out = str_replace("y", $short_y, $out);
    $out = str_replace("m", str_pad($jm, 2, "0", STR_PAD_LEFT), $out);
    $out = str_replace("d", str_pad($jd, 2, "0", STR_PAD_LEFT), $out);
    $out = str_replace("H", substr($time, 0, 2), $out);
    $out = str_replace("i", substr($time, 3, 2), $out);
    return $out;
}

function formatDate($d) {
    if(!$d) return "";
    // اگر قبلاً شمسی هست (شامل / و سال ۱۴۰۰+)
    if(strpos($d, "/") !== false) {
        $parts = explode("/", $d);
        if(count($parts) == 3 && $parts[0] > 1300) return $d;
    }
    // اگر میلادی هست
    $ts = strtotime($d) + 12600;
    if($ts) return jalali_date("Y/m/d", $ts);
    // نه شمسی نه میلادی - همون رو برگردون
    return $d;
}

function logActivity($uid, $action, $desc = '') {
    try {
        $db = getDB();
        if ($db) $db->prepare("INSERT INTO activity_logs (user_id,action,description,ip_address) VALUES (?,?,?,?)")->execute([$uid, $action, $desc, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}

?>