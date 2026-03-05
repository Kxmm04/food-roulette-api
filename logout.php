<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth_helper.php";

// ต้อง auth ก่อน เพื่อเช็คว่า token valid
$user = requireAuth($pdo);

// เอา token จาก header อีกครั้งเพื่อ delete
$token = getBearerToken();

try {
    $stmt = $pdo->prepare("DELETE FROM tokens WHERE token = :token LIMIT 1");
    $stmt->execute([":token" => $token]);

    echo json_encode([
        "ok" => true,
        "message" => "ออกจากระบบสำเร็จ"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ออกจากระบบไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>