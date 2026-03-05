<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

require_once "config.php";
require_once "auth_helper.php";

$user = requireAuth($pdo);

$lat = isset($_GET["lat"]) ? (float)$_GET["lat"] : null;
$lng = isset($_GET["lng"]) ? (float)$_GET["lng"] : null;
$radius_km = isset($_GET["radius_km"]) ? (float)$_GET["radius_km"] : 1.5; // default 1.5km
$limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 30;

if ($lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode(["ok"=>false,"message"=>"ต้องส่ง lat และ lng"], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($radius_km <= 0) $radius_km = 1.5;
if ($radius_km > 5) $radius_km = 5; // กันหนักเกินไป
if ($limit <= 0) $limit = 30;
if ($limit > 50) $limit = 50;

try {
    // แปลง radius เป็น delta โดยประมาณ (พอสำหรับค้นหา)
    $delta = $radius_km / 111.0; // 1 องศา ~ 111km
    $left = $lng - $delta;
    $right = $lng + $delta;
    $top = $lat + $delta;
    $bottom = $lat - $delta;

    // ค้นหาด้วย nominatim (bounded viewbox)
    $q = "restaurant"; // เน้นร้านอาหารก่อน
    $url = "https://nominatim.openstreetmap.org/search?"
        . "format=jsonv2"
        . "&q=" . urlencode($q)
        . "&bounded=1"
        . "&limit=" . urlencode((string)$limit)
        . "&viewbox=" . urlencode($left . "," . $top . "," . $right . "," . $bottom);

    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: FoodRouletteApp/1.0 (student project)\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $context);

    if ($raw === false) {
        http_response_code(502);
        echo json_encode(["ok"=>false,"message"=>"เรียก OSM ไม่สำเร็จ"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(502);
        echo json_encode(["ok"=>false,"message"=>"OSM ส่งข้อมูลผิดรูปแบบ"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // จัดรูปให้แอพใช้สะดวก
    $places = [];
    foreach ($data as $p) {
        $name = trim((string)($p["name"] ?? ""));
        $display = (string)($p["display_name"] ?? "");
        if ($name === "" && $display !== "") {
            $name = trim(explode(",", $display)[0]);
        }
        if ($name === "") $name = "ร้านจากแผนที่";

        $places[] = [
            "place_id" => (string)($p["place_id"] ?? ""),
            "name" => $name,
            "address" => $display,
            "lat" => isset($p["lat"]) ? (float)$p["lat"] : null,
            "lng" => isset($p["lon"]) ? (float)$p["lon"] : null,
        ];
    }

    echo json_encode([
        "ok" => true,
        "message" => "ดึงร้านจากแมพสำเร็จ",
        "count" => count($places),
        "places" => $places
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"message"=>"เกิดข้อผิดพลาด","error"=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
?>