<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$files = [
    'fire_danger' => __DIR__ . '/../data/fire_danger.json',
    'permits' => __DIR__ . '/../data/permits.json',
    'mainefireweather' => __DIR__ . '/../data/mainefireweather_cache.json'
];

$timestamps = [];

foreach ($files as $key => $file) {
    if (file_exists($file)) {
        $timestamps[$key] = filemtime($file);
    } else {
        $timestamps[$key] = 0;
    }
}

echo json_encode($timestamps);
?>
