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
    echo json_encode([
        "ok" => false,
        "message" => "กรุณากรอก full_name, email, password ให้ครบ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "รูปแบบอีเมลไม่ถูกต้อง"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // check email ซ้ำ
    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
    $check->execute([":email" => $email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode([
            "ok" => false,
            "message" => "อีเมลนี้ถูกใช้งานแล้ว"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, password_hash)
        VALUES (:full_name, :email, :password_hash)
    ");
    $stmt->execute([
        ":full_name" => $full_name,
        ":email" => $email,
        ":password_hash" => $password_hash
    ]);

    http_response_code(201);
    echo json_encode([
        "ok" => true,
        "message" => "สมัครสมาชิกสำเร็จ",
        "user" => [
            "user_id" => (int)$pdo->lastInsertId(),
            "full_name" => $full_name,
            "email" => $email
        ]
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