<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth.php";

$user = requireAuth($pdo);

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$restaurant_id = (int)($input["restaurant_id"] ?? 0);
$menu_name = trim($input["menu_name"] ?? "");
$price = (int)($input["price"] ?? 0);

if ($restaurant_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ restaurant_id"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($menu_name === "") {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ menu_name"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($price <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ price ให้ถูกต้อง"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // เช็คร้านว่ามีจริงไหม
    $chk = $pdo->prepare("
        SELECT restaurant_id, restaurant_name
        FROM restaurants
        WHERE restaurant_id = :restaurant_id
        LIMIT 1
    ");
    $chk->execute([":restaurant_id" => $restaurant_id]);
    $restaurant = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$restaurant) {
        http_response_code(404);
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบร้านอาหาร"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // เช็คเมนูซ้ำแบบง่าย: ชื่อเมนูในร้านเดียวกัน
    $dup = $pdo->prepare("
        SELECT menu_id
        FROM menus
        WHERE restaurant_id = :restaurant_id
          AND menu_name = :menu_name
        LIMIT 1
    ");
    $dup->execute([
        ":restaurant_id" => $restaurant_id,
        ":menu_name" => $menu_name
    ]);
    $existing = $dup->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            "ok" => true,
            "message" => "เมนูนี้มีอยู่แล้ว",
            "menu_id" => (int)$existing["menu_id"]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ถ้า menus ของคุณไม่มี created_at ให้ลบ created_at, NOW() ออก
    $ins = $pdo->prepare("
        INSERT INTO menus (restaurant_id, menu_name, price, is_available, created_at)
        VALUES (:restaurant_id, :menu_name, :price, 1, NOW())
    ");
    $ins->execute([
        ":restaurant_id" => $restaurant_id,
        ":menu_name" => $menu_name,
        ":price" => $price
    ]);

    http_response_code(201);
    echo json_encode([
        "ok" => true,
        "message" => "เพิ่มเมนูสำเร็จ",
        "menu_id" => (int)$pdo->lastInsertId(),
        "restaurant" => [
            "restaurant_id" => (int)$restaurant["restaurant_id"],
            "restaurant_name" => $restaurant["restaurant_name"]
        ],
        "menu" => [
            "menu_name" => $menu_name,
            "price" => $price,
            "is_available" => 1
        ]
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