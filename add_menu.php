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
$menu_name = trim($input["menu_name"] ?? "");
$price = (int)($input["price"] ?? 0);

if ($restaurant_id <= 0 || $menu_name === "" || $price <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ restaurant_id, menu_name, price ให้ถูกต้อง"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // เช็คว่าร้านมีจริง
    $chk = $pdo->prepare("SELECT restaurant_id FROM restaurants WHERE restaurant_id = :id LIMIT 1");
    $chk->execute([":id" => $restaurant_id]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(["ok"=>false,"message"=>"ไม่พบร้านอาหาร"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO menus (restaurant_id, menu_name, price, is_available)
        VALUES (:restaurant_id, :menu_name, :price, 1)
    ");
    $stmt->execute([
        ":restaurant_id" => $restaurant_id,
        ":menu_name" => $menu_name,
        ":price" => $price
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "เพิ่มเมนูสำเร็จ",
        "menu_id" => (int)$pdo->lastInsertId()
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "เพิ่มเมนูไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>