<?php
function verify_dashboard_token() {
    $configFile = __DIR__ . '/../config.json';
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        $dashboardToken = $config['dashboard_token'] ?? '';
        if (!empty($dashboardToken)) {
            $providedToken = $_GET['token'] ?? '';
            if ($providedToken !== $dashboardToken) {
                http_response_code(403);
                echo json_encode(['error' => 'Access Denied']);
                die();
            }
        }
    }
}
?>
