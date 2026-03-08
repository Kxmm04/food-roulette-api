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
$avg_price = isset($input["avg_price"]) && $input["avg_price"] !== "" ? (int)$input["avg_price"] : null;

if ($restaurant_name === "") {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"กรุณาระบุ restaurant_name"], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($address === "") {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"กรุณาระบุ address"], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($avg_price === null || $avg_price <= 0) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"กรุณาระบุ avg_price ให้ถูกต้อง"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // กันซ้ำแบบง่าย: ชื่อ + ที่อยู่
    $find = $pdo->prepare("
        SELECT restaurant_id
        FROM restaurants
        WHERE restaurant_name = :name AND address = :address
        LIMIT 1
    ");
    $find->execute([
        ":name" => $restaurant_name,
        ":address" => $address
    ]);
    $existingRestaurant = $find->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();

    if ($existingRestaurant) {
        $restaurant_id = (int)$existingRestaurant["restaurant_id"];
        $createdNew = false;
    } else {
        $insR = $pdo->prepare("
            INSERT INTO restaurants (restaurant_name, address, avg_price, created_at)
            VALUES (:name, :address, :avg_price, NOW())
        ");
        $insR->execute([
            ":name" => $restaurant_name,
            ":address" => $address,
            ":avg_price" => $avg_price
        ]);

        $restaurant_id = (int)$pdo->lastInsertId();
        $createdNew = true;
    }

    // บันทึกลง saved (กันซ้ำ)
    $dup = $pdo->prepare("
        SELECT saved_id
        FROM saved
        WHERE user_id = :user_id AND restaurant_id = :restaurant_id
        LIMIT 1
    ");
    $dup->execute([
        ":user_id" => (int)$user["user_id"],
        ":restaurant_id" => $restaurant_id
    ]);
    $existingSaved = $dup->fetch(PDO::FETCH_ASSOC);

    if ($existingSaved) {
        $pdo->commit();
        echo json_encode([
            "ok" => true,
            "message" => "เคยบันทึกร้านนี้ไว้แล้ว",
            "restaurant_id" => $restaurant_id,
            "saved_id" => (int)$existingSaved["saved_id"]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $insS = $pdo->prepare("
        INSERT INTO saved (user_id, restaurant_id, created_at)
        VALUES (:user_id, :restaurant_id, NOW())
    ");
    $insS->execute([
        ":user_id" => (int)$user["user_id"],
        ":restaurant_id" => $restaurant_id
    ]);

    $saved_id = (int)$pdo->lastInsertId();

    $pdo->commit();

    http_response_code($createdNew ? 201 : 200);
    echo json_encode([
        "ok" => true,
        "message" => $createdNew ? "เพิ่มร้านและบันทึกสำเร็จ" : "บันทึกร้านสำเร็จ",
        "restaurant_id" => $restaurant_id,
        "saved_id" => $saved_id
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "บันทึกร้านไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>