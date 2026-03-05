<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth_helper.php";

$user = requireAuth($pdo);

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$restaurant_name = trim($input["restaurant_name"] ?? "");
$address = trim($input["address"] ?? "");
$lat = isset($input["lat"]) ? (float)$input["lat"] : null;
$lng = isset($input["lng"]) ? (float)$input["lng"] : null;
$avg_price = isset($input["avg_price"]) && $input["avg_price"] !== "" ? (int)$input["avg_price"] : null;

if ($restaurant_name === "") {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"กรุณาระบุ restaurant_name"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // หาใน restaurants ก่อน (กันซ้ำ: ชื่อ + พิกัด)
    $stmtFind = $pdo->prepare("
        SELECT restaurant_id
        FROM restaurants
        WHERE restaurant_name = :name
          AND ((:lat IS NULL AND lat IS NULL) OR lat = :lat)
          AND ((:lng IS NULL AND lng IS NULL) OR lng = :lng)
        LIMIT 1
    ");
    $stmtFind->execute([
        ":name" => $restaurant_name,
        ":lat" => $lat,
        ":lng" => $lng
    ]);
    $existing = $stmtFind->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    if ($existing) {
        $restaurant_id = (int)$existing["restaurant_id"];
    } else {
        $insR = $pdo->prepare("
            INSERT INTO restaurants (restaurant_name, address, lat, lng, avg_price)
            VALUES (:name, :address, :lat, :lng, :avg_price)
        ");
        $insR->execute([
            ":name" => $restaurant_name,
            ":address" => ($address !== "" ? $address : null),
            ":lat" => $lat,
            ":lng" => $lng,
            ":avg_price" => $avg_price
        ]);
        $restaurant_id = (int)$pdo->lastInsertId();
    }

    // บันทึกลง saved (กันซ้ำ)
    $chk = $pdo->prepare("
        SELECT saved_id FROM saved
        WHERE user_id = :user_id AND restaurant_id = :restaurant_id
        LIMIT 1
    ");
    $chk->execute([
        ":user_id" => (int)$user["user_id"],
        ":restaurant_id" => $restaurant_id
    ]);
    $saved = $chk->fetch(PDO::FETCH_ASSOC);

    if ($saved) {
        $saved_id = (int)$saved["saved_id"];
        $already = true;
    } else {
        $insS = $pdo->prepare("
            INSERT INTO saved (user_id, restaurant_id)
            VALUES (:user_id, :restaurant_id)
        ");
        $insS->execute([
            ":user_id" => (int)$user["user_id"],
            ":restaurant_id" => $restaurant_id
        ]);
        $saved_id = (int)$pdo->lastInsertId();
        $already = false;
    }

    $pdo->commit();

    echo json_encode([
        "ok" => true,
        "message" => $already ? "ร้านนี้ถูกบันทึกไว้แล้ว" : "เพิ่มร้านจากแมพและบันทึกสำเร็จ",
        "restaurant_id" => $restaurant_id,
        "saved_id" => $saved_id
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "ok"=>false,
        "message"=>"เพิ่มร้านจากแมพไม่สำเร็จ",
        "error"=>$e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>