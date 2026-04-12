<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$classDaysUrl = 'https://mainefireweather.org/admin/php/get-content.php?content=class-days&which=map';
$indexUrl = 'https://mainefireweather.org/index.php';

$classDaysJson = @file_get_contents($classDaysUrl);
$indexHtml = @file_get_contents($indexUrl);

$classDays = [];
if ($classDaysJson) {
    $data = json_decode($classDaysJson, true);
    if (isset($data['classdays'])) {
        $classDays = $data['classdays'];
    }
}

$lastUpdate = 'Unknown';
if ($indexHtml) {
    if (preg_match('/Last Update:\s*<span[^>]*>(.*?)<\/span>/i', $indexHtml, $matches)) {
        $lastUpdate = trim($matches[1]);
    }
}

echo json_encode([
    'classdays' => $classDays,
    'lastUpdate' => $lastUpdate
]);
?>
