<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth.php";


function haversineKm($lat1, $lng1, $lat2, $lng2) {
    $earth = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth * $c;
}

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) $input = [];

$budget_min = (int)($input["budget_min"] ?? 0);
$budget_max = (int)($input["budget_max"] ?? 0);
$max_distance_km = (float)($input["max_distance_km"] ?? 0);

$user_lat = isset($input["user_lat"]) ? (float)$input["user_lat"] : null;
$user_lng = isset($input["user_lng"]) ? (float)$input["user_lng"] : null;

if ($budget_min < 0 || $budget_max < $budget_min) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"งบประมาณไม่ถูกต้อง"], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($max_distance_km <= 0) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"กรุณาระบุระยะทางมากกว่า 0 กม."], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($user_lat === null || $user_lng === null) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"กรุณาระบุ user_lat และ user_lng"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // ดึงเมนูจากร้านที่บันทึกไว้ + ราคาอยู่ในงบ
    $stmt = $pdo->prepare("
        SELECT
            r.restaurant_id,
            r.restaurant_name,
            r.address,
            r.lat,
            r.lng,

            m.menu_id,
            m.menu_name,
            m.price
        FROM saved s
        INNER JOIN restaurants r ON r.restaurant_id = s.restaurant_id
        INNER JOIN menus m ON m.restaurant_id = r.restaurant_id
        WHERE s.user_id = :user_id
          AND m.is_available = 1
          AND m.price BETWEEN :min_price AND :max_price
        ORDER BY r.restaurant_id ASC, m.menu_id ASC
    ");
    $stmt->execute([
        ":user_id" => (int)$user["user_id"],
        ":min_price" => $budget_min,
        ":max_price" => $budget_max
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows || count($rows) === 0) {
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบเมนูที่ตรงงบ หรือยังไม่ได้บันทึกร้าน/เมนู"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // filter ตามระยะทาง
    $candidates = [];
    foreach ($rows as $row) {
        if ($row["lat"] === null || $row["lng"] === null) continue;

        $dist = haversineKm($user_lat, $user_lng, (float)$row["lat"], (float)$row["lng"]);
        if ($dist <= $max_distance_km) {
            $candidates[] = [
                "restaurant" => [
                    "restaurant_id" => (int)$row["restaurant_id"],
                    "restaurant_name" => $row["restaurant_name"],
                    "address" => $row["address"],
                    "lat" => (float)$row["lat"],
                    "lng" => (float)$row["lng"],
                ],
                "menu" => [
                    "menu_id" => (int)$row["menu_id"],
                    "menu_name" => $row["menu_name"],
                    "price" => (int)$row["price"]
                ],
                "distance_km" => round($dist, 2)
            ];
        }
    }

    if (count($candidates) === 0) {
        echo json_encode([
            "ok" => false,
            "message" => "ไม่พบเมนูที่เข้าเงื่อนไขระยะทาง"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // สุ่ม 1 รายการ
    $pick = $candidates[random_int(0, count($candidates) - 1)];

    echo json_encode([
        "ok" => true,
        "message" => "สุ่มสำเร็จ",
        "candidate_count" => count($candidates),
        "filters" => [
            "budget_min" => $budget_min,
            "budget_max" => $budget_max,
            "max_distance_km" => $max_distance_km
        ],
        "result" => $pick
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "message" => "สุ่มเมนูไม่สำเร็จ",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>