<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$file = __DIR__ . '/../config.json';
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
    unset($data['admin_password']);
    unset($data['api_integrations']['gemini_api_key']);
    unset($data['api_integrations']['google_tts_api_key']);
    unset($data['dashboard_token']);
    unset($data['db_host']);
    unset($data['db_name']);
    unset($data['db_user']);
    unset($data['db_pass']);

    try {
        $pdo = getDbConnection();

        // Settings (anchors, etc)
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            if ($row['setting_key'] === 'truck_check' || $row['setting_key'] === 'truck_wash') {
                $data[$row['setting_key']] = json_decode($row['setting_value'], true);
            } else if ($row['setting_key'] === 'chore_anchor') {
                $data['chore_anchor'] = $row['setting_value'];
            } else if ($row['setting_key'] === 'chore_num_indices') {
                $data['chore_num_indices'] = (int)$row['setting_value'];
            }
        }

        // Everyday Chores
        $stmt = $pdo->query("SELECT name FROM everyday_chores ORDER BY display_order");
        $data['everyday_chores'] = [];
        while ($row = $stmt->fetch()) {
            $data['everyday_chores'][] = $row['name'];
        }

        // Regular Chores
        $stmt = $pdo->query("SELECT id, name FROM chores ORDER BY display_order");
        $data['chores'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Special Chores
        $stmt = $pdo->query("SELECT details FROM special_chores");
        $data['special_chores'] = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
             $data['special_chores'][] = json_decode($row['details'], true);
        }

        // Manual Events
        $stmt = $pdo->query("SELECT details FROM manual_events");
        $data['manual_events'] = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
             $data['manual_events'][] = json_decode($row['details'], true);
        }

        // Announcements
        $stmt = $pdo->query("SELECT details FROM announcements");
        $data['announcements'] = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
             $data['announcements'][] = json_decode($row['details'], true);
        }

        // Department Info / Stations
        $stmt = $pdo->query("SELECT id, number, address, rooms_json FROM stations");
        $stations = [];
        while ($row = $stmt->fetch()) {
             $stations[] = [
                 'id' => $row['id'],
                 'number' => $row['number'],
                 'address' => $row['address'],
                 'rooms' => json_decode($row['rooms_json'], true) ?: []
             ];
        }
        $data['department_info']['stations'] = $stations;

        // Apparatus
        $stmt = $pdo->query("SELECT id, name, abbr, category, type, status FROM apparatus ORDER BY display_order");
        $data['department_info']['apparatus'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Headers
        $stmt = $pdo->query("SELECT title FROM schedule_headers ORDER BY display_order");
        $headers = [];
        while ($row = $stmt->fetch()) {
            $headers[] = $row['title'];
        }
        if (!empty($headers)) {
            $data['headers'] = $headers;
        }

    } catch (\PDOException $e) {
        // Fall back to returning what we have if DB fails
    }

    echo json_encode($data);
} else {
    echo json_encode(['error' => 'Config file missing']);
}
?>
