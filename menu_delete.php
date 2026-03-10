<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth.php";

$user = requireAuth($pdo);

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$menu_id = (int)($input["menu_id"] ?? 0);

if ($menu_id <= 0) {
    http_response_code(400);
    echo json_encode(["ok" => false, "message" => "กรุณาระบุ menu_id"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $chk = $pdo->prepare("
        SELECT m.menu_id
        FROM menus m
        INNER JOIN restaurants r ON r.restaurant_id = m.restaurant_id
        INNER JOIN saved s ON s.restaurant_id = r.restaurant_id
        WHERE m.menu_id = :menu_id
          AND s.user_id = :user_id
        LIMIT 1
    ");
    $chk->execute([
        ":menu_id" => $menu_id,
        ":user_id" => (int)$user["user_id"]
    ]);

    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(["ok" => false, "message" => "ไม่พบเมนู"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $del = $pdo->prepare("DELETE FROM menus WHERE menu_id = :menu_id");
    $del->execute([":menu_id" => $menu_id]);

    echo json_encode([
        "ok" => true,
        "message" => "ลบเมนูสำเร็จ"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ลบเมนูไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>