<?php
// migrate_data.php
// This script reads existing data from the legacy JSON files and config.json
// and inserts it into the new MySQL database.

require_once __DIR__ . '/db.php';

try {
    $pdo = getDbConnection();

    // 1. Migrate settings and arrays from config.json
    $configFile = __DIR__ . '/../config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);

        // Settings
        $stmtSet = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        if (isset($config['truck_check'])) $stmtSet->execute(['truck_check', json_encode($config['truck_check'])]);
        if (isset($config['truck_wash'])) $stmtSet->execute(['truck_wash', json_encode($config['truck_wash'])]);
        if (isset($config['chore_anchor'])) $stmtSet->execute(['chore_anchor', $config['chore_anchor']]);
        if (isset($config['chore_num_indices'])) $stmtSet->execute(['chore_num_indices', (string)$config['chore_num_indices']]);

        // Apparatus
        if (isset($config['department_info']['apparatus'])) {
            $pdo->exec("DELETE FROM apparatus");
            $stmtApp = $pdo->prepare("INSERT INTO apparatus (id, name, abbr, category, type, status, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($config['department_info']['apparatus'] as $i => $app) {
                $stmtApp->execute([
                    $app['id'] ?? uniqid(),
                    $app['name'] ?? '',
                    $app['abbr'] ?? '',
                    $app['category'] ?? '',
                    $app['type'] ?? '',
                    $app['status'] ?? '',
                    $i
                ]);
            }
        }

        // Stations
        if (isset($config['department_info']['stations'])) {
            $pdo->exec("DELETE FROM stations");
            $stmtSt = $pdo->prepare("INSERT INTO stations (id, number, address, rooms_json) VALUES (?, ?, ?, ?)");
            foreach ($config['department_info']['stations'] as $st) {
                $stmtSt->execute([
                    $st['id'] ?? uniqid(),
                    $st['number'] ?? '',
                    $st['address'] ?? '',
                    json_encode($st['rooms'] ?? [])
                ]);
            }
        }

        // Headers
        if (isset($config['headers'])) {
            $pdo->exec("DELETE FROM schedule_headers");
            $stmtH = $pdo->prepare("INSERT INTO schedule_headers (title, display_order) VALUES (?, ?)");
            foreach ($config['headers'] as $i => $h) {
                $stmtH->execute([$h, $i]);
            }
        }

        // Everyday Chores
        if (isset($config['everyday_chores'])) {
            $pdo->exec("DELETE FROM everyday_chores");
            $stmtEC = $pdo->prepare("INSERT INTO everyday_chores (name, display_order) VALUES (?, ?)");
            foreach ($config['everyday_chores'] as $i => $c) {
                $stmtEC->execute([$c, $i]);
            }
        }

        // Chores
        if (isset($config['chores'])) {
            $pdo->exec("DELETE FROM chores");
            $stmtC = $pdo->prepare("INSERT INTO chores (id, name, display_order) VALUES (?, ?, ?)");
            foreach ($config['chores'] as $i => $c) {
                $stmtC->execute([$c['id'], $c['name'], $i]);
            }
        }

        // Special Chores
        if (isset($config['special_chores'])) {
            $pdo->exec("DELETE FROM special_chores");
            $stmtSC = $pdo->prepare("INSERT INTO special_chores (details) VALUES (?)");
            foreach ($config['special_chores'] as $sc) {
                $stmtSC->execute([json_encode($sc)]);
            }
        }

        // Manual Events
        if (isset($config['manual_events'])) {
            $pdo->exec("DELETE FROM manual_events");
            $stmtE = $pdo->prepare("INSERT INTO manual_events (details) VALUES (?)");
            foreach ($config['manual_events'] as $e) {
                $stmtE->execute([json_encode($e)]);
            }
        }

        // Announcements
        if (isset($config['announcements'])) {
            $pdo->exec("DELETE FROM announcements");
            $stmtA = $pdo->prepare("INSERT INTO announcements (details) VALUES (?)");
            foreach ($config['announcements'] as $a) {
                $stmtA->execute([json_encode($a)]);
            }
        }

        echo "Config arrays migrated to DB.\n";
    }

    // 2. Migrate Fire Danger
    $dangerFile = __DIR__ . '/../data/fire_danger.json';
    if (file_exists($dangerFile)) {
        $stmtSet = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmtSet->execute(['fire_danger', file_get_contents($dangerFile)]);
        echo "Fire danger migrated to DB.\n";
    }

    // 3. Migrate Permits
    $permitsFile = __DIR__ . '/../data/permits.json';
    if (file_exists($permitsFile)) {
        $permits = json_decode(file_get_contents($permitsFile), true);
        if (is_array($permits) && count($permits) > 0) {
            $pdo->exec("DELETE FROM permits");
            $stmtP = $pdo->prepare("INSERT INTO permits (permit_number, address, expires, details, created_at) VALUES (?, ?, ?, ?, ?)");
            foreach ($permits as $p) {
                $stmtP->execute([
                    $p['uid'] ?? uniqid(),
                    $p['address'] ?? 'Unknown',
                    date('Y-m-d H:i:s', strtotime($p['expires'])),
                    json_encode($p),
                    date('Y-m-d H:i:s', strtotime($p['created_at'] ?? 'now'))
                ]);
            }
            echo "Permits migrated to DB.\n";
        }
    }

    // 4. Migrate Locations
    $locFile = __DIR__ . '/../data/locations.json';
    if (file_exists($locFile)) {
        $locs = json_decode(file_get_contents($locFile), true);
        if (is_array($locs) && count($locs) > 0) {
            $pdo->exec("DELETE FROM locations");
            $stmtL = $pdo->prepare("INSERT INTO locations (lat, lng, name) VALUES (?, ?, ?)");
            foreach ($locs as $l) {
                if (isset($l['lat']) && $l['lng']) {
                    $stmtL->execute([
                        $l['lat'],
                        $l['lng'],
                        $l['name'] ?? ''
                    ]);
                }
            }
            echo "Locations migrated to DB.\n";
        }
    }

    echo "Migration completed successfully!\n";

} catch (\PDOException $e) {
    die("Database migration failed: " . $e->getMessage() . "\n");
}
