<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth.php";

$user = requireAuth($pdo);

try {
    $stmt = $pdo->prepare("
        SELECT
            r.restaurant_id,
            r.restaurant_name,
            r.address,
            r.lat,
            r.lng,
            r.avg_price,

            m.menu_id,
            m.menu_name,
            m.price,
            m.is_available
        FROM saved s
        INNER JOIN restaurants r ON r.restaurant_id = s.restaurant_id
        LEFT JOIN menus m ON m.restaurant_id = r.restaurant_id
        WHERE s.user_id = :user_id
        ORDER BY r.restaurant_id ASC, m.menu_id ASC
    ");
    $stmt->execute([":user_id" => (int)$user["user_id"]]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $rid = (int)$row["restaurant_id"];

        if (!isset($map[$rid])) {
            $map[$rid] = [
                "restaurant_id" => $rid,
                "restaurant_name" => $row["restaurant_name"],
                "address" => $row["address"],
                "lat" => $row["lat"] !== null ? (float)$row["lat"] : null,
                "lng" => $row["lng"] !== null ? (float)$row["lng"] : null,
                "avg_price" => $row["avg_price"] !== null ? (int)$row["avg_price"] : null,
                "menus" => []
            ];
        }

        if ($row["menu_id"] !== null) {
            $map[$rid]["menus"][] = [
                "menu_id" => (int)$row["menu_id"],
                "menu_name" => $row["menu_name"],
                "price" => (int)$row["price"],
                "is_available" => (int)$row["is_available"]
            ];
        }
    }

    $restaurants = array_values($map);

    echo json_encode([
        "ok" => true,
        "message" => "ดึงร้านพร้อมเมนูสำเร็จ",
        "count" => count($restaurants),
        "restaurants" => $restaurants
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ดึงร้านพร้อมเมนูไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>