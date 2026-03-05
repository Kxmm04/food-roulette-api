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
$menu_id = (int)($input["menu_id"] ?? 0);
$price = (int)($input["price"] ?? -1);
$distance_km = isset($input["distance_km"]) ? (float)$input["distance_km"] : -1;

if ($restaurant_id <= 0 || $menu_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ restaurant_id และ menu_id ให้ถูกต้อง"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($price < 0 || $distance_km < 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ price และ distance_km ให้ถูกต้อง"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // เช็คว่า menu อยู่ในร้านนั้นจริง (กันมั่ว)
    $chk = $pdo->prepare("
        SELECT m.menu_id, m.menu_name, m.price, r.restaurant_id, r.restaurant_name
        FROM menus m
        INNER JOIN restaurants r ON r.restaurant_id = m.restaurant_id
        WHERE m.menu_id = :menu_id
          AND r.restaurant_id = :restaurant_id
        LIMIT 1
    ");
    $chk->execute([
        ":menu_id" => $menu_id,
        ":restaurant_id" => $restaurant_id
    ]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(400);
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบ menu_id ใน restaurant_id ที่ระบุ"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ins = $pdo->prepare("
        INSERT INTO history (user_id, restaurant_id, menu_id, price, distance_km)
        VALUES (:user_id, :restaurant_id, :menu_id, :price, :distance_km)
    ");
    $ins->execute([
        ":user_id" => (int)$user["user_id"],
        ":restaurant_id" => $restaurant_id,
        ":menu_id" => $menu_id,
        ":price" => $price,
        ":distance_km" => $distance_km
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "บันทึกประวัติการกินสำเร็จ",
        "history_id" => (int)$pdo->lastInsertId(),
        "data" => [
            "restaurant_id" => $restaurant_id,
            "restaurant_name" => $row["restaurant_name"],
            "menu_id" => $menu_id,
            "menu_name" => $row["menu_name"],
            "price" => $price,
            "distance_km" => $distance_km
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "บันทึกประวัติไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>