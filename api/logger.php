<?php

function sys_log($component, $message, $status = 'info', $details = []) {
    $logFile = __DIR__ . '/../data/system.log';
    $maxSize = 5 * 1024 * 1024; // 5 MB

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'component' => $component,
        'message' => $message,
        'status' => $status, // 'info', 'warning', 'error', 'success'
        'details' => $details
    ];

    $logJson = json_encode($logEntry) . "\n";

    // Create file if it doesn't exist
    if (!file_exists($logFile)) {
        file_put_contents($logFile, '');
        chmod($logFile, 0666);
    }

    // Check size and rotate if needed
    if (filesize($logFile) > $maxSize) {
        $backupFile = $logFile . '.old';
        rename($logFile, $backupFile);
        file_put_contents($logFile, '');
        chmod($logFile, 0666);
    }

    // Append to log
    file_put_contents($logFile, $logJson, FILE_APPEND);
}
