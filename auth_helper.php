<?php
header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Bangkok');

function getBearerToken() {
    $headers = [];

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
    }

    $auth = '';
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'authorization') {
            $auth = $v;
            break;
        }
    }

    if (!$auth) return null;

    if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function requireAuth($pdo) {
    $token = getBearerToken();

    if (!$token) {
        http_response_code(401);
        echo json_encode([
            "ok" => false,
            "message" => "กรุณาเข้าสู่ระบบ (ไม่พบ token)"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.email, t.expires_at
        FROM tokens t
        INNER JOIN users u ON u.user_id = t.user_id
        WHERE t.token = :token
          AND t.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([":token" => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            "ok" => false,
            "message" => "Token ไม่ถูกต้องหรือหมดอายุ"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $user;
}
?>