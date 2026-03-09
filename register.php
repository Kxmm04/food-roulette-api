<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$full_name = trim($input["full_name"] ?? "");
$email = trim($input["email"] ?? "");
$password = $input["password"] ?? "";

if ($full_name === "" || $email === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"กรุณากรอกชื่อ อีเมล และรหัสผ่าน"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"รูปแบบอีเมลไม่ถูกต้อง"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // เช็คอีเมลซ้ำ
    $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
    $chk->execute([":email" => $email]);
    if ($chk->fetch()) {
        http_response_code(409);
        echo json_encode(["ok"=>false,"message"=>"อีเมลนี้ถูกใช้งานแล้ว"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    // ถ้า users ของคุณไม่มี created_at ให้ลบ created_at, NOW() ออก
    $ins = $pdo->prepare("
        INSERT INTO users (full_name, email, password_hash, created_at)
        VALUES (:full_name, :email, :password_hash, NOW())
    ");
    $ins->execute([
        ":full_name" => $full_name,
        ":email" => $email,
        ":password_hash" => $hash
    ]);

    http_response_code(201);
    echo json_encode([
        "ok" => true,
        "message" => "สมัครสมาชิกสำเร็จ",
        "user_id" => (int)$pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "สมัครสมาชิกไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>