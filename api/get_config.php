<?php
header('Content-Type: application/json');
$file = '../config.json';
if (file_exists($file)) {
    // Read the file, decode it, remove the password, re-encode it
    $data = json_decode(file_get_contents($file), true);
    unset($data['admin_password']); 
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'Config file missing']);
}
?>