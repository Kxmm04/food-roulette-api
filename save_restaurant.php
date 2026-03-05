<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth_helper.php";

$user = requireAuth($pdo);

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$restaurant_id = (int)($input["restaurant_id"] ?? 0);

if ($restaurant_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ restaurant_id ให้ถูกต้อง"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // เช็คว่าร้านมีจริงไหม
    $chk = $pdo->prepare("SELECT restaurant_id, restaurant_name FROM restaurants WHERE restaurant_id = :id LIMIT 1");
    $chk->execute([":id" => $restaurant_id]);
    $restaurant = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$restaurant) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบร้านอาหาร"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // เช็คซ้ำ
    $dup = $pdo->prepare("
        SELECT saved_id
        FROM saved
        WHERE user_id = :user_id AND restaurant_id = :restaurant_id
        LIMIT 1
    ");
    $dup->execute([
        ":user_id" => $user["user_id"],
        ":restaurant_id" => $restaurant_id
    ]);
    $existing = $dup->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            "ok" => true,
            "message" => "เคยบันทึกร้านนี้ไว้แล้ว",
            "saved_id" => (int)$existing["saved_id"]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // บันทึก
    $ins = $pdo->prepare("
        INSERT INTO saved (user_id, restaurant_id)
        VALUES (:user_id, :restaurant_id)
    ");
    $ins->execute([
        ":user_id" => $user["user_id"],
        ":restaurant_id" => $restaurant_id
    ]);

    http_response_code(201);
    echo json_encode([
        "ok" => true,
        "message" => "บันทึกร้านสำเร็จ",
        "saved_id" => (int)$pdo->lastInsertId(),
        "restaurant" => $restaurant
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "บันทึกร้านไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>