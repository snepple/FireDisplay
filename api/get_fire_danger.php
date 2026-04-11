<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$file = __DIR__ . '/../data/fire_danger.json';
if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode(["level" => "Unknown", "updated_at" => ""]);
}
?>
