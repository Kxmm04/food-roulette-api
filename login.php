<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$email = trim($input["email"] ?? "");
$password = $input["password"] ?? "";

if ($email === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"กรุณากรอกอีเมลและรหัสผ่าน"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, email, password_hash
        FROM users
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user["password_hash"])) {
        http_response_code(401);
        echo json_encode(["ok"=>false,"message"=>"อีเมลหรือรหัสผ่านไม่ถูกต้อง"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // สร้าง token อายุ 7 วัน
    $token = bin2hex(random_bytes(32));
    $expires_at = date("Y-m-d H:i:s", strtotime("+7 days"));

    // ถ้า tokens ของคุณไม่มี created_at ให้ลบ created_at, NOW() ออก
    $ins = $pdo->prepare("
        INSERT INTO tokens (user_id, token, expires_at, created_at)
        VALUES (:user_id, :token, :expires_at, NOW())
    ");
    $ins->execute([
        ":user_id" => (int)$user["user_id"],
        ":token" => $token,
        ":expires_at" => $expires_at
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "เข้าสู่ระบบสำเร็จ",
        "token" => $token,
        "expires_at" => $expires_at,
        "user" => [
            "user_id" => (int)$user["user_id"],
            "full_name" => $user["full_name"],
            "email" => $user["email"]
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "เข้าสู่ระบบไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>