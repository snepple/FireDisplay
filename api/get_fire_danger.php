<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once __DIR__ . '/db.php';

$default = ['level' => 'Unknown', 'updated_at' => ''];

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['fire_danger']);
    $row = $stmt->fetch();

    if ($row && $row['setting_value']) {
        echo $row['setting_value']; // Already JSON string
    } else {
        echo json_encode($default);
    }
} catch (\PDOException $e) {
    echo json_encode($default);
}
?>
