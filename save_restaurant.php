<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth.php";

$user = requireAuth($pdo);

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$restaurant_name = trim($input["restaurant_name"] ?? "");
$address = trim($input["address"] ?? "");
$avg_price = (int)($input["avg_price"] ?? 0);
$lat = isset($input["lat"]) ? (float)$input["lat"] : null;
$lng = isset($input["lng"]) ? (float)$input["lng"] : null;

if ($restaurant_name === "") {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุชื่อร้าน"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($address === "") {
    $address = "-";
}

if ($avg_price <= 0) {
    $avg_price = 50;
}

if ($lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => "กรุณาระบุ lat และ lng"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->beginTransaction();

    // เช็คร้านซ้ำในตาราง restaurants
    $checkRestaurant = $pdo->prepare("
        SELECT restaurant_id
        FROM restaurants
        WHERE restaurant_name = :restaurant_name
          AND address = :address
        LIMIT 1
    ");
    $checkRestaurant->execute([
        ":restaurant_name" => $restaurant_name,
        ":address" => $address
    ]);
    $restaurant = $checkRestaurant->fetch(PDO::FETCH_ASSOC);

    if ($restaurant) {
        $restaurant_id = (int)$restaurant["restaurant_id"];

        // อัปเดตพิกัด/ราคาเผื่อของเดิมยังไม่มี
        $updateRestaurant = $pdo->prepare("
            UPDATE restaurants
            SET avg_price = :avg_price,
                lat = :lat,
                lng = :lng
            WHERE restaurant_id = :restaurant_id
        ");
        $updateRestaurant->execute([
            ":avg_price" => $avg_price,
            ":lat" => $lat,
            ":lng" => $lng,
            ":restaurant_id" => $restaurant_id
        ]);
    } else {
        // เพิ่มร้านใหม่
        $insertRestaurant = $pdo->prepare("
            INSERT INTO restaurants (
                restaurant_name,
                address,
                avg_price,
                lat,
                lng
            ) VALUES (
                :restaurant_name,
                :address,
                :avg_price,
                :lat,
                :lng
            )
        ");
        $insertRestaurant->execute([
            ":restaurant_name" => $restaurant_name,
            ":address" => $address,
            ":avg_price" => $avg_price,
            ":lat" => $lat,
            ":lng" => $lng
        ]);

        $restaurant_id = (int)$pdo->lastInsertId();
    }

    // เช็กว่าผู้ใช้บันทึกร้านนี้แล้วหรือยัง
    $checkSaved = $pdo->prepare("
        SELECT saved_id
        FROM saved
        WHERE user_id = :user_id
          AND restaurant_id = :restaurant_id
        LIMIT 1
    ");
    $checkSaved->execute([
        ":user_id" => (int)$user["user_id"],
        ":restaurant_id" => $restaurant_id
    ]);
    $saved = $checkSaved->fetch(PDO::FETCH_ASSOC);

    if ($saved) {
        $pdo->commit();
        echo json_encode([
            "ok" => true,
            "message" => "คุณบันทึกร้านนี้ไว้แล้ว",
            "restaurant_id" => $restaurant_id
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // เพิ่มความสัมพันธ์ user กับร้าน
    $insertSaved = $pdo->prepare("
        INSERT INTO saved (user_id, restaurant_id)
        VALUES (:user_id, :restaurant_id)
    ");
    $insertSaved->execute([
        ":user_id" => (int)$user["user_id"],
        ":restaurant_id" => $restaurant_id
    ]);

    $pdo->commit();

    echo json_encode([
        "ok" => true,
        "message" => "บันทึกร้านสำเร็จ",
        "restaurant_id" => $restaurant_id
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "บันทึกร้านไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>