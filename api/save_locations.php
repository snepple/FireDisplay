<?php
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
if (!$input || empty($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing CSRF token.']);
    die();
}

if (!isset($input['locations']) || !is_array($input['locations'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid locations data.']);
    die();
}

require_once __DIR__ . '/db.php';

try {
    $pdo = getDbConnection();

    // Clear existing locations
    $pdo->exec("DELETE FROM locations");

    // Insert new locations
    $stmt = $pdo->prepare("INSERT INTO locations (lat, lng, name) VALUES (?, ?, ?)");
    foreach ($input['locations'] as $loc) {
        if (isset($loc['lat']) && isset($loc['lng'])) {
            $stmt->execute([
                $loc['lat'],
                $loc['lng'],
                $loc['name'] ?? ''
            ]);
        }
    }
    echo json_encode(['success' => true]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
