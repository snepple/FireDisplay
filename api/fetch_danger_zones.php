<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$jsonUrl = 'https://mainefireweather.org/files/MaineWeatherZones.json';
$json = @file_get_contents($jsonUrl);

if ($json === false) {
    echo json_encode(["error" => "Failed to fetch danger zones"]);
} else {
    echo $json;
}
?>
