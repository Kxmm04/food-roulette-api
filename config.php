<?php
date_default_timezone_set('Asia/Bangkok');

$pdo = null;

$DB_HOST = getenv('DB_HOST');
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
$DB_PORT = getenv('DB_PORT') ?: "3306";

if ($DB_HOST && $DB_NAME && $DB_USER) {
    try {
        $pdo = new PDO(
            "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
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
}
?>