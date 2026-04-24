<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/db.php';

try {
    $pdo = getDbConnection();

    $stmt = $pdo->query("SELECT lat, lng, name FROM locations");
    $locations = $stmt->fetchAll();

    echo json_encode($locations);
} catch (\PDOException $e) {
    echo json_encode([]);
}
?>
