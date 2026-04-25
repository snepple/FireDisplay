<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Disable output buffering
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
while (@ob_end_flush());
ob_implicit_flush(true);

$files_to_watch = [
    'permits' => __DIR__ . '/../data/permits.json',
    'fire_danger' => __DIR__ . '/../data/fire_danger.json',
    'mainefireweather' => __DIR__ . '/../data/mainefireweather_cache.json'
];

// Get initial modification times
$initial_mtimes = [];
foreach ($files_to_watch as $key => $file) {
    clearstatcache(true, $file);
    $initial_mtimes[$key] = file_exists($file) ? filemtime($file) : 0;
}

$timeout = 25; // seconds
$start_time = time();

while (true) {
    $changed = [];
    foreach ($files_to_watch as $key => $file) {
        clearstatcache(true, $file);
        $current_mtime = file_exists($file) ? filemtime($file) : 0;
        if ($current_mtime !== $initial_mtimes[$key]) {
            $changed[] = $key;
        }
    }

    if (!empty($changed)) {
        echo "event: update\n";
        echo "data: " . json_encode(["changed" => $changed]) . "\n\n";
        flush();
        break; // Exit after sending update
    }

    if (time() - $start_time >= $timeout) {
        // Send a ping/timeout event to keep connection alive or let client reconnect
        echo "event: timeout\n";
        echo "data: {}\n\n";
        flush();
        break;
    }

    sleep(1);
}
