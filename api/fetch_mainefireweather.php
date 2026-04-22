<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

$cacheFile = __DIR__ . '/../data/mainefireweather_cache.json';
$cacheTime = 3600; // 1 hour default

date_default_timezone_set('America/New_York');
$currentHour = (int)date('G'); // 0-23

if (file_exists($cacheFile)) {
    $cachedData = @json_decode(file_get_contents($cacheFile), true);
    if ($cachedData && isset($cachedData['lastUpdate'])) {
        $lastUpdate = $cachedData['lastUpdate'];
        $todayStr1 = date('M j Y');
        $todayStr2 = date('M d Y');
        $isUpdatedToday = (strpos($lastUpdate, $todayStr1) !== false) || (strpos($lastUpdate, $todayStr2) !== false);

        if ($currentHour >= 8 && !$isUpdatedToday) {
            $cacheTime = 300; // 5 minutes
        }
    }
}

$forceRefresh = isset($_GET['force']) && $_GET['force'] == '1';

if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    return;
}

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

$output = json_encode([
    'classdays' => $classDays,
    'lastUpdate' => $lastUpdate
]);

if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0777, true);
}

file_put_contents($cacheFile, $output);

echo $output;
?>
