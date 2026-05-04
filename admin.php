<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$configFile = 'config.json';

// --- DEFAULT CONFIGURATION ---
$defaultConfig = [
    "admin_password" => '$2y$10$OdIhGvKsJVEAXe.ZA49dEunbChz4k/DiVCUZ3IL5szyvGkA89XrIG',
    "dashboard_settings" => [
        "theme" => "dark",
        "pages" => [
            "dashboard" => ["enabled" => true, "duration" => 15],
            "calendar" => ["enabled" => true, "duration" => 15],
            "chores" => ["enabled" => true, "duration" => 15]
        ]
    ],
    "fire_danger_zone" => "7",
    "department_info" => [
        "name" => "Oakland Fire Department",
        "stations" => [
            ["id" => uniqid(), "number" => "Station 1", "address" => "11 Fairfield St", "rooms" => ["Community Room", "Training Room"]]
        ],
        "apparatus" => [
            ["id" => uniqid(), "name" => "9-6", "abbr" => "9-6", "category" => "EMS", "type" => "Ambulance", "status" => "In service"],
            ["id" => uniqid(), "name" => "900", "abbr" => "900", "category" => "Fire", "type" => "Chief", "status" => "In service"],
            ["id" => uniqid(), "name" => "Engine 2", "abbr" => "E2", "category" => "Fire", "type" => "Engine", "status" => "In service"],
            ["id" => uniqid(), "name" => "Engine 3", "abbr" => "E3", "category" => "Fire", "type" => "Engine", "status" => "In service"],
            ["id" => uniqid(), "name" => "Engine 4", "abbr" => "E4", "category" => "Fire", "type" => "Engine", "status" => "In service"],
            ["id" => uniqid(), "name" => "Engine 5", "abbr" => "E5", "category" => "Fire", "type" => "Engine", "status" => "In service"],
            ["id" => uniqid(), "name" => "Ladder 1", "abbr" => "L1", "category" => "Fire", "type" => "Ladder", "status" => "In service"],
            ["id" => uniqid(), "name" => "Marine 9", "abbr" => "M9", "category" => "Utility", "type" => "Rescue unit", "status" => "In service"],
            ["id" => uniqid(), "name" => "Rescue 4", "abbr" => "R4", "category" => "Fire", "type" => "Rescue unit", "status" => "In service"],
            ["id" => uniqid(), "name" => "Rescue 9", "abbr" => "R9", "category" => "Fire", "type" => "Rescue unit", "status" => "In service"]
        ]
    ],
    "headers" => ["9-1", "9-2", "9-3", "9-4", "9-5", "9-6<br>Meds", "R4<br>R9"],
    "truck_check" => ["anchor" => "2025-07-13", "interval" => 2],
    "truck_wash" => ["anchor" => "2025-07-20", "interval" => 2],
    "chore_anchor" => "2025-07-13",
    "chore_num_indices" => 6,
    "everyday_chores" => ["Clean Bathrooms", "Empty Trash Cans", "Wash Coffee Pot and Dishes"],
    "chores" => [
        ["id" => 1, "name" => "Kitchen"], ["id" => 2, "name" => "Dispatch/Vacuum/Offices/Bunks"],
        ["id" => 3, "name" => "Entries/Halls/Windows"], ["id" => 4, "name" => "Bay Floors"],
        ["id" => 5, "name" => "Tool/Gear/Compressor/Gym"], ["id" => 6, "name" => "Atlantic/Stand-by"]
    ],
    "special_chores" => [],
    "manual_events" => [],
    "announcements" => []
];

if (!file_exists($configFile)) file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));

$fileMtime = filemtime($configFile);
if (isset($_SESSION['config_cache']) && isset($_SESSION['config_mtime']) && $_SESSION['config_mtime'] === $fileMtime) {
    $decodedConfig = $_SESSION['config_cache'];
} else {
    $decodedConfig = json_decode(file_get_contents($configFile), true);
    $_SESSION['config_cache'] = $decodedConfig;
    $_SESSION['config_mtime'] = $fileMtime;
}

$configData = array_merge($defaultConfig, $decodedConfig);

// Ensure new keys exist if updating from older version
if (!isset($configData['department_info'])) $configData['department_info'] = $defaultConfig['department_info'];
if (!isset($configData['chore_num_indices'])) $configData['chore_num_indices'] = 6;
if (!isset($configData['special_chores'])) $configData['special_chores'] = [];
if (!isset($configData['dashboard_settings'])) $configData['dashboard_settings'] = $defaultConfig['dashboard_settings'];
if (!isset($configData['dashboard_settings']['pages'])) $configData['dashboard_settings']['pages'] = $defaultConfig['dashboard_settings']['pages'];
if (!isset($configData['fire_danger_zone'])) $configData['fire_danger_zone'] = $defaultConfig['fire_danger_zone'];

$todayStr = date('Y-m-d');

// Split Events
$active_events = []; $archived_events = [];
foreach ($configData['manual_events'] as $evt) {
    $isArchived = false;
    if ($evt['recurrence'] === 'none') { if ($evt['start_date'] < $todayStr) $isArchived = true; }
    else if ($evt['end_type'] === 'date' && !empty($evt['end_date_bound'])) { if ($evt['end_date_bound'] < $todayStr) $isArchived = true; }
    if ($isArchived) $archived_events[] = $evt; else $active_events[] = $evt;
}

// Split Special Chores
$active_chores = []; $archived_chores = [];
foreach ($configData['special_chores'] as $sc) {
    $isArchived = false;
    if ($sc['recurrence'] === 'none') { if ($sc['start_date'] < $todayStr) $isArchived = true; }
    else if ($sc['end_type'] === 'date' && !empty($sc['end_date_bound'])) { if ($sc['end_date_bound'] < $todayStr) $isArchived = true; }
    if ($isArchived) $archived_chores[] = $sc; else $active_chores[] = $sc;
}

// Split Announcements
$active_announcements = []; $archived_announcements = [];
foreach ($configData['announcements'] as $ann) {
    if ($ann['end_date'] < $todayStr) $archived_announcements[] = $ann;
    else $active_announcements[] = $ann;
}

// Prepare Rooms Datalist
$allRooms = [];
foreach ($configData['department_info']['stations'] as $st) {
    foreach ($st['rooms'] as $r) { $allRooms[] = $st['number'] . ' - ' . $r; }
}
$roomsJson = json_encode($allRooms, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Prepare Apparatus Dropdown Options
$appOptionsHtml = "<option value=''>-- Select Apparatus --</option>";
foreach($configData['department_info']['apparatus'] as $app) {
    $appOptionsHtml .= "<option value='" . htmlspecialchars($app['abbr']) . "'>" . htmlspecialchars($app['abbr']) . "</option>";
}


// --- HANDLE LOGIN / LOGOUT ---
if (isset($_POST['login'])) {
<<<<<<< HEAD
    if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
=======
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
>>>>>>> 8fa3497 (temp)
        die("CSRF token validation failed.");
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attemptsFile = __DIR__ . '/data/login_attempts.json';
    $attemptsData = [];
    if (file_exists($attemptsFile)) {
        $attemptsData = json_decode(file_get_contents($attemptsFile), true) ?: [];
    }

    // Clean up old attempts (> 15 minutes)
    $now = time();
    $lockoutDuration = 900; // 15 minutes
    $maxAttempts = 5;

    foreach ($attemptsData as $storedIp => $data) {
        if ($now - $data['last_attempt'] > $lockoutDuration) {
            unset($attemptsData[$storedIp]);
        }
    }

    $currentIpData = $attemptsData[$ip] ?? ['count' => 0, 'last_attempt' => 0];

    if ($currentIpData['count'] >= $maxAttempts && ($now - $currentIpData['last_attempt']) < $lockoutDuration) {
        $error = "Too many failed attempts. Please try again later.";
    } else {
        $is_valid = false;
        $needs_rehash = false;

        if (password_verify($_POST['password'], $configData['admin_password'])) {
            $is_valid = true;
            if (password_needs_rehash($configData['admin_password'], PASSWORD_DEFAULT)) {
                $needs_rehash = true;
            }
        } elseif ($_POST['password'] === $configData['admin_password']) {
            $is_valid = true;
            $needs_rehash = true;
        }

        if ($is_valid) {
            // Reset attempts on successful login
            unset($attemptsData[$ip]);
            file_put_contents($attemptsFile, json_encode($attemptsData, JSON_PRETTY_PRINT));

            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            if ($needs_rehash) {
                $configData['admin_password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
            }
            header("Location: admin.php"); exit;
        } else {
            $error = "Invalid Password";
            $currentIpData['count']++;
            $currentIpData['last_attempt'] = $now;
            $attemptsData[$ip] = $currentIpData;
            file_put_contents($attemptsFile, json_encode($attemptsData, JSON_PRETTY_PRINT));
        }
    }
}
if (isset($_GET['logout'])) {
    if (empty($_SESSION['csrf_token']) || empty($_GET['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_GET['csrf_token'])) {
        die("CSRF token validation failed.");
    }
    session_destroy(); header("Location: admin.php"); exit;
}

// --- SECURE AREA ---
if (!isset($_SESSION['admin_logged_in'])) {
    echo "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        :root {
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --text-color: #1d1d1f;
            --muted-text: #86868b;
            --border-color: #e5e5ea;
            --border-input: #d2d2d7;
            --hover-bg: #eef2ff;
            --primary-color: #007aff;
            --danger-color: #ff3b30;
            --success-color: #34c759;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #1c1c1c;
                --card-bg: #1C212B;
                --text-color: #fff;
                --muted-text: #cbd5e1;
                --border-color: #30363d;
                --border-input: #30363d;
                --hover-bg: #30363d;
                --primary-color: #0a84ff;
                --danger-color: #ff453a;
                --success-color: #32d74b;
            }
        }

        button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
</style></head><body style='font-family: \"Inter\", sans-serif; background: var(--bg-color); color: var(--text-color); display: flex; justify-content: center; align-items: center; height: 100vh; margin:0;'>";
    echo "<form method='POST' style='background: var(--card-bg); padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); width: 100%; max-width: 320px;'>";
    echo "<h2 style='margin-top:0; color: var(--text-color); text-align: center; font-weight: 600;'>Admin Login</h2>";
    if (isset($error)) echo "<p role='alert' style='color: var(--danger-color); font-weight: bold; text-align: center; font-size: 0.9em; margin-bottom: 15px;'>$error</p>";
    echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token']) . "'>";
    echo "<label for='login-password' style='display:block; text-align:left; margin-bottom:8px; font-weight:600; font-size:14px;'>Password</label>";
    echo "<input type='password' id='login-password' name='password' placeholder='Enter password' required style='padding: 12px; margin-bottom: 20px; width: 100%; box-sizing: border-box; background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 8px; font-size: 16px;'><br>";
    echo "<button type='submit' name='login' style='padding: 12px; width: 100%; cursor: pointer; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 16px;'>Login</button>";
    echo "</form></body></html>"; exit;
}

// --- API ENDPOINT ---
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $exportData = $configData;
    unset($exportData['admin_password']);
    unset($exportData['api_integrations']['gemini_api_key']);
    unset($exportData['api_integrations']['google_tts_api_key']);
    unset($exportData['dashboard_token']);
    echo json_encode($exportData);
    exit;
}


// --- HANDLE SAVES ---
$page = $_GET['page'] ?? 'settings';
$success = ""; $error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
<<<<<<< HEAD
    if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
=======
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
>>>>>>> 8fa3497 (temp)
        die("CSRF token validation failed.");
    }

    if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
        $logFile = __DIR__ . '/data/system.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            $success = "System logs cleared successfully.";
        } else {
            $error_msg = "Log file does not exist.";
        }
    }


    if (isset($_POST['save_settings'])) {
        $configData['dashboard_settings']['theme'] = $_POST['dash_theme'];

        // Update page rotation settings
        $pagesConfig = [
            'dashboard' => ['enabled' => isset($_POST['page_dashboard_enabled']), 'duration' => max(1, (int)($_POST['page_dashboard_duration'] ?? 15))],
            'calendar'  => ['enabled' => isset($_POST['page_calendar_enabled']),  'duration' => max(1, (int)($_POST['page_calendar_duration'] ?? 15))],
            'chores'    => ['enabled' => isset($_POST['page_chores_enabled']),    'duration' => max(1, (int)($_POST['page_chores_duration'] ?? 15))]
        ];
        $configData['dashboard_settings']['pages'] = $pagesConfig;
        $configData['dashboard_settings']['audio_enabled'] = isset($_POST['audio_enabled']);
        $configData['dashboard_settings']['tts_enabled'] = isset($_POST['tts_enabled']);

        $audioDir = __DIR__ . '/assets/audio/';
        if (!is_dir($audioDir)) {
            @mkdir($audioDir, 0777, true);
        }

        $allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/x-wav'];
        $allowedExts = ['mp3', 'wav'];

        foreach (['alert_audio_fire', 'alert_audio_permit'] as $fileKey) {
            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$fileKey]['tmp_name'];
                $name = basename($_FILES[$fileKey]['name']);
                $type = mime_content_type($tmpName);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (in_array($type, $allowedTypes) && in_array($ext, $allowedExts)) {
                    $safeName = $fileKey . '_' . time() . '.' . $ext;
                    $dest = $audioDir . $safeName;
                    if (move_uploaded_file($tmpName, $dest)) {
                        // Delete old file if exists
                        if (!empty($configData['dashboard_settings'][$fileKey])) {
                            $oldPath = __DIR__ . '/' . $configData['dashboard_settings'][$fileKey];
                            if (file_exists($oldPath) && is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $configData['dashboard_settings'][$fileKey] = 'assets/audio/' . $safeName;
                    }
                }
            }
        }

        $configData['dashboard_token'] = $_POST['dashboard_token'];

        $oldZone = $configData['fire_danger_zone'] ?? '8';
        $newZone = $_POST['fire_danger_zone'] ?? '8';
        $configData['fire_danger_zone'] = $newZone;

        if ($oldZone !== $newZone) {
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $classDaysJson = @file_get_contents('https://mainefireweather.org/admin/php/get-content.php?content=class-days&which=map', false, $ctx);
            if ($classDaysJson) {
                $data = json_decode($classDaysJson, true);
                if (isset($data['classdays'][$newZone])) {
                    $levelInt = (int)$data['classdays'][$newZone];
                    $levelsMap = [1 => "Snow Cover", 2 => "Low", 3 => "Moderate", 4 => "High", 5 => "Very High", 6 => "Extreme"];
                    if (isset($levelsMap[$levelInt])) {
                        $dangerData = ['level' => $levelsMap[$levelInt], 'updated_at' => date('c')];
                        file_put_contents(__DIR__ . '/data/fire_danger.json', json_encode($dangerData));
                    }
                }
            }
        }

        $configData['calendar_urls']['main'] = $_POST['cal_main'] ?? '';
        $configData['calendar_urls']['burn_permits'] = $_POST['cal_burn_permits'] ?? '';
        $configData['calendar_urls']['town_meetings'] = $_POST['cal_town_meetings'] ?? '';
        $configData['calendar_urls']['holidays'] = $_POST['cal_holidays'] ?? '';

        $success = "Dashboard Settings Saved.";
    }
    elseif (isset($_POST['save_dept_info'])) {
        $configData['department_info']['name'] = trim($_POST['dept_name']);
        $stations = [];
        if (isset($_POST['st_id'])) {
            $stCount = count($_POST['st_id']);
            for ($i=0; $i<$stCount; $i++) {
                $roomsArr = json_decode($_POST['st_rooms_json'][$i], true) ?: [];
                $stations[] = [
                    "id" => $_POST['st_id'][$i] ?: uniqid(),
                    "number" => trim($_POST['st_number'][$i]),
                    "address" => trim($_POST['st_address'][$i]),
                    "rooms" => $roomsArr
                ];
            }
        }
        $configData['department_info']['stations'] = $stations;

        $apparatus = [];
        if (isset($_POST['app_id'])) {
            $appCount = count($_POST['app_id']);
            for ($i=0; $i<$appCount; $i++) {
                $apparatus[] = [
                    "id" => $_POST['app_id'][$i] ?: uniqid(),
                    "name" => trim($_POST['app_name'][$i]),
                    "abbr" => trim($_POST['app_abbr'][$i]),
                    "category" => $_POST['app_category'][$i],
                    "type" => $_POST['app_type'][$i],
                    "status" => $_POST['app_status'][$i]
                ];
            }
        }
        $configData['department_info']['apparatus'] = $apparatus;
        $success = "Department Information Saved.";
    }
    elseif (isset($_POST['save_headers'])) {
        $combined = [];
        for ($i=0; $i<7; $i++) {
            $appArr = $_POST['app'][$i] ?? [];
            $app = is_array($appArr) ? implode(" & ", $appArr) : (is_string($appArr) ? $appArr : "");
            $oth = trim($_POST['other'][$i]);
            $combined[] = ($oth !== '') ? ($app !== '' ? $app . "<br>" . $oth : $oth) : $app;
        }
        $configData['headers'] = $combined;
        $success = "Headers Saved.";
    }
    elseif (isset($_POST['save_apparatus'])) {
        $configData['truck_check'] = ["anchor" => $_POST['truck_check_anchor'], "interval" => (int)$_POST['truck_check_interval']];
        $configData['truck_wash'] = ["anchor" => $_POST['truck_wash_anchor'], "interval" => (int)$_POST['truck_wash_interval']];
        $success = "Apparatus Schedules Saved.";
    }
    elseif (isset($_POST['save_chores'])) {
        $configData['everyday_chores'] = array_filter(array_map('trim', $_POST['everyday_chores'] ?? []), 'strlen');
        $configData['chore_anchor'] = $_POST['chore_anchor'];
        $configData['chore_num_indices'] = (int)$_POST['chore_num_indices'];
        $newChores = [];
        if (isset($_POST['chore_ids'])) {
            $choreCount = count($_POST['chore_ids']);
            for ($i=0; $i<$choreCount; $i++) {
                if (trim($_POST['chore_names'][$i]) !== '') {
                    $newChores[] = ["id" => (int)$_POST['chore_ids'][$i], "name" => trim($_POST['chore_names'][$i])];
                }
            }
        }
        $configData['chores'] = $newChores;

        $newSpecialChores = [];
        if (!empty($_POST['special_chores_json'])) {
            foreach ($_POST['special_chores_json'] as $jsonStr) {
                $scData = json_decode($jsonStr, true);
                if (!empty($scData['name'])) {
                    if (empty($scData['id'])) $scData['id'] = uniqid();
                    $newSpecialChores[] = $scData;
                }
            }
        }
        $configData['special_chores'] = array_merge($newSpecialChores, $archived_chores);
        $active_chores = $newSpecialChores;

        $success = "Station Duties Saved.";
    }
    elseif (isset($_POST['save_events'])) {
        $newActiveEvents = [];
        if (!empty($_POST['events_json'])) {
            foreach ($_POST['events_json'] as $jsonStr) {
                $evtData = json_decode($jsonStr, true);
                if (!empty($evtData['title'])) {
                    if (empty($evtData['id'])) $evtData['id'] = uniqid();
                    $newActiveEvents[] = $evtData;
                }
            }
        }
        $configData['manual_events'] = array_merge($newActiveEvents, $archived_events);
        $active_events = $newActiveEvents;
        $success = "Events Saved.";
    }
    elseif (isset($_POST['save_announcements'])) {
        $newAnns = [];
        if (!empty($_POST['anns_json'])) {
            foreach ($_POST['anns_json'] as $jsonStr) {
                $annData = json_decode($jsonStr, true);
                if (!empty($annData['content']) && $annData['content'] !== '<p><br></p>') {
                    if (empty($annData['id'])) $annData['id'] = uniqid();
                    $newAnns[] = $annData;
                }
            }
        }
        $configData['announcements'] = array_merge($newAnns, $archived_announcements);
        $active_announcements = $newAnns;
        $success = "Announcements Saved.";
    }
    elseif (isset($_POST['delete_archived_event'])) {
        $idToDel = $_POST['delete_id'];
        $archived_events = array_filter($archived_events, function($e) use ($idToDel) { return $e['id'] !== $idToDel; });
        $configData['manual_events'] = array_merge($active_events, $archived_events);
        $success = "Archived Event Deleted.";
    }
    elseif (isset($_POST['delete_archived_chore'])) {
        $idToDel = $_POST['delete_id'];
        $archived_chores = array_filter($archived_chores, function($c) use ($idToDel) { return $c['id'] !== $idToDel; });
        $configData['special_chores'] = array_merge($active_chores, $archived_chores);
        $success = "Archived Duty Deleted.";
    }
    elseif (isset($_POST['delete_archived_ann'])) {
        $idToDel = $_POST['delete_id'];
        $archived_announcements = array_filter($archived_announcements, function($e) use ($idToDel) { return $e['id'] !== $idToDel; });
        $configData['announcements'] = array_merge($active_announcements, $archived_announcements);
        $success = "Archived Announcement Deleted.";
    }
    elseif (isset($_POST['save_api_integration'])) {
        if (!isset($configData['api_integrations'])) {
            $configData['api_integrations'] = [];
        }
        $configData['api_integrations']['gemini_api_key'] = trim($_POST['gemini_api_key'] ?? '');
        $configData['api_integrations']['google_tts_api_key'] = trim($_POST['google_tts_api_key'] ?? '');
        $success = "API Integration Settings Saved.";
    }
    elseif (isset($_POST['save_email_integration'])) {
        if (!isset($configData['email_integration'])) {
            $configData['email_integration'] = [];
        }
        $configData['email_integration']['danger_address'] = trim($_POST['danger_address'] ?? '');
        $configData['email_integration']['permit_address'] = trim($_POST['permit_address'] ?? '');
        $success = "Email Integration Settings Saved.";
    }
    elseif (isset($_POST['change_password'])) {
        $is_valid = false;
        if (password_verify($_POST['current_password'], $configData['admin_password'])) {
            $is_valid = true;
        } elseif ($_POST['current_password'] === $configData['admin_password']) {
            $is_valid = true;
        }

        if ($is_valid) {
            if ($_POST['new_password'] === $_POST['confirm_password'] && strlen($_POST['new_password']) > 3) {
                $configData['admin_password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $success = "Password changed successfully!";
            } else { $error_msg = "New passwords do not match or are too short."; }
        } else { $error_msg = "Incorrect current password."; }
    }
    file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
}

