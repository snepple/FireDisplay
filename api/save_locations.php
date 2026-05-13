<?php
ini_set('session.use_strict_mode', 1);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();
header("Content-Type: application/json; charset=utf-8");

// Ensure user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    die();
}

// CSRF check
$input = json_decode(file_get_contents("php://input"), true);
if (!$input || empty($input['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing CSRF token.']);
    die();
}

if (!isset($input['locations']) || !is_array($input['locations'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid locations data.']);
    die();
}

$file = __DIR__ . '/../data/locations.json';

// Save locations
if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}

if (file_put_contents($file, json_encode($input['locations'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write data file.']);
}
?>
