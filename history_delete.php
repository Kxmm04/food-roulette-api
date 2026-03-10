<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth.php";

$user = requireAuth($pdo);

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$history_id = (int)($input["history_id"] ?? 0);

if ($history_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ history_id"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $chk = $pdo->prepare("
        SELECT history_id
        FROM history
        WHERE history_id = :history_id
          AND user_id = :user_id
        LIMIT 1
    ");
    $chk->execute([
        ":history_id" => $history_id,
        ":user_id" => (int)$user["user_id"]
    ]);

    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบประวัติการกิน"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $del = $pdo->prepare("
        DELETE FROM history
        WHERE history_id = :history_id
          AND user_id = :user_id
    ");
    $del->execute([
        ":history_id" => $history_id,
        ":user_id" => (int)$user["user_id"]
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "ลบประวัติการกินสำเร็จ"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ลบประวัติการกินไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>