$eventsJson = json_encode($active_events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$specialChoresJson = json_encode($active_chores, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

function isPage($p, $currentPage) { return $p === $currentPage ? 'active' : ''; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Settings</title>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ical.js/1.5.0/ical.min.js"></script>

    <style>
        :root {
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --text-color: #1d1d1f;
            --muted-text: #86868b;
            --border-color: #e5e5ea;
            --border-input: #d2d2d7;
            --hover-bg: #eef2ff;
            --primary-color: #007aff;
            --danger-color: #ff3b30;
            --success-color: #34c759;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #1c1c1c;
                --card-bg: #1C212B;
                --text-color: #fff;
                --muted-text: #cbd5e1;
                --border-color: #30363d;
                --border-input: #30363d;
                --hover-bg: #30363d;
                --primary-color: #0a84ff;
                --danger-color: #ff453a;
                --success-color: #32d74b;
            }
        }
        /* iOS / Light Apple Inspired Aesthetic */
        body { font-family: "Inter", sans-serif; background: var(--bg-color); color: var(--text-color); margin: 0; display: flex; height: 100vh; overflow: hidden; }

        .sidebar { width: 260px; background: var(--card-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; flex-shrink: 0;}
        .sidebar-header { padding: 25px 20px; font-size: 1.3em; font-weight: 700; border-bottom: 1px solid var(--border-color); color: var(--text-color); letter-spacing: -0.5px;}
        .sidebar-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; overflow-y: auto;}
        .sidebar-menu li a { display: block; padding: 14px 20px; color: var(--muted-text); text-decoration: none; border-bottom: 1px solid #f0f0f5; transition: all 0.2s ease; font-weight: 500;}
        .sidebar-menu li a:hover { background: var(--bg-color); color: var(--text-color); }
        .sidebar-menu li a.active { background: var(--hover-bg); color: var(--primary-color); border-left: 4px solid var(--primary-color); padding-left: 16px;}
        .sub-menu a { padding-left: 40px !important; font-size: 0.9em; border-bottom: none !important; border-left: none !important;}
        .sub-menu a.active { background: var(--bg-color); color: var(--primary-color); font-weight: bold;}

        .logout-btn { padding: 18px; text-align: center; background: var(--bg-color); color: var(--danger-color); text-decoration: none; font-weight: 600; border-top: 1px solid var(--border-color); transition: 0.2s;}
        .logout-btn:hover { background: var(--hover-bg); }

        .content { flex-grow: 1; padding: 40px 50px; overflow-y: auto; position: relative;}

        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header-bar h1 { margin: 0; font-size: 2.2em; font-weight: 700; letter-spacing: -0.5px;}

        /* Buttons */
        button.save-btn { background: var(--success-color); color: white; border: none; padding: 12px 28px; font-size: 1.05em; border-radius: 8px; cursor: pointer; font-weight: 600; box-shadow: 0 2px 8px rgba(52,199,89,0.3); transition: all 0.2s;}
        button.save-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(52,199,89,0.4); }
        button.action-btn { background: var(--primary-color); color: white; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s;}
        button.action-btn:hover { filter: brightness(0.9); }
        button.delete-btn { background: var(--danger-color); color: white; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s;}
        button.delete-btn:hover { filter: brightness(0.9); }
        button.add { background: var(--success-color); margin-bottom: 10px; }

        /* Cards & Inputs */
        .card { background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        h2 { margin-top: 0; color: var(--text-color); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; font-weight: 600;}
        h3 { color: var(--text-color); margin-bottom: 12px; font-weight: 600;}
        p.help { font-size: 0.95em; color: var(--muted-text); margin-top: -5px; margin-bottom: 25px; line-height: 1.5; }

        input[type="text"], input[type="date"], input[type="time"], input[type="number"], select, input[type="password"] {
            padding: 12px; background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 8px; box-sizing: border-box; width: 100%; font-size: 1em; font-family: inherit; transition: border-color 0.2s;
        }
        input[type="text"]:focus, input[type="date"]:focus, select:focus, input[type="password"]:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(0,122,255,0.1);}
        input[type="checkbox"] { transform: scale(1.2); margin-right: 8px;}
        label { font-size: 0.9em; color: var(--muted-text); font-weight: 600; margin-bottom: 8px; display: block;}

        .success { background: #e8f8eb; border: 1px solid var(--success-color); color: #248a3d; padding: 16px; border-radius: 8px; margin-bottom: 25px; font-weight: 600; }
        .error { background: var(--hover-bg); border: 1px solid #ff3b30; color: #cc2e26; padding: 16px; border-radius: 8px; margin-bottom: 25px; font-weight: 600; }

        /* Layouts */
        .flex-row { display: flex; gap: 20px; align-items: flex-end; margin-bottom: 20px; }
        .flex-col { display: flex; flex-direction: column; flex: 1;}
        .grid-7 { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; }

        .item-card, .event-card, .ann-card { background: var(--card-bg); padding: 25px; border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 20px; position: relative; }
        .event-card hr, .ann-card hr { border-top: 1px solid #d2d2d7; border-bottom: none; margin: 20px 0; }
        .config-box { background: var(--card-bg); padding: 20px; border-radius: 10px; border: 1px solid var(--border-color); }

        .room-tag { display: inline-flex; align-items: center; background: var(--border-color); padding: 6px 12px; border-radius: 6px; margin: 5px 5px 0 0; font-size: 0.9em; color: var(--text-color); font-weight: 500;}
        .room-tag button { background: none; border: none; color: var(--danger-color); margin-left: 8px; cursor: pointer; font-weight: bold;}

        /* Custom Multi-Select */
        .ms-container { position: relative; width: 100%; margin-bottom: 15px; }
        .ms-btn { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-color); padding: 12px; border-radius: 8px; width: 100%; text-align: left; cursor: pointer; display: flex; justify-content: space-between; align-items: center; min-height: 46px; font-size: 1em;}
        .ms-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; z-index: 100; max-height: 250px; overflow-y: auto; display: none; box-shadow: 0 8px 24px rgba(0,0,0,0.1); padding: 8px; margin-top: 4px;}
        .ms-dropdown.active { display: block; }
        .ms-dropdown label { display: flex; align-items: center; padding: 10px; cursor: pointer; border-radius: 6px; font-weight: normal; color: var(--text-color); margin: 0; transition: 0.1s;}
        .ms-dropdown label:hover { background: var(--bg-color); }
        .ms-dropdown input[type="checkbox"] { margin: 0 12px 0 0; transform: scale(1.3); }

        /* Admin Interactive Calendar */
        .admin-cal-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; background: var(--card-bg); padding: 15px; border-radius: 10px; border: 1px solid var(--border-color);}
        .admin-cal-controls .btn-group { display: flex; gap: 8px; }
        .admin-cal-controls h3, .admin-cal-controls h2 { color: var(--text-color) !important;}

        .admin-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--border-color); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
        .admin-cal-day { background: var(--card-bg); padding: 8px; min-height: 110px; cursor: pointer; transition: background 0.2s; display: flex; flex-direction: column; position: relative; overflow: hidden;}
        .admin-cal-day:hover { background: var(--hover-bg); }
        .admin-cal-day-header { font-size: 0.85em; color: var(--muted-text); text-align: right; margin-bottom: 5px; z-index: 2; font-weight: 600;}
        .admin-cal-lbl { background: var(--card-bg); text-align: center; font-weight: 600; padding: 10px 0; color: var(--muted-text); }

        .cal-evt-pill { font-size: 0.75em; background: var(--success-color); color: #fff; padding: 4px 6px; border-radius: 4px; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; z-index: 2; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.1);}

        /* Employee Shift Blocks on Admin Cal */
        .cal-shift-block { font-size: 0.7em; padding: 3px 5px; margin-bottom: 3px; border-radius: 4px; color: var(--text-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; z-index: 2; position: relative; font-weight: 500; border-left: 3px solid transparent;}
        .bg-career { background-color: #eef2ff; border-left-color: var(--primary-color);}
        .bg-perdiem { background-color: #fdf1f7; border-left-color: #e83e8c;}
        .bg-night { background-color: #fff4e6; border-left-color: #fd7e14;}

        /* Form Overrides */
        .recur-group, .end-group { background: var(--card-bg); padding: 20px; border-radius: 8px; border: 1px solid var(--border-input); margin-top: 15px;}
        .day-checkboxes { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;}
        .day-checkboxes label { display: inline-flex; align-items: center; background: var(--bg-color); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); font-weight: 500; margin-bottom:0; cursor: pointer; transition: 0.2s;}
        .day-checkboxes label:hover { background: var(--hover-bg);}

        button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
</style>

    <script>
        function extractRecurrenceState(data) {
            return {
                rec: data && data.recurrence ? data.recurrence : 'none',
                rInt: data && data.recur_interval ? data.recur_interval : 1,
                rWkDays: data && data.recur_weekdays ? data.recur_weekdays : [],
                rMoType: data && data.recur_month_type ? data.recur_month_type : 'date',
                rMoDate: data && data.recur_month_date ? data.recur_month_date : 1,
                rMoNth: data && data.recur_month_nth ? data.recur_month_nth : 1,
                rMoNthDay: data && data.recur_month_nth_day ? data.recur_month_nth_day : 0,
                endType: data && data.end_type ? data.end_type : 'date',
                endOccur: data && data.end_occurrences ? data.end_occurrences : 10,
                endDateBound: data && data.end_date_bound ? data.end_date_bound : ''
            };
        }

        function enforceSunday(input) {
            if (!input.value) return;
            const [y, m, d] = input.value.split('-');
            const dt = new Date(y, m - 1, d);
            if (dt.getDay() !== 0) {
                alert("Please select a Sunday for the Anchor Date.");
                input.value = '';
            }
            if(window.renderAppPreview) renderAppPreview();
            if(window.renderChorePreview) renderChorePreview();
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.ms-container')) {
                document.querySelectorAll('.ms-dropdown').forEach(d => d.classList.remove('active'));
            }
        });

        function updateMultiSelect(index) {
            let checked = document.querySelectorAll(`input[name="app[${index}][]"]:checked`);
            let arr = Array.from(checked).map(c => c.value);
            let txt = arr.length > 0 ? arr.join(' & ') : '-- Select Apparatus --';
            document.getElementById(`ms-txt-${index}`).textContent = txt;
        }

        function toggleRecurUI(sel, typePrefix) {
            const card = sel.closest(`.${typePrefix}-card`);
            const val = sel.value;
            card.querySelector('.r-interval').style.display = val !== 'none' ? 'flex' : 'none';
            card.querySelector('.r-end-opts').style.display = val !== 'none' ? 'block' : 'none';

            const lbl = card.querySelector('.r-int-lbl');
            if (val === 'daily') lbl.textContent = 'days';
            if (val === 'weekly') lbl.textContent = 'weeks';
            if (val === 'monthly') lbl.textContent = 'months';

            card.querySelector('.r-weekly-opts').style.display = val === 'weekly' ? 'block' : 'none';
            card.querySelector('.r-monthly-opts').style.display = val === 'monthly' ? 'block' : 'none';
        }
    </script>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">Dashboard Config</div>
        <ul class="sidebar-menu">
            <li><a href="?page=settings" class="<?= isPage('settings', $page) ?>">Dashboard Settings</a></li>
            <li><a href="?page=dept_info" class="<?= isPage('dept_info', $page) ?>">Department Info</a></li>
            <li><a href="?page=headers" class="<?= isPage('headers', $page) ?>">Calendar Headers</a></li>
            <li><a href="?page=apparatus" class="<?= isPage('apparatus', $page) ?>">Apparatus Maint.</a></li>
            <li>
                <a href="?page=chores" class="<?= isPage('chores', $page) ?>">Station Duties</a>
                <ul class="sidebar-menu sub-menu">
                    <li><a href="?page=archived_chores" class="<?= isPage('archived_chores', $page) ?>">Archived Duties</a></li>
                </ul>
            </li>
            <li>
                <a href="?page=events" class="<?= isPage('events', $page) ?>">Dept. Events</a>
                <ul class="sidebar-menu sub-menu">
                    <li><a href="?page=archived_events" class="<?= isPage('archived_events', $page) ?>">Archived Events</a></li>
                </ul>
            </li>
            <li>
                <a href="?page=announcements" class="<?= isPage('announcements', $page) ?>">Announcements</a>
                <ul class="sidebar-menu sub-menu">
                    <li><a href="?page=archived_anns" class="<?= isPage('archived_anns', $page) ?>">Archived Anns</a></li>
                </ul>
            </li>
            <li><a href="?page=email_integration" class="<?= isPage('email_integration', $page) ?>">Email Integration</a></li>
            <li><a href="?page=map_locations" class="<?= isPage('map_locations', $page) ?>">Map Locations</a></li>
            <li><a href="?page=api_integrations" class="<?= isPage('api_integrations', $page) ?>">API Integrations</a></li>
            <li><a href="?page=password" class="<?= isPage('password', $page) ?>">Change Password</a></li>
            <li><a href="?page=system_logs" class="<?= isPage('system_logs', $page) ?>">System Logs</a></li>
        </ul>
        <a href="?logout=true&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" class="logout-btn">Log Out Securely</a>
    </div>

    <div class="content">
        <form method="POST" id="mainConfigForm" onsubmit="runPreSubmitHooks()">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="header-bar">
                <h1 style="text-transform: capitalize;"><?= htmlspecialchars(str_replace('_', ' ', $page)) ?></h1>
                <button type="submit" name="save_<?= htmlspecialchars(explode('_', $page)[0]) ?>" class="save-btn">Save Changes</button>
            </div>

            <?php if($success) echo "<div class='success'>$success</div>"; ?>
            <?php if($error_msg) echo "<div class='error'>$error_msg</div>"; ?>

            <datalist id="room-list">
                <?php foreach(json_decode($roomsJson) as $r) echo "<option value=\"$r\">"; ?>
            </datalist>

            <?php if ($page === 'settings'): ?>
                <div class="card">
                    <h2>Dashboard Appearance</h2>
                    <div style="max-width: 400px;">
                        <label for="dash_theme_select">Display Theme</label>
                        <p class="help" id="dash_theme_help">Choose the color scheme for the digital signage screens.</p>
                        <select id="dash_theme_select" name="dash_theme" aria-describedby="dash_theme_help" style="margin-bottom: 20px;">
                            <option value="dark" <?= $configData['dashboard_settings']['theme'] === 'dark' ? 'selected' : '' ?>>Dark Mode (Default)</option>
                            <option value="light" <?= $configData['dashboard_settings']['theme'] === 'light' ? 'selected' : '' ?>>Light Mode</option>
                            <option value="auto" <?= $configData['dashboard_settings']['theme'] === 'auto' ? 'selected' : '' ?>>Auto (Light daytime, Dark nighttime)</option>
                        </select>
                    </div>
                    <div style="max-width: 400px; margin-top: 15px;">
                        <label for="dashboard_token_input">Dashboard Access Token</label>
                        <p class="help" id="dashboard_token_help">If set, users must append <code>?token=YOUR_TOKEN</code> to the dashboard URL.</p>
                        <input type="text" id="dashboard_token_input" name="dashboard_token" value="<?= htmlspecialchars($configData['dashboard_token'] ?? '') ?>" placeholder="Leave blank for public access" aria-describedby="dashboard_token_help" style="width:100%; padding:8px; box-sizing: border-box; border: 1px solid #c3c3c3; border-radius: 4px;">
                    </div>
                    <div style="max-width: 400px; margin-top: 15px;">
                        <label for="fire_danger_zone_input">Fire Danger Zone</label>
                        <p class="help" id="fire_danger_zone_help">The zone number used to extract the correct fire danger level from daily emails.</p>
                        <input type="text" id="fire_danger_zone_input" name="fire_danger_zone" value="<?= htmlspecialchars($configData['fire_danger_zone'] ?? '7') ?>" placeholder="e.g., 7" aria-describedby="fire_danger_zone_help" style="width:100%; padding:8px; box-sizing: border-box; border: 1px solid #c3c3c3; border-radius: 4px;">
                    </div>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2>Audio & Alerts</h2>
                    <p class="help">Configure global audio settings and custom alert sounds.</p>
                    <div style="margin-top: 15px;">
                        <label for="audio_enabled_input" style="display: flex; align-items: center; gap: 10px; font-weight: bold; margin-bottom: 10px;">
                                <input type="checkbox" id="audio_enabled_input" name="audio_enabled" <?= !empty($configData['dashboard_settings']['audio_enabled']) ? 'checked' : '' ?> style="transform: scale(1.2);">
                                Enable Audio Announcements
                            </label>
                        <p class="help" style="margin-bottom: 15px;">If enabled, the dashboard will attempt to play audio for alerts.</p>

                        <label for="tts_enabled_input" style="display: flex; align-items: center; gap: 10px; font-weight: bold; margin-bottom: 10px;">
                                <input type="checkbox" id="tts_enabled_input" name="tts_enabled" <?= !empty($configData['dashboard_settings']['tts_enabled']) ? 'checked' : '' ?> style="transform: scale(1.2);">
                                Enable Text-to-Speech (TTS)
                            </label>
                        <p class="help" style="margin-bottom: 20px;">If enabled, Google TTS or browser fallback will read alerts aloud.</p>

                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 250px;">
                                <label for="alert_audio_fire" style="font-weight: bold;">Fire Danger Alert Audio</label>
                                <?php if (!empty($configData['dashboard_settings']['alert_audio_fire'])): ?>
                                    <p style="font-size: 0.9em; color: var(--success-color);">Current: <?= htmlspecialchars(basename($configData['dashboard_settings']['alert_audio_fire'])) ?></p>
                                <?php endif; ?>
                                <input type="file" id="alert_audio_fire" name="alert_audio_fire" accept=".mp3,.wav" style="margin-top: 5px;">
                            </div>
                            <div style="flex: 1; min-width: 250px;">
                                <label for="alert_audio_permit" style="font-weight: bold;">New Permit Alert Audio</label>
                                <?php if (!empty($configData['dashboard_settings']['alert_audio_permit'])): ?>
                                    <p style="font-size: 0.9em; color: var(--success-color);">Current: <?= htmlspecialchars(basename($configData['dashboard_settings']['alert_audio_permit'])) ?></p>
                                <?php endif; ?>
                                <input type="file" id="alert_audio_permit" name="alert_audio_permit" accept=".mp3,.wav" style="margin-top: 5px;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2>Rotating Pages</h2>
                    <p class="help">Select which pages to display and how long (in seconds) they should stay on screen.</p>

                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px;">
                        <?php
                        $pagesSettings = $configData['dashboard_settings']['pages'];
                        $pageDefinitions = [
                            "dashboard" => "Main Dashboard",
                            "calendar" => "Calendar",
                            "chores" => "Chores & Duties"
                        ];
                        foreach ($pageDefinitions as $key => $label):
                            $enabled = $pagesSettings[$key]['enabled'];
                            $duration = $pagesSettings[$key]['duration'];
                        ?>
                        <div style="border: 1px solid var(--border-color); padding: 15px; border-radius: 6px; flex: 1; min-width: 200px;">
                            <label for="page_<?= $key ?>_enabled_input" style="display: flex; align-items: center; gap: 10px; font-weight: bold; margin-bottom: 10px;">
                                <input type="checkbox" id="page_<?= $key ?>_enabled_input" name="page_<?= $key ?>_enabled" <?= $enabled ? 'checked' : '' ?> style="transform: scale(1.2);">
                                <?= $label ?>
                            </label>
                            <label for="page_<?= $key ?>_duration_input" style="font-size: 0.9em; color: var(--muted-text); display: block; margin-bottom: 5px;">Duration (seconds)</label>
                            <input type="number" id="page_<?= $key ?>_duration_input" name="page_<?= $key ?>_duration" value="<?= htmlspecialchars((string)$duration) ?>" min="1" style="width: 100px; padding: 6px; border: 1px solid #c3c3c3; border-radius: 4px;">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2>Calendar URLs (.ics)</h2>
                    <p class="help">Enter the public .ics URLs for each calendar to display on the dashboard.</p>

                    <div style="margin-top: 15px;">
                        <label for="cal_main_input">Main Schedule Calendar</label>
                        <input type="text" id="cal_main_input" name="cal_main" value="<?= htmlspecialchars($configData['calendar_urls']['main'] ?? '') ?>" placeholder="https://..." style="width:100%; padding:8px; box-sizing: border-box; border: 1px solid #c3c3c3; border-radius: 4px;">
                    </div>

                    <div style="margin-top: 15px;">
                        <label for="cal_burn_permits_input">Burn Permits Calendar</label>
                        <input type="text" id="cal_burn_permits_input" name="cal_burn_permits" value="<?= htmlspecialchars($configData['calendar_urls']['burn_permits'] ?? '') ?>" placeholder="https://..." style="width:100%; padding:8px; box-sizing: border-box; border: 1px solid #c3c3c3; border-radius: 4px;">
                    </div>

                    <div style="margin-top: 15px;">
                        <label for="cal_town_meetings_input">Town Meetings Calendar</label>
                        <input type="text" id="cal_town_meetings_input" name="cal_town_meetings" value="<?= htmlspecialchars($configData['calendar_urls']['town_meetings'] ?? '') ?>" placeholder="https://..." style="width:100%; padding:8px; box-sizing: border-box; border: 1px solid #c3c3c3; border-radius: 4px;">
                    </div>

                    <div style="margin-top: 15px;">
                        <label for="cal_holidays_input">Holidays Calendar</label>
                        <input type="text" id="cal_holidays_input" name="cal_holidays" value="<?= htmlspecialchars($configData['calendar_urls']['holidays'] ?? '') ?>" placeholder="https://..." style="width:100%; padding:8px; box-sizing: border-box; border: 1px solid #c3c3c3; border-radius: 4px;">
                    </div>
                </div>
                <script>function runPreSubmitHooks() {}</script>

            <?php elseif ($page === 'dept_info'): ?>
                <div class="card">
                    <h2>Department Information</h2>
                    <label for="dept_name_input">Department Name</label>
                    <input type="text" id="dept_name_input" name="dept_name" value="<?= htmlspecialchars($configData['department_info']['name']) ?>" required style="margin-bottom: 25px; font-size: 1.2em;">

                    <h3>Stations & Rooms</h3>
                    <p class="help">Manage your stations and available rental rooms.</p>
                    <div id="stations-container">
                        <?php foreach($configData['department_info']['stations'] as $stIdx => $st): ?>
                            <div class="item-card st-card">
                                <button type="button" aria-label="Remove Station" class="delete-btn" style="position:absolute; right:20px; top:20px;" onclick="this.parentElement.remove()">Remove Station</button>
                                <input type="hidden" name="st_id[]" value="<?= $st['id'] ?>">
                                <input type="hidden" name="st_rooms_json[]" class="st-rooms-json" value="<?= htmlspecialchars(json_encode($st['rooms'])) ?>">

                                <div class="flex-row">
                                    <div class="flex-col" style="flex:1"><label for="st_number_<?= $stIdx ?>">Station Number/Name</label><input type="text" id="st_number_<?= $stIdx ?>" name="st_number[]" value="<?= htmlspecialchars($st['number']) ?>" required></div>
                                    <div class="flex-col" style="flex:2"><label for="st_address_<?= $stIdx ?>">Address</label><input type="text" id="st_address_<?= $stIdx ?>" name="st_address[]" value="<?= htmlspecialchars($st['address']) ?>" required></div>
                                </div>
                                <label>Rooms Available (e.g., Training Room)</label>
                                <div class="flex-row" style="margin-bottom: 10px;">
                                    <input type="text" class="room-input" placeholder="Room Name" style="flex:1;">
                                    <button type="button" class="action-btn" onclick="addRoomToStation(this)">Add Room</button>
                                </div>
                                <div class="rooms-visual-list">
                                    <?php foreach($st['rooms'] as $r): ?>
                                        <div class="room-tag"><span><?= htmlspecialchars($r) ?></span><button type="button" aria-label="Remove" onclick="this.parentElement.remove()">x</button></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="action-btn add" onclick="addStation()">+ Add Station</button>

                    <hr style="border-color: #e5e5ea; margin: 30px 0;">

                    <h3>Apparatus Inventory</h3>
                    <p class="help">Manage your fleet. Note: Abbreviations defined here will populate dropdowns on the Calendar Headers page.</p>
                    <div id="apparatus-container">
                        <?php foreach($configData['department_info']['apparatus'] as $app): ?>
                            <div class="item-card">
                                <button type="button" aria-label="Remove" class="delete-btn" style="position:absolute; right:20px; top:20px;" onclick="this.parentElement.remove()">Remove</button>
                                <input type="hidden" name="app_id[]" value="<?= $app['id'] ?>">
                                <div class="flex-row" style="margin-bottom: 0;">
                                    <div class="flex-col" style="flex:2"><label>Name of Apparatus</label><input type="text" name="app_name[]" value="<?= htmlspecialchars($app['name']) ?>" required></div>
                                    <div class="flex-col"><label>Abbreviation</label><input type="text" name="app_abbr[]" value="<?= htmlspecialchars($app['abbr']) ?>" required></div>
                                    <div class="flex-col">
                                        <label>Category</label>
                                        <select name="app_category[]">
                                            <option value="Fire" <?= $app['category']=='Fire'?'selected':'' ?>>Fire</option>
                                            <option value="EMS" <?= $app['category']=='EMS'?'selected':'' ?>>EMS</option>
                                            <option value="Utility" <?= $app['category']=='Utility'?'selected':'' ?>>Utility</option>
                                        </select>
                                    </div>
                                    <div class="flex-col">
                                        <label>Type</label>
                                        <select name="app_type[]">
                                            <option value="Engine" <?= $app['type']=='Engine'?'selected':'' ?>>Engine</option>
                                            <option value="Ladder" <?= $app['type']=='Ladder'?'selected':'' ?>>Ladder</option>
                                            <option value="Rescue unit" <?= $app['type']=='Rescue unit'?'selected':'' ?>>Rescue unit</option>
                                            <option value="Ambulance" <?= $app['type']=='Ambulance'?'selected':'' ?>>Ambulance</option>
                                            <option value="Brush" <?= $app['type']=='Brush'?'selected':'' ?>>Brush</option>
                                            <option value="Chief" <?= $app['type']=='Chief'?'selected':'' ?>>Chief</option>
                                        </select>
                                    </div>
                                    <div class="flex-col">
                                        <label>Status</label>
                                        <select name="app_status[]">
                                            <option value="In service" <?= $app['status']=='In service'?'selected':'' ?>>In service</option>
                                            <option value="Out of service" <?= $app['status']=='Out of service'?'selected':'' ?>>Out of service</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="action-btn add" onclick="addApparatus()">+ Add Apparatus</button>
                </div>
                <script>
                    function addRoomToStation(btn) {
                        const card = btn.closest('.st-card');
                        const input = card.querySelector('.room-input');
                        const val = input.value.trim();
                        if(val !== '') {
                            const list = card.querySelector('.rooms-visual-list');
                            list.insertAdjacentHTML('beforeend', `<div class="room-tag"><span>${val}</span><button type="button" aria-label="Remove" onclick="this.parentElement.remove()">x</button></div>`);
                            input.value = '';
                        }
                    }
                    function bundleRooms() {
                        document.querySelectorAll('.st-card').forEach(card => {
                            let rooms = [];
                            card.querySelectorAll('.rooms-visual-list .room-tag span').forEach(sp => rooms.push(sp.textContent));
                            card.querySelector('.st-rooms-json').value = JSON.stringify(rooms);
                        });
                    }
                    function addStation() {
                        const randId = Math.random().toString(36).substr(2, 9);
                        const html = `<button type="button" aria-label="Remove Station" class="delete-btn" style="position:absolute; right:20px; top:20px;" onclick="this.parentElement.remove()">Remove Station</button>
                            <input type="hidden" name="st_id[]" value="">
                            <input type="hidden" name="st_rooms_json[]" class="st-rooms-json" value="[]">
                            <div class="flex-row">
                                <div class="flex-col" style="flex:1"><label for="st_number_${randId}">Station Number/Name</label><input type="text" id="st_number_${randId}" name="st_number[]" required></div>
                                <div class="flex-col" style="flex:2"><label for="st_address_${randId}">Address</label><input type="text" id="st_address_${randId}" name="st_address[]" required></div>
                            </div>
                            <label>Rooms Available (e.g., Training Room)</label>
                            <div class="flex-row" style="margin-bottom: 10px;">
                                <input type="text" class="room-input" placeholder="Room Name" style="flex:1;">
                                <button type="button" class="action-btn" onclick="addRoomToStation(this)">Add Room</button>
                            </div>
                            <div class="rooms-visual-list"></div>`;
                        const div = document.createElement('div'); div.className = 'item-card st-card'; div.innerHTML = html;
                        document.getElementById('stations-container').appendChild(div);
                    }
                    function addApparatus() {
                        const html = `<button type="button" aria-label="Remove" class="delete-btn" style="position:absolute; right:20px; top:20px;" onclick="this.parentElement.remove()">Remove</button>
                            <input type="hidden" name="app_id[]" value="">
                            <div class="flex-row" style="margin-bottom: 0;">
                                <div class="flex-col" style="flex:2"><label>Name of Apparatus</label><input type="text" name="app_name[]" required></div>
                                <div class="flex-col"><label>Abbreviation</label><input type="text" name="app_abbr[]" required></div>
                                <div class="flex-col"><label>Category</label><select name="app_category[]"><option value="Fire">Fire</option><option value="EMS">EMS</option><option value="Utility">Utility</option></select></div>
                                <div class="flex-col"><label>Type</label><select name="app_type[]"><option value="Engine">Engine</option><option value="Ladder">Ladder</option><option value="Rescue unit">Rescue unit</option><option value="Ambulance">Ambulance</option><option value="Brush">Brush</option><option value="Chief">Chief</option></select></div>
                                <div class="flex-col"><label>Status</label><select name="app_status[]"><option value="In service">In service</option><option value="Out of service">Out of service</option></select></div>
                            </div>`;
                        const div = document.createElement('div'); div.className = 'item-card'; div.innerHTML = html;
                        document.getElementById('apparatus-container').appendChild(div);
                    }
                    function runPreSubmitHooks() { bundleRooms(); }
                </script>

            <?php elseif ($page === 'headers'): ?>
                <div class="card">
                    <h2>Calendar Day Headers (Sun - Sat)</h2>
                    <p class="help">Select the Apparatus abbreviation for each day. You can select multiple apparatuses. If you need a new apparatus, add it on the <a href="?page=dept_info" style="color:#007aff;">Department Info</a> page. You can optionally add "Other" free-text to appear beneath it.</p>

                    <div class="grid-7">
                        <?php
                        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        for ($i = 0; $i < 7; $i++) {
                            $parts = explode("<br>", $configData['headers'][$i] ?? '');
                            $appStr = htmlspecialchars($parts[0] ?? '');
                            $selectedApps = array_map('trim', explode('&', $appStr));

                            $oth = htmlspecialchars($parts[1] ?? '');

                            echo "<div class='config-box'>";
                            echo "<h4 style='margin:0 0 12px 0; text-align: center; color: var(--text-color);'>{$days[$i]}</h4>";

                            $displayText = empty($appStr) ? "-- Select Apparatus --" : $appStr;
                            echo "<label>Apparatus</label>";
                            echo "<div class='ms-container'>";
                            echo "<div class='ms-btn' onclick=\"this.nextElementSibling.classList.toggle('active')\"><span id='ms-txt-{$i}'>{$displayText}</span><span style='color:#86868b; font-size:0.8em;'>▼</span></div>";
                            echo "<div class='ms-dropdown'>";
                            foreach($configData['department_info']['apparatus'] as $app) {
                                $abbr = htmlspecialchars($app['abbr']);
                                $checked = in_array($abbr, $selectedApps) ? 'checked' : '';
                                echo "<label for='app_{$i}_{$abbr}'><input type='checkbox' id='app_{$i}_{$abbr}' name='app[{$i}][]' value='{$abbr}' onchange='updateMultiSelect({$i})' {$checked}> {$abbr}</label>";
                            }
                            echo "</div></div>";

                            echo "<label for='other_{$i}'>Other (Optional)</label><input type='text' id='other_{$i}' name='other[]' value='{$oth}'>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
                <script>function runPreSubmitHooks() { }</script>

            <?php elseif ($page === 'apparatus'): ?>
                <div class="card">
                    <h2>Apparatus Maintenance Schedules</h2>
                    <p class="help">Select anchor dates to establish the repeating cycles for checking and washing.</p>
                    <div class="flex-row">
                        <div class="flex-col config-box">
                            <h3 style="margin-top: 0;">✅ Check Trucks</h3>
                            <label for="truck_check_anchor_input">Anchor Sunday Date</label>
                            <input type="date" id="truck_check_anchor_input" name="truck_check_anchor" value="<?= $configData['truck_check']['anchor'] ?>" required onchange="enforceSunday(this)" style="margin-bottom: 15px;">
                            <label for="truck_check_interval_input">Repeats Every (Weeks)</label>
                            <input type="number" id="truck_check_interval_input" name="truck_check_interval" value="<?= $configData['truck_check']['interval'] ?>" min="1" required onchange="renderAppPreview()">
                        </div>
                        <div class="flex-col config-box">
                            <h3 style="margin-top: 0;">🧽 Wash Trucks</h3>
                            <label for="truck_wash_anchor_input">Anchor Sunday Date</label>
                            <input type="date" id="truck_wash_anchor_input" name="truck_wash_anchor" value="<?= $configData['truck_wash']['anchor'] ?>" required onchange="enforceSunday(this)" style="margin-bottom: 15px;">
                            <label for="truck_wash_interval_input">Repeats Every (Weeks)</label>
                            <input type="number" id="truck_wash_interval_input" name="truck_wash_interval" value="<?= $configData['truck_wash']['interval'] ?>" min="1" required onchange="renderAppPreview()">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>Schedule Preview</h2>
                    <div class="admin-cal-controls">
                        <button type="button" class="action-btn" aria-label="Previous month" onclick="changeAppMonth(-1)">&#10094;</button>
                        <h3 id="appMonthTitle" style="margin: 0;"></h3>
                        <button type="button" class="action-btn" aria-label="Next month" onclick="changeAppMonth(1)">&#10095;</button>
                    </div>
                    <div id="appPreviewCal" class="admin-cal-grid"></div>
                </div>

                <script>
                    let appMoOffset = 0;
                    function changeAppMonth(d) { appMoOffset += d; renderAppPreview(); }
                    function renderAppPreview() {
                        const cAnchor = document.querySelector('input[name="truck_check_anchor"]').value;
                        const cInt = parseInt(document.querySelector('input[name="truck_check_interval"]').value) || 1;
                        const wAnchor = document.querySelector('input[name="truck_wash_anchor"]').value;
                        const wInt = parseInt(document.querySelector('input[name="truck_wash_interval"]').value) || 1;

                        const grid = document.getElementById('appPreviewCal');
                        grid.innerHTML = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d => `<div class="admin-cal-lbl" style="padding: 6px 0; font-size: 0.9em;">${d}</div>`).join('');

                        let now = new Date();
                        let targetMonth = new Date(now.getFullYear(), now.getMonth() + appMoOffset, 1);
                        document.getElementById('appMonthTitle').textContent = targetMonth.toLocaleString('default', { month: 'long', year: 'numeric' });

                        let firstDay = new Date(targetMonth);
                        firstDay.setDate(1 - firstDay.getDay());

                        for(let i=0; i<35; i++) {
                            let currentDay = new Date(firstDay);
                            currentDay.setDate(firstDay.getDate() + i);

                            let div = document.createElement('div');
                            div.className = 'admin-cal-day';
                            div.style.minHeight = '70px';
                            if(currentDay.getMonth() !== targetMonth.getMonth()) div.style.opacity = '0.4';
                            if(currentDay.toDateString() === now.toDateString()) div.style.border = '2px solid #007aff';

                            let icons = [];
                            if (currentDay.getDay() === 0) {
                                if (cAnchor) {
                                    const [cy, cm, cd] = cAnchor.split('-');
                                    const cTime = new Date(cy, cm - 1, cd, 0, 0, 0).getTime();
                                    const tTime = new Date(currentDay.getFullYear(), currentDay.getMonth(), currentDay.getDate(), 0, 0, 0).getTime();
                                    const diffWks = Math.floor(Math.round((tTime - cTime) / 86400000) / 7);
                                    if (diffWks >= 0 && diffWks % cInt === 0) icons.push('✅ Check');
                                }
                                if (wAnchor) {
                                    const [wy, wm, wd] = wAnchor.split('-');
                                    const wTime = new Date(wy, wm - 1, wd, 0, 0, 0).getTime();
                                    const tTime = new Date(currentDay.getFullYear(), currentDay.getMonth(), currentDay.getDate(), 0, 0, 0).getTime();
                                    const diffWks = Math.floor(Math.round((tTime - wTime) / 86400000) / 7);
                                    if (diffWks >= 0 && diffWks % wInt === 0) icons.push('🧽 Wash');
                                }
                            }

                            let iconHtml = icons.length > 0 ? `<div style="color:#1d1d1f; font-size:0.9em; font-weight:600; margin-top:5px; text-align:center; line-height:1.5;">${icons.join('<br>')}</div>` : '';
                            div.innerHTML = `<div class="admin-cal-day-header">${currentDay.getDate()}</div>${iconHtml}`;
                            grid.appendChild(div);
                        }
                    }
                    window.onload = renderAppPreview;
                    function runPreSubmitHooks() {}
                </script>

            <?php elseif ($page === 'chores'): ?>
                <div class="card">
                    <h2>Station Duties Configuration</h2>

                    <div class="config-box flex-row" style="margin-bottom: 25px; align-items: flex-start;">
                        <div style="display: flex; flex-direction: column; gap: 20px; width: 250px; flex-shrink: 0;">
                            <div>
                                <label style="color: var(--text-color); font-size: 1.1em;">Chore #1 Anchor Date:</label>
                                <p class="help" style="margin-bottom: 8px;">Pick a Sunday where Index #1 should fall.</p>
                                <input type="date" name="chore_anchor" id="c_anchor" value="<?= $configData['chore_anchor'] ?>" required onchange="enforceSunday(this)">
                            </div>
                            <div>
                                <label style="color: var(--text-color); font-size: 1.1em;">Indices in Rotation:</label>
                                <p class="help" style="margin-bottom: 8px;">How many total numbered days?</p>
                                <input type="number" name="chore_num_indices" id="c_indices" value="<?= $configData['chore_num_indices'] ?>" min="1" required onchange="renderChorePreview()">
                            </div>
                        </div>
                        <div style="flex-grow: 1;">
                            <div class="admin-cal-controls" style="background: transparent; border: none; padding: 0; margin-bottom: 10px;">
                                <button type="button" class="action-btn" aria-label="Previous month" onclick="changeChoreMonth(-1)">&#10094;</button>
                                <strong id="choreMonthTitle" style="color:#1d1d1f; font-size: 1.2em;"></strong>
                                <button type="button" class="action-btn" aria-label="Next month" onclick="changeChoreMonth(1)">&#10095;</button>
                            </div>
                            <div id="chorePreviewCal" class="admin-cal-grid" style="font-size: 0.9em;"></div>
                        </div>
                    </div>

                    <h3 style="margin-bottom: 5px;">Numbered Chores</h3>
                    <p class="help">Define specific tasks to occur on specific index days in the rotation. <strong>You can assign multiple duties to the same Index #</strong> (e.g., both 'Kitchen' and 'Bathrooms' on Index #1).</p>

                    <div id="numbered-chores-container">
                        <div style="display: flex; gap: 15px; margin-bottom: 5px; padding: 0 10px;">
                            <label style="width: 80px;">Index #</label><label style="flex-grow:1;">Duty Description</label><label style="width:70px;"></label>
                        </div>
                        <?php foreach ($configData['chores'] as $chore): ?>
                            <div class="item-card flex-row" style="align-items: center; padding: 15px; margin-bottom: 10px;">
                                <input type="number" name="chore_ids[]" value="<?= $chore['id'] ?>" style="width: 80px; margin:0;" min="1" required>
                                <input type="text" name="chore_names[]" value="<?= htmlspecialchars($chore['name']) ?>" style="flex-grow: 1; margin:0;" required>
                                <button type="button" aria-label="Remove" class="delete-btn" onclick="this.parentElement.remove()" style="margin:0;">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="action-btn add" onclick="addNumberedChore()" style="margin-top: 10px;">+ Add Numbered Chore</button>

                    <hr style="border-color: #e5e5ea; margin: 30px 0;">

                    <h3 style="margin-bottom: 5px;">Everyday Tasks</h3>
                    <p class="help">These appear every single day regardless of rotation schedule.</p>
                    <div id="everyday-container">
                        <?php foreach ($configData['everyday_chores'] as $chore): ?>
                            <div class="flex-row" style="margin-bottom: 10px;">
                                <input type="text" name="everyday_chores[]" value="<?= htmlspecialchars($chore) ?>" style="flex-grow: 1;">
                                <button type="button" aria-label="Remove" class="delete-btn" onclick="this.parentElement.remove()">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="action-btn add" onclick="addEverydayChore()">+ Add Everyday Task</button>

                    <hr style="border-color: #e5e5ea; margin: 30px 0;">

                    <h3 style="margin-bottom: 5px;">Special / One-Off Duties</h3>
                    <p class="help">Schedule tasks to appear on specific dates. These will appear on the Today's Overview screen.</p>
                    <div id="sc-container"></div>
                    <button type="button" class="action-btn add" onclick="addSpecialChore()">+ Add Special Duty</button>

                </div>
                <script>
                    const existingSpecialChores = <?= json_encode($active_chores, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

                    function addEverydayChore() {
                        const div = document.createElement('div'); div.className = 'flex-row'; div.style.marginBottom = '10px';
                        div.innerHTML = `<input type="text" name="everyday_chores[]" placeholder="Describe everyday task..." style="flex-grow: 1;" required><button type="button" aria-label="Remove" class="delete-btn" onclick="this.parentElement.remove()">Remove</button>`;
                        document.getElementById('everyday-container').appendChild(div);
                    }
                    function addNumberedChore() {
                        const div = document.createElement('div'); div.className = 'item-card flex-row'; div.style.padding = '15px'; div.style.marginBottom = '10px'; div.style.alignItems = 'center';
                        div.innerHTML = `<input type="number" name="chore_ids[]" value="1" style="width: 80px; margin:0;" min="1" required><input type="text" name="chore_names[]" placeholder="Chore Description" style="flex-grow: 1; margin:0;" required><button type="button" aria-label="Remove" class="delete-btn" onclick="this.parentElement.remove()" style="margin:0;">Remove</button>`;
                        document.getElementById('numbered-chores-container').appendChild(div);
                    }
                    function addSpecialChore(data = null) {
                        const id = data && data.id ? data.id : '';
                        const name = data && data.name ? data.name : '';
                        const sd = data && data.start_date ? data.start_date : '<?= $todayStr ?>';
                        const { rec, rInt, rWkDays, rMoType, rMoDate, rMoNth, rMoNthDay, endType, endOccur, endDateBound } = extractRecurrenceState(data);

                        const tmpl = `
                            <button type="button" aria-label="Remove" class="delete-btn" style="position: absolute; right: 20px; top: 20px;" onclick="this.parentElement.remove(); renderChorePreview();">Remove</button>
                            <input type="hidden" class="sc-id" value="${id}">
                            <div class="flex-row" style="width: 85%;">
                                <div class="flex-col" style="flex:2;"><label for="sc_name_${id}">Duty / Chore Name</label><input type="text" id="sc_name_${id}" class="sc-name" value="${name}" required onchange="renderChorePreview()"></div>
                                <div class="flex-col"><label for="sc_sd_${id}">Start Date</label><input type="date" id="sc_sd_${id}" class="sc-sd" value="${sd}" required onchange="renderChorePreview()"></div>
                            </div>
                            <div class="recur-group">
                                <div class="flex-row">
                                    <div class="flex-col" style="max-width: 200px;">
                                        <label>Recurrence</label>
                                        <select class="sc-rec" onchange="toggleRecurUI(this, 'sc'); renderChorePreview();">
                                            <option value="none" ${rec==='none'?'selected':''}>Does not repeat</option>
                                            <option value="days" ${rec==='days'?'selected':''}>Every X Days</option>
                                            <option value="weeks" ${rec==='weeks'?'selected':''}>Every X Weeks</option>
                                            <option value="monthly" ${rec==='monthly'?'selected':''}>Monthly</option>
                                        </select>
                                    </div>
                                    <div class="flex-col r-interval" style="display: ${rec!=='none'?'flex':'none'}; max-width: 150px;">
                                        <label>Repeat every (X)</label>
                                        <div style="display:flex; align-items:center; gap:5px;">
                                            <input type="number" class="sc-rint" value="${rInt}" min="1" style="width: 70px;" onchange="renderChorePreview()">
                                            <span class="r-int-lbl" style="color:#86868b;">days</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="r-weekly-opts" style="display: ${rec==='weekly'?'block':'none'}; margin-top: 10px;">
                                    <label>Repeats On:</label>
                                    <div class="day-checkboxes">
                                        <label for="sc-rwk_0_${id}"><input type="checkbox" id="sc-rwk_0_${id}" value="0" class="sc-rwk" ${rWkDays.includes('0')?'checked':''} onchange="renderChorePreview()"> S</label>
                                        <label for="sc-rwk_1_${id}"><input type="checkbox" id="sc-rwk_1_${id}" value="1" class="sc-rwk" ${rWkDays.includes('1')?'checked':''} onchange="renderChorePreview()"> M</label>
                                        <label for="sc-rwk_2_${id}"><input type="checkbox" id="sc-rwk_2_${id}" value="2" class="sc-rwk" ${rWkDays.includes('2')?'checked':''} onchange="renderChorePreview()"> T</label>
                                        <label for="sc-rwk_3_${id}"><input type="checkbox" id="sc-rwk_3_${id}" value="3" class="sc-rwk" ${rWkDays.includes('3')?'checked':''} onchange="renderChorePreview()"> W</label>
                                        <label for="sc-rwk_4_${id}"><input type="checkbox" id="sc-rwk_4_${id}" value="4" class="sc-rwk" ${rWkDays.includes('4')?'checked':''} onchange="renderChorePreview()"> T</label>
                                        <label for="sc-rwk_5_${id}"><input type="checkbox" id="sc-rwk_5_${id}" value="5" class="sc-rwk" ${rWkDays.includes('5')?'checked':''} onchange="renderChorePreview()"> F</label>
                                        <label for="sc-rwk_6_${id}"><input type="checkbox" id="sc-rwk_6_${id}" value="6" class="sc-rwk" ${rWkDays.includes('6')?'checked':''} onchange="renderChorePreview()"> S</label>
                                    </div>
                                </div>
                                <div class="r-monthly-opts" style="display: ${rec==='monthly'?'block':'none'}; margin-top: 10px;">
                                    <div style="display:flex; align-items:center; gap:15px; margin-bottom: 10px;">
                                        <input type="radio" class="sc-rm-type" name="sc_rm_type_${Math.random()}" value="date" ${rMoType==='date'?'checked':''} onchange="renderChorePreview()">
                                        <span style="display:flex; align-items:center; gap:8px;">Day <input type="number" class="sc-rm-date" value="${rMoDate}" min="1" max="31" style="width:70px;" onchange="renderChorePreview()"> of the month</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:15px;">
                                        <input type="radio" class="sc-rm-type" name="sc_rm_type_${Math.random()}" value="nth" ${rMoType==='nth'?'checked':''} onchange="renderChorePreview()">
                                        <span style="display:flex; align-items:center; gap:8px;">The
                                            <select class="sc-rm-nth" style="width:auto;" onchange="renderChorePreview()">
                                                <option value="1" ${rMoNth==1?'selected':''}>First</option><option value="2" ${rMoNth==2?'selected':''}>Second</option>
                                                <option value="3" ${rMoNth==3?'selected':''}>Third</option><option value="4" ${rMoNth==4?'selected':''}>Fourth</option>
                                            </select>
                                            <select class="sc-rm-nth-day" style="width:auto;" onchange="renderChorePreview()">
                                                <option value="0" ${rMoNthDay==0?'selected':''}>Sunday</option><option value="1" ${rMoNthDay==1?'selected':''}>Monday</option>
                                                <option value="2" ${rMoNthDay==2?'selected':''}>Tuesday</option><option value="3" ${rMoNthDay==3?'selected':''}>Wednesday</option>
                                                <option value="4" ${rMoNthDay==4?'selected':''}>Thursday</option><option value="5" ${rMoNthDay==5?'selected':''}>Friday</option><option value="6" ${rMoNthDay==6?'selected':''}>Saturday</option>
                                            </select>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="end-group r-end-opts" style="display: ${rec!=='none'?'block':'none'};">
                                <label style="margin-bottom: 10px;">Ends:</label>
                                <div style="display:flex; align-items:center; gap:15px; margin-bottom:10px;">
                                    <input type="radio" class="sc-end-type" name="sc_end_type_${Math.random()}" value="occurrences" ${endType==='occurrences'?'checked':''} onchange="renderChorePreview()">
                                    <span style="display:flex; align-items:center; gap:8px;">After <input type="number" class="sc-end-occ" value="${endOccur}" min="1" style="width:80px;" onchange="renderChorePreview()"> occurrences</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:15px;">
                                    <input type="radio" class="sc-end-type" name="sc_end_type_${Math.random()}" value="date" ${endType==='date'?'checked':''} onchange="renderChorePreview()">
                                    <span style="display:flex; align-items:center; gap:8px;">By <input type="date" class="sc-end-bound" value="${endDateBound}" style="width:auto;" onchange="renderChorePreview()"></span>
                                </div>
                            </div>
                        `;
                        const div = document.createElement('div');
                        div.className = 'item-card sc-card';
                        div.innerHTML = tmpl;
                        document.getElementById('sc-container').appendChild(div);
                        renderChorePreview();
                    }

                    let choreMoOffset = 0;
                    function changeChoreMonth(d) { choreMoOffset += d; renderChorePreview(); }

                    function renderChorePreview() {
                        const anchorStr = document.getElementById('c_anchor').value;
                        const numIndices = parseInt(document.getElementById('c_indices').value) || 6;
                        if(!anchorStr) return;

                        const [y, m, d] = anchorStr.split('-');
                        const anchorTime = new Date(Date.UTC(y, m - 1, d, 12, 0, 0)).getTime();

                        const grid = document.getElementById('chorePreviewCal');
                        grid.innerHTML = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d => `<div class="admin-cal-lbl" style="padding: 4px 0; font-size: 0.85em;">${d}</div>`).join('');

                        let now = new Date();
                        let targetMonth = new Date(now.getFullYear(), now.getMonth() + choreMoOffset, 1);
                        document.getElementById('choreMonthTitle').textContent = targetMonth.toLocaleString('default', { month: 'long', year: 'numeric' });

                        let firstDay = new Date(targetMonth);
                        firstDay.setDate(1 - firstDay.getDay());

                        // Parse live SC data for preview
                        let scByDate = {};
                        document.querySelectorAll('.sc-card').forEach(card => {
                            let wks = []; card.querySelectorAll('.sc-rwk:checked').forEach(c => wks.push(c.value));
                            let rmType = card.querySelector('.sc-rm-type:checked');
                            let endType = card.querySelector('.sc-end-type:checked');

                            const name = card.querySelector('.sc-name').value || 'Unnamed Duty';
                            const sDateStr = card.querySelector('.sc-sd').value;
                            if(!sDateStr) return;

                            const [sy, sm, sd] = sDateStr.split('-');
                            const baseDate = new Date(sy, sm-1, sd, 0,0,0);
                            const rec = card.querySelector('.sc-rec').value;

                            let limitDate = new Date(2100,0,1);
                            if (endType && endType.value === 'date' && card.querySelector('.sc-end-bound').value) {
                                const [ly, lm, ld] = card.querySelector('.sc-end-bound').value.split('-');
                                limitDate = new Date(ly, lm-1, ld, 23, 59, 59);
                            }

                            let occurrences = 0;
                            const maxOccurrences = (endType && endType.value === 'occurrences') ? parseInt(card.querySelector('.sc-end-occ').value) || 1 : 9999;

                            let current = new Date(baseDate);
                            const addInst = (dObj) => {
                                const k = `${dObj.getFullYear()}-${String(dObj.getMonth()+1).padStart(2,'0')}-${String(dObj.getDate()).padStart(2,'0')}`;
                                if(!scByDate[k]) scByDate[k] = [];
                                scByDate[k].push(name);
                                occurrences++;
                            };

                            const parseWindow = new Date(firstDay); parseWindow.setDate(parseWindow.getDate() + 45); // Limit parsing overhead

                            if (rec === 'none') {
                                if (current <= limitDate) addInst(current);
                            } else if (rec === 'days') {
                                let interval = parseInt(card.querySelector('.sc-rint').value) || 1;
                                while (current <= limitDate && occurrences < maxOccurrences && current < parseWindow) {
                                    addInst(current);
                                    current.setDate(current.getDate() + interval);
                                }
                            } else if (rec === 'weeks') {
                                let interval = parseInt(card.querySelector('.sc-rint').value) || 1;
                                while (current <= limitDate && occurrences < maxOccurrences && current < parseWindow) {
                                    if (wks.includes(current.getDay().toString())) addInst(current);
                                    current.setDate(current.getDate() + 1);
                                    if (current.getDay() === 0) current.setDate(current.getDate() + (interval - 1) * 7);
                                }
                            }
                            // Simplified monthly parsing for preview performance
                        });

                        for(let i=0; i<35; i++) {
                            let currentDay = new Date(firstDay);
                            currentDay.setDate(firstDay.getDate() + i);

                            const targetTime = new Date(Date.UTC(currentDay.getFullYear(), currentDay.getMonth(), currentDay.getDate(), 12, 0, 0)).getTime();
                            const diffDays = Math.round((targetTime - anchorTime) / 86400000);
                            let choreIndex = (((diffDays % numIndices) + numIndices) % numIndices) + 1;

                            let div = document.createElement('div');
                            div.className = 'admin-cal-day';
                            div.style.minHeight = '50px';
                            div.style.padding = '4px 6px';
                            if(currentDay.getMonth() !== targetMonth.getMonth()) div.style.opacity = '0.4';
                            if(currentDay.toDateString() === now.toDateString()) div.style.border = '2px solid #007aff';

                            const dk = `${currentDay.getFullYear()}-${String(currentDay.getMonth()+1).padStart(2,'0')}-${String(currentDay.getDate()).padStart(2,'0')}`;
                            let pils = scByDate[dk] ? scByDate[dk].map(n => `<div class="cal-evt-pill" style="background:#eef2ff; color:#007aff; border: 1px solid #007aff;">${n}</div>`).join('') : '';

                            div.innerHTML = `<div class="admin-cal-day-header" style="font-size: 0.8em; margin-bottom: 2px;">${currentDay.getDate()}</div><div style="text-align:center; font-weight:700; color:#34c759; font-size: 1.1em; margin-bottom:2px;">${choreIndex}</div>${pils}`;
                            grid.appendChild(div);
                        }
                    }

                    function runPreSubmitHooks() {
                        const form = document.getElementById('mainConfigForm');
                        document.querySelectorAll('.sc-card').forEach(card => {
                            let wks = []; card.querySelectorAll('.sc-rwk:checked').forEach(c => wks.push(c.value));
                            let rmType = card.querySelector('.sc-rm-type:checked');
                            let endType = card.querySelector('.sc-end-type:checked');

                            const data = {
                                id: card.querySelector('.sc-id').value,
                                name: card.querySelector('.sc-name').value,
                                start_date: card.querySelector('.sc-sd').value,
                                recurrence: card.querySelector('.sc-rec').value,
                                recur_interval: card.querySelector('.sc-rint').value,
                                recur_weekdays: wks,
                                recur_month_type: rmType ? rmType.value : 'date',
                                recur_month_date: card.querySelector('.sc-rm-date').value,
                                recur_month_nth: card.querySelector('.sc-rm-nth').value,
                                recur_month_nth_day: card.querySelector('.sc-rm-nth-day').value,
                                end_type: endType ? endType.value : 'occurrences',
                                end_occurrences: card.querySelector('.sc-end-occ').value,
                                end_date_bound: card.querySelector('.sc-end-bound').value,
                            };
                            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'special_chores_json[]'; input.value = JSON.stringify(data);
                            form.appendChild(input);
                        });
                    }

                    window.onload = () => {
                        if (existingSpecialChores.length > 0) { existingSpecialChores.forEach(e => addSpecialChore(e)); }
                        renderChorePreview();
                    };
                </script>

            <?php elseif ($page === 'announcements'): ?>
                <div class="card">
                    <h2>Digital Announcements</h2>
                    <p class="help">Create rich-text announcements to display on the "Today's Overview" screen.</p>
                    <div id="anns-container"></div>
                    <button type="button" class="action-btn add" onclick="addAnn()">+ Add Announcement</button>
                </div>

                <script>
                    const existingAnns = <?= json_encode($active_announcements, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
                    let quillEditors = [];

                    function addAnn(data = null) {
                        const id = data ? data.id : '';
                        const content = data ? data.content : '';
                        const sd = data ? data.start_date : '<?= $todayStr ?>';
                        const ed = data ? data.end_date : '<?= $todayStr ?>';

                        const container = document.getElementById('anns-container');
                        const div = document.createElement('div');
                        div.className = 'ann-card';
                        const editorId = 'editor_' + Math.random().toString(36).substr(2, 9);

                        div.innerHTML = `
                            <button type="button" aria-label="Remove" class="delete-btn" style="position: absolute; right: 20px; top: 20px;" onclick="this.parentElement.remove()">Remove</button>
                            <input type="hidden" class="a-id" value="${id}">
                            <div class="flex-row" style="margin-bottom: 15px; width: 60%;">
                                <div class="flex-col"><label for="a_start_${editorId}">Start Display Date</label><input type="date" id="a_start_${editorId}" class="a-start" value="${sd}" required></div>
                                <div class="flex-col"><label for="a_end_${editorId}">End Display Date</label><input type="date" id="a_end_${editorId}" class="a-end" value="${ed}" required></div>
                            </div>
                            <label>Announcement Content</label>
                            <div id="${editorId}"></div>
                        `;
                        container.appendChild(div);

                        var quill = new Quill('#' + editorId, {
                            theme: 'snow',
                            modules: { toolbar: [ [{ 'header': [1, 2, 3, false] }], ['bold', 'italic', 'underline', 'strike'], [{ 'color': [] }, { 'background': [] }], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean'] ] }
                        });
                        quill.root.innerHTML = content;
                        div.dataset.editorId = editorId;
                        quillEditors[editorId] = quill;
                    }

                    function runPreSubmitHooks() {
                        const form = document.getElementById('mainConfigForm');
                        document.querySelectorAll('.ann-card').forEach(card => {
                            const edId = card.dataset.editorId;
                            const data = {
                                id: card.querySelector('.a-id').value,
                                start_date: card.querySelector('.a-start').value,
                                end_date: card.querySelector('.a-end').value,
                                content: quillEditors[edId].root.innerHTML
                            };
                            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'anns_json[]'; input.value = JSON.stringify(data);
                            form.appendChild(input);
                        });
                    }

                    window.onload = () => {
                        if (existingAnns.length > 0) { existingAnns.forEach(a => addAnn(a)); }
                        else { addAnn(); }
                    };
                </script>

            <?php elseif ($page === 'events'): ?>
                <div class="card">
                    <div class="admin-cal-controls">
                        <button type="button" class="action-btn" style="margin:0;" onclick="goToday()">&#128197; Go To Today</button>
                        <div style="display:flex; align-items:center; gap: 15px;">
                            <button type="button" id="prevBtn" aria-label="Previous date" onclick="changeAdminDate(-1)" class="action-btn">&#10094;</button>
                            <h2 id="adminMonthTitle" style="border:none; padding:0; margin:0; min-width: 200px; text-align:center;"></h2>
                            <button type="button" id="nextBtn" aria-label="Next date" onclick="changeAdminDate(1)" class="action-btn">&#10095;</button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="action-btn" onclick="setCalView('day')" id="btnDay">Day</button>
                            <button type="button" class="action-btn" onclick="setCalView('week')" id="btnWeek">Week</button>
                            <button type="button" class="action-btn" onclick="setCalView('month')" id="btnMonth">Month</button>
                        </div>
                    </div>
                    <div class="admin-cal-grid" id="adminCalLabels"></div>
                    <div class="admin-cal-grid" id="adminCalGrid" style="border-top: none;"></div>
                    <p style="text-align: center; color: var(--muted-text); font-size: 0.9em; margin-top: 10px;">Click any date box to create an event on that day. Employee shifts are overlaid in faint colors for scheduling reference.</p>
                </div>

                <div class="card" id="events-form-area">
                    <h2>Department Events</h2>
                    <p class="help">Build out your calendar. Advanced recurrence options are available.</p>
                    <div id="events-container"></div>
                    <button type="button" class="action-btn add" onclick="addEvent()">+ Add New Event</button>
                </div>

                <script>
                    const existingEvents = <?= $eventsJson ?>;
                    const roomsDatalist = `<?= $roomsJson ?>`;
                    let employeeShifts = {};

                    let calViewMode = 'month';
                    let calAnchorDate = new Date();

                    function setCalView(mode) {
                        calViewMode = mode;
                        document.getElementById('btnDay').style.opacity = mode === 'day' ? '1' : '0.5';
                        document.getElementById('btnWeek').style.opacity = mode === 'week' ? '1' : '0.5';
                        document.getElementById('btnMonth').style.opacity = mode === 'month' ? '1' : '0.5';
                        buildAdminCalendar();
                    }

                    function goToday() { calAnchorDate = new Date(); buildAdminCalendar(); }
                    function changeAdminDate(delta) {
                        if (calViewMode === 'month') calAnchorDate.setMonth(calAnchorDate.getMonth() + delta);
                        if (calViewMode === 'week') calAnchorDate.setDate(calAnchorDate.getDate() + (delta * 7));
                        if (calViewMode === 'day') calAnchorDate.setDate(calAnchorDate.getDate() + delta);
                        buildAdminCalendar();
                    }

                    function parseLocalYMD(dateStr) { const [y, m, d] = dateStr.split('-'); return new Date(y, m - 1, d); }
                    function formatYMD(dateObj) { return `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}-${String(dateObj.getDate()).padStart(2, '0')}`; }

                    function getNthWeekdayOfMonth(year, month, weekday, n) {
                        let d = new Date(year, month, 1);
                        let count = 0;
                        while (d.getMonth() === month) {
                            if (d.getDay() === weekday) {
                                count++;
                                if (count === n) return new Date(d);
                            }
                            d.setDate(d.getDate() + 1);
                        }
                        return null;
                    }

                    async function loadAdminShifts() {
                        const scheduleUrl = `<?= $config['calendar_urls']['main'] ?? 'https://calendar.google.com/calendar/ical/c303c9aa08e0a090db126a0b15eb0bc0e8b66cc1af810aa971059b7b01b6d25a@group.calendar.google.com/public/basic.ics' ?>&nocache=${Date.now()}`;
                        const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(scheduleUrl)}&_cb=${Date.now()}`;
                        try {
                            const response = await fetch(proxyUrl, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }, cache: 'no-store' });
                            if (!response.ok) throw new Error("Network issue");
                            const icsData = await response.text();
                            const jcalData = ICAL.parse(icsData);
                            const comp = new ICAL.Component(jcalData);

                            const parseDate = new Date(); parseDate.setDate(parseDate.getDate() + 90);

                            const timeRegex = /\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?\s*-\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?/gi;

                            const formatTime = (d) => {
                                let h = d.getHours(); let m = d.getMinutes();
                                let ampm = h >= 12 ? 'p' : 'a';
                                h = h % 12; h = h ? h : 12;
                                return `${h}${m===0?'':':'+m.toString().padStart(2,'0')}${ampm}`;
                            };

                            comp.getAllSubcomponents('vevent').forEach(event => {
                                const vevent = new ICAL.Event(event);
                                const summary = (vevent.summary || '').toLowerCase();
                                const isShift = summary.includes('career') || summary.includes('per-diem') || summary.includes('night duty');
                                if(!isShift) return;

                                let typeClass = 'bg-career';
                                let typeCode = 'C';
                                if(summary.includes('per-diem')) { typeClass = 'bg-perdiem'; typeCode = 'P'; }
                                if(summary.includes('night duty')) { typeClass = 'bg-night'; typeCode = 'N'; }

                                const nameClean = (vevent.summary || '').replace(timeRegex, '').replace(/career|per-diem|night duty/ig, '').replace(/-/g, '').trim();

                                const processOccurrence = (occurrence) => {
                                    const dateKey = formatYMD(occurrence.startDate.toJSDate());
                                    if (!employeeShifts[dateKey]) employeeShifts[dateKey] = [];
                                    const timeStr = `${formatTime(occurrence.startDate.toJSDate())}-${formatTime(occurrence.endDate.toJSDate())}`;
                                    employeeShifts[dateKey].push({ class: typeClass, text: `[${typeCode}] ${nameClean} (${timeStr})` });
                                };

                                if (vevent.isRecurring()) {
                                    const iterator = vevent.iterator();
                                    const duration = vevent.duration;
                                    let next;
                                    while ((next = iterator.next()) && next.toJSDate() < parseDate) {
                                        const endDate = next.clone();
                                        endDate.addDuration(duration);
                                        processOccurrence({ startDate: next, endDate: endDate, item: vevent });
                                    }
                                } else {
                                    if (vevent.startDate.toJSDate() < parseDate) { processOccurrence(vevent); }
                                }
                            });
                        } catch (e) { console.error("Could not load shifts for preview.", e); }
                        buildAdminCalendar();
                    }

                    function buildAdminCalendar() {
                        let startDate = new Date(calAnchorDate);
                        let daysToRender = 0;

                        const labelsContainer = document.getElementById('adminCalLabels');
                        const grid = document.getElementById('adminCalGrid');

                        if (calViewMode === 'month') {
                            startDate.setDate(1); startDate.setDate(1 - startDate.getDay());
                            const daysInMonth = new Date(calAnchorDate.getFullYear(), calAnchorDate.getMonth() + 1, 0).getDate();
                            const weeks = Math.ceil((new Date(calAnchorDate.getFullYear(), calAnchorDate.getMonth(), 1).getDay() + daysInMonth) / 7);
                            daysToRender = weeks * 7;
                            document.getElementById('adminMonthTitle').textContent = calAnchorDate.toLocaleString('default', { month: 'long', year: 'numeric' });
                            labelsContainer.style.display = 'grid'; labelsContainer.style.gridTemplateColumns = 'repeat(7, 1fr)';
                            labelsContainer.innerHTML = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d => `<div class="admin-cal-lbl">${d}</div>`).join('');
                            grid.style.gridTemplateColumns = 'repeat(7, 1fr)';
                        }
                        else if (calViewMode === 'week') {
                            startDate.setDate(startDate.getDate() - startDate.getDay());
                            daysToRender = 7;
                            let endWk = new Date(startDate); endWk.setDate(endWk.getDate() + 6);
                            document.getElementById('adminMonthTitle').textContent = `${startDate.toLocaleDateString([], {month:'short', day:'numeric'})} - ${endWk.toLocaleDateString([], {month:'short', day:'numeric', year:'numeric'})}`;
                            labelsContainer.style.display = 'grid'; labelsContainer.style.gridTemplateColumns = 'repeat(7, 1fr)';
                            labelsContainer.innerHTML = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d => `<div class="admin-cal-lbl">${d}</div>`).join('');
                            grid.style.gridTemplateColumns = 'repeat(7, 1fr)';
                        }
                        else if (calViewMode === 'day') {
                            daysToRender = 1;
                            document.getElementById('adminMonthTitle').textContent = startDate.toLocaleDateString([], {weekday:'long', month:'long', day:'numeric', year:'numeric'});
                            labelsContainer.style.display = 'none'; grid.style.gridTemplateColumns = '1fr';
                        }

                        let eventsByDate = {};
                        const endWindow = new Date(startDate);
                        endWindow.setDate(endWindow.getDate() + daysToRender);

                        existingEvents.forEach(evt => {
                            const baseDate = parseLocalYMD(evt.start_date);
                            const limitDate = parseLocalYMD(evt.end_date);
                            limitDate.setHours(23,59,59);

                            const addInst = (dObj) => {
                                const k = formatYMD(dObj);
                                if(!eventsByDate[k]) eventsByDate[k] = [];
                                eventsByDate[k].push(evt.title);
                            };

                            let current = new Date(baseDate);

                            if (evt.recurrence === 'none') {
                                if (current >= startDate && current < endWindow) addInst(current);
                            } else if (evt.recurrence === 'days') {
                                let interval = parseInt(evt.recur_interval) || 1;
                                while (current <= limitDate && current < endWindow) {
                                    if (current >= startDate) addInst(current);
                                    current.setDate(current.getDate() + interval);
                                }
                            } else if (evt.recurrence === 'weeks') {
                                let interval = parseInt(evt.recur_interval) || 1;
                                while (current <= limitDate && current < endWindow) {
                                    if (current >= startDate) {
                                        if (evt.recur_weekdays && evt.recur_weekdays.includes(current.getDay().toString())) {
                                            addInst(current);
                                        }
                                    }
                                    current.setDate(current.getDate() + 1);
                                    if (current.getDay() === 0) current.setDate(current.getDate() + (interval - 1) * 7);
                                }
                            } else if (evt.recurrence === 'monthly_date') {
                                let interval = parseInt(evt.recur_interval) || 1;
                                let targetD = parseInt(evt.recur_month_date) || 1;
                                current.setDate(1);
                                while (current <= limitDate && current < endWindow) {
                                    let tmp = new Date(current.getFullYear(), current.getMonth(), targetD);
                                    if (tmp >= baseDate && tmp <= limitDate && tmp >= startDate && tmp < endWindow) {
                                        addInst(tmp);
                                    }
                                    current.setMonth(current.getMonth() + interval);
                                }
                            } else if (evt.recurrence === 'monthly_nth') {
                                let interval = parseInt(evt.recur_interval) || 1;
                                let targetNth = parseInt(evt.recur_month_nth);
                                let targetDay = parseInt(evt.recur_month_nth_day);
                                current.setDate(1);
                                while (current <= limitDate && current < endWindow) {
                                    let nThDay = getNthWeekdayOfMonth(current.getFullYear(), current.getMonth(), targetDay, targetNth);
                                    if (nThDay && nThDay >= baseDate && nThDay <= limitDate && nThDay >= startDate && nThDay < endWindow) {
                                        addInst(nThDay);
                                    }
                                    current.setMonth(current.getMonth() + interval);
                                }
                            }
                        });

                        grid.innerHTML = '';
                        let renderCurrent = new Date(startDate);
                        for (let i = 0; i < daysToRender; i++) {
                            let div = document.createElement('div');
                            div.className = 'admin-cal-day';
                            if (calViewMode === 'month' && renderCurrent.getMonth() !== calAnchorDate.getMonth()) div.style.opacity = '0.4';
                            if (formatYMD(renderCurrent) === formatYMD(new Date())) div.style.border = '2px solid #007aff';

                            const dateStr = formatYMD(renderCurrent);
                            let headerText = renderCurrent.getDate();
                            if (calViewMode === 'week' || calViewMode === 'day') headerText = renderCurrent.toLocaleDateString([], {weekday:'short', month:'short', day:'numeric'});
                            div.innerHTML = `<div class="admin-cal-day-header">${headerText}</div>`;

                            // Render Employee Shifts as text blocks
                            if (employeeShifts[dateStr]) {
                                let shiftHtml = "";
                                employeeShifts[dateStr].forEach(sh => {
                                    shiftHtml += `<div class="cal-shift-block ${sh.class}" title="${sh.text}">${sh.text}</div>`;
                                });
                                if (shiftHtml) div.insertAdjacentHTML('beforeend', shiftHtml);
                            }

                            // Render Manual Events
                            if (eventsByDate[dateStr]) {
                                let evtHtml = "";
                                eventsByDate[dateStr].forEach(t => {
                                    evtHtml += `<div class="cal-evt-pill">${t}</div>`;
                                });
                                if (evtHtml) div.insertAdjacentHTML('beforeend', evtHtml);
                            }

                            div.onclick = () => {
                                addEvent({start_date: dateStr, end_date: dateStr});
                                document.getElementById('events-form-area').scrollIntoView({ behavior: 'smooth' });
                            };

                            grid.appendChild(div);
                            renderCurrent.setDate(renderCurrent.getDate() + 1);
                        }
                    }

                    function toggleEvtType(sel) {
                        const card = sel.closest('.event-card');
                        const locWrap = card.querySelector('.e-loc-wrap');
                        const locLbl = card.querySelector('.e-loc-lbl');

                        if (sel.value === 'Room Rental') {
                            locWrap.style.display = 'flex';
                            locLbl.textContent = 'Select/Enter Room';
                        } else if (sel.value === 'Fire Prevention' || sel.value === 'Community Event') {
                            locWrap.style.display = 'flex';
                            locLbl.textContent = 'Location / Address';
                        } else {
                            locWrap.style.display = 'none';
                            card.querySelector('.e-loc-input').value = '';
                        }
                    }

                    function addEvent(data = null) {
                        const id = data && data.id ? data.id : '';
                        const title = data && data.title ? data.title : '';
                        const eType = data && data.event_type ? data.event_type : 'Training';
                        const loc = data && data.location ? data.location : '';
                        const sd = data && data.start_date ? data.start_date : '<?= $todayStr ?>';
                        const ed = data && data.end_date ? data.end_date : '<?= $todayStr ?>';
                        const ad = data && data.all_day !== undefined ? data.all_day : true;
                        const st = data && data.start_time ? data.start_time : '';
                        const et = data && data.end_time ? data.end_time : '';

                        const { rec, rInt, rWkDays, rMoType, rMoDate, rMoNth, rMoNthDay, endType, endOccur, endDateBound } = extractRecurrenceState(data);

                        const tmpl = `
                            <button type="button" aria-label="Remove Event" class="delete-btn" style="position: absolute; right: 20px; top: 20px;" onclick="this.parentElement.remove()">Remove Event</button>
                            <input type="hidden" class="e-id" value="${id}">

                            <div class="flex-row" style="margin-bottom: 15px; width: 85%;">
                                <div class="flex-col" style="flex: 2;"><label for="e_title_${id}">Event Title</label><input type="text" id="e_title_${id}" class="e-title" value="${title}" required></div>
                                <div class="flex-col">
                                    <label for="e_type_${id}">Event Type</label>
                                    <select id="e_type_${id}" class="e-type" onchange="toggleEvtType(this)">
                                        <option value="Training" ${eType=='Training'?'selected':''}>Training</option>
                                        <option value="Room Rental" ${eType=='Room Rental'?'selected':''}>Room Rental</option>
                                        <option value="Fire Prevention" ${eType=='Fire Prevention'?'selected':''}>Fire Prevention</option>
                                        <option value="Community Event" ${eType=='Community Event'?'selected':''}>Community Event</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex-row e-loc-wrap" style="display: ${eType==='Training' ? 'none' : 'flex'}; margin-bottom: 15px; width: 85%;">
                                <div class="flex-col">
                                    <label class="e-loc-lbl">${eType==='Room Rental'?'Select/Enter Room':'Location'}</label>
                                    <input type="text" class="e-loc-input" list="room-list" value="${loc}">
                                </div>
                            </div>

                            <div class="flex-row" style="margin-bottom: 15px; width: 85%;">
                                <div class="flex-col"><label for="e_sd_${id}">Start Date</label><input type="date" id="e_sd_${id}" class="e-sd" value="${sd}" required onchange="syncDates(this)"></div>
                                <div class="flex-col"><label for="e_ed_${id}">End Date (of first instance)</label><input type="date" id="e_ed_${id}" class="e-ed" value="${ed}" required></div>
                            </div>

                            <div class="flex-row" style="margin-bottom: 15px;">
                                <label style="display:flex; align-items:center; cursor:pointer; color:#1d1d1f; font-size:1em; font-weight:600;">
                                    <input type="checkbox" class="e-allday" ${ad ? 'checked' : ''} onchange="toggleTime(this)"> All Day Event
                                </label>
                                <div class="flex-col time-inputs" style="display: ${ad ? 'none' : 'flex'}; flex-direction:row; gap:15px;">
                                    <div class="flex-col"><label for="e_st_${id}">Start Time</label><input type="time" id="e_st_${id}" class="e-st" value="${st}"></div>
                                    <div class="flex-col"><label for="e_et_${id}">End Time</label><input type="time" id="e_et_${id}" class="e-et" value="${et}"></div>
                                </div>
                            </div>

                            <hr>
                            <div class="recur-group">
                                <div class="flex-row">
                                    <div class="flex-col" style="max-width: 200px;">
                                        <label>Recurrence</label>
                                        <select class="e-rec" onchange="toggleRecurUI(this, 'e')">
                                            <option value="none" ${rec==='none'?'selected':''}>Does not repeat</option>
                                            <option value="daily" ${rec==='daily'?'selected':''}>Daily</option>
                                            <option value="weekly" ${rec==='weekly'?'selected':''}>Weekly</option>
                                            <option value="monthly" ${rec==='monthly'?'selected':''}>Monthly</option>
                                        </select>
                                    </div>
                                    <div class="flex-col r-interval" style="display: ${rec!=='none'?'flex':'none'}; max-width: 150px;">
                                        <label>Repeat every (X)</label>
                                        <div style="display:flex; align-items:center; gap:5px;">
                                            <input type="number" class="e-rint" value="${rInt}" min="1" style="width: 70px;">
                                            <span class="r-int-lbl" style="color:#86868b;">days</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="r-weekly-opts" style="display: ${rec==='weekly'?'block':'none'}; margin-top: 15px;">
                                    <label>Repeats On:</label>
                                    <div class="day-checkboxes">
                                        <label for="e-rwk_0_${id}"><input type="checkbox" id="e-rwk_0_${id}" value="0" class="e-rwk" ${rWkDays.includes('0')?'checked':''}> S</label>
                                        <label for="e-rwk_1_${id}"><input type="checkbox" id="e-rwk_1_${id}" value="1" class="e-rwk" ${rWkDays.includes('1')?'checked':''}> M</label>
                                        <label for="e-rwk_2_${id}"><input type="checkbox" id="e-rwk_2_${id}" value="2" class="e-rwk" ${rWkDays.includes('2')?'checked':''}> T</label>
                                        <label for="e-rwk_3_${id}"><input type="checkbox" id="e-rwk_3_${id}" value="3" class="e-rwk" ${rWkDays.includes('3')?'checked':''}> W</label>
                                        <label for="e-rwk_4_${id}"><input type="checkbox" id="e-rwk_4_${id}" value="4" class="e-rwk" ${rWkDays.includes('4')?'checked':''}> T</label>
                                        <label for="e-rwk_5_${id}"><input type="checkbox" id="e-rwk_5_${id}" value="5" class="e-rwk" ${rWkDays.includes('5')?'checked':''}> F</label>
                                        <label for="e-rwk_6_${id}"><input type="checkbox" id="e-rwk_6_${id}" value="6" class="e-rwk" ${rWkDays.includes('6')?'checked':''}> S</label>
                                    </div>
                                </div>

                                <div class="r-monthly-opts" style="display: ${rec==='monthly'?'block':'none'}; margin-top: 15px;">
                                    <div style="display:flex; align-items:center; gap:15px; margin-bottom: 10px;">
                                        <input type="radio" class="e-rm-type" name="rm_type_${Math.random()}" value="date" ${rMoType==='date'?'checked':''}>
                                        <span style="display:flex; align-items:center; gap:8px;">Day <input type="number" class="e-rm-date" value="${rMoDate}" min="1" max="31" style="width:70px;"> of the month</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:15px;">
                                        <input type="radio" class="e-rm-type" name="rm_type_${Math.random()}" value="nth" ${rMoType==='nth'?'checked':''}>
                                        <span style="display:flex; align-items:center; gap:8px;">The
                                            <select class="e-rm-nth" style="width:auto;">
                                                <option value="1" ${rMoNth==1?'selected':''}>First</option><option value="2" ${rMoNth==2?'selected':''}>Second</option>
                                                <option value="3" ${rMoNth==3?'selected':''}>Third</option><option value="4" ${rMoNth==4?'selected':''}>Fourth</option>
                                            </select>
                                            <select class="e-rm-nth-day" style="width:auto;">
                                                <option value="0" ${rMoNthDay==0?'selected':''}>Sunday</option><option value="1" ${rMoNthDay==1?'selected':''}>Monday</option>
                                                <option value="2" ${rMoNthDay==2?'selected':''}>Tuesday</option><option value="3" ${rMoNthDay==3?'selected':''}>Wednesday</option>
                                                <option value="4" ${rMoNthDay==4?'selected':''}>Thursday</option><option value="5" ${rMoNthDay==5?'selected':''}>Friday</option><option value="6" ${rMoNthDay==6?'selected':''}>Saturday</option>
                                            </select>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="end-group r-end-opts" style="display: ${rec!=='none'?'block':'none'};">
                                <label style="margin-bottom: 10px;">Ends:</label>
                                <div style="display:flex; align-items:center; gap:15px; margin-bottom:10px;">
                                    <input type="radio" class="e-end-type" name="end_type_${Math.random()}" value="occurrences" ${endType==='occurrences'?'checked':''}>
                                    <span style="display:flex; align-items:center; gap:8px;">After <input type="number" class="e-end-occ" value="${endOccur}" min="1" style="width:80px;"> occurrences</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:15px;">
                                    <input type="radio" class="e-end-type" name="end_type_${Math.random()}" value="date" ${endType==='date'?'checked':''}>
                                    <span style="display:flex; align-items:center; gap:8px;">By <input type="date" class="e-end-bound" value="${endDateBound}" style="width:auto;"></span>
                                </div>
                            </div>
                        `;
                        const div = document.createElement('div');
                        div.className = 'event-card';
                        div.innerHTML = tmpl;
                        document.getElementById('events-container').appendChild(div);
                    }

                    function syncDates(el) {
                        const card = el.closest('.event-card');
                        card.querySelector('.e-ed').value = el.value;
                    }

                    function toggleTime(cb) {
                        const card = cb.closest('.event-card');
                        card.querySelector('.time-inputs').style.display = cb.checked ? 'none' : 'flex';
                    }

                    function runPreSubmitHooks() {
                        const form = document.getElementById('mainConfigForm');
                        document.querySelectorAll('.event-card').forEach(card => {
                            let wks = [];
                            card.querySelectorAll('.e-rwk:checked').forEach(c => wks.push(c.value));
                            let rmType = card.querySelector('.e-rm-type:checked');
                            let endType = card.querySelector('.e-end-type:checked');

                            const data = {
                                id: card.querySelector('.e-id').value,
                                title: card.querySelector('.e-title').value,
                                event_type: card.querySelector('.e-type').value,
                                location: card.querySelector('.e-loc-input').value,
                                start_date: card.querySelector('.e-sd').value,
                                end_date: card.querySelector('.e-ed').value,
                                all_day: card.querySelector('.e-allday').checked,
                                start_time: card.querySelector('.e-st').value,
                                end_time: card.querySelector('.e-et').value,

                                recurrence: card.querySelector('.e-rec').value,
                                recur_interval: card.querySelector('.e-rint').value,
                                recur_weekdays: wks,
                                recur_month_type: rmType ? rmType.value : 'date',
                                recur_month_date: card.querySelector('.e-rm-date').value,
                                recur_month_nth: card.querySelector('.e-rm-nth').value,
                                recur_month_nth_day: card.querySelector('.e-rm-nth-day').value,

                                end_type: endType ? endType.value : 'occurrences',
                                end_occurrences: card.querySelector('.e-end-occ').value,
                                end_date_bound: card.querySelector('.e-end-bound').value,
                            };
                            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'events_json[]'; input.value = JSON.stringify(data);
                            form.appendChild(input);
                        });
                    }

                    window.onload = () => {
                        setCalView('month');
                        loadAdminShifts();
                        if (existingEvents.length > 0) { existingEvents.forEach(e => addEvent(e)); }
                        else { addEvent(); }
                    };
                </script>

            <?php elseif ($page === 'archived_chores'): ?>
                <div class="card">
                    <h2>Archived Special Duties</h2>
                    <p class="help">Special duties whose End Date has passed automatically appear here.</p>
                    <?php if (empty($archived_chores)): ?>
                        <p style="color: var(--muted-text); font-style: italic;">No archived duties.</p>
                    <?php else: ?>
                        <?php foreach($archived_chores as $evt): ?>
                            <div class="item-card flex-row" style="align-items: center; justify-content: space-between;">
                                <div>
                                    <h3 style="margin:0 0 5px 0; color: var(--text-color);"><?= htmlspecialchars($evt['name']) ?></h3>
                                    <div style="font-size: 0.9em; color: var(--muted-text);">Start: <?= $evt['start_date'] ?></div>
                                </div>
                                <button type="submit" form="delFormChore_<?= $evt['id'] ?>" name="delete_archived_chore" class="delete-btn" onclick="return confirm('Permanently delete this duty?');">Permanently Delete</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <script>function runPreSubmitHooks() {}</script>

            <?php elseif ($page === 'archived_events'): ?>
                <div class="card">
                    <h2>Archived Events</h2>
                    <p class="help">Events whose End Date has passed automatically appear here.</p>
                    <?php if (empty($archived_events)): ?>
                        <p style="color: var(--muted-text); font-style: italic;">No archived events.</p>
                    <?php else: ?>
                        <?php foreach($archived_events as $evt): ?>
                            <div class="item-card flex-row" style="align-items: center; justify-content: space-between;">
                                <div>
                                    <h3 style="margin:0 0 5px 0; color: var(--text-color);"><?= htmlspecialchars($evt['title']) ?></h3>
                                    <div style="font-size: 0.9em; color: var(--muted-text);">Start: <?= $evt['start_date'] ?></div>
                                </div>
                                <button type="submit" form="delFormEvt_<?= $evt['id'] ?>" name="delete_archived_event" class="delete-btn" onclick="return confirm('Permanently delete this event?');">Permanently Delete</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <script>function runPreSubmitHooks() {}</script>

            <?php elseif ($page === 'archived_anns'): ?>
                <div class="card">
                    <h2>Archived Announcements</h2>
                    <p class="help">Announcements whose End Date has passed automatically appear here.</p>
                    <?php if (empty($archived_announcements)): ?>
                        <p style="color: var(--muted-text); font-style: italic;">No archived announcements.</p>
                    <?php else: ?>
                        <?php foreach($archived_announcements as $ann): ?>
                            <div class="item-card flex-row" style="align-items: center; justify-content: space-between;">
                                <div style="flex-grow: 1; max-width: 70%; max-height: 60px; overflow: hidden;">
                                    <div style="font-size: 0.9em; color: var(--muted-text); margin-bottom: 5px; font-weight: bold;">Ran: <?= $ann['start_date'] ?> to <?= $ann['end_date'] ?></div>
                                    <div style="color: var(--muted-text); font-size: 0.9em;"><?= strip_tags($ann['content']) ?></div>
                                </div>
                                <button type="submit" form="delFormAnn_<?= $ann['id'] ?>" name="delete_archived_ann" class="delete-btn" onclick="return confirm('Permanently delete this announcement?');">Permanently Delete</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <script>function runPreSubmitHooks() {}</script>

            <?php elseif ($page === 'api_integrations'): ?>
                <div class="card fade-in">
                    <h1>API Integrations</h1>
                    <p style="color: var(--muted-text); margin-top: -10px; margin-bottom: 30px;">Manage third-party API keys and services used by the Fire Display dashboard.</p>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="input-group">
                            <label for="gemini_api_key">Gemini API Key (for Geocoding fallback)</label>
                            <input type="text" id="gemini_api_key" name="gemini_api_key" value="<?= htmlspecialchars($configData['api_integrations']['gemini_api_key'] ?? '') ?>" placeholder="AIzaSy..." autocomplete="off">
                            <p style="font-size: 0.85em; color: var(--muted-text); margin-top: 5px;">This key is used as a fallback to convert free-text burn permit locations into map coordinates when standard geocoding fails.</p>
                        </div>

                        <div class="input-group" style="margin-top: 15px;">
                            <label for="google_tts_api_key">Google TTS API Key (for Text-to-Speech)</label>
                            <input type="text" id="google_tts_api_key" name="google_tts_api_key" value="<?= htmlspecialchars($configData['api_integrations']['google_tts_api_key'] ?? '') ?>" placeholder="AIzaSy..." autocomplete="off">
                            <p style="font-size: 0.85em; color: var(--muted-text); margin-top: 5px;">This key is used for the Text-to-Speech integration in the dashboard.</p>
                        </div>

                        <button type="submit" name="save_api_integration" class="save-btn" style="padding: 15px 40px; margin-top: 20px;">💾 Save API Integrations</button>
                    </form>
                </div>

            <?php elseif ($page === 'email_integration'): ?>
                <div class="content-header">
                    <h1>Email Integration Settings</h1>
                </div>

                <div class="card">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="form-group">
                            <label for="danger_address">Fire Danger Email Address / Prefix</label>
                            <input type="text" id="danger_address" name="danger_address" value="<?= htmlspecialchars($configData['email_integration']['danger_address'] ?? '') ?>" placeholder="e.g. danger@yourdomain.com" autocomplete="off">
                            <small style="display:block; color:var(--muted-text); margin-top:5px;">If not set, falls back to ICS feed if provided, else "Unknown".</small>
                        </div>
                        <div class="form-group" style="margin-top: 15px;">
                            <label for="permit_address">Burn Permits Email Address / Prefix</label>
                            <input type="text" id="permit_address" name="permit_address" value="<?= htmlspecialchars($configData['email_integration']['permit_address'] ?? '') ?>" placeholder="e.g. permits@yourdomain.com" autocomplete="off">
                            <small style="display:block; color:var(--muted-text); margin-top:5px;">If not set, falls back to ICS feed if provided.</small>
                        </div>

                        <button type="submit" name="save_email_integration" class="save-btn" style="padding: 15px 40px; margin-top: 20px;">💾 Save Email Settings</button>
                    </form>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h3>Setup Instructions</h3>
                    <div style="background: var(--bg-color); padding: 15px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 0.9em; line-height: 1.5; color: var(--text-color);">
                        <p>To configure your email addresses to trigger the new <code>process_email.php</code> script on Bluehost, follow these step-by-step instructions:</p>

                        <h4>Step 1: Ensure Script Permissions</h4>
                        <p>First, ensure that the PHP script has the correct permissions so the mail server can execute it.<br>
                        Using the File Manager in cPanel or your FTP client, navigate to <code>api/process_email.php</code> and set its permissions to <code>755</code> (Read, Write, Execute for Owner; Read, Execute for Group and Public).<br>
                        <em>Note: I have already set this permission in the codebase, but verify it on your live server.</em></p>

                        <h4>Step 2: Ensure Proper Shebang</h4>
                        <p>The first line of <code>process_email.php</code> needs to tell the server where PHP is located.<br>
                        In cPanel, it's typically:<br>
                        <code>#!/usr/local/bin/php -q</code><br>
                        <em>Note: I have already updated the script to include this as the very first line.</em></p>

                        <h4>Step 3: Set up Email Forwarders (Piping) in Bluehost cPanel</h4>
                        <p>You need to create a "Forwarder" for each email address you want to use (e.g., <code><?= htmlspecialchars($configData['email_integration']['danger_address'] ?: 'danger@yourdomain.com') ?></code> and <code><?= htmlspecialchars($configData['email_integration']['permit_address'] ?: 'permits@yourdomain.com') ?></code>).</p>

                        <ol>
                            <li>Log into your <strong>Bluehost cPanel</strong>.</li>
                            <li>Scroll down to the <strong>Email</strong> section and click on <strong>Forwarders</strong>.</li>
                            <li>Click the <strong>Add Forwarder</strong> button.</li>
                            <li><strong>Address to Forward:</strong>
                                <ul>
                                    <li>Enter the prefix for the email address (e.g., <code><?= htmlspecialchars(explode('@', $configData['email_integration']['danger_address'] ?: 'danger')[0]) ?></code>).</li>
                                    <li>Select your domain from the drop-down menu.</li>
                                </ul>
                            </li>
                            <li><strong>Destination:</strong>
                                <ul>
                                    <li>Click on <strong>Advanced Options</strong> to expand the section.</li>
                                    <li>Select the option: <strong>Pipe to a Program</strong>.</li>
                                    <li>In the text box next to it, enter the path to your script relative to your home directory.
                                        <ul>
                                            <li><em>Example:</em> If your application is in the root <code>public_html</code> folder, the path would be: <code>public_html/api/process_email.php</code></li>
                                            <li><em>Note: Do not include a leading slash (<code>/</code>).</em></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                            <li>Click <strong>Add Forwarder</strong>.</li>
                            <li>Repeat steps 3-6 for your second email address (e.g., <code><?= htmlspecialchars($configData['email_integration']['permit_address'] ?: 'permits@yourdomain.com') ?></code>), pointing it to the exact same script: <code>public_html/api/process_email.php</code>.</li>
                        </ol>

                        <h4>How It Works:</h4>
                        <p>Now, whenever an email is sent to <code><?= htmlspecialchars($configData['email_integration']['danger_address'] ?: 'danger@yourdomain.com') ?></code> or <code><?= htmlspecialchars($configData['email_integration']['permit_address'] ?: 'permits@yourdomain.com') ?></code>, Bluehost's mail server will automatically execute <code>public_html/api/process_email.php</code> and pass the entire contents of the email to the script. The script checks who the email was addressed to (or the subject) and updates the corresponding JSON file.</p>
                    </div>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h3>Email Extraction Testing Tool</h3>
                    <p style="color: var(--muted-text); font-size: 0.9em; margin-bottom: 15px;">Paste the raw text of an email (including headers like <code>To:</code> and <code>Subject:</code>) into the box below to test how the system parses it. <strong>This will not affect live data.</strong></p>
                    <div class="form-group">
                        <textarea id="test_email_content" rows="10" style="font-family: Inter, monospace; font-size: 0.85em; background: var(--bg-color); color: var(--text-color); width: 100%; border: 1px solid var(--border-color); padding: 10px; border-radius: 4px;"></textarea>
                    </div>
                    <button type="button" class="btn btn-secondary" style="margin-top: 10px; padding: 10px 20px; font-weight: bold; background-color: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer;" onclick="testEmailExtraction()">Test Extraction</button>

                    <div id="test_email_results" style="margin-top: 20px; padding: 15px; border-radius: 6px; background: #f8f9fa; border: 1px solid #dee2e6; display: none;">
                        <h4 style="margin-top: 0; border-bottom: 1px solid #dee2e6; padding-bottom: 8px; margin-bottom: 12px; color: #495057;">Extraction Results</h4>
                        <div id="test_email_results_content" style="color: #212529;"></div>
                    </div>

                <!-- System Logs Section -->
                <div id="system_logs" class="tab-content <?= $page === 'system_logs' ? 'active' : '' ?>">
                    <h2>System Logs</h2>
                    <p class="help">View system events, errors, and integration usage. Logs rotate automatically at 5MB.</p>

                    <div class="card" style="margin-bottom: 20px;">
                        <div class="flex-row" style="align-items: center; justify-content: space-between;">
                            <div class="flex-row" style="gap: 15px;">
                                <select id="logComponentFilter" onchange="filterLogs()">
                                    <option value="all">All Components</option>
                                    <option value="Email">Email</option>
                                    <option value="ICS">ICS</option>
                                    <option value="Gemini">Gemini</option>
                                </select>
                                <select id="logStatusFilter" onchange="filterLogs()">
                                    <option value="all">All Statuses</option>
                                    <option value="info">Info</option>
                                    <option value="success">Success</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                </select>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="action" value="clear_logs">
                                <button type="submit" class="btn btn-remove" onclick="return confirm('Are you sure you want to clear all logs?');">Clear Logs</button>
                            </form>
                        </div>
                    </div>

                    <div class="card" style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th style="padding: 10px;">Timestamp</th>
                                    <th style="padding: 10px;">Component</th>
                                    <th style="padding: 10px;">Status</th>
                                    <th style="padding: 10px;">Message</th>
                                    <th style="padding: 10px;">Details</th>
                                </tr>
                            </thead>
                            <tbody id="logTableBody">
                                <?php
                                $logFile = __DIR__ . '/data/system.log';
                                if (file_exists($logFile)) {
                                    $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                                    if (!empty($logs)) {
                                        $logs = array_reverse($logs); // Show newest first
                                        foreach ($logs as $line) {
                                            $log = json_decode($line, true);
                                            if ($log) {
                                                $statusColor = 'inherit';
                                                if ($log['status'] === 'error') $statusColor = 'var(--danger-color)';
                                                if ($log['status'] === 'warning') $statusColor = 'var(--warning-color)';
                                                if ($log['status'] === 'success') $statusColor = 'var(--success-color)';

                                                echo "<tr class='log-row' data-component='".htmlspecialchars($log['component'])."' data-status='".htmlspecialchars($log['status'])."' style='border-bottom: 1px solid var(--border-color);'>";
                                                echo "<td style='padding: 10px; white-space: nowrap;'>" . htmlspecialchars($log['timestamp']) . "</td>";
                                                echo "<td style='padding: 10px; font-weight: bold;'>" . htmlspecialchars($log['component']) . "</td>";
                                                echo "<td style='padding: 10px; color: {$statusColor}; font-weight: bold;'>" . ucfirst(htmlspecialchars($log['status'])) . "</td>";
                                                echo "<td style='padding: 10px;'>" . htmlspecialchars($log['message']) . "</td>";
                                                echo "<td style='padding: 10px; font-size: 0.9em; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;' title='" . htmlspecialchars(json_encode($log['details']), ENT_QUOTES) . "'>" . htmlspecialchars(json_encode($log['details']), ENT_QUOTES) . "</td>";
                                                echo "</tr>";
                                            }
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' style='padding: 10px; text-align: center;'>No logs found.</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' style='padding: 10px; text-align: center;'>Log file does not exist yet.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <script>
                function filterLogs() {
                    const compFilter = document.getElementById('logComponentFilter').value.toLowerCase();
                    const statFilter = document.getElementById('logStatusFilter').value.toLowerCase();
                    const rows = document.querySelectorAll('.log-row');

                    rows.forEach(row => {
                        const comp = row.getAttribute('data-component').toLowerCase();
                        const stat = row.getAttribute('data-status').toLowerCase();

                        const compMatch = (compFilter === 'all' || comp === compFilter);
                        const statMatch = (statFilter === 'all' || stat === statFilter);

                        if (compMatch && statMatch) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }
                </script>

                </div>
                <script>
                    function runPreSubmitHooks() {}

                    async function testEmailExtraction() {
                        const content = document.getElementById('test_email_content').value;
                        const resultsDiv = document.getElementById('test_email_results');
                        const resultsContent = document.getElementById('test_email_results_content');

                        if (!content.trim()) {
                            alert("Please enter some email content to test.");
                            return;
                        }

                        resultsDiv.style.display = 'block';
                        resultsContent.innerHTML = '<em>Testing extraction...</em>';

                        try {
                            const response = await fetch('api/process_email.php?test=true&token=' + encodeURIComponent(document.querySelector('input[name="dashboard_token"]') ? document.querySelector('input[name="dashboard_token"]').value : ''), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'text/plain'
                                },
                                body: content
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            const rawText = await response.text();
                            const jsonStart = rawText.indexOf("{");
                            let result;
                            if (jsonStart !== -1) {
                                result = JSON.parse(rawText.substring(jsonStart));
                            } else {
                                throw new Error("Could not find valid JSON in response: " + rawText);
                            }
                            if (result.type === 'danger') {
                                resultsContent.innerHTML = `<div style="font-size: 1.1em;">Danger Level: <strong>${result.data.level}</strong></div><div style="font-size: 0.85em; color: #6c757d; margin-top: 5px;">(Matched rule: ${result.match_reason || 'Unknown'})</div>`;
                            } else if (result.type === 'permit') {
                                resultsContent.innerHTML = `<div style="font-size: 1.1em;">Extracted Permit:</div>
                                    <ul style="margin-top: 5px; padding-left: 20px;">
                                        <li><strong>Address:</strong> ${result.data.address}</li>
                                        <li><strong>Type:</strong> ${result.data.type}</li>
                                        <li><strong>Expires:</strong> ${new Date(result.data.expires).toLocaleString()}</li>
                                    </ul>`;
                            } else {
                                resultsContent.innerHTML = `<div style="color: #dc3545;"><strong>No match.</strong> The email did not match either the Fire Danger or Burn Permit routing logic.</div>`;
                            }

                        } catch (e) {
                            resultsContent.innerHTML = `<div style="color: #dc3545;"><strong>Error during extraction:</strong> ${e.message}</div>`;
                        }
                    }
                </script>
            <?php elseif ($page === 'password'): ?>
                <div class="card">
                    <h2>Change Admin Password</h2>
                    <div style="max-width: 400px;">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" style="margin-bottom: 15px;" required>
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" style="margin-bottom: 15px;" required>
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" style="margin-bottom: 20px;" required>
                    </div>
                </div>
                <script>function runPreSubmitHooks() {}</script>
            <?php endif; ?>

            <div style="display:flex; justify-content: flex-end;">
                <button type="submit" name="save_<?= htmlspecialchars(explode('_', $page)[0]) ?>" class="save-btn" style="padding: 15px 40px; margin-bottom: 50px;">💾 Save Changes</button>
            </div>
        </form>

        <?php if ($page === 'archived_chores'): ?>
            <?php foreach($archived_chores as $evt): ?>
                <form id="delFormChore_<?= $evt['id'] ?>" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="delete_id" value="<?= $evt['id'] ?>"></form>
            <?php endforeach; ?>
        <?php elseif ($page === 'archived_events'): ?>
            <?php foreach($archived_events as $evt): ?>
                <form id="delFormEvt_<?= $evt['id'] ?>" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="delete_id" value="<?= $evt['id'] ?>"></form>
            <?php endforeach; ?>
        <?php elseif ($page === 'archived_anns'): ?>
            <?php foreach($archived_announcements as $ann): ?>
                <form id="delFormAnn_<?= $ann['id'] ?>" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="delete_id" value="<?= $ann['id'] ?>"></form>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</body>
</html>
