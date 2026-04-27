<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security_check.php";
verify_dashboard_token();

$file = __DIR__ . '/../data/permits.json';
$permits = [];
if (file_exists($file)) {
    $permits = json_decode(file_get_contents($file), true) ?: [];
}

// Filter out expired permits before returning
$now = time();
$active_permits = array_filter($permits, function($p) use ($now) {
    return strtotime($p['expires']) > $now;
});

echo json_encode(array_values($active_permits));
?>
