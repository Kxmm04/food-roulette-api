<?php
date_default_timezone_set('Asia/Bangkok');

$DB_HOST = "localhost";
$DB_NAME = "food_roulette";     // ✅ ชื่อ DB ที่เราสร้าง
$DB_USER = "root";              // ✅ ปรับตามเครื่อง
$DB_PASS = "";                  // ✅ ปรับตามเครื่อง (ส่วนมาก XAMPP ว่าง)

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "ok" => false,
        "message" => "เชื่อมต่อฐานข้อมูลไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>