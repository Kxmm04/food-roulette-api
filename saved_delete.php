<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth.php";

$user = requireAuth($pdo);

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$saved_id = (int)($input["saved_id"] ?? 0);

if ($saved_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ saved_id"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $chk = $pdo->prepare("
        SELECT saved_id
        FROM saved
        WHERE saved_id = :saved_id
          AND user_id = :user_id
        LIMIT 1
    ");
    $chk->execute([
        ":saved_id" => $saved_id,
        ":user_id" => (int)$user["user_id"]
    ]);

    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบร้านที่บันทึกไว้"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $del = $pdo->prepare("
        DELETE FROM saved
        WHERE saved_id = :saved_id
          AND user_id = :user_id
    ");
    $del->execute([
        ":saved_id" => $saved_id,
        ":user_id" => (int)$user["user_id"]
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "ลบร้านที่บันทึกไว้สำเร็จ"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ลบร้านไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>