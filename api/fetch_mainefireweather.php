<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . "/security_check.php";
verify_dashboard_token();

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

// Bolt ⚡: Parallelize independent API calls using curl_multi to reduce latency
$mh = curl_multi_init();
$ch1 = curl_init($classDaysUrl);
$ch2 = curl_init($indexUrl);

curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_TIMEOUT, 15);
curl_setopt($ch1, CURLOPT_FAILONERROR, true);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
curl_setopt($ch2, CURLOPT_FAILONERROR, true);

curl_multi_add_handle($mh, $ch1);
curl_multi_add_handle($mh, $ch2);

$active = null;
do {
    $status = curl_multi_exec($mh, $active);
    if ($active) {
        curl_multi_select($mh);
    }
} while ($active && $status == CURLM_OK);

$classDaysJson = curl_multi_getcontent($ch1);
$indexHtml = curl_multi_getcontent($ch2);

curl_multi_remove_handle($mh, $ch1);
curl_multi_remove_handle($mh, $ch2);
curl_multi_close($mh);

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
