<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$email = trim($input["email"] ?? "");
$otp_code = trim($input["otp_code"] ?? "");

if ($email === "" || $otp_code === "") {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ email และ otp_code"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT otp_id, expires_at, is_used
        FROM otp_reset
        WHERE email = :email
          AND otp_code = :otp_code
        ORDER BY otp_id DESC
        LIMIT 1
    ");
    $stmt->execute([
        ":email" => $email,
        ":otp_code" => $otp_code
    ]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otp) {
        http_response_code(400);
        echo json_encode([
            "ok" => false,
            "message" => "OTP ไม่ถูกต้อง"
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

    echo json_encode([
        "ok" => true,
        "message" => "OTP ถูกต้อง"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ตรวจสอบ OTP ไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>