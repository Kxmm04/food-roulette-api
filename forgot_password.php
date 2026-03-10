<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once __DIR__ . "/vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$email = trim($input["email"] ?? "");

if ($email === "") {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุอีเมล"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT user_id, email
        FROM users
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบบัญชีผู้ใช้นี้"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $otp = str_pad((string)random_int(0, 999999), 6, "0", STR_PAD_LEFT);
    $expiresAt = date("Y-m-d H:i:s", time() + 300); // 5 นาที

    $ins = $pdo->prepare("
        INSERT INTO otp_reset (email, otp_code, expires_at, is_used)
        VALUES (:email, :otp_code, :expires_at, 0)
    ");
    $ins->execute([
        ":email" => $email,
        ":otp_code" => $otp,
        ":expires_at" => $expiresAt
    ]);

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'foodroulettehelp@gmail.com'; // Gmail ผู้ส่ง
    $mail->Password   = 'sjxprdcxbwjnqzyc'; // App Password 16 ตัว
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom('foodroulettehelp@gmail.com', 'Food Roulette');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'OTP รีเซ็ตรหัสผ่าน Food Roulette';
    $mail->Body = "
        <div style='font-family:Arial,sans-serif'>
            <h2>รีเซ็ตรหัสผ่าน</h2>
            <p>รหัส OTP ของคุณคือ:</p>
            <h1 style='letter-spacing:4px;'>$otp</h1>
            <p>OTP นี้จะหมดอายุใน 5 นาที</p>
        </div>
    ";

    $mail->send();

    echo json_encode([
        "ok" => true,
        "message" => "ส่ง OTP ไปยังอีเมลแล้ว"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ส่ง OTP ไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "บันทึก OTP ไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>