<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security_check.php";
verify_dashboard_token();

$file = __DIR__ . '/../data/fire_danger.json';
if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode(["level" => "Unknown", "updated_at" => ""]);
}
?>
