<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth_helper.php";

$user = requireAuth($pdo);

$limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 100;
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;

try {
    $stmt = $pdo->prepare("
        SELECT
            h.history_id,
            h.price,
            h.distance_km,
            h.eaten_at,

            r.restaurant_id,
            r.restaurant_name,

            m.menu_id,
            m.menu_name
        FROM history h
        INNER JOIN restaurants r ON r.restaurant_id = h.restaurant_id
        INNER JOIN menus m ON m.menu_id = h.menu_id
        WHERE h.user_id = :user_id
        ORDER BY h.eaten_at DESC, h.history_id DESC
        LIMIT :lim
    ");

    $stmt->bindValue(":user_id", (int)$user["user_id"], PDO::PARAM_INT);
    $stmt->bindValue(":lim", $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "message" => "ดึงประวัติการกินสำเร็จ",
        "count" => count($rows),
        "history" => $rows
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ดึงประวัติการกินไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>