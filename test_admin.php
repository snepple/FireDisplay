<?php
// Validate what we did
$file = 'admin.php';
$content = file_get_contents($file);
$hasSession = strpos($content, "session_start();");
$hasDB = strpos($content, "require_once __DIR__ . '/api/db.php';");
echo "Session Start Position: $hasSession\n";
echo "DB hook position: $hasDB\n";
