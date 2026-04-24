<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/db.php';

try {
    $pdo = getDbConnection();

    // Cleanup expired first
    $cleanup = $pdo->prepare("DELETE FROM permits WHERE expires <= ?");
    $cleanup->execute([date('Y-m-d H:i:s')]);

        $stmt = $pdo->prepare("SELECT details FROM permits WHERE expires > ? ORDER BY expires ASC");
    $stmt->execute([date('Y-m-d H:i:s')]);
    $permits = [];
    while ($row = $stmt->fetch()) {
        $permits[] = json_decode($row['details'], true);
    }

    echo json_encode($permits);
} catch (\PDOException $e) {
    echo json_encode([]);
}
?>
