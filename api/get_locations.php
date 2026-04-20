<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$file = __DIR__ . '/../data/locations.json';
$locations = [];
if (file_exists($file)) {
    $locations = json_decode(file_get_contents($file), true) ?: [];
}

echo json_encode(array_values($locations));
?>
