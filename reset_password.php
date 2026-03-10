<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$email = trim($input["email"] ?? "");
$otp_code = trim($input["otp_code"] ?? "");
$new_password = trim($input["new_password"] ?? "");

if ($email === "" || $otp_code === "" || $new_password === "") {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณากรอกข้อมูลให้ครบ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT reset_id, user_id, email, otp_code, expires_at, is_used
        FROM otp_reset
        WHERE email = :email
        ORDER BY reset_id DESC
        LIMIT 1
    ");
    $stmt->execute([":email" => $email]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otp) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบ OTP ของอีเมลนี้"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)$otp["is_used"] === 1) {
        http_response_code(400);
        echo json_encode([
            "ok" => false,
            "message" => "OTP นี้ถูกใช้ไปแล้ว"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (strtotime($otp["expires_at"]) < time()) {
        http_response_code(400);
        echo json_encode([
            "ok" => false,
            "message" => "OTP หมดอายุแล้ว"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($otp_code !== $otp["otp_code"]) {
        http_response_code(400);
        echo json_encode([
            "ok" => false,
            "message" => "OTP ไม่ถูกต้อง"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $passwordHash = password_hash($new_password, PASSWORD_DEFAULT);

    $updUser = $pdo->prepare("
        UPDATE users
        SET password_hash = :password
        WHERE user_id = :user_id
    ");
    $updUser->execute([
        ":password" => $passwordHash,
        ":user_id" => (int)$otp["user_id"]
    ]);

    $updOtp = $pdo->prepare("
        UPDATE otp_reset
        SET is_used = 1
        WHERE reset_id = :reset_id
    ");
    $updOtp->execute([
        ":reset_id" => (int)$otp["reset_id"]
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "เปลี่ยนรหัสผ่านสำเร็จ"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "เปลี่ยนรหัสผ่านไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>