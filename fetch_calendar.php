<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain; charset=utf-8");

$url = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($url)) {
    die("Error: No URL provided.");
}

// Use cURL instead of file_get_contents for better compatibility on Bluehost
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124');

$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$content) {
    echo "Error: Could not fetch calendar. HTTP Code: " . $httpCode;
} else {
    echo $content;
}
?>