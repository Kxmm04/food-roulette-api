<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth_helper.php";

$user = requireAuth($pdo);

$restaurant_id = isset($_GET["restaurant_id"]) ? (int)$_GET["restaurant_id"] : 0;
$limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 100;
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;

if ($restaurant_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ restaurant_id"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // เช็คว่าร้านมีจริง
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

    $stmt = $pdo->prepare("
        SELECT menu_id, restaurant_id, menu_name, price, is_available, created_at
        FROM menus
        WHERE restaurant_id = :restaurant_id
        ORDER BY menu_id DESC
        LIMIT :lim
    ");
    $stmt->bindValue(":restaurant_id", $restaurant_id, PDO::PARAM_INT);
    $stmt->bindValue(":lim", $limit, PDO::PARAM_INT);
    $stmt->execute();

    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "message" => "ดึงเมนูสำเร็จ",
        "restaurant" => $restaurant,
        "count" => count($menus),
        "menus" => $menus
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ดึงเมนูไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>