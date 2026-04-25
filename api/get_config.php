<?php
header('Content-Type: application/json');
$file = '../config.json';
if (file_exists($file)) {
    // Read the file, decode it, remove the password, re-encode it
    $data = json_decode(file_get_contents($file), true);

    // Check dashboard token if configured
    $dashboardToken = $data['dashboard_token'] ?? '';
    if (!empty($dashboardToken)) {
        $providedToken = $_GET['token'] ?? '';
        if ($providedToken !== $dashboardToken) {
            http_response_code(403);
            echo json_encode(['error' => 'Access Denied']);
            exit;
        }
    }

    unset($data['admin_password']);
    unset($data['api_integrations']['gemini_api_key']);
    unset($data['api_integrations']['google_tts_api_key']);
    unset($data['dashboard_token']);
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'Config file missing']);
}
?>