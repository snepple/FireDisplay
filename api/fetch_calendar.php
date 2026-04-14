<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain; charset=utf-8");

$url = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($url)) {
    http_response_code(400);
    die("Error: No URL provided.");
}

// Security: Prevent SSRF by checking against a whitelist
$configFile = __DIR__ . '/../config.json';
$allowedUrls = [
    'https://calendar.google.com/calendar/ical/c303c9aa08e0a090db126a0b15eb0bc0e8b66cc1af810aa971059b7b01b6d25a@group.calendar.google.com/public/basic.ics',
    'https://calendar.google.com/calendar/ical/permitsburn@gmail.com/public/basic.ics',
    'https://calendar.google.com/calendar/ical/amarshall@oaklandme.gov/public/basic.ics',
    'https://calendars.icloud.com/holidays/us_en-us.ics'
];

if (file_exists($configFile)) {
    $configData = json_decode(file_get_contents($configFile), true);
    if (isset($configData['calendar_urls']) && is_array($configData['calendar_urls'])) {
        foreach ($configData['calendar_urls'] as $key => $val) {
            if (!empty($val)) {
                $allowedUrls[] = $val;
            }
        }
    }
}

$isAllowed = false;

// Remove any query string from the requested URL for comparison
$requestedBaseUrl = explode('?', $url)[0];

foreach ($allowedUrls as $allowedUrl) {
    // Also remove query string from allowed URL just in case
    $allowedBaseUrl = explode('?', $allowedUrl)[0];

    if ($requestedBaseUrl === $allowedBaseUrl) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    die("Error: Requested URL is not allowed.");
}

// Ensure the data directory exists
$cacheDir = __DIR__ . '/../data';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// Check for cached calendar
$cacheFile = $cacheDir . '/calendar_cache_' . md5($url) . '.ics';
$cacheTime = 900; // 15 minutes

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    return;
}

// Use cURL instead of file_get_contents for better compatibility on Bluehost
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124');
curl_setopt($ch, CURLOPT_ENCODING, "");

$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$content) {
    echo "Error: Could not fetch calendar. HTTP Code: " . $httpCode;
} else {
    $content = str_replace("\r\n", "\n", $content);
    $content = str_replace("\n", "\r\n", $content);

    // Save to cache
    file_put_contents($cacheFile, $content);

    echo $content;
}
?>
