<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth.php";

$user = requireAuth($pdo);

$limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 100;
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;

try {
    $stmt = $pdo->prepare("
        SELECT
            s.saved_id,
            s.restaurant_id,
            r.restaurant_name,
            r.address,
            r.avg_price
        FROM saved s
        INNER JOIN restaurants r ON r.restaurant_id = s.restaurant_id
        WHERE s.user_id = :user_id
        ORDER BY s.saved_id DESC
        LIMIT :lim
    ");

    $stmt->bindValue(":user_id", (int)$user["user_id"], PDO::PARAM_INT);
    $stmt->bindValue(":lim", $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok" => true,
        "message" => "ดึงรายการร้านที่บันทึกไว้สำเร็จ",
        "count" => count($rows),
        "saved_restaurants" => $rows
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "ดึงรายการร้านที่บันทึกไว้ไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>  