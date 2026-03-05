<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";

$keyword = trim($_GET["keyword"] ?? "");
$limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 100;
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;

try {
    if ($keyword !== "") {
        $stmt = $pdo->prepare("
            SELECT restaurant_id, restaurant_name, address, lat, lng, avg_price
            FROM restaurants
            WHERE restaurant_name LIKE :kw
            ORDER BY restaurant_name ASC
            LIMIT :lim
        ");
        $stmt->bindValue(":kw", "%".$keyword."%", PDO::PARAM_STR);
        $stmt->bindValue(":lim", $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT restaurant_id, restaurant_name, address, lat, lng, avg_price
            FROM restaurants
            ORDER BY restaurant_name ASC
            LIMIT :lim
        ");
        $stmt->bindValue(":lim", $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "message" => "ดึงรายการร้านอาหารสำเร็จ",
        "count" => count($rows),
        "restaurants" => $rows
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ดึงรายการร้านอาหารไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>