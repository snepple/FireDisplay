<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security_check.php";
verify_dashboard_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    die();
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    // If not JSON, try POST vars
    $input = $_POST;
}

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Request']);
    die();
}

$required_fields = ['name', 'address', 'start_time', 'end_time', 'phone'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        die();
    }
}

$uid = md5($input['address'] . time());

$new_permit = [
    'uid' => $uid,
    'name' => $input['name'],
    'address' => $input['address'],
    'person_address' => $input['person_address'] ?? $input['address'],
    'phone' => $input['phone'],
    'email' => $input['email'] ?? '',
    'burn_location_address' => $input['burn_location_address'] ?? $input['address'],
    'burn_location_property' => $input['burn_location_property'] ?? '',
    'burn_type' => $input['burn_type'] ?? 'Open Burn',
    'type' => $input['burn_type'] ?? 'Open Burn',
    'items_to_burn' => $input['items_to_burn'] ?? '',
    'expires' => date('c', strtotime($input['end_time'])),
    'created_at' => date('c'),
    'details' => 'Manually entered permit'
];

$file = __DIR__ . '/../data/permits.json';
$permits = [];
if (file_exists($file)) {
    $permits = json_decode(file_get_contents($file), true) ?: [];
}

$permits[] = $new_permit;

// Cleanup expired
$now = time();
$permits = array_filter($permits, function($p) use ($now) {
    return strtotime($p['expires']) > $now;
});

if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}

if (file_put_contents($file, json_encode(array_values($permits)))) {
    echo json_encode(['success' => true, 'permit' => $new_permit]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write data file.']);
}
?>
